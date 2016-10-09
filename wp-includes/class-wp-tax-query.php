<?php

class WP_Tax_Query {

	public $queries = array();

	public $relation;

	private static $no_results = array( 'join' => array( '' ), 'where' => array( '0 = 1' ) );

	protected $table_aliases = array();

	public $queried_terms = array();

	public $primary_table;

	public $primary_id_column;

	public function __construct( $tax_query ) {
		if ( isset( $tax_query['relation'] ) ) {
			$this->relation = $this->sanitize_relation( $tax_query['relation'] );
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $this->sanitize_query( $tax_query );
	}

	public function sanitize_query( $queries ) {
		$cleaned_query = array();

		$defaults = array(
			'taxonomy' => '',
			'terms' => array(),
			'field' => 'term_id',
			'operator' => 'IN',
			'include_children' => true,
		);

		foreach ( $queries as $key => $query ) {
			if ( 'relation' === $key ) {
				$cleaned_query['relation'] = $this->sanitize_relation( $query );

			// First-order clause.
			} elseif ( self::is_first_order_clause( $query ) ) {

				$cleaned_clause = array_merge( $defaults, $query );
				$cleaned_clause['terms'] = (array) $cleaned_clause['terms'];
				$cleaned_query[] = $cleaned_clause;

				if ( ! empty( $cleaned_clause['taxonomy'] ) && 'NOT IN' !== $cleaned_clause['operator'] ) {
					$taxonomy = $cleaned_clause['taxonomy'];
					if ( ! isset( $this->queried_terms[ $taxonomy ] ) ) {
						$this->queried_terms[ $taxonomy ] = array();
					}

					if ( ! empty( $cleaned_clause['terms'] ) && ! isset( $this->queried_terms[ $taxonomy ]['terms'] ) ) {
						$this->queried_terms[ $taxonomy ]['terms'] = $cleaned_clause['terms'];
					}

					if ( ! empty( $cleaned_clause['field'] ) && ! isset( $this->queried_terms[ $taxonomy ]['field'] ) ) {
						$this->queried_terms[ $taxonomy ]['field'] = $cleaned_clause['field'];
					}
				}

			} elseif ( is_array( $query ) ) {
				$cleaned_subquery = $this->sanitize_query( $query );

				if ( ! empty( $cleaned_subquery ) ) {
					if ( ! isset( $cleaned_subquery['relation'] ) ) {
						$cleaned_subquery['relation'] = 'AND';
					}

					$cleaned_query[] = $cleaned_subquery;
				}
			}
		}

		return $cleaned_query;
	}

	public function sanitize_relation( $relation ) {
		if ( 'OR' === strtoupper( $relation ) ) {
			return 'OR';
		} else {
			return 'AND';
		}
	}

	protected static function is_first_order_clause( $query ) {
		return is_array( $query ) && ( empty( $query ) || array_key_exists( 'terms', $query ) || array_key_exists( 'taxonomy', $query ) || array_key_exists( 'include_children', $query ) || array_key_exists( 'field', $query ) || array_key_exists( 'operator', $query ) );
	}

	public function get_sql( $primary_table, $primary_id_column ) {
		$this->primary_table = $primary_table;
		$this->primary_id_column = $primary_id_column;

		return $this->get_sql_clauses();
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
					$clause_sql = $this->get_sql_for_clause( $clause, $query );

					$where_count = count( $clause_sql['where'] );
					if ( ! $where_count ) {
						$sql_chunks['where'][] = '';
					} elseif ( 1 === $where_count ) {
						$sql_chunks['where'][] = $clause_sql['where'][0];
					} else {
						$sql_chunks['where'][] = '( ' . implode( ' AND ', $clause_sql['where'] ) . ' )';
					}

					$sql_chunks['join'] = array_merge( $sql_chunks['join'], $clause_sql['join'] );
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

	public function get_sql_for_clause( &$clause, $parent_query ) {
		global $wpdb;

		$sql = array(
			'where' => array(),
			'join'  => array(),
		);

		$join = $where = '';

		$this->clean_query( $clause );

		if ( is_wp_error( $clause ) ) {
			return self::$no_results;
		}

		$terms = $clause['terms'];
		$operator = strtoupper( $clause['operator'] );

		if ( 'IN' == $operator ) {

			if ( empty( $terms ) ) {
				return self::$no_results;
			}

			$terms = implode( ',', $terms );

			$alias = $this->find_compatible_table_alias( $clause, $parent_query );
			if ( false === $alias ) {
				$i = count( $this->table_aliases );
				$alias = $i ? 'tt' . $i : $wpdb->term_relationships;

				$this->table_aliases[] = $alias;

				$clause['alias'] = $alias;

				$join .= " INNER JOIN $wpdb->term_relationships";
				$join .= $i ? " AS $alias" : '';
				$join .= " ON ($this->primary_table.$this->primary_id_column = $alias.object_id)";
			}


			$where = "$alias.term_taxonomy_id $operator ($terms)";

		} elseif ( 'NOT IN' == $operator ) {

			if ( empty( $terms ) ) {
				return $sql;
			}

			$terms = implode( ',', $terms );

			$where = "$this->primary_table.$this->primary_id_column NOT IN (
				SELECT object_id
				FROM $wpdb->term_relationships
				WHERE term_taxonomy_id IN ($terms)
			)";

		} elseif ( 'AND' == $operator ) {

			if ( empty( $terms ) ) {
				return $sql;
			}

			$num_terms = count( $terms );

			$terms = implode( ',', $terms );

			$where = "(
				SELECT COUNT(1)
				FROM $wpdb->term_relationships
				WHERE term_taxonomy_id IN ($terms)
				AND object_id = $this->primary_table.$this->primary_id_column
			) = $num_terms";

		} elseif ( 'NOT EXISTS' === $operator || 'EXISTS' === $operator ) {

			$where = $wpdb->prepare( "$operator (
				SELECT 1
				FROM $wpdb->term_relationships
				INNER JOIN $wpdb->term_taxonomy
				ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
				WHERE $wpdb->term_taxonomy.taxonomy = %s
				AND $wpdb->term_relationships.object_id = $this->primary_table.$this->primary_id_column
			)", $clause['taxonomy'] );

		}

		$sql['join'][]  = $join;
		$sql['where'][] = $where;
		return $sql;
	}

	protected function find_compatible_table_alias( $clause, $parent_query ) {
		$alias = false;
		if ( ! isset( $clause['operator'] ) || 'IN' !== $clause['operator'] ) {
			return $alias;
		}
		if ( ! isset( $parent_query['relation'] ) || 'OR' !== $parent_query['relation'] ) {
			return $alias;
		}

		$compatible_operators = array( 'IN' );

		foreach ( $parent_query as $sibling ) {
			if ( ! is_array( $sibling ) || ! $this->is_first_order_clause( $sibling ) ) {
				continue;
			}

			if ( empty( $sibling['alias'] ) || empty( $sibling['operator'] ) ) {
				continue;
			}
			if ( in_array( strtoupper( $sibling['operator'] ), $compatible_operators ) ) {
				$alias = $sibling['alias'];
				break;
			}
		}

		return $alias;
	}

	private function clean_query( &$query ) {
		if ( empty( $query['taxonomy'] ) ) {
			if ( 'term_taxonomy_id' !== $query['field'] ) {
				$query = new WP_Error( 'Invalid taxonomy' );
				return;
			}
			$query['include_children'] = false;
		} elseif ( ! taxonomy_exists( $query['taxonomy'] ) ) {
			$query = new WP_Error( 'Invalid taxonomy' );
			return;
		}

		$query['terms'] = array_unique( (array) $query['terms'] );

		if ( is_taxonomy_hierarchical( $query['taxonomy'] ) && $query['include_children'] ) {
			$this->transform_query( $query, 'term_id' );

			if ( is_wp_error( $query ) )
				return;

			$children = array();
			foreach ( $query['terms'] as $term ) {
				$children = array_merge( $children, get_term_children( $term, $query['taxonomy'] ) );
				$children[] = $term;
			}
			$query['terms'] = $children;
		}

		$this->transform_query( $query, 'term_taxonomy_id' );
	}

	public function transform_query( &$query, $resulting_field ) {
		global $wpdb;

		if ( empty( $query['terms'] ) )
			return;

		if ( $query['field'] == $resulting_field )
			return;

		$resulting_field = sanitize_key( $resulting_field );

		switch ( $query['field'] ) {
			case 'slug':
			case 'name':
				foreach ( $query['terms'] as &$term ) {
					$term = "'" . esc_sql( sanitize_term_field( $query['field'], $term, 0, $query['taxonomy'], 'db' ) ) . "'";
				}

				$terms = implode( ",", $query['terms'] );

				$terms = $wpdb->get_col( "
					SELECT $wpdb->term_taxonomy.$resulting_field
					FROM $wpdb->term_taxonomy
					INNER JOIN $wpdb->terms USING (term_id)
					WHERE taxonomy = '{$query['taxonomy']}'
					AND $wpdb->terms.{$query['field']} IN ($terms)
				" );
				break;
			case 'term_taxonomy_id':
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col( "
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE term_taxonomy_id IN ($terms)
				" );
				break;
			default:
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col( "
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE taxonomy = '{$query['taxonomy']}'
					AND term_id IN ($terms)
				" );
		}

		if ( 'AND' == $query['operator'] && count( $terms ) < count( $query['terms'] ) ) {
			$query = new WP_Error( 'Inexistent terms' );
			return;
		}

		$query['terms'] = $terms;
		$query['field'] = $resulting_field;
	}
}
