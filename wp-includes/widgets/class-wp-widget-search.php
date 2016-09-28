<?php
/**
 * Widget API: WP_Widget_Search class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.4.0
 */

class WP_Widget_Search extends WP_Widget {

	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_search',
			'description' => 'A search form for your site.',
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'search', 'Search', $widget_ops );
	}

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		get_search_form();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = $instance['title'];
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args((array) $new_instance, array( 'title' => ''));
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		return $instance;
	}

}
