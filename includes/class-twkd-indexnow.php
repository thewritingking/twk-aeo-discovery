<?php
/**
 * IndexNow notifications.
 *
 * Notifies IndexNow-participating engines (Microsoft Bing, Yandex, Seznam.cz,
 * Naver and others) whenever content is published or updated. This is the live
 * replacement for the old sitemap "ping" endpoints, which Google retired in
 * 2023 and Bing retired in favour of IndexNow.
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_IndexNow
 */
class TWKD_IndexNow {

	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_IndexNow|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return TWKD_IndexNow
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
		if ( twkd_get_option( 'enable_indexnow' ) ) {
			add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		}
		add_action( 'admin_post_twkd_indexnow_submit', array( $this, 'handle_manual_submit' ) );
		// WP-Cron handler: the on-publish notification is deferred to this hook so
		// the publish/update request is never blocked by the IndexNow round-trip.
		add_action( 'twkd_indexnow_event', array( $this, 'submit' ) );
	}

	/**
	 * Submit a post's URL whenever it becomes (or stays) published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, TWKD_Sitemap::instance()->included_post_types(), true ) ) {
			return;
		}
		$url = get_permalink( $post );
		if ( $url ) {
			// Defer to WP-Cron so the publish/update is not blocked by the
			// IndexNow HTTP round-trip. Dedupe so rapid re-saves of the same
			// URL do not queue multiple notifications.
			$args = array( array( $url ) );
			if ( ! wp_next_scheduled( 'twkd_indexnow_event', $args ) ) {
				wp_schedule_single_event( time() + 30, 'twkd_indexnow_event', $args );
			}
		}
	}

	/**
	 * Submit one or more URLs to IndexNow.
	 *
	 * @param string[] $urls URLs (max 10,000 per request per the protocol).
	 * @return string 'success' or 'error:...'.
	 */
	public function submit( array $urls ) {
		$urls = array_values( array_filter( array_unique( $urls ) ) );
		if ( empty( $urls ) ) {
			return 'error:NoUrls';
		}
		$urls = array_slice( $urls, 0, 10000 );

		$key  = twkd_get_indexnow_key();
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		$body = wp_json_encode(
			array(
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => home_url( '/' . $key . '.txt' ),
				'urlList'     => $urls,
			)
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result = 'error:' . $response->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			switch ( $code ) {
				case 200:
				case 202:
					$result = 'success';
					break;
				case 400:
					$result = 'error:InvalidRequest';
					break;
				case 403:
					$result = 'error:KeyNotValid';
					break;
				case 422:
					$result = 'error:UrlDoesNotMatchHostOrKey';
					break;
				case 429:
					$result = 'error:TooManyRequests';
					break;
				default:
					$result = 'error:HTTP' . $code;
			}
		}

		update_option(
			'twkd_indexnow_last',
			array(
				'time'   => time(),
				'count'  => count( $urls ),
				'result' => $result,
			),
			false
		);

		if ( 0 === strpos( $result, 'error:' ) ) {
			twkd_log_error( 'IndexNow', $result . ' (' . count( $urls ) . ' URL(s) submitted)' );
		}

		return $result;
	}

	/**
	 * Handle the "Submit now" buttons on the settings screen.
	 */
	public function handle_manual_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twk-aeo-discovery' ) );
		}
		check_admin_referer( 'twkd_indexnow_submit' );

		$scope = isset( $_POST['twkd_scope'] ) ? sanitize_key( wp_unslash( $_POST['twkd_scope'] ) ) : 'home';
		$urls  = array( home_url( '/' ) );

		if ( 'recent' === $scope ) {
			$recent = get_posts(
				array(
					'post_type'        => TWKD_Sitemap::instance()->included_post_types(),
					'post_status'      => 'publish',
					'numberposts'      => 100,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'suppress_filters' => false,
				)
			);
			foreach ( $recent as $post ) {
				$link = get_permalink( $post );
				if ( $link ) {
					$urls[] = $link;
				}
			}
		}

		$this->submit( $urls );

		wp_safe_redirect( add_query_arg( 'twkd_submitted', '1', admin_url( 'options-general.php?page=twk-aeo-discovery' ) ) );
		exit;
	}
}
