#!/usr/bin/php
<?

if (!isset($argv[3])) {
    echo "Usage: $argv[0] <host> <username> <password>\n";
    exit;
}

$host = $argv[1];
$username = $argv[2];
$password = $argv[3];

include( "ESXiTools.class.php" );

$hosts = ESXiTools::getHealth( $host, $username, $password, true );

if (!is_array($hosts)) {
    echo $hosts;
    exit;
}

foreach ($hosts as $hostData) {
    echo "===================================================\n";
    foreach( $hostData as $key => $value ) {
        echo "$key => $value\n";
    }
}

