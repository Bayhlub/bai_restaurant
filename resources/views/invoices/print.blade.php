<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-lao:400,700&display=swap" rel="stylesheet" />
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { font-family: 'Noto Sans Lao', 'Courier New', monospace; font-size: 12px; line-height: 1.35; }
        .receipt { width: 72mm; margin: 0 auto; padding: 4mm 0 8mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .logo { width: 14mm; height: 14mm; margin: 0 auto 1mm; display: block; }
        .name { font-size: 17px; font-weight: 700; }
        .name-en { font-size: 13px; font-weight: 700; }
        .muted { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.qty { width: 8mm; }
        td.amt { width: 20mm; text-align: right; white-space: nowrap; }
        .sub { font-size: 10px; color: #333; }
        .total td { font-size: 15px; font-weight: 700; padding-top: 3px; }
        .toolbar { position: fixed; top: 0; left: 0; right: 0; background: #f3f4f6; padding: 10px; text-align: center; font-family: sans-serif; }
        .toolbar a, .toolbar button { display: inline-block; margin: 0 6px; padding: 8px 16px; font-size: 14px; cursor: pointer; text-decoration: none; color: #111; background: #fff; border: 1px solid #ccc; border-radius: 6px; }
        @media screen { body { background: #e5e7eb; padding-top: 60px; } .receipt { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); padding: 6mm 4mm 10mm; } }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨 {{ __('Print') }}</button>
        <a href="{{ route('cashier') }}">← {{ __('All tables') }}</a>
    </div>

    <div class="receipt">
        <div class="center">
            <svg class="logo" viewBox="0 0 48 48" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="24" cy="24" r="21"/>
                <path d="M9 25 L39 25 A20 20 0 0 1 9 25 Z" fill="#000" stroke="none"/>
                <path d="M17 21 c-2.5 -3 2.5 -4.5 0 -7.5"/>
                <path d="M24 21 c-2.5 -3.5 2.5 -5.5 0 -9"/>
                <path d="M31 21 c-2.5 -3 2.5 -4.5 0 -7.5"/>
            </svg>
            <div class="name">{{ config('restaurant.name_lo') }}</div>
            <div class="name-en">{{ config('app.name') }}</div>
            @if (config('restaurant.address'))<div class="muted">{{ config('restaurant.address') }}</div>@endif
            @if (config('restaurant.phone'))<div class="muted">☎ {{ config('restaurant.phone') }}</div>@endif
        </div>

        <hr>

        <table class="muted">
            <tr><td>ໃບບິນ / Invoice</td><td class="right bold">{{ $invoice->number }}</td></tr>
            <tr><td>ວັນທີ / Date</td><td class="right">{{ $invoice->paid_at->format('d/m/Y H:i') }}</td></tr>
            <tr><td>ໂຕະ / Table</td><td class="right bold">{{ $invoice->session->table->number }}</td></tr>
            @if ($invoice->cashier)
                <tr><td>ພະນັກງານ / Cashier</td><td class="right">{{ $invoice->cashier->name }}</td></tr>
            @endif
        </table>

        <hr>

        <table>
            @foreach ($lines as $line)
                <tr>
                    <td class="qty">{{ $line->qty }}×</td>
                    <td>
                        {{ $line->name_lo }}
                        <div class="sub">{{ $line->name_en }} @ {{ number_format($line->unit_price) }}</div>
                    </td>
                    <td class="amt">{{ number_format($line->lineTotal()) }}</td>
                </tr>
            @endforeach
        </table>

        <hr>

        <table>
            <tr><td>ລວມ / Subtotal</td><td class="amt">{{ number_format($invoice->subtotal) }}</td></tr>
            @if ($invoice->discount > 0)
                <tr><td>ສ່ວນຫຼຸດ / Discount</td><td class="amt">-{{ number_format($invoice->discount) }}</td></tr>
            @endif
            <tr class="total"><td>ລວມທັງໝົດ / TOTAL</td><td class="amt">{{ number_format($invoice->total) }} ₭</td></tr>
            <tr><td>ເງິນສົດ / Cash</td><td class="amt">{{ number_format($invoice->cash_received) }}</td></tr>
            <tr><td>ເງິນທອນ / Change</td><td class="amt">{{ number_format($invoice->change_amount) }}</td></tr>
        </table>

        <hr>

        <div class="center">
            <div>{{ config('restaurant.receipt_footer_lo') }}</div>
            <div class="muted">{{ config('restaurant.receipt_footer_en') }}</div>
        </div>
    </div>

    @unless (request()->boolean('noprint'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endunless
</body>
</html>
