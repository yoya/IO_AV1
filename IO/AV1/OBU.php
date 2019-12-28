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
    //
    var $OperatingPointIdc = null;
    var $OrderHintBits = null;
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
                switch ($obu_type) {
                case self::OBU_SEQUENCE_HEADER:
                    $obu["sequence_header_obu"] = $this->parse_sequence_header_obu($bit);
                    break;
                case self::OBU_TEMPORAL_DELIMITER:
                    break;
                case self::OBU_FRAME_HEADER:
                    break;
                case self::OBU_REDUNDANT_FRAME_HEADER:
                    break;
                default:
                    break;
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
                $obu["seq_force_screen_content_tools"] = SELECT_SCREEN_CONTENT_TOOLS;
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
        return $obu;
    }
    function parse_timing_info($bit) {
        $info = [];
        throw "ERROR: parse_timing_info: not implemented yet\n";
    }
    function parse_decoder_model_info($bit) {
        $info = [];
        throw "ERROR: parse_decoder_model_info: not implemented yet\n";
    }
    function parse_operating_paramters_info($bit) {
        $info = [];
        throw "ERROR: parse_operating_parameters_info: not implemented yet\n";
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
            switch ($obu_type) {
            case self::OBU_SEQUENCE_HEADER:
                $this->dump_sequence_header_obu($obu["sequence_header_obu"], $opts);
                break;
            case self::OBU_TEMPORAL_DELIMITER:
                break;
            case self::OBU_FRAME_HEADER:
                break;
            case self::OBU_REDUNDANT_FRAME_HEADER:
                break;
            default:
                break;
            }
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
            $this->dump_obu_extention_header($obu["obu_extention_header"], $opts);
        }
    }
    function dump_obu_extention_header($obu, $opts = array()) {
        echo "    obu_extention_header:\n";
        echo "      temporal_id:{$obu['temporal_id']} ";
        echo "spatial_id:{$obu['spatial_id']} ";
        echo "reserved_3bits:{$obu['reserved_3bits']}\n";
    }
    function dump_sequence_header_obu($obu, $opts = array()) {
        echo "  sequence_header_obu:\n";
        echo "    seq_profile:{$obu['seq_profile']} ";
        echo "still_picture:{$obu['still_picture']} ";
        echo "reduced_still_picture_header:{$obu['reduced_still_picture_header']}\n";
        echo "    reduced_still_picture_header:{$obu['reduced_still_picture_header']}\n";
        if ($obu["reduced_still_picture_header"]) {
            echo "      timing_info_present_flag:{$obu['timing_info_present_flag']}\n";
            echo " decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}";
            echo " initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']}";
            echo " operating_points_cnt_minus_1:{$obu['operating_points_cnt_minus_1']}";
            echo " operating_point_idc[0]:{$obu['operating_point_idc'][0]}";
            echo " seq_level_idx[0]:{$obu['seq_level_idx'][0]}";
            echo " seq_tier[0]:{$obu['seq_tier'][0]}";
            echo " decoder_model_present_for_this_op[0]:{$obu['decoder_model_present_for_this_op'][0]}";
            echo " initial_display_delay_present_for_this_op[0]:{$obu['initial_display_delay_present_for_this_op'][0]}";
        } else {
            echo "      timing_info_present_flag:{$obu['timing_info_present_flag']}\n";
            if ($obu["timing_info_present_flag"]) {
                $this->dump_timing_info($obu["timing_info"], $opts);
                echo "        decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}\n";
                if ($obu["decoder_model_info_present_flag"]) {
                    $this->dump_decoder_model_info($obu["decoder_model_info"], $opts);
                    ;
                }
            } else {
                echo "        decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}\n";
            }
            echo "      initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']}\n";
            echo "      operating_points_cnt_minus_1:{$obu['operating_points_cnt_minus_1']}\n";
            for ($i = 0; $i <= $obu["operating_points_cnt_minus_1"]; $i++) {
                echo "      operating_point_idc_$i:{$obu['operating_point_idc'][$i]}\n";
                echo "      seq_level_idx_$i:{$obu['seq_level_idx'][$i]}\n";
                echo "      seq_tier_$i:{$obu['seq_tier'][$i]}\n";
                echo "      decoder_model_info_present_flag:{$obu['decoder_model_info_present_flag']}\n";
                if ($obu["decoder_model_info_present_flag"]) {
                    echo "        decoder_model_present_for_this_op_$i:{$obu["decoder_model_present_for_this_op"][$i]}\n";
                    echo "        decoder_model_present_for_this_op_$i:{$obu['decoder_model_present_for_this_op'][$i]}\n";
                    if ($obu["decoder_model_present_for_this_op"][$i]) {
                        $this->dump_operating_paramters_info($obu["operating_paramters_info"], $opts);
                    }
                } else {
                    echo "      decoder_model_present_for_this_op_$i:{$obu['decoder_model_present_for_this_op'][$i]}\n";
                }
                echo "    initial_display_delay_present_flag:{$obu['initial_display_delay_present_flag']}\n";
                if ($obu["initial_display_delay_present_flag"]) {
                    echo "      initial_display_delay_minus_1_$i:{$obu['initial_display_delay_minus_1'][$i]}\n";
                }
            }
        }
        echo "    OperatingPointIdc:{$this->OperatingPointIdc}\n";
        echo "    frame_width_bits_minus_1:{$obu['frame_width_bits_minus_1']}";
        echo " frame_height_bits_minus_1:{$obu['frame_height_bits_minus_1']}\n";
        echo "    frame_width_minus_1:{$obu['frame_width_minus_1']}";
        echo " frame_height_minus_1:{$obu['frame_height_minus_1']}\n";
        echo "    frame_id_numbers_present_flag:{$obu['frame_id_numbers_present_flag']}\n";
        if ($obu["frame_id_numbers_present_flag"]) {
            echo "      delta_frame_id_length_minus_2:{$obu['delta_frame_id_length_minus_2']}";
            echo " additional_frame_id_length_minus_1:{$obu['additional_frame_id_length_minus_1']}\n";
        }
        echo "    use_128x128_superblock:{$obu['use_128x128_superblock']}";
        echo " enable_filter_intra:{$obu['enable_filter_intra']}";
        echo " enable_intra_edge_filter:{$obu['enable_intra_edge_filter']}\n";
        if ($obu["reduced_still_picture_header"]) {
            echo "      enable_interintra_compound:{$obu['enable_interintra_compound']}";
            echo " enable_masked_compound:{$obu['enable_masked_compound']}\n";
            echo "      enable_warped_motion:{$obu['enable_warped_motion']}";
            echo " enable_dual_filter:{$obu['enable_dual_filter']}\n";
            echo "      enable_order_hint:{$obu['enable_order_hint']}\n";
            echo "      enable_jnt_comp:{$obu['enable_jnt_comp']}";
            echo " enable_ref_frame_mvs:{$obu['enable_ref_frame_mvs']}\n";
            echo "      seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}(SELECT_SCREEN_CONTENT_TOOLS)\n";
            echo "      seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV)\n";
            echo "      OrderHintBits:{$this->OrderHintBits}\n";
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
                echo "        seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}(SELECT_SCREEN_CONTENT_TOOLS)\n";
            } else {
                echo "        seq_force_screen_content_tools:{$obu['seq_force_screen_content_tools']}\n";
            }
            if ($obu["seq_force_screen_content_tools"] > 0) {
                echo "        seq_choose_integer_mv:{$obu['seq_choose_integer_mv']}\n";
                if ($obu["seq_choose_integer_mv"]) {
                    echo "          seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV)\n";
                } else {
                    echo "          seq_force_integer_mv:{$obu['seq_force_integer_mv']}\n";
                }
            } else {
                echo "          seq_force_integer_mv:{$obu['seq_force_integer_mv']}(SELECT_INTEGER_MV)\n";
            }
            if ($obu["enable_order_hint"]) {
                echo "          order_hint_bits_minus_1:{$obu['order_hint_bits_minus_1']}";
                echo " OrderHintBits:{$this->OrderHintBits}\n";
            } else {
                echo "          OrderHintBits:{$this->OrderHintBits}\n";
            }
        }
        echo "    enable_superres:{$obu['enable_superres']}";
        echo " enable_cdef:{$obu['enable_cdef']}\n";
        echo " enable_restoration:{$obu['enable_restoration']}\n";
        
    }
    function dump_timing_info($info, $opts) {
        echo "WARN: dump_timing_info not implemented yet.\n";
    }
    function dump_decoder_model_info($info, $opts) {
        echo "WARN: dump_decoder_model_info not implemented yet.\n";
    }
    function dump_operating_paramters_info($info, $opts) {
        echo "WARN: dump_operating_paramters_info not implemented yet.\n";
    }
}
