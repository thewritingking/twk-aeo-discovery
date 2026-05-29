<?php
/**
 * Setup wizard: a guided, branching entity-authority interview.
 *
 * Writes to the same twkd_settings option as the Entity Authority tab, but
 * non-destructively: re-running pre-fills every field, Save-and-continue only
 * writes fields the user actually filled, Skip writes nothing, and the only way
 * to erase a value is the explicit per-field Clear checkbox. Whether the user
 * has a given identifier "yet" is tracked separately from the data, so the
 * post-wizard report can tell "you have an ORCID" from "you still need one".
 *
 * @package TWK_AEO_Discovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Wizard
 */
class TWKD_Wizard {

	const STATE_OPTION = 'twkd_wizard_state';
	const PAGE_SLUG    = 'twk-aeo-discovery-setup';

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_Wizard|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return TWKD_Wizard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_twkd_wizard_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_twkd_wizard_dismiss', array( $this, 'handle_dismiss' ) );
		add_action( 'admin_init', array( $this, 'maybe_first_run_redirect' ) );
		add_action( 'admin_notices', array( $this, 'maybe_banner' ) );
	}

	/**
	 * Register the wizard as a hidden admin page (URL but no menu item).
	 */
	public function register_page() {
		add_submenu_page(
			'',
			__( 'AEO Setup', 'twk-aeo-discovery' ),
			__( 'AEO Setup', 'twk-aeo-discovery' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The wizard URL, optionally for a given step.
	 *
	 * @param int $step Step index.
	 * @return string
	 */
	public function url( $step = 0 ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'step' => (int) $step,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Wizard state (progress, site type, "don't have" flags, dismissal).
	 *
	 * @return array
	 */
	public function state() {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		return wp_parse_args(
			$state,
			array(
				'completed'  => false,
				'dismissed'  => false,
				'site_type'  => 'both',
				'dont_have'  => array(),
			)
		);
	}

	/**
	 * Persist a partial state update.
	 *
	 * @param array $changes Keys to merge into state.
	 */
	private function update_state( $changes ) {
		$state = array_merge( $this->state(), $changes );
		update_option( self::STATE_OPTION, $state, false );
	}

	/* --------------------------------------------------------------------- *
	 * Identifier registry — the single source of truth shared by the wizard
	 * step and the post-wizard report. Keep keys stable; the report keys its
	 * instructions off them.
	 * --------------------------------------------------------------------- */

	/**
	 * Curated high-value identifiers, by scope.
	 *
	 * @param string $scope 'person' or 'org'.
	 * @return array[] Each: key, label, example.
	 */
	public static function identifiers( $scope ) {
		$person = array(
			array( 'key' => 'orcid',          'label' => 'ORCID',          'example' => 'https://orcid.org/0000-0002-1825-0097' ),
			array( 'key' => 'isni',           'label' => 'ISNI',           'example' => 'https://isni.org/isni/0000000121032683' ),
			array( 'key' => 'wikidata',       'label' => 'Wikidata',       'example' => 'https://www.wikidata.org/wiki/Q42' ),
			array( 'key' => 'google_scholar', 'label' => 'Google Scholar', 'example' => 'https://scholar.google.com/citations?user=XXXXXXXX' ),
			array( 'key' => 'linkedin',       'label' => 'LinkedIn',       'example' => 'https://www.linkedin.com/in/your-handle/' ),
			array( 'key' => 'muckrack',       'label' => 'Muck Rack',      'example' => 'https://muckrack.com/your-name' ),
			array( 'key' => 'amazon_author',  'label' => 'Amazon Author',  'example' => 'https://www.amazon.com/author/your-name' ),
			array( 'key' => 'goodreads',      'label' => 'Goodreads',      'example' => 'https://www.goodreads.com/author/show/XXXX.Your_Name' ),
			array( 'key' => 'open_library',   'label' => 'Open Library',   'example' => 'https://openlibrary.org/authors/OLXXXXXXA' ),
		);
		$org = array(
			array( 'key' => 'linkedin_company', 'label' => 'LinkedIn (company)', 'example' => 'https://www.linkedin.com/company/your-company/' ),
			array( 'key' => 'wikidata',         'label' => 'Wikidata',           'example' => 'https://www.wikidata.org/wiki/Q95' ),
			array( 'key' => 'crunchbase',       'label' => 'Crunchbase',         'example' => 'https://www.crunchbase.com/organization/your-company' ),
			array( 'key' => 'x_twitter',        'label' => 'X (Twitter)',        'example' => 'https://x.com/yourhandle' ),
			array( 'key' => 'facebook',         'label' => 'Facebook',           'example' => 'https://www.facebook.com/yourpage' ),
			array( 'key' => 'youtube',          'label' => 'YouTube',            'example' => 'https://www.youtube.com/@yourchannel' ),
		);
		return 'org' === $scope ? $org : $person;
	}

	/* --------------------------------------------------------------------- *
	 * Step definitions
	 * --------------------------------------------------------------------- */

	/**
	 * The ordered list of step keys for the current site type. The site-type
	 * step is always first; organization and author steps are included only
	 * when relevant.
	 *
	 * @return string[]
	 */
	public function step_keys() {
		$type  = $this->state()['site_type'];
		$keys  = array( 'site_type' );
		$is_org    = ( 'org' === $type || 'both' === $type );
		$is_person = ( 'person' === $type || 'both' === $type );
		if ( $is_org ) {
			$keys[] = 'org_basics';
			$keys[] = 'org_reach';
			$keys[] = 'org_ids';
		}
		if ( $is_person ) {
			$keys[] = 'author_basics';
			$keys[] = 'author_bio';
			$keys[] = 'author_ids';
		}
		$keys[] = 'front_page';
		$keys[] = 'finish';
		return $keys;
	}

	/**
	 * Field definitions for a step: key => type. Types: text, url, textarea,
	 * textarea_urls. The identifier steps are handled separately.
	 *
	 * @param string $key Step key.
	 * @return array
	 */
	private function step_fields( $key ) {
		switch ( $key ) {
			case 'org_basics':
				return array(
					'org_name'        => 'text',
					'org_id'          => 'url',
					'org_logo'        => 'url',
					'org_description' => 'textarea',
				);
			case 'org_reach':
				return array(
					'org_areaserved'   => 'text',
					'org_altname'      => 'textarea',
					'org_knowsabout'   => 'textarea',
					'org_contactpoints' => 'textarea',
				);
			case 'author_basics':
				return array(
					'author_name'       => 'text',
					'author_givenname'  => 'text',
					'author_familyname' => 'text',
					'author_jobtitle'   => 'text',
					'author_url'        => 'url',
					'author_image'      => 'url',
				);
			case 'author_bio':
				return array(
					'author_bio'       => 'textarea',
					'author_knowsabout' => 'textarea',
					'author_altname'   => 'textarea',
				);
			default:
				return array();
		}
	}

	/* --------------------------------------------------------------------- *
	 * Save handling (non-destructive)
	 * --------------------------------------------------------------------- */

	/**
	 * Handle a step submission: Save-and-continue, Skip, or Back. Save writes
	 * only filled fields; Clear checkboxes erase; Skip writes nothing.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_wizard_save' );

		$step_index = isset( $_POST['step'] ) ? max( 0, (int) $_POST['step'] ) : 0;
		$action     = isset( $_POST['twkd_nav'] ) ? sanitize_key( wp_unslash( $_POST['twkd_nav'] ) ) : 'next';
		$keys       = $this->step_keys();
		$step_key   = isset( $keys[ $step_index ] ) ? $keys[ $step_index ] : 'finish';

		// Site-type selection (governs which later steps exist).
		if ( 'site_type' === $step_key && 'skip' !== $action && isset( $_POST['twkd_site_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['twkd_site_type'] ) );
			if ( in_array( $type, array( 'person', 'org', 'both' ), true ) ) {
				$this->update_state( array( 'site_type' => $type ) );
				$keys = $this->step_keys();
			}
		}

		if ( 'skip' !== $action && 'back' !== $action ) {
			$this->save_step( $step_key );
		}

		// Compute the destination step.
		if ( 'back' === $action ) {
			$dest = max( 0, $step_index - 1 );
		} else {
			$dest = $step_index + 1;
		}

		$last = count( $keys ) - 1;
		if ( $dest > $last ) {
			$dest = $last;
		}

		// Reaching the finish step marks completion and turns enrichment on.
		if ( isset( $keys[ $dest ] ) && 'finish' === $keys[ $dest ] && 'back' !== $action ) {
			$settings = get_option( TWKD_OPTION, array() );
			if ( is_array( $settings ) ) {
				$settings['enable_entity'] = 1;
				update_option( TWKD_OPTION, $settings );
			}
			$this->update_state( array( 'completed' => true ) );
			update_option( 'twkd_wizard_done', 1 );
		}

		wp_safe_redirect( $this->url( $dest ) );
		exit;
	}

	/**
	 * Save one step's fields into twkd_settings, non-destructively.
	 *
	 * @param string $step_key Step key.
	 */
	private function save_step( $step_key ) {
		$settings = get_option( TWKD_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$raw   = isset( $_POST[ TWKD_OPTION ] ) && is_array( $_POST[ TWKD_OPTION ] ) ? wp_unslash( $_POST[ TWKD_OPTION ] ) : array();
		$clear = isset( $_POST['twkd_clear'] ) && is_array( $_POST['twkd_clear'] ) ? array_map( 'sanitize_key', array_keys( $_POST['twkd_clear'] ) ) : array();

		// Identifier steps compile individual fields into a sameAs textarea and
		// record "don't have" flags separately.
		if ( 'org_ids' === $step_key || 'author_ids' === $step_key ) {
			$this->save_identifiers( $step_key, $settings, $raw, $clear );
			update_option( TWKD_OPTION, $settings );
			return;
		}

		// Front-page step is a single checkbox; its state is the value.
		if ( 'front_page' === $step_key ) {
			$settings['entity_suppress_front'] = empty( $raw['entity_suppress_front'] ) ? 0 : 1;
			update_option( TWKD_OPTION, $settings );
			return;
		}

		foreach ( $this->step_fields( $step_key ) as $field => $type ) {
			if ( in_array( $field, $clear, true ) ) {
				$settings[ $field ] = '';
				continue;
			}
			if ( ! isset( $raw[ $field ] ) ) {
				continue; // not submitted — leave untouched.
			}
			$value = $raw[ $field ];
			if ( '' === trim( (string) $value ) ) {
				continue; // empty — non-destructive, keep existing.
			}
			$settings[ $field ] = $this->sanitize_value( $value, $type );
		}

		update_option( TWKD_OPTION, $settings );
	}

	/**
	 * Compile identifier fields into the appropriate sameAs setting and record
	 * which identifiers the user flagged as "don't have yet".
	 *
	 * @param string $step_key Step key.
	 * @param array  $settings Settings array, by reference.
	 * @param array  $raw      Submitted values.
	 * @param array  $clear    Field keys flagged for clearing.
	 */
	private function save_identifiers( $step_key, &$settings, $raw, $clear ) {
		$scope     = ( 'org_ids' === $step_key ) ? 'org' : 'person';
		$target    = ( 'org' === $scope ) ? 'org_sameas' : 'author_sameas';
		$registry  = self::identifiers( $scope );
		$ids       = isset( $raw['twkd_id'] ) && is_array( $raw['twkd_id'] ) ? $raw['twkd_id'] : array();
		$urls      = array();
		$dont_have = $this->state()['dont_have'];

		// Preserve any existing free-form sameAs URLs the wizard didn't manage.
		$existing = twkd_lines_to_urls( isset( $settings[ $target ] ) ? $settings[ $target ] : '' );
		$managed_examples = wp_list_pluck( $registry, 'example' );
		foreach ( $existing as $u ) {
			$urls[ $u ] = $u;
		}

		foreach ( $registry as $id ) {
			$key   = $id['key'];
			$state_key = $scope . '_' . $key;
			$value = isset( $ids[ $key ] ) ? esc_url_raw( trim( (string) $ids[ $key ] ) ) : '';

			// "Don't have yet" flag, tracked separately from data.
			if ( ! empty( $raw['twkd_dont_have'][ $key ] ) ) {
				$dont_have[ $state_key ] = true;
			} else {
				unset( $dont_have[ $state_key ] );
			}

			if ( $value ) {
				$urls[ $value ] = $value;
			}
		}

		$settings[ $target ] = implode( "\n", array_values( $urls ) );
		$this->update_state( array( 'dont_have' => $dont_have ) );
	}

	/**
	 * Sanitize a single value by field type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private function sanitize_value( $value, $type ) {
		switch ( $type ) {
			case 'url':
				return esc_url_raw( trim( (string) $value ) );
			case 'textarea':
			case 'textarea_urls':
				return sanitize_textarea_field( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/* --------------------------------------------------------------------- *
	 * First-run redirect and banner
	 * --------------------------------------------------------------------- */

	/**
	 * On first activation, redirect once to the wizard.
	 */
	public function maybe_first_run_redirect() {
		if ( ! get_transient( 'twkd_activation_redirect' ) ) {
			return;
		}
		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Don't hijack bulk plugin activations.
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		// Consume the one-shot transient only once we know we're actually about
		// to redirect — otherwise a non-admin loading any /wp-admin/ URL during
		// the activation window would silently eat it and the admin who
		// activated would never see the wizard launch.
		delete_transient( 'twkd_activation_redirect' );
		wp_safe_redirect( $this->url( 0 ) );
		exit;
	}

	/**
	 * Show a dismissible setup banner until the wizard is completed or dismissed.
	 */
	public function maybe_banner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Fast path: an autoloaded boolean tells us the banner is no longer
		// needed (wizard completed or dismissed) without reading the full state
		// option. Saves one DB query per admin page on finished installs.
		if ( get_option( 'twkd_wizard_done' ) ) {
			return;
		}
		$state = $this->state();
		if ( $state['completed'] || $state['dismissed'] ) {
			// Lazy migration: a pre-flag install where the wizard was already
			// finished. Set the flag so future page loads skip the state read.
			update_option( 'twkd_wizard_done', 1 );
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) {
			return; // already in the wizard.
		}
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=twkd_wizard_dismiss' ),
			'twkd_wizard_dismiss'
		);
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p><p><a href="%s" class="button button-primary">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'TWK AEO Discovery:', 'twk-aeo-discovery' ),
			esc_html__( 'Set up your entity authority so answer engines can identify you. It takes a few minutes and you can re-run it anytime.', 'twk-aeo-discovery' ),
			esc_url( $this->url( 0 ) ),
			esc_html__( 'Run setup wizard', 'twk-aeo-discovery' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'twk-aeo-discovery' )
		);
	}

	/**
	 * Dismiss the banner.
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_wizard_dismiss' );
		$this->update_state( array( 'dismissed' => true ) );
		update_option( 'twkd_wizard_done', 1 );
		wp_safe_redirect( admin_url( 'options-general.php?page=twk-aeo-discovery' ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render the wizard for the current step.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keys       = $this->step_keys();
		$step_index = isset( $_GET['step'] ) ? max( 0, (int) $_GET['step'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$last       = count( $keys ) - 1;
		if ( $step_index > $last ) {
			$step_index = $last;
		}
		$step_key = $keys[ $step_index ];
		$total    = count( $keys );

		echo '<div class="wrap" style="max-width:760px;">';
		printf( '<h1>%s</h1>', esc_html__( 'AEO setup', 'twk-aeo-discovery' ) );

		// Progress.
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: current step number, 2: total steps. */
					__( 'Step %1$d of %2$d', 'twk-aeo-discovery' ),
					$step_index + 1,
					$total
				)
			)
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="twkd_wizard_save" />';
		printf( '<input type="hidden" name="step" value="%d" />', (int) $step_index );
		wp_nonce_field( 'twkd_wizard_save' );

		$this->render_step( $step_key );

		// Navigation.
		echo '<p style="margin-top:1.5em;">';
		if ( $step_index > 0 ) {
			printf( '<button type="submit" name="twkd_nav" value="back" class="button">%s</button> ', esc_html__( 'Back', 'twk-aeo-discovery' ) );
		}
		if ( 'finish' !== $step_key ) {
			printf( '<button type="submit" name="twkd_nav" value="next" class="button button-primary">%s</button> ', esc_html__( 'Save and continue', 'twk-aeo-discovery' ) );
			if ( 'site_type' !== $step_key ) {
				printf( '<button type="submit" name="twkd_nav" value="skip" class="button button-link">%s</button>', esc_html__( 'Skip this step', 'twk-aeo-discovery' ) );
			}
		} else {
			printf( '<a href="%s" class="button button-primary">%s</a>', esc_url( admin_url( 'options-general.php?page=twk-aeo-discovery&tab=entity' ) ), esc_html__( 'Done', 'twk-aeo-discovery' ) );
		}
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the body of a single step.
	 *
	 * @param string $step_key Step key.
	 */
	private function render_step( $step_key ) {
		switch ( $step_key ) {
			case 'site_type':
				$this->render_site_type();
				break;
			case 'org_ids':
				$this->render_identifiers( 'org' );
				break;
			case 'author_ids':
				$this->render_identifiers( 'person' );
				break;
			case 'front_page':
				$this->render_front_page();
				break;
			case 'finish':
				$this->render_finish();
				break;
			default:
				$this->render_fields( $step_key );
				break;
		}
	}

	/**
	 * Site-type chooser.
	 */
	private function render_site_type() {
		$type = $this->state()['site_type'];
		printf( '<h2>%s</h2>', esc_html__( 'What does this site represent?', 'twk-aeo-discovery' ) );
		printf( '<p>%s</p>', esc_html__( 'This decides which questions you will see. You can re-run the wizard and change it later.', 'twk-aeo-discovery' ) );
		$choices = array(
			'person' => __( 'A person (personal brand, author, consultant)', 'twk-aeo-discovery' ),
			'org'    => __( 'An organization (company, brand, publication)', 'twk-aeo-discovery' ),
			'both'   => __( 'Both a person and an organization', 'twk-aeo-discovery' ),
		);
		echo '<fieldset>';
		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="display:block;margin:.5em 0;"><input type="radio" name="twkd_site_type" value="%s" %s /> %s</label>',
				esc_attr( $value ),
				checked( $type, $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Generic field step renderer with pre-fill and per-field Clear.
	 *
	 * @param string $step_key Step key.
	 */
	private function render_fields( $step_key ) {
		$titles = array(
			'org_basics'    => array( __( 'Organization basics', 'twk-aeo-discovery' ), __( 'The core identity of the organization this site represents.', 'twk-aeo-discovery' ) ),
			'org_reach'     => array( __( 'Organization reach', 'twk-aeo-discovery' ), __( 'What the organization does and how it can be reached. Multi-value boxes take one entry per line.', 'twk-aeo-discovery' ) ),
			'author_basics' => array( __( 'About you', 'twk-aeo-discovery' ), __( 'The person this site represents.', 'twk-aeo-discovery' ) ),
			'author_bio'    => array( __( 'Your bio and expertise', 'twk-aeo-discovery' ), __( 'What you are known for. knowsAbout topics, one per line, are a strong entity signal.', 'twk-aeo-discovery' ) ),
		);
		$labels = array(
			'org_name'          => __( 'Organization name', 'twk-aeo-discovery' ),
			'org_id'            => __( 'Organization canonical URL (@id)', 'twk-aeo-discovery' ),
			'org_logo'          => __( 'Logo URL', 'twk-aeo-discovery' ),
			'org_description'   => __( 'Description', 'twk-aeo-discovery' ),
			'org_areaserved'    => __( 'Area served', 'twk-aeo-discovery' ),
			'org_altname'       => __( 'Alternate names (one per line)', 'twk-aeo-discovery' ),
			'org_knowsabout'    => __( 'Knows about / topics (one per line)', 'twk-aeo-discovery' ),
			'org_contactpoints' => __( 'Contact points (type | url | description)', 'twk-aeo-discovery' ),
			'author_name'       => __( 'Full name', 'twk-aeo-discovery' ),
			'author_givenname'  => __( 'Given (first) name', 'twk-aeo-discovery' ),
			'author_familyname' => __( 'Family (last) name', 'twk-aeo-discovery' ),
			'author_jobtitle'   => __( 'Job title', 'twk-aeo-discovery' ),
			'author_url'        => __( 'Your canonical URL (@id / profile)', 'twk-aeo-discovery' ),
			'author_image'      => __( 'Photo URL', 'twk-aeo-discovery' ),
			'author_bio'        => __( 'Short bio', 'twk-aeo-discovery' ),
			'author_knowsabout' => __( 'Knows about / topics (one per line)', 'twk-aeo-discovery' ),
			'author_altname'    => __( 'Alternate names / pen names (one per line)', 'twk-aeo-discovery' ),
		);
		$descriptions = array(
			'org_name'         => __( 'The legal or doing-business-as name of the organization this site represents. Use it consistently — answer engines reconcile entities by name match across your sameAs profiles.', 'twk-aeo-discovery' ),
			'org_id'           => __( 'A stable URL that uniquely identifies this organization in the schema graph. Does not need to resolve to a real page. Convention: https://yoursite.com/#organization or #business. Use the same URL on every page so all references reconcile to one entity.', 'twk-aeo-discovery' ),
			'org_logo'         => __( 'Full URL to your logo image, uploaded to this site\'s Media Library. Google requires the logo to be served from your own domain for Organization rich results — external/CDN URLs often will not qualify.', 'twk-aeo-discovery' ),
			'org_description'  => __( 'A short factual description of what the organization is and does. One or two sentences.', 'twk-aeo-discovery' ),
			'org_areaserved'   => __( 'Geographic area the organization serves. A country, a region, or "Worldwide".', 'twk-aeo-discovery' ),
			'org_altname'      => __( 'Other names the organization is known by, one per line. Common abbreviations, DBA names, or former names.', 'twk-aeo-discovery' ),
			'org_knowsabout'   => __( 'Topics the organization is recognized for, one per line. Strong entity-authority signal — answer engines use this to match queries to your expertise.', 'twk-aeo-discovery' ),
			'org_contactpoints'=> __( 'Contact channels, one per line in the format: type | URL | description. Example: customer service | https://example.com/contact | English-language support.', 'twk-aeo-discovery' ),
			'author_name'      => __( 'Your full public-facing name as it appears in your bylines.', 'twk-aeo-discovery' ),
			'author_givenname' => __( 'First name.', 'twk-aeo-discovery' ),
			'author_familyname'=> __( 'Last name.', 'twk-aeo-discovery' ),
			'author_jobtitle'  => __( 'Your current professional title.', 'twk-aeo-discovery' ),
			'author_url'       => __( 'Your canonical URL on this site — typically your author page or your homepage. Like the organization\'s @id, this is the stable URL that uniquely identifies you so per-post Person references all reconcile to one entity.', 'twk-aeo-discovery' ),
			'author_image'     => __( 'Full URL to your photo, uploaded to this site\'s Media Library. Like the logo, Google prefers images served from your own domain.', 'twk-aeo-discovery' ),
			'author_bio'       => __( 'A short third-person biography. Two or three sentences.', 'twk-aeo-discovery' ),
			'author_knowsabout'=> __( 'Topics you are recognized for, one per line. The single highest-value entity-authority signal for a Person — answer engines match queries to authors with the right knowsAbout.', 'twk-aeo-discovery' ),
			'author_altname'   => __( 'Other names you publish under, one per line. Pen names, byline variants, former names.', 'twk-aeo-discovery' ),
		);
		if ( isset( $titles[ $step_key ] ) ) {
			printf( '<h2>%s</h2>', esc_html( $titles[ $step_key ][0] ) );
			printf( '<p>%s</p>', esc_html( $titles[ $step_key ][1] ) );
		}
		echo '<table class="form-table" role="presentation">';
		foreach ( $this->step_fields( $step_key ) as $field => $type ) {
			$value = (string) twkd_get_option( $field );
			$label = isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			if ( 'textarea' === $type || 'textarea_urls' === $type ) {
				printf(
					'<textarea name="%s[%s]" rows="3" class="large-text">%s</textarea>',
					esc_attr( TWKD_OPTION ),
					esc_attr( $field ),
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="%s" name="%s[%s]" value="%s" class="regular-text" />',
					'url' === $type ? 'url' : 'text',
					esc_attr( TWKD_OPTION ),
					esc_attr( $field ),
					esc_attr( $value )
				);
			}
			if ( '' !== trim( $value ) ) {
				printf(
					'<label style="margin-left:1em;font-size:12px;color:#a00;"><input type="checkbox" name="twkd_clear[%s]" value="1" /> %s</label>',
					esc_attr( $field ),
					esc_html__( 'Clear', 'twk-aeo-discovery' )
				);
			}
			if ( isset( $descriptions[ $field ] ) ) {
				printf(
					'<p class="description" style="margin-top:.5em;max-width:42em;">%s</p>',
					esc_html( $descriptions[ $field ] )
				);
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Identifier step: one field per identifier, with a "don't have yet" toggle.
	 *
	 * @param string $scope 'person' or 'org'.
	 */
	private function render_identifiers( $scope ) {
		$registry = self::identifiers( $scope );
		$target   = ( 'org' === $scope ) ? 'org_sameas' : 'author_sameas';
		$current  = twkd_lines_to_urls( (string) twkd_get_option( $target ) );

		printf( '<h2>%s</h2>', 'org' === $scope ? esc_html__( 'Organization identifiers', 'twk-aeo-discovery' ) : esc_html__( 'Your identifiers', 'twk-aeo-discovery' ) );
		printf( '<p>%s</p>', esc_html__( 'These authoritative profiles (sameAs) are what let answer engines confirm who you are. Paste any you have; leave the rest blank — the finish report will tell you how to get the ones you do not.', 'twk-aeo-discovery' ) );

		echo '<table class="form-table" role="presentation">';
		foreach ( $registry as $id ) {
			$key = $id['key'];
			// Pre-fill: find an existing URL that matches this identifier's host.
			$prefill = $this->match_existing( $current, $id['example'] );
			echo '<tr><th scope="row">' . esc_html( $id['label'] ) . '</th><td>';
			printf(
				'<input type="url" name="%s[twkd_id][%s]" value="%s" class="regular-text" placeholder="%s" />',
				esc_attr( TWKD_OPTION ),
				esc_attr( $key ),
				esc_attr( $prefill ),
				esc_attr( $id['example'] )
			);
			echo '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Find an already-stored URL whose host matches the identifier example's host.
	 *
	 * @param string[] $urls    Existing sameAs URLs.
	 * @param string   $example Example URL for the identifier.
	 * @return string Matching URL or ''.
	 */
	private function match_existing( $urls, $example ) {
		$host = wp_parse_url( $example, PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}
		foreach ( $urls as $u ) {
			if ( wp_parse_url( $u, PHP_URL_HOST ) === $host ) {
				return $u;
			}
		}
		return '';
	}

	/**
	 * Front-page handling step.
	 */
	private function render_front_page() {
		printf( '<h2>%s</h2>', esc_html__( 'Front page', 'twk-aeo-discovery' ) );
		printf( '<p>%s</p>', esc_html__( 'If your homepage already outputs a hand-built schema graph you want left alone, enrichment can skip the front page. Most sites should leave this off.', 'twk-aeo-discovery' ) );
		printf(
			'<label><input type="checkbox" name="%s[entity_suppress_front]" value="1" %s /> %s</label>',
			esc_attr( TWKD_OPTION ),
			checked( twkd_get_option( 'entity_suppress_front' ), 1, false ),
			esc_html__( 'Leave my hand-built homepage graph alone (suppress enrichment on the front page)', 'twk-aeo-discovery' )
		);
		// Reuse the generic save path for this single checkbox.
		echo '<input type="hidden" name="twkd_fp_marker" value="1" />';
	}

	/**
	 * Finish step: summary + pointer to the report.
	 */
	private function render_finish() {
		$report_url = TWKD_Report::instance()->url();
		printf( '<h2>%s</h2>', esc_html__( 'All set', 'twk-aeo-discovery' ) );
		printf( '<p>%s</p>', esc_html__( 'Entity enrichment is now on. Your values are saved and will be merged into your SEO plugin\'s schema (or emitted standalone if none is active).', 'twk-aeo-discovery' ) );
		printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( $report_url ), esc_html__( 'View your setup report and next steps', 'twk-aeo-discovery' ) );
	}
}
