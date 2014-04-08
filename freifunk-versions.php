<?php
/*
Plugin Name: Freifunk Hamburg Firmware List Shortcode
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk Hamburg Firmware versions
Version: 0.1
Author: Martin Schuette
Author URI: http://mschuette.name/
Licence: 2-clause BSD
*/

define('FF_HH_STABLE_BASEDIR', 'http://gw09.hamburg.freifunk.net/stable/sysupgrade/');
define('FF_HH_CACHETIME', 15);

/* gets metadata from URL, handles caching */
function ff_hh_getmanifest () {
    // Caching
    if ( false === ( $manifest = get_transient( "ff_hh_manifest" ) ) ) {
        $manifest = array();
        $input  = wp_remote_retrieve_body( wp_remote_get(FF_HH_STABLE_BASEDIR . 'manifest') );
        foreach ( explode("\n", $input) as $line ) {
            $ret = sscanf($line, '%s %s %s %s', $hw, $ver, $hash, $filename);
            if ($ret === 4)
                $manifest[] = compact('hw', 'ver', 'filename');
        }

        $cachetime = FF_HH_CACHETIME * MINUTE_IN_SECONDS;
        set_transient( "ff_hh_manifest", $manifest, $cachetime );
    }
    return $manifest;
}

if ( ! shortcode_exists( 'ff_hh_versions' ) ) {
    add_shortcode( 'ff_hh_versions',    'ff_hh_shortcode_handler');
}
// Example:
// [ff_hh_versions]
function ff_hh_shortcode_handler( $atts, $content, $name ) {
    $manifest = ff_hh_getmanifest();
    $outstr = "<div class=\"ff $name\">";
    $outstr .= '<table><tr><th>Modell</th><th>Stable</th></tr>';

    foreach ($manifest as $line) {
        $outstr .= sprintf('<tr><td>%s</td><td><a href="%s%s">%s</a></td></tr>', $line['hw'], FF_HH_STABLE_BASEDIR, $line['filename'], $line['ver']);
    }
    
    $outstr .= '</table>';
    //$outstr .= '<pre>'.print_r($manifest, true).'</pre>';
    $outstr .= '</div>';
    return $outstr;
}

register_uninstall_hook( __FILE__, 'ff_hh_uninstall_hook' );
function ff_hh_uninstall_hook() {
    delete_option( 'ff_hh_manifest' );
}

