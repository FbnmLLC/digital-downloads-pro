<?php
define( "_VALID_PHP", true );

define( 'BASEPATH', dirname( dirname( dirname( __FILE__ ) ) ) . '/' );

$configFile = BASEPATH . "lib/config.ini.php";

if ( file_exists( $configFile ) ) {
	require_once( $configFile );
} else {
	exit( "Configuration file is missing. 123pay Installer can not continue." );
}

$configFile = BASEPATH . "lib/config.ini.php";

require_once( BASEPATH . "lib/class_registry.php" );

require_once( BASEPATH . "lib/class_db.php" );

Registry::set( 'Database', new Database( DB_SERVER, DB_USER, DB_PASS, DB_DATABASE ) );
$db = Registry::get( "Database" );
$db->connect();

$db->query( "INSERT INTO `gateways` (`name`, `displayname`, `dir`, `demo`, `extra_txt`, `extra_txt2`, `extra_txt3`, `extra`, `extra2`, `extra3`, `info`, `active`) VALUES ('_123pay', '_123pay', '_123pay', 0, '_123pay_merchant_id', 'Currency Code', '', '', 'IRR', '', 'https://123pay.ir', 1);" );

echo 'Setup done.<br>Now check if you can see 123pay in gateways list in admin.<br> After that remove the file _123pay-setup.php from _123pay directory in gateways.';
die();