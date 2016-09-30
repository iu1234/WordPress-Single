<?php

class WP_User_Query {

	public $query_vars = array();

	private $results;

	private $total_users = 0;

	public $meta_query = false;

	public $request;

	private $compat_fields = array( 'results', 'total_users' );

	public $query_fields;
	public $query_from;
	public $query_where;
	public $query_orderby;
	public $query_limit;

	public function __construct( $query = null ) {
		if ( ! empty( $query ) ) {
			$this->prepare_query( $query );
			$this->query();
		}
	}

	public static function fill_query_vars( $args ) {
		$defaults = array(
			'blog_id' => $GLOBALS['blog_id'],
			'role' => '',
			'role__in' => array(),
			'role__not_in' => array(),
			'meta_key' => '',
			'meta_value' => '',
			'meta_compare' => '',
			'include' => array(),
			'exclude' => array(),
			'search' => '',
			'search_columns' => array(),
			'orderby' => 'login',
			'order' => 'ASC',
			'offset' => '',
			'number' => '',
			'paged' => 1,
			'count_total' => true,
			'fields' => 'all',
			'who' => '',
			'has_published_posts' => null,
		);

		return wp_parse_args( $args, $defaults );
	}

	public function prepare_query( $query = array() ) {
		global $wpdb;

		if ( empty( $this->query_vars ) || ! empty( $query ) ) {
			$this->query_limit = null;
			$this->query_vars = $this->fill_query_vars( $query );
		}

		do_action( 'pre_get_users', $this );

		$qv =& $this->query_vars;
		$qv =  $this->fill_query_vars( $qv );

		if ( is_array( $qv['fields'] ) ) {
			$qv['fields'] = array_unique( $qv['fields'] );

			$this->query_fields = array();
			foreach ( $qv['fields'] as $field ) {
				$field = 'ID' === $field ? 'ID' : sanitize_key( $field );
				$this->query_fields[] = "$wpdb->users.$field";
			}
			$this->query_fields = implode( ',', $this->query_fields );
		} elseif ( 'all' == $qv['fields'] ) {
			$this->query_fields = "$wpdb->users.*";
		} else {
			$this->query_fields = "$wpdb->users.ID";
		}

		if ( isset( $qv['count_total'] ) && $qv['count_total'] )
			$this->query_fields = 'SQL_CALC_FOUND_ROWS ' . $this->query_fields;

		$this->query_from = "FROM $wpdb->users";
		$this->query_where = "WHERE 1=1";

		// Parse and sanitize 'include', for use by 'orderby' as well as 'include' below.
		if ( ! empty( $qv['include'] ) ) {
			$include = wp_parse_id_list( $qv['include'] );
		} else {
			$include = false;
		}

		if ( isset( $qv['who'] ) && 'authors' == $qv['who'] && $blog_id ) {
			$qv['meta_key'] = 'user_level';
			$qv['meta_value'] = 0;
			$qv['meta_compare'] = '!=';
		}

		if ( $qv['has_published_posts'] && $blog_id ) {
			if ( true === $qv['has_published_posts'] ) {
				$post_types = get_post_types( array( 'public' => true ) );
			} else {
				$post_types = (array) $qv['has_published_posts'];
			}

			foreach ( $post_types as &$post_type ) {
				$post_type = $wpdb->prepare( '%s', $post_type );
			}

			$posts_table = 'posts';
			$this->query_where .= " AND $wpdb->users.ID IN ( SELECT DISTINCT $posts_table.post_author FROM $posts_table WHERE $posts_table.post_status = 'publish' AND $posts_table.post_type IN ( " . join( ", ", $post_types ) . " ) )";
		}

		// Meta query.
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $qv );

		$roles = array();
		if ( isset( $qv['role'] ) ) {
			if ( is_array( $qv['role'] ) ) {
				$roles = $qv['role'];
			} elseif ( is_string( $qv['role'] ) && ! empty( $qv['role'] ) ) {
				$roles = array_map( 'trim', explode( ',', $qv['role'] ) );
			}
		}

		$role__in = array();
		if ( isset( $qv['role__in'] ) ) {
			$role__in = (array) $qv['role__in'];
		}

		$role__not_in = array();
		if ( isset( $qv['role__not_in'] ) ) {
			$role__not_in = (array) $qv['role__not_in'];
		}

		if ( $blog_id && ( ! empty( $roles ) || ! empty( $role__in ) || ! empty( $role__not_in ) || is_multisite() ) ) {
			$role_queries  = array();

			$roles_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $roles ) ) {
				foreach ( $roles as $role ) {
					$roles_clauses[] = array(
						'key'     => 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[] = $roles_clauses;
			}

			$role__in_clauses = array( 'relation' => 'OR' );
			if ( ! empty( $role__in ) ) {
				foreach ( $role__in as $role ) {
					$role__in_clauses[] = array(
						'key'     => 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[] = $role__in_clauses;
			}

			$role__not_in_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $role__not_in ) ) {
				foreach ( $role__not_in as $role ) {
					$role__not_in_clauses[] = array(
						'key'     => 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'NOT LIKE',
					);
				}

				$role_queries[] = $role__not_in_clauses;
			}

			if ( empty( $role_queries ) ) {
				$role_queries[] = array(
					'key' => 'capabilities',
					'compare' => 'EXISTS',
				);
			}

			$role_queries['relation'] = 'AND';

			if ( empty( $this->meta_query->queries ) ) {
				$this->meta_query->queries = $role_queries;
			} else {
				$this->meta_query->queries = array(
					'relation' => 'AND',
					array( $this->meta_query->queries, $role_queries ),
				);
			}

			$this->meta_query->parse_query_vars( $this->meta_query->queries );
		}

		if ( ! empty( $this->meta_query->queries ) ) {
			$clauses = $this->meta_query->get_sql( 'user', $wpdb->users, 'ID', $this );
			$this->query_from .= $clauses['join'];
			$this->query_where .= $clauses['where'];

			if ( $this->meta_query->has_or_relation() ) {
				$this->query_fields = 'DISTINCT ' . $this->query_fields;
			}
		}

		// sorting
		$qv['order'] = isset( $qv['order'] ) ? strtoupper( $qv['order'] ) : '';
		$order = $this->parse_order( $qv['order'] );

		if ( empty( $qv['orderby'] ) ) {
			// Default order is by 'user_login'.
			$ordersby = array( 'user_login' => $order );
		} elseif ( is_array( $qv['orderby'] ) ) {
			$ordersby = $qv['orderby'];
		} else {
			// 'orderby' values may be a comma- or space-separated list.
			$ordersby = preg_split( '/[,\s]+/', $qv['orderby'] );
		}

		$orderby_array = array();
		foreach ( $ordersby as $_key => $_value ) {
			if ( ! $_value ) {
				continue;
			}

			if ( is_int( $_key ) ) {
				// Integer key means this is a flat array of 'orderby' fields.
				$_orderby = $_value;
				$_order = $order;
			} else {
				// Non-integer key means this the key is the field and the value is ASC/DESC.
				$_orderby = $_key;
				$_order = $_value;
			}

			$parsed = $this->parse_orderby( $_orderby );

			if ( ! $parsed ) {
				continue;
			}

			$orderby_array[] = $parsed . ' ' . $this->parse_order( $_order );
		}

		// If no valid clauses were found, order by user_login.
		if ( empty( $orderby_array ) ) {
			$orderby_array[] = "user_login $order";
		}

		$this->query_orderby = 'ORDER BY ' . implode( ', ', $orderby_array );

		// limit
		if ( isset( $qv['number'] ) && $qv['number'] > 0 ) {
			if ( $qv['offset'] ) {
				$this->query_limit = $wpdb->prepare("LIMIT %d, %d", $qv['offset'], $qv['number']);
			} else {
				$this->query_limit = $wpdb->prepare( "LIMIT %d, %d", $qv['number'] * ( $qv['paged'] - 1 ), $qv['number'] );
			}
		}

		$search = '';
		if ( isset( $qv['search'] ) )
			$search = trim( $qv['search'] );

		if ( $search ) {
			$leading_wild = ( ltrim($search, '*') != $search );
			$trailing_wild = ( rtrim($search, '*') != $search );
			if ( $leading_wild && $trailing_wild )
				$wild = 'both';
			elseif ( $leading_wild )
				$wild = 'leading';
			elseif ( $trailing_wild )
				$wild = 'trailing';
			else
				$wild = false;
			if ( $wild )
				$search = trim($search, '*');

			$search_columns = array();
			if ( $qv['search_columns'] )
				$search_columns = array_intersect( $qv['search_columns'], array( 'ID', 'user_login', 'user_email', 'user_url', 'user_nicename' ) );
			if ( ! $search_columns ) {
				if ( false !== strpos( $search, '@') )
					$search_columns = array('user_email');
				elseif ( is_numeric($search) )
					$search_columns = array('user_login', 'ID');
				elseif ( preg_match('|^https?://|', $search) && ! ( is_multisite() && wp_is_large_network( 'users' ) ) )
					$search_columns = array('user_url');
				else
					$search_columns = array('user_login', 'user_url', 'user_email', 'user_nicename', 'display_name');
			}

			$search_columns = apply_filters( 'user_search_columns', $search_columns, $search, $this );

			$this->query_where .= $this->get_search_sql( $search, $search_columns, $wild );
		}

		if ( ! empty( $include ) ) {
			$ids = implode( ',', $include );
			$this->query_where .= " AND $wpdb->users.ID IN ($ids)";
		} elseif ( ! empty( $qv['exclude'] ) ) {
			$ids = implode( ',', wp_parse_id_list( $qv['exclude'] ) );
			$this->query_where .= " AND $wpdb->users.ID NOT IN ($ids)";
		}

		if ( ! empty( $qv['date_query'] ) && is_array( $qv['date_query'] ) ) {
			$date_query = new WP_Date_Query( $qv['date_query'], 'user_registered' );
			$this->query_where .= $date_query->get_sql();
		}

		do_action_ref_array( 'pre_user_query', array( &$this ) );
	}

	public function query() {
		global $wpdb;

		$qv =& $this->query_vars;

		$this->request = "SELECT $this->query_fields $this->query_from $this->query_where $this->query_orderby $this->query_limit";

		if ( is_array( $qv['fields'] ) || 'all' == $qv['fields'] ) {
			$this->results = $wpdb->get_results( $this->request );
		} else {
			$this->results = $wpdb->get_col( $this->request );
		}

		if ( isset( $qv['count_total'] ) && $qv['count_total'] )
			$this->total_users = $wpdb->get_var( apply_filters( 'found_users_query', 'SELECT FOUND_ROWS()' ) );

		if ( !$this->results )
			return;

		if ( 'all_with_meta' == $qv['fields'] ) {
			$r = array();
			foreach ( $this->results as $userid )
				$r[ $userid ] = new WP_User( $userid, '', $qv['blog_id'] );

			$this->results = $r;
		} elseif ( 'all' == $qv['fields'] ) {
			foreach ( $this->results as $key => $user ) {
				$this->results[ $key ] = new WP_User( $user, '', $qv['blog_id'] );
			}
		}
	}

	public function get( $query_var ) {
		if ( isset( $this->query_vars[$query_var] ) )
			return $this->query_vars[$query_var];

		return null;
	}

	public function set( $query_var, $value ) {
		$this->query_vars[$query_var] = $value;
	}

	protected function get_search_sql( $string, $cols, $wild = false ) {
		global $wpdb;

		$searches = array();
		$leading_wild = ( 'leading' == $wild || 'both' == $wild ) ? '%' : '';
		$trailing_wild = ( 'trailing' == $wild || 'both' == $wild ) ? '%' : '';
		$like = $leading_wild . $wpdb->esc_like( $string ) . $trailing_wild;

		foreach ( $cols as $col ) {
			if ( 'ID' == $col ) {
				$searches[] = $wpdb->prepare( "$col = %s", $string );
			} else {
				$searches[] = $wpdb->prepare( "$col LIKE %s", $like );
			}
		}

		return ' AND (' . implode(' OR ', $searches) . ')';
	}

	public function get_results() {
		return $this->results;
	}

	public function get_total() {
		return $this->total_users;
	}

	protected function parse_orderby( $orderby ) {
		global $wpdb;

		$meta_query_clauses = $this->meta_query->get_clauses();

		$_orderby = '';
		if ( in_array( $orderby, array( 'login', 'nicename', 'email', 'url', 'registered' ) ) ) {
			$_orderby = 'user_' . $orderby;
		} elseif ( in_array( $orderby, array( 'user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered' ) ) ) {
			$_orderby = $orderby;
		} elseif ( 'name' == $orderby || 'display_name' == $orderby ) {
			$_orderby = 'display_name';
		} elseif ( 'post_count' == $orderby ) {
			// todo: avoid the JOIN
			$where = get_posts_by_author_sql( 'post' );
			$this->query_from .= " LEFT OUTER JOIN (
				SELECT post_author, COUNT(*) as post_count
				FROM $wpdb->posts
				$where
				GROUP BY post_author
			) p ON ({$wpdb->users}.ID = p.post_author)
			";
			$_orderby = 'post_count';
		} elseif ( 'ID' == $orderby || 'id' == $orderby ) {
			$_orderby = 'ID';
		} elseif ( 'meta_value' == $orderby || $this->get( 'meta_key' ) == $orderby ) {
			$_orderby = "$wpdb->usermeta.meta_value";
		} elseif ( 'meta_value_num' == $orderby ) {
			$_orderby = "$wpdb->usermeta.meta_value+0";
		} elseif ( 'include' === $orderby && ! empty( $this->query_vars['include'] ) ) {
			$include = wp_parse_id_list( $this->query_vars['include'] );
			$include_sql = implode( ',', $include );
			$_orderby = "FIELD( $wpdb->users.ID, $include_sql )";
		} elseif ( isset( $meta_query_clauses[ $orderby ] ) ) {
			$meta_clause = $meta_query_clauses[ $orderby ];
			$_orderby = sprintf( "CAST(%s.meta_value AS %s)", esc_sql( $meta_clause['alias'] ), esc_sql( $meta_clause['cast'] ) );
		}

		return $_orderby;
	}

	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'DESC';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'ASC';
		} else {
			return 'DESC';
		}
	}

	public function __get( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return $this->$name;
		}
	}

	public function __set( $name, $value ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return $this->$name = $value;
		}
	}

	public function __isset( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return isset( $this->$name );
		}
	}

	public function __unset( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			unset( $this->$name );
		}
	}

	public function __call( $name, $arguments ) {
		if ( 'get_search_sql' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}
}
