<?php

class WP_Locale {

	public $weekday;

	public $weekday_initial;

	public $weekday_abbrev;

	public $start_of_week;

	public $month;

	public $month_abbrev;

	public $meridiem;

	public $number_format;
	
	public function __construct() {
		$this->init();
		$this->register_globals();
	}

	public function init() {
		$this->weekday[0] = 'Sunday';
		$this->weekday[1] = 'Monday';
		$this->weekday[2] = 'Tuesday';
		$this->weekday[3] = 'Wednesday';
		$this->weekday[4] = 'Thursday';
		$this->weekday[5] = 'Friday';
		$this->weekday[6] = 'Saturday';
		$this->weekday_initial[ 'Sunday' ]    = 'S';
		$this->weekday_initial[ 'Monday' ]    = 'M';
		$this->weekday_initial[ 'Tuesday' ]   = 'T';
		$this->weekday_initial[ 'Wednesday' ] = 'W';
		$this->weekday_initial[ 'Thursday' ]  = 'T';
		$this->weekday_initial[ 'Friday' ]    = 'F';
		$this->weekday_initial[ 'Saturday' ]  = 'S';
		$this->weekday_abbrev['Sunday']    = 'Sun';
		$this->weekday_abbrev['Monday']    = 'Mon';
		$this->weekday_abbrev['Tuesday']   = 'Tue';
		$this->weekday_abbrev['Wednesday'] = 'Wed';
		$this->weekday_abbrev['Thursday']  = 'Thu';
		$this->weekday_abbrev['Friday']    = 'Fri';
		$this->weekday_abbrev['Saturday']  = 'Sat';
		$this->month['01'] = 'January';
		$this->month['02'] = 'February';
		$this->month['03'] = 'March';
		$this->month['04'] = 'April';
		$this->month['05'] = 'May';
		$this->month['06'] = 'June';
		$this->month['07'] = 'July';
		$this->month['08'] = 'August';
		$this->month['09'] = 'September';
		$this->month['10'] = 'October';
		$this->month['11'] = 'November';
		$this->month['12'] = 'December';
		$this->month_genitive['01'] = 'January';
		$this->month_genitive['02'] = 'February';
		$this->month_genitive['03'] = 'March';
		$this->month_genitive['04'] = 'April';
		$this->month_genitive['05'] = 'May';
		$this->month_genitive['06'] = 'June';
		$this->month_genitive['07'] = 'July';
		$this->month_genitive['08'] = 'August';
		$this->month_genitive['09'] = 'September';
		$this->month_genitive['10'] = 'October';
		$this->month_genitive['11'] = 'November';
		$this->month_genitive['12'] = 'December';
		$this->month_abbrev[ 'January' ]   = 'Jan';
		$this->month_abbrev[ 'February' ]  = 'Feb';
		$this->month_abbrev[ 'March' ]     = 'Mar';
		$this->month_abbrev[ 'April' ]     = 'Apr';
		$this->month_abbrev[ 'May' ]       = 'May';
		$this->month_abbrev[ 'June' ]      = 'Jun';
		$this->month_abbrev[ 'July' ]      = 'Jul';
		$this->month_abbrev[ 'August' ]    = 'Aug';
		$this->month_abbrev[ 'September' ] = 'Sep';
		$this->month_abbrev[ 'October' ]   = 'Oct';
		$this->month_abbrev[ 'November' ]  = 'Nov';
		$this->month_abbrev[ 'December' ]  = 'Dec';
		$this->meridiem['am'] = 'am';
		$this->meridiem['pm'] = 'pm';
		$this->meridiem['AM'] = 'AM';
		$this->meridiem['PM'] = 'PM';
		$thousands_sep = 'number_format_thousands_sep';
		$thousands_sep = str_replace( ' ', '&nbsp;', $thousands_sep );
		$this->number_format['thousands_sep'] = ( 'number_format_thousands_sep' === $thousands_sep ) ? ',' : $thousands_sep;
		$decimal_point = 'number_format_decimal_point';
		$this->number_format['decimal_point'] = ( 'number_format_decimal_point' === $decimal_point ) ? '.' : $decimal_point;

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

	public function _strings_for_pot() {
		'F j, Y';
		'g:i a';
		'F j, Y g:i a';
	}
}
