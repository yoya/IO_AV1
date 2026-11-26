IO_AV1
======

English version: [README.en.md](README.en.md)

AV1 のビットストリームを解釈してダンプする為のライブラリです。
IVF コンテナと、OBU (Open Bitstream Unit) を並べただけのデータ (Low Overhead Bitstream Format) を読めます。
IO_Bit が必要です。利用に制限はかけません。コピーも改変もご自由にどうぞ。MIT ライセンスにしました。
自分の環境だと動かないとか、機能が欲しいとかいった要望があれば連絡下さい。

# License

MIT License

# Install

```
% composer require yoya/io_av1
```

# Usage

- av1dump.php (構造のダンプ。`-h` を付けると OBU ごとに 16 進ダンプも出す)

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

`-f -` で標準入力から読みます。

- PHP から使う

```php
require_once 'IO/AV1.php';

$av1 = new IO_AV1();
$av1->parse(file_get_contents('input.ivf'));   // IVF でも OBU 列でもよい
$ivf = $av1->IVF;                               // OBU 列を渡したときは $av1->OBU
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

# 設計概要

| クラス | 役割 |
|---|---|
| `IO_AV1` | 入口。`parse()` は先頭が IVF (`DKIF`) なら `IO_AV1_IVF` に、そうでなければ `IO_AV1_OBU` に渡す。結果は `$IVF` か `$OBU` に入る |
| `IO_AV1_IVF` | IVF コンテナ。ヘッダ (コーデック、幅・高さ、フレームレート、フレーム数) と各フレームを読み、フレームの中身を `IO_AV1_OBU` で解釈する |
| `IO_AV1_OBU` | OBU の列。OBU ヘッダ・拡張ヘッダと、sequence header (color_config, timing_info, decoder_model_info, operating_parameters_info を含む)、temporal delimiter を解釈する |
| `IO_AV1_Bit` | `IO_Bit` の派生。仕様書の記法に合わせた `get_f(n)`, `get_uvlc()`, `get_le(n)`, `get_leb128()` を持つ |
| `IO_Bit` (別パッケージ) | ビット単位の読み込み |

- 解釈した結果は、仕様書のシンタックス要素名をキーにした配列で持つ (`$obu->OBUs[$i]["sequence_header_obu"]["seq_profile"]` など)。
- sequence header と temporal delimiter 以外の OBU は、中身を解釈せず `obu_size` だけ読み飛ばす。
- IVF のフレームは、ヘッダのフレーム数に関係なくデータの終わりまで読む (書き出し側がシークできないと、フレーム数に 0 や 0xFFFFFFFF が入る)。ヘッダの値は `frameNum` にそのまま入り、`getFrameNum()` は実際に読んだフレーム数を返す。両者が違うと、ダンプのときに標準エラーへ警告を出す。
- ダンプで `(...)` に囲まれた値は、ビットストリームから読まずに条件から決まる値です。
- 書き出し (build) の機能はありません。

# 既知の制限

- frame header、tile group、metadata などの OBU は解釈しない。
- temporal delimiter 以外で `obu_size` が 0 の OBU があると例外になる。
- 操作点 (operating point) は 0 固定で、拡張ヘッダの temporal_id / spatial_id による選別はしない。
- PHP 8.2 以降では動的プロパティの Deprecated メッセージが出る。`php -d error_reporting='E_ALL & ~E_DEPRECATED' ...` で消せる。

# Test

```
% php tests/run.php
```

# Reference

- AV1 Bitstream & Decoding Process Specification
  - https://aomediacodec.github.io/av1-spec/av1-spec.pdf
- IVF
  - https://wiki.multimedia.cx/index.php/IVF
- AV1 のサンプル (Netflix)
  - http://download.opencontent.netflix.com/?prefix=AV1/
