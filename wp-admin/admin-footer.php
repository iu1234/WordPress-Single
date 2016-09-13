<?php
/**
 * WordPress Administration Template Footer
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( !defined('ABSPATH') )	die('-1');
?>

<div class="clear"></div></div>
<div class="clear"></div></div>
<div class="clear"></div></div>

<div id="wpfooter" role="contentinfo">
	<?php
	do_action( 'in_admin_footer' );
	?>
	<div class="clear"></div>
</div>
<?php

do_action( 'admin_footer', '' );
do_action( 'admin_print_footer_scripts' );
do_action( "admin_footer-" . $GLOBALS['hook_suffix'] );

if ( function_exists('get_site_option') ) {
	if ( false === get_site_option('can_compress_scripts') )
		compression_test();
}

?>

<div class="clear"></div></div>
<script type="text/javascript">if(typeof wpOnload=='function')wpOnload();</script>
</body>
</html>
