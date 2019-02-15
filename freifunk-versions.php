<?php
/*
Plugin Name: Freifunk Hamburg Firmware List Shortcode
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk Hamburg Firmware versions
Version: 0.5dev
Author: Martin Schuette
Author URI: http://mschuette.name/
Licence: 2-clause BSD
*/

define( 'FF_HH_UPDATES_URL', 'https://updates.hamburg.freifunk.net/' );
define( 'FF_HH_CACHETIME', 1 );

/* gets metadata from URL, handles caching */
function ff_hh_getmanifest( $branch_url, $domain, $branch ) {
	// Caching
	$cache_key = 'ff_hh_manifest_' . $domain . '_' . $branch;
	if ( WP_DEBUG || ( false === ( $manifest = get_transient( $cache_key ) ) ) ) {
		$manifest = array();
		$url = $branch_url . '/sysupgrade/' . $branch . '.manifest';
		$http_response = wp_remote_get( $url );  // TODO: error handling
		$input = wp_remote_retrieve_body( $http_response );
		foreach ( explode( "\n", $input ) as $line ) {
			$ret = sscanf( $line, '%s %s %s %s', $hw, $sw_ver, $hash, $filename );
			if ( $ret === 4 ) {
				if ( preg_match( '/^(.*)-v(\d+)$/', $hw, $matches ) ) {
					$hw     = $matches[1];
					$hw_ver = $matches[2];
				} else {
					$hw_ver = '1';
				}
				$manifest[$hw][$hw_ver] = $filename;
			}
		}

		$cachetime = FF_HH_CACHETIME * MINUTE_IN_SECONDS;
		set_transient( $cache_key, $manifest, $cachetime );
	}
	return $manifest;
}

/* gets latest version from first manifest line */
function ff_hh_getlatest( $branch_url, $domain, $branch ) {
	// Caching
	$cache_key = 'ff_hh_latestversion_' . $domain . '_' . $branch;
	if ( false === ( $sw_ver = get_transient( $cache_key ) ) ) {
		$sw_ver = 'unknown';
		$url = $branch_url . '/sysupgrade/' . $branch . '.manifest';
		$input  = wp_remote_retrieve_body( wp_remote_get( $url ) );
		foreach ( explode( "\n", $input ) as $line ) {
			$ret = sscanf( $line, '%s %s %s %s', $hw, $sw_ver, $hash, $filename );
			if ( $ret === 4 ) {
				// break processing on first matching line
				$cachetime = FF_HH_CACHETIME * MINUTE_IN_SECONDS;
				set_transient( $cache_key, $sw_ver, $cachetime );
				break;
			}
		}
	}
	return $sw_ver;
}

if ( ! shortcode_exists( 'ff_hh_latestversion' ) ) {
	add_shortcode( 'ff_hh_latestversion', 'ff_hh_shortcode_latestversion' );
}
// Example:
// [ff_hh_latestversion]
// [ff_hh_latestversion domain="ffhh-sued" branch="experimental"]
function ff_hh_shortcode_latestversion( $atts, $content, $name ) {
	$domain = 'ffhh';
	$branch = 'stable';
	if ( is_array( $atts ) ) {
		if ( array_key_exists( 'domain', $atts ) && ! empty( $atts['domain'] ) ) {
			$domain = $atts['domain'];
		}
		if ( array_key_exists( 'branch', $atts ) && ! empty( $atts['branch'] ) ) {
			$branch = $atts['branch'];
		}
	}

	$branch_url = FF_HH_UPDATES_URL . $domain . '/' . $branch;
	$sw_ver = ff_hh_getlatest( $branch_url, $domain, $branch );
	$outstr = "<span class=\"ff $name\">$sw_ver</span>";
	return $outstr;
}

if ( ! shortcode_exists( 'ff_hh_versions' ) ) {
	add_shortcode( 'ff_hh_versions', 'ff_hh_shortcode_versions' );
}
// Example:
// [ff_hh_versions]
// [ff_hh_versions domain="ffhh-sued" branch="experimental" grep="ubiquiti"]
function ff_hh_shortcode_versions( $atts, $content, $name ) {
	$domain = 'ffhh';
	$branch = 'stable';
	$grep = false;
	$filter = false;
	if ( is_array( $atts ) ) {
		if ( array_key_exists( 'domain', $atts ) && ! empty( $atts['domain'] ) ) {
			$domain = $atts['domain'];
		}
		if ( array_key_exists( 'branch', $atts ) && ! empty( $atts['branch'] ) ) {
			$branch = $atts['branch'];
		}
		if ( array_key_exists( 'grep', $atts ) && ! empty( $atts['grep'] ) ) {
			$grep = $atts['grep'];
		}
		if ( array_key_exists( 'filter', $atts ) && ! empty( $atts['filter'] ) ) {
			$filter = explode ( ',', $atts['filter'] );
		}
	}

	$branch_url = FF_HH_UPDATES_URL . $domain . '/' . $branch;
	$manifest = ff_hh_getmanifest( $branch_url, $domain, $branch );

	$outstr  = "<div class=\"ff $name\">";
	$outstr .= '<table><tr><th>Modell</th><th>Erstinstallation</th><th>Aktualisierung</th></tr>';

	ksort($manifest);
	foreach ( $manifest as $hw => $versions ) {
		// select some models
		if ( $grep && ( false === strpos( $hw, $grep ) ) ) {
			continue;
		}
		// filter others
		if ( $filter ) {
			$filtered = false;
			foreach ( $filter as $flt ) {
				if ( strpos ( $hw, $flt ) !== false ) {
					$filtered = true;
					break;
				}
			}
			if ( $filtered ) {
				continue;
			}
		}

		$hw = ff_hh_beautify_hw_name( $hw, $grep );
		$outstr .= sprintf( "\n<tr><td>%s</td>", $hw );

		// factory versions
		$hw_ver_links = array();
		foreach ( $versions as $hw_ver => $filename ) {
			if ( strpos( $hw, 'Unifi Ac Pro' ) || strpos( $hw, 'Unifi Ac Lite' ) ) {
				continue;
			}

			$filename = str_replace( '-sysupgrade', '', $filename );
			if (strpos($filename,'netgear') !== false) {
				$filename = str_replace( '.bin', '.img', $filename );
				$filename = str_replace( '.tar', '.img', $filename );
			}
			$hw_ver_links[] = sprintf(
				'<a href="%s%s">%s.x</a>',
				$branch_url . '/factory/',
				$filename,
				$hw_ver
			);
		}
		if ( count($hw_ver_links) > 0) {
			$outstr .= '<td>Hardware Ver. ' . join( ', ', $hw_ver_links ) . '</td>';
		} else {
			$outstr .= '<td><i>Benutze das Image</br>zur Aktualisierung</i></td>';
		}

		// sysupgrade versions
		$hw_ver_links = array();
		foreach ( $versions as $hw_ver => $filename ) {
			$hw_ver_links[] = sprintf(
				'<a href="%s%s">%s.x</a>',
				$branch_url . '/sysupgrade/',
				$filename,
				$hw_ver
			);
		}
		$outstr .= '<td>Hardware Ver. ' . join( ', ', $hw_ver_links ) . '</td>';

		$outstr .= '</tr>';
	}

	$outstr .= '</table>';
	$outstr .= '</div>';
	// $outstr .= '<pre>'.print_r( $manifest, true ).'</pre>';
	return $outstr;
}

// some crude rules to add capitalization and whitespace to the
// hardware model name.
// set $discard_vendor to strip the vendor name
// (used for single-vendor lists, e.g. $discard_vendor = 'tp-link' )
function ff_hh_beautify_hw_name( $hw, $discard_vendor = '' ) {
	if ( ! strncmp( $hw, 'tp-link', 7 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', ' ', $hw );
		$hw = str_replace( ' TL ', ' TL-', $hw );
	} elseif ( ! strncmp( $hw, 'ubiquiti', 8 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = str_replace( 'bullet-m', 'bullet-m', $hw );
		$hw = str_replace( '-', ' ', $hw );
		$hw = ucwords( $hw );
	} elseif ( ! strncmp( $hw, 'ubnt', 4 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = str_replace( 'erx', 'ER-X', $hw );
		$hw = str_replace( 'sfp', 'SFP', $hw );
		$hw = trim( $hw, ' -' );
		$hw = ucwords( $hw );
	} elseif ( ! strncmp( $hw, 'd-link', 6 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', ' ', $hw );
		$hw = str_replace( ' DIR ', ' DIR-', $hw );
	} elseif ( ! strncmp( $hw, 'linksys', 7 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', ' ', $hw );
		$hw = str_replace( ' WRT', ' WRT-', $hw );
	} elseif ( ! strncmp( $hw, 'buffalo', 7 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( 'HP-AG300H-WZR-600DHP', 'HP-AG300H & WZR-600DHP', $hw );
		$hw = str_replace( '-WZR', 'WZR', $hw );
	} elseif ( ! strncmp( $hw, 'netgear', 7 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'allnet', 6 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'gl-', 3 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'onion-omega', 11 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
	} elseif ( ! strncmp( $hw, 'alfa', 4 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'wd', 2 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, '8devices', 8 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( 'CARAMBOLA2-BOARD', 'Carambola 2', $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'meraki', 6 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( 'meraki', '', $hw );
		$hw = str_replace( '-', '', $hw );
	} elseif ( ! strncmp( $hw, 'openmesh', 8 ) ) {
		if ( $discard_vendor ) $hw = str_replace( $discard_vendor, '', $hw );
		$hw = strtoupper( $hw );
		$hw = str_replace( 'openmesh', '', $hw );
		$hw = str_replace( '-', '', $hw );
	}
	return $hw;
}
