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

class IO_AF1_Bit extends IO_Bit {
    function get_f($n) {
        return $this->getUIBits($n);
    }
    function get_uvlc($n) {
        $leadingZeros = 0;
        while (true) {
            $done = $this->getUIBit();
            if ($done)
                break;
            $leadingZeros++;
        }
        if ($leadingZeros >= 32) {
            return (1 << 32) - 1;
        }
        $value = $this->getUIBits($leadingZeros);
        return $value + (1 << $leadingZeros) - 1;
    }
}
