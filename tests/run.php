<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 *   IO_Bit is loaded from vendor/ (composer install) or include_path.
 */

// dynamic property deprecations (PHP 8.2+) are not tested here
error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(dirname(__FILE__));
if (is_readable($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}
set_include_path($root.PATH_SEPARATOR.get_include_path());
require_once 'IO/AV1.php';
require_once 'IO/AV1/Bit.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

// "0101 1..." bit string => bytes (zero padded)
function bits2bytes($s) {
    $s = str_replace(' ', '', $s);
    $s = str_pad($s, (int)ceil(strlen($s) / 8) * 8, '0');
    $o = '';
    foreach (str_split($s, 8) as $b) {
        $o .= chr(bindec($b));
    }
    return $o;
}

/*
 * IO_AV1_Bit::get_le
 */
$bit = new IO_AV1_Bit();
$bit->input("\x01\x02");
try {
    $v = $bit->get_le(2);
} catch (Throwable $e) {
    $v = get_class($e).": ".$e->getMessage();
}
check('get_le', 513, $v);

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
