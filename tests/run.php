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

/*
 * test data (tests/data/make.php)
 */
$datadir = $root.'/tests/data';

// warnings and notices (except deprecations) outside dumpOf() are failures
$warnings = array();
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    global $warnings;
    $warnings []= "$errstr at $errfile:$errline";
    return true;
}, E_ALL & ~E_DEPRECATED);

// run php in a child process with a time limit, returns [status, stdout, stderr]
function runPHP($args, $stdin = '') {
    global $root;
    $cmd = array('timeout', '20', PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0',
                 '-d', 'error_reporting='.(E_ALL & ~E_DEPRECATED), '-d', 'include_path='.get_include_path());
    $cmd = implode(' ', array_map('escapeshellarg', array_merge($cmd, $args)));
    $proc = proc_open($cmd, array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')), $pipes, $root);
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(proc_close($proc), $stdout, $stderr);
}

function pick($arr, $keys) {
    $values = array();
    foreach ($keys as $k) {
        $values[$k] = array_key_exists($k, $arr) ? $arr[$k] : '(unset)';
    }
    return $values;
}
// [obu_type, obu_extension_flag, obu_has_size_field, obu_size, _offset] of every OBU
function obuSummary($obu) {
    $list = array();
    foreach ($obu->OBUs as $o) {
        $h = $o['obu_header'];
        $list []= array($h['obu_type'], $h['obu_extension_flag'], $h['obu_has_size_field'], $o['obu_size'], $o['_offset']);
    }
    return $list;
}
function parseFile($name) {
    global $datadir;
    return parseAV1(file_get_contents("$datadir/$name"));
}
function dumpLines($name, $output, $lines) {
    foreach ($lines as $line) {
        check("$name dump ".trim(strtok($line, "\n")), true, strpos($output, $line) !== false);
    }
}
$toolKeys = array('frame_width_bits_minus_1', 'frame_height_bits_minus_1', 'frame_width_minus_1', 'frame_height_minus_1',
                  'frame_id_numbers_present_flag', 'use_128x128_superblock', 'enable_filter_intra', 'enable_intra_edge_filter',
                  'enable_interintra_compound', 'enable_masked_compound', 'enable_warped_motion', 'enable_dual_filter',
                  'enable_order_hint', 'enable_jnt_comp', 'enable_ref_frame_mvs', 'seq_force_screen_content_tools',
                  'seq_force_integer_mv', 'enable_superres', 'enable_cdef', 'enable_restoration', 'film_grain_params_present');

/*
 * basic.obu: TD, sequence header, frame (extension header), metadata (3 byte leb128),
 *   padding (2 byte leb128), frame header without obu_size
 */
$av1 = parseFile('basic.obu');
check('basic.obu parse', true, is_object($av1));
check('basic.obu not IVF', null, $av1->IVF);
check('basic.obu OBUs', array(
    array(2, 0, 1, 0, 2),
    array(1, 0, 1, 16, 4),
    array(6, 1, 1, 4, 23),
    array(5, 0, 1, 3, 31),
    array(15, 0, 1, 200, 37),
    array(3, 0, 0, 2, 238),
), obuSummary($av1->OBU));
check('basic.obu extension header', array('temporal_id' => 2, 'spatial_id' => 1, 'reserved_3bits' => 0),
      $av1->OBU->OBUs[2]['obu_header']['obu_extention_header']);
check('basic.obu forbidden / reserved bits', array(0, 0),
      array($av1->OBU->OBUs[1]['obu_header']['obu_forbidden_bit'], $av1->OBU->OBUs[1]['obu_header']['obu_reserved_1bit']));
$sh = $av1->OBU->OBUs[1]['sequence_header_obu'];
check('basic.obu sequence header', array(
    'seq_profile' => 0, 'still_picture' => 0, 'reduced_still_picture_header' => 0, 'timing_info_present_flag' => 0,
    'decoder_model_info_present_flag' => 0, 'initial_display_delay_present_flag' => 0, 'operating_points_cnt_minus_1' => 0,
    'operating_point_idc' => array(0), 'seq_level_idx' => array(8), 'seq_tier' => array(1),
    'decoder_model_present_for_this_op' => array(0)),
    pick($sh, array('seq_profile', 'still_picture', 'reduced_still_picture_header', 'timing_info_present_flag',
                    'decoder_model_info_present_flag', 'initial_display_delay_present_flag', 'operating_points_cnt_minus_1',
                    'operating_point_idc', 'seq_level_idx', 'seq_tier', 'decoder_model_present_for_this_op')));
check('basic.obu tools', array_combine($toolKeys, array(10, 10, 1919, 1079, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0)),
      pick($sh, $toolKeys));
check('basic.obu frame id / order hint', array(5, 3, 0, 1, 0, 6),
      array($sh['delta_frame_id_length_minus_2'], $sh['additional_frame_id_length_minus_1'],
            $sh['seq_choose_screen_content_tools'], $sh['seq_force_screen_content_tools'],
            $sh['seq_choose_integer_mv'], $sh['order_hint_bits_minus_1']));
check('basic.obu color_config', array('high_bitdepth' => 0, 'mono_chrome' => 0, 'color_description_present_flag' => 1,
                                      'color_primaries' => 1, 'transfer_characteristics' => 1, 'matrix_coefficients' => 1,
                                      'color_range' => 1, 'subsampling_x' => 1, 'subsampling_y' => 1,
                                      'chroma_sample_position' => 1, 'separate_uv_delta_q' => 1), $sh['color_config']);
check('basic.obu derived values', array(0, 7, 8, 3, 0),
      array($av1->OBU->OperatingPointIdc, $av1->OBU->OrderHintBits, $av1->OBU->BitDepth, $av1->OBU->NumPlanes,
            $av1->OBU->SeenFrameHeader));
list($output, $w) = dumpOf($av1, array());
check('basic.obu dump warnings', array(), $w);
dumpLines('basic.obu', $output, array(
    "  [0]obu_type:2 (OBU_TEMPORAL_DELIMITER)  obu_size:0\n",
    "  [1]obu_type:1 (OBU_SEQUENCE_HEADER)  obu_size:16\n",
    "    obu_extention_header:\n      temporal_id:2 spatial_id:1 reserved_3bits:0\n",
    "  [4]obu_type:15 (OBU_PADDING)  obu_size:200\n",
    "  [5]obu_type:3 (OBU_FRAME_HEADER)  (obu_size:2)\n",
    "      seq_tier_0:1\n",
    "    frame_width_minus_1:1919 frame_height_minus_1:1079\n",
    "      delta_frame_id_length_minus_2:5 additional_frame_id_length_minus_1:3\n",
    "        seq_force_screen_content_tools:1\n",
    "          seq_force_integer_mv:1\n",
    "          order_hint_bits_minus_1:6 OrderHintBits:7\n",
    "        color_primaries:1(BT_709)\n",
    "        chroma_sample_position:1(CSP_VERTICAL)\n",
));
// hexdump option: each OBU payload
list($output, $w) = dumpOf($av1, array('hexdump' => true));
check('basic.obu hexdump warnings', array(), $w);
check('basic.obu hexdump padding', 200, substr_count($output, "aa "));
check('basic.obu hexdump frame header', true, strpos($output, " 55 66 ") !== false);
check('basic.obu hexdump count', 6, substr_count($output, "  0123456789abcdef\n"));

/*
 * timing.obu: timing_info, decoder_model_info, 3 operating points
 */
$av1 = parseFile('timing.obu');
check('timing.obu parse', true, is_object($av1));
$sh = $av1->OBU->OBUs[1]['sequence_header_obu'];
check('timing.obu timing_info', array('num_units_in_display_tick' => 1001, 'time_scale' => 60000,
                                      'equal_picture_interval' => 1, 'num_ticks_per_picture_minus_1' => 1), $sh['timing_info']);
check('timing.obu decoder_model_info', array('buffer_delay_length_minus_1' => 15, 'num_units_in_decoding_tick' => 60000,
                                             'buffer_removal_time_length_minus_1' => 23,
                                             'frame_presentation_time_length_minus_1' => 15), $sh['decoder_model_info']);
check('timing.obu operating points', array(
    'operating_points_cnt_minus_1' => 2,
    'operating_point_idc' => array(0x307, 0x103, 0x101),
    'seq_level_idx' => array(12, 9, 4),
    'seq_tier' => array(0, 1, 0),
    'decoder_model_present_for_this_op' => array(1, 0, 1),
    'operating_parameters_info' => array(
        0 => array('decoder_buffer_delay' => 0x1234, 'encoder_buffer_delay' => 0x0567, 'low_delay_mode_flag' => 0),
        2 => array('decoder_buffer_delay' => 1, 'encoder_buffer_delay' => 2, 'low_delay_mode_flag' => 1)),
    'initial_display_delay_present_for_this_op' => array(1, 0, 1),
    'initial_display_delay_minus_1' => array(0 => 3, 2 => 15)),
    pick($sh, array('operating_points_cnt_minus_1', 'operating_point_idc', 'seq_level_idx', 'seq_tier',
                    'decoder_model_present_for_this_op', 'operating_parameters_info',
                    'initial_display_delay_present_for_this_op', 'initial_display_delay_minus_1')));
check('timing.obu OperatingPointIdc (operating point 0)', 0x307, $av1->OBU->OperatingPointIdc);
check('timing.obu size', array(160 - 1, 90 - 1, 10), array($sh['frame_width_minus_1'], $sh['frame_height_minus_1'], $av1->OBU->BitDepth));
check('timing.obu color_config', array('color_primaries' => 2, 'transfer_characteristics' => 2, 'matrix_coefficients' => 2,
                                       'color_range' => 0, 'chroma_sample_position' => 2, 'separate_uv_delta_q' => 0),
      pick($sh['color_config'], array('color_primaries', 'transfer_characteristics', 'matrix_coefficients',
                                      'color_range', 'chroma_sample_position', 'separate_uv_delta_q')));
list($output, $w) = dumpOf($av1, array());
check('timing.obu dump warnings', array(), $w);
dumpLines('timing.obu', $output, array(
    "      num_units_in_display_tick:1001 time_scale:60000 equal_picture_interval:1\n        num_ticks_per_picture_minus_1:1\n",
    "            decoder_buffer_delay:4660 encoder_buffer_delay:1383 low_delay_mode_flag:0\n",
    "        decoder_model_present_for_this_op_1:0\n",
    "        initial_display_delay_present_for_this_op_1:0\n      operating_point_idc_2:257\n",
    "          initial_display_delay_minus_1_2:15\n",
    "      (seq_tier_2:0)\n",
    "    OperatingPointIdc:775\n",
    "        chroma_sample_position:2(CSP_COLOCAED)\n",
));

/*
 * still.obu: reduced_still_picture_header, profile 1 sRGB
 */
$av1 = parseFile('still.obu');
check('still.obu parse', true, is_object($av1));
$sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
check('still.obu sequence header', array(
    'seq_profile' => 1, 'still_picture' => 1, 'reduced_still_picture_header' => 1, 'timing_info_present_flag' => 0,
    'operating_points_cnt_minus_1' => 0, 'operating_point_idc' => array(0), 'seq_level_idx' => array(31),
    'seq_tier' => array(0), 'decoder_model_present_for_this_op' => array(0),
    'initial_display_delay_present_for_this_op' => array(0)),
    pick($sh, array('seq_profile', 'still_picture', 'reduced_still_picture_header', 'timing_info_present_flag',
                    'operating_points_cnt_minus_1', 'operating_point_idc', 'seq_level_idx', 'seq_tier',
                    'decoder_model_present_for_this_op', 'initial_display_delay_present_for_this_op')));
check('still.obu tools', array_combine($toolKeys, array(15, 15, 65535, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 1)),
      pick($sh, $toolKeys));
check('still.obu color_config (sRGB)', array('high_bitdepth' => 1, 'mono_chrome' => 0, 'color_description_present_flag' => 1,
                                             'color_primaries' => 1, 'transfer_characteristics' => 13, 'matrix_coefficients' => 0,
                                             'color_range' => 1, 'subsampling_x' => 0, 'subsampling_y' => 0,
                                             'separate_uv_delta_q' => 0), $sh['color_config']);
check('still.obu derived values', array(0, 10, 3), array($av1->OBU->OrderHintBits, $av1->OBU->BitDepth, $av1->OBU->NumPlanes));
list($output, $w) = dumpOf($av1, array());
check('still.obu dump warnings', array(), $w);
dumpLines('still.obu', $output, array(
    " (seq_level_idx[0]:31)",
    "    frame_width_minus_1:65535 frame_height_minus_1:0\n",
    "        transfer_characteristics:13(TC_SRGB)\n",
    "        matrix_coefficients:0(MC_IDENTITY)\n",
    "        (color_range:1)\n        (subsampling_x:0) (subsampling_y:0)\n",
    "    film_grain_params_present:1\n",
));

/*
 * profile2.obu: 12 bit 4:2:2, mono.obu: 10 bit monochrome
 */
$av1 = parseFile('profile2.obu');
check('profile2.obu parse', true, is_object($av1));
$sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
check('profile2.obu color_config', array('high_bitdepth' => 1, 'twelve_bit' => 1, 'mono_chrome' => 0,
                                         'color_description_present_flag' => 1, 'color_primaries' => 9,
                                         'transfer_characteristics' => 16, 'matrix_coefficients' => 9,
                                         'color_range' => 1, 'subsampling_x' => 1, 'subsampling_y' => 0,
                                         'separate_uv_delta_q' => 1), $sh['color_config']);
check('profile2.obu derived values', array(12, 3), array($av1->OBU->BitDepth, $av1->OBU->NumPlanes));
list($output, $w) = dumpOf($av1, array());
check('profile2.obu dump warnings', array(), $w);
dumpLines('profile2.obu', $output, array(
    "        twelve_bit:1\n        BitDepth:12\n",
    "        transfer_characteristics:16(TC_SMPTE_2084)\n",
    "          subsampling_x:1\n            subsampling_y:0\n",
));
$av1 = parseFile('mono.obu');
check('mono.obu parse', true, is_object($av1));
$sh = $av1->OBU->OBUs[0]['sequence_header_obu'];
check('mono.obu color_config', array('high_bitdepth' => 1, 'mono_chrome' => 1, 'color_description_present_flag' => 0,
                                     'color_primaries' => 2, 'transfer_characteristics' => 2, 'matrix_coefficients' => 2,
                                     'color_range' => 1, 'subsampling_x' => 1, 'subsampling_y' => 1,
                                     'separate_uv_delta_q' => 0),
      pick($sh['color_config'], array('high_bitdepth', 'mono_chrome', 'color_description_present_flag', 'color_primaries',
                                      'transfer_characteristics', 'matrix_coefficients', 'color_range',
                                      'subsampling_x', 'subsampling_y', 'separate_uv_delta_q')));
check('mono.obu derived values', array(10, 1), array($av1->OBU->BitDepth, $av1->OBU->NumPlanes));
check('mono.obu film_grain after color_config', 0, $sh['film_grain_params_present']);
list($output, $w) = dumpOf($av1, array());
check('mono.obu dump warnings', array(), $w);
check('mono.obu dump NumPlanes', true, strpos($output, "      NumPlanes:1\n") !== false);

/*
 * av1.ivf: IVF header, 3 frames, 64 bit timestamp
 */
$data = file_get_contents("$datadir/av1.ivf");
check('av1.ivf isIVF', true, IO_AV1_IVF::isIVF($data));
$av1 = parseAV1($data);
check('av1.ivf parse', true, is_object($av1));
$ivf = $av1->IVF;
check('av1.ivf not OBU', null, $av1->OBU);
check('av1.ivf header', array('DKIF', 0, 32, 'AV01', 16, 16, 30000, 1001, 3),
      array($ivf->signature, $ivf->version, $ivf->headerLength, $ivf->codec, $ivf->width, $ivf->height,
            $ivf->fpsNum, $ivf->fpsDenom, $ivf->frameNum));
check('av1.ivf fps', 30000 / 1001, $ivf->fps);
check('av1.ivf getFrameNum', 3, $ivf->getFrameNum());
$v = array();
foreach ($ivf->frames as $frame) {
    $v []= array($frame['frameSize'], $frame['timestamp'], strlen($frame['payload']));
}
check('av1.ivf frames', array(array(24, 0, 24), array(6, 1, 6), array(4, 0x100000002, 4)), $v);
check('av1.ivf getPayload(1)', "\x12\x00\x32\x02\x11\x12", $ivf->getPayload(1));
check('av1.ivf frame 0 OBUs', array(array(2, 0, 1, 0, 2), array(1, 0, 1, 9, 4), array(6, 0, 1, 9, 15)),
      obuSummary($ivf->frames[0]['obu']));
check('av1.ivf frame 2 OBUs', array(array(2, 0, 1, 0, 2), array(6, 0, 0, 1, 3)), obuSummary($ivf->frames[2]['obu']));
check('av1.ivf frame 0 sequence header size', array('frame_width_minus_1' => 15, 'frame_height_minus_1' => 15),
      pick($ivf->frames[0]['obu']->OBUs[1]['sequence_header_obu'], array('frame_width_minus_1', 'frame_height_minus_1')));
list($output, $w) = dumpOf($av1, array());
check('av1.ivf dump warnings', array(), $w);
dumpLines('av1.ivf', $output, array(
    "signature:DKIF version:0 headerLength:32\n",
    "codec:AV01 width:16 height:16 frameRate:".(30000 / 1001)."=30000/1001 frameNum:3\n",
    "[0]frameSize:24 time:0 payload:12 00 0a 09 00 00 00 01 ...\n",
    "[1]frameSize:6 time:1 payload:12 00 32 02 11 12 ...\n",
    "[2]frameSize:4 time:4294967298 payload:12 00 30 21 ...\n",
    "  [1]obu_type:6 (OBU_FRAME)  (obu_size:1)\n",
));
check('isIVF short data', false, IO_AV1_IVF::isIVF(substr($data, 0, 31)));
check('isIVF bad signature', false, IO_AV1_IVF::isIVF("DKIX".substr($data, 4)));

/*
 * broken input
 */
check('truncated.ivf', 'IO_Bit_Exception: getData: 101 < 98 + 4', parseFile('truncated.ivf'));
check('truncated.ivf frame header', true,
      strpos(parseAV1(substr($data, 0, 32 + 6)), 'IO_Bit_Exception: ') === 0);
check('size0.obu', 'Exception: obusize == 0', parseFile('size0.obu'));
check('seq_truncated.obu', true, strpos(parseFile('seq_truncated.obu'), 'IO_Bit_Exception: ') === 0);
check('profile3.obu', 'Exception: must be seq_profile:3 <= 2)', parseFile('profile3.obu'));
$av1 = parseAV1('');
check('empty input', array(), $av1->OBU->OBUs);

/*
 * sample/av1dump.php on the test data
 */
foreach (array('basic.obu' => "  [5]obu_type:3 (OBU_FRAME_HEADER)  (obu_size:2)\n",
               'timing.obu' => "    OperatingPointIdc:775\n",
               'still.obu' => "    film_grain_params_present:1\n",
               'profile2.obu' => "        BitDepth:12\n",
               'mono.obu' => "      NumPlanes:1\n",
               'av1.ivf' => "[2]frameSize:4 time:4294967298") as $file => $expect) {
    list($status, $stdout, $stderr) = runPHP(array($root.'/sample/av1dump.php', '-f', "$datadir/$file"));
    check("av1dump $file exit status", 0, $status);
    check("av1dump $file no warnings", '', $stderr);
    check("av1dump $file output", true, strpos($stdout, $expect) !== false);
}
list($status, $stdout, $stderr) = runPHP(array($root.'/sample/av1dump.php', '-h', '-f', '-'), file_get_contents("$datadir/basic.obu"));
check('av1dump -h stdin exit status', 0, $status);
check('av1dump -h stdin no warnings', '', $stderr);
check('av1dump -h stdin hexdump', true, strpos($stdout, "             0  1  2  3  4  5  6  7   8  9  a  b  c  d  e  f  0123456789abcdef\n") !== false);
foreach (array('size0.obu' => 'obusize == 0', 'truncated.ivf' => 'getData: ') as $file => $expect) {
    list($status, $stdout, $stderr) = runPHP(array($root.'/sample/av1dump.php', '-f', "$datadir/$file"));
    check("av1dump $file exit status", 1, $status);
    check("av1dump $file error", true, (strpos($stdout, "ERROR: av1dump: ") === 0) && (strpos($stdout, $expect) !== false));
}
list($status, $stdout, $stderr) = runPHP(array($root.'/sample/av1dump.php'));
check('av1dump without -f exit status', 1, $status);
check('av1dump without -f usage', true, strpos($stderr, "Usage: php av1dump.php") === 0);

check('no warnings in this process', array(), $warnings);

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
