<?php
/**
 * Toolbar API: WP_Admin_Bar class
 *
 * @package WordPress
 * @subpackage Toolbar
 * @since 3.1.0
 */

/**
 * Core class used to implement the Toolbar API.
 *
 * @since 3.1.0
 */
class WP_Admin_Bar {
	private $nodes = array();
	private $bound = false;
	public $user;

	/**
	 * @param string $name
	 * @return string|array|void
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'proto' :
				return is_ssl() ? 'https://' : 'http://';

			case 'menu' :
				_deprecated_argument( 'WP_Admin_Bar', '3.3', 'Modify admin bar nodes with WP_Admin_Bar::get_node(), WP_Admin_Bar::add_node(), and WP_Admin_Bar::remove_node(), not the <code>menu</code> property.' );
				return array(); // Sorry, folks.
		}
	}

	/**
	 * @access public
	 */
	public function initialize() {
		$this->user = new stdClass;

		if ( is_user_logged_in() ) {
			/* Populate settings we need for the menu based on the current user. */
			$this->user->blogs = get_blogs_of_user( get_current_user_id() );
			if ( is_multisite() ) {
				$this->user->active_blog = get_active_blog_for_user( get_current_user_id() );
				$this->user->domain = empty( $this->user->active_blog ) ? user_admin_url() : trailingslashit( get_home_url( $this->user->active_blog->blog_id ) );
				$this->user->account_domain = $this->user->domain;
			} else {
				$this->user->active_blog = $this->user->blogs[get_current_blog_id()];
				$this->user->domain = trailingslashit( home_url() );
				$this->user->account_domain = $this->user->domain;
			}
		}

		add_action( 'wp_head', 'wp_admin_bar_header' );

		add_action( 'admin_head', 'wp_admin_bar_header' );

		if ( current_theme_supports( 'admin-bar' ) ) {
			/**
			 * To remove the default padding styles from WordPress for the Toolbar, use the following code:
			 * add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );
			 */
			$admin_bar_args = get_theme_support( 'admin-bar' );
			$header_callback = $admin_bar_args[0]['callback'];
		}

		if ( empty($header_callback) )
			$header_callback = '_admin_bar_bump_cb';

		add_action('wp_head', $header_callback);

		wp_enqueue_script( 'admin-bar' );
		wp_enqueue_style( 'admin-bar' );

		/**
		 * Fires after WP_Admin_Bar is initialized.
		 *
		 * @since 3.1.0
		 */
		do_action( 'admin_bar_init' );
	}

	/**
	 * @param array $node
	 */
	public function add_menu( $node ) {
		$this->add_node( $node );
	}

	/**
	 * @param string $id
	 */
	public function remove_menu( $id ) {
		$this->remove_node( $id );
	}

	public function add_node( $args ) {
		if ( func_num_args() >= 3 && is_string( func_get_arg(0) ) )
			$args = array_merge( array( 'parent' => func_get_arg(0) ), func_get_arg(2) );

		if ( is_object( $args ) )
			$args = get_object_vars( $args );

		// Ensure we have a valid title.
		if ( empty( $args['id'] ) ) {
			if ( empty( $args['title'] ) )
				return;

			_doing_it_wrong( __METHOD__, 'The menu ID should not be empty.', '3.3' );
			$args['id'] = esc_attr( sanitize_title( trim( $args['title'] ) ) );
		}

		$defaults = array(
			'id'     => false,
			'title'  => false,
			'parent' => false,
			'href'   => false,
			'group'  => false,
			'meta'   => array(),
		);

		// If the node already exists, keep any data that isn't provided.
		if ( $maybe_defaults = $this->get_node( $args['id'] ) )
			$defaults = get_object_vars( $maybe_defaults );

		// Do the same for 'meta' items.
		if ( ! empty( $defaults['meta'] ) && ! empty( $args['meta'] ) )
			$args['meta'] = wp_parse_args( $args['meta'], $defaults['meta'] );

		$args = wp_parse_args( $args, $defaults );

		$back_compat_parents = array(
			'my-account-with-avatar' => array( 'my-account', '3.3' ),
			'my-blogs'               => array( 'my-sites',   '3.3' ),
		);

		if ( isset( $back_compat_parents[ $args['parent'] ] ) ) {
			list( $new_parent, $version ) = $back_compat_parents[ $args['parent'] ];
			_deprecated_argument( __METHOD__, $version, sprintf( 'Use <code>%s</code> as the parent for the <code>%s</code> admin bar node instead of <code>%s</code>.', $new_parent, $args['id'], $args['parent'] ) );
			$args['parent'] = $new_parent;
		}

		$this->_set_node( $args );
	}

	final protected function _set_node( $args ) {
		$this->nodes[ $args['id'] ] = (object) $args;
	}

	final public function get_node( $id ) {
		if ( $node = $this->_get_node( $id ) )
			return clone $node;
	}

	final protected function _get_node( $id ) {
		if ( $this->bound )
			return;

		if ( empty( $id ) )
			$id = 'root';

		if ( isset( $this->nodes[ $id ] ) )
			return $this->nodes[ $id ];
	}

	final public function get_nodes() {
		if ( ! $nodes = $this->_get_nodes() )
			return;

		foreach ( $nodes as &$node ) {
			$node = clone $node;
		}
		return $nodes;
	}

	final protected function _get_nodes() {
		if ( $this->bound )
			return;

		return $this->nodes;
	}

	final public function add_group( $args ) {
		$args['group'] = true;

		$this->add_node( $args );
	}

	public function remove_node( $id ) {
		$this->_unset_node( $id );
	}

	final protected function _unset_node( $id ) {
		unset( $this->nodes[ $id ] );
	}

	public function render() {
		$root = $this->_bind();
		if ( $root )
			$this->_render( $root );
	}

	final protected function _bind() {
		if ( $this->bound )
			return;

		$this->remove_node( 'root' );
		$this->add_node( array(
			'id'    => 'root',
			'group' => false,
		) );

		foreach ( $this->_get_nodes() as $node ) {
			$node->children = array();
			$node->type = ( $node->group ) ? 'group' : 'item';
			unset( $node->group );

			if ( ! $node->parent )
				$node->parent = 'root';
		}

		foreach ( $this->_get_nodes() as $node ) {
			if ( 'root' == $node->id )
				continue;

			if ( ! $parent = $this->_get_node( $node->parent ) ) {
				continue;
			}

			$group_class = ( $node->parent == 'root' ) ? 'ab-top-menu' : 'ab-submenu';

			if ( $node->type == 'group' ) {
				if ( empty( $node->meta['class'] ) )
					$node->meta['class'] = $group_class;
				else
					$node->meta['class'] .= ' ' . $group_class;
			}

			if ( $parent->type == 'item' && $node->type == 'item' ) {
				$default_id = $parent->id . '-default';
				$default    = $this->_get_node( $default_id );

				if ( ! $default ) {
					$this->_set_node( array(
						'id'        => $default_id,
						'parent'    => $parent->id,
						'type'      => 'group',
						'children'  => array(),
						'meta'      => array(
							'class'     => $group_class,
						),
						'title'     => false,
						'href'      => false,
					) );
					$default = $this->_get_node( $default_id );
					$parent->children[] = $default;
				}
				$parent = $default;

			} elseif ( $parent->type == 'group' && $node->type == 'group' ) {
				$container_id = $parent->id . '-container';
				$container    = $this->_get_node( $container_id );

				if ( ! $container ) {
					$this->_set_node( array(
						'id'       => $container_id,
						'type'     => 'container',
						'children' => array( $parent ),
						'parent'   => false,
						'title'    => false,
						'href'     => false,
						'meta'     => array(),
					) );

					$container = $this->_get_node( $container_id );

					// Link the container node if a grandparent node exists.
					$grandparent = $this->_get_node( $parent->parent );

					if ( $grandparent ) {
						$container->parent = $grandparent->id;

						$index = array_search( $parent, $grandparent->children, true );
						if ( $index === false )
							$grandparent->children[] = $container;
						else
							array_splice( $grandparent->children, $index, 1, array( $container ) );
					}

					$parent->parent = $container->id;
				}

				$parent = $container;
			}

			// Update the parent ID (it might have changed).
			$node->parent = $parent->id;

			// Add the node to the tree.
			$parent->children[] = $node;
		}

		$root = $this->_get_node( 'root' );
		$this->bound = true;
		return $root;
	}

	final protected function _render( $root ) {
		global $is_IE;

		$class = 'nojq nojs';
		if ( $is_IE ) {
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 7' ) )
				$class .= ' ie7';
			elseif ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) )
				$class .= ' ie8';
			elseif ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 9' ) )
				$class .= ' ie9';
		} elseif ( wp_is_mobile() ) {
			$class .= ' mobile';
		}

		?>
		<div id="wpadminbar" class="<?php echo $class; ?>">
			<?php if ( ! is_admin() ) { ?>
				<a class="screen-reader-shortcut" href="#wp-toolbar" tabindex="1">Skip to toolbar</a>
			<?php } ?>
			<div class="quicklinks" id="wp-toolbar" role="navigation" aria-label="<?php esc_attr_e( 'Toolbar' ); ?>" tabindex="0">
				<?php foreach ( $root->children as $group ) {
					$this->_render_group( $group );
				} ?>
			</div>
			<?php if ( is_user_logged_in() ) : ?>
			<a class="screen-reader-shortcut" href="<?php echo esc_url( wp_logout_url() ); ?>">Log Out</a>
			<?php endif; ?>
		</div>

		<?php
	}

	final protected function _render_container( $node ) {
		if ( $node->type != 'container' || empty( $node->children ) )
			return;

		?><div id="<?php echo esc_attr( 'wp-admin-bar-' . $node->id ); ?>" class="ab-group-container"><?php
			foreach ( $node->children as $group ) {
				$this->_render_group( $group );
			}
		?></div><?php
	}

	final protected function _render_group( $node ) {
		if ( $node->type == 'container' ) {
			$this->_render_container( $node );
			return;
		}
		if ( $node->type != 'group' || empty( $node->children ) )
			return;

		if ( ! empty( $node->meta['class'] ) )
			$class = ' class="' . esc_attr( trim( $node->meta['class'] ) ) . '"';
		else
			$class = '';

		?><ul id="<?php echo esc_attr( 'wp-admin-bar-' . $node->id ); ?>"<?php echo $class; ?>><?php
			foreach ( $node->children as $item ) {
				$this->_render_item( $item );
			}
		?></ul><?php
	}

	final protected function _render_item( $node ) {
		if ( $node->type != 'item' )
			return;

		$is_parent = ! empty( $node->children );
		$has_link  = ! empty( $node->href );

		$tabindex = isset( $node->meta['tabindex'] ) ? (int) $node->meta['tabindex'] : '';
		$aria_attributes = $tabindex ? 'tabindex="' . $tabindex . '"' : '';

		$menuclass = '';

		if ( $is_parent ) {
			$menuclass = 'menupop ';
			$aria_attributes .= ' aria-haspopup="true"';
		}

		if ( ! empty( $node->meta['class'] ) )
			$menuclass .= $node->meta['class'];

		if ( $menuclass )
			$menuclass = ' class="' . esc_attr( trim( $menuclass ) ) . '"';

		?>

		<li id="<?php echo esc_attr( 'wp-admin-bar-' . $node->id ); ?>"<?php echo $menuclass; ?>><?php
			if ( $has_link ):
				?><a class="ab-item" <?php echo $aria_attributes; ?> href="<?php echo esc_url( $node->href ) ?>"<?php
					if ( ! empty( $node->meta['onclick'] ) ) :
						?> onclick="<?php echo esc_js( $node->meta['onclick'] ); ?>"<?php
					endif;
				if ( ! empty( $node->meta['target'] ) ) :
					?> target="<?php echo esc_attr( $node->meta['target'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['title'] ) ) :
					?> title="<?php echo esc_attr( $node->meta['title'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['rel'] ) ) :
					?> rel="<?php echo esc_attr( $node->meta['rel'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['lang'] ) ) :
					?> lang="<?php echo esc_attr( $node->meta['lang'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['dir'] ) ) :
					?> dir="<?php echo esc_attr( $node->meta['dir'] ); ?>"<?php
				endif;
				?>><?php
			else:
				?><div class="ab-item ab-empty-item" <?php echo $aria_attributes;
				if ( ! empty( $node->meta['title'] ) ) :
					?> title="<?php echo esc_attr( $node->meta['title'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['lang'] ) ) :
					?> lang="<?php echo esc_attr( $node->meta['lang'] ); ?>"<?php
				endif;
				if ( ! empty( $node->meta['dir'] ) ) :
					?> dir="<?php echo esc_attr( $node->meta['dir'] ); ?>"<?php
				endif;
				?>><?php
			endif;

			echo $node->title;

			if ( $has_link ) :
				?></a><?php
			else:
				?></div><?php
			endif;

			if ( $is_parent ) :
				?><div class="ab-sub-wrapper"><?php
					foreach ( $node->children as $group ) {
						$this->_render_group( $group );
					}
				?></div><?php
			endif;

			if ( ! empty( $node->meta['html'] ) )
				echo $node->meta['html'];

			?>
		</li><?php
	}

	public function recursive_render( $id, $node ) {
		_deprecated_function( __METHOD__, '3.3', 'WP_Admin_bar::render(), WP_Admin_Bar::_render_item()' );
		$this->_render_item( $node );
	}

	public function add_menus() {
		add_action( 'admin_bar_menu', 'wp_admin_bar_my_account_menu', 0 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_search_menu', 4 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_my_account_item', 7 );

		add_action( 'admin_bar_menu', 'wp_admin_bar_sidebar_toggle', 0 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_my_sites_menu', 20 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_site_menu', 30 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu', 40 );
		add_action( 'admin_bar_menu', 'wp_admin_bar_updates_menu', 50 );

		if ( ! is_network_admin() && ! is_user_admin() ) {
			add_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
			add_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );
		}
		add_action( 'admin_bar_menu', 'wp_admin_bar_edit_menu', 80 );

		add_action( 'admin_bar_menu', 'wp_admin_bar_add_secondary_groups', 200 );

		do_action( 'add_admin_bar_menus' );
	}
}
