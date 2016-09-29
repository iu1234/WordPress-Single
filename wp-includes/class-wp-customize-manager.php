<?php

final class WP_Customize_Manager {

	protected $theme;

	protected $original_stylesheet;

	protected $previewing = false;

	public $widgets;

	public $nav_menus;

	public $selective_refresh;

	protected $settings = array();

	protected $containers = array();

	protected $panels = array();

	protected $components = array( 'widgets', 'nav_menus' );

	protected $sections = array();

	protected $controls = array();

	protected $nonce_tick;

	protected $registered_panel_types = array();

	protected $registered_section_types = array();

	protected $registered_control_types = array();

	protected $preview_url;

	protected $return_url;

	protected $autofocus = array();

	private $_post_values;

	public function __construct() {
		require_once( ABSPATH . WPINC . '/class-wp-customize-setting.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-panel.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-section.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menus-panel.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-filter-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-setting.php' );

		$components = apply_filters( 'customize_loaded_components', $this->components, $this );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-selective-refresh.php' );
		$this->selective_refresh = new WP_Customize_Selective_Refresh( $this );

		if ( in_array( 'widgets', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-widgets.php' );
			$this->widgets = new WP_Customize_Widgets( $this );
		}

		if ( in_array( 'nav_menus', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-nav-menus.php' );
			$this->nav_menus = new WP_Customize_Nav_Menus( $this );
		}

		add_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );
		add_action( 'setup_theme', array( $this, 'setup_theme' ) );
		add_action( 'wp_loaded',   array( $this, 'wp_loaded' ) );
		add_action( 'wp_redirect_status', array( $this, 'wp_redirect_status' ), 1000 );
		add_action( 'wp_ajax_customize_save',           array( $this, 'save' ) );
		add_action( 'wp_ajax_customize_refresh_nonces', array( $this, 'refresh_nonces' ) );
		add_action( 'customize_register',                 array( $this, 'register_controls' ) );
		add_action( 'customize_register',                 array( $this, 'register_dynamic_settings' ), 11 );
		add_action( 'customize_controls_init',            array( $this, 'prepare_controls' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_control_scripts' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_panel_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_section_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_control_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_pane_settings' ), 1000 );
	}

	public function doing_ajax( $action = null ) {
		$doing_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		if ( ! $doing_ajax ) {
			return false;
		}

		if ( ! $action ) {
			return true;
		} else {
			return isset( $_REQUEST['action'] ) && wp_unslash( $_REQUEST['action'] ) === $action;
		}
	}

	protected function wp_die( $ajax_message, $message = null ) {
		if ( $this->doing_ajax() || isset( $_POST['customized'] ) ) {
			wp_die( $ajax_message );
		}

		if ( ! $message ) {
			$message = 'Cheatin&#8217; uh?';
		}

		wp_die( $message );
	}

	public function wp_die_handler() {
		if ( $this->doing_ajax() || isset( $_POST['customized'] ) ) {
			return '_ajax_wp_die_handler';
		}
		return '_default_wp_die_handler';
	}

	public function setup_theme() {
		send_origin_headers();
		$doing_ajax_or_is_customized = ( $this->doing_ajax() || isset( $_POST['customized'] ) );
		if ( is_admin() && ! $doing_ajax_or_is_customized ) {
			auth_redirect();
		} elseif ( $doing_ajax_or_is_customized && ! is_user_logged_in() ) {
			$this->wp_die( 0, 'You must be logged in to complete this action.' );
		}
		show_admin_bar( false );
		if ( ! current_user_can( 'customize' ) ) {
			$this->wp_die( -1, 'You are not allowed to customize the appearance of this site.' );
		}
		$this->original_stylesheet = get_stylesheet();
		$this->theme = wp_get_theme( isset( $_REQUEST['theme'] ) ? $_REQUEST['theme'] : null );
		if ( $this->is_theme_active() ) {
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		} else {
			if ( ! current_user_can( 'switch_themes' ) ) {
				$this->wp_die( -1, 'You are not allowed to edit theme options on this site.' );
			}
			if ( $this->theme()->errors() ) {
				$this->wp_die( -1, $this->theme()->errors()->get_error_message() );
			}
			if ( ! $this->theme()->is_allowed() ) {
				$this->wp_die( -1, 'The requested theme does not exist.' );
			}
		}
		$this->start_previewing_theme();
	}

	public function after_setup_theme() {
		$doing_ajax_or_is_customized = ( $this->doing_ajax() || isset( $_SERVER['customized'] ) );
		if ( ! $doing_ajax_or_is_customized && ! validate_current_theme() ) {
			wp_redirect( 'themes.php?broken=true' );
			exit;
		}
	}

	public function start_previewing_theme() {
		if ( $this->is_preview() ) { return; }
		$this->previewing = true;
		if ( ! $this->is_theme_active() ) {
			add_filter( 'template', array( $this, 'get_template' ) );
			add_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );
			add_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_template', array( $this, 'get_template' ) );
			add_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			add_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}
		do_action( 'start_previewing_theme', $this );
	}

	public function stop_previewing_theme() {
		if ( ! $this->is_preview() ) {
			return;
		}

		$this->previewing = false;

		if ( ! $this->is_theme_active() ) {
			remove_filter( 'template', array( $this, 'get_template' ) );
			remove_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			remove_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_template', array( $this, 'get_template' ) );

			remove_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			remove_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		do_action( 'stop_previewing_theme', $this );
	}

	public function theme() {
		if ( ! $this->theme ) {
			$this->theme = wp_get_theme();
		}
		return $this->theme;
	}

	public function settings() {
		return $this->settings;
	}

	public function controls() {
		return $this->controls;
	}

	public function containers() {
		return $this->containers;
	}

	public function sections() {
		return $this->sections;
	}

	public function panels() {
		return $this->panels;
	}

	public function is_theme_active() {
		return $this->get_stylesheet() == $this->original_stylesheet;
	}

	public function wp_loaded() {
		do_action( 'customize_register', $this );
		if ( $this->is_preview() && ! is_admin() )
			$this->customize_preview_init();
	}

	public function wp_redirect_status( $status ) {
		if ( $this->is_preview() && ! is_admin() )
			return 200;

		return $status;
	}

	public function unsanitized_post_values() {
		if ( ! isset( $this->_post_values ) ) {
			if ( isset( $_POST['customized'] ) ) {
				$this->_post_values = json_decode( wp_unslash( $_POST['customized'] ), true );
			}
			if ( empty( $this->_post_values ) ) {
				$this->_post_values = array();
			}
		}
		if ( empty( $this->_post_values ) ) {
			return array();
		} else {
			return $this->_post_values;
		}
	}

	public function post_value( $setting, $default = null ) {
		$post_values = $this->unsanitized_post_values();
		if ( array_key_exists( $setting->id, $post_values ) ) {
			return $setting->sanitize( $post_values[ $setting->id ] );
		} else {
			return $default;
		}
	}

	public function set_post_value( $setting_id, $value ) {
		$this->unsanitized_post_values();
		$this->_post_values[ $setting_id ] = $value;
		do_action( "customize_post_value_set_{$setting_id}", $value, $this );
		do_action( 'customize_post_value_set', $setting_id, $value, $this );
	}

	public function customize_preview_init() {
		$this->nonce_tick = check_ajax_referer( 'preview-customize_' . $this->get_stylesheet(), 'nonce' );
		$this->prepare_controls();
		wp_enqueue_script( 'customize-preview' );
		add_action( 'wp', array( $this, 'customize_preview_override_404_status' ) );
		add_action( 'wp_head', array( $this, 'customize_preview_base' ) );
		add_action( 'wp_head', array( $this, 'customize_preview_html5' ) );
		add_action( 'wp_head', array( $this, 'customize_preview_loading_style' ) );
		add_action( 'wp_footer', array( $this, 'customize_preview_settings' ), 20 );
		add_action( 'shutdown', array( $this, 'customize_preview_signature' ), 1000 );
		add_filter( 'wp_die_handler', array( $this, 'remove_preview_signature' ) );

		foreach ( $this->settings as $setting ) {
			$setting->preview();
		}

		do_action( 'customize_preview_init', $this );
	}

	public function customize_preview_override_404_status() {
		if ( is_404() ) {
			status_header( 200 );
		}
	}

	public function customize_preview_base() {
		?><base href="<?php echo home_url( '/' ); ?>" /><?php
	}

	public function customize_preview_html5() { ?>
		<!--[if lt IE 9]>
		<script type="text/javascript">
			var e = [ 'abbr', 'article', 'aside', 'audio', 'canvas', 'datalist', 'details',
				'figure', 'footer', 'header', 'hgroup', 'mark', 'menu', 'meter', 'nav',
				'output', 'progress', 'section', 'time', 'video' ];
			for ( var i = 0; i < e.length; i++ ) {
				document.createElement( e[i] );
			}
		</script>
		<![endif]--><?php
	}

	public function customize_preview_loading_style() {
		?><style>
			body.wp-customizer-unloading {
				opacity: 0.25;
				cursor: progress !important;
				-webkit-transition: opacity 0.5s;
				transition: opacity 0.5s;
			}
			body.wp-customizer-unloading * {
				pointer-events: none !important;
			}
		</style><?php
	}

	public function customize_preview_settings() {
		$settings = array(
			'theme' => array(
				'stylesheet' => $this->get_stylesheet(),
				'active'     => $this->is_theme_active(),
			),
			'url' => array(
				'self' => empty( $_SERVER['REQUEST_URI'] ) ? home_url( '/' ) : esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			),
			'channel' => wp_unslash( $_POST['customize_messenger_channel'] ),
			'activePanels' => array(),
			'activeSections' => array(),
			'activeControls' => array(),
			'nonce' => $this->get_nonces(),
			'l10n' => array(
				'shiftClickToEdit' => 'Shift-click to edit this element.',
			),
			'_dirty' => array_keys( $this->unsanitized_post_values() ),
		);

		foreach ( $this->panels as $panel_id => $panel ) {
			if ( $panel->check_capabilities() ) {
				$settings['activePanels'][ $panel_id ] = $panel->active();
				foreach ( $panel->sections as $section_id => $section ) {
					if ( $section->check_capabilities() ) {
						$settings['activeSections'][ $section_id ] = $section->active();
					}
				}
			}
		}
		foreach ( $this->sections as $id => $section ) {
			if ( $section->check_capabilities() ) {
				$settings['activeSections'][ $id ] = $section->active();
			}
		}
		foreach ( $this->controls as $id => $control ) {
			if ( $control->check_capabilities() ) {
				$settings['activeControls'][ $id ] = $control->active();
			}
		}

		?>
		<script type="text/javascript">
			var _wpCustomizeSettings = <?php echo wp_json_encode( $settings ); ?>;
			_wpCustomizeSettings.values = {};
			(function( v ) {
				<?php
				foreach ( $this->settings as $id => $setting ) {
					if ( $setting->check_capabilities() ) {
						printf(
							"v[%s] = %s;\n",
							wp_json_encode( $id ),
							wp_json_encode( $setting->js_value() )
						);
					}
				}
				?>
			})( _wpCustomizeSettings.values );
		</script>
		<?php
	}

	public function customize_preview_signature() {
		echo 'WP_CUSTOMIZER_SIGNATURE';
	}

	public function remove_preview_signature( $return = null ) {
		remove_action( 'shutdown', array( $this, 'customize_preview_signature' ), 1000 );
		return $return;
	}


	public function is_preview() {
		return (bool) $this->previewing;
	}

	public function get_template() {
		return $this->theme()->get_template();
	}

	public function get_stylesheet() {
		return $this->theme()->get_stylesheet();
	}

	public function get_template_root() {
		return get_raw_theme_root( $this->get_template(), true );
	}

	public function get_stylesheet_root() {
		return get_raw_theme_root( $this->get_stylesheet(), true );
	}

	public function current_theme( $current_theme ) {
		return $this->theme()->display('Name');
	}


	public function save() {
		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		$action = 'save-customize_' . $this->get_stylesheet();
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}
		if ( ! $this->is_theme_active() ) {
			$this->stop_previewing_theme();
			switch_theme( $this->get_stylesheet() );
			update_option( 'theme_switched_via_customizer', true );
			$this->start_previewing_theme();
		}

		do_action( 'customize_save', $this );

		foreach ( $this->settings as $setting ) {
			$setting->save();
		}

		do_action( 'customize_save_after', $this );

		$response = apply_filters( 'customize_save_response', array(), $this );
		wp_send_json_success( $response );
	}

	public function refresh_nonces() {
		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		wp_send_json_success( $this->get_nonces() );
	}

	public function add_setting( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Setting ) {
			$setting = $id;
		} else {
			$class = 'WP_Customize_Setting';
			$args = apply_filters( 'customize_dynamic_setting_args', $args, $id );
			$class = apply_filters( 'customize_dynamic_setting_class', $class, $id, $args );

			$setting = new $class( $this, $id, $args );
		}

		$this->settings[ $setting->id ] = $setting;
		return $setting;
	}

	public function add_dynamic_settings( $setting_ids ) {
		$new_settings = array();
		foreach ( $setting_ids as $setting_id ) {
			if ( $this->get_setting( $setting_id ) ) {
				continue;
			}

			$setting_args = false;
			$setting_class = 'WP_Customize_Setting';

			$setting_args = apply_filters( 'customize_dynamic_setting_args', $setting_args, $setting_id );
			if ( false === $setting_args ) {
				continue;
			}

			$setting_class = apply_filters( 'customize_dynamic_setting_class', $setting_class, $setting_id, $setting_args );

			$setting = new $setting_class( $this, $setting_id, $setting_args );

			$this->add_setting( $setting );
			$new_settings[] = $setting;
		}
		return $new_settings;
	}

	public function get_setting( $id ) {
		if ( isset( $this->settings[ $id ] ) ) {
			return $this->settings[ $id ];
		}
	}

	public function remove_setting( $id ) {
		unset( $this->settings[ $id ] );
	}

	public function add_panel( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Panel ) {
			$panel = $id;
		} else {
			$panel = new WP_Customize_Panel( $this, $id, $args );
		}

		$this->panels[ $panel->id ] = $panel;
		return $panel;
	}

	public function get_panel( $id ) {
		if ( isset( $this->panels[ $id ] ) ) {
			return $this->panels[ $id ];
		}
	}

	public function remove_panel( $id ) {
		if ( in_array( $id, $this->components, true ) ) {
			$message = sprintf( 'Removing %1$s manually will cause PHP warnings. Use the %2$s filter instead.',
				$id,
				'<a href="#"><code>customize_loaded_components</code></a>'
			);

			_doing_it_wrong( __METHOD__, $message, '4.5' );
		}
		unset( $this->panels[ $id ] );
	}

	public function register_panel_type( $panel ) {
		$this->registered_panel_types[] = $panel;
	}

	public function render_panel_templates() {
		foreach ( $this->registered_panel_types as $panel_type ) {
			$panel = new $panel_type( $this, 'temp', array() );
			$panel->print_template();
		}
	}

	public function add_section( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Section ) {
			$section = $id;
		} else {
			$section = new WP_Customize_Section( $this, $id, $args );
		}

		$this->sections[ $section->id ] = $section;
		return $section;
	}

	public function get_section( $id ) {
		if ( isset( $this->sections[ $id ] ) )
			return $this->sections[ $id ];
	}

	public function remove_section( $id ) {
		unset( $this->sections[ $id ] );
	}

	public function register_section_type( $section ) {
		$this->registered_section_types[] = $section;
	}

	public function render_section_templates() {
		foreach ( $this->registered_section_types as $section_type ) {
			$section = new $section_type( $this, 'temp', array() );
			$section->print_template();
		}
	}

	public function add_control( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Control ) {
			$control = $id;
		} else {
			$control = new WP_Customize_Control( $this, $id, $args );
		}

		$this->controls[ $control->id ] = $control;
		return $control;
	}

	public function get_control( $id ) {
		if ( isset( $this->controls[ $id ] ) )
			return $this->controls[ $id ];
	}

	public function remove_control( $id ) {
		unset( $this->controls[ $id ] );
	}

	public function register_control_type( $control ) {
		$this->registered_control_types[] = $control;
	}

	public function render_control_templates() {
		foreach ( $this->registered_control_types as $control_type ) {
			$control = new $control_type( $this, 'temp', array(
				'settings' => array(),
			) );
			$control->print_template();
		}
	}

	protected function _cmp_priority( $a, $b ) {
		if ( $a->priority === $b->priority ) {
			return $a->instance_number - $b->instance_number;
		} else {
			return $a->priority - $b->priority;
		}
	}

	public function prepare_controls() {
		$controls = array();
		uasort( $this->controls, array( $this, '_cmp_priority' ) );
		foreach ( $this->controls as $id => $control ) {
			if ( ! isset( $this->sections[ $control->section ] ) || ! $control->check_capabilities() ) {
				continue;
			}

			$this->sections[ $control->section ]->controls[] = $control;
			$controls[ $id ] = $control;
		}
		$this->controls = $controls;
		uasort( $this->sections, array( $this, '_cmp_priority' ) );
		$sections = array();
		foreach ( $this->sections as $section ) {
			if ( ! $section->check_capabilities() ) {
				continue;
			}
			usort( $section->controls, array( $this, '_cmp_priority' ) );
			if ( ! $section->panel ) {
				$sections[ $section->id ] = $section;
			} else {
				if ( isset( $this->panels [ $section->panel ] ) ) {
					$this->panels[ $section->panel ]->sections[ $section->id ] = $section;
				}
			}
		}
		$this->sections = $sections;
		uasort( $this->panels, array( $this, '_cmp_priority' ) );
		$panels = array();
		foreach ( $this->panels as $panel ) {
			if ( ! $panel->check_capabilities() ) {
				continue;
			}
			uasort( $panel->sections, array( $this, '_cmp_priority' ) );
			$panels[ $panel->id ] = $panel;
		}
		$this->panels = $panels;
		$this->containers = array_merge( $this->panels, $this->sections );
		uasort( $this->containers, array( $this, '_cmp_priority' ) );
	}

	public function enqueue_control_scripts() {
		foreach ( $this->controls as $control ) {
			$control->enqueue();
		}
	}

	public function is_ios() {
		return wp_is_mobile() && preg_match( '/iPad|iPod|iPhone/', $_SERVER['HTTP_USER_AGENT'] );
	}

	public function get_document_title_template() {
		if ( $this->is_theme_active() ) {
			$document_title_tmpl = 'Customize: %s';
		} else {
			$document_title_tmpl = 'Live Preview: %s';
		}
		$document_title_tmpl = html_entity_decode( $document_title_tmpl, ENT_QUOTES, 'UTF-8' );
		return $document_title_tmpl;
	}

	public function set_preview_url( $preview_url ) {
		$preview_url = esc_url_raw( $preview_url );
		$this->preview_url = wp_validate_redirect( $preview_url, home_url( '/' ) );
	}

	public function get_preview_url() {
		if ( empty( $this->preview_url ) ) {
			$preview_url = home_url( '/' );
		} else {
			$preview_url = $this->preview_url;
		}
		return $preview_url;
	}

	public function set_return_url( $return_url ) {
		$return_url = esc_url_raw( $return_url );
		$return_url = remove_query_arg( wp_removable_query_args(), $return_url );
		$return_url = wp_validate_redirect( $return_url );
		$this->return_url = $return_url;
	}

	public function get_return_url() {
		$referer = wp_get_referer();
		$excluded_referer_basenames = array( 'customize.php', 'wp-login.php' );

		if ( $this->return_url ) {
			$return_url = $this->return_url;
		} else if ( $referer && ! in_array( basename( parse_url( $referer, PHP_URL_PATH ) ), $excluded_referer_basenames, true ) ) {
			$return_url = $referer;
		} else if ( $this->preview_url ) {
			$return_url = $this->preview_url;
		} else {
			$return_url = home_url( '/' );
		}
		return $return_url;
	}

	public function set_autofocus( $autofocus ) {
		$this->autofocus = array_filter( wp_array_slice_assoc( $autofocus, array( 'panel', 'section', 'control' ) ), 'is_string' );
	}

	public function get_autofocus() {
		return $this->autofocus;
	}

	public function get_nonces() {
		$nonces = array(
			'save' => wp_create_nonce( 'save-customize_' . $this->get_stylesheet() ),
			'preview' => wp_create_nonce( 'preview-customize_' . $this->get_stylesheet() ),
		);

		$nonces = apply_filters( 'customize_refresh_nonces', $nonces, $this );

		return $nonces;
	}

	public function customize_pane_settings() {
		$allowed_urls = array( home_url( '/' ) );
		$admin_origin = parse_url( admin_url() );
		$home_origin  = parse_url( home_url() );
		$cross_domain = ( strtolower( $admin_origin['host'] ) !== strtolower( $home_origin['host'] ) );

		if ( is_ssl() && ! $cross_domain ) {
			$allowed_urls[] = home_url( '/', 'https' );
		}

		$allowed_urls = array_unique( apply_filters( 'customize_allowed_urls', $allowed_urls ) );

		$login_url = add_query_arg( array(
			'interim-login' => 1,
			'customize-login' => 1,
		), wp_login_url() );

		$settings = array(
			'theme'    => array(
				'stylesheet' => $this->get_stylesheet(),
				'active'     => $this->is_theme_active(),
			),
			'url'      => array(
				'preview'       => esc_url_raw( $this->get_preview_url() ),
				'parent'        => esc_url_raw( admin_url() ),
				'activated'     => esc_url_raw( home_url( '/' ) ),
				'ajax'          => esc_url_raw( admin_url( 'admin-ajax.php', 'relative' ) ),
				'allowed'       => array_map( 'esc_url_raw', $allowed_urls ),
				'isCrossDomain' => $cross_domain,
				'home'          => esc_url_raw( home_url( '/' ) ),
				'login'         => esc_url_raw( $login_url ),
			),
			'browser'  => array(
				'mobile' => wp_is_mobile(),
				'ios'    => $this->is_ios(),
			),
			'panels'   => array(),
			'sections' => array(),
			'nonce'    => $this->get_nonces(),
			'autofocus' => $this->get_autofocus(),
			'documentTitleTmpl' => $this->get_document_title_template(),
			'previewableDevices' => $this->get_previewable_devices(),
		);

		foreach ( $this->sections() as $id => $section ) {
			if ( $section->check_capabilities() ) {
				$settings['sections'][ $id ] = $section->json();
			}
		}

		foreach ( $this->panels() as $panel_id => $panel ) {
			if ( $panel->check_capabilities() ) {
				$settings['panels'][ $panel_id ] = $panel->json();
				foreach ( $panel->sections as $section_id => $section ) {
					if ( $section->check_capabilities() ) {
						$settings['sections'][ $section_id ] = $section->json();
					}
				}
			}
		}

		?>
		<script type="text/javascript">
			var _wpCustomizeSettings = <?php echo wp_json_encode( $settings ); ?>;
			_wpCustomizeSettings.controls = {};
			_wpCustomizeSettings.settings = {};
			<?php

			echo "(function ( s ){\n";
			foreach ( $this->settings() as $setting ) {
				if ( $setting->check_capabilities() ) {
					printf(
						"s[%s] = %s;\n",
						wp_json_encode( $setting->id ),
						wp_json_encode( array(
							'value'     => $setting->js_value(),
							'transport' => $setting->transport,
							'dirty'     => $setting->dirty,
						) )
					);
				}
			}
			echo "})( _wpCustomizeSettings.settings );\n";

			echo "(function ( c ){\n";
			foreach ( $this->controls() as $control ) {
				if ( $control->check_capabilities() ) {
					printf(
						"c[%s] = %s;\n",
						wp_json_encode( $control->id ),
						wp_json_encode( $control->json() )
					);
				}
			}
			echo "})( _wpCustomizeSettings.controls );\n";
		?>
		</script>
		<?php
	}

	public function get_previewable_devices() {
		$devices = array(
			'desktop' => array(
				'label' => 'Enter desktop preview mode',
				'default' => true,
			),
			'tablet' => array(
				'label' => 'Enter tablet preview mode',
			),
			'mobile' => array(
				'label' => 'Enter mobile preview mode',
			),
		);

		$devices = apply_filters( 'customize_previewable_devices', $devices );

		return $devices;
	}

	public function register_controls() {
		$this->register_panel_type( 'WP_Customize_Panel' );
		$this->register_section_type( 'WP_Customize_Section' );
		$this->register_section_type( 'WP_Customize_Sidebar_Section' );
		$this->register_control_type( 'WP_Customize_Color_Control' );
		$this->register_control_type( 'WP_Customize_Media_Control' );
		$this->register_control_type( 'WP_Customize_Upload_Control' );
		$this->register_control_type( 'WP_Customize_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Image_Control' );
		$this->register_control_type( 'WP_Customize_Cropped_Image_Control' );
		$this->register_control_type( 'WP_Customize_Site_Icon_Control' );
		$this->register_control_type( 'WP_Customize_Theme_Control' );

		$this->add_section( new WP_Customize_Themes_Section( $this, 'themes', array(
			'title'      => $this->theme()->display( 'Name' ),
			'capability' => 'switch_themes',
			'priority'   => 0,
		) ) );

		$this->add_setting( new WP_Customize_Filter_Setting( $this, 'active_theme', array(
			'capability' => 'switch_themes',
		) ) );

		require_once( ABSPATH . 'wp-admin/includes/theme.php' );

		if ( ! $this->is_theme_active() ) {
			$themes = wp_prepare_themes_for_js( array( wp_get_theme( $this->original_stylesheet ) ) );
			$active_theme = current( $themes );
			$active_theme['isActiveTheme'] = true;
			$this->add_control( new WP_Customize_Theme_Control( $this, $active_theme['id'], array(
				'theme'    => $active_theme,
				'section'  => 'themes',
				'settings' => 'active_theme',
			) ) );
		}

		$themes = wp_prepare_themes_for_js();
		foreach ( $themes as $theme ) {
			if ( $theme['active'] || $theme['id'] === $this->original_stylesheet ) {
				continue;
			}

			$theme_id = 'theme_' . $theme['id'];
			$theme['isActiveTheme'] = false;
			$this->add_control( new WP_Customize_Theme_Control( $this, $theme_id, array(
				'theme'    => $theme,
				'section'  => 'themes',
				'settings' => 'active_theme',
			) ) );
		}

		$this->add_section( 'title_tagline', array(
			'title'    => 'Site Identity',
			'priority' => 20,
		) );

		$this->add_setting( 'blogname', array(
			'default'    => get_option( 'blogname' ),
			'type'       => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'blogname', array(
			'label'      => 'Site Title',
			'section'    => 'title_tagline',
		) );

		$this->add_setting( 'blogdescription', array(
			'default'    => get_option( 'blogdescription' ),
			'type'       => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'blogdescription', array(
			'label'      => 'Tagline',
			'section'    => 'title_tagline',
		) );

		if ( ! current_theme_supports( 'custom-header', 'header-text' ) ) {
			$this->add_setting( 'header_text', array(
				'theme_supports'    => array( 'custom-logo', 'header-text' ),
				'default'           => 1,
				'sanitize_callback' => 'absint',
			) );

			$this->add_control( 'header_text', array(
				'label'    => 'Display Site Title and Tagline',
				'section'  => 'title_tagline',
				'settings' => 'header_text',
				'type'     => 'checkbox',
			) );
		}

		$this->add_setting( 'site_icon', array(
			'type'       => 'option',
			'capability' => 'manage_options',
			'transport'  => 'postMessage',
		) );

		$this->add_control( new WP_Customize_Site_Icon_Control( $this, 'site_icon', array(
			'label'       => 'Site Icon',
			'description' => sprintf(
				'The Site Icon is used as a browser and app icon for your site. Icons must be square, and at least %s pixels wide and tall.',
				'<strong>512</strong>'
			),
			'section'     => 'title_tagline',
			'priority'    => 60,
			'height'      => 512,
			'width'       => 512,
		) ) );

		$this->add_setting( 'custom_logo', array(
			'theme_supports' => array( 'custom-logo' ),
			'transport'      => 'postMessage',
		) );

		$custom_logo_args = get_theme_support( 'custom-logo' );
		$this->add_control( new WP_Customize_Cropped_Image_Control( $this, 'custom_logo', array(
			'label'         => 'Logo',
			'section'       => 'title_tagline',
			'priority'      => 8,
			'height'        => $custom_logo_args[0]['height'],
			'width'         => $custom_logo_args[0]['width'],
			'flex_height'   => $custom_logo_args[0]['flex-height'],
			'flex_width'    => $custom_logo_args[0]['flex-width'],
			'button_labels' => array(
				'select'       => 'Select logo',
				'change'       => 'Change logo',
				'remove'       => 'Remove',
				'default'      => 'Default',
				'placeholder'  => 'No logo selected',
				'frame_title'  => 'Select logo',
				'frame_button' => 'Choose logo',
			),
		) ) );

		$this->selective_refresh->add_partial( 'custom_logo', array(
			'settings'            => array( 'custom_logo' ),
			'selector'            => '.custom-logo-link',
			'render_callback'     => array( $this, '_render_custom_logo_partial' ),
			'container_inclusive' => true,
		) );

		$this->add_section( 'colors', array(
			'title'          => 'Colors',
			'priority'       => 40,
		) );

		$this->add_setting( 'header_textcolor', array(
			'theme_supports' => array( 'custom-header', 'header-text' ),
			'default'        => get_theme_support( 'custom-header', 'default-text-color' ),

			'sanitize_callback'    => array( $this, '_sanitize_header_textcolor' ),
			'sanitize_js_callback' => 'maybe_hash_hex_color',
		) );

		$this->add_control( 'display_header_text', array(
			'settings' => 'header_textcolor',
			'label'    => 'Display Site Title and Tagline',
			'section'  => 'title_tagline',
			'type'     => 'checkbox',
			'priority' => 40,
		) );

		$this->add_control( new WP_Customize_Color_Control( $this, 'header_textcolor', array(
			'label'   => 'Header Text Color',
			'section' => 'colors',
		) ) );

		$this->add_setting( 'background_color', array(
			'default'        => get_theme_support( 'custom-background', 'default-color' ),
			'theme_supports' => 'custom-background',

			'sanitize_callback'    => 'sanitize_hex_color_no_hash',
			'sanitize_js_callback' => 'maybe_hash_hex_color',
		) );

		$this->add_control( new WP_Customize_Color_Control( $this, 'background_color', array(
			'label'   => 'Background Color',
			'section' => 'colors',
		) ) );

		$this->add_section( 'header_image', array(
			'title'          => 'Header Image',
			'theme_supports' => 'custom-header',
			'priority'       => 60,
		) );

		$this->add_setting( new WP_Customize_Filter_Setting( $this, 'header_image', array(
			'default'        => get_theme_support( 'custom-header', 'default-image' ),
			'theme_supports' => 'custom-header',
		) ) );

		$this->add_setting( new WP_Customize_Header_Image_Setting( $this, 'header_image_data', array(
			// 'default'        => get_theme_support( 'custom-header', 'default-image' ),
			'theme_supports' => 'custom-header',
		) ) );

		$this->add_control( new WP_Customize_Header_Image_Control( $this ) );

		$this->add_section( 'background_image', array(
			'title'          => 'Background Image',
			'theme_supports' => 'custom-background',
			'priority'       => 80,
		) );

		$this->add_setting( 'background_image', array(
			'default'        => get_theme_support( 'custom-background', 'default-image' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_setting( new WP_Customize_Background_Image_Setting( $this, 'background_image_thumb', array(
			'theme_supports' => 'custom-background',
		) ) );

		$this->add_control( new WP_Customize_Background_Image_Control( $this ) );

		$this->add_setting( 'background_repeat', array(
			'default'        => get_theme_support( 'custom-background', 'default-repeat' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_repeat', array(
			'label'      => 'Background Repeat',
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'no-repeat'  => 'No Repeat',
				'repeat'     => 'Tile',
				'repeat-x'   => 'Tile Horizontally',
				'repeat-y'   => 'Tile Vertically',
			),
		) );

		$this->add_setting( 'background_position_x', array(
			'default'        => get_theme_support( 'custom-background', 'default-position-x' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_position_x', array(
			'label'      => 'Background Position',
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'left'       => 'Left',
				'center'     => 'Center',
				'right'      => 'Right',
			),
		) );

		$this->add_setting( 'background_attachment', array(
			'default'        => get_theme_support( 'custom-background', 'default-attachment' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_attachment', array(
			'label'      => 'Background Attachment',
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'scroll'     => 'Scroll',
				'fixed'      => 'Fixed',
			),
		) );

		if ( get_theme_support( 'custom-background', 'wp-head-callback' ) === '_custom_background_cb' ) {
			foreach ( array( 'color', 'image', 'position_x', 'repeat', 'attachment' ) as $prop ) {
				$this->get_setting( 'background_' . $prop )->transport = 'postMessage';
			}
		}

		if ( get_pages() ) {
			$this->add_section( 'static_front_page', array(
				'title'          => 'Static Front Page',
			//	'theme_supports' => 'static-front-page',
				'priority'       => 120,
				'description'    => 'Your theme supports a static front page.',
			) );

			$this->add_setting( 'show_on_front', array(
				'default'        => get_option( 'show_on_front' ),
				'capability'     => 'manage_options',
				'type'           => 'option',
			//	'theme_supports' => 'static-front-page',
			) );

			$this->add_control( 'show_on_front', array(
				'label'   => 'Front page displays',
				'section' => 'static_front_page',
				'type'    => 'radio',
				'choices' => array(
					'posts' => 'Your latest posts',
					'page'  => 'A static page',
				),
			) );

			$this->add_setting( 'page_on_front', array(
				'type'       => 'option',
				'capability' => 'manage_options',
			//	'theme_supports' => 'static-front-page',
			) );

			$this->add_control( 'page_on_front', array(
				'label'      => 'Front page',
				'section'    => 'static_front_page',
				'type'       => 'dropdown-pages',
			) );

			$this->add_setting( 'page_for_posts', array(
				'type'           => 'option',
				'capability'     => 'manage_options',
			//	'theme_supports' => 'static-front-page',
			) );

			$this->add_control( 'page_for_posts', array(
				'label'      => 'Posts page',
				'section'    => 'static_front_page',
				'type'       => 'dropdown-pages',
			) );
		}
	}

	public function register_dynamic_settings() {
		$this->add_dynamic_settings( array_keys( $this->unsanitized_post_values() ) );
	}

	public function _sanitize_header_textcolor( $color ) {
		if ( 'blank' === $color )
			return 'blank';

		$color = sanitize_hex_color_no_hash( $color );
		if ( empty( $color ) )
			$color = get_theme_support( 'custom-header', 'default-text-color' );

		return $color;
	}

	public function _render_custom_logo_partial() {
		return get_custom_logo();
	}
}

function sanitize_hex_color( $color ) {
	if ( '' === $color )
		return '';
	if ( preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) )
		return $color;
}

function sanitize_hex_color_no_hash( $color ) {
	$color = ltrim( $color, '#' );
	if ( '' === $color )
		return '';
	return sanitize_hex_color( '#' . $color ) ? $color : null;
}

function maybe_hash_hex_color( $color ) {
	if ( $unhashed = sanitize_hex_color_no_hash( $color ) )
		return '#' . $unhashed;

	return $color;
}
