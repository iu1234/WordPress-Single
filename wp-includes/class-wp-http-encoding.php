<?php

class WP_Http_Encoding {

	public static function compress( $raw, $level = 9, $supports = null ) {
		return gzdeflate( $raw, $level );
	}

	public static function decompress( $compressed, $length = null ) {

		if ( empty($compressed) )
			return $compressed;

		if ( false !== ( $decompressed = @gzinflate( $compressed ) ) )
			return $decompressed;

		if ( false !== ( $decompressed = self::compatible_gzinflate( $compressed ) ) )
			return $decompressed;

		if ( false !== ( $decompressed = @gzuncompress( $compressed ) ) )
			return $decompressed;

		if ( function_exists('gzdecode') ) {
			$decompressed = @gzdecode( $compressed );

			if ( false !== $decompressed )
				return $decompressed;
		}

		return $compressed;
	}

	public static function compatible_gzinflate($gzData) {

		if ( substr($gzData, 0, 3) == "\x1f\x8b\x08" ) {
			$i = 10;
			$flg = ord( substr($gzData, 3, 1) );
			if ( $flg > 0 ) {
				if ( $flg & 4 ) {
					list($xlen) = unpack('v', substr($gzData, $i, 2) );
					$i = $i + 2 + $xlen;
				}
				if ( $flg & 8 )
					$i = strpos($gzData, "\0", $i) + 1;
				if ( $flg & 16 )
					$i = strpos($gzData, "\0", $i) + 1;
				if ( $flg & 2 )
					$i = $i + 2;
			}
			$decompressed = @gzinflate( substr($gzData, $i, -8) );
			if ( false !== $decompressed )
				return $decompressed;
		}

		// Compressed data from java.util.zip.Deflater amongst others.
		$decompressed = @gzinflate( substr($gzData, 2) );
		if ( false !== $decompressed )
			return $decompressed;

		return false;
	}

	public static function accept_encoding( $url, $args ) {
		$type = array();
		$compression_enabled = self::is_available();

		if ( ! $args['decompress'] )
			$compression_enabled = false;
		elseif ( $args['stream'] )
			$compression_enabled = false;
		elseif ( isset( $args['limit_response_size'] ) )
			$compression_enabled = false;

		if ( $compression_enabled ) {
			if ( function_exists( 'gzinflate' ) )
				$type[] = 'deflate;q=1.0';

			if ( function_exists( 'gzuncompress' ) )
				$type[] = 'compress;q=0.5';

			if ( function_exists( 'gzdecode' ) )
				$type[] = 'gzip;q=0.5';
		}

		$type = apply_filters( 'wp_http_accept_encoding', $type, $url, $args );

		return implode(', ', $type);
	}

	public static function content_encoding() {
		return 'deflate';
	}

	public static function should_decode($headers) {
		if ( is_array( $headers ) ) {
			if ( array_key_exists('content-encoding', $headers) && ! empty( $headers['content-encoding'] ) )
				return true;
		} elseif ( is_string( $headers ) ) {
			return ( stripos($headers, 'content-encoding:') !== false );
		}

		return false;
	}

	public static function is_available() {
		return ( function_exists('gzuncompress') || function_exists('gzdeflate') || function_exists('gzinflate') );
	}
}
