<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <style>
    @page { size: {{ (float)($schema['page']['w'] ?? 58) }}mm {{ (float)($schema['page']['h'] ?? 40) }}mm; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, sans-serif; }
    .page {
      position: relative;
      width:  {{ (float)($schema['page']['w'] ?? 58) }}mm;
      height: {{ (float)($schema['page']['h'] ?? 40) }}mm;
    }
    .blk  { position:absolute;  margin:0; padding:0; }
    .text { white-space: pre-line; word-break: break-word; margin:0; padding:0; }
    .barcode1d table {
    width: 100% !important;
    height: 100% !important;
    border-collapse: collapse;
  }
  .barcode1d td {
    padding: 0;
    margin: 0;
  }
  </style>
</head>
<body>
@php
  $pad = (float)($schema['page']['pad'] ?? 0);

  $resolve = function($str, $label) {
    return preg_replace_callback('/\{\{\s*([\w\.]+)(\|[^}]*)?\s*\}\}/u', function($m) use ($label) {
      $key = $m[1];
      $fallback = isset($m[2]) ? ltrim($m[2], '|') : '';
      $val = data_get($label, $key);
      return ($val === '' || $val === null) ? $fallback : $val;
    }, (string)$str);
  };
@endphp


  @foreach($labels as $label)
  @foreach($schemas as $index => $schema)
    @php
      $count = 1;
      if($label->duplicateDM && $index == 0) {
        $count = 2;
      }
      if($label->duplicateSHK && $index == 1) {
        $count = 2;
      }
    @endphp

    @for($i = 0; $i < $count; $i++)
      <div class="page">
        @foreach(($schema['blocks'] ?? []) as $b)
          @php
            $type = $b['type'] ?? 'text';
            $x = $pad + (float)($b['x'] ?? 0);
            $y = $pad + (float)($b['y'] ?? 0);
            $w = (float)($b['w'] ?? 10);
            $h = (float)($b['h'] ?? 5);

            $st    = $b['style'] ?? [];
            $fs_mm = max(0.5, (float)($st['size'] ?? 3));
            $al    = $st['align'] ?? 'left';
            $underline    = !empty($st['underline']);
            $italic    = !empty($st['italic']);
            $bd    = !empty($st['bold']);
          @endphp

          @if($label->preview)
          <div style="
            position:absolute;
            top:30%; left:15%;
            font-size:4.5mm;
            color:rgba(75, 75, 75, 0.1);
            font-weight:bold;
            white-space:nowrap;
          ">
            ПРЕДПРОСМОТР <br> НЕ ДЛЯ ПЕЧАТИ
          </div>
          @endif

          {{-- Текст --}}
          @if($type === 'text')
            @php
              $raw = (string)$resolve($b['text'] ?? '', $label);
              $txt = $raw;
            @endphp
            <div class="blk text"
                style="left:{{$x}}mm;top:{{$y}}mm;width:{{$w}}mm;height:{{$h}}mm;
                        font-size:{{$fs_mm}}mm; line-height: {{$fs_mm*0.9}}mm;
                        text-align:{{$al}}; font-weight:{{$bd?'700':'400'}};
                        text-decoration:{{$underline ? 'underline' : 'none'}};
                        font-style:{{$italic ? 'italic' : 'normal'}};">
              <span style="position:relative; top:-{{ round($fs_mm*2.2,2) }}mm;">
                {{ $txt }}
              </span>
            </div>

          {{-- Штрихкод --}}
          @elseif($type === 'barcode')
            @if(!empty($label->barcode1D))
            @php
              $gen1D = new \Milon\Barcode\DNS1D();
              $barcodeHeight = round($h * 0.82 * 3.78);
              $barcodeHtml = $gen1D->getBarcodeHTML(
                $label->barcode, 'EAN13', 1, $barcodeHeight, 'black', true
              );
            @endphp

              <div class="blk barcode1d"
                  style="left:{{$x}}mm;top:{{$y}}mm;width:{{$w}}mm;height:{{$h}}mm; text-align:center;">
                {!! $barcodeHtml !!}
                <style>
                  .barcode1d div {
                    font-size: {{ round($h * 0.18, 2) }}mm !important;
                    letter-spacing: {{ round($w / (strlen($label->barcode ?? '') * 12), 2) }}mm;
                    text-align: center;
                  }
                </style>
              </div>
            @endif
          
          {{-- Datamatrix --}}
          @elseif($type === 'datamatrix')
            @if(!empty($label->barcode2D))
              <div class="blk"
                  style="left:{{$x}}mm;top:{{$y}}mm;width:{{$w}}mm;height:{{$h}}mm;">
                <img src="data:image/png;base64,{{ $label->barcode2D }}"
                    style="width:100%;height:100%;object-fit:contain;" alt="ЧЗ">
              </div>
            @endif

          {{-- Логотип ЧЗ --}}
          @elseif($type === 'czLogo')
            @if(!empty($label->czLogo))
              <div class="blk"
                  style="left:{{$x}}mm;top:{{$y}}mm;width:{{$w}}mm;height:{{$h}}mm;">
                <img src="data:image/png;base64,{{ $label->czLogo }}"
                    style="max-width:100%;max-height:100%;object-fit:contain;" alt="ЧЗ">
              </div>
            @endif

          {{-- Обычная картинка --}}
          @elseif($type === 'image')
            @php $src = $resolve($b['src'] ?? '', $label); @endphp
            @if($src)
              <div class="blk"
                  style="left:{{$x}}mm;top:{{$y}}mm;width:{{$w}}mm;height:{{$h}}mm;">
                <img src="{{ $src }}"
                    style="width:100%;height:100%;object-fit:contain;" alt="">
              </div>
            @endif
          @endif
        @endforeach
      </div>
    @endfor
    @if(!$loop->last)
      <div style="page-break-after: always;"></div>
    @endif
  @endforeach
@endforeach
</body>
</html>
