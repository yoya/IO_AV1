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
            fprint(STDERR, "DKIF version:$version shoud be 0\n");
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
        $this->length = $bit->getUI16LE();
        $this->codec = $bit->getData(4);
        $this->width = $bit->getUI16LE();
        $this->height = $bit->getUI16LE();
        $this->fpsNum = $bit->getUI32LE();
        $this->fpsDenom = $bit->getUI32LE();
        $this->fps = $this->fpsNum / $this->fpsDenom;
        $this->frameNum = $bit->getUI32LE();
        $bit->incrementOffset(4, 0);
        $this->frames = [];
        for ($i = 0 ; $i < $this->frameNum ; $i++) {
            $frame = [];
            $frame["size"] = $bit->getUI32LE();
            $frame["timestamp"] = $bit->getUI64LE();
            $frame["payload"] = $bit->getData($frame["size"]);
            $this->frames []= $frame;
        }
    }
    function getFrameNum($i) {
        return $this->frameNum;
    }
    function getPayload($i) {
        return $this->frames[$i]["payload"];
    }
    function dump($opts = array()) {
        echo "signature:{$this->signature} version:{$this->version} length:{$this->length}\n";
        echo "codec:{$this->codec} width:{$this->width} height:{$this->height}\n";
        echo "frameRate:{$this->fps}={$this->fpsNum}/{$this->fpsDenom} frameNum:{$this->frameNum}\n";
        if ($this->frameNum != count($this->frames)) {
            $count_frames = count($this->frames);
            fprintf(STDERR, "Error: frameNum:{$this->frameNum} != count(frames):$count_frames\n");
        }
        foreach ($this->frames as $i => $frame) {
            $size = $frame["size"];
            $timestamp = $frame["timestamp"];
            echo "[$i] size:$size time:$timestamp payload:";
            for ($j = 0 ; $j < min($size, 0x10) ; $j++) {
                printf("%02x ", ord($frame["payload"][$j]));
            }
            echo "\n";
        }
    }
}
