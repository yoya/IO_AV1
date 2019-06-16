<?php

/*
  IO_HEVC class
  (c) 2019/06/15 yoya@awm.jp
  ref) https://aomediacodec.github.io/av1-spec/av1-spec.pdf
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

class IO_AV1_Bit extends IO_Bit {
    function get_f($n) {
        return $this->getUIBits($n);
    }
    function get_uvlc($n) {
        $leadingZeros = 0;
        while (true) {
            $done = $this->get_f(1);
            if ($done)
                break;
            $leadingZeros++;
        }
        if ($leadingZeros >= 32) {
            return (1 << 32) - 1;
        }
        $value = $this->get_f($leadingZeros);
        return $value + (1 << $leadingZeros) - 1;
    }

    function get_le($n) {
        $t = 0;
        for ($i = 0 ; $i < $n ; $i++) {
            $byte = $this->get_f(8);
            $t += (byte << ($i * 8));
        }
        return $t;
    }
    function get_leb128() {
        $value = 0;
        // $Leb128Bytes = 0;
        for ($i = 0 ; $i < 8 ; $i++) {
            $leb128_byte = $this->get_f(8);
            $value |= (($leb128_byte & 0x7f) << ($i*7));
            // $Leb128Bytes += 1
            if (! ($leb128_byte & 0x80)) {
                break;
            }
        }
        return $value;
    }
}
