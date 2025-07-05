<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <style>
    @font-face {
      font-family: 'DejaVu Sans';
      font-style: normal;
      font-weight: normal;
      src: url("{{ base_path('vendor/tecnickcom/tcpdf/fonts/DejaVuSans.ttf') }}") format("truetype");
    }

    @page { 
      size: 58mm 40mm; 
      margin: 0;
    }

    body {
      margin: 0; 
      padding: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 9px;
    }

    .label-box {
      position: relative;
      width: 53mm;
      height: 35mm;
      padding: 8px;
      background: #fff;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }

    .label-header {
      text-align: center;
      margin-bottom: 4px;
    }

    .label-header-text {
      font-weight: bold;
      font-size: 11px;
    }

    .label-content {
      display: table;
      width: 100%;
    }

    .label {
      display: table-cell;
      width: 50%;
      vertical-align: middle;
      box-sizing: border-box;
    }

    .text-center {
      text-align: center;
    }

    .label.left {
      width: 50%;
    }

    .label-size {
      font-size: 14px;
      font-weight: bold;
    }

    .label-barcode-block {
      margin-top: 5px;
      text-align: center;
    }

    .cz-logo{
      height: 5mm;
    }

    .label-cz-logo{
      height: 5mm;
    }

    .label-text {
      position: relative;
      height: 18mm;
      margin: 0;
      padding: 0;
    }

    .label-text p {
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      transform: translateY(-50%);
      margin: 0;
      text-align: center;
    }
  </style>
</head>
<body>
  @foreach($labels as $i => $label)
    @if($withDM)
      <div class="label-box">
        <div class="label-content" style="margin-top: 5px">
        <div class="label text-center left" style="width: 45%; height: 23mm;">
          <img 
            src="data:image/png;base64,{{ $label->barcode2D }}"
            alt="DataMatrix" 
            style="width: 20mm; height: 20mm;"
          >
        </div>

        <div class="label text-center right">
          <div class="label-cz-logo">
            <img
              class="cz-logo"
              alt="Честный знак"
              src="data:image/png;base64,{{ $label->czLogo }}"
            />
          </div>  
          <div class="label-text">
            <p>{{$label->name}}, цвет {{$label->color}}, размер {{$label->size}}</p>
          </div>
        </div>

        </div>
        <div style="text-align: right; margin-bottom: 4px">
          <span style="font-size: 11px; font-weight: bold; margin-right: 10px">{{ $label->packerId }}</span>
          <span style="font-size: 11px; font-weight: bold">{{ $label->id }}</span>
        </div>
        <div>
          <span style="margin-right: 5px; font-size: 9px">{{$label->gtin14}}</span>
          <span style="font-size: 9px">{{$label->serialNumber}}</span>
        </div>
      </div>
    @endif

    @if($dupDM)
      <div class="label-box">
        <div class="label-content" style="margin-top: 5px">
        <div class="label text-center left" style="width: 45%; height: 23mm;">
          <img 
            src="data:image/png;base64,{{ $label->barcode2D }}"
            alt="DataMatrix" 
            style="width: 20mm; height: 20mm;"
          >
        </div>

        <div class="label text-center right">
          <div class="label-cz-logo">
            <img
              class="cz-logo"
              alt="Честный знак"
              src="data:image/png;base64,{{ $label->czLogo }}"
            />
          </div>  
          <div class="label-text">
            <p>{{$label->name}}, цвет {{$label->color}}, размер {{$label->size}}</p>
          </div>
        </div>

        </div>
        <div style="text-align: right; margin-bottom: 4px">
          <span style="font-size: 11px; font-weight: bold; margin-right: 10px">{{ $label->packerId }}</span>
          <span style="font-size: 11px; font-weight: bold">{{ $label->id }}</span>
        </div>
        <div>
          <span style="margin-right: 5px; font-size: 9px">{{$label->gtin14}}</span>
          <span style="font-size: 9px">{{$label->serialNumber}}</span>
        </div>
      </div>
    @endif

    @if($withC128)
      <div class="label-box">
        <div class="label-header">
          <span class="label-header-text">{{ $label->name }}</span>
        </div>

        <div class="label-content">
          <div class="label" style="width: 80%">
            <div class="label-line">Артикул: {{ $label->article }}</div>
            <div class="label-line">Цвет: {{ $label->color }}</div>
          </div>

          <div class="label text-center" style="width: 20%">
            <div class="label-size">{{ $label->size }}</div>
          </div>
        </div>
        
        <div class="label-content">
          <div class="label">
            <div class="label-line">{{ $label->client }}</div>
            <div class="label-line">Состав: {{ $label->composition }}</div>
          </div>                  
        </div>
        
        <div class="label-barcode-block">
          <img
            alt="Штрихкод"
            src="data:image/png;base64,{{ $label->barcode1D }}"
          />
        </div>
      </div>
    @endif
    @if(! $loop->last)
      <div style="page-break-after: always;"></div>
    @endif   
  @endforeach
</body>
</html>