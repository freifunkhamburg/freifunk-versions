<?php

class SimpleTests extends WP_UnitTestCase {
	function test_beautify() {
		$cmplist = array( // examples with and without grep param
			array( 'tp-link-tl-wr740n-nd',  '',         'TP-Link TL-WR740N ND' ),
			array( 'tp-link-tl-wr740n-nd',  'tp-link',  'TL-WR740N ND' ),
			array( 'tp-link-tl-wdr4300',    '',         'TP-Link TL-WDR4300' ),
			array( 'tp-link-tl-wdr4300',    'tp-link',  'TL-WDR4300' ),
			array( 'ubiquiti-unifi',        '',         'Ubiquiti Unifi' ),
			array( 'ubiquiti-unifi',        'ubiquiti', 'Unifi' ),
			array( 'ubiquiti-bullet-m',     '',         'Ubiquiti Bullet M2' ),
			array( 'ubiquiti-bullet-m',     'ubiquiti', 'Bullet M2' ),
			array( 'd-link-dir-615-rev-e1', '',         'D-Link DIR-615 REV E1' ),
			array( 'd-link-dir-615-rev-e1', 'd-link',   'DIR-615 REV E1' ),
		);
		foreach ( $cmplist as $item ) {
			$expect = $item[2];
			$result = ff_hh_beautify_hw_name( $item[0], $item[1] );
			$this->assertEquals( $expect, trim( $result ) );
		}
	}
}
