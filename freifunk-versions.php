<?php
/*
Plugin Name: Freifunk Hamburg Firmware Shortcodes
Plugin URI: https://github.com/freifunkhamburg/freifunk-versions
Description: Shortcodes for Freifunk Hamburg firmware information
Author: Freifunk Hamburg
Author URI: https://github.com/freifunkhamburg
Author: Martin Schuette
Author URI: https://mschuette.name/
Licence: 2-Clause BSD
*/

define( 'FIRMWARE_BASE_URL', 'https://updates.hamburg.freifunk.net' );
define( 'FIRMWARE_CACHETIME', 1 );

// Download firmware list and return the current release.
function firmware_get_release( $base_url, $domain, $branch ) {
    $cache_key = 'ff_firmware_release_' . $domain . '_' . $branch;
    if ( false === ( $sw_ver = get_transient( $cache_key ) ) ) {
        $sw_ver = '???';
        $url = "$base_url/$domain/$branch/images/images.list";
        $http_response = wp_remote_get( $url );
        $input  = wp_remote_retrieve_body( $http_response );
        foreach ( explode( "\n", $input ) as $line ) {
            $ret = sscanf( $line, 'RELEASE=%s', $sw_ver );
            if ( $ret === 1 ) {
                // break processing on first matching line
                $cachetime = FIRMWARE_CACHETIME * MINUTE_IN_SECONDS;
                set_transient( $cache_key, $sw_ver, $cachetime );
                break;
            }
        }
    }
    return $sw_ver;
}

// Download firmware list and return the firmware versions.
// The returned array is structured like this:
// versions[hardware_name][hardware_version][factory|upgrade] => URL
function firmware_get_versions( $base_url, $domain, $branch ) {
    $release = firmware_get_release( $base_url, $domain, $branch );
    $factory_regex = "/^(?:.*$release-)(.*)\.(?:bin|img|img\.gz)$/";
    $upgrade_regex = "/^(?:.*$release-)(.*)(?:-sysupgrade\..*)$/";
    $cache_key = 'ff_firmware_versions_' . $domain . '_' . $branch;
    if ( WP_DEBUG || ( false === ( $versions = get_transient( $cache_key ) ) ) ) {
        $versions = [];
        $url = "$base_url/$domain/$branch/images/images.list";
        $http_response = wp_remote_get( $url );
        $input = wp_remote_retrieve_body( $http_response );

        foreach ( explode( "\n", $input ) as $line ) {
            $ret = sscanf( $line, '%s %s', $folder, $filename );
            if ( $ret !== 2 ) {
                continue;
            }

            $url = "$base_url/$domain/$branch/images/$folder/$filename";
            if ( $folder === 'sysupgrade' ) {
                $result = firmware_parse_version( $filename, $upgrade_regex );
                if ( $result !== NULL ) {
                    $versions[$result[0]][$result[1]]['upgrade'] = $url;
                }
            } else {
                $result = firmware_parse_version( $filename, $factory_regex );
                if ( $result !== NULL ) {
                    $versions[$result[0]][$result[1]]['factory'] = $url;
                }
            }
        }

        $cachetime = FIRMWARE_CACHETIME * MINUTE_IN_SECONDS;
        set_transient( $cache_key, $versions, $cachetime );
    }
    return $versions;
}

function firmware_parse_version( $filename, $regex ) {
    if ( preg_match( $regex, $filename, $matches ) ) {
        $hw = $matches[1];
        $hw_ver = '1';
        if ( preg_match( '/^(.*?)-?v(\d+)$/', $hw, $matches ) ) {
            $hw = $matches[1];
            $hw_ver = $matches[2];
        }
        return [$hw, $hw_ver];
    } else {
        return NULL;
    }
}

// Example:
// [ff_firmware_release]
// [ff_firmware_release domain="ffhh" branch="experimental"]
function firmware_release( $atts, $content, $name ) {
    $domain = 'multi';
    $branch = 'stable';
    if ( is_array( $atts ) ) {
        if ( array_key_exists( 'domain', $atts ) && ! empty( $atts['domain'] ) ) {
            $domain = $atts['domain'];
        }
        if ( array_key_exists( 'branch', $atts ) && ! empty( $atts['branch'] ) ) {
            $branch = $atts['branch'];
        }
    }
    $sw_ver = firmware_get_release( FIRMWARE_BASE_URL, $domain, $branch );
    $outstr = "<span class=\"ff $name\">$sw_ver</span>";
    return $outstr;
}

// Example:
// [ff_firmware_versions]
// [ff_firmware_versions prefix="ubiquiti,ubnt" filter="ac-pro"]
function firmware_versions( $atts, $content, $name ) {
    $domain = 'multi';
    $branch = 'stable';
    $prefix = false;
    $filter = false;
    if ( is_array( $atts ) ) {
        if ( array_key_exists( 'domain', $atts ) && ! empty( $atts['domain'] ) ) {
            $domain = $atts['domain'];
        }
        if ( array_key_exists( 'branch', $atts ) && ! empty( $atts['branch'] ) ) {
            $branch = $atts['branch'];
        }
        if ( array_key_exists( 'prefix', $atts ) && ! empty( $atts['prefix'] ) ) {
            $prefix = explode ( ',', $atts['prefix'] );
        }
        if ( array_key_exists( 'filter', $atts ) && ! empty( $atts['filter'] ) ) {
            $filter = explode ( ',', $atts['filter'] );
        }
    }

    $versions = firmware_get_versions( FIRMWARE_BASE_URL, $domain, $branch );
    if ( $prefix ) {
        $filtered = [];
        foreach ( $versions as $hw => $hw_versions ) {
            foreach ( $prefix as $pfx ) {
                if ( strpos ( $hw, $pfx ) === 0 ) {
                    $hw = substr( $hw, strlen($pfx) );
                    $hw = trim( $hw, '-' );
                    $filtered[$hw] = $hw_versions;
                }
            }
        }
        $versions = $filtered;
    }
    if ( $filter ) {
        $filtered = [];
        foreach ( $versions as $hw => $hw_versions ) {
            foreach ( $filter as $flt ) {
                if ( strpos ( $hw, $flt ) === FALSE ) {
                    $filtered[$hw] = $hw_versions;
                }
            }
        }
        $versions = $filtered;
    }

    $outstr  = "\n<div class=\"ff $name\">";
    $outstr .= "\n<table>\n<tr><th>Modell</th><th>Erstinstallation</th><th>Aktualisierung</th></tr>";

    ksort($versions);
    foreach ( $versions as $hw => $hw_versions ) {
        $hw = firmware_beautify_hw_name( $hw, $prefix );
        $outstr .= sprintf( "\n<tr><td>%s</td>", $hw );

        // factory images
        $factory_links = [];
        foreach ( $hw_versions as $hw_ver => $urls ) {
            if ( ! array_key_exists( 'factory', $urls ) ) {
                continue;
            }
            $factory_url = $urls['factory'];
            $factory_links[] = "<a href=\"$factory_url\">$hw_ver.x</a>";
        }
        if ( count($factory_links) > 0 ) {
            $outstr .= '<td>Version ' . join( ', ', $factory_links ) . '</td>';
        } else {
            $outstr .= '<td></td>';
        }

        // upgrade images
        $upgrade_links = [];
        foreach ( $hw_versions as $hw_ver => $urls ) {
            if ( ! array_key_exists( 'upgrade', $urls ) ) {
                continue;
            }
            $upgrade_url = $urls['upgrade'];
            $upgrade_links[] = "<a href=\"$upgrade_url\">$hw_ver.x</a>";
        }
        if ( count($upgrade_links) > 0 ) {
            $outstr .= '<td>Version ' . join( ', ', $upgrade_links ) . '</td>';
        } else {
            $outstr .= '<td></td>';
        }

        $outstr .= "</tr>";
    }

    $outstr .= "\n</table>";
    $outstr .= "\n</div>\n";

    return $outstr;
}

// Add capitalization and whitespace to the hardware model name.
function firmware_beautify_hw_name( $hw, $prefix ) {
    $vendor = '';
    if ( $prefix ) {
        $vendor = $prefix[0];
    }

    if ( $vendor === '8devices' ) {
        $hw = str_replace( 'carambola2-board', 'carambola 2', $hw );
        $hw = ucwords( $hw );
    } elseif ( $vendor === 'buffalo' ) {
        $hw = str_replace( 'hp-ag300h-wzr-600dhp', 'hp-ag300h & wzr-600dhp', $hw );
        $hw = strtoupper( $hw );
    } elseif ( $vendor === 'd-link' ) {
        $hw = str_replace( '-', ' ', $hw );
        $hw = str_replace( 'dir ', 'dir-', $hw );
        $hw = strtoupper( $hw );
    } elseif ( $vendor === 'onion' ) {
        $hw = ucwords( $hw );
    } elseif ( $vendor === 'tp-link' ) {
        $hw = str_replace( 'n-nd', 'n/nd', $hw );
        $hw = str_replace( '-', ' ', $hw );
        $hw = str_replace( 'tl ', 'tl-', $hw );
        $hw = strtoupper( $hw );
        $hw = str_replace( 'ARCHER', 'Archer', $hw );
    } elseif ( $vendor === 'ubiquiti' ) {
        $hw = str_replace( '-', ' ', $hw );
        $hw = ucwords( $hw );
        $hw = str_replace( 'Ac', 'AC', $hw );
        $hw = str_replace( 'Ap', 'AP', $hw );
        $hw = str_replace( 'Erx', 'ER-X', $hw );
        $hw = str_replace( 'Sfp', 'SFP', $hw );
        $hw = str_replace( 'Xw', 'XW', $hw );
    } else {
        $hw = strtoupper( $hw );
    }

    return $hw;
}

if ( ! shortcode_exists( 'ff_firmware_release' ) ) {
    add_shortcode( 'ff_firmware_release', 'firmware_release' );
}

if ( ! shortcode_exists( 'ff_firmware_versions' ) ) {
    add_shortcode( 'ff_firmware_versions', 'firmware_versions' );
}
