<?php

class WP_Date_Query {

	public $queries = array();

	public $relation = 'AND';

	public $column = 'post_date';

	public $compare = '=';

	public $time_keys = array( 'after', 'before', 'year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second' );

	public function __construct( $date_query, $default_column = 'post_date' ) {

		if ( isset( $date_query['relation'] ) && 'OR' === strtoupper( $date_query['relation'] ) ) {
			$this->relation = 'OR';
		} else {
			$this->relation = 'AND';
		}

		if ( ! is_array( $date_query ) ) {
			return;
		}

		if ( ! isset( $date_query[0] ) && ! empty( $date_query ) ) {
			$date_query = array( $date_query );
		}

		if ( empty( $date_query ) ) {
			return;
		}

		if ( ! empty( $date_query['column'] ) ) {
			$date_query['column'] = esc_sql( $date_query['column'] );
		} else {
			$date_query['column'] = esc_sql( $default_column );
		}

		$this->column = $this->validate_column( $this->column );

		$this->compare = $this->get_compare( $date_query );

		$this->queries = $this->sanitize_query( $date_query );
	}

	public function sanitize_query( $queries, $parent_query = null ) {
		$cleaned_query = array();

		$defaults = array(
			'column'   => 'post_date',
			'compare'  => '=',
			'relation' => 'AND',
		);

		foreach ( $queries as $qkey => $qvalue ) {
			if ( is_numeric( $qkey ) && ! is_array( $qvalue ) ) {
				unset( $queries[ $qkey ] );
			}
		}

		foreach ( $defaults as $dkey => $dvalue ) {
			if ( isset( $queries[ $dkey ] ) ) {
				continue;
			}

			if ( isset( $parent_query[ $dkey ] ) ) {
				$queries[ $dkey ] = $parent_query[ $dkey ];
			} else {
				$queries[ $dkey ] = $dvalue;
			}
		}

		// Validate the dates passed in the query.
		if ( $this->is_first_order_clause( $queries ) ) {
			$this->validate_date_values( $queries );
		}

		foreach ( $queries as $key => $q ) {
			if ( ! is_array( $q ) || in_array( $key, $this->time_keys, true ) ) {
				// This is a first-order query. Trust the values and sanitize when building SQL.
				$cleaned_query[ $key ] = $q;
			} else {
				// Any array without a time key is another query, so we recurse.
				$cleaned_query[] = $this->sanitize_query( $q, $queries );
			}
		}

		return $cleaned_query;
	}

	protected function is_first_order_clause( $query ) {
		$time_keys = array_intersect( $this->time_keys, array_keys( $query ) );
		return ! empty( $time_keys );
	}

	public function get_compare( $query ) {
		if ( ! empty( $query['compare'] ) && in_array( $query['compare'], array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) )
			return strtoupper( $query['compare'] );

		return $this->compare;
	}

	public function validate_date_values( $date_query = array() ) {
		if ( empty( $date_query ) ) {
			return false;
		}

		$valid = true;

		if ( array_key_exists( 'before', $date_query ) && is_array( $date_query['before'] ) ){
			$valid = $this->validate_date_values( $date_query['before'] );
		}

		if ( array_key_exists( 'after', $date_query ) && is_array( $date_query['after'] ) ){
			$valid = $this->validate_date_values( $date_query['after'] );
		}

		// Array containing all min-max checks.
		$min_max_checks = array();

		// Days per year.
		if ( array_key_exists( 'year', $date_query ) ) {
			if ( is_array( $date_query['year'] ) ) {
				$_year = reset( $date_query['year'] );
			} else {
				$_year = $date_query['year'];
			}

			$max_days_of_year = date( 'z', mktime( 0, 0, 0, 12, 31, $_year ) ) + 1;
		} else {
			// otherwise we use the max of 366 (leap-year)
			$max_days_of_year = 366;
		}

		$min_max_checks['dayofyear'] = array(
			'min' => 1,
			'max' => $max_days_of_year
		);

		// Days per week.
		$min_max_checks['dayofweek'] = array(
			'min' => 1,
			'max' => 7
		);

		// Days per week.
		$min_max_checks['dayofweek_iso'] = array(
			'min' => 1,
			'max' => 7
		);

		// Months per year.
		$min_max_checks['month'] = array(
			'min' => 1,
			'max' => 12
		);

		if ( isset( $_year ) ) {
			$week_count = date( 'W', mktime( 0, 0, 0, 12, 28, $_year ) );
		} else {
			$week_count = 53;
		}

		$min_max_checks['week'] = array(
			'min' => 1,
			'max' => $week_count
		);

		$min_max_checks['day'] = array(
			'min' => 1,
			'max' => 31
		);

		$min_max_checks['hour'] = array(
			'min' => 0,
			'max' => 23
		);

		$min_max_checks['minute'] = array(
			'min' => 0,
			'max' => 59
		);

		$min_max_checks['second'] = array(
			'min' => 0,
			'max' => 59
		);

		// Concatenate and throw a notice for each invalid value.
		foreach ( $min_max_checks as $key => $check ) {
			if ( ! array_key_exists( $key, $date_query ) ) {
				continue;
			}

			foreach ( (array) $date_query[ $key ] as $_value ) {
				$is_between = $_value >= $check['min'] && $_value <= $check['max'];

				if ( ! is_numeric( $_value ) || ! $is_between ) {
					$error = sprintf(
						'Invalid value %1$s for %2$s. Expected value should be between %3$s and %4$s.',
						'<code>' . esc_html( $_value ) . '</code>',
						'<code>' . esc_html( $key ) . '</code>',
						'<code>' . esc_html( $check['min'] ) . '</code>',
						'<code>' . esc_html( $check['max'] ) . '</code>'
					);

					_doing_it_wrong( __CLASS__, $error, '4.1.0' );

					$valid = false;
				}
			}
		}

		if ( ! $valid ) { return $valid; }

		$day_month_year_error_msg = '';

		$day_exists   = array_key_exists( 'day', $date_query ) && is_numeric( $date_query['day'] );
		$month_exists = array_key_exists( 'month', $date_query ) && is_numeric( $date_query['month'] );
		$year_exists  = array_key_exists( 'year', $date_query ) && is_numeric( $date_query['year'] );

		if ( $day_exists && $month_exists && $year_exists ) {
			if ( ! wp_checkdate( $date_query['month'], $date_query['day'], $date_query['year'], sprintf( '%s-%s-%s', $date_query['year'], $date_query['month'], $date_query['day'] ) ) ) {
				$day_month_year_error_msg = sprintf(
					'The following values do not describe a valid date: year %1$s, month %2$s, day %3$s.',
					'<code>' . esc_html( $date_query['year'] ) . '</code>',
					'<code>' . esc_html( $date_query['month'] ) . '</code>',
					'<code>' . esc_html( $date_query['day'] ) . '</code>'
				);

				$valid = false;
			}

		} elseif ( $day_exists && $month_exists ) {
			if ( ! wp_checkdate( $date_query['month'], $date_query['day'], 2012, sprintf( '2012-%s-%s', $date_query['month'], $date_query['day'] ) ) ) {
				$day_month_year_error_msg = sprintf(
					'The following values do not describe a valid date: month %1$s, day %2$s.',
					'<code>' . esc_html( $date_query['month'] ) . '</code>',
					'<code>' . esc_html( $date_query['day'] ) . '</code>'
				);

				$valid = false;
			}
		}

		if ( ! empty( $day_month_year_error_msg ) ) {
			_doing_it_wrong( __CLASS__, $day_month_year_error_msg, '4.1.0' );
		}

		return $valid;
	}

	public function validate_column( $column ) {
		global $wpdb;

		$valid_columns = array(
			'post_date', 'post_date_gmt', 'post_modified',
			'post_modified_gmt', 'comment_date', 'comment_date_gmt',
			'user_registered',
		);

		if ( false === strpos( $column, '.' ) ) {
			if ( ! in_array( $column, apply_filters( 'date_query_valid_columns', $valid_columns ) ) ) {
				$column = 'post_date';
			}

			$known_columns = array(
				$wpdb->posts => array(
					'post_date',
					'post_date_gmt',
					'post_modified',
					'post_modified_gmt',
				),
				$wpdb->comments => array(
					'comment_date',
					'comment_date_gmt',
				),
				$wpdb->users => array(
					'user_registered',
				),
			);

			foreach ( $known_columns as $table_name => $table_columns ) {
				if ( in_array( $column, $table_columns ) ) {
					$column = $table_name . '.' . $column;
					break;
				}
			}

		}

		return preg_replace( '/[^a-zA-Z0-9_$\.]/', '', $column );
	}

	public function get_sql() {
		$sql = $this->get_sql_clauses();

		$where = $sql['where'];

		return apply_filters( 'get_date_sql', $where, $this );
	}

	protected function get_sql_clauses() {
		$sql = $this->get_sql_for_query( $this->queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	protected function get_sql_for_query( $query, $depth = 0 ) {
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

		foreach ( $query as $key => $clause ) {
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
				// This is a subquery, so we recurse.
				} else {
					$clause_sql = $this->get_sql_for_query( $clause, $depth + 1 );

					$sql_chunks['where'][] = $clause_sql['where'];
					$sql_chunks['join'][]  = $clause_sql['join'];
				}
			}
		}

		// Filter to remove empties.
		$sql_chunks['join']  = array_filter( $sql_chunks['join'] );
		$sql_chunks['where'] = array_filter( $sql_chunks['where'] );

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		// Filter duplicate JOIN clauses and combine into a single string.
		if ( ! empty( $sql_chunks['join'] ) ) {
			$sql['join'] = implode( ' ', array_unique( $sql_chunks['join'] ) );
		}

		// Generate a single WHERE clause with proper brackets and indentation.
		if ( ! empty( $sql_chunks['where'] ) ) {
			$sql['where'] = '( ' . "\n  " . $indent . implode( ' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $sql_chunks['where'] ) . "\n" . $indent . ')';
		}

		return $sql;
	}

	protected function get_sql_for_subquery( $query ) {
		return $this->get_sql_for_clause( $query, '' );
	}

	protected function get_sql_for_clause( $query, $parent_query ) {
		global $wpdb;

		// The sub-parts of a $where part.
		$where_parts = array();

		$column = ( ! empty( $query['column'] ) ) ? esc_sql( $query['column'] ) : $this->column;

		$column = $this->validate_column( $column );

		$compare = $this->get_compare( $query );

		$inclusive = ! empty( $query['inclusive'] );

		// Assign greater- and less-than values.
		$lt = '<';
		$gt = '>';

		if ( $inclusive ) {
			$lt .= '=';
			$gt .= '=';
		}

		// Range queries.
		if ( ! empty( $query['after'] ) )
			$where_parts[] = $wpdb->prepare( "$column $gt %s", $this->build_mysql_datetime( $query['after'], ! $inclusive ) );

		if ( ! empty( $query['before'] ) )
			$where_parts[] = $wpdb->prepare( "$column $lt %s", $this->build_mysql_datetime( $query['before'], $inclusive ) );

		// Specific value queries.

		if ( isset( $query['year'] ) && $value = $this->build_value( $compare, $query['year'] ) )
			$where_parts[] = "YEAR( $column ) $compare $value";

		if ( isset( $query['month'] ) && $value = $this->build_value( $compare, $query['month'] ) ) {
			$where_parts[] = "MONTH( $column ) $compare $value";
		} elseif ( isset( $query['monthnum'] ) && $value = $this->build_value( $compare, $query['monthnum'] ) ) {
			$where_parts[] = "MONTH( $column ) $compare $value";
		}
		if ( isset( $query['week'] ) && false !== ( $value = $this->build_value( $compare, $query['week'] ) ) ) {
			$where_parts[] = _wp_mysql_week( $column ) . " $compare $value";
		} elseif ( isset( $query['w'] ) && false !== ( $value = $this->build_value( $compare, $query['w'] ) ) ) {
			$where_parts[] = _wp_mysql_week( $column ) . " $compare $value";
		}
		if ( isset( $query['dayofyear'] ) && $value = $this->build_value( $compare, $query['dayofyear'] ) )
			$where_parts[] = "DAYOFYEAR( $column ) $compare $value";

		if ( isset( $query['day'] ) && $value = $this->build_value( $compare, $query['day'] ) )
			$where_parts[] = "DAYOFMONTH( $column ) $compare $value";

		if ( isset( $query['dayofweek'] ) && $value = $this->build_value( $compare, $query['dayofweek'] ) )
			$where_parts[] = "DAYOFWEEK( $column ) $compare $value";

		if ( isset( $query['dayofweek_iso'] ) && $value = $this->build_value( $compare, $query['dayofweek_iso'] ) )
			$where_parts[] = "WEEKDAY( $column ) + 1 $compare $value";

		if ( isset( $query['hour'] ) || isset( $query['minute'] ) || isset( $query['second'] ) ) {
			// Avoid notices.
			foreach ( array( 'hour', 'minute', 'second' ) as $unit ) {
				if ( ! isset( $query[ $unit ] ) ) {
					$query[ $unit ] = null;
				}
			}

			if ( $time_query = $this->build_time_query( $column, $compare, $query['hour'], $query['minute'], $query['second'] ) ) {
				$where_parts[] = $time_query;
			}
		}

		/*
		 * Return an array of 'join' and 'where' for compatibility
		 * with other query classes.
		 */
		return array(
			'where' => $where_parts,
			'join'  => array(),
		);
	}

	public function build_value( $compare, $value ) {
		if ( ! isset( $value ) )
			return false;

		switch ( $compare ) {
			case 'IN':
			case 'NOT IN':
				$value = (array) $value;

				// Remove non-numeric values.
				$value = array_filter( $value, 'is_numeric' );

				if ( empty( $value ) ) {
					return false;
				}

				return '(' . implode( ',', array_map( 'intval', $value ) ) . ')';

			case 'BETWEEN':
			case 'NOT BETWEEN':
				if ( ! is_array( $value ) || 2 != count( $value ) ) {
					$value = array( $value, $value );
				} else {
					$value = array_values( $value );
				}

				// If either value is non-numeric, bail.
				foreach ( $value as $v ) {
					if ( ! is_numeric( $v ) ) {
						return false;
					}
				}

				$value = array_map( 'intval', $value );

				return $value[0] . ' AND ' . $value[1];

			default;
				if ( ! is_numeric( $value ) ) {
					return false;
				}

				return (int) $value;
		}
	}

	public function build_mysql_datetime( $datetime, $default_to_max = false ) {
		$now = current_time( 'timestamp' );

		if ( ! is_array( $datetime ) ) {
			if ( preg_match( '/^(\d{4})$/', $datetime, $matches ) ) {
				// Y
				$datetime = array(
					'year' => intval( $matches[1] ),
				);

			} elseif ( preg_match( '/^(\d{4})\-(\d{2})$/', $datetime, $matches ) ) {
				// Y-m
				$datetime = array(
					'year'  => intval( $matches[1] ),
					'month' => intval( $matches[2] ),
				);

			} elseif ( preg_match( '/^(\d{4})\-(\d{2})\-(\d{2})$/', $datetime, $matches ) ) {
				// Y-m-d
				$datetime = array(
					'year'  => intval( $matches[1] ),
					'month' => intval( $matches[2] ),
					'day'   => intval( $matches[3] ),
				);

			} elseif ( preg_match( '/^(\d{4})\-(\d{2})\-(\d{2}) (\d{2}):(\d{2})$/', $datetime, $matches ) ) {
				// Y-m-d H:i
				$datetime = array(
					'year'   => intval( $matches[1] ),
					'month'  => intval( $matches[2] ),
					'day'    => intval( $matches[3] ),
					'hour'   => intval( $matches[4] ),
					'minute' => intval( $matches[5] ),
				);
			}

			// If no match is found, we don't support default_to_max.
			if ( ! is_array( $datetime ) ) {
				// @todo Timezone issues here possibly
				return gmdate( 'Y-m-d H:i:s', strtotime( $datetime, $now ) );
			}
		}

		$datetime = array_map( 'absint', $datetime );

		if ( ! isset( $datetime['year'] ) )
			$datetime['year'] = gmdate( 'Y', $now );

		if ( ! isset( $datetime['month'] ) )
			$datetime['month'] = ( $default_to_max ) ? 12 : 1;

		if ( ! isset( $datetime['day'] ) )
			$datetime['day'] = ( $default_to_max ) ? (int) date( 't', mktime( 0, 0, 0, $datetime['month'], 1, $datetime['year'] ) ) : 1;

		if ( ! isset( $datetime['hour'] ) )
			$datetime['hour'] = ( $default_to_max ) ? 23 : 0;

		if ( ! isset( $datetime['minute'] ) )
			$datetime['minute'] = ( $default_to_max ) ? 59 : 0;

		if ( ! isset( $datetime['second'] ) )
			$datetime['second'] = ( $default_to_max ) ? 59 : 0;

		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $datetime['year'], $datetime['month'], $datetime['day'], $datetime['hour'], $datetime['minute'], $datetime['second'] );
	}

	public function build_time_query( $column, $compare, $hour = null, $minute = null, $second = null ) {
		global $wpdb;

		// Have to have at least one
		if ( ! isset( $hour ) && ! isset( $minute ) && ! isset( $second ) )
			return false;

		// Complex combined queries aren't supported for multi-value queries
		if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			$return = array();

			if ( isset( $hour ) && false !== ( $value = $this->build_value( $compare, $hour ) ) )
				$return[] = "HOUR( $column ) $compare $value";

			if ( isset( $minute ) && false !== ( $value = $this->build_value( $compare, $minute ) ) )
				$return[] = "MINUTE( $column ) $compare $value";

			if ( isset( $second ) && false !== ( $value = $this->build_value( $compare, $second ) ) )
				$return[] = "SECOND( $column ) $compare $value";

			return implode( ' AND ', $return );
		}

		// Cases where just one unit is set
		if ( isset( $hour ) && ! isset( $minute ) && ! isset( $second ) && false !== ( $value = $this->build_value( $compare, $hour ) ) ) {
			return "HOUR( $column ) $compare $value";
		} elseif ( ! isset( $hour ) && isset( $minute ) && ! isset( $second ) && false !== ( $value = $this->build_value( $compare, $minute ) ) ) {
			return "MINUTE( $column ) $compare $value";
		} elseif ( ! isset( $hour ) && ! isset( $minute ) && isset( $second ) && false !== ( $value = $this->build_value( $compare, $second ) ) ) {
			return "SECOND( $column ) $compare $value";
		}

		// Single units were already handled. Since hour & second isn't allowed, minute must to be set.
		if ( ! isset( $minute ) )
			return false;

		$format = $time = '';

		// Hour
		if ( null !== $hour ) {
			$format .= '%H.';
			$time   .= sprintf( '%02d', $hour ) . '.';
		} else {
			$format .= '0.';
			$time   .= '0.';
		}

		// Minute
		$format .= '%i';
		$time   .= sprintf( '%02d', $minute );

		if ( isset( $second ) ) {
			$format .= '%s';
			$time   .= sprintf( '%02d', $second );
		}

		return $wpdb->prepare( "DATE_FORMAT( $column, %s ) $compare %f", $format, $time );
	}
}
