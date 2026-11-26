<?php

/*
  IO_IVF class
  (c) 2019/06/16 yoya@awm.jp
  https://wiki.multimedia.cx/index.php/IVF
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
    require_once 'IO/AV1/OBU.php';
}

class IO_AV1_IVF {
    static function isIVF($data) {
        if (strlen($data) < 32) {
            return false;
        }
        if (substr($data, 0, 4) !== "DKIF") {
            return false;
        }
        $version = ord($data[4]) + 0x100*ord($data[5]);
        if ($version !== 0) {
            fprintf(STDERR, "DKIF version:$version should be 0\n");
            return false;
        }
        return true;
    }
    function parse($data, $opts = array()) {
        $bit = new IO_Bit();
        $this->_ivfData = $data;
        $bit->input($data);
        $this->signature = $bit->getData(4);
        $this->version = $bit->getUI16LE();
        $this->headerLength = $bit->getUI16LE();
        $this->codec = $bit->getData(4);
        $this->width = $bit->getUI16LE();
        $this->height = $bit->getUI16LE();
        $this->fpsNum = $bit->getUI32LE();
        $this->fpsDenom = $bit->getUI32LE();
        $this->fps = $this->fpsNum / $this->fpsDenom;
        // frame count in the header (may be 0 or 0xFFFFFFFF when the writer could not seek back)
        $this->frameNum = $bit->getUI32LE();
        $bit->incrementOffset(4, 0);
        $this->frames = [];
        // read frames until the data ends, regardless of the header count
        while ($bit->hasNextData(1)) {
            $frame = [];
            $frame["frameSize"] = $bit->getUI32LE();
            $frame["timestamp"] = $bit->getUI64LE();
            $payload = $bit->getData($frame["frameSize"]);
            $frame["payload"] = $payload;
            try {
                $obu = new IO_AV1_OBU();
                $obu->parse($payload);
                $frame["obu"] = $obu;
            } catch (Exception $e) {
                throw $e;
            }
            $this->frames []= $frame;
        }
    }
    function getFrameNum() {
        return count($this->frames);
    }
    function getPayload($i) {
        return $this->frames[$i]["payload"];
    }
    function dump($opts = array()) {
        echo "signature:{$this->signature} version:{$this->version} headerLength:{$this->headerLength}\n";
        echo "codec:{$this->codec} width:{$this->width} height:{$this->height} ";
        echo "frameRate:{$this->fps}={$this->fpsNum}/{$this->fpsDenom} frameNum:{$this->frameNum}\n";
        $count_frames = count($this->frames);
        if ($this->frameNum != $count_frames) {
            fprintf(STDERR, "Warning: frameNum:{$this->frameNum} (header) != count(frames):$count_frames\n");
        }
        foreach ($this->frames as $i => $frame) {
            $frameSize = $frame["frameSize"];
            $timestamp = $frame["timestamp"];
            echo "[$i]frameSize:$frameSize time:$timestamp payload:";
            for ($j = 0 ; $j < min($frameSize, 8) ; $j++) {
                printf("%02x ", ord($frame["payload"][$j]));
            }
            echo "...\n";
            if (isset($frame["obu"])) {
                $frame["obu"]->dump($opts);
            }
        }
    }
}
