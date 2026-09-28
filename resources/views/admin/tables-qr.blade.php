<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('QR codes') }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600,700&family=noto-sans-lao:400,600,700&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Figtree', 'Noto Sans Lao', sans-serif; margin: 0; padding: 10mm; color: #111; }
        .toolbar { margin-bottom: 10mm; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8mm; }
        .card { border: 1px dashed #999; border-radius: 6px; padding: 8mm; text-align: center; break-inside: avoid; }
        .card .logo { margin: 0 auto 2mm; width: 16mm; }
        .card .logo svg { width: 100%; height: auto; }
        .card h1 { margin: 0; font-size: 20pt; }
        .card .name-en { margin: 0 0 2mm; font-size: 12pt; color: #555; }
        .card h2 { margin: 0 0 4mm; font-size: 32pt; }
        .card svg { width: 55mm; height: 55mm; }
        .card p { margin: 3mm 0 0; font-size: 11pt; color: #444; }
        .card p.url { margin-top: 1mm; font-size: 7pt; color: #888; word-break: break-all; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .grid { gap: 6mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <div class="grid">
        @foreach ($tables as $table)
            <div class="card">
                <div class="logo">{!! file_get_contents(public_path('logo.svg')) !!}</div>
                <h1>{{ config('restaurant.name_lo') }}</h1>
                <div class="name-en">{{ config('app.name') }}</div>
                <h2>{{ __('Table') }} {{ $table->number }}</h2>
                {!! \App\Support\QrCode::svg($table->customerUrl(), 300) !!}
                <p>ສະແກນເພື່ອສັ່ງອາຫານ · Scan to order</p>
                <p class="url">{{ $table->customerUrl() }}</p>
            </div>
        @endforeach
    </div>
</body>
</html>
