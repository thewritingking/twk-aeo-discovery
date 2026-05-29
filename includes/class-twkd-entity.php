<?php
/**
 * Entity authority.
 *
 * Does NOT emit its own schema. Instead it enriches the Organization and Person
 * nodes that Slim SEO already outputs, via the slim_seo_schema_graph filter,
 * adding the logo, sameAs (including ORCID/ISNI/Wikidata), jobTitle, bio, and
 * knowsAbout that Slim SEO leaves thin. Matching sameAs identifiers are what let
 * answer engines reconcile the per-post author with the canonical homepage entity.
 *
 * If this plugin is deactivated, Slim SEO's basic schema simply returns —
 * nothing breaks. The plugin never owns the graph.
 *
 * @package LoweSitemapAEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Entity
 */
class TWKD_Entity {

	/**
	 * Singleton instance.
	 *
	 * @var TWKD_Entity|null
	 */
	private static $instance = null;

	/**
	 * Per-request cache of entity field values.
	 *
	 * @var array|null
	 */
	private $entity_cache = null;

	/**
	 * Get singleton.
	 *
	 * @return TWKD_Entity
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks for whichever supported SEO plugin is active. Each filter
	 * only fires when its plugin is present, so registering all of them is safe;
	 * an absent plugin's filter simply never runs.
	 *
	 * Slim SEO and Yoast both pass a node array (the @graph contents), so both
	 * use enrich(). Rank Math, AIOSEO and The SEO Framework use different graph
	 * shapes and are handled by their own adapter methods (added separately).
	 */
	public function hooks() {
		if ( ! twkd_entity_enabled() ) {
			return;
		}
		add_filter( 'slim_seo_schema_graph', array( $this, 'enrich' ), 20 );
		add_filter( 'wpseo_schema_graph', array( $this, 'enrich' ), 20 );
		add_filter( 'rank_math/json_ld', array( $this, 'enrich_walk' ), 99, 2 );
		add_filter( 'aioseo_schema_output', array( $this, 'enrich_walk' ), 20 );
		add_filter( 'the_seo_framework_schema_graph_data', array( $this, 'enrich_walk' ), 20 );

		// No supported SEO plugin? Then there is no graph to enrich, so emit our
		// own minimal entity graph on the front page. This is the only case where
		// the plugin outputs schema itself rather than enriching a host's.
		if ( '' === $this->detect_host() ) {
			add_action( 'wp_head', array( $this, 'output_standalone_graph' ), 20 );
		}
	}

	/**
	 * Build a minimal WebSite + Organization + Person entity graph from the
	 * configured fields. Returns the node array (used for standalone output and
	 * reused by the setup report's graph-health view).
	 *
	 * @return array Numeric array of schema nodes.
	 */
	public function build_entity_graph() {
		$d      = $this->get_entity_data();
		$home   = home_url( '/' );
		$org_id = '' !== $d['org_id'] ? $d['org_id'] : $home . '#organization';
		$nodes  = array();

		$nodes[] = array(
			'@type'     => 'WebSite',
			'@id'       => $home . '#website',
			'url'       => $home,
			'name'      => get_bloginfo( 'name' ),
			'publisher' => array( '@id' => $org_id ),
		);

		$org = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'url'   => $home,
			'name'  => '' !== $d['org_name'] ? $d['org_name'] : get_bloginfo( 'name' ),
		);
		if ( $d['org_logo'] ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $d['org_logo'],
			);
		}
		if ( $d['org_sameas'] ) {
			$org['sameAs'] = $d['org_sameas'];
		}
		if ( $d['org_knows'] ) {
			$org['knowsAbout'] = $d['org_knows'];
		}
		if ( $d['org_contacts'] ) {
			$org['contactPoint'] = $d['org_contacts'];
		}
		if ( $d['org_alt'] ) {
			$org['alternateName'] = $d['org_alt'];
		}
		if ( $d['org_desc'] ) {
			$org['description'] = $d['org_desc'];
		}
		if ( $d['org_area'] ) {
			$org['areaServed'] = array(
				'@type' => 'Country',
				'name'  => $d['org_area'],
			);
		}
		$nodes[] = $org;

		if ( '' !== $d['author_name'] ) {
			$person_id = '' !== $d['author_id'] ? $d['author_id'] : $home . '#person';
			$person    = array(
				'@type' => 'Person',
				'@id'   => $person_id,
				'name'  => $d['author_name'],
			);
			if ( $d['author_url'] ) {
				$person['url'] = $d['author_url'];
			}
			if ( $d['author_image'] ) {
				$person['image'] = $d['author_image'];
			}
			if ( $d['author_job'] ) {
				$person['jobTitle'] = $d['author_job'];
			}
			if ( $d['author_bio'] ) {
				$person['description'] = $d['author_bio'];
			}
			if ( $d['author_given'] ) {
				$person['givenName'] = $d['author_given'];
			}
			if ( $d['author_fam'] ) {
				$person['familyName'] = $d['author_fam'];
			}
			if ( $d['author_alt'] ) {
				$person['alternateName'] = $d['author_alt'];
			}
			if ( $d['author_knows'] ) {
				$person['knowsAbout'] = $d['author_knows'];
			}
			if ( $d['author_same'] ) {
				$person['sameAs'] = $d['author_same'];
			}
			$nodes[] = $person;
		}

		return $nodes;
	}

	/**
	 * Output the standalone entity graph as JSON-LD on the front page.
	 */
	public function output_standalone_graph() {
		// In standalone mode (no host SEO plugin active), emit the global
		// entity nodes on every page so AI agents and answer engines can
		// reconcile your Organization/Person/WebSite identity regardless of
		// which page they land on. The suppress_front option still skips the
		// front page specifically — for sites with hand-built homepage schema.
		if ( is_front_page() && twkd_get_option( 'entity_suppress_front' ) ) {
			return;
		}
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => $this->build_entity_graph(),
		);
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph ) . "</script>\n";
	}

	/**
	 * Detect the active supported SEO plugin (for the admin display and the
	 * standalone-fallback decision). Returns a human-readable name or ''.
	 *
	 * @return string
	 */
	public function detect_host() {
		if ( defined( 'SLIM_SEO_VER' ) || defined( 'SLIM_SEO_FILE' ) || function_exists( 'slim_seo' ) ) {
			return 'Slim SEO';
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
			return 'The SEO Framework';
		}
		return '';
	}

	/**
	 * Enrich (or, on the front page, suppress) a schema graph. Accepts either a
	 * numeric node array (Slim SEO, Yoast) or an associative wrapper with a
	 * '@graph' key, and returns the same shape it received.
	 *
	 * @param array $graph The schema graph.
	 * @return array
	 */
	public function enrich( $graph ) {
		$suppress = twkd_get_option( 'entity_suppress_front' ) && is_front_page();

		if ( is_array( $graph ) && isset( $graph['@graph'] ) && is_array( $graph['@graph'] ) ) {
			$graph['@graph'] = $suppress ? array() : $this->enrich_nodes( $graph['@graph'] );
			return $graph;
		}

		if ( $suppress ) {
			return array();
		}
		if ( ! is_array( $graph ) ) {
			return $graph;
		}
		return $this->enrich_nodes( $graph );
	}

	/**
	 * Enrich the Organization and Person nodes in a node array, then repoint any
	 * canonical @id changes through the whole array (so references follow).
	 *
	 * @param array $graph Numeric array of schema nodes.
	 * @return array
	 */
	private function enrich_nodes( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}
		$data   = $this->get_entity_data();
		$id_map = array();

		foreach ( $graph as &$node ) {
			$this->enrich_single_node( $node, $data, $id_map );
		}
		unset( $node );

		// Repoint every @id occurrence (node ids and references) to the canonical id.
		if ( $id_map ) {
			twkd_repoint_ids( $graph, $id_map );
		}
		return $graph;
	}

	/**
	 * Walk-based adapter for SEO plugins that pass a node structure which may
	 * key or nest nodes differently from a flat @graph. Used by Rank Math
	 * (rank_math/json_ld, keyed pieces, author nested under ProfilePage) and
	 * AIOSEO (aioseo_schema_output, graphs as multidimensional arrays). Walks the
	 * structure recursively, enriches any Organization or Person node found at
	 * any depth with the shared per-node logic, then repoints ids.
	 *
	 * @param array $data   The host plugin's schema structure.
	 * @param mixed $jsonld Optional second arg some filters pass (unused).
	 * @return array
	 */
	public function enrich_walk( $data, $jsonld = null ) {
		if ( ! twkd_entity_enabled() || ! is_array( $data ) ) {
			return $data;
		}
		if ( twkd_get_option( 'entity_suppress_front' ) && is_front_page() ) {
			return $data;
		}
		$entity = $this->get_entity_data();
		$id_map = array();
		$this->enrich_recursive( $data, $entity, $id_map );
		if ( $id_map ) {
			twkd_repoint_ids( $data, $id_map );
		}
		return $data;
	}

	/**
	 * Recursively visit every array that looks like a schema node (has @type),
	 * enriching Organization/Person nodes wherever they appear.
	 *
	 * @param array $data   Structure to walk, by reference.
	 * @param array $entity Entity field values.
	 * @param array $id_map Collected @id remaps, by reference.
	 */
	private function enrich_recursive( &$data, $entity, &$id_map ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		if ( isset( $data['@type'] ) ) {
			$this->enrich_single_node( $data, $entity, $id_map );
		}
		foreach ( $data as &$child ) {
			if ( is_array( $child ) ) {
				$this->enrich_recursive( $child, $entity, $id_map );
			}
		}
		unset( $child );
	}

	/**
	 * Enrich one node in place if it is the Organization or the author Person.
	 *
	 * @param array $node   Schema node, by reference.
	 * @param array $d      Entity field values from get_entity_data().
	 * @param array $id_map Collected @id remaps, by reference.
	 */
	private function enrich_single_node( &$node, $d, &$id_map ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			return;
		}
		$types = (array) $node['@type'];

		if ( in_array( 'Organization', $types, true ) ) {
			if ( $d['org_logo'] ) {
				$node['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $d['org_logo'],
				);
			}
			if ( $d['org_sameas'] ) {
				$node['sameAs'] = $d['org_sameas'];
			}
			if ( $d['org_knows'] ) {
				$node['knowsAbout'] = $d['org_knows'];
			}
			if ( $d['org_contacts'] ) {
				$node['contactPoint'] = $d['org_contacts'];
			}
			if ( $d['org_alt'] ) {
				$node['alternateName'] = $d['org_alt'];
			}
			if ( $d['org_desc'] ) {
				$node['description'] = $d['org_desc'];
			}
			if ( $d['org_area'] ) {
				$node['areaServed'] = array(
					'@type' => 'Country',
					'name'  => $d['org_area'],
				);
			}
			if ( $d['org_id'] && ! empty( $node['@id'] ) && $node['@id'] !== $d['org_id'] ) {
				$id_map[ $node['@id'] ] = $d['org_id'];
			}
		}

		$is_author = in_array( 'Person', $types, true )
			&& isset( $node['name'] )
			&& ( '' === $d['author_name'] || $node['name'] === $d['author_name'] );

		if ( $is_author ) {
			if ( $d['author_bio'] ) {
				$node['description'] = $d['author_bio'];
			}
			if ( $d['author_job'] ) {
				$node['jobTitle'] = $d['author_job'];
			}
			if ( $d['author_image'] ) {
				$node['image'] = $d['author_image'];
			}
			if ( $d['author_knows'] ) {
				$node['knowsAbout'] = $d['author_knows'];
			}
			if ( $d['author_same'] ) {
				$node['sameAs'] = $d['author_same'];
			}
			if ( $d['author_alt'] ) {
				$node['alternateName'] = $d['author_alt'];
			}
			if ( $d['author_url'] ) {
				$node['url'] = $d['author_url'];
			}
			if ( $d['author_given'] ) {
				$node['givenName'] = $d['author_given'];
			}
			if ( $d['author_fam'] ) {
				$node['familyName'] = $d['author_fam'];
			}
			if ( $d['author_id'] && ! empty( $node['@id'] ) && $node['@id'] !== $d['author_id'] ) {
				$id_map[ $node['@id'] ] = $d['author_id'];
			}
		}
	}

	/**
	 * Fetch and cache the entity field values for one request.
	 *
	 * @return array
	 */
	private function get_entity_data() {
		if ( null !== $this->entity_cache ) {
			return $this->entity_cache;
		}
		$this->entity_cache = array(
			'org_name'     => trim( (string) twkd_get_option( 'org_name' ) ),
			'org_logo'     => twkd_get_option( 'org_logo' ),
			'org_sameas'   => twkd_lines_to_urls( twkd_get_option( 'org_sameas' ) ),
			'org_knows'    => twkd_lines_to_list( twkd_get_option( 'org_knowsabout' ) ),
			'org_contacts' => twkd_parse_contactpoints( twkd_get_option( 'org_contactpoints' ) ),
			'org_alt'      => twkd_lines_to_list( twkd_get_option( 'org_altname' ) ),
			'org_desc'     => trim( (string) twkd_get_option( 'org_description' ) ),
			'org_area'     => trim( (string) twkd_get_option( 'org_areaserved' ) ),
			'org_id'       => trim( (string) twkd_get_option( 'org_id' ) ),
			'author_name'  => trim( (string) twkd_get_option( 'author_name' ) ),
			'author_bio'   => trim( (string) twkd_get_option( 'author_bio' ) ),
			'author_job'   => trim( (string) twkd_get_option( 'author_jobtitle' ) ),
			'author_image' => trim( (string) twkd_get_option( 'author_image' ) ),
			'author_same'  => twkd_lines_to_urls( twkd_get_option( 'author_sameas' ) ),
			'author_knows' => twkd_lines_to_list( twkd_get_option( 'author_knowsabout' ) ),
			'author_alt'   => twkd_lines_to_list( twkd_get_option( 'author_altname' ) ),
			'author_url'   => trim( (string) twkd_get_option( 'author_url' ) ),
			'author_given' => trim( (string) twkd_get_option( 'author_givenname' ) ),
			'author_fam'   => trim( (string) twkd_get_option( 'author_familyname' ) ),
			'author_id'    => trim( (string) twkd_get_option( 'author_id' ) ),
		);
		return $this->entity_cache;
	}
}

/**
 * Recursively replace any @id value found in $map with its canonical value,
 * covering both node identifiers and references to them.
 *
 * @param array $data Graph (or sub-tree), passed by reference.
 * @param array $map  Old @id => new @id.
 */
function twkd_repoint_ids( &$data, $map ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	foreach ( $data as $key => &$value ) {
		if ( '@id' === $key && is_string( $value ) && isset( $map[ $value ] ) ) {
			$value = $map[ $value ];
		} elseif ( is_array( $value ) ) {
			twkd_repoint_ids( $value, $map );
		}
	}
	unset( $value );
}

/**
 * Whether entity enrichment is on.
 *
 * @return bool
 */
function twkd_entity_enabled() {
	return (bool) twkd_get_option( 'enable_entity' );
}

/**
 * Parse a newline-separated textarea into a clean list of URLs.
 *
 * @param string $raw Raw textarea value.
 * @return string[]
 */
function twkd_lines_to_urls( $raw ) {
	$raw = (string) $raw;
	if ( '' === trim( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( preg_split( '/[\r\n]+/', $raw ) as $line ) {
		$url = esc_url_raw( trim( $line ) );
		if ( $url ) {
			$out[] = $url;
		}
	}
	return $out;
}

/**
 * Parse a textarea of "contactType | url | description" lines into an array of
 * schema.org ContactPoint objects.
 *
 * @param string $raw Raw value.
 * @return array[]
 */
function twkd_parse_contactpoints( $raw ) {
	$raw = (string) $raw;
	if ( '' === trim( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( preg_split( '/[\r\n]+/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$type  = isset( $parts[0] ) ? $parts[0] : '';
		$url   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
		$desc  = isset( $parts[2] ) ? $parts[2] : '';
		if ( '' === $type || '' === $url ) {
			continue;
		}
		$cp = array(
			'@type'       => 'ContactPoint',
			'contactType' => $type,
			'url'         => $url,
		);
		if ( '' !== $desc ) {
			$cp['description'] = $desc;
		}
		$cp['availableLanguage'] = 'English';
		$cp['areaServed']        = 'US';
		$out[] = $cp;
	}
	return $out;
}

/**
 * Parse a newline- or comma-separated textarea into a clean list of strings.
 *
 * @param string $raw Raw value.
 * @return string[]
 */
function twkd_lines_to_list( $raw ) {
	$raw = (string) $raw;
	if ( '' === trim( $raw ) ) {
		return array();
	}
	$parts = preg_split( '/[\r\n,]+/', $raw );
	$out   = array();
	foreach ( $parts as $p ) {
		$p = trim( $p );
		if ( '' !== $p ) {
			$out[] = $p;
		}
	}
	return $out;
}
