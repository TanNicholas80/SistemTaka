<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Reprint Barcode</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            width: 65mm;
        }

        .label-wrapper {
            width: 65mm;
            page-break-inside: avoid;
            position: relative;
        }

        .label {
            width: 65mm;
            box-sizing: border-box;
            padding: 1.5mm;
            display: table;
            table-layout: auto;
        }

        .reprint-badge {
            position: absolute;
            top: 2mm;
            right: 2mm;
            font-size: 5px;
            font-weight: bold;
            letter-spacing: 0.3px;
            z-index: 10;
        }

        .new-page .label-wrapper {
            page-break-before: always;
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
            margin-top: 1mm;
        }

        .barcode-text {
            margin-top: 0.8mm;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            word-break: break-all;
            white-space: nowrap;
        }

        .right {
            display: table-cell;
            vertical-align: middle;
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
    @foreach($labels as $index => $label)
        <div class="label-wrapper {{ $index > 0 ? 'new-page' : '' }}">
            @if(!empty($label['isReprint']))
                <div class="reprint-badge">REPRINT</div>
            @endif
            <div class="label label-type-{{ $type ?: 'DEFAULT' }}">
                <div class="left">
                    <img class="qr" src="{{ $label['qrSrc'] ?? '' }}" alt="QR" />
                    <div class="barcode-text">
                        {{ $label['barcode'] ?? '' }}
                    </div>
                </div>
                <div class="right">
                    @foreach(($label['rightLines'] ?? []) as $line)
                        <div class="line">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</body>

</html>
