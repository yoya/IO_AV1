<?php

/*
  IO_OBU class
  (c) 2019/06/18 yoya@awm.jp
  https://aomediacodec.github.io/av1-spec/av1-spec.pdf
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/AV1/Bit.php';
}

class IO_AV1_OBU {
    const OBU_SEQUENCE_HEADER        = 1 ;
    const OBU_TEMPORAL_DELIMITER     = 2 ;
    const OBU_FRAME_HEADER           = 3 ;
    const OBU_TILE_GROUP             = 4 ;
    const OBU_METADATA               = 5 ;
    const OBU_FRAME                  = 6 ;
    const OBU_REDUNDANT_FRAME_HEADER = 7 ;
    const OBU_TILE_LIST              = 8 ;
    const OBU_PADDING               = 15 ;
    static $OBUTypeNameTable = [
        0  => "Reserved",
        self::OBU_SEQUENCE_HEADER        => "OBU_SEQUENCE_HEADER",
        self::OBU_TEMPORAL_DELIMITER     => "OBU_TEMPORAL_DELIMITER",
        self::OBU_FRAME_HEADER           => "OBU_FRAME_HEADER",
        self::OBU_TILE_GROUP             => "OBU_TILE_GROUP",
        self::OBU_METADATA               => "OBU_METADATA",
        self::OBU_FRAME                  => "OBU_FRAME",
        self::OBU_REDUNDANT_FRAME_HEADER => "OBU_REDUNDANT_FRAME_HEADER",
        self::OBU_TILE_LIST              => "OBU_TILE_LIST",
        9  => "Reserved",
        10 => "Reserved",
        11 => "Reserved",
        12 => "Reserved",
        13 => "Reserved",
        14 => "Reserved",
        self::OBU_PADDING                => "OBU_PADDING"
    ];
    var $OperatingPointIdc = null;
    static function getOBUTypeName($obu_type) {
        if (isset(self::$OBUTypeNameTable[$obu_type])) {
            $name = self::$OBUTypeNameTable[$obu_type];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    /*
     * parser functions
     */
    function parse($data, $opts = array()) {
        $this->_data = $data;
        $this->OBUs = [];
        $temporal_id = 0; // XXX
        $spatial_id = 0; // XXX
        $bit = new IO_AV1_Bit();
        $bit->input($data);
        while ($bit->hasNextData(1)) {
                $obu_header = $this->parse_obu_header($bit, $opts);
                $obu_type           = $obu_header["obu_type"];
                $obu_extension_flag = $obu_header["obu_extension_flag"];
                if ($obu_header["obu_has_size_field"]) {
                    $obu_size = $bit->get_leb128();
                } else {
                    $obu_size = strlen($data) - 1 - $obu_extension_flag;
                }
                if (($obu_type != self::OBU_TEMPORAL_DELIMITER) &&
                    ($obu_size == 0)) {
                    throw new Exception("obusize == 0");
                }
                $startPosition = $bit->get_position();
                if ( $obu_type != self::OBU_SEQUENCE_HEADER &&
                     $obu_type != self::OBU_TEMPORAL_DELIMITER &&
                     $this->OperatingPointIdc !== 0 &&
                     $obu_extension_flag == 1 ) {
                    {
                        $inTemporalLayer = ($this->OperatingPointIdc >> $temporal_id ) & 1;
                        $inSpatialLayer = ($this->OperatingPointIdc >> ( $spatial_id + 8 ) ) & 1;
                    }
                }
                $obu_payload = [];
                $obu = ["obu_header" => $obu_header, "obu_size" => $obu_size];
                if ( $obu_type == self::OBU_SEQUENCE_HEADER ) {
                    ;
                } else if ( $obu_type == self::OBU_TEMPORAL_DELIMITER ) {
                    ;
                } else if ( $obu_type == self::OBU_FRAME_HEADER ) {
                    ;
                } else if ( $obu_type == self::OBU_REDUNDANT_FRAME_HEADER ) {
                    ;
                }
                $bit->set_position($startPosition + $obu_size);;
                $this->OBUs []= $obu;
        }
    }
    function parse_obu_header($bit, $opts = array()) {
        $obu_header = [];
        $obu_header["obu_forbidden_bit"]  = $bit->get_f(1);
        $obu_header["obu_type"]           = $bit->get_f(4);
        $obu_header["obu_extension_flag"] = $bit->get_f(1);
        $obu_header["obu_has_size_field"] = $bit->get_f(1);
        $obu_header["obu_reserved_1bit"]  = $bit->get_f(1);
        if ($obu_header["obu_extension_flag"] == 1) {
            $obu_header["obu_extention_header"] = $this->parse_obu_extention_header($bit, $opts);
        }
        return $obu_header;
    }
    function parse_obu_extention_header($bit, $opts = array()) {
        $obu_extention_header = [];
        $obu_extention_header["temporal_id"]    = $bit->get_f(3);
        $obu_extention_header["spatial_id"]     = $bit->get_f(2);
        $obu_extention_header["reserved_3bits"] = $bit->get_f(3);
        return $obu_extention_header;
    }
    /*
     * dumper functions
     */
    function dump($opts = array()) {
        foreach ($this->OBUs as $i => $obu) {
            $obu_header = $obu["obu_header"];
            $obu_size   = $obu["obu_size"];
            $obu_type = $obu_header["obu_type"];
            $obu_type_name = $this->getOBUTypeName($obu_type);
            echo "  [$i]obu_type:$obu_type ($obu_type_name)";
            if ($obu_header["obu_has_size_field"]) {
                echo "  obu_size:$obu_size\n";
            } else {
                echo "  (obu_size:$obu_size)\n";
            }
            $this->dump_obu_header($obu_header, $opts);
        }
    }
    function dump_obu_header($obu, $opts = array()) {
        echo "  obu_header:\n";
        echo "    obu_forbidden_bit:{$obu['obu_forbidden_bit']} ";
        echo "obu_type:{$obu['obu_type']} ";
        echo "obu_extension_flag:{$obu['obu_extension_flag']}\n";
        echo "    obu_has_size_field:{$obu['obu_has_size_field']} ";
        echo "obu_reserved_1bit:{$obu['obu_reserved_1bit']}\n";
        if ($obu["obu_extension_flag"] == 1) {
            $this->parse_obu_extention_header($obu["obu_extention_header"], $opts);
        }
    }
    function dump_obu_extention_header($obu, $opts = array()) {
        echo "obu_extention_header:\n";
        echo "    temporal_id:{$obu['temporal_id']} ";
        echo "spatial_id:{$obu['spatial_id']} ";
        echo "reserved_3bits:{$obu['reserved_3bits']}\n";
    }
}
