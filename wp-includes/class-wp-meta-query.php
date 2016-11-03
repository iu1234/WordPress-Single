<?php
/**
 * Meta API: WP_Meta_Query class
 *
 * @package WordPress
 * @subpackage Meta
 * @since 4.4.0
 */

class WP_Meta_Query {

	public $queries = array();

	public $relation;

	public $meta_table;

	public $meta_id_column;

	public $primary_table;

	public $primary_id_column;

	protected $table_aliases = array();

	protected $clauses = array();

	protected $has_or_relation = false;

	public function __construct( $meta_query = false ) {
		if ( !$meta_query )
			return;

		if ( isset( $meta_query['relation'] ) && strtoupper( $meta_query['relation'] ) == 'OR' ) {
			$this->relation = 'OR';
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $this->sanitize_query( $meta_query );
	}

	public function sanitize_query( $queries ) {
		$clean_queries = array();

		if ( ! is_array( $queries ) ) {
			return $clean_queries;
		}

		foreach ( $queries as $key => $query ) {
			if ( 'relation' === $key ) {
				$relation = $query;

			} elseif ( ! is_array( $query ) ) {
				continue;

			// First-order clause.
			} elseif ( $this->is_first_order_clause( $query ) ) {
				if ( isset( $query['value'] ) && array() === $query['value'] ) {
					unset( $query['value'] );
				}

				$clean_queries[ $key ] = $query;

			} else {
				$cleaned_query = $this->sanitize_query( $query );

				if ( ! empty( $cleaned_query ) ) {
					$clean_queries[ $key ] = $cleaned_query;
				}
			}
		}

		if ( empty( $clean_queries ) ) {
			return $clean_queries;
		}

		if ( isset( $relation ) && 'OR' === strtoupper( $relation ) ) {
			$clean_queries['relation'] = 'OR';
			$this->has_or_relation = true;

		} elseif ( 1 === count( $clean_queries ) ) {
			$clean_queries['relation'] = 'OR';

		} else {
			$clean_queries['relation'] = 'AND';
		}

		return $clean_queries;
	}

	protected function is_first_order_clause( $query ) {
		return isset( $query['key'] ) || isset( $query['value'] );
	}

	public function parse_query_vars( $qv ) {
		$meta_query = array();

		$primary_meta_query = array();
		foreach ( array( 'key', 'compare', 'type' ) as $key ) {
			if ( ! empty( $qv[ "meta_$key" ] ) ) {
				$primary_meta_query[ $key ] = $qv[ "meta_$key" ];
			}
		}

		if ( isset( $qv['meta_value'] ) && '' !== $qv['meta_value'] && ( ! is_array( $qv['meta_value'] ) || $qv['meta_value'] ) ) {
			$primary_meta_query['value'] = $qv['meta_value'];
		}

		$existing_meta_query = isset( $qv['meta_query'] ) && is_array( $qv['meta_query'] ) ? $qv['meta_query'] : array();

		if ( ! empty( $primary_meta_query ) && ! empty( $existing_meta_query ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$primary_meta_query,
				$existing_meta_query,
			);
		} elseif ( ! empty( $primary_meta_query ) ) {
			$meta_query = array(
				$primary_meta_query,
			);
		} elseif ( ! empty( $existing_meta_query ) ) {
			$meta_query = $existing_meta_query;
		}

		$this->__construct( $meta_query );
	}

	public function get_cast_for_type( $type = '' ) {
		if ( empty( $type ) )
			return 'CHAR';

		$meta_type = strtoupper( $type );

		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $meta_type ) )
			return 'CHAR';

		if ( 'NUMERIC' == $meta_type )
			$meta_type = 'SIGNED';

		return $meta_type;
	}

	public function get_sql( $type, $primary_table, $primary_id_column, $context = null ) {
		if ( ! $meta_table = _get_meta_table( $type ) ) {
			return false;
		}

		$this->meta_table     = $meta_table;
		$this->meta_id_column = sanitize_key( $type . '_id' );

		$this->primary_table     = $primary_table;
		$this->primary_id_column = $primary_id_column;

		$sql = $this->get_sql_clauses();

		if ( false !== strpos( $sql['join'], 'LEFT JOIN' ) ) {
			$sql['join'] = str_replace( 'INNER JOIN', 'LEFT JOIN', $sql['join'] );
		}

		return apply_filters_ref_array( 'get_meta_sql', array( $sql, $this->queries, $type, $primary_table, $primary_id_column, $context ) );
	}

	protected function get_sql_clauses() {
		$queries = $this->queries;
		$sql = $this->get_sql_for_query( $queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	protected function get_sql_for_query( &$query, $depth = 0 ) {
		$sql_chunks = array(
			'join'  => array(),
			'where' => array(),
		);

		$sql = array(
			'join'  => '',
			'where' => '',
		);

		$indent = '';
		for ( $i = 0; $i < $depth; $i++ ) {
			$indent .= "  ";
		}

		foreach ( $query as $key => &$clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {

				// This is a first-order clause.
				if ( $this->is_first_order_clause( $clause ) ) {
					$clause_sql = $this->get_sql_for_clause( $clause, $query, $key );

					$where_count = count( $clause_sql['where'] );
					if ( ! $where_count ) {
						$sql_chunks['where'][] = '';
					} elseif ( 1 === $where_count ) {
						$sql_chunks['where'][] = $clause_sql['where'][0];
					} else {
						$sql_chunks['where'][] = '( ' . implode( ' AND ', $clause_sql['where'] ) . ' )';
					}

					$sql_chunks['join'] = array_merge( $sql_chunks['join'], $clause_sql['join'] );
				// This is a subquery, so we recurse.
				} else {
					$clause_sql = $this->get_sql_for_query( $clause, $depth + 1 );

					$sql_chunks['where'][] = $clause_sql['where'];
					$sql_chunks['join'][]  = $clause_sql['join'];
				}
			}
		}

		$sql_chunks['join']  = array_filter( $sql_chunks['join'] );
		$sql_chunks['where'] = array_filter( $sql_chunks['where'] );

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		if ( ! empty( $sql_chunks['join'] ) ) {
			$sql['join'] = implode( ' ', array_unique( $sql_chunks['join'] ) );
		}

		if ( ! empty( $sql_chunks['where'] ) ) {
			$sql['where'] = '( ' . "\n  " . $indent . implode( ' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $sql_chunks['where'] ) . "\n" . $indent . ')';
		}

		return $sql;
	}

	public function get_sql_for_clause( &$clause, $parent_query, $clause_key = '' ) {
		global $wpdb;

		$sql_chunks = array(
			'where' => array(),
			'join' => array(),
		);

		if ( isset( $clause['compare'] ) ) {
			$clause['compare'] = strtoupper( $clause['compare'] );
		} else {
			$clause['compare'] = isset( $clause['value'] ) && is_array( $clause['value'] ) ? 'IN' : '=';
		}

		if ( ! in_array( $clause['compare'], array(
			'=', '!=', '>', '>=', '<', '<=',
			'LIKE', 'NOT LIKE',
			'IN', 'NOT IN',
			'BETWEEN', 'NOT BETWEEN',
			'EXISTS', 'NOT EXISTS',
			'REGEXP', 'NOT REGEXP', 'RLIKE'
		) ) ) {
			$clause['compare'] = '=';
		}

		$meta_compare = $clause['compare'];

		// First build the JOIN clause, if one is required.
		$join = '';

		// We prefer to avoid joins if possible. Look for an existing join compatible with this clause.
		$alias = $this->find_compatible_table_alias( $clause, $parent_query );
		if ( false === $alias ) {
			$i = count( $this->table_aliases );
			$alias = $i ? 'mt' . $i : $this->meta_table;

			// JOIN clauses for NOT EXISTS have their own syntax.
			if ( 'NOT EXISTS' === $meta_compare ) {
				$join .= " LEFT JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';
				$join .= $wpdb->prepare( " ON ($this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $alias.meta_key = %s )", $clause['key'] );

			// All other JOIN clauses.
			} else {
				$join .= " INNER JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';
				$join .= " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column )";
			}

			$this->table_aliases[] = $alias;
			$sql_chunks['join'][] = $join;
		}

		// Save the alias to this clause, for future siblings to find.
		$clause['alias'] = $alias;

		// Determine the data type.
		$_meta_type = isset( $clause['type'] ) ? $clause['type'] : '';
		$meta_type  = $this->get_cast_for_type( $_meta_type );
		$clause['cast'] = $meta_type;

		// Fallback for clause keys is the table alias. Key must be a string.
		if ( is_int( $clause_key ) || ! $clause_key ) {
			$clause_key = $clause['alias'];
		}

		// Ensure unique clause keys, so none are overwritten.
		$iterator = 1;
		$clause_key_base = $clause_key;
		while ( isset( $this->clauses[ $clause_key ] ) ) {
			$clause_key = $clause_key_base . '-' . $iterator;
			$iterator++;
		}

		// Store the clause in our flat array.
		$this->clauses[ $clause_key ] =& $clause;

		// Next, build the WHERE clause.

		// meta_key.
		if ( array_key_exists( 'key', $clause ) ) {
			if ( 'NOT EXISTS' === $meta_compare ) {
				$sql_chunks['where'][] = $alias . '.' . $this->meta_id_column . ' IS NULL';
			} else {
				$sql_chunks['where'][] = $wpdb->prepare( "$alias.meta_key = %s", trim( $clause['key'] ) );
			}
		}

		// meta_value.
		if ( array_key_exists( 'value', $clause ) ) {
			$meta_value = $clause['value'];

			if ( in_array( $meta_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
				if ( ! is_array( $meta_value ) ) {
					$meta_value = preg_split( '/[,\s]+/', $meta_value );
				}
			} else {
				$meta_value = trim( $meta_value );
			}

			switch ( $meta_compare ) {
				case 'IN' :
				case 'NOT IN' :
					$meta_compare_string = '(' . substr( str_repeat( ',%s', count( $meta_value ) ), 1 ) . ')';
					$where = $wpdb->prepare( $meta_compare_string, $meta_value );
					break;

				case 'BETWEEN' :
				case 'NOT BETWEEN' :
					$meta_value = array_slice( $meta_value, 0, 2 );
					$where = $wpdb->prepare( '%s AND %s', $meta_value );
					break;

				case 'LIKE' :
				case 'NOT LIKE' :
					$meta_value = '%' . $wpdb->esc_like( $meta_value ) . '%';
					$where = $wpdb->prepare( '%s', $meta_value );
					break;

				// EXISTS with a value is interpreted as '='.
				case 'EXISTS' :
					$meta_compare = '=';
					$where = $wpdb->prepare( '%s', $meta_value );
					break;

				// 'value' is ignored for NOT EXISTS.
				case 'NOT EXISTS' :
					$where = '';
					break;

				default :
					$where = $wpdb->prepare( '%s', $meta_value );
					break;

			}

			if ( $where ) {
				$sql_chunks['where'][] = "CAST($alias.meta_value AS {$meta_type}) {$meta_compare} {$where}";
			}
		}

		if ( 1 < count( $sql_chunks['where'] ) ) {
			$sql_chunks['where'] = array( '( ' . implode( ' AND ', $sql_chunks['where'] ) . ' )' );
		}

		return $sql_chunks;
	}

	public function get_clauses() {
		return $this->clauses;
	}

	protected function find_compatible_table_alias( $clause, $parent_query ) {
		$alias = false;
		foreach ( $parent_query as $sibling ) {
			if ( empty( $sibling['alias'] ) ) {
				continue;
			}

			if ( ! is_array( $sibling ) || ! $this->is_first_order_clause( $sibling ) ) {
				continue;
			}

			$compatible_compares = array();

			if ( 'OR' === $parent_query['relation'] ) {
				$compatible_compares = array( '=', 'IN', 'BETWEEN', 'LIKE', 'REGEXP', 'RLIKE', '>', '>=', '<', '<=' );

			} elseif ( isset( $sibling['key'] ) && isset( $clause['key'] ) && $sibling['key'] === $clause['key'] ) {
				$compatible_compares = array( '!=', 'NOT IN', 'NOT LIKE' );
			}

			$clause_compare  = strtoupper( $clause['compare'] );
			$sibling_compare = strtoupper( $sibling['compare'] );
			if ( in_array( $clause_compare, $compatible_compares ) && in_array( $sibling_compare, $compatible_compares ) ) {
				$alias = $sibling['alias'];
				break;
			}
		}

		return apply_filters( 'meta_query_find_compatible_table_alias', $alias, $clause, $parent_query, $this ) ;
	}

	public function has_or_relation() {
		return $this->has_or_relation;
	}
}
