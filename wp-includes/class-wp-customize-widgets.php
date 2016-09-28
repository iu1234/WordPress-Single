<?php
/**
 * WordPress Customize Widgets classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.9.0
 */

final class WP_Customize_Widgets {

	public $manager;

	protected $core_widget_id_bases = array( 'categories',	'nav_menu', 'recent-posts', 'search', 'text' );

	protected $rendered_sidebars = array();

	protected $rendered_widgets = array();

	protected $old_sidebars_widgets = array();

	protected $selective_refreshable_widgets;

	protected $setting_id_patterns = array(
		'widget_instance' => '/^widget_(?P<id_base>.+?)(?:\[(?P<widget_number>\d+)\])?$/',
		'sidebar_widgets' => '/^sidebars_widgets\[(?P<sidebar_id>.+?)\]$/',
	);

	public function __construct( $manager ) {
		$this->manager = $manager;

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		add_filter( 'customize_dynamic_setting_args',          array( $this, 'filter_customize_dynamic_setting_args' ), 10, 2 );
		add_action( 'widgets_init',                            array( $this, 'register_settings' ), 95 );
		add_action( 'wp_loaded',                               array( $this, 'override_sidebars_widgets_for_theme_switch' ) );
		add_action( 'customize_controls_init',                 array( $this, 'customize_controls_init' ) );
		add_action( 'customize_register',                      array( $this, 'schedule_customize_register' ), 1 );
		add_action( 'customize_controls_enqueue_scripts',      array( $this, 'enqueue_scripts' ) );
		add_action( 'customize_controls_print_styles',         array( $this, 'print_styles' ) );
		add_action( 'customize_controls_print_scripts',        array( $this, 'print_scripts' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'print_footer_scripts' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'output_widget_control_templates' ) );
		add_action( 'customize_preview_init',                  array( $this, 'customize_preview_init' ) );
		add_filter( 'customize_refresh_nonces',                array( $this, 'refresh_nonces' ) );

		add_action( 'dynamic_sidebar',                         array( $this, 'tally_rendered_widgets' ) );
		add_filter( 'is_active_sidebar',                       array( $this, 'tally_sidebars_via_is_active_sidebar_calls' ), 10, 2 );
		add_filter( 'dynamic_sidebar_has_widgets',             array( $this, 'tally_sidebars_via_dynamic_sidebar_calls' ), 10, 2 );

		// Selective Refresh.
		add_filter( 'customize_dynamic_partial_args',          array( $this, 'customize_dynamic_partial_args' ), 10, 2 );
		add_action( 'customize_preview_init',                  array( $this, 'selective_refresh_init' ) );
	}

	public function get_selective_refreshable_widgets() {
		global $wp_widget_factory;
		if ( ! current_theme_supports( 'customize-selective-refresh-widgets' ) ) {
			return array();
		}
		if ( ! isset( $this->selective_refreshable_widgets ) ) {
			$this->selective_refreshable_widgets = array();
			foreach ( $wp_widget_factory->widgets as $wp_widget ) {
				$this->selective_refreshable_widgets[ $wp_widget->id_base ] = ! empty( $wp_widget->widget_options['customize_selective_refresh'] );
			}
		}
		return $this->selective_refreshable_widgets;
	}

	public function is_widget_selective_refreshable( $id_base ) {
		$selective_refreshable_widgets = $this->get_selective_refreshable_widgets();
		return ! empty( $selective_refreshable_widgets[ $id_base ] );
	}

	protected function get_setting_type( $setting_id ) {
		static $cache = array();
		if ( isset( $cache[ $setting_id ] ) ) {
			return $cache[ $setting_id ];
		}
		foreach ( $this->setting_id_patterns as $type => $pattern ) {
			if ( preg_match( $pattern, $setting_id ) ) {
				$cache[ $setting_id ] = $type;
				return $type;
			}
		}
	}

	public function register_settings() {
		$widget_setting_ids = array();
		$incoming_setting_ids = array_keys( $this->manager->unsanitized_post_values() );
		foreach ( $incoming_setting_ids as $setting_id ) {
			if ( ! is_null( $this->get_setting_type( $setting_id ) ) ) {
				$widget_setting_ids[] = $setting_id;
			}
		}
		if ( $this->manager->doing_ajax( 'update-widget' ) && isset( $_REQUEST['widget-id'] ) ) {
			$widget_setting_ids[] = $this->get_setting_id( wp_unslash( $_REQUEST['widget-id'] ) );
		}

		$settings = $this->manager->add_dynamic_settings( array_unique( $widget_setting_ids ) );

		if ( ! $this->manager->doing_ajax( 'customize_save' ) ) {
			foreach ( $settings as $setting ) {
				$setting->preview();
			}
		}
	}

	public function filter_customize_dynamic_setting_args( $args, $setting_id ) {
		if ( $this->get_setting_type( $setting_id ) ) {
			$args = $this->get_setting_args( $setting_id );
		}
		return $args;
	}

	protected function get_post_value( $name, $default = null ) {
		if ( ! isset( $_POST[ $name ] ) ) {
			return $default;
		}

		return wp_unslash( $_POST[ $name ] );
	}

	public function override_sidebars_widgets_for_theme_switch() {
		global $sidebars_widgets;

		if ( $this->manager->doing_ajax() || $this->manager->is_theme_active() ) {
			return;
		}

		$this->old_sidebars_widgets = wp_get_sidebars_widgets();
		add_filter( 'customize_value_old_sidebars_widgets_data', array( $this, 'filter_customize_value_old_sidebars_widgets_data' ) );

		// retrieve_widgets() looks at the global $sidebars_widgets
		$sidebars_widgets = $this->old_sidebars_widgets;
		$sidebars_widgets = retrieve_widgets( 'customize' );
		add_filter( 'option_sidebars_widgets', array( $this, 'filter_option_sidebars_widgets_for_theme_switch' ), 1 );
		// reset global cache var used by wp_get_sidebars_widgets()
		unset( $GLOBALS['_wp_sidebars_widgets'] );
	}

	public function filter_customize_value_old_sidebars_widgets_data( $old_sidebars_widgets ) {
		return $this->old_sidebars_widgets;
	}

	public function filter_option_sidebars_widgets_for_theme_switch( $sidebars_widgets ) {
		$sidebars_widgets = $GLOBALS['sidebars_widgets'];
		$sidebars_widgets['array_version'] = 3;
		return $sidebars_widgets;
	}

	public function customize_controls_init() {
		do_action( 'load-widgets.php' );
		do_action( 'widgets.php' );
		do_action( 'sidebar_admin_setup' );
	}

	public function schedule_customize_register() {
		if ( is_admin() ) {
			$this->customize_register();
		} else {
			add_action( 'wp', array( $this, 'customize_register' ) );
		}
	}

	public function customize_register() {
		global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_sidebars;

		add_filter( 'sidebars_widgets', array( $this, 'preview_sidebars_widgets' ), 1 );

		$sidebars_widgets = array_merge(
			array( 'wp_inactive_widgets' => array() ),
			array_fill_keys( array_keys( $wp_registered_sidebars ), array() ),
			wp_get_sidebars_widgets()
		);

		$new_setting_ids = array();

		foreach ( array_keys( $wp_registered_widgets ) as $widget_id ) {
			$setting_id   = $this->get_setting_id( $widget_id );
			$setting_args = $this->get_setting_args( $setting_id );
			if ( ! $this->manager->get_setting( $setting_id ) ) {
				$this->manager->add_setting( $setting_id, $setting_args );
			}
			$new_setting_ids[] = $setting_id;
		}

		if ( ! $this->manager->is_theme_active() ) {
			$setting_id = 'old_sidebars_widgets_data';
			$setting_args = $this->get_setting_args( $setting_id, array(
				'type' => 'global_variable',
				'dirty' => true,
			) );
			$this->manager->add_setting( $setting_id, $setting_args );
		}

		$this->manager->add_panel( 'widgets', array(
			'type'            => 'widgets',
			'title'           => 'Widgets',
			'description'     => __( 'Widgets are independent sections of content that can be placed into widgetized areas provided by your theme (commonly called sidebars).' ),
			'priority'        => 110,
			'active_callback' => array( $this, 'is_panel_active' ),
		) );

		foreach ( $sidebars_widgets as $sidebar_id => $sidebar_widget_ids ) {
			if ( empty( $sidebar_widget_ids ) ) {
				$sidebar_widget_ids = array();
			}

			$is_registered_sidebar = is_registered_sidebar( $sidebar_id );
			$is_inactive_widgets   = ( 'wp_inactive_widgets' === $sidebar_id );
			$is_active_sidebar     = ( $is_registered_sidebar && ! $is_inactive_widgets );

			// Add setting for managing the sidebar's widgets.
			if ( $is_registered_sidebar || $is_inactive_widgets ) {
				$setting_id   = sprintf( 'sidebars_widgets[%s]', $sidebar_id );
				$setting_args = $this->get_setting_args( $setting_id );
				if ( ! $this->manager->get_setting( $setting_id ) ) {
					if ( ! $this->manager->is_theme_active() ) {
						$setting_args['dirty'] = true;
					}
					$this->manager->add_setting( $setting_id, $setting_args );
				}
				$new_setting_ids[] = $setting_id;

				// Add section to contain controls.
				$section_id = sprintf( 'sidebar-widgets-%s', $sidebar_id );
				if ( $is_active_sidebar ) {

					$section_args = array(
						'title' => $wp_registered_sidebars[ $sidebar_id ]['name'],
						'description' => $wp_registered_sidebars[ $sidebar_id ]['description'],
						'priority' => array_search( $sidebar_id, array_keys( $wp_registered_sidebars ) ),
						'panel' => 'widgets',
						'sidebar_id' => $sidebar_id,
					);

					$section_args = apply_filters( 'customizer_widgets_section_args', $section_args, $section_id, $sidebar_id );

					$section = new WP_Customize_Sidebar_Section( $this->manager, $section_id, $section_args );
					$this->manager->add_section( $section );

					$control = new WP_Widget_Area_Customize_Control( $this->manager, $setting_id, array(
						'section'    => $section_id,
						'sidebar_id' => $sidebar_id,
						'priority'   => count( $sidebar_widget_ids ), // place 'Add Widget' and 'Reorder' buttons at end.
					) );
					$new_setting_ids[] = $setting_id;

					$this->manager->add_control( $control );
				}
			}

			foreach ( $sidebar_widget_ids as $i => $widget_id ) {

				// Skip widgets that may have gone away due to a plugin being deactivated.
				if ( ! $is_active_sidebar || ! isset( $wp_registered_widgets[$widget_id] ) ) {
					continue;
				}

				$registered_widget = $wp_registered_widgets[$widget_id];
				$setting_id        = $this->get_setting_id( $widget_id );
				$id_base           = $wp_registered_widget_controls[$widget_id]['id_base'];

				$control = new WP_Widget_Form_Customize_Control( $this->manager, $setting_id, array(
					'label'          => $registered_widget['name'],
					'section'        => $section_id,
					'sidebar_id'     => $sidebar_id,
					'widget_id'      => $widget_id,
					'widget_id_base' => $id_base,
					'priority'       => $i,
					'width'          => $wp_registered_widget_controls[$widget_id]['width'],
					'height'         => $wp_registered_widget_controls[$widget_id]['height'],
					'is_wide'        => $this->is_wide_widget( $widget_id ),
				) );
				$this->manager->add_control( $control );
			}
		}

		if ( ! $this->manager->doing_ajax( 'customize_save' ) ) {
			foreach ( $new_setting_ids as $new_setting_id ) {
				$this->manager->get_setting( $new_setting_id )->preview();
			}
		}
	}

	public function is_panel_active() {
		global $wp_registered_sidebars;
		return ! empty( $wp_registered_sidebars );
	}

	public function get_setting_id( $widget_id ) {
		$parsed_widget_id = $this->parse_widget_id( $widget_id );
		$setting_id       = sprintf( 'widget_%s', $parsed_widget_id['id_base'] );

		if ( ! is_null( $parsed_widget_id['number'] ) ) {
			$setting_id .= sprintf( '[%d]', $parsed_widget_id['number'] );
		}
		return $setting_id;
	}

	public function is_wide_widget( $widget_id ) {
		global $wp_registered_widget_controls;

		$parsed_widget_id = $this->parse_widget_id( $widget_id );
		$width            = $wp_registered_widget_controls[$widget_id]['width'];
		$is_core          = in_array( $parsed_widget_id['id_base'], $this->core_widget_id_bases );
		$is_wide          = ( $width > 250 && ! $is_core );

		return apply_filters( 'is_wide_widget_in_customizer', $is_wide, $widget_id );
	}

	public function parse_widget_id( $widget_id ) {
		$parsed = array(
			'number' => null,
			'id_base' => null,
		);

		if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $matches ) ) {
			$parsed['id_base'] = $matches[1];
			$parsed['number']  = intval( $matches[2] );
		} else {
			// likely an old single widget
			$parsed['id_base'] = $widget_id;
		}
		return $parsed;
	}

	public function parse_widget_setting_id( $setting_id ) {
		if ( ! preg_match( '/^(widget_(.+?))(?:\[(\d+)\])?$/', $setting_id, $matches ) ) {
			return new WP_Error( 'widget_setting_invalid_id' );
		}

		$id_base = $matches[2];
		$number  = isset( $matches[3] ) ? intval( $matches[3] ) : null;

		return compact( 'id_base', 'number' );
	}

	public function print_styles() {
		do_action( 'admin_print_styles-widgets.php' );
		do_action( 'admin_print_styles' );
	}

	public function print_scripts() {
		do_action( 'admin_print_scripts-widgets.php' );
		do_action( 'admin_print_scripts' );
	}

	public function enqueue_scripts() {
		global $wp_scripts, $wp_registered_sidebars, $wp_registered_widgets;

		wp_enqueue_style( 'customize-widgets' );
		wp_enqueue_script( 'customize-widgets' );
		do_action( 'admin_enqueue_scripts', 'widgets.php' );
		$available_widgets = array();

		foreach ( $this->get_available_widgets() as $available_widget ) {
			unset( $available_widget['control_tpl'] );
			$available_widgets[] = $available_widget;
		}

		$widget_reorder_nav_tpl = sprintf(
			'<div class="widget-reorder-nav"><span class="move-widget" tabindex="0">%1$s</span><span class="move-widget-down" tabindex="0">%2$s</span><span class="move-widget-up" tabindex="0">%3$s</span></div>',
			'Move to another area&hellip;',
			'Move down',
			'Move up'
		);

		$move_widget_area_tpl = str_replace(
			array( '{description}', '{btn}' ),
			array(
				'Select an area to move this widget into:',
				'Move',
			),
			'<div class="move-widget-area">
				<p class="description">{description}</p>
				<ul class="widget-area-select">
					<% _.each( sidebars, function ( sidebar ){ %>
						<li class="" data-id="<%- sidebar.id %>" title="<%- sidebar.description %>" tabindex="0"><%- sidebar.name %></li>
					<% }); %>
				</ul>
				<div class="move-widget-actions">
					<button class="move-widget-btn button-secondary" type="button">{btn}</button>
				</div>
			</div>'
		);

		$settings = array(
			'registeredSidebars'   => array_values( $wp_registered_sidebars ),
			'registeredWidgets'    => $wp_registered_widgets,
			'availableWidgets'     => $available_widgets, // @todo Merge this with registered_widgets
			'l10n' => array(
				'saveBtnLabel'     => __( 'Apply' ),
				'saveBtnTooltip'   => __( 'Save and preview changes before publishing them.' ),
				'removeBtnLabel'   => __( 'Remove' ),
				'removeBtnTooltip' => __( 'Trash widget by moving it to the inactive widgets sidebar.' ),
				'error'            => __( 'An error has occurred. Please reload the page and try again.' ),
				'widgetMovedUp'    => __( 'Widget moved up' ),
				'widgetMovedDown'  => __( 'Widget moved down' ),
				'noAreasRendered'  => __( 'There are no widget areas currently rendered in the preview. Navigate in the preview to a template that makes use of a widget area in order to access its widgets here.' ),
				'reorderModeOn'    => __( 'Reorder mode enabled' ),
				'reorderModeOff'   => __( 'Reorder mode closed' ),
				'reorderLabelOn'   => esc_attr__( 'Reorder widgets' ),
				'reorderLabelOff'  => esc_attr__( 'Close reorder mode' ),
			),
			'tpl' => array(
				'widgetReorderNav' => $widget_reorder_nav_tpl,
				'moveWidgetArea'   => $move_widget_area_tpl,
			),
			'selectiveRefreshableWidgets' => $this->get_selective_refreshable_widgets(),
		);

		foreach ( $settings['registeredWidgets'] as &$registered_widget ) {
			unset( $registered_widget['callback'] ); // may not be JSON-serializeable
		}

		$wp_scripts->add_data(
			'customize-widgets',
			'data',
			sprintf( 'var _wpCustomizeWidgetsSettings = %s;', wp_json_encode( $settings ) )
		);
	}

	public function output_widget_control_templates() {
		?>
		<div id="widgets-left"><!-- compatibility with JS which looks for widget templates here -->
		<div id="available-widgets">
			<div class="customize-section-title">
				<button class="customize-section-back" tabindex="-1">
					<span class="screen-reader-text">Back</span>
				</button>
				<h3>
					<span class="customize-action"><?php
						/* translators: &#9656; is the unicode right-pointing triangle, and %s is the section title in the Customizer */
						echo sprintf( 'Customizing &#9656; %s', esc_html( $this->manager->get_panel( 'widgets' )->title ) );
					?></span>
					Add a Widget
				</h3>
			</div>
			<div id="available-widgets-filter">
				<label class="screen-reader-text" for="widgets-search">Search Widgets</label>
				<input type="search" id="widgets-search" placeholder="<?php esc_attr_e( 'Search widgets&hellip;' ) ?>" />
			</div>
			<div id="available-widgets-list">
			<?php foreach ( $this->get_available_widgets() as $available_widget ): ?>
				<div id="widget-tpl-<?php echo esc_attr( $available_widget['id'] ) ?>" data-widget-id="<?php echo esc_attr( $available_widget['id'] ) ?>" class="widget-tpl <?php echo esc_attr( $available_widget['id'] ) ?>" tabindex="0">
					<?php echo $available_widget['control_tpl']; ?>
				</div>
			<?php endforeach; ?>
			</div><!-- #available-widgets-list -->
		</div><!-- #available-widgets -->
		</div><!-- #widgets-left -->
		<?php
	}

	public function print_footer_scripts() {
		do_action( 'admin_print_footer_scripts' );
		do_action( 'admin_footer-widgets.php' );
	}

	public function get_setting_args( $id, $overrides = array() ) {
		$args = array(
			'type'       => 'option',
			'capability' => 'edit_theme_options',
			'default'    => array(),
		);

		if ( preg_match( $this->setting_id_patterns['sidebar_widgets'], $id, $matches ) ) {
			$args['sanitize_callback'] = array( $this, 'sanitize_sidebar_widgets' );
			$args['sanitize_js_callback'] = array( $this, 'sanitize_sidebar_widgets_js_instance' );
			$args['transport'] = current_theme_supports( 'customize-selective-refresh-widgets' ) ? 'postMessage' : 'refresh';
		} elseif ( preg_match( $this->setting_id_patterns['widget_instance'], $id, $matches ) ) {
			$args['sanitize_callback'] = array( $this, 'sanitize_widget_instance' );
			$args['sanitize_js_callback'] = array( $this, 'sanitize_widget_js_instance' );
			$args['transport'] = $this->is_widget_selective_refreshable( $matches['id_base'] ) ? 'postMessage' : 'refresh';
		}

		$args = array_merge( $args, $overrides );

		return apply_filters( 'widget_customizer_setting_args', $args, $id );
	}

	public function sanitize_sidebar_widgets( $widget_ids ) {
		$widget_ids = array_map( 'strval', (array) $widget_ids );
		$sanitized_widget_ids = array();
		foreach ( $widget_ids as $widget_id ) {
			$sanitized_widget_ids[] = preg_replace( '/[^a-z0-9_\-]/', '', $widget_id );
		}
		return $sanitized_widget_ids;
	}

	public function get_available_widgets() {
		static $available_widgets = array();
		if ( ! empty( $available_widgets ) ) {
			return $available_widgets;
		}

		global $wp_registered_widgets, $wp_registered_widget_controls;
		require_once ABSPATH . '/wp-admin/includes/widgets.php';

		$sort = $wp_registered_widgets;
		usort( $sort, array( $this, '_sort_name_callback' ) );
		$done = array();

		foreach ( $sort as $widget ) {
			if ( in_array( $widget['callback'], $done, true ) ) {
				continue;
			}

			$sidebar = is_active_widget( $widget['callback'], $widget['id'], false, false );
			$done[]  = $widget['callback'];

			if ( ! isset( $widget['params'][0] ) ) {
				$widget['params'][0] = array();
			}

			$available_widget = $widget;
			unset( $available_widget['callback'] ); // not serializable to JSON

			$args = array(
				'widget_id'   => $widget['id'],
				'widget_name' => $widget['name'],
				'_display'    => 'template',
			);

			$is_disabled     = false;
			$is_multi_widget = ( isset( $wp_registered_widget_controls[$widget['id']]['id_base'] ) && isset( $widget['params'][0]['number'] ) );
			if ( $is_multi_widget ) {
				$id_base            = $wp_registered_widget_controls[$widget['id']]['id_base'];
				$args['_temp_id']   = "$id_base-__i__";
				$args['_multi_num'] = next_widget_id_number( $id_base );
				$args['_add']       = 'multi';
			} else {
				$args['_add'] = 'single';

				if ( $sidebar && 'wp_inactive_widgets' !== $sidebar ) {
					$is_disabled = true;
				}
				$id_base = $widget['id'];
			}

			$list_widget_controls_args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
			$control_tpl = $this->get_widget_control( $list_widget_controls_args );

			// The properties here are mapped to the Backbone Widget model.
			$available_widget = array_merge( $available_widget, array(
				'temp_id'      => isset( $args['_temp_id'] ) ? $args['_temp_id'] : null,
				'is_multi'     => $is_multi_widget,
				'control_tpl'  => $control_tpl,
				'multi_number' => ( $args['_add'] === 'multi' ) ? $args['_multi_num'] : false,
				'is_disabled'  => $is_disabled,
				'id_base'      => $id_base,
				'transport'    => $this->is_widget_selective_refreshable( $id_base ) ? 'postMessage' : 'refresh',
				'width'        => $wp_registered_widget_controls[$widget['id']]['width'],
				'height'       => $wp_registered_widget_controls[$widget['id']]['height'],
				'is_wide'      => $this->is_wide_widget( $widget['id'] ),
			) );

			$available_widgets[] = $available_widget;
		}

		return $available_widgets;
	}

	protected function _sort_name_callback( $widget_a, $widget_b ) {
		return strnatcasecmp( $widget_a['name'], $widget_b['name'] );
	}

	public function get_widget_control( $args ) {
		$args[0]['before_form'] = '<div class="form">';
		$args[0]['after_form'] = '</div><!-- .form -->';
		$args[0]['before_widget_content'] = '<div class="widget-content">';
		$args[0]['after_widget_content'] = '</div><!-- .widget-content -->';
		ob_start();
		call_user_func_array( 'wp_widget_control', $args );
		$control_tpl = ob_get_clean();
		return $control_tpl;
	}

	public function get_widget_control_parts( $args ) {
		$args[0]['before_widget_content'] = '<div class="widget-content">';
		$args[0]['after_widget_content'] = '</div><!-- .widget-content -->';
		$control_markup = $this->get_widget_control( $args );

		$content_start_pos = strpos( $control_markup, $args[0]['before_widget_content'] );
		$content_end_pos = strrpos( $control_markup, $args[0]['after_widget_content'] );

		$control = substr( $control_markup, 0, $content_start_pos + strlen( $args[0]['before_widget_content'] ) );
		$control .= substr( $control_markup, $content_end_pos );
		$content = trim( substr(
			$control_markup,
			$content_start_pos + strlen( $args[0]['before_widget_content'] ),
			$content_end_pos - $content_start_pos - strlen( $args[0]['before_widget_content'] )
		) );

		return compact( 'control', 'content' );
	}

	public function customize_preview_init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'customize_preview_enqueue' ) );
		add_action( 'wp_print_styles',    array( $this, 'print_preview_css' ), 1 );
		add_action( 'wp_footer',          array( $this, 'export_preview_data' ), 20 );
	}

	public function refresh_nonces( $nonces ) {
		$nonces['update-widget'] = wp_create_nonce( 'update-widget' );
		return $nonces;
	}

	public function preview_sidebars_widgets( $sidebars_widgets ) {
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );

		unset( $sidebars_widgets['array_version'] );
		return $sidebars_widgets;
	}

	public function customize_preview_enqueue() {
		wp_enqueue_script( 'customize-preview-widgets' );
		wp_enqueue_style( 'customize-preview' );
	}

	public function print_preview_css() {
		?>
		<style>
		.widget-customizer-highlighted-widget {
			outline: none;
			-webkit-box-shadow: 0 0 2px rgba(30,140,190,0.8);
			box-shadow: 0 0 2px rgba(30,140,190,0.8);
			position: relative;
			z-index: 1;
		}
		</style>
		<?php
	}

	public function export_preview_data() {
		global $wp_registered_sidebars, $wp_registered_widgets;
		$settings = array(
			'renderedSidebars'   => array_fill_keys( array_unique( $this->rendered_sidebars ), true ),
			'renderedWidgets'    => array_fill_keys( array_keys( $this->rendered_widgets ), true ),
			'registeredSidebars' => array_values( $wp_registered_sidebars ),
			'registeredWidgets'  => $wp_registered_widgets,
			'l10n'               => array(
				'widgetTooltip'  => 'Shift-click to edit this widget.',
			),
			'selectiveRefreshableWidgets' => $this->get_selective_refreshable_widgets(),
		);
		foreach ( $settings['registeredWidgets'] as &$registered_widget ) {
			unset( $registered_widget['callback'] ); // may not be JSON-serializeable
		}

		?>
		<script type="text/javascript">
			var _wpWidgetCustomizerPreviewSettings = <?php echo wp_json_encode( $settings ); ?>;
		</script>
		<?php
	}

	public function tally_rendered_widgets( $widget ) {
		$this->rendered_widgets[ $widget['id'] ] = true;
	}

	public function is_widget_rendered( $widget_id ) {
		return in_array( $widget_id, $this->rendered_widgets );
	}

	public function is_sidebar_rendered( $sidebar_id ) {
		return in_array( $sidebar_id, $this->rendered_sidebars );
	}

	public function tally_sidebars_via_is_active_sidebar_calls( $is_active, $sidebar_id ) {
		if ( is_registered_sidebar( $sidebar_id ) ) {
			$this->rendered_sidebars[] = $sidebar_id;
		}
		return $is_active;
	}

	public function tally_sidebars_via_dynamic_sidebar_calls( $has_widgets, $sidebar_id ) {
		if ( is_registered_sidebar( $sidebar_id ) ) {
			$this->rendered_sidebars[] = $sidebar_id;
		}

		return $has_widgets;
	}

	protected function get_instance_hash_key( $serialized_instance ) {
		return wp_hash( $serialized_instance );
	}

	public function sanitize_widget_instance( $value ) {
		if ( $value === array() ) {
			return $value;
		}

		if ( empty( $value['is_widget_customizer_js_value'] )
			|| empty( $value['instance_hash_key'] )
			|| empty( $value['encoded_serialized_instance'] ) )
		{
			return;
		}

		$decoded = base64_decode( $value['encoded_serialized_instance'], true );
		if ( false === $decoded ) {
			return;
		}

		if ( ! hash_equals( $this->get_instance_hash_key( $decoded ), $value['instance_hash_key'] ) ) {
			return;
		}

		$instance = unserialize( $decoded );
		if ( false === $instance ) {
			return;
		}

		return $instance;
	}

	public function sanitize_widget_js_instance( $value ) {
		if ( empty( $value['is_widget_customizer_js_value'] ) ) {
			$serialized = serialize( $value );

			$value = array(
				'encoded_serialized_instance'   => base64_encode( $serialized ),
				'title'                         => empty( $value['title'] ) ? '' : $value['title'],
				'is_widget_customizer_js_value' => true,
				'instance_hash_key'             => $this->get_instance_hash_key( $serialized ),
			);
		}
		return $value;
	}

	public function sanitize_sidebar_widgets_js_instance( $widget_ids ) {
		global $wp_registered_widgets;
		$widget_ids = array_values( array_intersect( $widget_ids, array_keys( $wp_registered_widgets ) ) );
		return $widget_ids;
	}

	public function call_widget_update( $widget_id ) {
		global $wp_registered_widget_updates, $wp_registered_widget_controls;

		$setting_id = $this->get_setting_id( $widget_id );

		if ( ! did_action( 'customize_preview_init' ) ) {
			foreach ( $this->manager->settings() as $setting ) {
				if ( $setting->id !== $setting_id ) {
					$setting->preview();
				}
			}
		}

		$this->start_capturing_option_updates();
		$parsed_id   = $this->parse_widget_id( $widget_id );
		$option_name = 'widget_' . $parsed_id['id_base'];

		$added_input_vars = array();
		if ( ! empty( $_POST['sanitized_widget_setting'] ) ) {
			$sanitized_widget_setting = json_decode( $this->get_post_value( 'sanitized_widget_setting' ), true );
			if ( false === $sanitized_widget_setting ) {
				$this->stop_capturing_option_updates();
				return new WP_Error( 'widget_setting_malformed' );
			}

			$instance = $this->sanitize_widget_instance( $sanitized_widget_setting );
			if ( is_null( $instance ) ) {
				$this->stop_capturing_option_updates();
				return new WP_Error( 'widget_setting_unsanitized' );
			}

			if ( ! is_null( $parsed_id['number'] ) ) {
				$value = array();
				$value[$parsed_id['number']] = $instance;
				$key = 'widget-' . $parsed_id['id_base'];
				$_REQUEST[$key] = $_POST[$key] = wp_slash( $value );
				$added_input_vars[] = $key;
			} else {
				foreach ( $instance as $key => $value ) {
					$_REQUEST[$key] = $_POST[$key] = wp_slash( $value );
					$added_input_vars[] = $key;
				}
			}
		}

		// Invoke the widget update callback.
		foreach ( (array) $wp_registered_widget_updates as $name => $control ) {
			if ( $name === $parsed_id['id_base'] && is_callable( $control['callback'] ) ) {
				ob_start();
				call_user_func_array( $control['callback'], $control['params'] );
				ob_end_clean();
				break;
			}
		}

		// Clean up any input vars that were manually added
		foreach ( $added_input_vars as $key ) {
			unset( $_POST[ $key ] );
			unset( $_REQUEST[ $key ] );
		}

		// Make sure the expected option was updated.
		if ( 0 !== $this->count_captured_options() ) {
			if ( $this->count_captured_options() > 1 ) {
				$this->stop_capturing_option_updates();
				return new WP_Error( 'widget_setting_too_many_options' );
			}

			$updated_option_name = key( $this->get_captured_options() );
			if ( $updated_option_name !== $option_name ) {
				$this->stop_capturing_option_updates();
				return new WP_Error( 'widget_setting_unexpected_option' );
			}
		}

		// Obtain the widget instance.
		$option = $this->get_captured_option( $option_name );
		if ( null !== $parsed_id['number'] ) {
			$instance = $option[ $parsed_id['number'] ];
		} else {
			$instance = $option;
		}

		$this->manager->set_post_value( $setting_id, $this->sanitize_widget_js_instance( $instance ) );

		// Obtain the widget control with the updated instance in place.
		ob_start();
		$form = $wp_registered_widget_controls[ $widget_id ];
		if ( $form ) {
			call_user_func_array( $form['callback'], $form['params'] );
		}
		$form = ob_get_clean();

		$this->stop_capturing_option_updates();

		return compact( 'instance', 'form' );
	}

	public function wp_ajax_update_widget() {

		if ( ! is_user_logged_in() ) {
			wp_die( 0 );
		}

		check_ajax_referer( 'update-widget', 'nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		if ( empty( $_POST['widget-id'] ) ) {
			wp_send_json_error( 'missing_widget-id' );
		}

		/** This action is documented in wp-admin/includes/ajax-actions.php */
		do_action( 'load-widgets.php' );

		/** This action is documented in wp-admin/includes/ajax-actions.php */
		do_action( 'widgets.php' );

		/** This action is documented in wp-admin/widgets.php */
		do_action( 'sidebar_admin_setup' );

		$widget_id = $this->get_post_value( 'widget-id' );
		$parsed_id = $this->parse_widget_id( $widget_id );
		$id_base = $parsed_id['id_base'];

		$is_updating_widget_template = (
			isset( $_POST[ 'widget-' . $id_base ] )
			&&
			is_array( $_POST[ 'widget-' . $id_base ] )
			&&
			preg_match( '/__i__|%i%/', key( $_POST[ 'widget-' . $id_base ] ) )
		);
		if ( $is_updating_widget_template ) {
			wp_send_json_error( 'template_widget_not_updatable' );
		}

		$updated_widget = $this->call_widget_update( $widget_id ); // => {instance,form}
		if ( is_wp_error( $updated_widget ) ) {
			wp_send_json_error( $updated_widget->get_error_code() );
		}

		$form = $updated_widget['form'];
		$instance = $this->sanitize_widget_js_instance( $updated_widget['instance'] );

		wp_send_json_success( compact( 'form', 'instance' ) );
	}

	public function customize_dynamic_partial_args( $partial_args, $partial_id ) {
		if ( ! current_theme_supports( 'customize-selective-refresh-widgets' ) ) {
			return $partial_args;
		}

		if ( preg_match( '/^widget\[(?P<widget_id>.+)\]$/', $partial_id, $matches ) ) {
			if ( false === $partial_args ) {
				$partial_args = array();
			}
			$partial_args = array_merge(
				$partial_args,
				array(
					'type'                => 'widget',
					'render_callback'     => array( $this, 'render_widget_partial' ),
					'container_inclusive' => true,
					'settings'            => array( $this->get_setting_id( $matches['widget_id'] ) ),
					'capability'          => 'edit_theme_options',
				)
			);
		}

		return $partial_args;
	}

	public function selective_refresh_init() {
		if ( ! current_theme_supports( 'customize-selective-refresh-widgets' ) ) {
			return;
		}
		add_filter( 'dynamic_sidebar_params', array( $this, 'filter_dynamic_sidebar_params' ) );
		add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_allowed_data_attributes' ) );
		add_action( 'dynamic_sidebar_before', array( $this, 'start_dynamic_sidebar' ) );
		add_action( 'dynamic_sidebar_after', array( $this, 'end_dynamic_sidebar' ) );
	}

	public function filter_dynamic_sidebar_params( $params ) {
		$sidebar_args = array_merge(
			array(
				'before_widget' => '',
				'after_widget' => '',
			),
			$params[0]
		);

		$matches = array();
		$is_valid = (
			isset( $sidebar_args['id'] )
			&&
			is_registered_sidebar( $sidebar_args['id'] )
			&&
			( isset( $this->current_dynamic_sidebar_id_stack[0] ) && $this->current_dynamic_sidebar_id_stack[0] === $sidebar_args['id'] )
			&&
			preg_match( '#^<(?P<tag_name>\w+)#', $sidebar_args['before_widget'], $matches )
		);
		if ( ! $is_valid ) {
			return $params;
		}
		$this->before_widget_tags_seen[ $matches['tag_name'] ] = true;

		$context = array(
			'sidebar_id' => $sidebar_args['id'],
		);
		if ( isset( $this->context_sidebar_instance_number ) ) {
			$context['sidebar_instance_number'] = $this->context_sidebar_instance_number;
		} else if ( isset( $sidebar_args['id'] ) && isset( $this->sidebar_instance_count[ $sidebar_args['id'] ] ) ) {
			$context['sidebar_instance_number'] = $this->sidebar_instance_count[ $sidebar_args['id'] ];
		}

		$attributes = sprintf( ' data-customize-partial-id="%s"', esc_attr( 'widget[' . $sidebar_args['widget_id'] . ']' ) );
		$attributes .= ' data-customize-partial-type="widget"';
		$attributes .= sprintf( ' data-customize-partial-placement-context="%s"', esc_attr( wp_json_encode( $context ) ) );
		$attributes .= sprintf( ' data-customize-widget-id="%s"', esc_attr( $sidebar_args['widget_id'] ) );
		$sidebar_args['before_widget'] = preg_replace( '#^(<\w+)#', '$1 ' . $attributes, $sidebar_args['before_widget'] );

		$params[0] = $sidebar_args;
		return $params;
	}

	protected $before_widget_tags_seen = array();

	public function filter_wp_kses_allowed_data_attributes( $allowed_html ) {
		foreach ( array_keys( $this->before_widget_tags_seen ) as $tag_name ) {
			if ( ! isset( $allowed_html[ $tag_name ] ) ) {
				$allowed_html[ $tag_name ] = array();
			}
			$allowed_html[ $tag_name ] = array_merge(
				$allowed_html[ $tag_name ],
				array_fill_keys( array(
					'data-customize-partial-id',
					'data-customize-partial-type',
					'data-customize-partial-placement-context',
					'data-customize-partial-widget-id',
					'data-customize-partial-options',
				), true )
			);
		}
		return $allowed_html;
	}

	protected $sidebar_instance_count = array();

	protected $context_sidebar_instance_number;

	protected $current_dynamic_sidebar_id_stack = array();

	public function start_dynamic_sidebar( $index ) {
		array_unshift( $this->current_dynamic_sidebar_id_stack, $index );
		if ( ! isset( $this->sidebar_instance_count[ $index ] ) ) {
			$this->sidebar_instance_count[ $index ] = 0;
		}
		$this->sidebar_instance_count[ $index ] += 1;
		if ( ! $this->manager->selective_refresh->is_render_partials_request() ) {
			printf( "\n<!--dynamic_sidebar_before:%s:%d-->\n", esc_html( $index ), intval( $this->sidebar_instance_count[ $index ] ) );
		}
	}

	public function end_dynamic_sidebar( $index ) {
		array_shift( $this->current_dynamic_sidebar_id_stack );
		if ( ! $this->manager->selective_refresh->is_render_partials_request() ) {
			printf( "\n<!--dynamic_sidebar_after:%s:%d-->\n", esc_html( $index ), intval( $this->sidebar_instance_count[ $index ] ) );
		}
	}

	protected $rendering_widget_id;

	protected $rendering_sidebar_id;

	public function filter_sidebars_widgets_for_rendering_widget( $sidebars_widgets ) {
		$sidebars_widgets[ $this->rendering_sidebar_id ] = array( $this->rendering_widget_id );
		return $sidebars_widgets;
	}

	public function render_widget_partial( $partial, $context ) {
		$id_data   = $partial->id_data();
		$widget_id = array_shift( $id_data['keys'] );

		if ( ! is_array( $context )
			|| empty( $context['sidebar_id'] )
			|| ! is_registered_sidebar( $context['sidebar_id'] )
		) {
			return false;
		}

		$this->rendering_sidebar_id = $context['sidebar_id'];

		if ( isset( $context['sidebar_instance_number'] ) ) {
			$this->context_sidebar_instance_number = intval( $context['sidebar_instance_number'] );
		}

		// Filter sidebars_widgets so that only the queried widget is in the sidebar.
		$this->rendering_widget_id = $widget_id;

		$filter_callback = array( $this, 'filter_sidebars_widgets_for_rendering_widget' );
		add_filter( 'sidebars_widgets', $filter_callback, 1000 );

		// Render the widget.
		ob_start();
		dynamic_sidebar( $this->rendering_sidebar_id = $context['sidebar_id'] );
		$container = ob_get_clean();

		// Reset variables for next partial render.
		remove_filter( 'sidebars_widgets', $filter_callback, 1000 );

		$this->context_sidebar_instance_number = null;
		$this->rendering_sidebar_id = null;
		$this->rendering_widget_id = null;

		return $container;
	}

	protected $_captured_options = array();

	protected $_is_capturing_option_updates = false;

	protected function is_option_capture_ignored( $option_name ) {
		return ( 0 === strpos( $option_name, '_transient_' ) );
	}

	protected function get_captured_options() {
		return $this->_captured_options;
	}

	protected function get_captured_option( $option_name, $default = false ) {
		if ( array_key_exists( $option_name, $this->_captured_options ) ) {
			$value = $this->_captured_options[ $option_name ];
		} else {
			$value = $default;
		}
		return $value;
	}

	protected function count_captured_options() {
		return count( $this->_captured_options );
	}

	protected function start_capturing_option_updates() {
		if ( $this->_is_capturing_option_updates ) {
			return;
		}

		$this->_is_capturing_option_updates = true;

		add_filter( 'pre_update_option', array( $this, 'capture_filter_pre_update_option' ), 10, 3 );
	}

	public function capture_filter_pre_update_option( $new_value, $option_name, $old_value ) {
		if ( $this->is_option_capture_ignored( $option_name ) ) {
			return;
		}

		if ( ! isset( $this->_captured_options[ $option_name ] ) ) {
			add_filter( "pre_option_{$option_name}", array( $this, 'capture_filter_pre_get_option' ) );
		}

		$this->_captured_options[ $option_name ] = $new_value;

		return $old_value;
	}

	public function capture_filter_pre_get_option( $value ) {
		$option_name = preg_replace( '/^pre_option_/', '', current_filter() );

		if ( isset( $this->_captured_options[ $option_name ] ) ) {
			$value = $this->_captured_options[ $option_name ];

			/** This filter is documented in wp-includes/option.php */
			$value = apply_filters( 'option_' . $option_name, $value );
		}

		return $value;
	}

	protected function stop_capturing_option_updates() {
		if ( ! $this->_is_capturing_option_updates ) {
			return;
		}

		remove_filter( 'pre_update_option', array( $this, 'capture_filter_pre_update_option' ), 10 );

		foreach ( array_keys( $this->_captured_options ) as $option_name ) {
			remove_filter( "pre_option_{$option_name}", array( $this, 'capture_filter_pre_get_option' ) );
		}

		$this->_captured_options = array();
		$this->_is_capturing_option_updates = false;
	}

	public function setup_widget_addition_previews() {
		_deprecated_function( __METHOD__, '4.2.0' );
	}

	public function prepreview_added_sidebars_widgets() {
		_deprecated_function( __METHOD__, '4.2.0' );
	}

	public function prepreview_added_widget_instance() {
		_deprecated_function( __METHOD__, '4.2.0' );
	}

	public function remove_prepreview_filters() {
		_deprecated_function( __METHOD__, '4.2.0' );
	}
}
