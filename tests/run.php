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

// OBU with (or without) obu_size field
function obu($type, $payload, $hasSize = true) {
    $h = ($type << 3) | ($hasSize ? 2 : 0);
    return chr($h).($hasSize ? chr(strlen($payload)) : '').$payload;
}

// sequence header bits after the operating points (16x16, 8bit 4:2:0)
function seqTail($frameIdPresent) {
    $s  = '0011 0011 1111 1111';  // frame_width/height_bits_minus_1=3, frame_width/height_minus_1=15
    // frame_id_numbers_present_flag, delta_frame_id_length_minus_2 f(4)=5, additional_frame_id_length_minus_1 f(3)=3
    $s .= $frameIdPresent ? '1 0101 011' : '0';
    $s .= '1 0 1';                // use_128x128_superblock=1 enable_filter_intra=0 enable_intra_edge_filter=1
    $s .= '0 0 0 0 0';            // interintra masked warped dual order_hint
    $s .= '1 1';                  // seq_choose_screen_content_tools=1 seq_choose_integer_mv=1
    $s .= '0 1 0';                // superres=0 cdef=1 restoration=0
    $s .= '0 0 0 0 00 0';         // color_config: high_bitdepth mono_chrome color_description color_range csp(2) separate_uv_delta_q
    $s .= '1';                    // film_grain_params_present=1
    return $s.'1';                // trailing one bit
}

// output and warnings of $obj->dump($opts) (no options when $opts is null)
function dumpOf($obj, $opts = null) {
    $warnings = array();
    set_error_handler(function($no, $str) use (&$warnings) {
        $warnings []= $str;
        return true;
    }, E_ALL & ~E_DEPRECATED);
    ob_start();
    if (is_null($opts)) {
        $obj->dump();
    } else {
        $obj->dump($opts);
    }
    $output = ob_get_clean();
    restore_error_handler();
    return array($output, $warnings);
}

// IVF file (16x16, 30/1 fps) with the given header frame count and frames
function ivf($count, $payloads) {
    $ivf = "DKIF".pack('vv', 0, 32)."AV01".pack('vvVVVV', 16, 16, 30, 1, $count, 0);
    foreach ($payloads as $i => $payload) {
        $ivf .= pack('V', strlen($payload)).pack('P', $i).$payload;
    }
    return $ivf;
}

function parseAV1($data) {
    $av1 = new IO_AV1();
    try {
        $av1->parse($data);
    } catch (Throwable $e) {
        return get_class($e).": ".$e->getMessage();
    }
    return $av1;
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

/*
 * sequence header with timing_info
 */
$s = '000 0 0 1'.sprintf('%032b', 1001).sprintf('%032b', 30000).'0'.'0'. // timing_info, decoder_model_info_present_flag=0
    '0 00000 000000000000 00000'.seqTail(false);
$av1 = parseAV1(obu(1, bits2bytes($s)));
check('timing_info parse', true, is_object($av1));
if (is_object($av1)) {
    $sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
    check('timing_info', array('num_units_in_display_tick' => 1001, 'time_scale' => 30000,
                               'equal_picture_interval' => 0), $sh['timing_info']);
    check('timing_info frame_width_minus_1', 15, $sh['frame_width_minus_1']);
    check('timing_info film_grain_params_present', 1, $sh['film_grain_params_present']);
}

/*
 * sequence header with decoder_model_info and operating_parameters_info (5.5.4, 5.5.5)
 */
$s = '000 0 0 1'.sprintf('%032b', 1001).sprintf('%032b', 30000).'1 1'. // timing_info, num_ticks_per_picture_minus_1=0
    '1'.                                                // decoder_model_info_present_flag
    '01001'.sprintf('%032b', 90000).'00100 00111'.      // decoder_model_info (buffer_delay_length_minus_1=9)
    '1 00001'.                                          // initial_display_delay_present_flag, operating_points_cnt_minus_1=1
    // op 0: idc=0x101 seq_level_idx=8 seq_tier=1, decoder model present (f(10) 700, f(10) 300, low_delay 1), delay 9
    sprintf('%012b', 0x101).'01000 1'.'1'.sprintf('%010b', 700).sprintf('%010b', 300).'1'.'1 1001'.
    // op 1: idc=0x103 seq_level_idx=5, no decoder model, no initial display delay
    sprintf('%012b', 0x103).'00101'.'0'.'0'.
    seqTail(false);
$av1 = parseAV1(obu(1, bits2bytes($s)));
check('decoder_model_info parse', true, is_object($av1));
if (is_object($av1)) {
    $sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
    check('decoder_model_info', array('buffer_delay_length_minus_1' => 9, 'num_units_in_decoding_tick' => 90000,
                                      'buffer_removal_time_length_minus_1' => 4,
                                      'frame_presentation_time_length_minus_1' => 7), $sh['decoder_model_info']);
    check('decoder_model_present_for_this_op', array(1, 0), $sh['decoder_model_present_for_this_op']);
    check('operating_parameters_info', array(0 => array('decoder_buffer_delay' => 700, 'encoder_buffer_delay' => 300,
                                                        'low_delay_mode_flag' => 1)),
          $sh['operating_parameters_info']);
    check('decoder_model seq_tier', array(1, 0), $sh['seq_tier']);
    check('decoder_model initial_display_delay_minus_1', array(0 => 9), $sh['initial_display_delay_minus_1']);
    check('decoder_model operating_point_idc', array(0x101, 0x103), $sh['operating_point_idc']);
    check('decoder_model frame_width_minus_1', 15, $sh['frame_width_minus_1']);
    check('decoder_model film_grain_params_present', 1, $sh['film_grain_params_present']);
    list($output, $warnings) = dumpOf($av1, array());
    check('decoder_model dump warnings', array(), $warnings);
    foreach (array("    decoder_model_info:\n      buffer_delay_length_minus_1:9 num_units_in_decoding_tick:90000\n".
                   "      buffer_removal_time_length_minus_1:4 frame_presentation_time_length_minus_1:7\n",
                   "        decoder_model_present_for_this_op_0:1\n          operating_parameters_info_0:\n".
                   "            decoder_buffer_delay:700 encoder_buffer_delay:300 low_delay_mode_flag:1\n",
                   "        decoder_model_present_for_this_op_1:0\n") as $line) {
        check('decoder_model dump '.strtok($line, ':'), true, strpos($output, $line) !== false);
    }
    check('decoder_model dump not implemented', false, strpos($output, 'not implemented') !== false);
}

/*
 * sequence header: additional_frame_id_length_minus_1 is f(3)
 */
$av1 = parseAV1(obu(1, bits2bytes('000 0 0 0'.'0 00000 000000000000 00000'.seqTail(true))));
$sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
$v = array();
foreach (array('delta_frame_id_length_minus_2', 'additional_frame_id_length_minus_1',
               'use_128x128_superblock', 'enable_filter_intra', 'enable_intra_edge_filter',
               'enable_cdef', 'film_grain_params_present') as $k) {
    $v[$k] = $sh[$k];
}
check('frame_id fields', array('delta_frame_id_length_minus_2' => 5, 'additional_frame_id_length_minus_1' => 3,
                               'use_128x128_superblock' => 1, 'enable_filter_intra' => 0,
                               'enable_intra_edge_filter' => 1, 'enable_cdef' => 1,
                               'film_grain_params_present' => 1), $v);

/*
 * obu_size of an OBU without obu_size field (not the first OBU)
 */
$seq = bits2bytes('000 0 0 0'.'0 00000 000000000000 00000'.seqTail(false));
$av1 = parseAV1(obu(2, '').obu(1, $seq, false));
$v = array();
foreach ($av1->OBU->OBUs as $o) {
    $v []= array($o['obu_header']['obu_type'], $o['obu_size'], $o['_offset']);
}
check('obu_size without size field', array(array(2, 0, 2), array(1, strlen($seq), 3)), $v);

/*
 * IO_AV1::dump() without options
 */
list($output, $warnings) = dumpOf(parseAV1(obu(2, '')));
check('dump without options warnings', array(), $warnings);
check('dump without options output', true, strpos($output, "[0]obu_type:2 (OBU_TEMPORAL_DELIMITER)") !== false);

/*
 * dump of initial_display_delay (present_for_this_op 0 and 1)
 */
foreach (array('0' => null, '1 0101' => 5) as $bits => $minus1) {
    $s = '000 0 0 0'.'1 00000 000000000000 00000 '.$bits.seqTail(false);
    list($output, $warnings) = dumpOf(parseAV1(obu(1, bits2bytes($s))), array());
    $present = is_null($minus1) ? 0 : 1;
    check("initial_display_delay $present warnings", array(), $warnings);
    check("initial_display_delay $present present_for_this_op", true,
          strpos($output, "initial_display_delay_present_for_this_op_0:$present\n") !== false);
    check("initial_display_delay $present minus_1", !is_null($minus1),
          strpos($output, "initial_display_delay_minus_1_0:$minus1\n") !== false);
}

/*
 * IO_AV1_IVF::isIVF with an unsupported version
 */
try {
    $v = IO_AV1_IVF::isIVF("DKIF\x01\x00".str_repeat("\0", 26));
} catch (Throwable $e) {
    $v = get_class($e).": ".$e->getMessage();
}
check('isIVF version 1', false, $v);

/*
 * IVF dump header line
 */
$payload = obu(2, '');
$av1 = parseAV1(ivf(1, array($payload)));
check('ivf parse', true, is_object($av1));
if (is_object($av1)) {
    list($output, $warnings) = dumpOf($av1, array());
    check('ivf dump header', true, strpos($output, "codec:AV01 width:16 height:16 frameRate:30=30/1") !== false);
}

/*
 * IVF frame count: header value as is, frames are read until the data ends
 */
foreach (array(array(2, 2), array(0, 2), array(0xFFFFFFFF, 2), array(1, 1)) as $c) {
    list($count, $nframes) = $c;
    $ivf = ivf($count, array_fill(0, $nframes, $payload));
    $av1 = parseAV1($ivf);
    check("ivf header count $count parse", true, is_object($av1));
    if (! is_object($av1)) {
        continue;
    }
    check("ivf header count $count frameNum", $count, $av1->IVF->frameNum);
    check("ivf header count $count getFrameNum", $nframes, $av1->IVF->getFrameNum());
    check("ivf header count $count frames", $nframes, count($av1->IVF->frames));
    // dump in a child process to see stderr
    $tmp = tempnam(sys_get_temp_dir(), 'ivf');
    file_put_contents($tmp, $ivf);
    $code = 'error_reporting(E_ALL & ~E_DEPRECATED); require_once "IO/AV1.php"; '.
        '$a = new IO_AV1(); $a->parse(file_get_contents($argv[1])); $a->dump(array());';
    $cmd = escapeshellarg(PHP_BINARY).' -d display_errors=stderr -d include_path='.
        escapeshellarg(get_include_path()).' -r '.escapeshellarg($code).' '.escapeshellarg($tmp);
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    unlink($tmp);
    check("ivf header count $count dump frameNum", true, strpos($stdout, "frameNum:$count\n") !== false);
    check("ivf header count $count dump last frame", true, strpos($stdout, "[".($nframes - 1)."]frameSize:") !== false);
    $expect = ($count === $nframes) ? '' : "Warning: frameNum:$count (header) != count(frames):$nframes\n";
    check("ivf header count $count dump stderr", $expect, $stderr);
}

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
