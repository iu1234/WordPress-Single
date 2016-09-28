<?php
/**
 * oEmbed API: Top-level oEmbed functionality
 *
 * @package WordPress
 * @subpackage oEmbed
 * @since 4.4.0
 */

function wp_embed_register_handler( $id, $regex, $callback, $priority = 10 ) {
	global $wp_embed;
	$wp_embed->register_handler( $id, $regex, $callback, $priority );
}

function wp_embed_unregister_handler( $id, $priority = 10 ) {
	global $wp_embed;
	$wp_embed->unregister_handler( $id, $priority );
}

function wp_embed_defaults( $url = '' ) {
	if ( ! empty( $GLOBALS['content_width'] ) )
		$width = (int) $GLOBALS['content_width'];

	if ( empty( $width ) )
		$width = 500;

	$height = min( ceil( $width * 1.5 ), 1000 );

	return apply_filters( 'embed_defaults', compact( 'width', 'height' ), $url );
}

function wp_oembed_get( $url, $args = '' ) {
	require_once( ABSPATH . WPINC . '/class-oembed.php' );
	$oembed = _wp_oembed_get_object();
	return $oembed->get_html( $url, $args );
}

function wp_oembed_add_provider( $format, $provider, $regex = false ) {
	require_once( ABSPATH . WPINC . '/class-oembed.php' );

	if ( did_action( 'plugins_loaded' ) ) {
		$oembed = _wp_oembed_get_object();
		$oembed->providers[$format] = array( $provider, $regex );
	} else {
		WP_oEmbed::_add_provider_early( $format, $provider, $regex );
	}
}

function wp_oembed_remove_provider( $format ) {
	require_once( ABSPATH . WPINC . '/class-oembed.php' );
	if ( did_action( 'plugins_loaded' ) ) {
		$oembed = _wp_oembed_get_object();

		if ( isset( $oembed->providers[ $format ] ) ) {
			unset( $oembed->providers[ $format ] );
			return true;
		}
	} else {
		WP_oEmbed::_remove_provider_early( $format );
	}

	return false;
}

function wp_maybe_load_embeds() {
	wp_embed_register_handler( 'audio', '#^https?://.+?\.(' . join( '|', wp_get_audio_extensions() ) . ')$#i', apply_filters( 'wp_audio_embed_handler', 'wp_embed_handler_audio' ), 9999 );
	wp_embed_register_handler( 'video', '#^https?://.+?\.(' . join( '|', wp_get_video_extensions() ) . ')$#i', apply_filters( 'wp_video_embed_handler', 'wp_embed_handler_video' ), 9999 );
}

function wp_embed_handler_audio( $matches, $attr, $url, $rawattr ) {
	$audio = sprintf( '[audio src="%s" /]', esc_url( $url ) );
	return apply_filters( 'wp_embed_handler_audio', $audio, $attr, $url, $rawattr );
}

function wp_embed_handler_video( $matches, $attr, $url, $rawattr ) {
	$dimensions = '';
	if ( ! empty( $rawattr['width'] ) && ! empty( $rawattr['height'] ) ) {
		$dimensions .= sprintf( 'width="%d" ', (int) $rawattr['width'] );
		$dimensions .= sprintf( 'height="%d" ', (int) $rawattr['height'] );
	}
	$video = sprintf( '[video %s src="%s" /]', $dimensions, esc_url( $url ) );

	return apply_filters( 'wp_embed_handler_video', $video, $attr, $url, $rawattr );
}

function wp_oembed_register_route() {
	$controller = new WP_oEmbed_Controller();
	$controller->register_routes();
}

function wp_oembed_add_discovery_links() {
	$output = '';

	if ( is_singular() ) {
		$output .= '<link rel="alternate" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";

		if ( class_exists( 'SimpleXMLElement' ) ) {
			$output .= '<link rel="alternate" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";
		}
	}

	echo apply_filters( 'oembed_discovery_links', $output );
}

function wp_oembed_add_host_js() {
	wp_enqueue_script( 'wp-embed' );
}

function get_post_embed_url( $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$embed_url     = trailingslashit( get_permalink( $post ) ) . user_trailingslashit( 'embed' );
	$path_conflict = get_page_by_path( str_replace( home_url(), '', $embed_url ), OBJECT, get_post_types( array( 'public' => true ) ) );

	if ( ! get_option( 'permalink_structure' ) || $path_conflict ) {
		$embed_url = add_query_arg( array( 'embed' => 'true' ), get_permalink( $post ) );
	}

	return esc_url_raw( apply_filters( 'post_embed_url', $embed_url, $post ) );
}

function get_oembed_endpoint_url( $permalink = '', $format = 'json' ) {
	$url = rest_url( 'oembed/1.0/embed' );

	if ( 'json' === $format ) {
		$format = false;
	}

	if ( '' !== $permalink ) {
		$url = add_query_arg( array(
			'url'    => urlencode( $permalink ),
			'format' => $format,
		), $url );
	}

	return apply_filters( 'oembed_endpoint_url', $url, $permalink, $format );
}

function get_post_embed_html( $width, $height, $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$embed_url = get_post_embed_url( $post );

	$output = '<blockquote class="wp-embedded-content"><a href="' . esc_url( get_permalink( $post ) ) . '">' . get_the_title( $post ) . "</a></blockquote>\n";

	$output .= "<script type='text/javascript'>\n";
	$output .= "<!--//--><![CDATA[//><!--\n";
	if ( SCRIPT_DEBUG ) {
		$output .= file_get_contents( ABSPATH . WPINC . '/js/wp-embed.js' );
	} else {
		$output .=<<<JS
		!function(a,b){"use strict";function c(){if(!e){e=!0;var a,c,d,f,g=-1!==navigator.appVersion.indexOf("MSIE 10"),h=!!navigator.userAgent.match(/Trident.*rv:11\./),i=b.querySelectorAll("iframe.wp-embedded-content");for(c=0;c<i.length;c++)if(d=i[c],!d.getAttribute("data-secret")){if(f=Math.random().toString(36).substr(2,10),d.src+="#?secret="+f,d.setAttribute("data-secret",f),g||h)a=d.cloneNode(!0),a.removeAttribute("security"),d.parentNode.replaceChild(a,d)}else;}}var d=!1,e=!1;if(b.querySelector)if(a.addEventListener)d=!0;if(a.wp=a.wp||{},!a.wp.receiveEmbedMessage)if(a.wp.receiveEmbedMessage=function(c){var d=c.data;if(d.secret||d.message||d.value)if(!/[^a-zA-Z0-9]/.test(d.secret)){var e,f,g,h,i,j=b.querySelectorAll('iframe[data-secret="'+d.secret+'"]'),k=b.querySelectorAll('blockquote[data-secret="'+d.secret+'"]');for(e=0;e<k.length;e++)k[e].style.display="none";for(e=0;e<j.length;e++)if(f=j[e],c.source===f.contentWindow){if(f.removeAttribute("style"),"height"===d.message){if(g=parseInt(d.value,10),g>1e3)g=1e3;else if(200>~~g)g=200;f.height=g}if("link"===d.message)if(h=b.createElement("a"),i=b.createElement("a"),h.href=f.getAttribute("src"),i.href=d.value,i.host===h.host)if(b.activeElement===f)a.top.location.href=d.value}else;}},d)a.addEventListener("message",a.wp.receiveEmbedMessage,!1),b.addEventListener("DOMContentLoaded",c,!1),a.addEventListener("load",c,!1)}(window,document);
JS;
	}
	$output .= "\n//--><!]]>";
	$output .= "\n</script>";

	$output .= sprintf(
		'<iframe sandbox="allow-scripts" security="restricted" src="%1$s" width="%2$d" height="%3$d" title="%4$s" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>',
		esc_url( $embed_url ),
		absint( $width ),
		absint( $height ),
		esc_attr(
			sprintf(
				/* translators: 1: post title, 2: site name */
				__( '&#8220;%1$s&#8221; &#8212; %2$s' ),
				get_the_title( $post ),
				get_bloginfo( 'name' )
			)
		)
	);

	return apply_filters( 'embed_html', $output, $post, $width, $height );
}

function get_oembed_response_data( $post, $width ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	if ( 'publish' !== get_post_status( $post ) ) {
		return false;
	}

	$min_max_width = apply_filters( 'oembed_min_max_width', array(
		'min' => 200,
		'max' => 600
	) );

	$width  = min( max( $min_max_width['min'], $width ), $min_max_width['max'] );
	$height = max( ceil( $width / 16 * 9 ), 200 );

	$data = array(
		'version'       => '1.0',
		'provider_name' => get_bloginfo( 'name' ),
		'provider_url'  => get_home_url(),
		'author_name'   => get_bloginfo( 'name' ),
		'author_url'    => get_home_url(),
		'title'         => $post->post_title,
		'type'          => 'link',
	);

	$author = get_user_by( 'id', $post->post_author );

	if ( $author ) {
		$data['author_name'] = $author->display_name;
		$data['author_url']  = get_author_posts_url( $author->ID );
	}

	return apply_filters( 'oembed_response_data', $data, $post, $width, $height );
}

function get_oembed_response_data_rich( $data, $post, $width, $height ) {
	$data['width']  = absint( $width );
	$data['height'] = absint( $height );
	$data['type']   = 'rich';
	$data['html']   = get_post_embed_html( $width, $height, $post );

	// Add post thumbnail to response if available.
	$thumbnail_id = false;

	if ( has_post_thumbnail( $post->ID ) ) {
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
	}

	if ( 'attachment' === get_post_type( $post ) ) {
		if ( wp_attachment_is_image( $post ) ) {
			$thumbnail_id = $post->ID;
		} else if ( wp_attachment_is( 'video', $post ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post );
			$data['type'] = 'video';
		}
	}

	if ( $thumbnail_id ) {
		list( $thumbnail_url, $thumbnail_width, $thumbnail_height ) = wp_get_attachment_image_src( $thumbnail_id, array( $width, 99999 ) );
		$data['thumbnail_url']    = $thumbnail_url;
		$data['thumbnail_width']  = $thumbnail_width;
		$data['thumbnail_height'] = $thumbnail_height;
	}

	return $data;
}

function wp_oembed_ensure_format( $format ) {
	if ( ! in_array( $format, array( 'json', 'xml' ), true ) ) {
		return 'json';
	}

	return $format;
}

function _oembed_rest_pre_serve_request( $served, $result, $request, $server ) {
	$params = $request->get_params();

	if ( '/oembed/1.0/embed' !== $request->get_route() || 'GET' !== $request->get_method() ) {
		return $served;
	}
	if ( ! isset( $params['format'] ) || 'xml' !== $params['format'] ) {
		return $served;
	}

	$data = $server->response_to_data( $result, false );

	if ( ! class_exists( 'SimpleXMLElement' ) ) {
		status_header( 501 );
		die( get_status_header_desc( 501 ) );
	}

	$result = _oembed_create_xml( $data );

	// Bail if there's no XML.
	if ( ! $result ) {
		status_header( 501 );
		return get_status_header_desc( 501 );
	}

	if ( ! headers_sent() ) {
		$server->send_header( 'Content-Type', 'text/xml; charset=' . get_option( 'blog_charset' ) );
	}

	echo $result;

	return true;
}

function _oembed_create_xml( $data, $node = null ) {
	if ( ! is_array( $data ) || empty( $data ) ) {
		return false;
	}

	if ( null === $node ) {
		$node = new SimpleXMLElement( '<oembed></oembed>' );
	}

	foreach ( $data as $key => $value ) {
		if ( is_numeric( $key ) ) {
			$key = 'oembed';
		}

		if ( is_array( $value ) ) {
			$item = $node->addChild( $key );
			_oembed_create_xml( $value, $item );
		} else {
			$node->addChild( $key, esc_html( $value ) );
		}
	}

	return $node->asXML();
}

function wp_filter_oembed_result( $result, $data, $url ) {
	if ( false === $result || ! in_array( $data->type, array( 'rich', 'video' ) ) ) {
		return $result;
	}

	require_once( ABSPATH . WPINC . '/class-oembed.php' );
	$wp_oembed = _wp_oembed_get_object();

	if ( false !== $wp_oembed->get_provider( $url, array( 'discover' => false ) ) ) {
		return $result;
	}

	$allowed_html = array(
		'a'          => array(
			'href'         => true,
		),
		'blockquote' => array(),
		'iframe'     => array(
			'src'          => true,
			'width'        => true,
			'height'       => true,
			'frameborder'  => true,
			'marginwidth'  => true,
			'marginheight' => true,
			'scrolling'    => true,
			'title'        => true,
		),
	);

	$html = wp_kses( $result, $allowed_html );

	preg_match( '|(<blockquote>.*?</blockquote>)?.*(<iframe.*?></iframe>)|ms', $html, $content );
	if ( empty( $content[2] ) ) {
		return false;
	}
	$html = $content[1] . $content[2];

	if ( ! empty( $content[1] ) ) {
		$html = str_replace( '<iframe', '<iframe style="position: absolute; clip: rect(1px, 1px, 1px, 1px);"', $html );
		$html = str_replace( '<blockquote', '<blockquote class="wp-embedded-content"', $html );
	}

	$html = str_replace( '<iframe', '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"', $html );

	preg_match( '/ src=[\'"]([^\'"]*)[\'"]/', $html, $results );

	if ( ! empty( $results ) ) {
		$secret = '123456';
		$url = esc_url( "{$results[1]}#?secret=$secret" );
		$html = str_replace( $results[0], " src=\"$url\" data-secret=\"$secret\"", $html );
		$html = str_replace( '<blockquote', "<blockquote data-secret=\"$secret\"", $html );
	}

	return $html;
}

function wp_embed_excerpt_more( $more_string ) {
	if ( ! is_embed() ) {
		return $more_string;
	}

	$link = sprintf( '<a href="%1$s" class="wp-embed-more" target="_top">%2$s</a>',
		esc_url( get_permalink() ),
		sprintf( 'Continue reading %s', '<span class="screen-reader-text">' . get_the_title() . '</span>' )
	);
	return ' &hellip; ' . $link;
}

function the_excerpt_embed() {
	$output = get_the_excerpt();

	echo apply_filters( 'the_excerpt_embed', $output );
}

function wp_embed_excerpt_attachment( $content ) {
	if ( is_attachment() ) {
		return prepend_attachment( '' );
	}

	return $content;
}

function enqueue_embed_scripts() {
	wp_enqueue_style( 'open-sans' );
	wp_enqueue_style( 'wp-embed-template-ie' );

	do_action( 'enqueue_embed_scripts' );
}

function print_embed_styles() {
	?>
	<style type="text/css">
	<?php
		if ( SCRIPT_DEBUG ) {
			readfile( ABSPATH . WPINC . "/css/wp-embed-template.css" );
		} else {
			?>
			body,html{padding:0;margin:0}body{font-family:sans-serif}.screen-reader-text{clip:rect(1px,1px,1px,1px);height:1px;overflow:hidden;position:absolute!important;width:1px}.dashicons{display:inline-block;width:20px;height:20px;background-color:transparent;background-repeat:no-repeat;-webkit-background-size:20px 20px;background-size:20px;background-position:center;-webkit-transition:background .1s ease-in;transition:background .1s ease-in;position:relative;top:5px}.dashicons-no{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M15.55%2013.7l-2.19%202.06-3.42-3.65-3.64%203.43-2.06-2.18%203.64-3.43-3.42-3.64%202.18-2.06%203.43%203.64%203.64-3.42%202.05%202.18-3.64%203.43z%27%20fill%3D%27%23fff%27%2F%3E%3C%2Fsvg%3E")}.dashicons-admin-comments{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M5%202h9q.82%200%201.41.59T16%204v7q0%20.82-.59%201.41T14%2013h-2l-5%205v-5H5q-.82%200-1.41-.59T3%2011V4q0-.82.59-1.41T5%202z%27%20fill%3D%27%2382878c%27%2F%3E%3C%2Fsvg%3E")}.wp-embed-comments a:hover .dashicons-admin-comments{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M5%202h9q.82%200%201.41.59T16%204v7q0%20.82-.59%201.41T14%2013h-2l-5%205v-5H5q-.82%200-1.41-.59T3%2011V4q0-.82.59-1.41T5%202z%27%20fill%3D%27%230073aa%27%2F%3E%3C%2Fsvg%3E")}.dashicons-share{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M14.5%2012q1.24%200%202.12.88T17.5%2015t-.88%202.12-2.12.88-2.12-.88T11.5%2015q0-.34.09-.69l-4.38-2.3Q6.32%2013%205%2013q-1.24%200-2.12-.88T2%2010t.88-2.12T5%207q1.3%200%202.21.99l4.38-2.3q-.09-.35-.09-.69%200-1.24.88-2.12T14.5%202t2.12.88T17.5%205t-.88%202.12T14.5%208q-1.3%200-2.21-.99l-4.38%202.3Q8%209.66%208%2010t-.09.69l4.38%202.3q.89-.99%202.21-.99z%27%20fill%3D%27%2382878c%27%2F%3E%3C%2Fsvg%3E");display:none}.js .dashicons-share{display:inline-block}.wp-embed-share-dialog-open:hover .dashicons-share{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M14.5%2012q1.24%200%202.12.88T17.5%2015t-.88%202.12-2.12.88-2.12-.88T11.5%2015q0-.34.09-.69l-4.38-2.3Q6.32%2013%205%2013q-1.24%200-2.12-.88T2%2010t.88-2.12T5%207q1.3%200%202.21.99l4.38-2.3q-.09-.35-.09-.69%200-1.24.88-2.12T14.5%202t2.12.88T17.5%205t-.88%202.12T14.5%208q-1.3%200-2.21-.99l-4.38%202.3Q8%209.66%208%2010t-.09.69l4.38%202.3q.89-.99%202.21-.99z%27%20fill%3D%27%230073aa%27%2F%3E%3C%2Fsvg%3E")}.wp-embed{padding:25px;font:400 14px/1.5 'Open Sans',sans-serif;color:#82878c;background:#fff;border:1px solid #e5e5e5;-webkit-box-shadow:0 1px 1px rgba(0,0,0,.05);box-shadow:0 1px 1px rgba(0,0,0,.05);overflow:auto;zoom:1}.wp-embed a{color:#82878c;text-decoration:none}.wp-embed a:hover{text-decoration:underline}.wp-embed-featured-image{margin-bottom:20px}.wp-embed-featured-image img{width:100%;height:auto;border:none}.wp-embed-featured-image.square{float:left;max-width:160px;margin-right:20px}.wp-embed p{margin:0}p.wp-embed-heading{margin:0 0 15px;font-weight:700;font-size:22px;line-height:1.3}.wp-embed-heading a{color:#32373c}.wp-embed .wp-embed-more{color:#b4b9be}.wp-embed-footer{display:table;width:100%;margin-top:30px}.wp-embed-site-icon{position:absolute;top:50%;left:0;-webkit-transform:translateY(-50%);-ms-transform:translateY(-50%);transform:translateY(-50%);height:25px;width:25px;border:0}.wp-embed-site-title{font-weight:700;line-height:25px}.wp-embed-site-title a{position:relative;display:inline-block;padding-left:35px}.wp-embed-meta,.wp-embed-site-title{display:table-cell}.wp-embed-meta{text-align:right;white-space:nowrap;vertical-align:middle}.wp-embed-comments,.wp-embed-share{display:inline}.wp-embed-comments a,.wp-embed-share-tab-button{display:inline-block}.wp-embed-meta a:hover{text-decoration:none;color:#0073aa}.wp-embed-comments a{line-height:25px}.wp-embed-comments+.wp-embed-share{margin-left:10px}.wp-embed-share-dialog{position:absolute;top:0;left:0;right:0;bottom:0;background-color:#222;background-color:rgba(10,10,10,.9);color:#fff;opacity:1;-webkit-transition:opacity .25s ease-in-out;transition:opacity .25s ease-in-out}.wp-embed-share-dialog.hidden{opacity:0;visibility:hidden}.wp-embed-share-dialog-close,.wp-embed-share-dialog-open{margin:-8px 0 0;padding:0;background:0 0;border:none;cursor:pointer;outline:0}.wp-embed-share-dialog-close .dashicons,.wp-embed-share-dialog-open .dashicons{padding:4px}.wp-embed-share-dialog-open .dashicons{top:8px}.wp-embed-share-dialog-close:focus .dashicons,.wp-embed-share-dialog-open:focus .dashicons{-webkit-box-shadow:0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8);box-shadow:0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8);-webkit-border-radius:100%;border-radius:100%}.wp-embed-share-dialog-close{position:absolute;top:20px;right:20px;font-size:22px}.wp-embed-share-dialog-close:hover{text-decoration:none}.wp-embed-share-dialog-close .dashicons{height:24px;width:24px;-webkit-background-size:24px 24px;background-size:24px}.wp-embed-share-dialog-content{height:100%;-webkit-transform-style:preserve-3d;transform-style:preserve-3d;overflow:hidden}.wp-embed-share-dialog-text{margin-top:25px;padding:20px}.wp-embed-share-tabs{margin:0 0 20px;padding:0;list-style:none}.wp-embed-share-tab-button button{margin:0;padding:0;border:none;background:0 0;font-size:16px;line-height:1.3;color:#aaa;cursor:pointer;-webkit-transition:color .1s ease-in;transition:color .1s ease-in}.wp-embed-share-tab-button [aria-selected=true],.wp-embed-share-tab-button button:hover{color:#fff}.wp-embed-share-tab-button+.wp-embed-share-tab-button{margin:0 0 0 10px;padding:0 0 0 11px;border-left:1px solid #aaa}.wp-embed-share-tab[aria-hidden=true]{display:none}p.wp-embed-share-description{margin:0;font-size:14px;line-height:1;font-style:italic;color:#aaa}.wp-embed-share-input{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;width:100%;border:none;height:28px;margin:0 0 10px;padding:0 5px;font:400 14px/1.5 'Open Sans',sans-serif;resize:none;cursor:text}textarea.wp-embed-share-input{height:72px}html[dir=rtl] .wp-embed-featured-image.square{float:right;margin-right:0;margin-left:20px}html[dir=rtl] .wp-embed-site-title a{padding-left:0;padding-right:35px}html[dir=rtl] .wp-embed-site-icon{margin-right:0;margin-left:10px;left:auto;right:0}html[dir=rtl] .wp-embed-meta{text-align:left}html[dir=rtl] .wp-embed-share{margin-left:0;margin-right:10px}html[dir=rtl] .wp-embed-share-dialog-close{right:auto;left:20px}html[dir=rtl] .wp-embed-share-tab-button+.wp-embed-share-tab-button{margin:0 10px 0 0;padding:0 11px 0 0;border-left:none;border-right:1px solid #aaa}
			<?php
		}
	?>
	</style>
	<?php
}

function print_embed_scripts() {
	?>
	<script type="text/javascript">
	<?php
		if ( SCRIPT_DEBUG ) {
			readfile( ABSPATH . WPINC . "/js/wp-embed-template.js" );
		} else {
			?>
			!function(a,b){"use strict";function c(b,c){a.parent.postMessage({message:b,value:c,secret:g},"*")}function d(){function d(){l.className=l.className.replace("hidden",""),b.querySelector('.wp-embed-share-tab-button [aria-selected="true"]').focus()}function e(){l.className+=" hidden",b.querySelector(".wp-embed-share-dialog-open").focus()}function f(a){var c=b.querySelector('.wp-embed-share-tab-button [aria-selected="true"]');c.setAttribute("aria-selected","false"),b.querySelector("#"+c.getAttribute("aria-controls")).setAttribute("aria-hidden","true"),a.target.setAttribute("aria-selected","true"),b.querySelector("#"+a.target.getAttribute("aria-controls")).setAttribute("aria-hidden","false")}function g(a){var c,d,e=a.target,f=e.parentElement.previousElementSibling,g=e.parentElement.nextElementSibling;if(37===a.keyCode)c=f;else{if(39!==a.keyCode)return!1;c=g}"rtl"===b.documentElement.getAttribute("dir")&&(c=c===f?g:f),c&&(d=c.firstElementChild,e.setAttribute("tabindex","-1"),e.setAttribute("aria-selected",!1),b.querySelector("#"+e.getAttribute("aria-controls")).setAttribute("aria-hidden","true"),d.setAttribute("tabindex","0"),d.setAttribute("aria-selected","true"),d.focus(),b.querySelector("#"+d.getAttribute("aria-controls")).setAttribute("aria-hidden","false"))}function h(a){var c=b.querySelector('.wp-embed-share-tab-button [aria-selected="true"]');n!==a.target||a.shiftKey?c===a.target&&a.shiftKey&&(n.focus(),a.preventDefault()):(c.focus(),a.preventDefault())}function i(a){var b,d=a.target;b=d.hasAttribute("href")?d.getAttribute("href"):d.parentElement.getAttribute("href"),b&&(c("link",b),a.preventDefault())}if(!k){k=!0;var j,l=b.querySelector(".wp-embed-share-dialog"),m=b.querySelector(".wp-embed-share-dialog-open"),n=b.querySelector(".wp-embed-share-dialog-close"),o=b.querySelectorAll(".wp-embed-share-input"),p=b.querySelectorAll(".wp-embed-share-tab-button button");if(o)for(j=0;j<o.length;j++)o[j].addEventListener("click",function(a){a.target.select()});if(m&&m.addEventListener("click",function(){d()}),n&&n.addEventListener("click",function(){e()}),p)for(j=0;j<p.length;j++)p[j].addEventListener("click",f),p[j].addEventListener("keydown",g);b.addEventListener("keydown",function(a){27===a.keyCode&&-1===l.className.indexOf("hidden")?e():9===a.keyCode&&h(a)},!1),a.self!==a.top&&(c("height",Math.ceil(b.body.getBoundingClientRect().height)),b.addEventListener("click",i))}}function e(){a.self!==a.top&&(clearTimeout(i),i=setTimeout(function(){c("height",Math.ceil(b.body.getBoundingClientRect().height))},100))}function f(){a.self===a.top||g||(g=a.location.hash.replace(/.*secret=([\d\w]{10}).*/,"$1"),clearTimeout(h),h=setTimeout(function(){f()},100))}var g,h,i,j=b.querySelector&&a.addEventListener,k=!1;j&&(f(),b.documentElement.className=b.documentElement.className.replace(/\bno-js\b/,"")+" js",b.addEventListener("DOMContentLoaded",d,!1),a.addEventListener("load",d,!1),a.addEventListener("resize",e,!1))}(window,document);
			<?php
		}
	?>
	</script>
	<?php
}

function _oembed_filter_feed_content( $content ) {
	return str_replace( '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);"', '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"', $content );
}

function print_embed_comments_button() {
	if ( is_404() || ! ( get_comments_number() || comments_open() ) ) {
		return;
	}
	?>
	<div class="wp-embed-comments">
		<a href="<?php comments_link(); ?>" target="_top">
			<span class="dashicons dashicons-admin-comments"></span>
			<?php
			printf(
				_n(
					'%s <span class="screen-reader-text">Comment</span>',
					'%s <span class="screen-reader-text">Comments</span>',
					get_comments_number()
				),
				number_format_i18n( get_comments_number() )
			);
			?>
		</a>
	</div>
	<?php
}

function print_embed_sharing_button() {
	if ( is_404() ) {
		return;
	}
	?>
	<div class="wp-embed-share">
		<button type="button" class="wp-embed-share-dialog-open" aria-label="<?php esc_attr_e( 'Open sharing dialog' ); ?>">
			<span class="dashicons dashicons-share"></span>
		</button>
	</div>
	<?php
}

function print_embed_sharing_dialog() {
	if ( is_404() ) {
		return;
	}
	?>
	<div class="wp-embed-share-dialog hidden" role="dialog" aria-label="<?php esc_attr_e( 'Sharing options' ); ?>">
		<div class="wp-embed-share-dialog-content">
			<div class="wp-embed-share-dialog-text">
				<ul class="wp-embed-share-tabs" role="tablist">
					<li class="wp-embed-share-tab-button wp-embed-share-tab-button-wordpress" role="presentation">
						<button type="button" role="tab" aria-controls="wp-embed-share-tab-wordpress" aria-selected="true" tabindex="0"><?php esc_html_e( 'WordPress Embed' ); ?></button>
					</li>
					<li class="wp-embed-share-tab-button wp-embed-share-tab-button-html" role="presentation">
						<button type="button" role="tab" aria-controls="wp-embed-share-tab-html" aria-selected="false" tabindex="-1"><?php esc_html_e( 'HTML Embed' ); ?></button>
					</li>
				</ul>
				<div id="wp-embed-share-tab-wordpress" class="wp-embed-share-tab" role="tabpanel" aria-hidden="false">
					<input type="text" value="<?php the_permalink(); ?>" class="wp-embed-share-input" aria-describedby="wp-embed-share-description-wordpress" tabindex="0" readonly/>

					<p class="wp-embed-share-description" id="wp-embed-share-description-wordpress">
						<?php _e( 'Copy and paste this URL into your WordPress site to embed' ); ?>
					</p>
				</div>
				<div id="wp-embed-share-tab-html" class="wp-embed-share-tab" role="tabpanel" aria-hidden="true">
					<textarea class="wp-embed-share-input" aria-describedby="wp-embed-share-description-html" tabindex="0" readonly><?php echo esc_textarea( get_post_embed_html( 600, 400 ) ); ?></textarea>

					<p class="wp-embed-share-description" id="wp-embed-share-description-html">
						<?php _e( 'Copy and paste this code into your site to embed' ); ?>
					</p>
				</div>
			</div>

			<button type="button" class="wp-embed-share-dialog-close" aria-label="<?php esc_attr_e( 'Close sharing dialog' ); ?>">
				<span class="dashicons dashicons-no"></span>
			</button>
		</div>
	</div>
	<?php
}

function the_embed_site_title() {
	$site_title = sprintf(
		'<a href="%s" target="_top"><img src="%s" srcset="%s 2x" width="32" height="32" alt="" class="wp-embed-site-icon"/><span>%s</span></a>',
		esc_url( home_url() ),
		esc_url( get_site_icon_url( 32, admin_url( 'images/w-logo-blue.png' ) ) ),
		esc_url( get_site_icon_url( 64, admin_url( 'images/w-logo-blue.png' ) ) ),
		esc_html( get_bloginfo( 'name' ) )
	);

	$site_title = '<div class="wp-embed-site-title">' . $site_title . '</div>';

	echo apply_filters( 'embed_site_title_html', $site_title );
}

function wp_filter_pre_oembed_result( $result, $url, $args ) {
	$post_id = url_to_postid( $url );

	$post_id = apply_filters( 'oembed_request_post_id', $post_id, $url );

	if ( ! $post_id ) {
		return $result;
	}

	$width = isset( $args['width'] ) ? $args['width'] : 0;

	$data = get_oembed_response_data( $post_id, $width );
	$data = _wp_oembed_get_object()->data2html( (object) $data, $url );

	if ( ! $data ) {
		return $result;
	}

	return $data;
}
