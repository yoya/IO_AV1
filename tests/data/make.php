<?php
/*
 * generator of the synthetic test data (AV1 OBU streams, IVF)
 * usage: php tests/data/make.php
 *   writes every file in tests/data/ (byte-identical on each run)
 * ref) AV1 Bitstream & Decoding Process Specification (5.3 OBU, 5.5 sequence header)
 *      https://wiki.multimedia.cx/index.php/IVF
 *
 * Sequence headers follow the syntax; frame / tile / metadata payloads are filler.
 */

$dir = dirname(__FILE__);

function f($v, $n) { return $n ? sprintf("%0{$n}b", $v) : ''; }
function uvlc($v) {
    $b = decbin($v + 1);
    return str_repeat('0', strlen($b) - 1).$b;
}
function leb128($v, $minBytes = 1) {
    $o = '';
    for ($i = 0; ($i === 0) || ($v > 0) || ($i < $minBytes); $i++) {
        $byte = $v & 0x7f;
        $v >>= 7;
        if (($v > 0) || ($i + 1 < $minBytes)) {
            $byte |= 0x80;
        }
        $o .= chr($byte);
    }
    return $o;
}
// bit string + trailing_bits (5.3.4) => bytes
function bytes($s) {
    $s = str_replace(' ', '', $s).'1';
    $s = str_pad($s, (int)ceil(strlen($s) / 8) * 8, '0');
    $o = '';
    foreach (str_split($s, 8) as $b) {
        $o .= chr(bindec($b));
    }
    return $o;
}
// OBU: header (5.3.2), optional extension header (5.3.3), optional obu_size (leb128)
function obu($type, $payload, $opts = []) {
    $opts += ['size' => true, 'ext' => null, 'lebBytes' => 1, 'obu_size' => null];
    $h = f(0, 1).f($type, 4).(is_null($opts['ext']) ? '0' : '1').($opts['size'] ? '1' : '0').'0';
    $data = chr(bindec($h));
    if (! is_null($opts['ext'])) {
        list($tid, $sid) = $opts['ext'];
        $data .= chr(bindec(f($tid, 3).f($sid, 2).'000'));
    }
    if ($opts['size']) {
        $size = is_null($opts['obu_size']) ? strlen($payload) : $opts['obu_size'];
        $data .= leb128($size, $opts['lebBytes']);
    }
    return $data.$payload;
}

// sequence_header_obu (5.5.1) as a bit string
function seqHeader($p) {
    $p += ['still' => 0, 'reduced' => 0, 'timing' => null, 'decoder_model' => null, 'idd' => 0,
           'frame_id' => null, 'sb128' => 0, 'filter_intra' => 1, 'intra_edge' => 1,
           'interintra' => 0, 'masked' => 0, 'warped' => 0, 'dual' => 0, 'order_hint' => null,
           'choose_sct' => 1, 'force_sct' => 0, 'choose_imv' => 1, 'force_imv' => 0,
           'superres' => 0, 'cdef' => 1, 'restoration' => 1, 'film_grain' => 0];
    $s = f($p['profile'], 3).f($p['still'], 1).f($p['reduced'], 1);
    if ($p['reduced']) {
        $s .= f($p['level'], 5);
    } else {
        $s .= is_null($p['timing']) ? '0' : '1';
        if (! is_null($p['timing'])) {
            $t = $p['timing'];
            $s .= f($t[0], 32).f($t[1], 32).(is_null($t[2]) ? '0' : '1'.uvlc($t[2]));
            $s .= is_null($p['decoder_model']) ? '0' : '1';
            if (! is_null($p['decoder_model'])) {
                $d = $p['decoder_model'];
                $s .= f($d[0], 5).f($d[1], 32).f($d[2], 5).f($d[3], 5);
            }
        }
        $s .= f($p['idd'], 1).f(count($p['ops']) - 1, 5);
        foreach ($p['ops'] as $op) {
            $op += ['tier' => 0, 'dm' => null, 'idd' => null];
            $s .= f($op['idc'], 12).f($op['level'], 5);
            if ($op['level'] > 7) {
                $s .= f($op['tier'], 1);
            }
            if (! is_null($p['decoder_model'])) {
                $s .= is_null($op['dm']) ? '0' : '1';
                if (! is_null($op['dm'])) {
                    $n = $p['decoder_model'][0] + 1;
                    $s .= f($op['dm'][0], $n).f($op['dm'][1], $n).f($op['dm'][2], 1);
                }
            }
            if ($p['idd']) {
                $s .= is_null($op['idd']) ? '0' : '1'.f($op['idd'], 4);
            }
        }
    }
    $s .= f($p['wbits'], 4).f($p['hbits'], 4).f($p['width'] - 1, $p['wbits'] + 1).f($p['height'] - 1, $p['hbits'] + 1);
    if (! $p['reduced']) {
        $s .= is_null($p['frame_id']) ? '0' : '1'.f($p['frame_id'][0], 4).f($p['frame_id'][1], 3);
    }
    $s .= f($p['sb128'], 1).f($p['filter_intra'], 1).f($p['intra_edge'], 1);
    if (! $p['reduced']) {
        $s .= f($p['interintra'], 1).f($p['masked'], 1).f($p['warped'], 1).f($p['dual'], 1);
        $s .= is_null($p['order_hint']) ? '0' : '1'.f($p['order_hint'][0], 1).f($p['order_hint'][1], 1);
        $s .= f($p['choose_sct'], 1);
        $force_sct = $p['choose_sct'] ? 2 : $p['force_sct'];
        if (! $p['choose_sct']) {
            $s .= f($p['force_sct'], 1);
        }
        if ($force_sct > 0) {
            $s .= f($p['choose_imv'], 1);
            if (! $p['choose_imv']) {
                $s .= f($p['force_imv'], 1);
            }
        }
        if (! is_null($p['order_hint'])) {
            $s .= f($p['order_hint'][2], 3);
        }
    }
    $s .= f($p['superres'], 1).f($p['cdef'], 1).f($p['restoration'], 1);
    // color_config (5.5.2)
    $c = $p['color'] + ['twelve' => 0, 'mono' => 0, 'desc' => null, 'range' => 0, 'ssx' => 1, 'ssy' => 1, 'csp' => 0, 'uvq' => 0];
    $s .= f($c['high'], 1);
    $bitDepth = $c['high'] ? 10 : 8;
    if (($p['profile'] == 2) && $c['high']) {
        $s .= f($c['twelve'], 1);
        $bitDepth = $c['twelve'] ? 12 : 10;
    }
    if ($p['profile'] != 1) {
        $s .= f($c['mono'], 1);
    }
    $s .= is_null($c['desc']) ? '0' : '1'.f($c['desc'][0], 8).f($c['desc'][1], 8).f($c['desc'][2], 8);
    if ($c['mono']) {
        $s .= f($c['range'], 1);
    } else {
        if ($c['desc'] !== [1, 13, 0]) {  // not sRGB
            $s .= f($c['range'], 1);
            if (($p['profile'] == 2) && ($bitDepth == 12)) {
                $s .= f($c['ssx'], 1);
                if ($c['ssx']) {
                    $s .= f($c['ssy'], 1);
                }
            }
            $ssx = [0 => 1, 1 => 0][$p['profile']] ?? (($bitDepth == 12) ? $c['ssx'] : 1);
            $ssy = [0 => 1, 1 => 0][$p['profile']] ?? (($bitDepth == 12) ? ($c['ssx'] ? $c['ssy'] : 0) : 0);
            if ($ssx && $ssy) {
                $s .= f($c['csp'], 2);
            }
        }
        $s .= f($c['uvq'], 1);
    }
    return $s.f($p['film_grain'], 1);
}

$files = [];

/*
 * basic.obu: temporal unit of profile 0 (8 bit 4:2:0, BT.709, order hint, frame id)
 *   TD, sequence header, frame with extension header, metadata with a 3 byte leb128 obu_size,
 *   padding with a 2 byte leb128 obu_size, a frame header OBU without obu_size (last)
 */
$sh = bytes(seqHeader([
    'profile' => 0, 'ops' => [['idc' => 0, 'level' => 8, 'tier' => 1]],
    'wbits' => 10, 'hbits' => 10, 'width' => 1920, 'height' => 1080, 'frame_id' => [5, 3], 'sb128' => 1,
    'interintra' => 1, 'masked' => 1, 'warped' => 1, 'dual' => 1, 'order_hint' => [1, 1, 6],
    'choose_sct' => 0, 'force_sct' => 1, 'choose_imv' => 0, 'force_imv' => 1,
    'superres' => 1, 'cdef' => 1, 'restoration' => 0,
    'color' => ['high' => 0, 'desc' => [1, 1, 1], 'range' => 1, 'csp' => 1, 'uvq' => 1],
]));
$files['basic.obu'] =
    obu(2, '').
    obu(1, $sh).
    obu(6, "\x10\x20\x30\x40", ['ext' => [2, 1]]).
    obu(5, "\x01\x02\x03", ['lebBytes' => 3]).
    obu(15, str_repeat("\xaa", 200)).
    obu(3, "\x55\x66", ['size' => false]);

/*
 * timing.obu: timing_info (equal_picture_interval), decoder_model_info, 3 operating points
 */
$files['timing.obu'] = obu(2, '').obu(1, bytes(seqHeader([
    'profile' => 0, 'timing' => [1001, 60000, 1], 'decoder_model' => [15, 60000, 23, 15], 'idd' => 1,
    'ops' => [
        ['idc' => 0x307, 'level' => 12, 'tier' => 0, 'dm' => [0x1234, 0x0567, 0], 'idd' => 3],
        ['idc' => 0x103, 'level' => 9, 'tier' => 1, 'dm' => null, 'idd' => null],
        ['idc' => 0x101, 'level' => 4, 'dm' => [1, 2, 1], 'idd' => 15],
    ],
    'wbits' => 7, 'hbits' => 6, 'width' => 160, 'height' => 90,
    'color' => ['high' => 1, 'range' => 0, 'csp' => 2],
])));

/*
 * still.obu: reduced_still_picture_header, profile 1 (4:4:4) with sRGB
 */
$files['still.obu'] = obu(1, bytes(seqHeader([
    'profile' => 1, 'still' => 1, 'reduced' => 1, 'level' => 31,
    'wbits' => 15, 'hbits' => 15, 'width' => 65536, 'height' => 1,
    'sb128' => 1, 'filter_intra' => 0, 'intra_edge' => 0, 'superres' => 0, 'cdef' => 0, 'restoration' => 0,
    'color' => ['high' => 1, 'desc' => [1, 13, 0], 'uvq' => 0], 'film_grain' => 1,
])));

/*
 * profile2.obu: profile 2, 12 bit 4:2:2 (subsampling_x 1, subsampling_y 0)
 * mono.obu: profile 0, 10 bit monochrome
 */
$files['profile2.obu'] = obu(1, bytes(seqHeader([
    'profile' => 2, 'ops' => [['idc' => 0, 'level' => 5]], 'wbits' => 3, 'hbits' => 3, 'width' => 16, 'height' => 16,
    'color' => ['high' => 1, 'twelve' => 1, 'desc' => [9, 16, 9], 'range' => 1, 'ssx' => 1, 'ssy' => 0, 'uvq' => 1],
])));
$files['mono.obu'] = obu(1, bytes(seqHeader([
    'profile' => 0, 'ops' => [['idc' => 0, 'level' => 0]], 'wbits' => 3, 'hbits' => 3, 'width' => 8, 'height' => 8,
    'color' => ['high' => 1, 'mono' => 1, 'range' => 1],
])));

/*
 * av1.ivf: 3 frames, 64 bit timestamp, header frame count 3
 */
function ivfHeader($width, $height, $rate, $scale, $count, $version = 0) {
    return "DKIF".pack('vv', $version, 32)."AV01".pack('vvVVVV', $width, $height, $rate, $scale, $count, 0);
}
function ivfFrame($timestamp, $payload) {
    return pack('V', strlen($payload)).pack('P', $timestamp).$payload;
}
$sh16 = obu(1, bytes(seqHeader([
    'profile' => 0, 'ops' => [['idc' => 0, 'level' => 0]], 'wbits' => 3, 'hbits' => 3, 'width' => 16, 'height' => 16,
    'color' => ['high' => 0],
])));
$frames = [
    ivfFrame(0, obu(2, '').$sh16.obu(6, "\x01\x02\x03\x04\x05\x06\x07\x08\x09")),
    ivfFrame(1, obu(2, '').obu(6, "\x11\x12")),
    ivfFrame(0x100000002, obu(2, '').obu(6, "\x21", ['size' => false])),
];
$files['av1.ivf'] = ivfHeader(16, 16, 30000, 1001, 3).implode('', $frames);
// the last frame is cut in its payload
$files['truncated.ivf'] = substr($files['av1.ivf'], 0, -1);

/*
 * broken OBU streams
 */
// non temporal delimiter OBU with obu_size 0
$files['size0.obu'] = obu(2, '').obu(6, '');
// sequence header cut in color_config (obu_size is that of the whole header)
$files['seq_truncated.obu'] = substr(obu(1, $sh), 0, 2 + 5);
// seq_profile 3 (reserved)
$s = seqHeader(['profile' => 2, 'ops' => [['idc' => 0, 'level' => 0]], 'wbits' => 3, 'hbits' => 3,
                'width' => 16, 'height' => 16, 'color' => ['high' => 0]]);
$files['profile3.obu'] = obu(1, bytes('011'.substr($s, 3)));

foreach ($files as $name => $data) {
    file_put_contents("$dir/$name", $data);
}
