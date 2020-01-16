<?php

/*
  IO_AV1_OBU class
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
    // 3. Symbols
    const SELECT_SCREEN_CONTENT_TOOLS = 2;
    const SELECT_INTEGER_MV           = 2;
    // 6.4.2 Color config semantic
    const CP_BT_709       = 1;
    const CP_UNSPECIFIED  = 2;
    const CP_BT_470_M     = 4;
    const CP_BT_470_B_G   = 5;
    const CP_BT_601       = 6;
    const CP_SMPTE_240    = 7;
    const CP_GENERIC_FILM = 8;
    const CP_BT_2020      = 9;
    const CP_XYZ          = 10;
    const CP_SMPTE_431    = 11;
    const CP_SMPTE_432    = 12;
    const CP_EBU_3213     = 22;
    static $CPNameTable = [
        self::CP_BT_709       => "BT_709",
        self::CP_UNSPECIFIED  => "CP_UNSPECIFIED",
        self::CP_BT_470_M     => "CP_BT_470_M",
        self::CP_BT_470_B_G   => "CP_BT_470_B_G",
        self::CP_BT_601       => "CP_BT_601",
        self::CP_SMPTE_240    => "CP_SMPTE_240",
        self::CP_GENERIC_FILM => "CP_GENERIC_FILM",
        self::CP_BT_2020      => "CP_BT_2020",
        self::CP_XYZ          => "CP_XYZ",
        self::CP_SMPTE_431    => "CP_SMPTE_431",
        self::CP_SMPTE_432    => "CP_SMPTE_432",
        self::CP_EBU_3213     => "CP_EBU_3213 ",
    ];
    //
    const TC_RESERVED_0     = 0;
    const TC_BT_709         = 1;
    const TC_UNSPECIFIED    = 2;
    const TC_RESERVED_3     = 3;
    const TC_BT_470_M       = 4;
    const TC_BT_470_B_G     = 5;
    const TC_BT_601         = 6;
    const TC_SMPTE_240      = 7;
    const TC_LINEAR         = 8;
    const TC_LOG_100        = 9;
    const TC_LOG_100_SQRT10 = 10;
    const TC_IEC_61966      = 11;
    const TC_BT_1361        = 12;
    const TC_SRGB           = 13;
    const TC_BT_2020_10BIT  = 14;
    const TC_BT_2020_12BIT  = 15;
    const TC_SMPTE_2084     = 16;
    const TC_SMPTE_428      = 17;
    const TC_HLG            = 18;
    static $TCNameTable = [
        self::TC_RESERVED_0     => "TC_RESERVED_0",
        self::TC_BT_709         => "TC_BT_709",
        self::TC_UNSPECIFIED    => "TC_UNSPECIFIED",
        self::TC_RESERVED_3     => "TC_RESERVED_3",
        self::TC_BT_470_M       => "TC_BT_470_M",
        self::TC_BT_470_B_G     => "TC_BT_470_B_G",
        self::TC_BT_601         => "TC_BT_601",
        self::TC_SMPTE_240      => "TC_SMPTE_240",
        self::TC_LINEAR         => "TC_LINEAR",
        self::TC_LOG_100        => "TC_LOG_100",
        self::TC_LOG_100_SQRT10 => "TC_LOG_100_SQRT10",
        self::TC_IEC_61966      => "TC_IEC_61966",
        self::TC_BT_1361        => "TC_BT_1361",
        self::TC_SRGB           => "TC_SRGB",
        self::TC_BT_2020_10BIT  => "TC_BT_2020_10BIT",
        self::TC_BT_2020_12BIT  => "TC_BT_2020_12BIT",
        self::TC_SMPTE_2084     => "TC_SMPTE_2084",
        self::TC_SMPTE_428      => "TC_SMPTE_428",
        self::TC_HLG            => "TC_HLG",
    ];
    //
    const MC_IDENTITY    = 0;
    const MC_BT_709      = 1;
    const MC_UNSPECIFIED = 2;
    const MC_RESERVED_3  = 3;
    const MC_FCC         = 4;
    const MC_BT_470_B_G  = 5;
    const MC_BT_601      = 6;
    const MC_SMPTE_240   = 7;
    const MC_SMPTE_YCGCO = 8;
    const MC_BT_2020_NCL = 9;
    const MC_BT_2020_CL  = 10;
    const MC_SMPTE_2085  = 11;
    const MC_CHROMAT_NCL = 12;
    const MC_CHROMAT_CL  = 13;
    const MC_ICTCP       = 14;
    static $MCNameTable = [
        self::MC_IDENTITY    => "MC_IDENTITY",
        self::MC_BT_709      => "MC_BT_709",
        self::MC_UNSPECIFIED => "MC_UNSPECIFIED",
        self::MC_RESERVED_3  => "MC_RESERVED_3",
        self::MC_FCC         => "MC_FCC",
        self::MC_BT_470_B_G  => "MC_BT_470_B_G",
        self::MC_BT_601      => "MC_BT_601",
        self::MC_SMPTE_240   => "MC_SMPTE_240",
        self::MC_SMPTE_YCGCO => "MC_SMPTE_YCGCO",
        self::MC_BT_2020_NCL => "MC_BT_2020_NCL",
        self::MC_BT_2020_CL  => "MC_BT_2020_CL",
        self::MC_SMPTE_2085  => "MC_SMPTE_2085",
        self::MC_CHROMAT_NCL => "MC_CHROMAT_NCL",
        self::MC_CHROMAT_CL  => "MC_CHROMAT_CL",
        self::MC_ICTCP       => "MC_ICTCP",
    ];
    //
    const CSP_UNKNOWN  = 0;
    const CSP_VERTICAL = 1;
    const CSP_COLOCAED = 2;
    const CSP_RESERVED = 3;
    static $CSPNameTable = [
        self::CSP_UNKNOWN  => "CSP_UNKNOWN",
        self::CSP_VERTICAL => "CSP_VERTICAL",
        self::CSP_COLOCAED => "CSP_COLOCAED",
        self::CSP_RESERVED => "CSP_RESERVED",
    ];
    //
    var $OperatingPointIdc = null;
    var $OrderHintBits = null;
    var $BitDepth = null;
    var $NumPlanes = null;
    var $SeenFrameHeader = null;
    function choose_operating_point() {
        return 0; // XXX
    }
    static function getOBUTypeName($obu_type) {
        if (isset(self::$OBUTypeNameTable[$obu_type])) {
            $name = self::$OBUTypeNameTable[$obu_type];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    static function getCPName($cp) {
        if (isset(self::$CPNameTable[$cp])) {
            $name = self::$CPNameTable[$cp];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    static function getTCName($cp) {
        if (isset(self::$TCNameTable[$cp])) {
            $name = self::$TCNameTable[$cp];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    static function getMCName($cp) {
        if (isset(self::$MCNameTable[$cp])) {
            $name = self::$MCNameTable[$cp];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    static function getCSPName($cp) {
        if (isset(self::$CSPNameTable[$cp])) {
            $name = self::$CSPNameTable[$cp];
        } else {
            $name = "UNKNOWN";
        }
        return $name;
    }
    /*
     * parser functions
     */
    // 5.3.1. Geenral OBU syntax
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
                switch ($obu_type) {
                case self::OBU_SEQUENCE_HEADER:
                    $obu["sequence_header_obu"] = $this->parse_sequence_header_obu($bit);
                    break;
                case self::OBU_TEMPORAL_DELIMITER:
                    $this->parse_temporal_delimiter_obu($bit);
                    break;
                case self::OBU_FRAME_HEADER:
                    break;
                case self::OBU_REDUNDANT_FRAME_HEADER:
                    break;
                default:
                    break;
                }
                $obu["_offset"] = $startPosition;
                $bit->set_position($startPosition + $obu_size);
                $this->OBUs []= $obu;
        }
    }
    // 5.3.2. OBU header syntax
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
    // 5.5.3. OBU extension header syntax
    function parse_obu_extention_header($bit, $opts = array()) {
        $obu_extention_header = [];
        $obu_extention_header["temporal_id"]    = $bit->get_f(3);
        $obu_extention_header["spatial_id"]     = $bit->get_f(2);
        $obu_extention_header["reserved_3bits"] = $bit->get_f(3);
        return $obu_extention_header;
    }
    // 5.5.1. General sequence header OBU syntax
    function parse_sequence_header_obu($bit) {
        $obu = [];
        $obu["seq_profile"]                  = $bit->get_f(3);
        $obu["still_picture"]                = $bit->get_f(1);
        $obu["reduced_still_picture_header"] = $bit->get_f(1);
        if ($obu["reduced_still_picture_header"]) {
            $obu["timing_info_present_flag"]            = 0;
            $obu["decoder_model_info_present_flag"]     = 0;
            $obu["initial_display_delay_present_flag"]  = 0;
            $obu["operating_points_cnt_minus_1"]        = 0;
            $obu["operating_point_idc"] = [0 => 0];
            $obu["seq_level_idx"] = [0 => $bit->get_f(5)];
            $obu["seq_tier"] = [0 => 0];
            $obu["decoder_model_present_for_this_op"] = [0 => 0];
            $obu["initial_display_delay_present_for_this_op"] = [0 => 0];
        } else {
            $obu["timing_info_present_flag"] = $bit->get_f(1);
            if ($obu["timing_info_present_flag"]) {
                $obu["timing_info"] = $this->parse_timing_info();
                $obu["decoder_model_info_present_flag"] = $bit->get_f(1);
                if ($obu["decoder_model_info_present_flag"]) {
                    $obu["decoder_model_info"] = $this->parse_decoder_model_info();
                }
            } else {
                $obu["decoder_model_info_present_flag"] = 0;
            }
            $obu["initial_display_delay_present_flag"] = $bit->get_f(1);
            $obu["operating_points_cnt_minus_1"]       = $bit->get_f(5);
            $obu["operating_point_idc"]                       = [];
            $obu["seq_level_idx"]                             = [];
            $obu["seq_tier"]                                  = [];
            $obu["decoder_model_present_for_this_op"]         = [];
            $obu["initial_display_delay_present_for_this_op"] = [];
            $obu["initial_display_delay_minus_1"]             = [];
            for ($i = 0; $i <= $obu["operating_points_cnt_minus_1"]; $i++) {
                $obu["operating_point_idc"][$i] = $bit->get_f(12);
                $obu["seq_level_idx"][$i] = $bit->get_f(5);
                if ($obu["seq_level_idx"][$i] > 7) {
                    $obu["seq_tier"][$i] = $bit->get_f(1);
                } else {
                    $obu["seq_tier"][$i] = 0;
                }
                if ($obu["decoder_model_info_present_flag"]) {
                    $obu["decoder_model_present_for_this_op"][$i] = $bit->get_f(1);
                    if ($obu["decoder_model_present_for_this_op"][$i]) {
                        $obu["operating_paramters_info"] = $this->parse_operating_paramters_info($bit);
                    }
                } else {
                    $obu["decoder_model_present_for_this_op"][$i] = 0;
                }
                if ($obu["initial_display_delay_present_flag"]) {
                    $obu["initial_display_delay_present_for_this_op"][$i] = $bit->get_f(1);
                    if ($obu["initial_display_delay_present_for_this_op"][$i]) {
                        $obu["initial_display_delay_minus_1"][$i] = $bit->get_f(4);
                    }
                }
            }
        }
        $operatingPoint = $this->choose_operating_point();
        $this->OperatingPointIdc = $obu["operating_point_idc"][$operatingPoint];
        $obu["frame_width_bits_minus_1"]  = $bit->get_f(4);
        $obu["frame_height_bits_minus_1"] = $bit->get_f(4);
        $obu["frame_width_minus_1"]  = $bit->get_f($obu["frame_width_bits_minus_1"] + 1);
        $obu["frame_height_minus_1"] = $bit->get_f($obu["frame_height_bits_minus_1"] + 1);
        if ($obu["reduced_still_picture_header"]) {
            $obu["frame_id_numbers_present_flag"] = 0;
        } else {
            $obu["frame_id_numbers_present_flag"] = $bit->get_f(1);
        }
        if ($obu["frame_id_numbers_present_flag"]) {
            $obu["delta_frame_id_length_minus_2"]      = $bit->get_f(4);
            $obu["additional_frame_id_length_minus_1"] = $bit->get_f(4);
        }
        $obu["use_128x128_superblock"] = $bit->get_f(1);
        $obu["enable_filter_intra"] = $bit->get_f(1);
        $obu["enable_intra_edge_filter"] = $bit->get_f(1);
        if ($obu["reduced_still_picture_header"]) {
            $obu["enable_interintra_compound"] = 0;
            $obu["enable_masked_compound"]     = 0;
            $obu["enable_warped_motion"]       = 0;
            $obu["enable_dual_filter"]         = 0;
            $obu["enable_order_hint"]          = 0;
            $obu["enable_jnt_comp"]            = 0;
            $obu["enable_ref_frame_mvs"]       = 0;
            $obu["seq_force_screen_content_tools"] = self::SELECT_SCREEN_CONTENT_TOOLS;
            $obu["seq_force_integer_mv"] = self::SELECT_INTEGER_MV;
            $this->OrderHintBits = 0;
        } else {
            $obu["enable_interintra_compound"] = $bit->get_f(1);
            $obu["enable_masked_compound"]     = $bit->get_f(1);
            $obu["enable_warped_motion"]       = $bit->get_f(1);
            $obu["enable_dual_filter"]         = $bit->get_f(1);
            $obu["enable_order_hint"]          = $bit->get_f(1);
            if ($obu["enable_order_hint"]) {
                $obu["enable_jnt_comp"]      = $bit->get_f(1);
                $obu["enable_ref_frame_mvs"] = $bit->get_f(1);
            } else {
                $obu["enable_jnt_comp"]      = 0;
                $obu["enable_ref_frame_mvs"] = 0;
            }
            $obu["seq_choose_screen_content_tools"] = $bit->get_f(1);
            if ($obu["seq_choose_screen_content_tools"]) {
                $obu["seq_force_screen_content_tools"] = self::SELECT_SCREEN_CONTENT_TOOLS;
            } else {
                $obu["seq_force_screen_content_tools"] = $bit->get_f(1);
            }
            if ($obu["seq_force_screen_content_tools"] > 0) {
                $obu["seq_choose_integer_mv"] = $bit->get_f(1);
                if ($obu["seq_choose_integer_mv"]) {
                    $obu["seq_force_integer_mv"] = self::SELECT_INTEGER_MV;
                } else {
                    $obu["seq_force_integer_mv"] = $bit->get_f(1);
                }
            } else {
                $obu["seq_force_integer_mv"] = self::SELECT_INTEGER_MV;
            }
            if ($obu["enable_order_hint"]) {
                $obu["order_hint_bits_minus_1"] = $bit->get_f(3);
                $this->OrderHintBits = $obu["order_hint_bits_minus_1"] + 1;
            } else {
                $this->OrderHintBits = 0;
            }
        }
        $obu["enable_superres"] = $bit->get_f(1);
        $obu["enable_cdef"] = $bit->get_f(1);
        $obu["enable_restoration"] = $bit->get_f(1);
        $obu["color_config"] = $this->parse_color_config($bit, $obu);
        $obu["film_grain_params_present"] = $bit->get_f(1);
        return $obu;
    }
    // 5.5.2. Color config syntax
    function parse_color_config($bit, $obu) {
        $config = [];
        $config["high_bitdepth"] = $bit->get_f(1);
        if (($obu["seq_profile"] == 2) && $config["high_bitdepth"]) {
            $config["twelve_bit"] = $bit->get_f(1);
            $this->BitDepth = ($config["twelve_bit"]) ? 12: 10;
        } else if ($obu["seq_profile"] <= 2) {
            $this->BitDepth = ($config["high_bitdepth"]) ? 10: 8;
        } else {
            throw new Exception("must be seq_profile:{$obu['seq_profile']} <= 2)");
        }
        if ($obu["seq_profile"] == 1) {
            $config["mono_chrome"] = 0;
        } else {
            $config["mono_chrome"] = $bit->get_f(1);
        }
        $this->NumPlanes = $config["mono_chrome"]? 1: 3;
        $config["color_description_present_flag"] = $bit->get_f(1);
        if ($config["color_description_present_flag"]) {
            $config["color_primaries"]           = $bit->get_f(8);
            $config["transfer_characteristics"] = $bit->get_f(8);
            $config["matrix_coefficients"]      = $bit->get_f(8);
        } else {
            $config["color_primaries"]          = self::CP_UNSPECIFIED;
            $config["transfer_characteristics"] = self::TC_UNSPECIFIED;
            $config["matrix_coefficients"]      = self::MC_UNSPECIFIED;
        }
        if ($config["mono_chrome"]) {
            $config["color_range"] = $bit->get_f(1);
            $config["subsampling_x"] = 1;
            $config["subsampling_y"] = 1;
            $config["chroma_sampling_position"] = self::CSP_UNKNOWN;
            $config["separate_uv_delta_q"] = 0;
            return $config;
        } else if (($config["color_primaries"] == self::CP_BT_709) &&
                   ($config["transfer_characteristics"] == self::TC_SRGB) &&
                   ($config["matrix_coefficients"] == self::MC_IDENTITY)) {
            $config["color_range"] = 1;
            $config["subsampling_x"] = 0;
            $config["subsampling_y"] = 0;
        } else {
            $config["color_range"] = $bit->get_f(1);
            if ($obu["seq_profile"] == 0) {
                $config["subsampling_x"] = 1;
                $config["subsampling_y"] = 1;
            } else if ($obu["seq_profile"] == 1) {
                $config["subsampling_x"] = 0;
                $config["subsampling_y"] = 0;
            } else {
                if ($this->BitDepth == 12) {
                    $config["subsampling_x"] = $bit->get_f(1);
                    if ($config["subsampling_x"]) {
                        $config["subsampling_y"] = $bit->get_f(1);
                    } else {
                        $config["subsampling_y"] = 0;
                    }
                }  else {
                    $config["subsampling_x"] = 1;
                    $config["subsampling_y"] = 0;
                }
            }
            if ($config["subsampling_x"] && $config["subsampling_y"]) {
                $config["chroma_sample_position"] = $bit->get_f(2);
            }
        }
        $config["separate_uv_delta_q"] = $bit->get_f(1);
        return $config;
    }
    // 5.5.3. Timing info syntax
    function parse_timing_info($bit) {
        $info = [];
        $info["num_units_in_display_tick"] = $bit->get_f(32);
        $info["time_scale"] = $bit->get_f(32);
        $info["equal_picture_interval"] = $bit->get_f(1);
        if ($info["equal_picture_interval"] ) {
            $info["num_ticks_per_picture_minus_1"] = $bit->get_uvlc();
        }
        return $info;
    }
    // 5.5.4. Decoder model info syntax
    function parse_decoder_model_info($bit) {
        $info = [];
        throw "ERROR: parse_decoder_model_info: not implemented yet\n";
    }
    // 5.5.5. Operating parameters info syntax
    function parse_operating_paramters_info($bit) {
        $info = [];
        throw "ERROR: parse_operating_parameters_info: not implemented yet\n";
    }
    // 5.6. Temporal delimiter obu syntax
    function parse_temporal_delimiter_obu($bit) {
        $this->SeenFrameHeader = 0;
    }
    /*
     * dumper functions
     */
    // 5.3.1. Geenral OBU syntax
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
            switch ($obu_type) {
            case self::OBU_SEQUENCE_HEADER:
                $this->dump_sequence_header_obu($obu["sequence_header_obu"], $opts);
                break;
            case self::OBU_TEMPORAL_DELIMITER:
                $this->dump_temporal_delimiter_obu($opts);
                break;
            case self::OBU_FRAME_HEADER:
                break;
            case self::OBU_REDUNDANT_FRAME_HEADER:
                break;
            default:
                break;
            }
            if ($opts["hexdump"]) {
                $bit = new IO_Bit();
                $bit->input($this->_data);
                $bit->hexdump($obu["_offset"], $obu["obu_size"]);
            }
        }
    }
    // 5.3.2. OBU header syntax
    function dump_obu_header($obu, $opts = array()) {
        echo "  obu_header:\n";
        echo "    obu_forbidden_bit:{$obu['obu_forbidden_bit']} ";
        echo "obu_type:{$obu['obu_type']} ";
        echo "obu_extension_flag:{$obu['obu_extension_flag']}\n";
        echo "    obu_has_size_field:{$obu['obu_has_size_field']} ";
        echo "obu_reserved_1bit:{$obu['obu_reserved_1bit']}\n";
        if ($obu["obu_extension_flag"] == 1) {
            $this->dump_obu_extention_header($obu["obu_extention_header"], $opts);
        }
    }
    // 5.5.3. OBU extension header syntax
    function dump_obu_extention_header($obu, $opts = array()) {
        echo "    obu_extention_header:\n";
        echo "      temporal_id:{$obu['temporal_id']} ";
        echo "spatial_id:{$obu['spatial_id']} ";
        echo "reserved_3bits:{$obu['reserved_3bits']}\n";
    }
    // 5.5.1. General sequence header OBU syntax
    function dump_sequence_header_obu($obu, $opts = array()) {
        echo "  sequence_header_obu:\n";
        echo "    seq_profile:{$obu['seq_profile']} ";
        echo "still_picture:{$obu['still_picture']} ";
        echo "reduced_still_picture_header:{$obu['reduced_still_picture_header']}\n";
        echo "    reduced_still_picture_header:{$obu['reduced_still_picture_header']}\n";
        if ($obu["reduced_still_picture_header"]) {
            echo "      (timing_info_present_flag:{$obu['timing_info_present_flag']})\n";
            echo " (decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']})";
            echo " (initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']})";
            echo " (operating_points_cnt_minus_1:{$obu['operating_points_cnt_minus_1']})";
            echo " (operating_point_idc[0]:{$obu['operating_point_idc'][0]})";
            echo " (seq_level_idx[0]:{$obu['seq_level_idx'][0]})";
            echo " (seq_tier[0]:{$obu['seq_tier'][0]})";
            echo " (decoder_model_present_for_this_op[0]:{$obu['decoder_model_present_for_this_op'][0]})";
            echo " (initial_display_delay_present_for_this_op[0]:{$obu['initial_display_delay_present_for_this_op'][0]})";
        } else {
            echo "      timing_info_present_flag:{$obu['timing_info_present_flag']}\n";
            if ($obu["timing_info_present_flag"]) {
                $this->dump_timing_info($obu["timing_info"], $opts);
                echo "        decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}\n";
                if ($obu["decoder_model_info_present_flag"]) {
                    $this->dump_decoder_model_info($obu["decoder_model_info"], $opts);
                }
            } else {
                echo "        (decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']})\n";
            }
            echo "      initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']}\n";
            echo "      operating_points_cnt_minus_1:{$obu['operating_points_cnt_minus_1']}\n";
            for ($i = 0; $i <= $obu["operating_points_cnt_minus_1"]; $i++) {
                echo "      operating_point_idc_$i:{$obu['operating_point_idc'][$i]}\n";
                echo "      seq_level_idx_$i:{$obu['seq_level_idx'][$i]}\n";
                if ($obu["seq_level_idx"][$i] > 7) {
                    echo "      seq_tier_$i:{$obu['seq_tier'][$i]}\n";
                } else {
                    echo "      (seq_tier_$i:{$obu['seq_tier'][$i]})\n";
                }
                echo "      decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}\n";
                if ($obu["decoder_model_info_present_flag"]) {
                    echo "        decoder_model_present_for_this_op_$i:{$obu["decoder_model_present_for_this_op"][$i]}\n";
                    if ($obu["decoder_model_present_for_this_op"][$i]) {
                        $this->dump_operating_paramters_info($obu["operating_paramters_info"], $opts);
                    }
                } else {
                    echo "      (decoder_model_present_for_this_op_$i:{$obu['decoder_model_present_for_this_op'][$i]})\n";
                }
                echo "      initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']}\n";
                if ($obu["initial_display_delay_present_flag"]) {
                    echo "        initial_display_delay_minus_1_$i:{$obu['initial_display_delay_minus_1'][$i]}\n";
                }
            }
        }
        echo "    OperatingPointIdc:{$this->OperatingPointIdc}\n";
        echo "    frame_width_bits_minus_1:{$obu['frame_width_bits_minus_1']}";
        echo " frame_height_bits_minus_1:{$obu['frame_height_bits_minus_1']}\n";
        echo "    frame_width_minus_1:{$obu['frame_width_minus_1']}";
        echo " frame_height_minus_1:{$obu['frame_height_minus_1']}\n";
        if ($obu["reduced_still_picture_header"]) {
            echo "        (frame_id_numbers_present_flag:{$obu['frame_id_numbers_present_flag']})\n";
        } else {
            echo "        frame_id_numbers_present_flag:{$obu['frame_id_numbers_present_flag']}\n";
        }
        if ($obu["frame_id_numbers_present_flag"]) {
            echo "      delta_frame_id_length_minus_2:{$obu['delta_frame_id_length_minus_2']}";
            echo " additional_frame_id_length_minus_1:{$obu['additional_frame_id_length_minus_1']}\n";
        }
        echo "    use_128x128_superblock:{$obu['use_128x128_superblock']}";
        echo " enable_filter_intra:{$obu['enable_filter_intra']}";
        echo " enable_intra_edge_filter:{$obu['enable_intra_edge_filter']}\n";
        if ($obu["reduced_still_picture_header"]) {
            echo "      (enable_interintra_compound:{$obu['enable_interintra_compound']})";
            echo " (enable_masked_compound:{$obu['enable_masked_compound']})\n";
            echo "      (enable_warped_motion:{$obu['enable_warped_motion']})";
            echo " (enable_dual_filter:{$obu['enable_dual_filter']})\n";
            echo "      (enable_order_hint:{$obu['enable_order_hint']})\n";
            echo "      (enable_jnt_comp:{$obu['enable_jnt_comp']})";
            echo " (enable_ref_frame_mvs:{$obu['enable_ref_frame_mvs']})\n";
            echo "      (seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}(SELECT_SCREEN_CONTENT_TOOLS))\n";
            echo "      (seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV))\n";
            echo "      (OrderHintBits:{$this->OrderHintBits})\n";
        } else {
            echo "      enable_interintra_compound:{$obu['enable_interintra_compound']}";
            echo " enable_masked_compound:{$obu['enable_masked_compound']}\n";
            echo "      enable_warped_motion:{$obu['enable_warped_motion']}";
            echo " enable_dual_filter:{$obu['enable_dual_filter']}\n";
            echo "      enable_order_hint:{$obu['enable_order_hint']}\n";
            if ($obu["enable_order_hint"]) {
                echo "        enable_jnt_comp:{$obu['enable_jnt_comp']}";
                echo " enable_ref_frame_mvs:{$obu['enable_ref_frame_mvs']}\n";
            } else {
                echo "        enable_jnt_comp:{$obu['enable_jnt_comp']}";
                echo " enable_ref_frame_mvs:{$obu['enable_ref_frame_mvs']}\n";
            }
            echo "      seq_choose_screen_content_tools:{$obu['seq_choose_screen_content_tools']}\n";
            if ($obu["seq_choose_screen_content_tools"]) {
                echo "        (seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}(SELECT_SCREEN_CONTENT_TOOLS))\n";
            } else {
                echo "        seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}\n";
            }
            if ($obu["seq_force_screen_content_tools"] > 0) {
                echo "        seq_choose_integer_mv:{$obu['seq_choose_integer_mv']}\n";
                if ($obu["seq_choose_integer_mv"]) {
                    echo "          (seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV))\n";
                } else {
                    echo "          seq_force_integer_mv:{$obu['seq_force_integer_mv']}\n";
                }
            } else {
                echo "          (seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV))\n";
            }
            if ($obu["enable_order_hint"]) {
                echo "          order_hint_bits_minus_1:{$obu['order_hint_bits_minus_1']}";
                echo " OrderHintBits:{$this->OrderHintBits}\n";
            } else {
                echo "          (OrderHintBits:{$this->OrderHintBits})\n";
            }
        }
        echo "    enable_superres:{$obu['enable_superres']}";
        echo " enable_cdef:{$obu['enable_cdef']}";
        echo " enable_restoration:{$obu['enable_restoration']}\n";
        $this->dump_color_config($obu["color_config"], $obu, $opts);
        echo "    film_grain_params_present:{$obu['film_grain_params_present']}\n";
    }
    // 5.5.2. Color config syntax
    function dump_color_config($config, $obu, $opts) {
        echo "    color_config:\n";
        echo "      high_bitdepth:{$config['high_bitdepth']}\n";
        if (($obu["seq_profile"] == 2) && $config["high_bitdepth"]) {
            echo "        twelve_bit:{$config['twelve_bit']}\n";
            echo "        BitDepth:{$this->BitDepth}\n";
        } else if ($obu["seq_profile"] <= 2) {
            echo "        BitDepth:{$this->BitDepth}\n";
        } else {
            throw new Exception("must be seq_profile:{$obu['seq_profile']}) <= 2) {");            
        }
        if ($obu["seq_profile"] == 1) {
            echo "        (mono_chrome:{$config['mono_chrome']}\n";
        } else {
            echo "        mono_chrome:{$config['mono_chrome']}\n";
        }
        echo "      NumPlanes:{$this->NumPlanes}\n";
        echo "      color_description_present_flag:{$config['color_description_present_flag']}\n";
        if ($config["color_description_present_flag"]) {
            $cp = $config["color_primaries"];
            $cp_name = $this->getCPName($cp);
            $tc = $config["transfer_characteristics"];
            $tc_name = $this->getTCName($tc);
            $mc = $config["matrix_coefficients"];
            $mc_name = $this->getMCName($mc);
            echo "        color_primaries:$cp($cp_name)\n";
            echo "        transfer_characteristics:$tc($tc_name)\n";
            echo "        matrix_coefficients:$mc($mc_name)\n";
        } else {
            echo "        (color_primaries:{$config['color_primaries']}(CP_UNSPECIFIED))\n";
            echo "        (transfer_characteristics:{$config['transfer_characteristics']}(TC_UNSPECIFIED))\n";
            echo "        (matrix_coefficients:{$config['matrix_coefficients']}(MC_UNSPECIFIED))\n";
        }
        if ($config["mono_chrome"]) {
            echo "        color_range:{$config['color_range']}\n";
            echo "        (subsampling_x:{$config['subsampling_x']})";
            echo " (subsampling_y:{$config['subsampling_y']})\n";
            echo "        (chroma_sampling_position:{$config['chroma_sampling_position']}(CSP_UNKNOWN))";
            echo " (separate_uv_delta_q:{$config['separate_uv_delta_q']})\n";
            return ;
        } else if (($config["color_primaries"] == self::CP_BT_709) &&
                   ($config["transfer_characteristics"] == self::TC_SRGB) &&
                   ($config["matrix_coefficients"] == self::MC_IDENTITY)) {
            echo "        (color_range:{$config['color_range']})\n";
            echo "        (subsampling_x:{$config['subsampling_x']})";
            echo " (subsampling_y:{$config['subsampling_y']})\n";
        } else {
            echo "        color_range:{$config['color_range']}\n";
            if ($obu["seq_profile"] == 0) {
                echo "          (subsampling_x:{$config['subsampling_x']})";
                echo " (subsampling_y:{$config['subsampling_y']})\n";
            } else if ($obu["seq_profile"] == 1) {
                echo "          (subsampling_x:{$config['subsampling_x']})";
                echo " (subsampling_y:{$config['subsampling_y']})\n";
            } else {
                if ($this->BitDepth == 12) {
                    echo "          subsampling_x:{$config['subsampling_x']}\n";
                    if ($config["subsampling_x"]) {
                        echo "            subsampling_y:{$config['subsampling_y']}\n";
                    } else {
                        echo "            (subsampling_y:{$config['subsampling_y']})\n";
                    }
                } else {
                    echo "            (subsampling_x:{$config['subsampling_x']})";
                    echo " (subsampling_y:{$config['subsampling_y']})\n";
                }
            }
            if ($config["subsampling_x"] && $config["subsampling_y"]) {
                $csp = $config["chroma_sample_position"];
                $csp_name = $this->getCSPName($csp);
                echo "        chroma_sample_position:$csp($csp_name)\n";
            }
        }
        echo "      separate_uv_delta_q:{$config['separate_uv_delta_q']}\n";
    }
    // 5.5.3. Timing info syntax
    function dump_timing_info($info, $opts) {
        echo "    timing_info:\n";
        echo "      num_units_in_display_tick:{$info['num_units_in_display_tick']}";
        echo " time_scale:{$info['time_scale']}";
        echo " equal_picture_interval:{$info['equal_picture_interval']}\n";
        if ($info["equal_picture_interval"] ) {
            echo "        num_ticks_per_picture_minus_1:{$info['num_ticks_per_picture_minus_1']}\n";
        }
    }
    // 5.5.4. Decoder model info syntax
    function dump_decoder_model_info($info, $opts) {
        echo "WARN: dump_decoder_model_info not implemented yet.\n";
    }
    // 5.5.5. Operating parameters info syntax
    function dump_operating_paramters_info($info, $opts) {
        echo "WARN: dump_operating_paramters_info not implemented yet.\n";
    }
    // 5.6. Temporal delimiter obu syntax
    function dump_temporal_delimiter_obu($opts) {
        echo "  dump_temporal_delimiter_obu:\n";
        echo "    SeenFrameHeader:{$this->SeenFrameHeader}\n";
    }
}
