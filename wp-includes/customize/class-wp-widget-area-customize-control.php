<?php

class WP_Widget_Area_Customize_Control extends WP_Customize_Control {

	public $type = 'sidebar_widgets';

	public $sidebar_id;

	public function to_json() {
		parent::to_json();
		$exported_properties = array( 'sidebar_id' );
		foreach ( $exported_properties as $key ) {
			$this->json[ $key ] = $this->$key;
		}
	}

	public function render_content() {
		$id = 'reorder-widgets-desc-' . str_replace( array( '[', ']' ), array( '-', '' ), $this->id );
		?>
		<button type="button" class="button-secondary add-new-widget" aria-expanded="false" aria-controls="available-widgets">
			<?php _e( 'Add a Widget' ); ?>
		</button>
		<button type="button" class="button-link reorder-toggle" aria-label="Reorder widgets" aria-describedby="<?php echo esc_attr( $id ); ?>">
			<span class="reorder">Reorder</span>
			<span class="reorder-done">Done</span>
		</button>
		<p class="screen-reader-text" id="<?php echo esc_attr( $id ); ?>">When in reorder mode, additional controls to reorder widgets will be available in the widgets list above.</p>
		<?php
	}
}
