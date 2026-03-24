<!doctype html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Print Non PL</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            margin: 0;
            padding-top: 1.6mm;
            padding-bottom: 0mm;
            font-family: Arial, sans-serif;
            width: 65mm;
        }
        .labels-wrap {
            width: 65mm;
        }
        .label {
            width: 65mm;
            border-top: none;
            box-sizing: border-box;
            padding: 1.5mm;
            display: table;
            table-layout: auto;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .left {
            width: 21mm;
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .qr {
            width: 16mm;
            height: 16mm;
            object-fit: contain;
        }
        .barcode-text {
            margin-top: 0.8mm;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            word-break: break-all;
        }
        .right {
            display: table-cell;
            vertical-align: start;
            width: 41mm;
            font-size: 11px;
            line-height: 1.1;
            padding-left: 1.5mm;
        }
        .line {
            margin-bottom: 0.5mm;
            word-break: break-word;
            font-weight: bold;
            text-transform: uppercase;
        }
        .line:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
@foreach($labels as $label)
    <div class="label label-type-{{ $type ?: 'DEFAULT' }}">
        <div class="left">
            <img class="qr" src="{{ $label['qrSrc'] ?? '' }}" alt="QR"/>
            <div class="barcode-text">{{ $label['barcode'] ?? '' }}</div>
        </div>
        <div class="right">
            @foreach(($label['rightLines'] ?? []) as $line)
                <div class="line">{{ $line }}</div>
            @endforeach
        </div>
    </div>
@endforeach
</body>
</html>

