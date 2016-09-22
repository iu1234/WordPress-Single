<?php
/**
 * Rewrite API: WP_Rewrite class
 *
 * @package WordPress
 * @subpackage Rewrite
 * @since 1.5.0
 */

class WP_Rewrite {

	public $permalink_structure;

	public $use_trailing_slashes;

	var $author_base = 'author';

	var $author_structure;

	var $date_structure;

	var $page_structure;

	var $search_base = 'search';

	var $search_structure;

	var $comments_base = 'comments';

	public $pagination_base = 'page';

	var $comments_pagination_base = 'comment-page';

	var $feed_base = 'feed';

	public $front;

	public $root = '';

	public $index = 'index.php';

	var $matches = '';

	var $rules;

	var $extra_rules = array();

	var $extra_rules_top = array();

	var $non_wp_rules = array();

	var $extra_permastructs = array();

	var $endpoints;

	public $use_verbose_rules = false;

	public $use_verbose_page_rules = true;

	var $rewritecode = array( '%year%',	'%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%postname%', '%post_id%',	'%author%',	'%pagename%', '%search%' );

	var $rewritereplace = array(
		'([0-9]{4})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([^/]+)',
		'([0-9]+)',
		'([^/]+)',
		'([^/]+?)',
		'(.+)'
	);

	var $queryreplace = array( 'year=',	'monthnum=', 'day=', 'hour=', 'minute=', 'second=',	'name=', 'p=', 'author_name=', 'pagename=',	's=' );

	public function __construct() {
		$this->init();
	}
	
	public function init() {
		$this->extra_rules = $this->non_wp_rules = $this->endpoints = array();
		$this->permalink_structure = get_option('permalink_structure');
		$this->front = substr($this->permalink_structure, 0, strpos($this->permalink_structure, '%'));
		$this->root = '';
		if ( $this->using_index_permalinks() )
			$this->root = $this->index . '/';
		unset($this->author_structure);
		unset($this->date_structure);
		unset($this->page_structure);
		unset($this->search_structure);
		$this->use_trailing_slashes = ( '/' == substr($this->permalink_structure, -1, 1) );
		if ( preg_match("/^[^%]*%(?:postname|category|tag|author)%/", $this->permalink_structure) )
			 $this->use_verbose_page_rules = true;
		else
			$this->use_verbose_page_rules = false;
	}

	public function using_permalinks() {
		return ! empty($this->permalink_structure);
	}

	public function using_index_permalinks() {
		if ( empty( $this->permalink_structure ) ) {
			return false;
		}
		return preg_match( '#^/*' . $this->index . '#', $this->permalink_structure );
	}

	public function using_mod_rewrite_permalinks() {
		return $this->using_permalinks() && ! $this->using_index_permalinks();
	}

	public function preg_index($number) {
		$match_prefix = '$';
		$match_suffix = '';

		if ( ! empty($this->matches) ) {
			$match_prefix = '$' . $this->matches . '[';
			$match_suffix = ']';
		}

		return "$match_prefix$number$match_suffix";
	}

	public function page_uri_index() {
		global $wpdb;

		$pages = $wpdb->get_results("SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'page' AND post_status != 'auto-draft'");
		$posts = get_page_hierarchy( $pages );

		if ( !$posts )
			return array( array(), array() );

		$posts = array_reverse($posts, true);

		$page_uris = array();
		$page_attachment_uris = array();

		foreach ( $posts as $id => $post ) {
			$uri = get_page_uri($id);
			$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = %d", $id ));
			if ( !empty($attachments) ) {
				foreach ( $attachments as $attachment ) {
					$attach_uri = get_page_uri($attachment->ID);
					$page_attachment_uris[$attach_uri] = $attachment->ID;
				}
			}

			$page_uris[$uri] = $id;
		}

		return array( $page_uris, $page_attachment_uris );
	}

	public function page_rewrite_rules() {
		$this->add_rewrite_tag( '%pagename%', '(.?.+?)', 'pagename=' );
		return $this->generate_rewrite_rules( $this->get_page_permastruct(), EP_PAGES, true, true, false, false );
	}

	public function get_date_permastruct() {
		if ( isset($this->date_structure) )
			return $this->date_structure;

		if ( empty($this->permalink_structure) ) {
			$this->date_structure = '';
			return false;
		}

		$endians = array('%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%');

		$this->date_structure = '';
		$date_endian = '';

		foreach ( $endians as $endian ) {
			if ( false !== strpos($this->permalink_structure, $endian) ) {
				$date_endian= $endian;
				break;
			}
		}

		if ( empty($date_endian) )
			$date_endian = '%year%/%monthnum%/%day%';

		$front = $this->front;
		preg_match_all('/%.+?%/', $this->permalink_structure, $tokens);
		$tok_index = 1;
		foreach ( (array) $tokens[0] as $token) {
			if ( '%post_id%' == $token && ($tok_index <= 3) ) {
				$front = $front . 'date/';
				break;
			}
			$tok_index++;
		}

		$this->date_structure = $front . $date_endian;

		return $this->date_structure;
	}

	public function get_year_permastruct() {
		$structure = $this->get_date_permastruct();

		if ( empty($structure) )
			return false;

		$structure = str_replace('%monthnum%', '', $structure);
		$structure = str_replace('%day%', '', $structure);
		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	public function get_month_permastruct() {
		$structure = $this->get_date_permastruct();

		if ( empty($structure) )
			return false;

		$structure = str_replace('%day%', '', $structure);
		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	public function get_day_permastruct() {
		return $this->get_date_permastruct();
	}

	public function get_category_permastruct() {
		return $this->get_extra_permastruct('category');
	}

	public function get_tag_permastruct() {
		return $this->get_extra_permastruct('post_tag');
	}

	public function get_extra_permastruct($name) {
		if ( empty($this->permalink_structure) )
			return false;

		if ( isset($this->extra_permastructs[$name]) )
			return $this->extra_permastructs[$name]['struct'];

		return false;
	}

	public function get_author_permastruct() {
		if ( isset($this->author_structure) )
			return $this->author_structure;

		if ( empty($this->permalink_structure) ) {
			$this->author_structure = '';
			return false;
		}

		$this->author_structure = $this->front . $this->author_base . '/%author%';

		return $this->author_structure;
	}

	public function get_search_permastruct() {
		if ( isset($this->search_structure) )
			return $this->search_structure;

		if ( empty($this->permalink_structure) ) {
			$this->search_structure = '';
			return false;
		}

		$this->search_structure = $this->root . $this->search_base . '/%search%';

		return $this->search_structure;
	}

	public function get_page_permastruct() {
		if ( isset($this->page_structure) )
			return $this->page_structure;

		if (empty($this->permalink_structure)) {
			$this->page_structure = '';
			return false;
		}

		$this->page_structure = $this->root . '%pagename%';

		return $this->page_structure;
	}

	public function add_rewrite_tag( $tag, $regex, $query ) {
		$position = array_search( $tag, $this->rewritecode );
		if ( false !== $position && null !== $position ) {
			$this->rewritereplace[ $position ] = $regex;
			$this->queryreplace[ $position ] = $query;
		} else {
			$this->rewritecode[] = $tag;
			$this->rewritereplace[] = $regex;
			$this->queryreplace[] = $query;
		}
	}


	public function remove_rewrite_tag( $tag ) {
		$position = array_search( $tag, $this->rewritecode );
		if ( false !== $position && null !== $position ) {
			unset( $this->rewritecode[ $position ] );
			unset( $this->rewritereplace[ $position ] );
			unset( $this->queryreplace[ $position ] );
		}
	}

	public function generate_rewrite_rules($permalink_structure, $ep_mask = EP_NONE, $paged = true, $feed = true, $forcomments = false, $walk_dirs = true, $endpoints = true) {

		$pageregex = $this->pagination_base . '/?([0-9]{1,})/?$';
		$commentregex = $this->comments_pagination_base . '-([0-9]{1,})/?$';
		$embedregex = 'embed/?$';

		if ( $endpoints ) {
			$ep_query_append = array ();
			foreach ( (array) $this->endpoints as $endpoint) {
				$epmatch = $endpoint[1] . '(/(.*))?/?$';

				$epquery = '&' . $endpoint[2] . '=';
				$ep_query_append[$epmatch] = array ( $endpoint[0], $epquery );
			}
		}

		$front = substr($permalink_structure, 0, strpos($permalink_structure, '%'));

		preg_match_all('/%.+?%/', $permalink_structure, $tokens);

		$num_tokens = count($tokens[0]);

		$index = $this->index;
		$feedindex = $index;
		$trackbackindex = $index;
		$embedindex = $index;

		$queries = array();
		for ( $i = 0; $i < $num_tokens; ++$i ) {
			if ( 0 < $i )
				$queries[$i] = $queries[$i - 1] . '&';
			else
				$queries[$i] = '';

			$query_token = str_replace($this->rewritecode, $this->queryreplace, $tokens[0][$i]) . $this->preg_index($i+1);
			$queries[$i] .= $query_token;
		}

		$structure = $permalink_structure;
		if ( $front != '/' )
			$structure = str_replace($front, '', $structure);

		$structure = trim($structure, '/');
		$dirs = $walk_dirs ? explode('/', $structure) : array( $structure );
		$num_dirs = count($dirs);

		$front = preg_replace('|^/+|', '', $front);

		$post_rewrite = array();
		$struct = $front;
		for ( $j = 0; $j < $num_dirs; ++$j ) {
			$struct .= $dirs[$j] . '/'; // Accumulate. see comment near explode('/', $structure) above.
			$struct = ltrim($struct, '/');

			$match = str_replace($this->rewritecode, $this->rewritereplace, $struct);
			$num_toks = preg_match_all('/%.+?%/', $struct, $toks);

			$query = ( ! empty( $num_toks ) && isset( $queries[$num_toks - 1] ) ) ? $queries[$num_toks - 1] : '';

			switch ( $dirs[$j] ) {
				case '%year%':
					$ep_mask_specific = EP_YEAR;
					break;
				case '%monthnum%':
					$ep_mask_specific = EP_MONTH;
					break;
				case '%day%':
					$ep_mask_specific = EP_DAY;
					break;
				default:
					$ep_mask_specific = EP_NONE;
			}

			// Create query for /page/xx.
			$pagematch = $match . $pageregex;
			$pagequery = $index . '?' . $query . '&paged=' . $this->preg_index($num_toks + 1);

			// Create query for /comment-page-xx.
			$commentmatch = $match . $commentregex;
			$commentquery = $index . '?' . $query . '&cpage=' . $this->preg_index($num_toks + 1);

			if ( get_option('page_on_front') ) {
				// Create query for Root /comment-page-xx.
				$rootcommentmatch = $match . $commentregex;
				$rootcommentquery = $index . '?' . $query . '&page_id=' . get_option('page_on_front') . '&cpage=' . $this->preg_index($num_toks + 1);
			}

			// Create query for /feed/(feed|atom|rss|rss2|rdf).
			$feedmatch = $match . $feedregex;
			$feedquery = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			// Create query for /(feed|atom|rss|rss2|rdf) (see comment near creation of $feedregex).
			$feedmatch2 = $match . $feedregex2;
			$feedquery2 = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			// Create query and regex for embeds.
			$embedmatch = $match . $embedregex;
			$embedquery = $embedindex . '?' . $query . '&embed=true';

			// If asked to, turn the feed queries into comment feed ones.
			if ( $forcomments ) {
				$feedquery .= '&withcomments=1';
				$feedquery2 .= '&withcomments=1';
			}

			// Start creating the array of rewrites for this dir.
			$rewrite = array();

			// ...adding on /feed/ regexes => queries
			if ( $feed ) {
				$rewrite = array( $feedmatch => $feedquery, $feedmatch2 => $feedquery2, $embedmatch => $embedquery );
			}

			//...and /page/xx ones
			if ( $paged ) {
				$rewrite = array_merge( $rewrite, array( $pagematch => $pagequery ) );
			}

			// Only on pages with comments add ../comment-page-xx/.
			if ( EP_PAGES & $ep_mask || EP_PERMALINK & $ep_mask ) {
				$rewrite = array_merge($rewrite, array($commentmatch => $commentquery));
			} elseif ( EP_ROOT & $ep_mask && get_option('page_on_front') ) {
				$rewrite = array_merge($rewrite, array($rootcommentmatch => $rootcommentquery));
			}

			// Do endpoints.
			if ( $endpoints ) {
				foreach ( (array) $ep_query_append as $regex => $ep) {
					// Add the endpoints on if the mask fits.
					if ( $ep[0] & $ep_mask || $ep[0] & $ep_mask_specific )
						$rewrite[$match . $regex] = $index . '?' . $query . $ep[1] . $this->preg_index($num_toks + 2);
				}
			}

			// If we've got some tags in this dir.
			if ( $num_toks ) {
				$post = false;
				$page = false;

				if ( strpos($struct, '%postname%') !== false
						|| strpos($struct, '%post_id%') !== false
						|| strpos($struct, '%pagename%') !== false
						|| (strpos($struct, '%year%') !== false && strpos($struct, '%monthnum%') !== false && strpos($struct, '%day%') !== false && strpos($struct, '%hour%') !== false && strpos($struct, '%minute%') !== false && strpos($struct, '%second%') !== false)
						) {
					$post = true;
					if ( strpos($struct, '%pagename%') !== false )
						$page = true;
				}

				if ( ! $post ) {
					// For custom post types, we need to add on endpoints as well.
					foreach ( get_post_types( array('_builtin' => false ) ) as $ptype ) {
						if ( strpos($struct, "%$ptype%") !== false ) {
							$post = true;

							// This is for page style attachment URLs.
							$page = is_post_type_hierarchical( $ptype );
							break;
						}
					}
				}

				if ( $post ) {
					$embedmatch = $match . $embedregex;
					$embedquery = $embedindex . '?' . $query . '&embed=true';

					$match = rtrim($match, '/');

					$submatchbase = str_replace( array('(', ')'), '', $match);

					$sub1 = $submatchbase . '/([^/]+)/';

					$sub1comment = $sub1 . $commentregex;

					$sub1embed = $sub1 . $embedregex;

					$sub2 = $submatchbase . '/attachment/([^/]+)/';

					$sub2comment = $sub2 . $commentregex;

					$sub2embed = $sub2 . $embedregex;

					$subquery = $index . '?attachment=' . $this->preg_index(1);
					$subtbquery = $subquery . '&tb=1';
					$subfeedquery = $subquery . '&feed=' . $this->preg_index(2);
					$subcommentquery = $subquery . '&cpage=' . $this->preg_index(2);
					$subembedquery = $subquery . '&embed=true';

					// Do endpoints for attachments.
					if ( !empty($endpoints) ) {
						foreach ( (array) $ep_query_append as $regex => $ep ) {
							if ( $ep[0] & EP_ATTACHMENT ) {
								$rewrite[$sub1 . $regex] = $subquery . $ep[1] . $this->preg_index(3);
								$rewrite[$sub2 . $regex] = $subquery . $ep[1] . $this->preg_index(3);
							}
						}
					}

					$sub1 .= '?$';
					$sub2 .= '?$';

					$match = $match . '(?:/([0-9]+))?/?$';
					$query = $index . '?' . $query . '&page=' . $this->preg_index($num_toks + 1);

				} else {
					$match .= '?$';
					$query = $index . '?' . $query;
				}

				$rewrite = array_merge($rewrite, array($match => $query));

				if ( $post ) {
					$rewrite = array_merge( array( $embedmatch => $embedquery ), $rewrite );


					if ( ! $page ) {

						$rewrite = array_merge( $rewrite, array(
							$sub1        => $subquery,
							$sub1tb      => $subtbquery,
							$sub1comment => $subcommentquery,
							$sub1embed   => $subembedquery
						) );
					}

					$rewrite = array_merge( array( $sub2 => $subquery, $sub2tb => $subtbquery, $sub2comment => $subcommentquery, $sub2embed => $subembedquery ), $rewrite );
				}
			}
			$post_rewrite = array_merge($rewrite, $post_rewrite);
		}

		return $post_rewrite;
	}

	public function generate_rewrite_rule($permalink_structure, $walk_dirs = false) {
		return $this->generate_rewrite_rules($permalink_structure, EP_NONE, false, false, false, $walk_dirs);
	}

	public function rewrite_rules() {
		$rewrite = array();

		if ( empty($this->permalink_structure) )
			return $rewrite;

		$home_path = parse_url( home_url() );
		$robots_rewrite = ( empty( $home_path['path'] ) || '/' == $home_path['path'] ) ? array( 'robots\.txt$' => $this->index . '?robots=1' ) : array();

		$deprecated_files = array(
			'.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => $this->index . '?feed=old',
			'.*wp-app\.php(/.*)?$' => $this->index . '?error=403',
		);

		$registration_pages = array();
		if ( is_multisite() && is_main_site() ) {
			$registration_pages['.*wp-signup.php$'] = $this->index . '?signup=true';
			$registration_pages['.*wp-activate.php$'] = $this->index . '?activate=true';
		}

		$registration_pages['.*wp-register.php$'] = $this->index . '?register=true';

		$post_rewrite = $this->generate_rewrite_rules( $this->permalink_structure, EP_PERMALINK );

		$post_rewrite = apply_filters( 'post_rewrite_rules', $post_rewrite );

		$date_rewrite = $this->generate_rewrite_rules($this->get_date_permastruct(), EP_DATE);

		$date_rewrite = apply_filters( 'date_rewrite_rules', $date_rewrite );

		$root_rewrite = $this->generate_rewrite_rules($this->root . '/', EP_ROOT);

		$root_rewrite = apply_filters( 'root_rewrite_rules', $root_rewrite );

		$comments_rewrite = $this->generate_rewrite_rules($this->root . $this->comments_base, EP_COMMENTS, false, true, true, false);

		$comments_rewrite = apply_filters( 'comments_rewrite_rules', $comments_rewrite );

		$search_structure = $this->get_search_permastruct();
		$search_rewrite = $this->generate_rewrite_rules($search_structure, EP_SEARCH);

		$search_rewrite = apply_filters( 'search_rewrite_rules', $search_rewrite );

		$author_rewrite = $this->generate_rewrite_rules($this->get_author_permastruct(), EP_AUTHORS);

		$author_rewrite = apply_filters( 'author_rewrite_rules', $author_rewrite );

		$page_rewrite = $this->page_rewrite_rules();

		$page_rewrite = apply_filters( 'page_rewrite_rules', $page_rewrite );

		foreach ( $this->extra_permastructs as $permastructname => $struct ) {
			if ( is_array( $struct ) ) {
				if ( count( $struct ) == 2 )
					$rules = $this->generate_rewrite_rules( $struct[0], $struct[1] );
				else
					$rules = $this->generate_rewrite_rules( $struct['struct'], $struct['ep_mask'], $struct['paged'], $struct['feed'], $struct['forcomments'], $struct['walk_dirs'], $struct['endpoints'] );
			} else {
				$rules = $this->generate_rewrite_rules( $struct );
			}

			$rules = apply_filters( $permastructname . '_rewrite_rules', $rules );
			if ( 'post_tag' == $permastructname ) {
				$rules = apply_filters( 'tag_rewrite_rules', $rules );
			}

			$this->extra_rules_top = array_merge($this->extra_rules_top, $rules);
		}

		if ( $this->use_verbose_page_rules )
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $deprecated_files, $registration_pages, $root_rewrite, $comments_rewrite, $search_rewrite,  $author_rewrite, $date_rewrite, $page_rewrite, $post_rewrite, $this->extra_rules);
		else
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $deprecated_files, $registration_pages, $root_rewrite, $comments_rewrite, $search_rewrite,  $author_rewrite, $date_rewrite, $post_rewrite, $page_rewrite, $this->extra_rules);

		do_action_ref_array( 'generate_rewrite_rules', array( &$this ) );

		$this->rules = apply_filters( 'rewrite_rules_array', $this->rules );

		return $this->rules;
	}

	public function wp_rewrite_rules() {
		$this->rules = get_option('rewrite_rules');
		if ( empty($this->rules) ) {
			$this->matches = 'matches';
			$this->rewrite_rules();
			update_option('rewrite_rules', $this->rules);
		}
		return $this->rules;
	}

	public function mod_rewrite_rules() {
		if ( ! $this->using_permalinks() )
			return '';

		$site_root = parse_url( site_url() );
		if ( isset( $site_root['path'] ) )
			$site_root = trailingslashit($site_root['path']);

		$home_root = parse_url(home_url());
		if ( isset( $home_root['path'] ) )
			$home_root = trailingslashit($home_root['path']);
		else
			$home_root = '/';

		$rules = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteBase $home_root\n";
		$rules .= "RewriteRule ^index\.php$ - [L]\n";

		foreach ( (array) $this->non_wp_rules as $match => $query) {
			$match = str_replace('.+?', '.+', $match);
			$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
		}

		if ( $this->use_verbose_rules ) {
			$this->matches = '';
			$rewrite = $this->rewrite_rules();
			$num_rules = count($rewrite);
			$rules .= "RewriteCond %{REQUEST_FILENAME} -f [OR]\n" .
				"RewriteCond %{REQUEST_FILENAME} -d\n" .
				"RewriteRule ^.*$ - [S=$num_rules]\n";

			foreach ( (array) $rewrite as $match => $query) {
				// Apache 1.3 does not support the reluctant (non-greedy) modifier.
				$match = str_replace('.+?', '.+', $match);

				if ( strpos($query, $this->index) !== false )
					$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
				else
					$rules .= 'RewriteRule ^' . $match . ' ' . $site_root . $query . " [QSA,L]\n";
			}
		} else {
			$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n" .
				"RewriteCond %{REQUEST_FILENAME} !-d\n" .
				"RewriteRule . {$home_root}{$this->index} [L]\n";
		}

		$rules .= "</IfModule>\n";

		$rules = apply_filters( 'mod_rewrite_rules', $rules );

		return apply_filters( 'rewrite_rules', $rules );
	}

	public function add_rule( $regex, $query, $after = 'bottom' ) {
		if ( is_array( $query ) ) {
			$external = false;
			$query = add_query_arg( $query, 'index.php' );
		} else {
			$index = false === strpos( $query, '?' ) ? strlen( $query ) : strpos( $query, '?' );
			$front = substr( $query, 0, $index );
			$external = $front != $this->index;
		}
		if ( $external ) {
			$this->add_external_rule( $regex, $query );
		} else {
			if ( 'bottom' == $after ) {
				$this->extra_rules = array_merge( $this->extra_rules, array( $regex => $query ) );
			} else {
				$this->extra_rules_top = array_merge( $this->extra_rules_top, array( $regex => $query ) );
			}
		}
	}

	public function add_external_rule( $regex, $query ) {
		$this->non_wp_rules[ $regex ] = $query;
	}

	public function add_endpoint( $name, $places, $query_var = true ) {
		global $wp;

		if ( true === $query_var || null === func_get_arg( 2 ) ) {
			$query_var = $name;
		}
		$this->endpoints[] = array( $places, $name, $query_var );

		if ( $query_var ) {
			$wp->add_query_var( $query_var );
		}
	}

	public function add_permastruct( $name, $struct, $args = array() ) {
		if ( ! is_array( $args ) )
			$args = array( 'with_front' => $args );
		if ( func_num_args() == 4 )
			$args['ep_mask'] = func_get_arg( 3 );

		$defaults = array(
			'with_front' => true,
			'ep_mask' => EP_NONE,
			'paged' => true,
			'feed' => true,
			'forcomments' => false,
			'walk_dirs' => true,
			'endpoints' => true,
		);
		$args = array_intersect_key( $args, $defaults );
		$args = wp_parse_args( $args, $defaults );

		if ( $args['with_front'] )
			$struct = $this->front . $struct;
		else
			$struct = $this->root . $struct;
		$args['struct'] = $struct;

		$this->extra_permastructs[ $name ] = $args;
	}

	public function remove_permastruct( $name ) {
		unset( $this->extra_permastructs[ $name ] );
	}

	public function flush_rules( $hard = true ) {
		static $do_hard_later = null;
		if ( ! did_action( 'wp_loaded' ) ) {
			add_action( 'wp_loaded', array( $this, 'flush_rules' ) );
			$do_hard_later = ( isset( $do_hard_later ) ) ? $do_hard_later || $hard : $hard;
			return;
		}

		if ( isset( $do_hard_later ) ) {
			$hard = $do_hard_later;
			unset( $do_hard_later );
		}

		update_option( 'rewrite_rules', '' );
		$this->wp_rewrite_rules();

		if ( ! $hard || ! apply_filters( 'flush_rewrite_rules_hard', true ) ) {
			return;
		}
		if ( function_exists( 'save_mod_rewrite_rules' ) )
			save_mod_rewrite_rules();
	}

	public function set_permalink_structure($permalink_structure) {
		if ( $permalink_structure != $this->permalink_structure ) {
			$old_permalink_structure = $this->permalink_structure;
			update_option('permalink_structure', $permalink_structure);

			$this->init();

			do_action( 'permalink_structure_changed', $old_permalink_structure, $permalink_structure );
		}
	}

	public function set_category_base($category_base) {
		if ( $category_base != get_option('category_base') ) {
			update_option('category_base', $category_base);
			$this->init();
		}
	}

	public function set_tag_base( $tag_base ) {
		if ( $tag_base != get_option( 'tag_base') ) {
			update_option( 'tag_base', $tag_base );
			$this->init();
		}
	}

}
