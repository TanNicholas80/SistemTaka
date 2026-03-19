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
            padding: 0;
            font-family: Arial, sans-serif;
            width: 80mm;
        }
        .labels-wrap {
            width: 80mm;
        }
        .label {
            width: 80mm;
            border: 1px solid #000;
            border-top: none;
            box-sizing: border-box;
            padding: 2mm;
            display: table;
            table-layout: fixed;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .label:first-child { border-top: 1px solid #000; }
        .left {
            width: 25mm;
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .qr {
            width: 23mm;
            height: 23mm;
            object-fit: contain;
        }
        .barcode-text {
            margin-top: 1mm;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            word-break: break-all;
        }
        .right {
            display: table-cell;
            vertical-align: middle;
            width: 52mm;
            font-size: 11px;
            line-height: 1.15;
            padding-left: 2mm;
        }
        .line {
            margin-bottom: 0.8mm;
            word-break: break-word;
            font-weight: bold;
            text-transform: uppercase;
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

