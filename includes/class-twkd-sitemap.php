<?php
/**
 * Sitemap engine and front-end routing.
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Sitemap
 *
 * Dynamically generates a sitemap index and per-object sub-sitemaps, and
 * routes the IndexNow key file and llms.txt / llms-full.txt requests.
 */
class TWKD_Sitemap {

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_Sitemap|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return TWKD_Sitemap
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
		add_action( 'init', array( $this, 'register_rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'parse_request', array( $this, 'maybe_route' ) );
		add_action( 'template_redirect', array( $this, 'route' ) );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), PHP_INT_MAX, 2 );

		// Replace the WordPress core sitemap with ours, if requested.
		if ( twkd_get_option( 'disable_core_sitemap' ) ) {
			add_filter( 'wp_sitemaps_enabled', '__return_false' );
		}
	}

	/**
	 * Register all rewrite rules.
	 */
	public function register_rewrites() {
		if ( ! twkd_get_option( 'enable_sitemap' ) && ! twkd_get_option( 'enable_llms' ) && ! twkd_get_option( 'enable_indexnow' ) ) {
			return;
		}

		// Sitemap index.
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?twkd_route=index', 'top' );

		// Sub-sitemaps: sitemap-{object}-{page}.xml  e.g. sitemap-pt_post-1.xml.
		// The object-slug character class MUST include '-' so post-type and
		// taxonomy slugs containing hyphens (e.g. press-release, post-tag)
		// resolve. Regex backtracking correctly picks the trailing -N.xml as
		// the page number even when the slug itself contains hyphens.
		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$',
			'index.php?twkd_route=sub&twkd_object=$matches[1]&twkd_page=$matches[2]',
			'top'
		);

		// llms.txt / llms-full.txt for AI answer engines.
		add_rewrite_rule( '^llms\.txt$', 'index.php?twkd_route=llms', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?twkd_route=llms_full', 'top' );

		// IndexNow key verification file at the site root: {key}.txt.
		$key = twkd_get_indexnow_key();
		if ( $key ) {
			add_rewrite_rule( '^' . preg_quote( $key, '/' ) . '\.txt$', 'index.php?twkd_route=indexnow_key', 'top' );
		}
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'twkd_route';
		$vars[] = 'twkd_object';
		$vars[] = 'twkd_page';
		return $vars;
	}

	/**
	 * Primary dispatch: match the request path directly, independent of rewrite
	 * rules or query-var registration. Fires during WP::parse_request(), before
	 * the main query, so it is immune to stale/unflushed rewrite rules — the
	 * usual reason a custom .xml route falls through to the homepage.
	 *
	 * @param WP $wp The WordPress request object.
	 */
	public function maybe_route( $wp ) {
		$path = isset( $wp->request ) ? $wp->request : '';
		if ( '' === $path && isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		}
		$path = trim( $path, '/' );
		if ( '' === $path ) {
			return;
		}

		$key = twkd_get_indexnow_key();

		if ( 'sitemap.xml' === $path ) {
			$this->dispatch( 'index', '', 1 );
		} elseif ( 'sitemap.xsl' === $path ) {
			$this->dispatch( 'xsl', '', 1 );
		} elseif ( preg_match( '#^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$#', $path, $m ) ) {
			$this->dispatch( 'sub', $m[1], (int) $m[2] );
		} elseif ( 'llms.txt' === $path ) {
			$this->dispatch( 'llms', '', 1 );
		} elseif ( 'llms-full.txt' === $path ) {
			$this->dispatch( 'llms_full', '', 1 );
		} elseif ( $key && $path === $key . '.txt' ) {
			$this->dispatch( 'indexnow_key', '', 1 );
		}
	}

	/**
	 * Fallback dispatch via the rewrite query var (if a rule did match).
	 */
	public function route() {
		$route = get_query_var( 'twkd_route' );
		if ( ! $route ) {
			return;
		}
		$this->dispatch( $route, get_query_var( 'twkd_object' ), max( 1, (int) get_query_var( 'twkd_page' ) ) );
	}

	/**
	 * Output the requested resource and exit.
	 *
	 * @param string $route  Route key.
	 * @param string $object Sub-sitemap object slug.
	 * @param int    $page   Page number.
	 */
	private function dispatch( $route, $object, $page ) {
		switch ( $route ) {
			case 'index':
				$this->output_index();
				break;
			case 'sub':
				$this->output_sub( $object, max( 1, (int) $page ) );
				break;
			case 'xsl':
				header( 'Content-Type: application/xslt+xml; charset=UTF-8' );
				header( 'Cache-Control: no-cache, no-store, must-revalidate' );
				echo $this->build_xsl(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static stylesheet markup.
				exit;
			case 'indexnow_key':
				header( 'Content-Type: text/plain; charset=UTF-8' );
				header( 'Cache-Control: no-cache, no-store, must-revalidate' );
				echo esc_html( twkd_get_indexnow_key() );
				exit;
			case 'llms':
				header( 'Content-Type: text/plain; charset=UTF-8' );
				header( 'Cache-Control: no-cache, no-store, must-revalidate' );
				echo TWKD_LLMS::build( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain, admin-authored.
				exit;
			case 'llms_full':
				header( 'Content-Type: text/plain; charset=UTF-8' );
				header( 'Cache-Control: no-cache, no-store, must-revalidate' );
				echo TWKD_LLMS::build( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain, admin-authored.
				exit;
		}
	}

	/**
	 * Included public post types (filtered to the user's selection).
	 *
	 * @return string[]
	 */
	public function included_post_types() {
		$selected = (array) twkd_get_option( 'post_types' );
		$public   = get_post_types( array( 'public' => true ), 'names' );
		unset( $public['attachment'] );
		return array_values( array_intersect( $selected, array_keys( $public ) ) );
	}

	/**
	 * Included public taxonomies (filtered to the user's selection).
	 *
	 * @return string[]
	 */
	public function included_taxonomies() {
		$selected = (array) twkd_get_option( 'taxonomies' );
		$public   = get_taxonomies( array( 'public' => true ), 'names' );
		return array_values( array_intersect( $selected, array_values( $public ) ) );
	}

	/**
	 * Output the sitemap index, listing every sub-sitemap.
	 */
	private function output_index() {
		if ( ! twkd_get_option( 'enable_sitemap' ) ) {
			return;
		}

		$per_page = max( 1, (int) twkd_get_option( 'per_page' ) );
		$entries  = array();

		foreach ( $this->included_post_types() as $pt ) {
			$counts = wp_count_posts( $pt );
			$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;
			if ( $total < 1 ) {
				continue;
			}
			$pages   = (int) ceil( $total / $per_page );
			$lastmod = $this->latest_post_modified( $pt );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$entries[] = array(
					'loc'     => home_url( '/sitemap-pt_' . $pt . '-' . $i . '.xml' ),
					'lastmod' => $lastmod,
				);
			}
		}

		foreach ( $this->included_taxonomies() as $tax ) {
			$total = (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
			if ( $total < 1 ) {
				continue;
			}
			$pages = (int) ceil( $total / $per_page );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$entries[] = array(
					'loc'     => home_url( '/sitemap-tax_' . $tax . '-' . $i . '.xml' ),
					'lastmod' => '',
				);
			}
		}

		$this->send_xml_headers();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo $this->stylesheet_pi(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $entries as $e ) {
			echo "\t<sitemap>\n";
			echo "\t\t<loc>" . esc_url( $e['loc'] ) . "</loc>\n";
			if ( $e['lastmod'] ) {
				echo "\t\t<lastmod>" . esc_html( $e['lastmod'] ) . "</lastmod>\n";
			}
			echo "\t</sitemap>\n";
		}
		echo '</sitemapindex>';
		exit;
	}

	/**
	 * Output one sub-sitemap.
	 *
	 * @param string $object Object slug, e.g. pt_post or tax_category.
	 * @param int    $page   1-based page number.
	 */
	private function output_sub( $object, $page ) {
		if ( ! twkd_get_option( 'enable_sitemap' ) || ! $object ) {
			return;
		}

		$per_page = max( 1, (int) twkd_get_option( 'per_page' ) );
		$offset   = ( $page - 1 ) * $per_page;
		$urls     = array();

		if ( 0 === strpos( $object, 'pt_' ) ) {
			$pt = substr( $object, 3 );
			if ( ! in_array( $pt, $this->included_post_types(), true ) ) {
				return;
			}

			// Include the front page once, at the top of the first page of the first type.
			if ( 1 === $page && $this->should_prepend_home( $pt ) ) {
				$urls[] = array( 'loc' => home_url( '/' ), 'lastmod' => $this->latest_post_modified( $pt ) );
			}

			$q = new WP_Query(
				array(
					'post_type'              => $pt,
					'post_status'            => 'publish',
					'posts_per_page'         => $per_page,
					'offset'                 => $offset,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'ignore_sticky_posts'    => true,
				)
			);
			foreach ( $q->posts as $post ) {
				$loc = get_permalink( $post );
				if ( ! $loc ) {
					continue;
				}
				$urls[] = array(
					'loc'     => $loc,
					'lastmod' => get_post_modified_time( 'c', true, $post ),
				);
			}
		} elseif ( 0 === strpos( $object, 'tax_' ) ) {
			$tax = substr( $object, 4 );
			if ( ! in_array( $tax, $this->included_taxonomies(), true ) ) {
				return;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => true,
					'number'     => $per_page,
					'offset'     => $offset,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$loc = get_term_link( $term );
					if ( is_wp_error( $loc ) || ! $loc ) {
						continue;
					}
					$urls[] = array( 'loc' => $loc, 'lastmod' => '' );
				}
			} else {
				twkd_log_error( 'Sitemap', 'Could not load terms for taxonomy "' . $tax . '": ' . $terms->get_error_message() );
			}
		} else {
			return;
		}

		$this->send_xml_headers();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo $this->stylesheet_pi(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $urls as $u ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( $u['loc'] ) . "</loc>\n";
			if ( $u['lastmod'] ) {
				echo "\t\t<lastmod>" . esc_html( $u['lastmod'] ) . "</lastmod>\n";
			}
			echo "\t</url>\n";
		}
		echo '</urlset>';
		exit;
	}

	/**
	 * Whether the home URL should be added to this post type's first page.
	 *
	 * @param string $pt Post type.
	 * @return bool
	 */
	private function should_prepend_home( $pt ) {
		$types = $this->included_post_types();
		$first = reset( $types );
		if ( $pt !== $first ) {
			return false;
		}
		// Static front page is already a 'page' entry; don't duplicate.
		if ( 'page' === get_option( 'show_on_front' ) && in_array( 'page', $types, true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Latest modified date (ISO 8601, GMT) for a post type.
	 *
	 * @param string $pt Post type.
	 * @return string
	 */
	private function latest_post_modified( $pt ) {
		$q = new WP_Query(
			array(
				'post_type'              => $pt,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);
		if ( empty( $q->posts ) ) {
			return '';
		}
		return get_post_modified_time( 'c', true, $q->posts[0] );
	}

	/**
	 * Send XML response headers.
	 */
	private function send_xml_headers() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow', true );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		}
	}

	/**
	 * The XML stylesheet processing instruction, if the styled view is enabled.
	 *
	 * @return string
	 */
	private function stylesheet_pi() {
		if ( ! twkd_get_option( 'sitemap_stylesheet' ) ) {
			return '';
		}
		return '<?xml-stylesheet type="text/xsl" href="' . esc_url( home_url( '/sitemap.xsl' ) ) . '"?>' . "\n";
	}

	/**
	 * The XSL stylesheet that renders a sitemap as a readable HTML table.
	 * Handles both the sitemap index and individual url sets.
	 *
	 * @return string
	 */
	private function build_xsl() {
		$title = esc_html( get_bloginfo( 'name' ) );
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
	<xsl:output method="html" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html lang="en">
		<head>
			<meta charset="UTF-8"/>
			<meta name="viewport" content="width=device-width, initial-scale=1"/>
			<title><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> &#8212; XML Sitemap</title>
			<style>
				:root { --accent: #007bff; --ink: #1a1a1a; --muted: #6b7280; --line: #e5e7eb; --bg: #f7f8fa; }
				* { box-sizing: border-box; }
				body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
				.wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 64px; }
				header { border-bottom: 3px solid var(--accent); padding-bottom: 16px; margin-bottom: 8px; }
				h1 { font-size: 22px; font-weight: 600; margin: 0; }
				.sub { color: var(--muted); font-size: 13px; margin-top: 6px; }
				.count { color: var(--muted); font-size: 13px; margin: 14px 0; }
				table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
				th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #fafbfc; padding: 11px 14px; border-bottom: 1px solid var(--line); }
				td { padding: 11px 14px; border-bottom: 1px solid var(--line); vertical-align: top; word-break: break-word; }
				tr:last-child td { border-bottom: none; }
				tr:hover td { background: #fafbff; }
				a { color: var(--accent); text-decoration: none; }
				a:hover { text-decoration: underline; }
				.when { color: var(--muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
				.note { color: var(--muted); font-size: 12px; margin-top: 18px; }
			</style>
		</head>
		<body>
			<div class="wrap">
				<header>
					<h1><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></h1>
					<div class="sub">XML sitemap &#8212; for search engines and AI answer engines. The links below are clickable.</div>
				</header>

				<xsl:if test="s:sitemapindex">
					<div class="count">
						<xsl:value-of select="count(s:sitemapindex/s:sitemap)"/> sitemap(s) in this index.
					</div>
					<table>
						<tr><th>Sitemap</th><th>Last modified</th></tr>
						<xsl:for-each select="s:sitemapindex/s:sitemap">
							<tr>
								<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
								<td class="when"><xsl:value-of select="substring(s:lastmod,1,10)"/></td>
							</tr>
						</xsl:for-each>
					</table>
				</xsl:if>

				<xsl:if test="s:urlset">
					<div class="count">
						<xsl:value-of select="count(s:urlset/s:url)"/> URL(s) in this sitemap.
					</div>
					<table>
						<tr><th>URL</th><th>Last modified</th></tr>
						<xsl:for-each select="s:urlset/s:url">
							<tr>
								<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
								<td class="when"><xsl:value-of select="substring(s:lastmod,1,10)"/></td>
							</tr>
						</xsl:for-each>
					</table>
				</xsl:if>

				<div class="note">Generated by TWK AEO Discovery.</div>
			</div>
		</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
		<?php
		return ltrim( (string) ob_get_clean() );
	}


	/**
	 * Add the sitemap line (and an AI-welcome note) to the virtual robots.txt.
	 *
	 * Note: this only affects the robots.txt that WordPress generates. If a
	 * physical robots.txt file exists in the site root, WordPress serves that
	 * instead and this filter never runs.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether the site is set to be indexed.
	 * @return string
	 */
	public function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		if ( twkd_get_option( 'enable_sitemap' ) && twkd_get_option( 'robots_sitemap' ) ) {
			$output .= "\nSitemap: " . esc_url_raw( home_url( '/sitemap.xml' ) ) . "\n";
		}
		if ( twkd_get_option( 'ai_welcome' ) ) {
			$output .= "\n# AI answer engines are welcome to crawl this site.\n";
		}
		return $output;
	}
}
