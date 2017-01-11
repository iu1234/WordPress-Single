<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Location_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menu_Location_Control extends WP_Customize_Control {

	public $type = 'nav_menu_location';

	public $location_id = '';

	public function to_json() {
		parent::to_json();
		$this->json['locationId'] = $this->location_id;
	}

	public function render_content() {
		if ( empty( $this->choices ) ) {
			return;
		}
		?>
		<label>
			<?php if ( ! empty( $this->label ) ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $this->description ) ) : ?>
			<span class="description customize-control-description"><?php echo $this->description; ?></span>
			<?php endif; ?>
			<select <?php $this->link(); ?>>
				<?php
				foreach ( $this->choices as $value => $label ) :
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
				endforeach;
				?>
			</select>
		</label>
		<?php
	}
}
