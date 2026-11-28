IO_AV1
======

This is a library for interpreting and dumping the AV1 bitstream.
It reads the IVF container and plain sequences of OBUs (Open Bitstream Units, the Low Overhead Bitstream Format).
IO_Bit is required. Not limited in use, free to copy and modify by MIT license.
Please contact me if it doesn't work in your environment or you want new features.

# License

MIT License

# Install

```
% composer require yoya/io_av1
```

# Usage

- av1dump.php (structure dump; `-h` also prints a hex dump of each OBU)

```
% php vendor/yoya/io_av1/sample/av1dump.php -f input.ivf
signature:DKIF version:0 headerLength:32
codec:AV01 width:16 height:16 frameRate:30=30/1 frameNum:1
[0]frameSize:13 time:0 payload:12 00 0a 09 00 00 00 40 ...
  [0]obu_type:2 (OBU_TEMPORAL_DELIMITER)  obu_size:0
  obu_header:
    obu_forbidden_bit:0 obu_type:2 obu_extension_flag:0
    obu_has_size_field:1 obu_reserved_1bit:0
  dump_temporal_delimiter_obu:
    SeenFrameHeader:0
  [1]obu_type:1 (OBU_SEQUENCE_HEADER)  obu_size:9
  obu_header:
    obu_forbidden_bit:0 obu_type:1 obu_extension_flag:0
    obu_has_size_field:1 obu_reserved_1bit:0
  sequence_header_obu:
    seq_profile:0 still_picture:0 reduced_still_picture_header:0
(omit)
    frame_width_bits_minus_1:3 frame_height_bits_minus_1:3
    frame_width_minus_1:15 frame_height_minus_1:15
(omit)
    film_grain_params_present:0
```

`-f -` reads from standard input.

- From PHP

```php
require_once 'IO/AV1.php';

$av1 = new IO_AV1();
$av1->parse(file_get_contents('input.ivf'));   // IVF or a sequence of OBUs
$ivf = $av1->IVF;                               // $av1->OBU for a sequence of OBUs
echo $ivf->codec, " ", $ivf->width, "x", $ivf->height, " frames:", $ivf->getFrameNum(), "\n";
foreach ($ivf->frames[0]["obu"]->OBUs as $obu) {
    $type = $obu["obu_header"]["obu_type"];
    echo IO_AV1_OBU::getOBUTypeName($type), " size:", $obu["obu_size"], "\n";
    if (isset($obu["sequence_header_obu"])) {
        $sh = $obu["sequence_header_obu"];
        echo "  profile:", $sh["seq_profile"], " ", $sh["frame_width_minus_1"] + 1, "x", $sh["frame_height_minus_1"] + 1, "\n";
    }
}
```

```
AV01 16x16 frames:1
OBU_TEMPORAL_DELIMITER size:0
OBU_SEQUENCE_HEADER size:9
  profile:0 16x16
```

# Design overview

| Class | Role |
|---|---|
| `IO_AV1` | Entry point. `parse()` hands the data to `IO_AV1_IVF` if it starts with IVF (`DKIF`), otherwise to `IO_AV1_OBU`. The result goes to `$IVF` or `$OBU` |
| `IO_AV1_IVF` | IVF container. Reads the header (codec, width/height, frame rate, frame count) and each frame, and parses the frame data with `IO_AV1_OBU` |
| `IO_AV1_OBU` | Sequence of OBUs. Parses the OBU header, the extension header, the sequence header (including color_config, timing_info, decoder_model_info and operating_parameters_info) and the temporal delimiter |
| `IO_AV1_Bit` | Subclass of `IO_Bit` with `get_f(n)`, `get_uvlc()`, `get_le(n)`, `get_leb128()` named after the spec's descriptors |
| `IO_Bit` (separate package) | Bit-level reading |

- Parsed results are arrays keyed by the spec's syntax element names (`$obu->OBUs[$i]["sequence_header_obu"]["seq_profile"]` etc.).
- OBUs other than the sequence header and the temporal delimiter are not parsed; they are skipped by `obu_size`.
- IVF frames are read until the data ends, regardless of the header's frame count (writers that cannot seek put 0 or 0xFFFFFFFF there). The header value goes to `frameNum` as is, and `getFrameNum()` returns the number of frames read. When they differ, the dump prints a warning to stderr.
- Values in `(...)` in the dump are not read from the bitstream but determined by conditions.
- There is no writer (build).

# Known limitations

- Frame header, tile group, metadata and other OBUs are not parsed.
- An OBU with `obu_size` 0 other than the temporal delimiter throws an exception.
- The operating point is fixed to 0; OBUs are not selected by temporal_id / spatial_id of the extension header.
- The Annex B (length delimited) format (`aomenc --annexb=1` and others) is not supported. It is not detected either: given such data, the leading lengths are read as OBU headers and wrong values are shown without an error.
- PHP 8.2 or later prints Deprecated messages for dynamic properties. `php -d error_reporting='E_ALL & ~E_DEPRECATED' ...` hides them.

# Test

```
% php tests/run.php
```

# Reference

- AV1 Bitstream & Decoding Process Specification
  - https://aomediacodec.github.io/av1-spec/av1-spec.pdf
- IVF
  - https://wiki.multimedia.cx/index.php/IVF
- AV1 samples (Netflix)
  - http://download.opencontent.netflix.com/?prefix=AV1/
