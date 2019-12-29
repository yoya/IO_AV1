<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/AV1.php';
}

$options = getopt("f:hvtdR");

if ((isset($options['f']) === false) || (($options['f'] !== "-") && is_readable($options['f']) === false)) {
    fprintf(STDERR, "Usage: php av1dump.php -f <av1_file> [-htvd]\n");
    fprintf(STDERR, "ex) php av1dump.php -f test.ivf -t \n");
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$av1data = file_get_contents($filename);

$opts = array(
    'hexdump'  => isset($options['h']),
    'typeonly' => isset($options['t']),
    'verbose'  => isset($options['v']),
    'debug'    => isset($options['d']),
    'restrict' => isset($options['r']),
);

$av1 = new IO_AV1();
try {
    $av1->parse($av1data, $opts);
} catch (Exception $e) {
    echo "ERROR: av1dump: $filename:".PHP_EOL;
    echo $e->getMessage()." file:".$e->getFile()." line:".$e->getLine().PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
    exit (1);
}

$av1->dump($opts);
