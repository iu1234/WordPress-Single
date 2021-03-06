<?php

class WP_Comment_Query {

	public $request;

	public $meta_query = false;

	protected $meta_query_clauses;

	protected $sql_clauses = array(
		'select'  => '',
		'from'    => '',
		'where'   => array(),
		'groupby' => '',
		'orderby' => '',
		'limits'  => '',
	);

	protected $filtered_where_clause;

	public $date_query = false;

	public $query_vars;

	public $query_var_defaults;

	public $comments;

	public $found_comments = 0;

	public $max_num_pages = 0;

	public function __call( $name, $arguments ) {
		if ( 'get_search_sql' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	public function __construct( $query = '' ) {
		$this->query_var_defaults = array(
			'author_email' => '',
			'author_url' => '',
			'author__in' => '',
			'author__not_in' => '',
			'include_unapproved' => '',
			'fields' => '',
			'ID' => '',
			'comment__in' => '',
			'comment__not_in' => '',
			'karma' => '',
			'number' => '',
			'offset' => '',
			'no_found_rows' => true,
			'orderby' => '',
			'order' => 'DESC',
			'parent' => '',
			'parent__in' => '',
			'parent__not_in' => '',
			'post_author__in' => '',
			'post_author__not_in' => '',
			'post_ID' => '',
			'post_id' => 0,
			'post__in' => '',
			'post__not_in' => '',
			'post_author' => '',
			'post_name' => '',
			'post_parent' => '',
			'post_status' => '',
			'post_type' => '',
			'status' => 'all',
			'type' => '',
			'type__in' => '',
			'type__not_in' => '',
			'user_id' => '',
			'search' => '',
			'count' => false,
			'meta_key' => '',
			'meta_value' => '',
			'meta_query' => '',
			'date_query' => null,
			'hierarchical' => false,
			'update_comment_meta_cache' => true,
			'update_comment_post_cache' => false,
		);

		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	public function parse_query( $query = '' ) {
		if ( empty( $query ) ) {
			$query = $this->query_vars;
		}

		$this->query_vars = wp_parse_args( $query, $this->query_var_defaults );
		do_action_ref_array( 'parse_comment_query', array( &$this ) );
	}

	public function query( $query ) {
		$this->query_vars = wp_parse_args( $query );
		return $this->get_comments();
	}

	public function get_comments() {
		global $wpdb;

		$this->parse_query();

		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $this->query_vars );

		do_action_ref_array( 'pre_get_comments', array( &$this ) );

		$this->meta_query->parse_query_vars( $this->query_vars );
		if ( ! empty( $this->meta_query->queries ) ) {
			$this->meta_query_clauses = $this->meta_query->get_sql( 'comment', $wpdb->comments, 'comment_ID', $this );
		}

		$key = md5( serialize( wp_array_slice_assoc( $this->query_vars, array_keys( $this->query_var_defaults ) ) ) );
		$last_changed = wp_cache_get( 'last_changed', 'comment' );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, 'comment' );
		}
		$cache_key = "get_comment_ids:$key:$last_changed";

		$comment_ids = wp_cache_get( $cache_key, 'comment' );
		if ( false === $comment_ids ) {
			$comment_ids = $this->get_comment_ids();
			wp_cache_add( $cache_key, $comment_ids, 'comment' );
		}

		if ( $this->query_vars['count'] ) {
			return intval( $comment_ids );
		}

		$comment_ids = array_map( 'intval', $comment_ids );

		$this->comment_count = count( $this->comments );

		if ( $comment_ids && $this->query_vars['number'] && ! $this->query_vars['no_found_rows'] ) {
			$found_comments_query = apply_filters( 'found_comments_query', 'SELECT FOUND_ROWS()', $this );
			$this->found_comments = (int) $wpdb->get_var( $found_comments_query );

			$this->max_num_pages = ceil( $this->found_comments / $this->query_vars['number'] );
		}

		if ( 'ids' == $this->query_vars['fields'] ) {
			$this->comments = $comment_ids;
			return $this->comments;
		}

		_prime_comment_caches( $comment_ids, $this->query_vars['update_comment_meta_cache'] );

		$_comments = array();
		foreach ( $comment_ids as $comment_id ) {
			if ( $_comment = get_comment( $comment_id ) ) {
				$_comments[] = $_comment;
			}
		}

		if ( $this->query_vars['update_comment_post_cache'] ) {
			$comment_post_ids = array();
			foreach ( $_comments as $_comment ) {
				$comment_post_ids[] = $_comment->comment_post_ID;
			}

			_prime_post_caches( $comment_post_ids, false, false );
		}

		$_comments = apply_filters_ref_array( 'the_comments', array( $_comments, &$this ) );

		$comments = array_map( 'get_comment', $_comments );

		if ( $this->query_vars['hierarchical'] ) {
			$comments = $this->fill_descendants( $comments );
		}

		$this->comments = $comments;
		return $this->comments;
	}

	protected function get_comment_ids() {
		global $wpdb;

		$approved_clauses = array();

		$status_clauses = array();
		$statuses = $this->query_vars['status'];
		if ( ! is_array( $statuses ) ) {
			$statuses = preg_split( '/[\s,]+/', $statuses );
		}

		if ( ! in_array( 'any', $statuses ) ) {
			foreach ( $statuses as $status ) {
				switch ( $status ) {
					case 'hold' :
						$status_clauses[] = "comment_approved = '0'";
						break;

					case 'approve' :
						$status_clauses[] = "comment_approved = '1'";
						break;

					case 'all' :
					case '' :
						$status_clauses[] = "( comment_approved = '0' OR comment_approved = '1' )";
						break;

					default :
						$status_clauses[] = $wpdb->prepare( "comment_approved = %s", $status );
						break;
				}
			}

			if ( ! empty( $status_clauses ) ) {
				$approved_clauses[] = '( ' . implode( ' OR ', $status_clauses ) . ' )';
			}
		}

		if ( ! empty( $this->query_vars['include_unapproved'] ) ) {
			$include_unapproved = $this->query_vars['include_unapproved'];

			if ( ! is_array( $include_unapproved ) ) {
				$include_unapproved = preg_split( '/[\s,]+/', $include_unapproved );
			}

			$unapproved_ids = $unapproved_emails = array();
			foreach ( $include_unapproved as $unapproved_identifier ) {
				if ( is_numeric( $unapproved_identifier ) ) {
					$approved_clauses[] = $wpdb->prepare( "( user_id = %d AND comment_approved = '0' )", $unapproved_identifier );
				} else {
					$approved_clauses[] = $wpdb->prepare( "( comment_author_email = %s AND comment_approved = '0' )", $unapproved_identifier );
				}
			}
		}

		if ( ! empty( $approved_clauses ) ) {
			if ( 1 === count( $approved_clauses ) ) {
				$this->sql_clauses['where']['approved'] = $approved_clauses[0];
			} else {
				$this->sql_clauses['where']['approved'] = '( ' . implode( ' OR ', $approved_clauses ) . ' )';
			}
		}

		$order = ( 'ASC' == strtoupper( $this->query_vars['order'] ) ) ? 'ASC' : 'DESC';

		if ( in_array( $this->query_vars['orderby'], array( 'none', array(), false ), true ) ) {
			$orderby = '';
		} elseif ( ! empty( $this->query_vars['orderby'] ) ) {
			$ordersby = is_array( $this->query_vars['orderby'] ) ?
				$this->query_vars['orderby'] :
				preg_split( '/[,\s]/', $this->query_vars['orderby'] );

			$orderby_array = array();
			$found_orderby_comment_ID = false;
			foreach ( $ordersby as $_key => $_value ) {
				if ( ! $_value ) {
					continue;
				}

				if ( is_int( $_key ) ) {
					$_orderby = $_value;
					$_order = $order;
				} else {
					$_orderby = $_key;
					$_order = $_value;
				}

				if ( ! $found_orderby_comment_ID && in_array( $_orderby, array( 'comment_ID', 'comment__in' ) ) ) {
					$found_orderby_comment_ID = true;
				}

				$parsed = $this->parse_orderby( $_orderby );

				if ( ! $parsed ) {
					continue;
				}

				if ( 'comment__in' === $_orderby ) {
					$orderby_array[] = $parsed;
					continue;
				}

				$orderby_array[] = $parsed . ' ' . $this->parse_order( $_order );
			}

			if ( empty( $orderby_array ) ) {
				$orderby_array[] = "$wpdb->comments.comment_date_gmt $order";
			}

			if ( ! $found_orderby_comment_ID ) {
				$comment_ID_order = '';

				foreach ( $orderby_array as $orderby_clause ) {
					if ( preg_match( '/comment_date(?:_gmt)*\ (ASC|DESC)/', $orderby_clause, $match ) ) {
						$comment_ID_order = $match[1];
						break;
					}
				}

				if ( ! $comment_ID_order ) {
					foreach ( $orderby_array as $orderby_clause ) {
						if ( false !== strpos( 'ASC', $orderby_clause ) ) {
							$comment_ID_order = 'ASC';
						} else {
							$comment_ID_order = 'DESC';
						}

						break;
					}
				}

				if ( ! $comment_ID_order ) {
					$comment_ID_order = 'DESC';
				}

				$orderby_array[] = "$wpdb->comments.comment_ID $comment_ID_order";
			}

			$orderby = implode( ', ', $orderby_array );
		} else {
			$orderby = "$wpdb->comments.comment_date_gmt $order";
		}

		$number = absint( $this->query_vars['number'] );
		$offset = absint( $this->query_vars['offset'] );

		if ( ! empty( $number ) ) {
			if ( $offset ) {
				$limits = 'LIMIT ' . $offset . ',' . $number;
			} else {
				$limits = 'LIMIT ' . $number;
			}
		}

		if ( $this->query_vars['count'] ) {
			$fields = 'COUNT(*)';
		} else {
			$fields = "$wpdb->comments.comment_ID";
		}

		$post_id = absint( $this->query_vars['post_id'] );
		if ( ! empty( $post_id ) ) {
			$this->sql_clauses['where']['post_id'] = $wpdb->prepare( 'comment_post_ID = %d', $post_id );
		}

		if ( ! empty( $this->query_vars['comment__in'] ) ) {
			$this->sql_clauses['where']['comment__in'] = "$wpdb->comments.comment_ID IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['comment__in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['comment__not_in'] ) ) {
			$this->sql_clauses['where']['comment__not_in'] = "$wpdb->comments.comment_ID NOT IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['comment__not_in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['parent__in'] ) ) {
			$this->sql_clauses['where']['parent__in'] = 'comment_parent IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['parent__in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['parent__not_in'] ) ) {
			$this->sql_clauses['where']['parent__not_in'] = 'comment_parent NOT IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['parent__not_in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['post__in'] ) ) {
			$this->sql_clauses['where']['post__in'] = 'comment_post_ID IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['post__in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['post__not_in'] ) ) {
			$this->sql_clauses['where']['post__not_in'] = 'comment_post_ID NOT IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['post__not_in'] ) ) . ' )';
		}

		if ( '' !== $this->query_vars['author_email'] ) {
			$this->sql_clauses['where']['author_email'] = $wpdb->prepare( 'comment_author_email = %s', $this->query_vars['author_email'] );
		}

		if ( '' !== $this->query_vars['author_url'] ) {
			$this->sql_clauses['where']['author_url'] = $wpdb->prepare( 'comment_author_url = %s', $this->query_vars['author_url'] );
		}

		if ( '' !== $this->query_vars['karma'] ) {
			$this->sql_clauses['where']['karma'] = $wpdb->prepare( 'comment_karma = %d', $this->query_vars['karma'] );
		}

		$raw_types = array(
			'IN' => array_merge( (array) $this->query_vars['type'], (array) $this->query_vars['type__in'] ),
			'NOT IN' => (array) $this->query_vars['type__not_in'],
		);

		$comment_types = array();
		foreach ( $raw_types as $operator => $_raw_types ) {
			$_raw_types = array_unique( $_raw_types );

			foreach ( $_raw_types as $type ) {
				switch ( $type ) {
					case '':
					case 'all' :
						break;

					case 'comment':
					case 'comments':
						$comment_types[ $operator ][] = "''";
						break;

					case 'pings':
						$comment_types[ $operator ][] = "'pingback'";
						$comment_types[ $operator ][] = "'trackback'";
						break;

					default:
						$comment_types[ $operator ][] = $wpdb->prepare( '%s', $type );
						break;
				}
			}

			if ( ! empty( $comment_types[ $operator ] ) ) {
				$types_sql = implode( ', ', $comment_types[ $operator ] );
				$this->sql_clauses['where']['comment_type__' . strtolower( str_replace( ' ', '_', $operator ) ) ] = "comment_type $operator ($types_sql)";
			}
		}

		if ( $this->query_vars['hierarchical'] && ! $this->query_vars['parent'] ) {
			$this->query_vars['parent'] = 0;
		}

		if ( '' !== $this->query_vars['parent'] ) {
			$this->sql_clauses['where']['parent'] = $wpdb->prepare( 'comment_parent = %d', $this->query_vars['parent'] );
		}

		if ( is_array( $this->query_vars['user_id'] ) ) {
			$this->sql_clauses['where']['user_id'] = 'user_id IN (' . implode( ',', array_map( 'absint', $this->query_vars['user_id'] ) ) . ')';
		} elseif ( '' !== $this->query_vars['user_id'] ) {
			$this->sql_clauses['where']['user_id'] = $wpdb->prepare( 'user_id = %d', $this->query_vars['user_id'] );
		}

		if ( strlen( $this->query_vars['search'] ) ) {
			$search_sql = $this->get_search_sql(
				$this->query_vars['search'],
				array( 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content' )
			);

			$this->sql_clauses['where']['search'] = preg_replace( '/^\s*AND\s*/', '', $search_sql );
		}

		$join_posts_table = false;
		$plucked = wp_array_slice_assoc( $this->query_vars, array( 'post_author', 'post_name', 'post_parent' ) );
		$post_fields = array_filter( $plucked );

		if ( ! empty( $post_fields ) ) {
			$join_posts_table = true;
			foreach ( $post_fields as $field_name => $field_value ) {
				$esses = array_fill( 0, count( (array) $field_value ), '%s' );
				$this->sql_clauses['where'][ $field_name ] = $wpdb->prepare( " {$wpdb->posts}.{$field_name} IN (" . implode( ',', $esses ) . ')', $field_value );
			}
		}

		foreach ( array( 'post_status', 'post_type' ) as $field_name ) {
			$q_values = array();
			if ( ! empty( $this->query_vars[ $field_name ] ) ) {
				$q_values = $this->query_vars[ $field_name ];
				if ( ! is_array( $q_values ) ) {
					$q_values = explode( ',', $q_values );
				}

				if ( in_array( 'any', $q_values, true ) || empty( $q_values ) ) {
					continue;
				}

				$join_posts_table = true;

				$esses = array_fill( 0, count( $q_values ), '%s' );
				$this->sql_clauses['where'][ $field_name ] = $wpdb->prepare( " {$wpdb->posts}.{$field_name} IN (" . implode( ',', $esses ) . ")", $q_values );
			}
		}

		if ( ! empty( $this->query_vars['author__in'] ) ) {
			$this->sql_clauses['where']['author__in'] = 'user_id IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['author__in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['author__not_in'] ) ) {
			$this->sql_clauses['where']['author__not_in'] = 'user_id NOT IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['author__not_in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['post_author__in'] ) ) {
			$join_posts_table = true;
			$this->sql_clauses['where']['post_author__in'] = 'post_author IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['post_author__in'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['post_author__not_in'] ) ) {
			$join_posts_table = true;
			$this->sql_clauses['where']['post_author__not_in'] = 'post_author NOT IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['post_author__not_in'] ) ) . ' )';
		}

		$join = '';

		if ( $join_posts_table ) {
			$join .= "JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";
		}

		if ( ! empty( $this->meta_query_clauses ) ) {
			$join .= $this->meta_query_clauses['join'];

			$this->sql_clauses['where']['meta_query'] = preg_replace( '/^\s*AND\s*/', '', $this->meta_query_clauses['where'] );

			if ( ! $this->query_vars['count'] ) {
				$groupby = "{$wpdb->comments}.comment_ID";
			}
		}

		$date_query = $this->query_vars['date_query'];
		if ( ! empty( $date_query ) && is_array( $date_query ) ) {
			$date_query_object = new WP_Date_Query( $date_query, 'comment_date' );
			$this->sql_clauses['where']['date_query'] = preg_replace( '/^\s*AND\s*/', '', $date_query_object->get_sql() );
		}

		$where = implode( ' AND ', $this->sql_clauses['where'] );

		$pieces = array( 'fields', 'join', 'where', 'orderby', 'limits', 'groupby' );

		$clauses = apply_filters_ref_array( 'comments_clauses', array( compact( $pieces ), &$this ) );

		$fields = isset( $clauses[ 'fields' ] ) ? $clauses[ 'fields' ] : '';
		$join = isset( $clauses[ 'join' ] ) ? $clauses[ 'join' ] : '';
		$where = isset( $clauses[ 'where' ] ) ? $clauses[ 'where' ] : '';
		$orderby = isset( $clauses[ 'orderby' ] ) ? $clauses[ 'orderby' ] : '';
		$limits = isset( $clauses[ 'limits' ] ) ? $clauses[ 'limits' ] : '';
		$groupby = isset( $clauses[ 'groupby' ] ) ? $clauses[ 'groupby' ] : '';

		$this->filtered_where_clause = $where;

		if ( $where ) {
			$where = 'WHERE ' . $where;
		}

		if ( $groupby ) {
			$groupby = 'GROUP BY ' . $groupby;
		}

		if ( $orderby ) {
			$orderby = "ORDER BY $orderby";
		}

		$found_rows = '';
		if ( ! $this->query_vars['no_found_rows'] ) {
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		$this->sql_clauses['select']  = "SELECT $found_rows $fields";
		$this->sql_clauses['from']    = "FROM $wpdb->comments $join";
		$this->sql_clauses['groupby'] = $groupby;
		$this->sql_clauses['orderby'] = $orderby;
		$this->sql_clauses['limits']  = $limits;

		$this->request = "{$this->sql_clauses['select']} {$this->sql_clauses['from']} {$where} {$this->sql_clauses['groupby']} {$this->sql_clauses['orderby']} {$this->sql_clauses['limits']}";

		if ( $this->query_vars['count'] ) {
			return intval( $wpdb->get_var( $this->request ) );
		} else {
			$comment_ids = $wpdb->get_col( $this->request );
			return array_map( 'intval', $comment_ids );
		}
	}

	protected function fill_descendants( $comments ) {
		global $wpdb;

		$levels = array(
			0 => wp_list_pluck( $comments, 'comment_ID' ),
		);

		$_where = $this->filtered_where_clause;
		$exclude_keys = array( 'parent', 'parent__in', 'parent__not_in' );
		foreach ( $exclude_keys as $exclude_key ) {
			if ( isset( $this->sql_clauses['where'][ $exclude_key ] ) ) {
				$clause = $this->sql_clauses['where'][ $exclude_key ];

				$pattern = '|(?:AND)?\s*' . $clause . '\s*(?:AND)?|';
				$_where_parts = preg_split( $pattern, $_where );

				$_where_parts = array_filter( array_map( 'trim', $_where_parts ) );

				$_where = implode( ' AND ', $_where_parts );
			}
		}

		$level = 0;
		do {
			$parent_ids = $levels[ $level ];
			if ( ! $parent_ids ) {
				break;
			}

			$where = 'WHERE ' . $_where . ' AND comment_parent IN (' . implode( ',', array_map( 'intval', $parent_ids ) ) . ')';
			$comment_ids = $wpdb->get_col( "{$this->sql_clauses['select']} {$this->sql_clauses['from']} {$where} {$this->sql_clauses['groupby']} ORDER BY comment_date_gmt ASC, comment_ID ASC" );

			$level++;
			$levels[ $level ] = $comment_ids;
		} while ( $comment_ids );

		$descendant_ids = array();
		for ( $i = 1; $i < count( $levels ); $i++ ) {
			$descendant_ids = array_merge( $descendant_ids, $levels[ $i ] );
		}

		_prime_comment_caches( $descendant_ids, $this->query_vars['update_comment_meta_cache'] );

		$all_comments = $comments;
		foreach ( $descendant_ids as $descendant_id ) {
			$all_comments[] = get_comment( $descendant_id );
		}

		if ( 'threaded' === $this->query_vars['hierarchical'] ) {
			$threaded_comments = $ref = array();
			foreach ( $all_comments as $k => $c ) {
				$_c = get_comment( $c->comment_ID );
				if ( ! isset( $ref[ $c->comment_parent ] ) ) {
					$threaded_comments[ $_c->comment_ID ] = $_c;
					$ref[ $_c->comment_ID ] = $threaded_comments[ $_c->comment_ID ];
				} else {

					$ref[ $_c->comment_parent ]->add_child( $_c );
					$ref[ $_c->comment_ID ] = $ref[ $_c->comment_parent ]->get_child( $_c->comment_ID );
				}
			}

			foreach ( $ref as $_ref ) {
				$_ref->populated_children( true );
			}

			$comments = $threaded_comments;
		} else {
			$comments = $all_comments;
		}

		return $comments;
	}

	protected function get_search_sql( $string, $cols ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $string ) . '%';

		$searches = array();
		foreach ( $cols as $col ) {
			$searches[] = $wpdb->prepare( "$col LIKE %s", $like );
		}

		return ' AND (' . implode(' OR ', $searches) . ')';
	}

	protected function parse_orderby( $orderby ) {
		global $wpdb;

		$allowed_keys = array(
			'comment_agent',
			'comment_approved',
			'comment_author',
			'comment_author_email',
			'comment_author_IP',
			'comment_author_url',
			'comment_content',
			'comment_date',
			'comment_date_gmt',
			'comment_ID',
			'comment_karma',
			'comment_parent',
			'comment_post_ID',
			'comment_type',
			'user_id',
		);

		if ( ! empty( $this->query_vars['meta_key'] ) ) {
			$allowed_keys[] = $this->query_vars['meta_key'];
			$allowed_keys[] = 'meta_value';
			$allowed_keys[] = 'meta_value_num';
		}

		$meta_query_clauses = $this->meta_query->get_clauses();
		if ( $meta_query_clauses ) {
			$allowed_keys = array_merge( $allowed_keys, array_keys( $meta_query_clauses ) );
		}

		$parsed = false;
		if ( $orderby == $this->query_vars['meta_key'] || $orderby == 'meta_value' ) {
			$parsed = "$wpdb->commentmeta.meta_value";
		} elseif ( $orderby == 'meta_value_num' ) {
			$parsed = "$wpdb->commentmeta.meta_value+0";
		} elseif ( $orderby == 'comment__in' ) {
			$comment__in = implode( ',', array_map( 'absint', $this->query_vars['comment__in'] ) );
			$parsed = "FIELD( {$wpdb->comments}.comment_ID, $comment__in )";
		} elseif ( in_array( $orderby, $allowed_keys ) ) {

			if ( isset( $meta_query_clauses[ $orderby ] ) ) {
				$meta_clause = $meta_query_clauses[ $orderby ];
				$parsed = sprintf( "CAST(%s.meta_value AS %s)", esc_sql( $meta_clause['alias'] ), esc_sql( $meta_clause['cast'] ) );
			} else {
				$parsed = "$wpdb->comments.$orderby";
			}
		}

		return $parsed;
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
}
