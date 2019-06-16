<?php

/*
  IO_AV1 class
  (c) 2019/06/15 yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/AV1/IVF.php';
    require_once 'IO/AV1/OBU.php';
}

class IO_AV1 {
    var $IVF = null;
    var $OBU = null;
    function parse($av1Data, $opts = array()) {
        // IVF parse
        $ivf = new IO_AV1_IVF();
        if ($ivf->isIVF($av1Data)) {
            try {
                $ivf->parse($av1Data, $opts);
                $this->IVF = $ivf;
            } catch (Exception $e) {
                throw $e;
            }
        } else {
            try {
                $obu = new IO_AV1_OBU();
                $obu->parse($av1Data);
                $this->OBU = $obu;
            } catch (Exception $e) {
                throw $e;
            }
        }
    }
    function dump($opts = array()) {
        if (! is_null($this->IVF)) {
            $this->IVF->dump();
        }
        if (! is_null($this->OBU)) {
            $this->OBU->dump();
        }
    }
}
