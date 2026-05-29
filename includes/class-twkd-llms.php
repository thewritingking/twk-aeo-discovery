<?php
/**
 * llms.txt / llms-full.txt generation.
 *
 * A proposed convention for giving AI answer engines a clean, structured
 * summary of a site at /llms.txt (and an expanded /llms-full.txt). Adoption is
 * still uneven and the format is debated, but it is cheap to publish and costs
 * nothing if engines ignore it.
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_LLMS
 */
class TWKD_LLMS {

	/**
	 * Build the llms.txt body.
	 *
	 * @param bool $full Whether to build the expanded llms-full.txt.
	 * @return string
	 */
	public static function build( $full = false ) {
		// Custom mode: serve the editable field verbatim.
		if ( 'custom' === twkd_get_option( 'llms_mode' ) ) {
			return (string) twkd_get_option( 'llms_custom' );
		}

		$name = get_bloginfo( 'name' );
		$desc = get_bloginfo( 'description' );

		$out  = '# ' . $name . "\n\n";
		if ( $desc ) {
			$out .= '> ' . $desc . "\n\n";
		}

		$intro = trim( (string) twkd_get_option( 'llms_intro' ) );
		if ( '' !== $intro ) {
			$out .= $intro . "\n\n";
		}

		// Key pages, defined by the site owner as "Title|URL" or bare URLs.
		$key_pages = self::parse_key_pages();
		if ( ! empty( $key_pages ) ) {
			$out .= "## Key pages\n\n";
			foreach ( $key_pages as $page ) {
				$out .= '- [' . $page['title'] . '](' . $page['url'] . ")\n";
			}
			$out .= "\n";
		}

		// Recent content.
		$limit  = $full ? 100 : 25;
		$recent = get_posts(
			array(
				'post_type'        => TWKD_Sitemap::instance()->included_post_types(),
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		if ( ! empty( $recent ) ) {
			$out .= "## Recent content\n\n";
			foreach ( $recent as $post ) {
				$title = wp_strip_all_tags( get_the_title( $post ) );
				$url   = get_permalink( $post );
				$out  .= '- [' . $title . '](' . $url . ')';
				if ( $full ) {
					$summary = has_excerpt( $post )
						? get_the_excerpt( $post )
						: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
					if ( $summary ) {
						$out .= ': ' . $summary;
					}
				}
				$out .= "\n";
			}
			$out .= "\n";
		}

		$out .= '## Sitemap' . "\n\n";
		$out .= home_url( '/sitemap.xml' ) . "\n";

		return $out;
	}

	/**
	 * Parse the configured key pages list.
	 *
	 * @return array[] Array of { title, url }.
	 */
	private static function parse_key_pages() {
		$raw = trim( (string) twkd_get_option( 'llms_key_pages' ) );
		if ( '' === $raw ) {
			return array();
		}
		$lines = preg_split( '/[\r\n]+/', $raw );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '|' ) ) {
				list( $title, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
			} else {
				$url   = $line;
				$title = $url;
			}
			$url = esc_url_raw( $url );
			if ( $url ) {
				$out[] = array(
					'title' => $title ? $title : $url,
					'url'   => $url,
				);
			}
		}
		return $out;
	}
}
