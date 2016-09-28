<?php
/**
 * Widget API: WP_Widget_Links class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.4.0
 */

class WP_Widget_Links extends WP_Widget {

	public function __construct() {
		$widget_ops = array(
			'description' => 'Your blogroll',
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'links', 'Links', $widget_ops );
	}

	public function widget( $args, $instance ) {
		$show_description = isset($instance['description']) ? $instance['description'] : false;
		$show_name = isset($instance['name']) ? $instance['name'] : false;
		$show_rating = isset($instance['rating']) ? $instance['rating'] : false;
		$show_images = isset($instance['images']) ? $instance['images'] : true;
		$category = isset($instance['category']) ? $instance['category'] : false;
		$orderby = isset( $instance['orderby'] ) ? $instance['orderby'] : 'name';
		$order = $orderby == 'rating' ? 'DESC' : 'ASC';
		$limit = isset( $instance['limit'] ) ? $instance['limit'] : -1;

		$before_widget = preg_replace( '/id="[^"]*"/', 'id="%id"', $args['before_widget'] );

		$widget_links_args = array(
			'title_before'     => $args['before_title'],
			'title_after'      => $args['after_title'],
			'category_before'  => $before_widget,
			'category_after'   => $args['after_widget'],
			'show_images'      => $show_images,
			'show_description' => $show_description,
			'show_name'        => $show_name,
			'show_rating'      => $show_rating,
			'category'         => $category,
			'class'            => 'linkcat widget',
			'orderby'          => $orderby,
			'order'            => $order,
			'limit'            => $limit,
		);

		wp_list_bookmarks( apply_filters( 'widget_links_args', $widget_links_args, $instance ) );
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance = (array) $new_instance;
		$instance = array( 'images' => 0, 'name' => 0, 'description' => 0, 'rating' => 0 );
		foreach ( $instance as $field => $val ) {
			if ( isset($new_instance[$field]) )
				$instance[$field] = 1;
		}

		$instance['orderby'] = 'name';
		if ( in_array( $new_instance['orderby'], array( 'name', 'rating', 'id', 'rand' ) ) )
			$instance['orderby'] = $new_instance['orderby'];

		$instance['category'] = intval( $new_instance['category'] );
		$instance['limit'] = ! empty( $new_instance['limit'] ) ? intval( $new_instance['limit'] ) : -1;

		return $instance;
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'images' => true, 'name' => true, 'description' => false, 'rating' => false, 'category' => false, 'orderby' => 'name', 'limit' => -1 ) );
		$link_cats = get_terms( 'link_category' );
		if ( ! $limit = intval( $instance['limit'] ) )
			$limit = -1;
			?>
		<p>
		<label for="<?php echo $this->get_field_id('category'); ?>"><?php _e( 'Select Link Category:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id('category'); ?>" name="<?php echo $this->get_field_name('category'); ?>">
		<option value="">All Links</option>
		<?php
		foreach ( $link_cats as $link_cat ) {
			echo '<option value="' . intval( $link_cat->term_id ) . '"'
				. selected( $instance['category'], $link_cat->term_id, false )
				. '>' . $link_cat->name . "</option>\n";
		}
		?>
		</select>
		<label for="<?php echo $this->get_field_id('orderby'); ?>">Sort by:</label>
		<select name="<?php echo $this->get_field_name('orderby'); ?>" id="<?php echo $this->get_field_id('orderby'); ?>" class="widefat">
			<option value="name"<?php selected( $instance['orderby'], 'name' ); ?>>Link title</option>
			<option value="rating"<?php selected( $instance['orderby'], 'rating' ); ?>>Link rating</option>
			<option value="id"<?php selected( $instance['orderby'], 'id' ); ?>>Link ID</option>
			<option value="rand"<?php selected( $instance['orderby'], 'rand' ); ?>>Random</option>
		</select>
		</p>
		<p>
		<input class="checkbox" type="checkbox"<?php checked($instance['images'], true) ?> id="<?php echo $this->get_field_id('images'); ?>" name="<?php echo $this->get_field_name('images'); ?>" />
		<label for="<?php echo $this->get_field_id('images'); ?>">Show Link Image</label><br />
		<input class="checkbox" type="checkbox"<?php checked($instance['name'], true) ?> id="<?php echo $this->get_field_id('name'); ?>" name="<?php echo $this->get_field_name('name'); ?>" />
		<label for="<?php echo $this->get_field_id('name'); ?>">Show Link Name</label><br />
		<input class="checkbox" type="checkbox"<?php checked($instance['description'], true) ?> id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" />
		<label for="<?php echo $this->get_field_id('description'); ?>">Show Link Description</label><br />
		<input class="checkbox" type="checkbox"<?php checked($instance['rating'], true) ?> id="<?php echo $this->get_field_id('rating'); ?>" name="<?php echo $this->get_field_name('rating'); ?>" />
		<label for="<?php echo $this->get_field_id('rating'); ?>">Show Link Rating</label>
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('limit'); ?>">Number of links to show:</label>
		<input id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit == -1 ? '' : intval( $limit ); ?>" size="3" />
		</p>
		<?php
	}
}
