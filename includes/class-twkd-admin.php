<?php
/**
 * Settings screen (Settings > TWK AEO Discovery).
 *
 * Uses the WordPress Settings API so saves go through options.php with a core
 * nonce and a manage_options capability check. This is what closes the
 * unauthenticated-settings-change class of bug.
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Admin
 */
class TWKD_Admin {

	const GROUP = 'twkd_group';

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return TWKD_Admin
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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'update_option_' . TWKD_OPTION, array( $this, 'flush' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_post_twkd_llms_write', array( $this, 'write_llms_file' ) );
		add_action( 'admin_post_twkd_llms_remove', array( $this, 'remove_llms_file' ) );
		add_action( 'admin_post_twkd_export', array( $this, 'export_settings' ) );
		add_action( 'admin_post_twkd_import', array( $this, 'import_settings' ) );
		add_action( 'admin_post_twkd_clear_log', array( $this, 'clear_log' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TWKD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Enqueue the plugin's admin stylesheet on plugin admin pages only.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'twk-aeo-discovery' ) ) {
			return;
		}
		wp_enqueue_style(
			'twkd-admin',
			TWKD_URL . 'assets/admin.css',
			array(),
			TWKD_VERSION
		);
	}

	/**
	 * Add the settings page under Settings.
	 */
	public function menu() {
		add_options_page(
			__( 'TWK AEO Discovery', 'twk-aeo-discovery' ),
			__( 'TWK AEO Discovery', 'twk-aeo-discovery' ),
			'manage_options',
			'twk-aeo-discovery',
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting with its sanitize callback.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			TWKD_OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=twk-aeo-discovery' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'twk-aeo-discovery' ) . '</a>' );
		return $links;
	}

	/**
	 * Re-register and flush rewrite rules after settings change.
	 */
	public function flush() {
		TWKD_Sitemap::instance()->register_rewrites();
		flush_rewrite_rules();
	}

	/**
	 * Write the currently served llms.txt content to a physical file in the
	 * site root, so the content survives even if the plugin is deleted.
	 */
	public function write_llms_file() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_llms_file' );

		$path    = ABSPATH . 'llms.txt';
		$content = TWKD_LLMS::build( false );
		$ok      = false;

		// WP_Filesystem-based write. On most installs this uses the 'direct'
		// transport (no credentials needed); on hosts that require credentials,
		// request_filesystem_credentials() handles it.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
				$ok = (bool) $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
			}
		}

		set_transient( 'twkd_llms_file_result', $ok ? 'written' : 'write_failed', 60 );
		if ( ! $ok ) {
			twkd_log_error( 'llms.txt', 'Could not write llms.txt to the site root — check file/folder permissions.' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=twk-aeo-discovery' ) );
		exit;
	}

	/**
	 * Remove the physical llms.txt so the plugin serves it dynamically again.
	 */
	public function remove_llms_file() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_llms_file' );

		$path = ABSPATH . 'llms.txt';
		$ok   = false;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem->exists( $path ) ) {
				$ok = (bool) $wp_filesystem->delete( $path );
			}
		}

		set_transient( 'twkd_llms_file_result', $ok ? 'removed' : 'remove_failed', 60 );
		if ( ! $ok ) {
			twkd_log_error( 'llms.txt', 'Could not remove the physical llms.txt — check file permissions on the site root.' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=twk-aeo-discovery' ) );
		exit;
	}

	/**
	 * Clear the Diagnostics error log.
	 */
	public function clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_clear_log' );
		delete_option( 'twkd_error_log' );
		wp_safe_redirect( admin_url( 'options-general.php?page=twk-aeo-discovery&tab=diagnostics' ) );
		exit;
	}

	/**
	 * Admin notices (IndexNow manual submit result, physical robots.txt warning).
	 */
	public function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_twk-aeo-discovery' !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['twkd_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status = sanitize_text_field( wp_unslash( $_GET['twkd_import'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'ok' === $status ) {
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Settings imported. Review the site-specific fields (Organization details, canonical @ids, llms.txt content, sitemap post types), then Save.', 'twk-aeo-discovery' ) );
			} elseif ( 'invalid' === $status ) {
				printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( 'Import failed: that file is not a valid TWK AEO Discovery export.', 'twk-aeo-discovery' ) );
			} elseif ( 'nofile' === $status ) {
				printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( 'Import failed: no file was uploaded.', 'twk-aeo-discovery' ) );
			}
		}

		if ( isset( $_GET['twkd_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$last = get_option( 'twkd_indexnow_last' );
			if ( $last && 'success' === $last['result'] ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html( sprintf( /* translators: %d: number of URLs. */ __( 'Submitted %d URL(s) to IndexNow.', 'twk-aeo-discovery' ), (int) $last['count'] ) )
				);
			} elseif ( $last ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html( sprintf( /* translators: %s: error code. */ __( 'IndexNow submission failed: %s', 'twk-aeo-discovery' ), $last['result'] ) )
				);
			}
		}

		if ( twkd_get_option( 'robots_sitemap' ) && file_exists( ABSPATH . 'robots.txt' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'A physical robots.txt file exists in your site root. WordPress serves that file instead of the virtual one, so the sitemap line added by this plugin will not appear. Add it to your robots.txt manually.', 'twk-aeo-discovery' )
			);
		}

		$file_result = get_transient( 'twkd_llms_file_result' );
		if ( $file_result ) {
			delete_transient( 'twkd_llms_file_result' );
			$map = array(
				'written'       => array( 'success', __( 'Wrote llms.txt to your site root. It will persist even if the plugin is removed.', 'twk-aeo-discovery' ) ),
				'write_failed'  => array( 'error', __( 'Could not write llms.txt — your site root is not writable. Adjust permissions or create the file manually.', 'twk-aeo-discovery' ) ),
				'removed'       => array( 'success', __( 'Removed the physical llms.txt. The plugin now serves it dynamically.', 'twk-aeo-discovery' ) ),
				'remove_failed' => array( 'error', __( 'Could not remove the physical llms.txt.', 'twk-aeo-discovery' ) ),
			);
			if ( isset( $map[ $file_result ] ) ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $map[ $file_result ][0] ),
					esc_html( $map[ $file_result ][1] )
				);
			}
		}

		if ( twkd_get_option( 'enable_llms' ) && file_exists( ABSPATH . 'llms.txt' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'A physical llms.txt exists in your site root and is served by your web server. The plugin\'s editable version is inactive until you remove that file (use the Remove button below).', 'twk-aeo-discovery' )
			);
		}
	}

	/**
	 * Render a small help-icon tooltip for a settings field. Uses a dashicons
	 * glyph (bundled with WordPress core, no extra assets) plus a CSS tooltip
	 * driven by a data attribute — shows instantly on hover or keyboard focus,
	 * works in every browser (the native title attribute is unreliable), and
	 * stays accessible via aria-label. The tooltip CSS is loaded by
	 * enqueue_assets() on plugin admin pages only.
	 *
	 * @param string $text Tooltip text, already translated.
	 * @return string Safe HTML to echo inline next to a label.
	 */
	private function tip( $text ) {
		// Echoing directly (not returning) means call sites use `<?php $this->tip(...)`
		// without `echo`, which both satisfies PHPCS OutputNotEscaped and keeps the
		// rendering logic in one place. The dynamic value is esc_attr'd inline below.
		echo ' <span class="twkd-tip dashicons dashicons-editor-help" data-twkd-tip="' . esc_attr( $text ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $text ) . '"></span>';
	}

	/**
	 * Sanitize the submitted settings.
	 *
	 * Non-destructive across tabs: the page now submits one tab at a time
	 * (each tab carries a hidden twkd_tab_submitted field). We start from the
	 * currently-saved settings and only update the fields belonging to the
	 * submitted tab — so a Save on Sitemap leaves Entity Authority untouched,
	 * and vice versa. Without this merge, posting one tab would reset every
	 * field on every other tab to defaults.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		$current = get_option( TWKD_OPTION, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$out = array_merge( twkd_default_settings(), $current );

		$tab = isset( $input['twkd_tab_submitted'] ) ? sanitize_key( $input['twkd_tab_submitted'] ) : '';
		// No tab marker means this is not a single-tab form save (e.g. a settings
		// import, which carries the full settings array). In that case process
		// every field; otherwise scope to just the submitted tab.
		$all = ( '' === $tab );

		if ( $all || 'sitemap' === $tab ) {
			$out['enable_sitemap']       = empty( $input['enable_sitemap'] ) ? 0 : 1;
			$out['robots_sitemap']       = empty( $input['robots_sitemap'] ) ? 0 : 1;
			$out['disable_core_sitemap'] = empty( $input['disable_core_sitemap'] ) ? 0 : 1;
			$out['sitemap_stylesheet']   = empty( $input['sitemap_stylesheet'] ) ? 0 : 1;
			$out['per_page']             = min( 50000, max( 1, (int) ( isset( $input['per_page'] ) ? $input['per_page'] : 2000 ) ) );

			$public_pt = get_post_types( array( 'public' => true ), 'names' );
			unset( $public_pt['attachment'] );
			$out['post_types'] = array();
			if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				foreach ( $input['post_types'] as $pt ) {
					$pt = sanitize_key( $pt );
					if ( isset( $public_pt[ $pt ] ) ) {
						$out['post_types'][] = $pt;
					}
				}
			}

			$public_tax = get_taxonomies( array( 'public' => true ), 'names' );
			$out['taxonomies'] = array();
			if ( ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
				foreach ( $input['taxonomies'] as $tax ) {
					$tax = sanitize_key( $tax );
					if ( isset( $public_tax[ $tax ] ) ) {
						$out['taxonomies'][] = $tax;
					}
				}
			}
		}

		if ( $all || 'indexnow' === $tab ) {
			$out['enable_indexnow'] = empty( $input['enable_indexnow'] ) ? 0 : 1;
		}

		if ( $all || 'entity' === $tab ) {
			$out['enable_entity']         = empty( $input['enable_entity'] ) ? 0 : 1;
			$out['entity_suppress_front'] = empty( $input['entity_suppress_front'] ) ? 0 : 1;
			$out['org_name']          = isset( $input['org_name'] ) ? sanitize_text_field( $input['org_name'] ) : '';
			$out['org_id']            = isset( $input['org_id'] ) ? esc_url_raw( $input['org_id'] ) : '';
			$out['org_logo']          = isset( $input['org_logo'] ) ? esc_url_raw( $input['org_logo'] ) : '';
			$out['org_sameas']        = isset( $input['org_sameas'] ) ? sanitize_textarea_field( $input['org_sameas'] ) : '';
			$out['org_knowsabout']    = isset( $input['org_knowsabout'] ) ? sanitize_textarea_field( $input['org_knowsabout'] ) : '';
			$out['org_contactpoints'] = isset( $input['org_contactpoints'] ) ? sanitize_textarea_field( $input['org_contactpoints'] ) : '';
			$out['org_altname']       = isset( $input['org_altname'] ) ? sanitize_textarea_field( $input['org_altname'] ) : '';
			$out['org_description']   = isset( $input['org_description'] ) ? sanitize_textarea_field( $input['org_description'] ) : '';
			$out['org_areaserved']    = isset( $input['org_areaserved'] ) ? sanitize_text_field( $input['org_areaserved'] ) : '';
			$out['author_name']       = isset( $input['author_name'] ) ? sanitize_text_field( $input['author_name'] ) : '';
			$out['author_id']         = isset( $input['author_id'] ) ? esc_url_raw( $input['author_id'] ) : '';
			$out['author_bio']        = isset( $input['author_bio'] ) ? sanitize_textarea_field( $input['author_bio'] ) : '';
			$out['author_jobtitle']   = isset( $input['author_jobtitle'] ) ? sanitize_text_field( $input['author_jobtitle'] ) : '';
			$out['author_image']      = isset( $input['author_image'] ) ? esc_url_raw( $input['author_image'] ) : '';
			$out['author_sameas']     = isset( $input['author_sameas'] ) ? sanitize_textarea_field( $input['author_sameas'] ) : '';
			$out['author_knowsabout'] = isset( $input['author_knowsabout'] ) ? sanitize_textarea_field( $input['author_knowsabout'] ) : '';
			$out['author_altname']    = isset( $input['author_altname'] ) ? sanitize_textarea_field( $input['author_altname'] ) : '';
			$out['author_url']        = isset( $input['author_url'] ) ? esc_url_raw( $input['author_url'] ) : '';
			$out['author_givenname']  = isset( $input['author_givenname'] ) ? sanitize_text_field( $input['author_givenname'] ) : '';
			$out['author_familyname'] = isset( $input['author_familyname'] ) ? sanitize_text_field( $input['author_familyname'] ) : '';
		}

		if ( $all || 'ai' === $tab ) {
			$out['enable_llms']    = empty( $input['enable_llms'] ) ? 0 : 1;
			$out['ai_welcome']     = empty( $input['ai_welcome'] ) ? 0 : 1;
			$out['llms_mode']      = ( isset( $input['llms_mode'] ) && 'custom' === $input['llms_mode'] ) ? 'custom' : 'auto';
			$out['llms_custom']    = isset( $input['llms_custom'] ) ? sanitize_textarea_field( wp_unslash( $input['llms_custom'] ) ) : '';
			$out['llms_intro']     = isset( $input['llms_intro'] ) ? sanitize_textarea_field( $input['llms_intro'] ) : '';
			$out['llms_key_pages'] = isset( $input['llms_key_pages'] ) ? sanitize_textarea_field( $input['llms_key_pages'] ) : '';
		}

		return $out;
	}

	/**
	 * Render the settings page. Five tabs across the top: Sitemap, IndexNow,
	 * Entity Authority, AI Engines, Tools. Each tab is its own self-contained
	 * form (or, for Tools, action buttons) so saves are bounded to one tab.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$public_pt  = get_post_types( array( 'public' => true ), 'objects' );
		unset( $public_pt['attachment'] );
		$public_tax = get_taxonomies( array( 'public' => true ), 'objects' );

		$sel_pt  = (array) twkd_get_option( 'post_types' );
		$sel_tax = (array) twkd_get_option( 'taxonomies' );
		$key     = twkd_get_indexnow_key();
		$last    = get_option( 'twkd_indexnow_last' );

		$valid_tabs = array( 'sitemap', 'indexnow', 'entity', 'ai', 'tools', 'diagnostics' );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'sitemap'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
			$active_tab = 'sitemap';
		}

		$tab_url = function ( $tab ) {
			return admin_url( 'options-general.php?page=twk-aeo-discovery&tab=' . $tab );
		};
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'TWK AEO Discovery', 'twk-aeo-discovery' ); ?>
				<span class="twkd-version" style="font-size:13px;font-weight:400;color:#646970;margin-left:8px;vertical-align:middle;">
					<?php printf( /* translators: %s: plugin version number. */ esc_html__( 'v%s', 'twk-aeo-discovery' ), esc_html( TWKD_VERSION ) ); ?>
				</span>
			</h1>
			<p>
				<?php esc_html_e( 'Your sitemap index:', 'twk-aeo-discovery' ); ?>
				<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/sitemap.xml' ) ); ?></a>
			</p>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $tab_url( 'sitemap' ) ); ?>" class="nav-tab <?php echo 'sitemap' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sitemap', 'twk-aeo-discovery' ); ?></a>
				<a href="<?php echo esc_url( $tab_url( 'indexnow' ) ); ?>" class="nav-tab <?php echo 'indexnow' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'IndexNow', 'twk-aeo-discovery' ); ?></a>
				<a href="<?php echo esc_url( $tab_url( 'entity' ) ); ?>" class="nav-tab <?php echo 'entity' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Entity Authority', 'twk-aeo-discovery' ); ?></a>
				<a href="<?php echo esc_url( $tab_url( 'ai' ) ); ?>" class="nav-tab <?php echo 'ai' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'AI Engines', 'twk-aeo-discovery' ); ?></a>
				<a href="<?php echo esc_url( $tab_url( 'tools' ) ); ?>" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tools', 'twk-aeo-discovery' ); ?></a>
				<a href="<?php echo esc_url( $tab_url( 'diagnostics' ) ); ?>" class="nav-tab <?php echo 'diagnostics' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Diagnostics', 'twk-aeo-discovery' ); ?></a>
			</h2>

			<?php if ( in_array( $active_tab, array( 'sitemap', 'indexnow', 'entity', 'ai' ), true ) ) : ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( TWKD_OPTION ); ?>[twkd_tab_submitted]" value="<?php echo esc_attr( $active_tab ); ?>" />

				<?php if ( 'sitemap' === $active_tab ) : ?>
					<h2 class="title"><?php esc_html_e( 'Sitemap', 'twk-aeo-discovery' ); ?></h2>
					<p class="description"><?php esc_html_e( 'A dynamic XML sitemap index at /sitemap.xml, with per-object sub-sitemaps paginated. Submit /sitemap.xml once in Google Search Console; ongoing change notifications happen via IndexNow.', 'twk-aeo-discovery' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable sitemap', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Turn dynamic XML sitemap generation on or off. When off, /sitemap.xml returns nothing.', 'twk-aeo-discovery' ) ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[enable_sitemap]" value="1" <?php checked( twkd_get_option( 'enable_sitemap' ) ); ?> /> <?php esc_html_e( 'Generate a dynamic XML sitemap', 'twk-aeo-discovery' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Included post types', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Tick every post type that should appear in the sitemap. Custom post types (e.g. books, podcasts, case-studies) typically should be included. Attachments are never included.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<?php foreach ( $public_pt as $pt ) : ?>
									<label style="display:inline-block;margin-right:1em;">
										<input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $sel_pt, true ) ); ?> />
										<?php echo esc_html( $pt->labels->name ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Included taxonomies', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Tick every public taxonomy whose term archives should appear in the sitemap (categories, tags, custom taxonomy term pages).', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<?php foreach ( $public_tax as $tax ) : ?>
									<label style="display:inline-block;margin-right:1em;">
										<input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[taxonomies][]" value="<?php echo esc_attr( $tax->name ); ?>" <?php checked( in_array( $tax->name, $sel_tax, true ) ); ?> />
										<?php echo esc_html( $tax->labels->name ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'URLs per sitemap file', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'How many URLs each sub-sitemap holds before pagination kicks in. Default 2000 is fine for most sites. Example: a site with 8,000 posts at 2,000 per file produces 4 sub-sitemaps (page 1 through 4).', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="number" min="1" max="50000" name="<?php echo esc_attr( TWKD_OPTION ); ?>[per_page]" value="<?php echo esc_attr( twkd_get_option( 'per_page' ) ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Protocol maximum is 50,000.', 'twk-aeo-discovery' ); ?></span></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'robots.txt', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Three independent toggles for how the sitemap interacts with robots.txt and the built-in WordPress sitemap. Recommended: leave all three on.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[robots_sitemap]" value="1" <?php checked( twkd_get_option( 'robots_sitemap' ) ); ?> /> <?php esc_html_e( 'Add the sitemap line to the virtual robots.txt', 'twk-aeo-discovery' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[disable_core_sitemap]" value="1" <?php checked( twkd_get_option( 'disable_core_sitemap' ) ); ?> /> <?php esc_html_e( 'Disable the built-in WordPress sitemap (/wp-sitemap.xml) to avoid duplicates', 'twk-aeo-discovery' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[sitemap_stylesheet]" value="1" <?php checked( twkd_get_option( 'sitemap_stylesheet' ) ); ?> /> <?php esc_html_e( 'Show the sitemap as a styled, clickable page in the browser (cosmetic; crawlers ignore it)', 'twk-aeo-discovery' ); ?></label>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php elseif ( 'indexnow' === $active_tab ) : ?>
					<h2 class="title"><?php esc_html_e( 'Search engine notifications (IndexNow)', 'twk-aeo-discovery' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Google retired anonymous sitemap pings in 2023; submit your sitemap once in Google Search Console. IndexNow covers ongoing change notifications for Microsoft Bing, Yandex, Seznam.cz, Naver and other participating engines.', 'twk-aeo-discovery' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'IndexNow', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'When enabled, every publish and update fires an asynchronous notification to participating engines. Safe to leave on. Google does not participate but this does not hurt.', 'twk-aeo-discovery' ) ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[enable_indexnow]" value="1" <?php checked( twkd_get_option( 'enable_indexnow' ) ); ?> /> <?php esc_html_e( 'Notify engines automatically when content is published or updated', 'twk-aeo-discovery' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'API key', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Auto-generated on activation. Each site needs its own key. Engines validate ownership by fetching {key}.txt at your site root and confirming it returns the same key.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<code><?php echo esc_html( $key ); ?></code><br />
								<span class="description"><?php esc_html_e( 'Key file:', 'twk-aeo-discovery' ); ?> <a href="<?php echo esc_url( home_url( '/' . $key . '.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/' . $key . '.txt' ) ); ?></a></span>
								<?php if ( $last ) : ?>
									<br /><span class="description"><?php echo esc_html( sprintf( /* translators: 1: result, 2: human time diff. */ __( 'Last submission: %1$s, %2$s ago.', 'twk-aeo-discovery' ), $last['result'], human_time_diff( $last['time'] ) ) ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php elseif ( 'entity' === $active_tab ) : ?>
					<h2 class="title"><?php esc_html_e( 'Entity authority', 'twk-aeo-discovery' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Enriches the Organization and Person nodes your SEO plugin already outputs, adding the logo, sameAs (ORCID/ISNI/Wikidata/LinkedIn), job title, bio, and knowsAbout it leaves thin. Matching sameAs identifiers are what let answer engines reconcile your per-post author with the canonical homepage entity.', 'twk-aeo-discovery' ); ?></p>
					<?php
					$twkd_host = TWKD_Entity::instance()->detect_host();
					if ( '' !== $twkd_host ) {
						printf(
							'<div class="notice notice-info inline" style="margin:1em 0;padding:8px 12px;"><p style="margin:0;">%s</p></div>',
							sprintf(
								/* translators: %s: detected SEO plugin name. */
								esc_html__( 'Detected SEO plugin: %s. Its Organization and Person schema will be enriched with the values below.', 'twk-aeo-discovery' ),
								'<strong>' . esc_html( $twkd_host ) . '</strong>'
							)
						);
					} else {
						printf(
							'<div class="notice notice-warning inline" style="margin:1em 0;padding:8px 12px;"><p style="margin:0;">%s</p></div>',
							esc_html__( 'No supported SEO plugin detected (Slim SEO, Yoast, Rank Math, AIOSEO, The SEO Framework). The plugin will emit its own minimal Organization and Person graph on the front page instead, so entity authority still works.', 'twk-aeo-discovery' )
						);
					}
					?>
					<p>
						<a href="<?php echo esc_url( TWKD_Wizard::instance()->url( 0 ) ); ?>" class="button"><?php esc_html_e( 'Run setup wizard', 'twk-aeo-discovery' ); ?></a>
						<span class="description" style="margin-left:.5em;"><?php esc_html_e( 'A guided, non-destructive walk-through of every field below. Safe to re-run anytime.', 'twk-aeo-discovery' ); ?></span>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enrichment', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Master switch. When on, the values below get merged into your SEO plugin\'s Organization and Person schema sitewide (or emitted as a standalone graph if no SEO plugin is active). The suppress-front option is for sites with a hand-built homepage graph that should stand alone.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[enable_entity]" value="1" <?php checked( twkd_get_option( 'enable_entity' ) ); ?> /> <?php esc_html_e( 'Inject the values below into your SEO plugin\'s Organization and Person schema', 'twk-aeo-discovery' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[entity_suppress_front]" value="1" <?php checked( twkd_get_option( 'entity_suppress_front' ) ); ?> /> <?php esc_html_e( 'Suppress enrichment on the front page (use your hand-built homepage graph instead)', 'twk-aeo-discovery' ); ?></label>
							</td>
						</tr>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_id"><?php esc_html_e( 'Organization canonical @id', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Optional. The URL fragment id Slim SEO\'s Organization should use sitewide. Example: https://example.com/#business — when set, every Organization reference across the site is unified to this id. Leave blank to keep Slim SEO\'s default and reconcile by sameAs only.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<input type="url" id="twkd_org_id" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_id]" value="<?php echo esc_attr( twkd_get_option( 'org_id' ) ); ?>" placeholder="https://example.com/#business" />
								<p class="description"><?php esc_html_e( 'Optional. If set, Slim SEO\'s Organization @id is rewritten to this and all references repointed — so the whole site uses one Organization id, matching your homepage graph. Leave blank to keep Slim SEO\'s id and reconcile by sameAs only.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_logo"><?php esc_html_e( 'Organization logo URL', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'A direct URL to a square logo image (PNG or JPG, ideally 600x600 or larger). Example: https://example.com/wp-content/uploads/logo.png. Required for Knowledge Graph eligibility.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="url" id="twkd_org_logo" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_logo]" value="<?php echo esc_attr( twkd_get_option( 'org_logo' ) ); ?>" placeholder="https://" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_sameas"><?php esc_html_e( 'Organization sameAs', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Authority links for the organization, one URL per line. Examples: https://www.linkedin.com/company/example, https://twitter.com/example, https://www.crunchbase.com/organization/example. These corroborate the organization\'s identity across the web.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_org_sameas" class="large-text code" rows="5" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_sameas]"><?php echo esc_textarea( twkd_get_option( 'org_sameas' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One URL per line — your other sites and brand profiles (LinkedIn, X, BookBub, directory listings).', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_knows"><?php esc_html_e( 'Organization knowsAbout', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Topics the organization is known for, one per line or comma-separated. Examples: web design, search engine optimization, content marketing. Helps AI engines understand the organization\'s domain of expertise.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_org_knows" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_knowsabout]"><?php echo esc_textarea( twkd_get_option( 'org_knowsabout' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Topics, one per line or comma-separated. Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_contacts"><?php esc_html_e( 'Contact points', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Defined once, applied to the Organization on every page. One per line, format: contactType | url | description. Example: customer service | https://example.com/contact/ | Get in touch about support.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_org_contacts" class="large-text code" rows="4" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_contactpoints]"><?php echo esc_textarea( twkd_get_option( 'org_contactpoints' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One per line: contactType | url | description. English / US is assumed. Defined once here, applied to the Organization on every page.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_alt"><?php esc_html_e( 'Organization alternateName', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Other names the organization is known by, one per line. Examples: trade names, abbreviations, former names. Useful for matching legacy mentions.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_org_alt" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_altname]"><?php echo esc_textarea( twkd_get_option( 'org_altname' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Other names the organization is known by, one per line. Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_desc"><?php esc_html_e( 'Organization description', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'One to three sentences describing what the organization does. Example: A studio that designs and builds custom WordPress sites for service businesses.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_org_desc" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_description]"><?php echo esc_textarea( twkd_get_option( 'org_description' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_org_area"><?php esc_html_e( 'Organization areaServed', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'A country name, output as a Country in schema. Example: United States, United Kingdom, Canada.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<input type="text" id="twkd_org_area" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[org_areaserved]" value="<?php echo esc_attr( twkd_get_option( 'org_areaserved' ) ); ?>" placeholder="United States" />
								<p class="description"><?php esc_html_e( 'A country name, output as a Country. Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_name"><?php esc_html_e( 'Author display name', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'The display name that matches the post author. Example: Jane Smith. Only Person nodes with this name get enriched; leave blank to enrich every Person node Slim SEO emits.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<input type="text" id="twkd_author_name" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_name]" value="<?php echo esc_attr( twkd_get_option( 'author_name' ) ); ?>" />
								<p class="description"><?php esc_html_e( 'Must match the author name Slim SEO outputs (the post author\'s display name). Only Person nodes with this name get enriched; leave blank to enrich every Person node.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_id"><?php esc_html_e( 'Author canonical @id', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Optional but recommended. Example: https://example.com/#author. When set, every Person reference is unified to this id so each post\'s author is literally the same node as your homepage Person.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<input type="url" id="twkd_author_id" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_id]" value="<?php echo esc_attr( twkd_get_option( 'author_id' ) ); ?>" placeholder="https://example.com/#richard" />
								<p class="description"><?php esc_html_e( 'Optional but recommended. If set, Slim SEO\'s Person @id is rewritten to this and every author reference repointed — so each post\'s author is literally the same node as your homepage Person, not just reconciled by sameAs.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_alt"><?php esc_html_e( 'Author alternateName', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Other names you are known by, one per line. Useful for old records that used a different byline (e.g. maiden name, former pen name).', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_author_alt" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_altname]"><?php echo esc_textarea( twkd_get_option( 'author_altname' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Other names you are known by (for old-record matching), one per line. Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_url"><?php esc_html_e( 'Author URL', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'The canonical URL that represents the author. Usually your homepage or an author archive. Example: https://example.com/.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="url" id="twkd_author_url" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_url]" value="<?php echo esc_attr( twkd_get_option( 'author_url' ) ); ?>" placeholder="https://" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_given"><?php esc_html_e( 'Author given name', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'First name. Example: Jane.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="text" id="twkd_author_given" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_givenname]" value="<?php echo esc_attr( twkd_get_option( 'author_givenname' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_family"><?php esc_html_e( 'Author family name', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Last name. Example: Smith.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="text" id="twkd_author_family" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_familyname]" value="<?php echo esc_attr( twkd_get_option( 'author_familyname' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_bio"><?php esc_html_e( 'Author bio', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'A short biographical paragraph. Example: Jane Smith is a web designer with twenty years of experience building sites for service businesses. Overrides whatever Slim SEO pulled from the WordPress user profile.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_author_bio" class="large-text" rows="4" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_bio]"><?php echo esc_textarea( twkd_get_option( 'author_bio' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Overrides the description Slim SEO pulls from the user profile. (Updating the profile Biographical Info fixes it at the source too.)', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_job"><?php esc_html_e( 'Author job title', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Professional title. Examples: Founder, Senior Editor, Web Developer.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="text" id="twkd_author_job" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_jobtitle]" value="<?php echo esc_attr( twkd_get_option( 'author_jobtitle' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_image"><?php esc_html_e( 'Author image URL', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'A direct URL to a portrait image (square works best). Example: https://example.com/wp-content/uploads/jane.jpg.', 'twk-aeo-discovery' ) ); ?></th>
							<td><input type="url" id="twkd_author_image" class="regular-text" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_image]" value="<?php echo esc_attr( twkd_get_option( 'author_image' ) ); ?>" placeholder="https://" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_sameas"><?php esc_html_e( 'Author sameAs', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Authority links for the author, one URL per line. Strongest first: ORCID (https://orcid.org/0000-0000-0000-0000), Wikidata (https://www.wikidata.org/wiki/Q12345), ISNI (https://isni.org/isni/0000000000000000), Open Library (https://openlibrary.org/authors/OL123A), plus LinkedIn, Twitter, etc.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_author_sameas" class="large-text code" rows="6" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_sameas]"><?php echo esc_textarea( twkd_get_option( 'author_sameas' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One URL per line. The authority identifiers belong here — ORCID, ISNI, Wikidata, OpenLibrary — alongside LinkedIn and your profiles. These are what reconcile every post\'s author to your canonical entity.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_author_knows"><?php esc_html_e( 'Author knowsAbout', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Topics the author is known for, one per line or comma-separated. Examples: photography, machine learning, contract law, urban gardening. Three to ten entries is a reasonable range.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_author_knows" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[author_knowsabout]"><?php echo esc_textarea( twkd_get_option( 'author_knowsabout' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Topics, one per line or comma-separated. Optional.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php elseif ( 'ai' === $active_tab ) : ?>
					<h2 class="title"><?php esc_html_e( 'AI answer engines (AEO)', 'twk-aeo-discovery' ); ?></h2>
					<p class="description"><?php esc_html_e( 'A small llms.txt file at the site root tells AI answer engines (ChatGPT, Claude, Perplexity, and similar) which pages on your site they should treat as authoritative. Auto-generate from the intro and key pages below, or supply the full content yourself.', 'twk-aeo-discovery' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'llms.txt', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Publish /llms.txt and /llms-full.txt on your site. Both are simple text files that AI engines look for to understand what your site is about.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[enable_llms]" value="1" <?php checked( twkd_get_option( 'enable_llms' ) ); ?> /> <?php esc_html_e( 'Publish /llms.txt and /llms-full.txt', 'twk-aeo-discovery' ); ?></label>
								<?php if ( twkd_get_option( 'enable_llms' ) ) : ?>
									<br /><span class="description"><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/llms.txt' ) ); ?></a></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Content source', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Auto: builds the file from the intro and key-pages list below. Custom: serves whatever you type into the Custom content field verbatim. Use Custom if you want full control over what AI engines see.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<label style="display:block;margin-bottom:.4em;"><input type="radio" name="<?php echo esc_attr( TWKD_OPTION ); ?>[llms_mode]" value="auto" <?php checked( twkd_get_option( 'llms_mode' ), 'auto' ); ?> /> <?php esc_html_e( 'Auto-generate from site content (uses the intro and key pages below)', 'twk-aeo-discovery' ); ?></label>
								<label style="display:block;"><input type="radio" name="<?php echo esc_attr( TWKD_OPTION ); ?>[llms_mode]" value="custom" <?php checked( twkd_get_option( 'llms_mode' ), 'custom' ); ?> /> <?php esc_html_e( 'Custom content (serve the text I write below, verbatim)', 'twk-aeo-discovery' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_llms_custom"><?php esc_html_e( 'Custom content', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'The full llms.txt served verbatim when Content source is Custom. Plain text or Markdown. Example: a paragraph about your site, then a list of canonical URLs with one-line descriptions.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_llms_custom" class="large-text code" rows="16" name="<?php echo esc_attr( TWKD_OPTION ); ?>[llms_custom]"><?php echo esc_textarea( twkd_get_option( 'llms_custom' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'The full llms.txt, served verbatim when Content source is set to Custom. If you already had an llms.txt file, its contents were imported here on activation.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_llms_intro"><?php esc_html_e( 'Auto intro', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Used in Auto mode. A short factual paragraph about what the site covers. Example: A blog about urban gardening in temperate climates, with practical guides for small-space growers.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_llms_intro" class="large-text" rows="3" name="<?php echo esc_attr( TWKD_OPTION ); ?>[llms_intro]"><?php echo esc_textarea( twkd_get_option( 'llms_intro' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used in Auto-generate mode: a short, factual summary of who you are and what the site covers.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="twkd_llms_key"><?php esc_html_e( 'Auto key pages', 'twk-aeo-discovery' ); ?></label><?php $this->tip( __( 'Used in Auto mode. One page per line, as "Title | https://url" or just a URL. Example: About | https://example.com/about/ — these are the pages AI engines should treat as the authoritative source for your site.', 'twk-aeo-discovery' ) ); ?></th>
							<td>
								<textarea id="twkd_llms_key" class="large-text code" rows="4" name="<?php echo esc_attr( TWKD_OPTION ); ?>[llms_key_pages]"><?php echo esc_textarea( twkd_get_option( 'llms_key_pages' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used in Auto-generate mode. One per line, as "Title | https://url" or a bare URL.', 'twk-aeo-discovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'AI crawlers', 'twk-aeo-discovery' ); ?><?php $this->tip( __( 'Adds a friendly note to robots.txt indicating AI crawlers (GPTBot, ClaudeBot, PerplexityBot, etc.) are welcome. Does not block anything; just signals openness for citation.', 'twk-aeo-discovery' ) ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( TWKD_OPTION ); ?>[ai_welcome]" value="1" <?php checked( twkd_get_option( 'ai_welcome' ) ); ?> /> <?php esc_html_e( 'Add a note welcoming AI crawlers to robots.txt (does not block anything)', 'twk-aeo-discovery' ); ?></label></td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php endif; ?>

			</form>
			<?php elseif ( 'tools' === $active_tab ) : // tools tab ?>
				<h2 class="title"><?php esc_html_e( 'llms.txt file', 'twk-aeo-discovery' ); ?></h2>
				<?php $llms_file = ABSPATH . 'llms.txt'; ?>
				<p class="description">
					<?php
					if ( file_exists( $llms_file ) ) {
						esc_html_e( 'Status: a physical llms.txt exists and is served by your web server (the editable version is inactive until it is removed).', 'twk-aeo-discovery' );
					} else {
						esc_html_e( 'Status: served dynamically by the plugin. Write it to a file if you want the content to survive the plugin being deleted.', 'twk-aeo-discovery' );
					}
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="twkd_llms_write" />
					<?php wp_nonce_field( 'twkd_llms_file' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Write current content to llms.txt', 'twk-aeo-discovery' ); ?></button>
				</form>
				<?php if ( file_exists( $llms_file ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="twkd_llms_remove" />
						<?php wp_nonce_field( 'twkd_llms_file' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Remove physical llms.txt', 'twk-aeo-discovery' ); ?></button>
					</form>
				<?php endif; ?>

				<hr />
				<h2 class="title"><?php esc_html_e( 'Manual IndexNow submission', 'twk-aeo-discovery' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="twkd_indexnow_submit" />
					<?php wp_nonce_field( 'twkd_indexnow_submit' ); ?>
					<p>
						<button type="submit" name="twkd_scope" value="home" class="button"><?php esc_html_e( 'Submit homepage now', 'twk-aeo-discovery' ); ?></button>
						<button type="submit" name="twkd_scope" value="recent" class="button"><?php esc_html_e( 'Submit 100 most recent URLs', 'twk-aeo-discovery' ); ?></button>
					</p>
				</form>

				<hr />
				<h2 class="title"><?php esc_html_e( 'Move settings to another site', 'twk-aeo-discovery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Export these settings to a file, then import it on another site running this plugin. The IndexNow key is not included; each site keeps its own. After importing, review the site-specific fields: Organization name / description / logo / sameAs, the canonical @ids, the llms.txt content, and the sitemap post types.', 'twk-aeo-discovery' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="twkd_export" />
					<?php wp_nonce_field( 'twkd_export' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Export settings', 'twk-aeo-discovery' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:inline;margin-left:8px;">
					<input type="hidden" name="action" value="twkd_import" />
					<?php wp_nonce_field( 'twkd_import' ); ?>
					<input type="file" name="twkd_import_file" accept=".json,application/json" required />
					<button type="submit" class="button"><?php esc_html_e( 'Import settings', 'twk-aeo-discovery' ); ?></button>
				</form>

			<?php else : // diagnostics tab ?>
				<h2 class="title"><?php esc_html_e( 'Diagnostics', 'twk-aeo-discovery' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The most recent errors this plugin recorded — failed IndexNow submissions, file writes it could not complete, invalid setting imports, and sitemap problems. This lists only this plugin\'s own issues, not general site errors. An empty list is the healthy state.', 'twk-aeo-discovery' ); ?></p>
				<?php
				$twkd_log = get_option( 'twkd_error_log', array() );
				if ( empty( $twkd_log ) || ! is_array( $twkd_log ) ) :
					?>
					<p><strong><?php esc_html_e( 'No errors recorded. That is the good outcome.', 'twk-aeo-discovery' ); ?></strong></p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:900px;margin-top:1em;">
						<thead>
							<tr>
								<th style="width:160px;"><?php esc_html_e( 'When', 'twk-aeo-discovery' ); ?></th>
								<th style="width:110px;"><?php esc_html_e( 'Area', 'twk-aeo-discovery' ); ?></th>
								<th><?php esc_html_e( 'Detail', 'twk-aeo-discovery' ); ?></th>
								<th style="width:70px;"><?php esc_html_e( 'Count', 'twk-aeo-discovery' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $twkd_log as $twkd_entry ) : ?>
								<tr>
									<td>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: human-readable time difference, e.g. "2 hours". */
												__( '%s ago', 'twk-aeo-discovery' ),
												human_time_diff( (int) $twkd_entry['time'] )
											)
										);
										?>
									</td>
									<td><?php echo esc_html( isset( $twkd_entry['context'] ) ? $twkd_entry['context'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $twkd_entry['message'] ) ? $twkd_entry['message'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $twkd_entry['count'] ) ? (int) $twkd_entry['count'] : 1 ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
						<input type="hidden" name="action" value="twkd_clear_log" />
						<?php wp_nonce_field( 'twkd_clear_log' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'twk-aeo-discovery' ); ?></button>
					</form>
				<?php endif; ?>

			<?php endif; ?>

		</div>
		<?php
	}


	/**
	 * Download the current settings as a JSON file. The IndexNow key is a
	 * separate option and is intentionally not included, so each site keeps
	 * its own key.
	 */
	public function export_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'twkd_export' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'twk-aeo-discovery' ) );
		}

		$settings = get_option( TWKD_OPTION, array() );
		$payload  = array(
			'_twkd_export' => TWKD_VERSION,
			'_exported_at' => gmdate( 'c' ),
			'_source'      => home_url(),
			'settings'     => $settings,
		);

		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$filename = 'twk-discovery-settings-' . sanitize_file_name( $host ? $host : 'site' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Import settings from an uploaded export file, running every value
	 * through the normal sanitizer. The destination site's IndexNow key is
	 * untouched.
	 */
	public function import_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'twkd_import' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'twk-aeo-discovery' ) );
		}

		$redirect = admin_url( 'options-general.php?page=twk-aeo-discovery' );

		// $_FILES['twkd_import_file']['tmp_name'] is a server-generated temp filename,
		// not user-controlled input. Standard pattern documented at:
		// https://github.com/WordPress/WordPress-Coding-Standards/issues/1408
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_FILES['twkd_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['twkd_import_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_safe_redirect( add_query_arg( 'twkd_import', 'nofile', $redirect ) );
			exit;
		}

		$raw  = file_get_contents( $_FILES['twkd_import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reading an uploaded temp file.
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			twkd_log_error( 'Import', 'Settings import failed: the uploaded file was not a valid TWK AEO Discovery export.' );
			wp_safe_redirect( add_query_arg( 'twkd_import', 'invalid', $redirect ) );
			exit;
		}

		// Run the imported values through the same sanitizer the settings form uses.
		$clean = $this->sanitize( $data['settings'] );
		update_option( TWKD_OPTION, $clean );

		wp_safe_redirect( add_query_arg( 'twkd_import', 'ok', $redirect ) );
		exit;
	}
}
