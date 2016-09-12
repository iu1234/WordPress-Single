<?php
/**
 * Date and Time Locale object
 *
 * @package WordPress
 * @subpackage i18n
 */

class WP_Locale {

	public $weekday;

	public $weekday_initial;

	public $weekday_abbrev;

	public $start_of_week;

	public $month;

	public $month_abbrev;

	public $meridiem;

	public $text_direction = 'ltr';

	public $number_format;

	public function init() {

		$this->weekday[0] = 'Sunday';
		$this->weekday[1] = 'Monday';
		$this->weekday[2] = 'Tuesday';
		$this->weekday[3] = 'Wednesday';
		$this->weekday[4] = 'Thursday';
		$this->weekday[5] = 'Friday';
		$this->weekday[6] = 'Saturday';

		$this->weekday_initial[ 'Sunday' ]    = /* translators: one-letter abbreviation of the weekday */ _x( 'S', 'Sunday initial' );
		$this->weekday_initial[ 'Monday' ]    = /* translators: one-letter abbreviation of the weekday */ _x( 'M', 'Monday initial' );
		$this->weekday_initial[ 'Tuesday' ]   = /* translators: one-letter abbreviation of the weekday */ _x( 'T', 'Tuesday initial' );
		$this->weekday_initial[ 'Wednesday' ] = /* translators: one-letter abbreviation of the weekday */ _x( 'W', 'Wednesday initial' );
		$this->weekday_initial[ 'Thursday' ]  = /* translators: one-letter abbreviation of the weekday */ _x( 'T', 'Thursday initial' );
		$this->weekday_initial[ 'Friday' ]    = /* translators: one-letter abbreviation of the weekday */ _x( 'F', 'Friday initial' );
		$this->weekday_initial[ 'Saturday' ]  = /* translators: one-letter abbreviation of the weekday */ _x( 'S', 'Saturday initial' );

		$this->weekday_abbrev[__('Sunday')]    = /* translators: three-letter abbreviation of the weekday */ __('Sun');
		$this->weekday_abbrev[__('Monday')]    = /* translators: three-letter abbreviation of the weekday */ __('Mon');
		$this->weekday_abbrev[__('Tuesday')]   = /* translators: three-letter abbreviation of the weekday */ __('Tue');
		$this->weekday_abbrev[__('Wednesday')] = /* translators: three-letter abbreviation of the weekday */ __('Wed');
		$this->weekday_abbrev[__('Thursday')]  = /* translators: three-letter abbreviation of the weekday */ __('Thu');
		$this->weekday_abbrev[__('Friday')]    = /* translators: three-letter abbreviation of the weekday */ __('Fri');
		$this->weekday_abbrev[__('Saturday')]  = /* translators: three-letter abbreviation of the weekday */ __('Sat');

		// The Months
		$this->month['01'] = /* translators: month name */ __( 'January' );
		$this->month['02'] = /* translators: month name */ __( 'February' );
		$this->month['03'] = /* translators: month name */ __( 'March' );
		$this->month['04'] = /* translators: month name */ __( 'April' );
		$this->month['05'] = /* translators: month name */ __( 'May' );
		$this->month['06'] = /* translators: month name */ __( 'June' );
		$this->month['07'] = /* translators: month name */ __( 'July' );
		$this->month['08'] = /* translators: month name */ __( 'August' );
		$this->month['09'] = /* translators: month name */ __( 'September' );
		$this->month['10'] = /* translators: month name */ __( 'October' );
		$this->month['11'] = /* translators: month name */ __( 'November' );
		$this->month['12'] = /* translators: month name */ __( 'December' );

		// The Months, genitive
		$this->month_genitive['01'] = _x( 'January', 'genitive' );
		$this->month_genitive['02'] = /* translators: month name, genitive */ _x( 'February', 'genitive' );
		$this->month_genitive['03'] = /* translators: month name, genitive */ _x( 'March', 'genitive' );
		$this->month_genitive['04'] = /* translators: month name, genitive */ _x( 'April', 'genitive' );
		$this->month_genitive['05'] = /* translators: month name, genitive */ _x( 'May', 'genitive' );
		$this->month_genitive['06'] = /* translators: month name, genitive */ _x( 'June', 'genitive' );
		$this->month_genitive['07'] = /* translators: month name, genitive */ _x( 'July', 'genitive' );
		$this->month_genitive['08'] = /* translators: month name, genitive */ _x( 'August', 'genitive' );
		$this->month_genitive['09'] = /* translators: month name, genitive */ _x( 'September', 'genitive' );
		$this->month_genitive['10'] = /* translators: month name, genitive */ _x( 'October', 'genitive' );
		$this->month_genitive['11'] = /* translators: month name, genitive */ _x( 'November', 'genitive' );
		$this->month_genitive['12'] = /* translators: month name, genitive */ _x( 'December', 'genitive' );

		$this->month_abbrev[ 'January' ]   = /* translators: three-letter abbreviation of the month */ _x( 'Jan', 'January abbreviation' );
		$this->month_abbrev[ 'February' ]  = /* translators: three-letter abbreviation of the month */ _x( 'Feb', 'February abbreviation' );
		$this->month_abbrev[ 'March' ]     = /* translators: three-letter abbreviation of the month */ _x( 'Mar', 'March abbreviation' );
		$this->month_abbrev[ 'April' ]     = /* translators: three-letter abbreviation of the month */ _x( 'Apr', 'April abbreviation' );
		$this->month_abbrev[ 'May' ]       = /* translators: three-letter abbreviation of the month */ _x( 'May', 'May abbreviation' );
		$this->month_abbrev[ 'June' ]      = /* translators: three-letter abbreviation of the month */ _x( 'Jun', 'June abbreviation' );
		$this->month_abbrev[ 'July' ]      = /* translators: three-letter abbreviation of the month */ _x( 'Jul', 'July abbreviation' );
		$this->month_abbrev[ 'August' ]    = /* translators: three-letter abbreviation of the month */ _x( 'Aug', 'August abbreviation' );
		$this->month_abbrev[ 'September' ] = /* translators: three-letter abbreviation of the month */ _x( 'Sep', 'September abbreviation' );
		$this->month_abbrev[ 'October' ]   = /* translators: three-letter abbreviation of the month */ _x( 'Oct', 'October abbreviation' );
		$this->month_abbrev[ 'November' ]  = /* translators: three-letter abbreviation of the month */ _x( 'Nov', 'November abbreviation' );
		$this->month_abbrev[ 'December' ]  = /* translators: three-letter abbreviation of the month */ _x( 'Dec', 'December abbreviation' );

		$this->meridiem['am'] = 'am';
		$this->meridiem['pm'] = 'pm';
		$this->meridiem['AM'] = 'AM';
		$this->meridiem['PM'] = 'PM';

		$thousands_sep = 'number_format_thousands_sep';

		$thousands_sep = str_replace( ' ', '&nbsp;', $thousands_sep );

		$this->number_format['thousands_sep'] = ( 'number_format_thousands_sep' === $thousands_sep ) ? ',' : $thousands_sep;

		$decimal_point = 'number_format_decimal_point';

		$this->number_format['decimal_point'] = ( 'number_format_decimal_point' === $decimal_point ) ? '.' : $decimal_point;

		if ( isset( $GLOBALS['text_direction'] ) )
			$this->text_direction = $GLOBALS['text_direction'];
		elseif ( 'rtl' == _x( 'ltr', 'text direction' ) )
			$this->text_direction = 'rtl';

		if ( 'rtl' === $this->text_direction && strpos( $GLOBALS['wp_version'], '-src' ) ) {
			$this->text_direction = 'ltr';
			add_action( 'all_admin_notices', array( $this, 'rtl_src_admin_notice' ) );
		}
	}

	public function rtl_src_admin_notice() {
		echo '<div class="error"><p>' . sprintf( 'The %s directory of the develop repository must be used for RTL.', '<code>build</code>' ) . '</p></div>';
	}

	public function get_weekday($weekday_number) {
		return $this->weekday[$weekday_number];
	}

	public function get_weekday_initial($weekday_name) {
		return $this->weekday_initial[$weekday_name];
	}

	public function get_weekday_abbrev($weekday_name) {
		return $this->weekday_abbrev[$weekday_name];
	}

	public function get_month($month_number) {
		return $this->month[zeroise($month_number, 2)];
	}

	public function get_month_abbrev($month_name) {
		return $this->month_abbrev[$month_name];
	}

	public function get_meridiem($meridiem) {
		return $this->meridiem[$meridiem];
	}

	public function register_globals() {
		$GLOBALS['weekday']         = $this->weekday;
		$GLOBALS['weekday_initial'] = $this->weekday_initial;
		$GLOBALS['weekday_abbrev']  = $this->weekday_abbrev;
		$GLOBALS['month']           = $this->month;
		$GLOBALS['month_abbrev']    = $this->month_abbrev;
	}

	public function __construct() {
		$this->init();
		$this->register_globals();
	}

	public function _strings_for_pot() {
		'F j, Y';
		'g:i a';
		'F j, Y g:i a';
	}
}
