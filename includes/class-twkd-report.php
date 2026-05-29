<?php
/**
 * Post-wizard report: a clear page that shows what is configured, what is
 * missing, what actions remain, and how to complete each identifier the user
 * does not yet have. Per-identifier instructions come from TWKD_Instructions,
 * which keys off the same identifier registry the wizard uses.
 *
 * @package TWK_AEO_Discovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Report
 */
class TWKD_Report {

	const PAGE_SLUG = 'twk-aeo-discovery-report';

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_Report|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return TWKD_Report
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
		add_action( 'admin_post_twkd_report_download', array( $this, 'handle_download' ) );
	}

	/**
	 * Register the hidden admin page.
	 */
	public function register_page() {
		add_submenu_page(
			'',
			__( 'AEO Setup Report', 'twk-aeo-discovery' ),
			__( 'AEO Setup Report', 'twk-aeo-discovery' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Canonical URL for the report.
	 *
	 * @return string
	 */
	public function url() {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Data collection
	 * --------------------------------------------------------------------- */

	/**
	 * Snapshot of the current entity configuration plus the wizard state, used
	 * by every section so the report is internally consistent.
	 *
	 * @return array
	 */
	private function snapshot() {
		$entity = TWKD_Entity::instance();
		$state  = TWKD_Wizard::instance()->state();
		return array(
			'host'      => $entity->detect_host(),
			'graph'     => $entity->build_entity_graph(),
			'state'     => $state,
			'site_type' => $state['site_type'],
			'dont_have' => $state['dont_have'],
			'opts'      => array(
				'org_name'        => trim( (string) twkd_get_option( 'org_name' ) ),
				'org_id'          => trim( (string) twkd_get_option( 'org_id' ) ),
				'org_logo'        => trim( (string) twkd_get_option( 'org_logo' ) ),
				'org_sameas'      => twkd_lines_to_urls( twkd_get_option( 'org_sameas' ) ),
				'org_description' => trim( (string) twkd_get_option( 'org_description' ) ),
				'org_knowsabout'  => twkd_lines_to_list( twkd_get_option( 'org_knowsabout' ) ),
				'author_name'     => trim( (string) twkd_get_option( 'author_name' ) ),
				'author_id'       => trim( (string) twkd_get_option( 'author_id' ) ),
				'author_url'      => trim( (string) twkd_get_option( 'author_url' ) ),
				'author_image'    => trim( (string) twkd_get_option( 'author_image' ) ),
				'author_bio'      => trim( (string) twkd_get_option( 'author_bio' ) ),
				'author_sameas'   => twkd_lines_to_urls( twkd_get_option( 'author_sameas' ) ),
				'author_knowsabout' => twkd_lines_to_list( twkd_get_option( 'author_knowsabout' ) ),
				'suppress_front'  => (bool) twkd_get_option( 'entity_suppress_front' ),
				'enable_entity'   => (bool) twkd_get_option( 'enable_entity' ),
			),
		);
	}

	/**
	 * Graph-health validation. Returns an array of [level, message] tuples;
	 * levels are 'critical', 'warning', 'info'.
	 *
	 * @param array $s Snapshot.
	 * @return array
	 */
	private function health( $s ) {
		$out       = array();
		$is_org    = ( 'org' === $s['site_type'] || 'both' === $s['site_type'] );
		$is_person = ( 'person' === $s['site_type'] || 'both' === $s['site_type'] );

		if ( ! $s['opts']['enable_entity'] ) {
			$out[] = array( 'critical', __( 'Entity enrichment is currently OFF. Nothing below is being applied to your site\'s schema until you enable it.', 'twk-aeo-discovery' ) );
		}

		if ( $is_org ) {
			if ( '' === $s['opts']['org_name'] ) {
				$out[] = array( 'critical', __( 'Organization name is empty. Without a name the Organization node cannot be rendered correctly.', 'twk-aeo-discovery' ) );
			}
			if ( '' === $s['opts']['org_id'] ) {
				$out[] = array( 'warning', __( 'Organization canonical URL (@id) is empty. A stable @id helps search engines reconcile the same organization across pages and references.', 'twk-aeo-discovery' ) );
			}
			if ( '' === $s['opts']['org_logo'] ) {
				$out[] = array( 'warning', __( 'Organization logo URL is empty. Google requires a logo for Organization rich results.', 'twk-aeo-discovery' ) );
			}
			if ( count( $s['opts']['org_sameas'] ) < 2 ) {
				$out[] = array( 'critical', __( 'Organization has fewer than two sameAs identifiers. Two or more authoritative external profiles are what let answer engines reconcile the organization.', 'twk-aeo-discovery' ) );
			}
		}

		if ( $is_person ) {
			if ( '' === $s['opts']['author_name'] ) {
				$out[] = array( 'critical', __( 'Author name is empty. The Person node cannot be matched against your site\'s post bylines without it.', 'twk-aeo-discovery' ) );
			}
			if ( '' === $s['opts']['author_id'] ) {
				$out[] = array( 'warning', __( 'Author canonical URL (@id) is empty. A stable @id is what unifies the per-post Person with the homepage Person.', 'twk-aeo-discovery' ) );
			}
			if ( count( $s['opts']['author_sameas'] ) < 2 ) {
				$out[] = array( 'critical', __( 'Author has fewer than two sameAs identifiers. Two or more authoritative external profiles are the core entity-authority signal — this is the most important thing to fix.', 'twk-aeo-discovery' ) );
			}
			if ( '' === $s['opts']['author_image'] ) {
				$out[] = array( 'info', __( 'Author photo URL is empty. Rich results render better with one.', 'twk-aeo-discovery' ) );
			}
		}

		if ( '' === $s['host'] && $s['opts']['suppress_front'] ) {
			$out[] = array( 'info', __( 'No SEO plugin is active and "suppress front page" is on. The standalone entity graph will emit on every page except the front page. Turn off suppress to emit on the front page as well.', 'twk-aeo-discovery' ) );
		}

		if ( empty( $out ) ) {
			$out[] = array( 'info', __( 'No issues detected. Your entity graph is well-formed.', 'twk-aeo-discovery' ) );
		}
		return $out;
	}

	/**
	 * Pending identifiers list, derived from which identifiers in the registry
	 * have no matching URL in the user's stored sameAs lists. An identifier is
	 * "matched" if any stored URL shares its host with the registry example.
	 *
	 * @param array $s Snapshot.
	 * @return array Each: scope, key, label.
	 */
	private function pending_identifiers( $s ) {
		$pending = array();
		$scopes  = array();
		if ( 'org' === $s['site_type'] || 'both' === $s['site_type'] ) {
			$scopes[] = 'org';
		}
		if ( 'person' === $s['site_type'] || 'both' === $s['site_type'] ) {
			$scopes[] = 'person';
		}
		foreach ( $scopes as $scope ) {
			$urls = ( 'org' === $scope ) ? $s['opts']['org_sameas'] : $s['opts']['author_sameas'];
			$hosts = array();
			foreach ( $urls as $u ) {
				$h = wp_parse_url( $u, PHP_URL_HOST );
				if ( $h ) {
					$hosts[ $h ] = true;
				}
			}
			foreach ( TWKD_Wizard::identifiers( $scope ) as $id ) {
				$example_host = wp_parse_url( $id['example'], PHP_URL_HOST );
				if ( $example_host && ! isset( $hosts[ $example_host ] ) ) {
					$pending[] = array(
						'scope' => $scope,
						'key'   => $id['key'],
						'label' => $id['label'],
					);
				}
			}
		}
		return $pending;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render the report page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = $this->snapshot();
		echo '<div class="wrap" style="max-width:880px;">';
		printf( '<h1>%s</h1>', esc_html__( 'AEO setup report', 'twk-aeo-discovery' ) );

		// Top actions. "Done" is the primary call-to-action so the user has a
		// clear way out of the report back to the settings they manage.
		$done_url = admin_url( 'options-general.php?page=twk-aeo-discovery&tab=entity' );
		printf(
			'<p><a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a> <a href="%s" class="button">%s</a> <button type="button" class="button" id="twkd-copy-report">%s</button></p>',
			esc_url( $done_url ),
			esc_html__( 'Done', 'twk-aeo-discovery' ),
			esc_url( TWKD_Wizard::instance()->url( 0 ) ),
			esc_html__( 'Re-run wizard', 'twk-aeo-discovery' ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=twkd_report_download' ), 'twkd_report_download' ) ),
			esc_html__( 'Download as text', 'twk-aeo-discovery' ),
			esc_html__( 'Copy full report', 'twk-aeo-discovery' )
		);

		echo '<div id="twkd-report-body">';
		$this->section_summary( $s );
		$this->section_health( $s );
		$this->section_actions( $s );
		$this->section_instructions( $s );
		$this->section_verification( $s );
		$this->section_graph( $s );
		echo '</div>';

		// Bottom exit row — same Done/Re-run pair so a user who scrolled all
		// the way down doesn't have to scroll back up to find their way out.
		printf(
			'<p style="margin-top:1.5em;padding-top:1em;border-top:1px solid #ddd;"><a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a></p>',
			esc_url( $done_url ),
			esc_html__( 'Done', 'twk-aeo-discovery' ),
			esc_url( TWKD_Wizard::instance()->url( 0 ) ),
			esc_html__( 'Re-run wizard', 'twk-aeo-discovery' )
		);

		$this->copy_script();
		echo '</div>';
	}

	/**
	 * Section: at-a-glance summary.
	 *
	 * @param array $s Snapshot.
	 */
	private function section_summary( $s ) {
		printf( '<h2>%s</h2>', esc_html__( 'At a glance', 'twk-aeo-discovery' ) );
		echo '<table class="widefat striped" style="max-width:680px;"><tbody>';
		$rows = array(
			array( __( 'Enrichment', 'twk-aeo-discovery' ), $s['opts']['enable_entity'] ? __( 'On', 'twk-aeo-discovery' ) : __( 'Off', 'twk-aeo-discovery' ) ),
			array( __( 'Detected SEO plugin', 'twk-aeo-discovery' ), '' !== $s['host'] ? $s['host'] : __( 'None — emitting standalone graph', 'twk-aeo-discovery' ) ),
			array( __( 'Site represents', 'twk-aeo-discovery' ), $this->site_type_label( $s['site_type'] ) ),
			array( __( 'Organization sameAs count', 'twk-aeo-discovery' ), (string) count( $s['opts']['org_sameas'] ) ),
			array( __( 'Author sameAs count', 'twk-aeo-discovery' ), (string) count( $s['opts']['author_sameas'] ) ),
			array( __( 'Pending identifiers', 'twk-aeo-discovery' ), (string) count( $this->pending_identifiers( $s ) ) ),
		);
		foreach ( $rows as $r ) {
			printf( '<tr><th style="width:240px;text-align:left;">%s</th><td>%s</td></tr>', esc_html( $r[0] ), esc_html( $r[1] ) );
		}
		echo '</tbody></table>';
	}

	/**
	 * Human label for site type.
	 *
	 * @param string $type Site type.
	 * @return string
	 */
	private function site_type_label( $type ) {
		switch ( $type ) {
			case 'person':
				return __( 'A person', 'twk-aeo-discovery' );
			case 'org':
				return __( 'An organization', 'twk-aeo-discovery' );
			default:
				return __( 'Both a person and an organization', 'twk-aeo-discovery' );
		}
	}

	/**
	 * Section: graph health.
	 *
	 * @param array $s Snapshot.
	 */
	private function section_health( $s ) {
		printf( '<h2>%s</h2>', esc_html__( 'Graph health', 'twk-aeo-discovery' ) );
		$colors = array( 'critical' => '#a00', 'warning' => '#b45309', 'info' => '#0a0' );
		echo '<ul style="margin:0;padding:0;list-style:none;">';
		foreach ( $this->health( $s ) as $row ) {
			list( $level, $msg ) = $row;
			$color = isset( $colors[ $level ] ) ? $colors[ $level ] : '#333';
			printf(
				'<li style="margin:.5em 0;padding:.5em .75em;border-left:4px solid %s;background:#fafafa;"><strong style="color:%s;text-transform:uppercase;font-size:11px;">%s</strong>&nbsp; %s</li>',
				esc_attr( $color ),
				esc_attr( $color ),
				esc_html( strtoupper( $level ) ),
				esc_html( $msg )
			);
		}
		echo '</ul>';
	}

	/**
	 * Section: action items (pending identifiers + missing critical fields).
	 *
	 * @param array $s Snapshot.
	 */
	private function section_actions( $s ) {
		$pending = $this->pending_identifiers( $s );
		printf( '<h2>%s</h2>', esc_html__( 'Action items', 'twk-aeo-discovery' ) );
		if ( empty( $pending ) ) {
			printf( '<p>%s</p>', esc_html__( 'No identifiers flagged as pending. Anything missing in Graph Health above is the next thing to address.', 'twk-aeo-discovery' ) );
			return;
		}
		printf( '<p>%s</p>', esc_html__( 'These are identifiers from the recognized registry that we did not detect in your stored sameAs lists. Setup instructions for each are in the next section — ignore any you do not want.', 'twk-aeo-discovery' ) );
		echo '<ul>';
		foreach ( $pending as $p ) {
			$scope_label = ( 'org' === $p['scope'] ) ? __( 'Organization', 'twk-aeo-discovery' ) : __( 'You', 'twk-aeo-discovery' );
			printf(
				'<li>%s &mdash; %s</li>',
				esc_html( $p['label'] ),
				esc_html( $scope_label )
			);
		}
		echo '</ul>';
	}

	/**
	 * Section: setup instructions for each pending identifier.
	 *
	 * @param array $s Snapshot.
	 */
	private function section_instructions( $s ) {
		$pending = $this->pending_identifiers( $s );
		if ( empty( $pending ) ) {
			return;
		}
		printf( '<h2>%s</h2>', esc_html__( 'How to get each pending identifier', 'twk-aeo-discovery' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'These walk-throughs are accurate as of the plugin release date. Each service may change its signup flow; check the official site if anything looks different.', 'twk-aeo-discovery' ) );
		foreach ( $pending as $p ) {
			$inst = TWKD_Instructions::get( $p['scope'], $p['key'] );
			if ( null === $inst ) {
				continue;
			}
			printf( '<h3 style="margin-top:1.5em;border-bottom:1px solid #ddd;padding-bottom:.25em;">%s</h3>', esc_html( $inst['title'] ) );
			// Body is plain prose with double-newline paragraph breaks.
			foreach ( preg_split( "/\n\s*\n/", $inst['body'] ) as $para ) {
				printf( '<p>%s</p>', esc_html( trim( $para ) ) );
			}
		}
	}

	/**
	 * Section: external verification steps.
	 *
	 * @param array $s Snapshot.
	 */
	private function section_verification( $s ) {
		printf( '<h2>%s</h2>', esc_html__( 'How to verify what is being emitted', 'twk-aeo-discovery' ) );
		printf( '<p>%s</p>', esc_html__( 'Two external tools will show you exactly what schema your site is publishing right now. Run both after making changes.', 'twk-aeo-discovery' ) );
		echo '<ol>';
		printf(
			'<li><strong>%s</strong> &mdash; <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">search.google.com/test/rich-results</a>. %s</li>',
			esc_html__( 'Google Rich Results Test', 'twk-aeo-discovery' ),
			esc_html__( 'Paste your homepage URL. The tool fetches the page and shows which schema types Google recognized, with any warnings. Aim for zero errors; warnings about optional properties are fine.', 'twk-aeo-discovery' )
		);
		printf(
			'<li><strong>%s</strong> &mdash; <a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer">validator.schema.org</a>. %s</li>',
			esc_html__( 'Schema Markup Validator', 'twk-aeo-discovery' ),
			esc_html__( 'Run by Schema.org itself. Stricter than Google\'s tool because it checks against the full vocabulary, not only what Google currently uses. Useful for catching property typos.', 'twk-aeo-discovery' )
		);
		printf(
			'<li><strong>%s</strong> &mdash; <a href="https://%s" target="_blank" rel="noopener noreferrer">%s</a> %s</li>',
			esc_html__( 'View source on your homepage', 'twk-aeo-discovery' ),
			esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			esc_html__( 'Search the page source for "application/ld+json" — the JSON-LD blocks are what answer engines parse. Confirm your sameAs URLs and identifiers appear inside the Organization or Person node.', 'twk-aeo-discovery' )
		);
		echo '</ol>';
		printf( '<p class="description">%s</p>', esc_html__( 'External services (ORCID, Wikidata, LinkedIn, etc.) may change their schemes; re-running this report yearly catches drift.', 'twk-aeo-discovery' ) );
	}

	/**
	 * Section: current entity graph (raw view).
	 *
	 * @param array $s Snapshot.
	 */
	private function section_graph( $s ) {
		printf( '<h2>%s</h2>', esc_html__( 'Current entity graph', 'twk-aeo-discovery' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'The minimal Organization + Person graph the plugin would emit standalone, or merge into your SEO plugin\'s graph. This is reference data; the live page may have additional nodes from your SEO plugin.', 'twk-aeo-discovery' ) );
		$json = wp_json_encode(
			array( '@context' => 'https://schema.org', '@graph' => $s['graph'] ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		// Dedicated copy-the-JSON button — paste straight into the Rich Results
		// Test or validator.schema.org without grabbing the rest of the report.
		printf(
			'<p><button type="button" class="button" id="twkd-copy-graph">%s</button></p>',
			esc_html__( 'Copy graph JSON', 'twk-aeo-discovery' )
		);
		printf(
			'<pre id="twkd-graph-json" style="background:#f6f7f7;padding:1em;overflow:auto;max-height:340px;font-size:12px;">%s</pre>',
			esc_html( (string) $json )
		);
	}

	/**
	 * Inline JS for the copy-to-clipboard button. Pulls innerText of the report
	 * body so the user gets a clean text version of everything they see.
	 */
	private function copy_script() {
		?>
		<script>
		(function(){
			var COPIED    = '<?php echo esc_js( __( 'Copied', 'twk-aeo-discovery' ) ); ?>';
			var FULL      = '<?php echo esc_js( __( 'Copy full report', 'twk-aeo-discovery' ) ); ?>';
			var GRAPH     = '<?php echo esc_js( __( 'Copy graph JSON', 'twk-aeo-discovery' ) ); ?>';

			function copyText(text, btn, restore) {
				var done = function(){
					btn.textContent = COPIED;
					setTimeout(function(){ btn.textContent = restore; }, 2000);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done);
				} else {
					var ta = document.createElement('textarea');
					ta.value = text;
					document.body.appendChild(ta);
					ta.select();
					try { document.execCommand('copy'); } catch (e) {}
					document.body.removeChild(ta);
					done();
				}
			}

			var reportBtn = document.getElementById('twkd-copy-report');
			var reportBody = document.getElementById('twkd-report-body');
			if (reportBtn && reportBody) {
				reportBtn.addEventListener('click', function(){
					copyText(reportBody.innerText || reportBody.textContent || '', reportBtn, FULL);
				});
			}

			var graphBtn = document.getElementById('twkd-copy-graph');
			var graphPre = document.getElementById('twkd-graph-json');
			if (graphBtn && graphPre) {
				graphBtn.addEventListener('click', function(){
					copyText(graphPre.textContent || graphPre.innerText || '', graphBtn, GRAPH);
				});
			}
		})();
		</script>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Download
	 * --------------------------------------------------------------------- */

	/**
	 * Download the report as a plain-text file.
	 */
	public function handle_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_report_download' );

		$s    = $this->snapshot();
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		$text = $this->build_text_report( $s, $site );

		$filename = 'aeo-setup-report-' . sanitize_file_name( $site ) . '-' . gmdate( 'Y-m-d' ) . '.txt';
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $text ) );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text download.
		exit;
	}

	/**
	 * Build the plain-text version of the report.
	 *
	 * @param array  $s    Snapshot.
	 * @param string $site Host.
	 * @return string
	 */
	private function build_text_report( $s, $site ) {
		$lines   = array();
		$lines[] = 'AEO setup report — ' . $site;
		$lines[] = 'Generated ' . gmdate( 'Y-m-d H:i' ) . ' UTC';
		$lines[] = str_repeat( '=', 60 );
		$lines[] = '';

		$lines[] = 'AT A GLANCE';
		$lines[] = str_repeat( '-', 60 );
		$lines[] = 'Enrichment:          ' . ( $s['opts']['enable_entity'] ? 'On' : 'Off' );
		$lines[] = 'Detected SEO plugin: ' . ( '' !== $s['host'] ? $s['host'] : 'None (emitting standalone graph)' );
		$lines[] = 'Site represents:     ' . $this->site_type_label( $s['site_type'] );
		$lines[] = 'Org sameAs count:    ' . count( $s['opts']['org_sameas'] );
		$lines[] = 'Author sameAs count: ' . count( $s['opts']['author_sameas'] );
		$lines[] = 'Pending identifiers: ' . count( $this->pending_identifiers( $s ) );
		$lines[] = '';

		$lines[] = 'GRAPH HEALTH';
		$lines[] = str_repeat( '-', 60 );
		foreach ( $this->health( $s ) as $row ) {
			$lines[] = '[' . strtoupper( $row[0] ) . '] ' . $row[1];
		}
		$lines[] = '';

		$pending = $this->pending_identifiers( $s );
		if ( ! empty( $pending ) ) {
			$lines[] = 'ACTION ITEMS — IDENTIFIERS TO ACQUIRE';
			$lines[] = str_repeat( '-', 60 );
			foreach ( $pending as $p ) {
				$lines[] = '- ' . $p['label'] . ' (' . ( 'org' === $p['scope'] ? 'organization' : 'person' ) . ')';
			}
			$lines[] = '';

			$lines[] = 'HOW TO GET EACH PENDING IDENTIFIER';
			$lines[] = str_repeat( '-', 60 );
			foreach ( $pending as $p ) {
				$inst = TWKD_Instructions::get( $p['scope'], $p['key'] );
				if ( null === $inst ) {
					continue;
				}
				$lines[] = '';
				$lines[] = '## ' . $inst['title'];
				$lines[] = '';
				$lines[] = wordwrap( $inst['body'], 78, "\n", false );
			}
			$lines[] = '';
		}

		$lines[] = 'VERIFICATION';
		$lines[] = str_repeat( '-', 60 );
		$lines[] = '1. Google Rich Results Test: https://search.google.com/test/rich-results';
		$lines[] = '2. Schema Markup Validator:  https://validator.schema.org/';
		$lines[] = '3. View source on your homepage and search for "application/ld+json".';
		$lines[] = '';
		$lines[] = 'External identifier services may change; re-run this report yearly.';
		$lines[] = '';

		return implode( "\n", $lines );
	}
}
