<?php
/**
 * List Table API: WP_Plugins_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

class WP_Plugins_List_Table extends WP_List_Table {

	public function __construct( $args = array() ) {
		global $status, $page;

		parent::__construct( array(
			'plural' => 'plugins',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$status = 'all';
		if ( isset( $_REQUEST['plugin_status'] ) && in_array( $_REQUEST['plugin_status'], array( 'active', 'inactive', 'recently_activated', 'upgrade', 'mustuse', 'dropins', 'search' ) ) )
			$status = $_REQUEST['plugin_status'];

		if ( isset($_REQUEST['s']) )
			$_SERVER['REQUEST_URI'] = add_query_arg('s', wp_unslash($_REQUEST['s']) );

		$page = $this->get_pagenum();
	}

	protected function get_table_classes() {
		return array( 'widefat', $this->_args['plural'] );
	}

	public function ajax_user_can() {
		return current_user_can('activate_plugins');
	}

	public function prepare_items() {
		global $status, $plugins, $totals, $page, $orderby, $order, $s;
		wp_reset_vars( array( 'orderby', 'order' ) );
		$all_plugins = apply_filters( 'all_plugins', get_plugins() );
		$plugins = array(
			'all'                => $all_plugins,
			'search'             => array(),
			'active'             => array(),
			'inactive'           => array(),
			'recently_activated' => array(),
			'upgrade'            => array(),
			'mustuse'            => array(),
			'dropins'            => array(),
		);
		$screen = $this->screen;
		if ( apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ) { $plugins['mustuse'] = get_mu_plugins(); }
		if ( apply_filters( 'show_advanced_plugins', true, 'dropins' ) )
			$plugins['dropins'] = get_dropins();
		if ( current_user_can( 'update_plugins' ) ) {
			$current = get_site_transient( 'update_plugins' );
			foreach ( (array) $plugins['all'] as $plugin_file => $plugin_data ) {
				if ( isset( $current->response[ $plugin_file ] ) ) {
					$plugins['all'][ $plugin_file ]['update'] = true;
					$plugins['upgrade'][ $plugin_file ] = $plugins['all'][ $plugin_file ];
				}
			}
		}
		if ( ! $screen->in_admin( 'network' ) ) {
			$show = current_user_can( 'manage_network_plugins' );
			$show_network_active = apply_filters( 'show_network_active_plugins', $show );
		}
		set_transient( 'plugin_slugs', array_keys( $plugins['all'] ), DAY_IN_SECONDS );
		if ( $screen->in_admin( 'network' ) ) {
			$recently_activated = get_site_option( 'recently_activated', array() );
		} else {
			$recently_activated = get_option( 'recently_activated', array() );
		}
		foreach ( $recently_activated as $key => $time ) {
			if ( $time + WEEK_IN_SECONDS < time() ) {
				unset( $recently_activated[$key] );
			}
		}
		if ( $screen->in_admin( 'network' ) ) {
			update_site_option( 'recently_activated', $recently_activated );
		} else {
			update_option( 'recently_activated', $recently_activated );
		}
		$plugin_info = get_site_transient( 'update_plugins' );
		foreach ( (array) $plugins['all'] as $plugin_file => $plugin_data ) {
			if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
				$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
				if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
					$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
				}

			} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
				$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
				if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
					$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
				}
			}

			if ( is_multisite() && ! $screen->in_admin( 'network' ) && is_network_only_plugin( $plugin_file ) && ! is_plugin_active( $plugin_file ) ) {
				if ( $show_network_active ) {
					$plugins['inactive'][ $plugin_file ] = $plugin_data;
				} else {
					unset( $plugins['all'][ $plugin_file ] );
				}
			} elseif ( ! $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) {
				if ( $show_network_active ) {
					$plugins['active'][ $plugin_file ] = $plugin_data;
				} else {
					unset( $plugins['all'][ $plugin_file ] );
				}
			} elseif ( ( ! $screen->in_admin( 'network' ) && is_plugin_active( $plugin_file ) )
				|| ( $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) ) {
				$plugins['active'][ $plugin_file ] = $plugin_data;
			} else {
				if ( isset( $recently_activated[ $plugin_file ] ) ) {
					$plugins['recently_activated'][ $plugin_file ] = $plugin_data;
				}
				$plugins['inactive'][ $plugin_file ] = $plugin_data;
			}
		}

		if ( strlen( $s ) ) {
			$status = 'search';
			$plugins['search'] = array_filter( $plugins['all'], array( $this, '_search_callback' ) );
		}

		$totals = array();
		foreach ( $plugins as $type => $list )
			$totals[ $type ] = count( $list );

		if ( empty( $plugins[ $status ] ) && !in_array( $status, array( 'all', 'search' ) ) )
			$status = 'all';

		$this->items = array();
		foreach ( $plugins[ $status ] as $plugin_file => $plugin_data ) {
			$this->items[$plugin_file] = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, false, true );
		}

		$total_this_page = $totals[ $status ];

		if ( ! $orderby ) {
			$orderby = 'Name';
		} else {
			$orderby = ucfirst( $orderby );
		}

		$order = strtoupper( $order );

		uasort( $this->items, array( $this, '_order_callback' ) );

		$plugins_per_page = $this->get_items_per_page( str_replace( '-', '_', $screen->id . '_per_page' ), 999 );

		$start = ( $page - 1 ) * $plugins_per_page;

		if ( $total_this_page > $plugins_per_page )
			$this->items = array_slice( $this->items, $start, $plugins_per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_this_page,
			'per_page' => $plugins_per_page,
		) );
	}

	public function _search_callback( $plugin ) {
		global $s;

		foreach ( $plugin as $value ) {
			if ( is_string( $value ) && false !== stripos( strip_tags( $value ), urldecode( $s ) ) ) {
				return true;
			}
		}

		return false;
	}

	public function _order_callback( $plugin_a, $plugin_b ) {
		global $orderby, $order;

		$a = $plugin_a[$orderby];
		$b = $plugin_b[$orderby];

		if ( $a == $b )
			return 0;

		if ( 'DESC' === $order ) {
			return strcasecmp( $b, $a );
		} else {
			return strcasecmp( $a, $b );
		}
	}

	public function no_items() {
		global $plugins;

		if ( ! empty( $_REQUEST['s'] ) ) {
			$s = esc_html( wp_unslash( $_REQUEST['s'] ) );

			printf( __( 'No plugins found for &#8220;%s&#8221;.' ), $s );
			if ( ! is_multisite() && current_user_can( 'install_plugins' ) ) {
				echo ' <a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=' . urlencode( $s ) ) ) . '">' . __( 'Search for plugins in the WordPress Plugin Directory.' ) . '</a>';
			}
		} elseif ( ! empty( $plugins['all'] ) )
			_e( 'No plugins found.' );
		else
			_e( 'You do not appear to have any plugins available at this time.' );
	}

	public function get_columns() {
		global $status;
		return array(
			'cb'          => !in_array( $status, array( 'mustuse', 'dropins' ) ) ? '<input type="checkbox" />' : '',
			'name'        => __( 'Plugin' ),
			'description' => __( 'Description' ),
		);
	}

	protected function get_sortable_columns() {
		return array();
	}

	protected function get_views() {
		global $totals, $status;

		$status_links = array();
		foreach ( $totals as $type => $count ) {
			if ( !$count )
				continue;

			switch ( $type ) {
				case 'all':
					$text = _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'plugins' );
					break;
				case 'active':
					$text = _n( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', $count );
					break;
				case 'recently_activated':
					$text = _n( 'Recently Active <span class="count">(%s)</span>', 'Recently Active <span class="count">(%s)</span>', $count );
					break;
				case 'inactive':
					$text = _n( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', $count );
					break;
				case 'mustuse':
					$text = _n( 'Must-Use <span class="count">(%s)</span>', 'Must-Use <span class="count">(%s)</span>', $count );
					break;
				case 'dropins':
					$text = _n( 'Drop-ins <span class="count">(%s)</span>', 'Drop-ins <span class="count">(%s)</span>', $count );
					break;
				case 'upgrade':
					$text = _n( 'Update Available <span class="count">(%s)</span>', 'Update Available <span class="count">(%s)</span>', $count );
					break;
			}

			if ( 'search' !== $type ) {
				$status_links[$type] = sprintf( "<a href='%s' %s>%s</a>",
					add_query_arg('plugin_status', $type, 'plugins.php'),
					( $type === $status ) ? ' class="current"' : '',
					sprintf( $text, number_format_i18n( $count ) )
					);
			}
		}

		return $status_links;
	}

	protected function get_bulk_actions() {
		global $status;

		$actions = array();

		if ( 'active' != $status )
			$actions['activate-selected'] = $this->screen->in_admin( 'network' ) ? 'Network Activate' : 'Activate';

		if ( 'inactive' != $status && 'recent' != $status )
			$actions['deactivate-selected'] = $this->screen->in_admin( 'network' ) ? 'Network Deactivate' : 'Deactivate';

		if ( !is_multisite() || $this->screen->in_admin( 'network' ) ) {
			if ( current_user_can( 'update_plugins' ) )
				$actions['update-selected'] = 'Update';
			if ( current_user_can( 'delete_plugins' ) && ( 'active' != $status ) )
				$actions['delete-selected'] = 'Delete';
		}

		return $actions;
	}

	public function bulk_actions( $which = '' ) {
		global $status;

		if ( in_array( $status, array( 'mustuse', 'dropins' ) ) )
			return;

		parent::bulk_actions( $which );
	}

	protected function extra_tablenav( $which ) {
		global $status;

		if ( ! in_array($status, array('recently_activated', 'mustuse', 'dropins') ) )
			return;

		echo '<div class="alignleft actions">';

		if ( 'recently_activated' == $status ) {
			submit_button( 'Clear List', 'button', 'clear-recent-list', false );
		} elseif ( 'top' === $which && 'mustuse' === $status ) {
			echo '<p>' . sprintf( 'Files in the %s directory are executed automatically.',
				'<code>' . str_replace( ABSPATH, '/', WPMU_PLUGIN_DIR ) . '</code>'
			) . '</p>';
		} elseif ( 'top' === $which && 'dropins' === $status ) {
			echo '<p>' . sprintf( 'Drop-ins are advanced plugins in the %s directory that replace WordPress functionality when present.',
				'<code>' . str_replace( ABSPATH, '', WP_CONTENT_DIR ) . '</code>'
			) . '</p>';
		}
		echo '</div>';
	}

	public function current_action() {
		if ( isset($_POST['clear-recent-list']) )
			return 'clear-recent-list';

		return parent::current_action();
	}

	public function display_rows() {
		global $status;

		if ( is_multisite() && ! $this->screen->in_admin( 'network' ) && in_array( $status, array( 'mustuse', 'dropins' ) ) )
			return;

		foreach ( $this->items as $plugin_file => $plugin_data )
			$this->single_row( array( $plugin_file, $plugin_data ) );
	}

	public function single_row( $item ) {
		global $status, $page, $s, $totals;

		list( $plugin_file, $plugin_data ) = $item;
		$context = $status;
		$screen = $this->screen;

		$actions = array(
			'deactivate' => '',
			'activate' => '',
			'details' => '',
			'edit' => '',
			'delete' => '',
		);

		$restrict_network_active = false;
		$restrict_network_only = false;

		if ( 'mustuse' === $context ) {
			$is_active = true;
		} elseif ( 'dropins' === $context ) {
			$dropins = _get_dropins();
			$plugin_name = $plugin_file;
			if ( $plugin_file != $plugin_data['Name'] )
				$plugin_name .= '<br/>' . $plugin_data['Name'];
			if ( true === ( $dropins[ $plugin_file ][1] ) ) {
				$is_active = true;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . '</strong></p>';
			} elseif ( defined( $dropins[ $plugin_file ][1] ) && constant( $dropins[ $plugin_file ][1] ) ) {
				$is_active = true;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . '</strong></p>';
			} else {
				$is_active = false;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . ' <span class="error-message">' . __( 'Inactive:' ) . '</span></strong> ' .
					sprintf( 'Requires %1$s in %2$s file.',
						"<code>define('" . $dropins[ $plugin_file ][1] . "', true);</code>",
						'<code>wp-config.php</code>'
					) . '</p>';
			}
			if ( $plugin_data['Description'] )
				$description .= '<p>' . $plugin_data['Description'] . '</p>';
		} else {
			if ( $screen->in_admin( 'network' ) ) {
				$is_active = is_plugin_active_for_network( $plugin_file );
			} else {
				$is_active = is_plugin_active( $plugin_file );
				$restrict_network_active = ( is_multisite() && is_plugin_active_for_network( $plugin_file ) );
				$restrict_network_only = ( is_multisite() && is_network_only_plugin( $plugin_file ) && ! $is_active );
			}

			if ( $screen->in_admin( 'network' ) ) {
				if ( $is_active ) {
					if ( current_user_can( 'manage_network_plugins' ) ) {
						$actions['deactivate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file ) . '" aria-label="' . esc_attr( sprintf( __( 'Network deactivate %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Network Deactivate' ) . '</a>';
						}
				} else {
					if ( current_user_can( 'manage_network_plugins' ) ) {
						$actions['activate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file ) . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Network Activate %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Network Activate' ) . '</a>';
					}
					if ( current_user_can( 'delete_plugins' ) && ! is_plugin_active( $plugin_file ) ) {
						$actions['delete'] = '<a href="' . wp_nonce_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins' ) . '" class="delete" aria-label="' . esc_attr( sprintf( __( 'Delete %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Delete' ) . '</a>';
					}
				}
			} else {
				if ( $restrict_network_active ) {
					$actions = array(
						'network_active' => 'Network Active',
					);
				} elseif ( $restrict_network_only ) {
					$actions = array(
						'network_only' => 'Network Only',
					);
				} elseif ( $is_active ) {
					$actions['deactivate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file ) . '" aria-label="' . esc_attr( sprintf( __( 'Deactivate %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Deactivate' ) . '</a>';
				} else {
					$actions['activate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file ) . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Activate %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Activate' ) . '</a>';

					if ( ! is_multisite() && current_user_can( 'delete_plugins' ) ) {
						$actions['delete'] = '<a href="' . wp_nonce_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins' ) . '" class="delete" aria-label="' . esc_attr( sprintf( __( 'Delete %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Delete' ) . '</a>';
					}
				}

			 }
			if ( ( ! is_multisite() || $screen->in_admin( 'network' ) ) && current_user_can( 'edit_plugins' ) && is_writable( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				$actions['edit'] = '<a href="plugin-editor.php?file=' . $plugin_file . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Edit %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Edit' ) . '</a>';
			}
		}

		$actions = array_filter( $actions );

		if ( $screen->in_admin( 'network' ) ) {
			$actions = apply_filters( 'network_admin_plugin_action_links', $actions, $plugin_file, $plugin_data, $context );
			$actions = apply_filters( "network_admin_plugin_action_links_{$plugin_file}", $actions, $plugin_file, $plugin_data, $context );

		} else {
			$actions = apply_filters( 'plugin_action_links', $actions, $plugin_file, $plugin_data, $context );
			$actions = apply_filters( "plugin_action_links_{$plugin_file}", $actions, $plugin_file, $plugin_data, $context );

		}

		$class = $is_active ? 'active' : 'inactive';
		$checkbox_id =  "checkbox_" . md5($plugin_data['Name']);
		if ( $restrict_network_active || $restrict_network_only || in_array( $status, array( 'mustuse', 'dropins' ) ) ) {
			$checkbox = '';
		} else {
			$checkbox = "<label class='screen-reader-text' for='" . $checkbox_id . "' >" . sprintf( __( 'Select %s' ), $plugin_data['Name'] ) . "</label>"
				. "<input type='checkbox' name='checked[]' value='" . esc_attr( $plugin_file ) . "' id='" . $checkbox_id . "' />";
		}
		if ( 'dropins' != $context ) {
			$description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
			$plugin_name = $plugin_data['Name'];
		}

		if ( ! empty( $totals['upgrade'] ) && ! empty( $plugin_data['update'] ) )
			$class .= ' update';

		$plugin_slug = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : sanitize_title( $plugin_name );
		printf( '<tr class="%s" data-slug="%s" data-plugin="%s">',
			esc_attr( $class ),
			esc_attr( $plugin_slug ),
			esc_attr( $plugin_file )
		);

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$extra_classes = '';
			if ( in_array( $column_name, $hidden ) ) {
				$extra_classes = ' hidden';
			}

			switch ( $column_name ) {
				case 'cb':
					echo "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'name':
					echo "<td class='plugin-title column-primary'><strong>$plugin_name</strong>";
					echo $this->row_actions( $actions, true );
					echo "</td>";
					break;
				case 'description':
					$classes = 'column-description desc';

					echo "<td class='$classes{$extra_classes}'>
						<div class='plugin-description'>$description</div>
						<div class='$class second plugin-version-author-uri'>";

					$plugin_meta = array();
					if ( !empty( $plugin_data['Version'] ) )
						$plugin_meta[] = sprintf( 'Version %s', $plugin_data['Version'] );
					if ( !empty( $plugin_data['Author'] ) ) {
						$author = $plugin_data['Author'];
						if ( !empty( $plugin_data['AuthorURI'] ) )
							$author = '<a href="' . $plugin_data['AuthorURI'] . '">' . $plugin_data['Author'] . '</a>';
						$plugin_meta[] = sprintf( 'By %s', $author );
					}

					// Details link using API info, if available
					if ( isset( $plugin_data['slug'] ) && current_user_can( 'install_plugins' ) ) {
						$plugin_meta[] = sprintf( '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
							esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data['slug'] .
								'&TB_iframe=true&width=600&height=550' ) ),
							esc_attr( sprintf( 'More information about %s', $plugin_name ) ),
							esc_attr( $plugin_name ),
							'View details'
						);
					} elseif ( ! empty( $plugin_data['PluginURI'] ) ) {
						$plugin_meta[] = sprintf( '<a href="%s">%s</a>',
							esc_url( $plugin_data['PluginURI'] ),
							'Visit plugin site'
						);
					}

					$plugin_meta = apply_filters( 'plugin_row_meta', $plugin_meta, $plugin_file, $plugin_data, $status );
					echo implode( ' | ', $plugin_meta );

					echo "</div></td>";
					break;
				default:
					$classes = "$column_name column-$column_name$class";

					echo "<td class='$classes{$extra_classes}'>";

					do_action( 'manage_plugins_custom_column', $column_name, $plugin_file, $plugin_data );

					echo "</td>";
			}
		}

		echo "</tr>";

		do_action( 'after_plugin_row', $plugin_file, $plugin_data, $status );

		do_action( "after_plugin_row_$plugin_file", $plugin_file, $plugin_data, $status );
	}

	protected function get_primary_column_name() {
		return 'name';
	}
}
