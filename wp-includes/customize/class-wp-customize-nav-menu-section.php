<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Section class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menu_Section extends WP_Customize_Section {

	public $type = 'nav_menu';

	public function json() {
		$exported = parent::json();
		$exported['menu_id'] = intval( preg_replace( '/^nav_menu\[(\d+)\]/', '$1', $this->id ) );
		return $exported;
	}
}
