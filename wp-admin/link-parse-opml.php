<?php
/**
 * Parse OPML XML files and store in globals.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined('ABSPATH') )
	die();

global $opml;

function startElement($parser, $tagName, $attrs) {
	global $names, $urls, $targets, $descriptions, $feeds;

	if ( 'OUTLINE' === $tagName ) {
		$name = '';
		if ( isset( $attrs['TEXT'] ) ) {
			$name = $attrs['TEXT'];
		}
		if ( isset( $attrs['TITLE'] ) ) {
			$name = $attrs['TITLE'];
		}
		$url = '';
		if ( isset( $attrs['URL'] ) ) {
			$url = $attrs['URL'];
		}
		if ( isset( $attrs['HTMLURL'] ) ) {
			$url = $attrs['HTMLURL'];
		}

		// Save the data away.
		$names[] = $name;
		$urls[] = $url;
		$targets[] = isset( $attrs['TARGET'] ) ? $attrs['TARGET'] :  '';
		$feeds[] = isset( $attrs['XMLURL'] ) ? $attrs['XMLURL'] :  '';
		$descriptions[] = isset( $attrs['DESCRIPTION'] ) ? $attrs['DESCRIPTION'] :  '';
	} // End if outline.
}

function endElement($parser, $tagName) {
	// Nothing to do.
}

$xml_parser = xml_parser_create();

xml_set_element_handler($xml_parser, "startElement", "endElement");

if ( ! xml_parse( $xml_parser, $opml, true ) ) {
	printf(
		'XML Error: %1$s at line %2$s',
		xml_error_string( xml_get_error_code( $xml_parser ) ),
		xml_get_current_line_number( $xml_parser )
	);
}

xml_parser_free($xml_parser);
