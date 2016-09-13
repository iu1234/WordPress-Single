<?php
/**
 * WordPress scripts and styles default loader.
 *
 * Several constants are used to manage the loading, concatenating and compression of scripts and CSS:
 * define('SCRIPT_DEBUG', true); loads the development (non-minified) versions of all scripts and CSS, and disables compression and concatenation,
 * define('CONCATENATE_SCRIPTS', false); disables compression and concatenation of scripts and CSS,
 * define('COMPRESS_SCRIPTS', false); disables compression of scripts,
 * define('COMPRESS_CSS', false); disables compression of CSS,
 * define('ENFORCE_GZIP', true); forces gzip for compression (default is deflate).
 *
 * The globals $concatenate_scripts, $compress_scripts and $compress_css can be set by plugins
 * to temporarily override the above settings. Also a compression test is run once and the result is saved
 * as option 'can_compress_scripts' (0/1). The test will run again if that option is deleted.
 *
 * @package WordPress
 */

require( ABSPATH . WPINC . '/class.wp-dependencies.php' );
require( ABSPATH . WPINC . '/class.wp-scripts.php' );
require( ABSPATH . WPINC . '/functions.wp-scripts.php' );
require( ABSPATH . WPINC . '/class.wp-styles.php' );
require( ABSPATH . WPINC . '/functions.wp-styles.php' );

function wp_default_scripts( &$scripts ) {
	if ( ! $guessurl = site_url() ) {
		$guessed_url = true;
		$guessurl = wp_guess_url();
	}

	$scripts->base_url = $guessurl;
	$scripts->content_url = defined('WP_CONTENT_URL')? WP_CONTENT_URL : '';
	$scripts->default_version = get_bloginfo( 'version' );
	$scripts->default_dirs = array('/wp-admin/js/', '/wp-includes/js/');

	$scripts->add( 'utils', "/wp-includes/js/utils.js" );
	did_action( 'init' ) && $scripts->localize( 'utils', 'userSettings', array(
		'url' => (string) SITECOOKIEPATH,
		'uid' => (string) get_current_user_id(),
		'time' => (string) time(),
		'secure' => (string) ( 'https' === parse_url( site_url(), PHP_URL_SCHEME ) ),
	) );

	$scripts->add( 'common', "/wp-admin/js/common.js", array('jquery', 'hoverIntent', 'utils'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'common', 'commonL10n', array(
		'warnDelete' => "You are about to permanently delete these items.\n  'Cancel' to stop, 'OK' to delete.",
		'dismiss'    => 'Dismiss this notice.',
	) );
	$scripts->add( 'wp-a11y', "/wp-includes/js/wp-a11y.js", array( 'jquery' ), false, 1 );
	$scripts->add( 'sack', "/wp-includes/js/tw-sack.js", array(), '1.6.1', 1 );
	$scripts->add( 'quicktags', "/wp-includes/js/quicktags.js", array(), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'quicktags', 'quicktagsL10n', array(
		'closeAllOpenTags'      => __( 'Close all open tags' ),
		'closeTags'             => __( 'close tags' ),
		'enterURL'              => __( 'Enter the URL' ),
		'enterImageURL'         => __( 'Enter the URL of the image' ),
		'enterImageDescription' => __( 'Enter a description of the image' ),
		'textdirection'         => __( 'text direction' ),
		'toggleTextdirection'   => __( 'Toggle Editor Text Direction' ),
		'dfw'                   => __( 'Distraction-free writing mode' ),
		'strong'          => __( 'Bold' ),
		'strongClose'     => __( 'Close bold tag' ),
		'em'              => __( 'Italic' ),
		'emClose'         => __( 'Close italic tag' ),
		'link'            => __( 'Insert link' ),
		'blockquote'      => __( 'Blockquote' ),
		'blockquoteClose' => __( 'Close blockquote tag' ),
		'del'             => __( 'Deleted text (strikethrough)' ),
		'delClose'        => __( 'Close deleted text tag' ),
		'ins'             => __( 'Inserted text' ),
		'insClose'        => __( 'Close inserted text tag' ),
		'image'           => __( 'Insert image' ),
		'ul'              => __( 'Bulleted list' ),
		'ulClose'         => __( 'Close bulleted list tag' ),
		'ol'              => __( 'Numbered list' ),
		'olClose'         => __( 'Close numbered list tag' ),
		'li'              => __( 'List item' ),
		'liClose'         => __( 'Close list item tag' ),
		'code'            => __( 'Code' ),
		'codeClose'       => __( 'Close code tag' ),
		'more'            => __( 'Insert Read More tag' ),
	) );

	$scripts->add( 'colorpicker', "/wp-includes/js/colorpicker.js", array('prototype'), '3517m' );
	$scripts->add( 'editor', "/wp-admin/js/editor.js", array('utils','jquery'), false, 1 );
	$scripts->add( 'wp-fullscreen-stub', "/wp-admin/js/wp-fullscreen-stub.js", array(), false, 1 );
	$scripts->add( 'wp-ajax-response', "/wp-includes/js/wp-ajax-response.js", array('jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-ajax-response', 'wpAjax', array(
		'noPerm' => 'You do not have permission to do that.',
		'broken' => 'An unidentified error has occurred.'
	) );

	$scripts->add( 'wp-pointer', "/wp-includes/js/wp-pointer.js", array( 'jquery-ui-widget', 'jquery-ui-position' ), '20111129a', 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-pointer', 'wpPointerL10n', array(
		'dismiss' => 'Dismiss',
	) );
	$scripts->add( 'autosave', "/wp-includes/js/autosave.js", array('heartbeat'), false, 1 );
	$scripts->add( 'heartbeat', "/wp-includes/js/heartbeat.js", array('jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'heartbeat', 'heartbeatSettings',
		apply_filters( 'heartbeat_settings', array() )
	);

	$scripts->add( 'wp-auth-check', "/wp-includes/js/wp-auth-check.js", array('heartbeat'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-auth-check', 'authcheckL10n', array(
		'beforeunload' => 'Your session has expired. You can log in again from this page or go to the login page.',
		'interval' => apply_filters( 'wp_auth_check_interval', 3 * MINUTE_IN_SECONDS ),
	) );

	$scripts->add( 'wp-lists', "/wp-includes/js/wp-lists.js", array( 'wp-ajax-response', 'jquery-color' ), false, 1 );

	$scripts->add( 'prototype', 'https://ajax.googleapis.com/ajax/libs/prototype/1.7.1.0/prototype.js', array(), '1.7.1');
	$scripts->add( 'scriptaculous-root', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/scriptaculous.js', array('prototype'), '1.9.0');
	$scripts->add( 'scriptaculous-builder', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/builder.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous-dragdrop', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/dragdrop.js', array('scriptaculous-builder', 'scriptaculous-effects'), '1.9.0');
	$scripts->add( 'scriptaculous-effects', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/effects.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous-slider', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/slider.js', array('scriptaculous-effects'), '1.9.0');
	$scripts->add( 'scriptaculous-sound', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/sound.js', array( 'scriptaculous-root' ), '1.9.0' );
	$scripts->add( 'scriptaculous-controls', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/controls.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous', false, array('scriptaculous-dragdrop', 'scriptaculous-slider', 'scriptaculous-controls') );

	$scripts->add( 'cropper', '/wp-includes/js/crop/cropper.js', array('scriptaculous-dragdrop') );
	$scripts->add( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '1.12.4' );
	$scripts->add( 'jquery-core', '/wp-includes/js/jquery/jquery.js', array(), '1.12.4' );
	$scripts->add( 'jquery-migrate', "/wp-includes/js/jquery/jquery-migrate.js", array(), '1.4.1' );

	$scripts->add( 'jquery-ui-core', "/wp-includes/js/jquery/ui/core.js", array('jquery'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-core', "/wp-includes/js/jquery/ui/effect.js", array('jquery'), '1.11.4', 1 );

	$scripts->add( 'jquery-effects-blind', "/wp-includes/js/jquery/ui/effect-blind.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-bounce', "/wp-includes/js/jquery/ui/effect-bounce.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-clip', "/wp-includes/js/jquery/ui/effect-clip.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-drop', "/wp-includes/js/jquery/ui/effect-drop.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-explode', "/wp-includes/js/jquery/ui/effect-explode.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-fade', "/wp-includes/js/jquery/ui/effect-fade.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-fold', "/wp-includes/js/jquery/ui/effect-fold.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-highlight', "/wp-includes/js/jquery/ui/effect-highlight.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-puff', "/wp-includes/js/jquery/ui/effect-puff.js", array('jquery-effects-core', 'jquery-effects-scale'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-pulsate', "/wp-includes/js/jquery/ui/effect-pulsate.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-scale', "/wp-includes/js/jquery/ui/effect-scale.js", array('jquery-effects-core', 'jquery-effects-size'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-shake', "/wp-includes/js/jquery/ui/effect-shake.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-size', "/wp-includes/js/jquery/ui/effect-size.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-slide', "/wp-includes/js/jquery/ui/effect-slide.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-transfer', "/wp-includes/js/jquery/ui/effect-transfer.js", array('jquery-effects-core'), '1.11.4', 1 );

	$scripts->add( 'jquery-ui-accordion', "/wp-includes/js/jquery/ui/accordion.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-autocomplete', "/wp-includes/js/jquery/ui/autocomplete.js", array( 'jquery-ui-menu', 'wp-a11y' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-button', "/wp-includes/js/jquery/ui/button.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-datepicker', "/wp-includes/js/jquery/ui/datepicker.js", array('jquery-ui-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-dialog', "/wp-includes/js/jquery/ui/dialog.js", array('jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-button', 'jquery-ui-position'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-draggable', "/wp-includes/js/jquery/ui/draggable.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-droppable', "/wp-includes/js/jquery/ui/droppable.js", array('jquery-ui-draggable'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-menu', "/wp-includes/js/jquery/ui/menu.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-mouse', "/wp-includes/js/jquery/ui/mouse.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-position', "/wp-includes/js/jquery/ui/position.js", array('jquery'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-progressbar', "/wp-includes/js/jquery/ui/progressbar.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-resizable', "/wp-includes/js/jquery/ui/resizable.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-selectable', "/wp-includes/js/jquery/ui/selectable.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-selectmenu', "/wp-includes/js/jquery/ui/selectmenu.js", array('jquery-ui-menu'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-slider', "/wp-includes/js/jquery/ui/slider.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-sortable', "/wp-includes/js/jquery/ui/sortable.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-spinner', "/wp-includes/js/jquery/ui/spinner.js", array( 'jquery-ui-button' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-tabs', "/wp-includes/js/jquery/ui/tabs.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-tooltip', "/wp-includes/js/jquery/ui/tooltipx.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-widget', "/wp-includes/js/jquery/ui/widget.js", array('jquery'), '1.11.4', 1 );

	// Strings for 'jquery-ui-autocomplete' live region messages
	did_action( 'init' ) && $scripts->localize( 'jquery-ui-autocomplete', 'uiAutocompleteL10n', array(
		'noResults' => __( 'No search results.' ),
		/* translators: Number of results found when using jQuery UI Autocomplete */
		'oneResult' => __( '1 result found. Use up and down arrow keys to navigate.' ),
		'manyResults' => __( '%d results found. Use up and down arrow keys to navigate.' ),
	) );

	$scripts->add( 'jquery-form', "/wp-includes/js/jquery/jquery.form.js", array('jquery'), '3.37.0', 1 );
	$scripts->add( 'jquery-color', "/wp-includes/js/jquery/jquery.color.min.js", array('jquery'), '2.1.1', 1 );
	$scripts->add( 'suggest', "/wp-includes/js/jquery/suggest.js", array('jquery'), '1.1-20110113', 1 );
	$scripts->add( 'schedule', '/wp-includes/js/jquery/jquery.schedule.js', array('jquery'), '20m', 1 );
	$scripts->add( 'jquery-query', "/wp-includes/js/jquery/jquery.query.js", array('jquery'), '2.1.7', 1 );
	$scripts->add( 'jquery-serialize-object', "/wp-includes/js/jquery/jquery.serialize-object.js", array('jquery'), '0.2', 1 );
	$scripts->add( 'jquery-hotkeys', "/wp-includes/js/jquery/jquery.hotkeys.js", array('jquery'), '0.0.2m', 1 );
	$scripts->add( 'jquery-table-hotkeys', "/wp-includes/js/jquery/jquery.table-hotkeys.js", array('jquery', 'jquery-hotkeys'), false, 1 );
	$scripts->add( 'jquery-touch-punch', "/wp-includes/js/jquery/jquery.ui.touch-punch.js", array('jquery-ui-widget', 'jquery-ui-mouse'), '0.2.2', 1 );
	$scripts->add( 'masonry', "/wp-includes/js/masonry.min.js", array(), '3.1.2', 1 );
	$scripts->add( 'jquery-masonry', "/wp-includes/js/jquery/jquery.masonry.js", array( 'jquery', 'masonry' ), '3.1.2', 1 );

	$scripts->add( 'thickbox', "/wp-includes/js/thickbox/thickbox.js", array('jquery'), '3.1-20121105', 1 );
	did_action( 'init' ) && $scripts->localize( 'thickbox', 'thickboxL10n', array(
		'next' => __('Next &gt;'),
		'prev' => __('&lt; Prev'),
		'image' => __('Image'),
		'of' => __('of'),
		'close' => __('Close'),
		'noiframes' => __('This feature requires inline frames. You have iframes disabled or your browser does not support them.'),
		'loadingAnimation' => includes_url('js/thickbox/loadingAnimation.gif'),
	) );

	$scripts->add( 'jcrop', "/wp-includes/js/jcrop/jquery.Jcrop.min.js", array('jquery'), '0.9.12');

	$scripts->add( 'swfobject', "/wp-includes/js/swfobject.js", array(), '2.2-20120417');

	// error message for both plupload and swfupload
	$uploader_l10n = array(
		'queue_limit_exceeded' => __('You have attempted to queue too many files.'),
		'file_exceeds_size_limit' => __('%s exceeds the maximum upload size for this site.'),
		'zero_byte_file' => __('This file is empty. Please try another.'),
		'invalid_filetype' => __('This file type is not allowed. Please try another.'),
		'not_an_image' => __('This file is not an image. Please try another.'),
		'image_memory_exceeded' => __('Memory exceeded. Please try another smaller file.'),
		'image_dimensions_exceeded' => __('This is larger than the maximum size. Please try another.'),
		'default_error' => __('An error occurred in the upload. Please try again later.'),
		'missing_upload_url' => __('There was a configuration error. Please contact the server administrator.'),
		'upload_limit_exceeded' => __('You may only upload 1 file.'),
		'http_error' => __('HTTP error.'),
		'upload_failed' => __('Upload failed.'),
		'big_upload_failed' => __('Please try uploading this file with the %1$sbrowser uploader%2$s.'),
		'big_upload_queued' => __('%s exceeds the maximum upload size for the multi-file uploader when used in your browser.'),
		'io_error' => __('IO error.'),
		'security_error' => __('Security error.'),
		'file_cancelled' => __('File canceled.'),
		'upload_stopped' => __('Upload stopped.'),
		'dismiss' => __('Dismiss'),
		'crunching' => __('Crunching&hellip;'),
		'deleted' => __('moved to the trash.'),
		'error_uploading' => __('&#8220;%s&#8221; has failed to upload.')
	);

	$scripts->add( 'plupload', '/wp-includes/js/plupload/plupload.full.min.js', array(), '2.1.8' );
	// Back compat handles:
	foreach ( array( 'all', 'html5', 'flash', 'silverlight', 'html4' ) as $handle ) {
		$scripts->add( "plupload-$handle", false, array( 'plupload' ), '2.1.1' );
	}

	$scripts->add( 'plupload-handlers', "/wp-includes/js/plupload/handlers.js", array( 'plupload', 'jquery' ) );
	did_action( 'init' ) && $scripts->localize( 'plupload-handlers', 'pluploadL10n', $uploader_l10n );

	$scripts->add( 'wp-plupload', "/wp-includes/js/plupload/wp-plupload$suffix.js", array( 'plupload', 'jquery', 'json2', 'media-models' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-plupload', 'pluploadL10n', $uploader_l10n );

	$scripts->add( 'swfupload', '/wp-includes/js/swfupload/swfupload.js', array(), '2201-20110113');
	$scripts->add( 'swfupload-swfobject', '/wp-includes/js/swfupload/plugins/swfupload.swfobject.js', array('swfupload', 'swfobject'), '2201a');
	$scripts->add( 'swfupload-queue', '/wp-includes/js/swfupload/plugins/swfupload.queue.js', array('swfupload'), '2201');
	$scripts->add( 'swfupload-speed', '/wp-includes/js/swfupload/plugins/swfupload.speed.js', array('swfupload'), '2201');
	$scripts->add( 'swfupload-all', false, array('swfupload', 'swfupload-swfobject', 'swfupload-queue'), '2201');
	$scripts->add( 'swfupload-handlers', "/wp-includes/js/swfupload/handlers$suffix.js", array('swfupload-all', 'jquery'), '2201-20110524');
	did_action( 'init' ) && $scripts->localize( 'swfupload-handlers', 'swfuploadL10n', $uploader_l10n );

	$scripts->add( 'comment-reply', "/wp-includes/js/comment-reply$suffix.js", array(), false, 1 );

	$scripts->add( 'json2', "/wp-includes/js/json2$suffix.js", array(), '2015-05-03' );
	did_action( 'init' ) && $scripts->add_data( 'json2', 'conditional', 'lt IE 8' );

	$scripts->add( 'underscore', "/wp-includes/js/underscore$dev_suffix.js", array(), '1.8.3', 1 );
	$scripts->add( 'backbone', "/wp-includes/js/backbone$dev_suffix.js", array( 'underscore','jquery' ), '1.2.3', 1 );

	$scripts->add( 'wp-util', "/wp-includes/js/wp-util$suffix.js", array('underscore', 'jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-util', '_wpUtilSettings', array(
		'ajax' => array(
			'url' => admin_url( 'admin-ajax.php', 'relative' ),
		),
	) );

	$scripts->add( 'wp-backbone', "/wp-includes/js/wp-backbone$suffix.js", array('backbone', 'wp-util'), false, 1 );

	$scripts->add( 'revisions', "/wp-admin/js/revisions$suffix.js", array( 'wp-backbone', 'jquery-ui-slider', 'hoverIntent' ), false, 1 );

	$scripts->add( 'imgareaselect', "/wp-includes/js/imgareaselect/jquery.imgareaselect$suffix.js", array('jquery'), false, 1 );

	$scripts->add( 'mediaelement', "/wp-includes/js/mediaelement/mediaelement-and-player.min.js", array('jquery'), '2.18.1-a', 1 );
	did_action( 'init' ) && $scripts->localize( 'mediaelement', 'mejsL10n', array(
		'language' => get_bloginfo( 'language' ),
		'strings'  => array(
			'Close'               => __( 'Close' ),
			'Fullscreen'          => __( 'Fullscreen' ),
			'Download File'       => __( 'Download File' ),
			'Download Video'      => __( 'Download Video' ),
			'Play/Pause'          => __( 'Play/Pause' ),
			'Mute Toggle'         => __( 'Mute Toggle' ),
			'None'                => __( 'None' ),
			'Turn off Fullscreen' => __( 'Turn off Fullscreen' ),
			'Go Fullscreen'       => __( 'Go Fullscreen' ),
			'Unmute'              => __( 'Unmute' ),
			'Mute'                => __( 'Mute' ),
			'Captions/Subtitles'  => __( 'Captions/Subtitles' )
		),
	) );


	$scripts->add( 'wp-mediaelement', "/wp-includes/js/mediaelement/wp-mediaelement$suffix.js", array('mediaelement'), false, 1 );
	$mejs_settings = array(
		'pluginPath' => includes_url( 'js/mediaelement/', 'relative' ),
	);
	did_action( 'init' ) && $scripts->localize( 'mediaelement', '_wpmejsSettings',
		apply_filters( 'mejs_settings', $mejs_settings )
	);

	$scripts->add( 'froogaloop',  "/wp-includes/js/mediaelement/froogaloop.min.js", array(), '2.0' );
	$scripts->add( 'wp-playlist', "/wp-includes/js/mediaelement/wp-playlist$suffix.js", array( 'wp-util', 'backbone', 'mediaelement' ), false, 1 );

	$scripts->add( 'zxcvbn-async', "/wp-includes/js/zxcvbn-async$suffix.js", array(), '1.0' );
	did_action( 'init' ) && $scripts->localize( 'zxcvbn-async', '_zxcvbnSettings', array(
		'src' => empty( $guessed_url ) ? includes_url( '/js/zxcvbn.min.js' ) : $scripts->base_url . '/wp-includes/js/zxcvbn.min.js',
	) );

	$scripts->add( 'password-strength-meter', "/wp-admin/js/password-strength-meter$suffix.js", array( 'jquery', 'zxcvbn-async' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'password-strength-meter', 'pwsL10n', array(
		'short'    => _x( 'Very weak', 'password strength' ),
		'bad'      => _x( 'Weak', 'password strength' ),
		'good'     => _x( 'Medium', 'password strength' ),
		'strong'   => _x( 'Strong', 'password strength' ),
		'mismatch' => _x( 'Mismatch', 'password mismatch' ),
	) );

	$scripts->add( 'user-profile', "/wp-admin/js/user-profile$suffix.js", array( 'jquery', 'password-strength-meter', 'wp-util' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'user-profile', 'userProfileL10n', array(
		'warn'     => __( 'Your new password has not been saved.' ),
		'show'     => __( 'Show' ),
		'hide'     => __( 'Hide' ),
		'cancel'   => __( 'Cancel' ),
		'ariaShow' => esc_attr__( 'Show password' ),
		'ariaHide' => esc_attr__( 'Hide password' ),
	) );

	$scripts->add( 'language-chooser', "/wp-admin/js/language-chooser$suffix.js", array( 'jquery' ), false, 1 );

	$scripts->add( 'user-suggest', "/wp-admin/js/user-suggest$suffix.js", array( 'jquery-ui-autocomplete' ), false, 1 );

	$scripts->add( 'admin-bar', "/wp-includes/js/admin-bar$suffix.js", array(), false, 1 );

	$scripts->add( 'wplink', "/wp-includes/js/wplink$suffix.js", array( 'jquery', 'wp-a11y' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wplink', 'wpLinkL10n', array(
		'title' => __('Insert/edit link'),
		'update' => __('Update'),
		'save' => __('Add Link'),
		'noTitle' => __('(no title)'),
		'noMatchesFound' => __('No results found.'),
		'linkSelected' => __( 'Link selected.' ),
		'linkInserted' => __( 'Link inserted.' ),
	) );

	$scripts->add( 'wpdialogs', "/wp-includes/js/wpdialog$suffix.js", array( 'jquery-ui-dialog' ), false, 1 );

	$scripts->add( 'word-count', "/wp-admin/js/word-count$suffix.js", array(), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'word-count', 'wordCountL10n', array(
		/*
		 * translators: If your word count is based on single characters (e.g. East Asian characters),
		 * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
		 * Do not translate into your own language.
		 */
		'type' => _x( 'words', 'Word count type. Do not translate!' ),
		'shortcodes' => ! empty( $GLOBALS['shortcode_tags'] ) ? array_keys( $GLOBALS['shortcode_tags'] ) : array()
	) );

	$scripts->add( 'media-upload', "/wp-admin/js/media-upload$suffix.js", array( 'thickbox', 'shortcode' ), false, 1 );

	$scripts->add( 'hoverIntent', "/wp-includes/js/hoverIntent$suffix.js", array('jquery'), '1.8.1', 1 );

	$scripts->add( 'customize-base',     "/wp-includes/js/customize-base.js",     array( 'jquery', 'json2', 'underscore' ), false, 1 );
	$scripts->add( 'customize-loader',   "/wp-includes/js/customize-loader.js",   array( 'customize-base' ), false, 1 );
	$scripts->add( 'customize-preview',  "/wp-includes/js/customize-preview.js",  array( 'customize-base' ), false, 1 );
	$scripts->add( 'customize-models',   "/wp-includes/js/customize-models.js", array( 'underscore', 'backbone' ), false, 1 );
	$scripts->add( 'customize-views',    "/wp-includes/js/customize-views.js",  array( 'jquery', 'underscore', 'imgareaselect', 'customize-models', 'media-editor', 'media-views' ), false, 1 );
	$scripts->add( 'customize-controls', "/wp-admin/js/customize-controls$suffix.js", array( 'customize-base', 'wp-a11y' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'customize-controls', '_wpCustomizeControlsL10n', array(
		'activate'           => __( 'Save &amp; Activate' ),
		'save'               => __( 'Save &amp; Publish' ),
		'saveAlert'          => __( 'The changes you made will be lost if you navigate away from this page.' ),
		'saved'              => __( 'Saved' ),
		'cancel'             => __( 'Cancel' ),
		'close'              => __( 'Close' ),
		'cheatin'            => __( 'Cheatin&#8217; uh?' ),
		'notAllowed'         => __( 'You are not allowed to customize the appearance of this site.' ),
		'previewIframeTitle' => __( 'Site Preview' ),
		'loginIframeTitle'   => __( 'Session expired' ),
		'collapseSidebar'    => __( 'Collapse Sidebar' ),
		'expandSidebar'      => __( 'Expand Sidebar' ),
		'untitledBlogName'   => __( '(Untitled)' ),
		// Used for overriding the file types allowed in plupload.
		'allowedFiles'       => __( 'Allowed Files' ),
	) );
	$scripts->add( 'customize-selective-refresh', "/wp-includes/js/customize-selective-refresh.js", array( 'jquery', 'wp-util', 'customize-preview' ), false, 1 );

	$scripts->add( 'customize-widgets', "/wp-admin/js/customize-widgets.js", array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-droppable', 'wp-backbone', 'customize-controls' ), false, 1 );
	$scripts->add( 'customize-preview-widgets', "/wp-includes/js/customize-preview-widgets$suffix.js", array( 'jquery', 'wp-util', 'customize-preview', 'customize-selective-refresh' ), false, 1 );

	$scripts->add( 'customize-nav-menus', "/wp-admin/js/customize-nav-menus$suffix.js", array( 'jquery', 'wp-backbone', 'customize-controls', 'accordion', 'nav-menu' ), false, 1 );
	$scripts->add( 'customize-preview-nav-menus', "/wp-includes/js/customize-preview-nav-menus$suffix.js", array( 'jquery', 'wp-util', 'customize-preview', 'customize-selective-refresh' ), false, 1 );

	$scripts->add( 'accordion', "/wp-admin/js/accordion$suffix.js", array( 'jquery' ), false, 1 );

	$scripts->add( 'shortcode', "/wp-includes/js/shortcode$suffix.js", array( 'underscore' ), false, 1 );
	$scripts->add( 'media-models', "/wp-includes/js/media-models$suffix.js", array( 'wp-backbone' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'media-models', '_wpMediaModelsL10n', array(
		'settings' => array(
			'ajaxurl' => admin_url( 'admin-ajax.php', 'relative' ),
			'post' => array( 'id' => 0 ),
		),
	) );

	$scripts->add( 'wp-embed', "/wp-includes/js/wp-embed$suffix.js" );

	// To enqueue media-views or media-editor, call wp_enqueue_media().
	// Both rely on numerous settings, styles, and templates to operate correctly.
	$scripts->add( 'media-views',  "/wp-includes/js/media-views$suffix.js",  array( 'utils', 'media-models', 'wp-plupload', 'jquery-ui-sortable', 'wp-mediaelement' ), false, 1 );
	$scripts->add( 'media-editor', "/wp-includes/js/media-editor$suffix.js", array( 'shortcode', 'media-views' ), false, 1 );
	$scripts->add( 'media-audiovideo', "/wp-includes/js/media-audiovideo$suffix.js", array( 'media-editor' ), false, 1 );
	$scripts->add( 'mce-view', "/wp-includes/js/mce-view$suffix.js", array( 'shortcode', 'jquery', 'media-views', 'media-audiovideo' ), false, 1 );

	if ( is_admin() ) {
		$scripts->add( 'admin-tags', "/wp-admin/js/tags$suffix.js", array( 'jquery', 'wp-ajax-response' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'admin-tags', 'tagsl10n', array(
			'noPerm' => __('You do not have permission to do that.'),
			'broken' => __('An unidentified error has occurred.')
		));

		$scripts->add( 'admin-comments', "/wp-admin/js/edit-comments$suffix.js", array('wp-lists', 'quicktags', 'jquery-query'), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'admin-comments', 'adminCommentsL10n', array(
			'hotkeys_highlight_first' => isset($_GET['hotkeys_highlight_first']),
			'hotkeys_highlight_last' => isset($_GET['hotkeys_highlight_last']),
			'replyApprove' => __( 'Approve and Reply' ),
			'reply' => __( 'Reply' ),
			'warnQuickEdit' => __( "Are you sure you want to edit this comment?\nThe changes you made will be lost." ),
			'docTitleComments' => __( 'Comments' ),
			/* translators: %s: comments count */
			'docTitleCommentsCount' => __( 'Comments (%s)' ),
		) );

		$scripts->add( 'xfn', "/wp-admin/js/xfn$suffix.js", array('jquery'), false, 1 );

		$scripts->add( 'postbox', "/wp-admin/js/postbox$suffix.js", array('jquery-ui-sortable'), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'postbox', 'postBoxL10n', array(
			'postBoxEmptyString' => __( 'Drag boxes here' ),
		) );

		$scripts->add( 'tags-box', "/wp-admin/js/tags-box$suffix.js", array( 'jquery', 'suggest' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'tags-box', 'tagsBoxL10n', array(
			'tagDelimiter' => _x( ',', 'tag delimiter' ),
		) );

		$scripts->add( 'post', "/wp-admin/js/post.js", array( 'suggest', 'wp-lists', 'postbox', 'tags-box', 'underscore', 'word-count', 'wp-a11y' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'post', 'postL10n', array(
			'ok' => 'OK',
			'cancel' => 'Cancel',
			'publishOn' => 'Publish on:',
			'publishOnFuture' =>  'Schedule for:',
			'publishOnPast' => 'Published on:',
			'dateFormat' => '%1$s %2$s, %3$s @ %4$s:%5$s',
			'showcomm' => 'Show more comments',
			'endcomm' => 'No more comments found.',
			'publish' => 'Publish',
			'schedule' => __('Schedule'),
			'update' => 'Update',
			'savePending' => __('Save as Pending'),
			'saveDraft' => 'Save Draft',
			'private' => __('Private'),
			'public' => __('Public'),
			'publicSticky' => __('Public, Sticky'),
			'password' => __('Password Protected'),
			'privatelyPublished' => __('Privately Published'),
			'published' => __('Published'),
			'saveAlert' => __('The changes you made will be lost if you navigate away from this page.'),
			'savingText' => __('Saving Draft&#8230;'),
			'permalinkSaved' => __( 'Permalink saved' ),
		) );

		$scripts->add( 'press-this', "/wp-admin/js/press-this$suffix.js", array( 'jquery', 'tags-box' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'press-this', 'pressThisL10n', array(
			'newPost' => __( 'Title' ),
			'serverError' => __( 'Connection lost or the server is busy. Please try again later.' ),
			'saveAlert' => __( 'The changes you made will be lost if you navigate away from this page.' ),
			/* translators: %d: nth embed found in a post */
			'suggestedEmbedAlt' => __( 'Suggested embed #%d' ),
			/* translators: %d: nth image found in a post */
			'suggestedImgAlt' => __( 'Suggested image #%d' ),
		) );

		$scripts->add( 'editor-expand', "/wp-admin/js/editor-expand$suffix.js", array( 'jquery' ), false, 1 );

		$scripts->add( 'link', "/wp-admin/js/link.js", array( 'wp-lists', 'postbox' ), false, 1 );

		$scripts->add( 'comment', "/wp-admin/js/comment.js", array( 'jquery', 'postbox' ) );
		$scripts->add_data( 'comment', 'group', 1 );
		did_action( 'init' ) && $scripts->localize( 'comment', 'commentL10n', array(
			'submittedOn' => 'Submitted on:',
			'dateFormat' => __( '%1$s %2$s, %3$s @ %4$s:%5$s' )
		) );

		$scripts->add( 'admin-gallery', "/wp-admin/js/gallery$suffix.js", array( 'jquery-ui-sortable' ) );

		$scripts->add( 'admin-widgets', "/wp-admin/js/widgets$suffix.js", array( 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ), false, 1 );

		$scripts->add( 'theme', "/wp-admin/js/theme$suffix.js", array( 'wp-backbone', 'wp-a11y' ), false, 1 );

		$scripts->add( 'inline-edit-post', "/wp-admin/js/inline-edit-post.js", array( 'jquery', 'suggest', 'wp-a11y' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'inline-edit-post', 'inlineEditL10n', array(
			'error'      => __( 'Error while saving the changes.' ),
			'ntdeltitle' => __( 'Remove From Bulk Edit' ),
			'notitle'    => __( '(no title)' ),
			'comma'      => trim( _x( ',', 'tag delimiter' ) ),
			'saved'      => 'Changes saved.',
		) );

		$scripts->add( 'inline-edit-tax', "/wp-admin/js/inline-edit-tax$suffix.js", array( 'jquery', 'wp-a11y' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'inline-edit-tax', 'inlineEditL10n', array(
			'error' => __( 'Error while saving the changes.' ),
			'saved' => __( 'Changes saved.' ),
		) );

		$scripts->add( 'plugin-install', "/wp-admin/js/plugin-install$suffix.js", array( 'jquery', 'jquery-ui-core', 'thickbox' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'plugin-install', 'plugininstallL10n', array(
			'plugin_information' => 'Plugin:',
			'plugin_modal_label' => 'Plugin details',
			'ays' => 'Are you sure you want to install this plugin?'
		) );

		$scripts->add( 'farbtastic', '/wp-admin/js/farbtastic.js', array('jquery'), '1.2' );
		$scripts->add( 'iris', '/wp-admin/js/iris.min.js', array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ), '1.0.7', 1 );
		$scripts->add( 'wp-color-picker', "/wp-admin/js/color-picker.js", array( 'iris' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'wp-color-picker', 'wpColorPickerL10n', array(
			'clear' => 'Clear',
			'defaultString' => 'Default',
			'pick' => 'Select Color',
			'current' => 'Current Color',
		) );

		$scripts->add( 'dashboard', "/wp-admin/js/dashboard.js", array( 'jquery', 'admin-comments', 'postbox' ), false, 1 );

		$scripts->add( 'list-revisions', "/wp-includes/js/wp-list-revisions.js" );

		$scripts->add( 'media-grid', "/wp-includes/js/media-grid.js", array( 'media-editor' ), false, 1 );
		$scripts->add( 'media', "/wp-admin/js/media.js", array( 'jquery' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'media', 'attachMediaBoxL10n', array(
			'error' => __( 'An error has occurred. Please reload the page and try again.' ),
		));

		$scripts->add( 'image-edit', "/wp-admin/js/image-edit$suffix.js", array('jquery', 'json2', 'imgareaselect'), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'image-edit', 'imageEditL10n', array(
			'error' => __( 'Could not load the preview image. Please reload the page and try again.' )
		));

		$scripts->add( 'set-post-thumbnail', "/wp-admin/js/set-post-thumbnail$suffix.js", array( 'jquery' ), false, 1 );
		did_action( 'init' ) && $scripts->localize( 'set-post-thumbnail', 'setPostThumbnailL10n', array(
			'setThumbnail' => 'Use as featured image',
			'saving' => 'Saving...',
			'error' => 'Could not set that as the thumbnail image. Try a different attachment.',
			'done' => 'Done'
		) );

		$scripts->add( 'nav-menu', "/wp-admin/js/nav-menu$suffix.js", array( 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'wp-lists', 'postbox', 'json2' ) );
		did_action( 'init' ) && $scripts->localize( 'nav-menu', 'navMenuL10n', array(
			'noResultsFound' => 'No results found.',
			'warnDeleteMenu' => "You are about to permanently delete this menu. \n 'Cancel' to stop, 'OK' to delete.",
			'saveAlert' => 'The changes you made will be lost if you navigate away from this page.',
			'untitled' => _x( '(no label)', 'missing menu item navigation label' )
		) );

		$scripts->add( 'custom-header', "/wp-admin/js/custom-header.js", array( 'jquery-masonry' ), false, 1 );
		$scripts->add( 'custom-background', "/wp-admin/js/custom-background.js", array( 'wp-color-picker', 'media-views' ), false, 1 );
		$scripts->add( 'media-gallery', "/wp-admin/js/media-gallery.js", array('jquery'), false, 1 );
		$scripts->add( 'svg-painter', '/wp-admin/js/svg-painter.js', array( 'jquery' ), false, 1 );
	}
}

function wp_default_styles( &$styles ) {
	if ( ! $guessurl = site_url() )
		$guessurl = wp_guess_url();

	$styles->base_url = $guessurl;
	$styles->content_url = defined('WP_CONTENT_URL')? WP_CONTENT_URL : '';
	$styles->default_version = get_bloginfo( 'version' );
	$styles->default_dirs = array('/wp-admin/', '/wp-includes/css/');

	$styles->add( 'colors', true, array( 'wp-admin', 'buttons' ) );

	$styles->add( 'common',              "/wp-admin/css/common.css" );
	$styles->add( 'forms',               "/wp-admin/css/forms.css" );
	$styles->add( 'admin-menu',          "/wp-admin/css/admin-menu.css" );
	$styles->add( 'dashboard',           "/wp-admin/css/dashboard.css" );
	$styles->add( 'list-tables',         "/wp-admin/css/list-tables.css" );
	$styles->add( 'edit',                "/wp-admin/css/edit.css" );
	$styles->add( 'revisions',           "/wp-admin/css/revisions.css" );
	$styles->add( 'media',               "/wp-admin/css/media.css" );
	$styles->add( 'themes',              "/wp-admin/css/themes.css" );
	$styles->add( 'nav-menus',           "/wp-admin/css/nav-menus.css" );
	$styles->add( 'widgets',             "/wp-admin/css/widgets.css" );
	$styles->add( 'site-icon',           "/wp-admin/css/site-icon.css" );
	$styles->add( 'l10n',                "/wp-admin/css/l10n.css" );

	$styles->add( 'wp-admin', false, array( 'open-sans', 'dashicons', 'common', 'forms', 'admin-menu', 'dashboard', 'list-tables', 'edit', 'revisions', 'media', 'themes', 'about', 'nav-menus', 'widgets', 'site-icon', 'l10n' ) );

	$styles->add( 'login',               "/wp-admin/css/login.css", array( 'open-sans', 'dashicons', 'buttons', 'forms', 'l10n' ) );
	$styles->add( 'install',             "/wp-admin/css/install.css", array( 'open-sans', 'buttons' ) );
	$styles->add( 'wp-color-picker',     "/wp-admin/css/color-picker.css" );
	$styles->add( 'customize-controls',  "/wp-admin/css/customize-controls.css", array( 'wp-admin', 'colors', 'ie', 'imgareaselect' ) );
	$styles->add( 'customize-widgets',   "/wp-admin/css/customize-widgets.css", array( 'wp-admin', 'colors' ) );
	$styles->add( 'customize-nav-menus', "/wp-admin/css/customize-nav-menus.css", array( 'wp-admin', 'colors' ) );
	$styles->add( 'press-this',          "/wp-admin/css/press-this.css", array( 'buttons' ) );

	$styles->add( 'ie', "/wp-admin/css/ie.css" );
	$styles->add_data( 'ie', 'conditional', 'lte IE 7' );

	$styles->add( 'buttons',   "/wp-includes/css/buttons.css" );
	$styles->add( 'dashicons', "/wp-includes/css/dashicons.css" );

	$styles->add( 'admin-bar',            "/wp-includes/css/admin-bar.css", array( 'dashicons' ) );
	$styles->add( 'wp-auth-check',        "/wp-includes/css/wp-auth-check.css", array( 'dashicons' ) );
	$styles->add( 'editor-buttons',       "/wp-includes/css/editor.css", array( 'dashicons' ) );
	$styles->add( 'media-views',          "/wp-includes/css/media-views.css", array( 'buttons', 'dashicons', 'wp-mediaelement' ) );
	$styles->add( 'wp-pointer',           "/wp-includes/css/wp-pointer.css", array( 'dashicons' ) );
	$styles->add( 'customize-preview',    "/wp-includes/css/customize-preview.css" );
	$styles->add( 'wp-embed-template-ie', "/wp-includes/css/wp-embed-template-ie.css" );
	$styles->add_data( 'wp-embed-template-ie', 'conditional', 'lte IE 8' );

	$styles->add( 'imgareaselect',       '/wp-includes/js/imgareaselect/imgareaselect.css', array(), '0.9.8' );
	$styles->add( 'wp-jquery-ui-dialog', "/wp-includes/css/jquery-ui-dialog.css", array( 'dashicons' ) );
	$styles->add( 'mediaelement',        "/wp-includes/js/mediaelement/mediaelementplayer.min.css", array(), '2.18.1' );
	$styles->add( 'wp-mediaelement',     "/wp-includes/js/mediaelement/wp-mediaelement.css", array( 'mediaelement' ) );
	$styles->add( 'thickbox',            '/wp-includes/js/thickbox/thickbox.css', array( 'dashicons' ) );

}

function wp_prototype_before_jquery( $js_array ) {
	if ( false === $prototype = array_search( 'prototype', $js_array, true ) )
		return $js_array;

	if ( false === $jquery = array_search( 'jquery', $js_array, true ) )
		return $js_array;

	if ( $prototype < $jquery )
		return $js_array;

	unset($js_array[$prototype]);

	array_splice( $js_array, $jquery, 0, 'prototype' );

	return $js_array;
}

function wp_just_in_time_script_localization() {
	wp_localize_script( 'autosave', 'autosaveL10n', array(
		'autosaveInterval' => AUTOSAVE_INTERVAL,
		'blog_id' => get_current_blog_id(),
	) );
}

function wp_style_loader_src( $src, $handle ) {
	global $_wp_admin_css_colors;

	if ( wp_installing() )
		return preg_replace( '#^wp-admin/#', './', $src );

	if ( 'colors' == $handle ) {
		$color = get_user_option('admin_color');

		if ( empty($color) || !isset($_wp_admin_css_colors[$color]) )
			$color = 'fresh';

		$color = $_wp_admin_css_colors[$color];
		$url = $color->url;

		if ( ! $url ) {
			return false;
		}

		$parsed = parse_url( $src );
		if ( isset($parsed['query']) && $parsed['query'] ) {
			wp_parse_str( $parsed['query'], $qv );
			$url = add_query_arg( $qv, $url );
		}

		return $url;
	}

	return $src;
}

function print_head_scripts() {
	global $concatenate_scripts;

	if ( ! did_action('wp_print_scripts') ) {
		/** This action is documented in wp-includes/functions.wp-scripts.php */
		do_action( 'wp_print_scripts' );
	}

	$wp_scripts = wp_scripts();

	script_concat_settings();
	$wp_scripts->do_concat = $concatenate_scripts;
	$wp_scripts->do_head_items();

	/**
	 * Filter whether to print the head scripts.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $print Whether to print the head scripts. Default true.
	 */
	if ( apply_filters( 'print_head_scripts', true ) ) {
		_print_scripts();
	}

	$wp_scripts->reset();
	return $wp_scripts->done;
}

function print_footer_scripts() {
	global $wp_scripts, $concatenate_scripts;

	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
		return array(); // No need to run if not instantiated.
	}
	script_concat_settings();
	$wp_scripts->do_concat = $concatenate_scripts;
	$wp_scripts->do_footer_items();

	/**
	 * Filter whether to print the footer scripts.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $print Whether to print the footer scripts. Default true.
	 */
	if ( apply_filters( 'print_footer_scripts', true ) ) {
		_print_scripts();
	}

	$wp_scripts->reset();
	return $wp_scripts->done;
}

function _print_scripts() {
	global $wp_scripts, $compress_scripts;

	$zip = $compress_scripts ? 1 : 0;
	if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
		$zip = 'gzip';

	if ( $concat = trim( $wp_scripts->concat, ', ' ) ) {

		if ( !empty($wp_scripts->print_code) ) {
			echo "\n<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n"; // not needed in HTML 5
			echo $wp_scripts->print_code;
			echo "/* ]]> */\n";
			echo "</script>\n";
		}

		$concat = str_split( $concat, 128 );
		$concat = 'load%5B%5D=' . implode( '&load%5B%5D=', $concat );

		$src = $wp_scripts->base_url . "/wp-admin/load-scripts.php?c={$zip}&" . $concat . '&ver=' . $wp_scripts->default_version;
		echo "<script type='text/javascript' src='" . esc_attr($src) . "'></script>\n";
	}

	if ( !empty($wp_scripts->print_html) )
		echo $wp_scripts->print_html;
}

function wp_print_head_scripts() {
	if ( ! did_action('wp_print_scripts') ) {
		/** This action is documented in wp-includes/functions.wp-scripts.php */
		do_action( 'wp_print_scripts' );
	}

	global $wp_scripts;

	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
		return array(); // no need to run if nothing is queued
	}
	return print_head_scripts();
}

function _wp_footer_scripts() {
	print_late_styles();
	print_footer_scripts();
}

function wp_print_footer_scripts() {
	do_action( 'wp_print_footer_scripts' );
}

function wp_enqueue_scripts() {
	do_action( 'wp_enqueue_scripts' );
}

function print_admin_styles() {
	global $concatenate_scripts;

	$wp_styles = wp_styles();

	script_concat_settings();
	$wp_styles->do_concat = $concatenate_scripts;
	$wp_styles->do_items(false);

	if ( apply_filters( 'print_admin_styles', true ) ) {
		_print_styles();
	}

	$wp_styles->reset();
	return $wp_styles->done;
}

function print_late_styles() {
	global $wp_styles, $concatenate_scripts;

	if ( ! ( $wp_styles instanceof WP_Styles ) ) {
		return;
	}

	script_concat_settings();
	$wp_styles->do_concat = $concatenate_scripts;
	$wp_styles->do_footer_items();

	if ( apply_filters( 'print_late_styles', true ) ) {
		_print_styles();
	}

	$wp_styles->reset();
	return $wp_styles->done;
}

function _print_styles() {
	global $compress_css;

	$wp_styles = wp_styles();

	$zip = $compress_css ? 1 : 0;
	if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
		$zip = 'gzip';

	if ( $concat = trim( $wp_styles->concat, ', ' ) ) {
		$dir = $wp_styles->text_direction;
		$ver = $wp_styles->default_version;

		$concat = str_split( $concat, 128 );
		$concat = 'load%5B%5D=' . implode( '&load%5B%5D=', $concat );

		$href = $wp_styles->base_url . "/wp-admin/load-styles.php?c={$zip}&dir={$dir}&" . $concat . '&ver=' . $ver;
		echo "<link rel='stylesheet' href='" . esc_attr($href) . "' type='text/css' media='all' />\n";

		if ( !empty($wp_styles->print_code) ) {
			echo "<style type='text/css'>\n";
			echo $wp_styles->print_code;
			echo "\n</style>\n";
		}
	}

	if ( !empty($wp_styles->print_html) )
		echo $wp_styles->print_html;
}

function script_concat_settings() {
	global $concatenate_scripts, $compress_scripts, $compress_css;

	$compressed_output = ( ini_get('zlib.output_compression') || 'ob_gzhandler' == ini_get('output_handler') );

	if ( ! isset($concatenate_scripts) ) {
		$concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;
		if ( ( ! is_admin() && ! did_action( 'login_init' ) ) || ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) )
			$concatenate_scripts = false;
	}

	if ( ! isset($compress_scripts) ) {
		$compress_scripts = defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : true;
		if ( $compress_scripts && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_scripts = false;
	}

	if ( ! isset($compress_css) ) {
		$compress_css = defined('COMPRESS_CSS') ? COMPRESS_CSS : true;
		if ( $compress_css && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_css = false;
	}
}
