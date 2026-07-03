<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $snapshot['invoice_number'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1c1917;
            line-height: 1.5;
        }
        .page { padding: 40px 45px; }
        .header { width: 100%; margin-bottom: 30px; }
        .header td { vertical-align: top; }
        .brand { font-size: 20pt; font-weight: bold; letter-spacing: 1px; }
        .doc-title { font-size: 15pt; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; font-size: 9pt; color: #57534e; margin-top: 4px; }
        .addresses { width: 100%; margin-bottom: 28px; }
        .addresses td { vertical-align: top; width: 50%; }
        .label {
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px;
            color: #78716c; margin-bottom: 4px; font-weight: bold;
        }
        .muted { color: #57534e; font-size: 9pt; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items th {
            text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px;
            color: #78716c; padding: 6px 8px; border-bottom: 2px solid #1c1917;
        }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { padding: 7px 8px; border-bottom: 1px solid #e7e5e4; font-size: 9.5pt; }
        .item-variant { color: #78716c; font-size: 8.5pt; }
        table.totals { width: 45%; margin-left: 55%; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; font-size: 9.5pt; }
        table.totals td.num { text-align: right; }
        table.totals tr.grand td {
            font-weight: bold; font-size: 11pt;
            border-top: 2px solid #1c1917; padding-top: 8px;
        }
        .tax-note { margin-top: 16px; font-size: 8.5pt; color: #57534e; }
        .footer {
            position: fixed; bottom: 25px; left: 45px; right: 45px;
            border-top: 1px solid #e7e5e4; padding-top: 10px;
            font-size: 7.5pt; color: #78716c; text-align: center;
        }
    </style>
</head>
<body>
<div class="page">
    <table class="header">
        <tr>
            <td class="brand">{{ $snapshot['seller']['company_name'] }}</td>
            <td>
                <div class="doc-title">INVOICE</div>
                <div class="doc-meta">
                    Invoice no. <strong>{{ $snapshot['invoice_number'] }}</strong><br>
                    Invoice date: {{ $snapshot['issued_at'] }}<br>
                    Order no. {{ $snapshot['order']['number'] }}<br>
                    Order date: {{ $snapshot['order']['placed_at'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="addresses">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <strong>{{ $snapshot['buyer']['name'] }}</strong><br>
                {{ $snapshot['buyer']['street'] }}<br>
                @if ($snapshot['buyer']['address_extra'] !== '')
                    {{ $snapshot['buyer']['address_extra'] }}<br>
                @endif
                {{ $snapshot['buyer']['postal_code'] }} {{ $snapshot['buyer']['city'] }}@if ($snapshot['buyer']['state'] !== ''), {{ $snapshot['buyer']['state'] }}@endif<br>
                {{ $snapshot['buyer']['country'] }}<br>
                <span class="muted">{{ $snapshot['buyer']['email'] }}</span>
            </td>
            <td>
                <div class="label">Seller</div>
                <strong>{{ $snapshot['seller']['company_name'] }}</strong><br>
                {{ $snapshot['seller']['address_line1'] }}<br>
                @if ($snapshot['seller']['address_line2'] !== '')
                    {{ $snapshot['seller']['address_line2'] }}<br>
                @endif
                {{ $snapshot['seller']['postal_code'] }} {{ $snapshot['seller']['city'] }}<br>
                {{ $snapshot['seller']['country'] }}<br>
                <span class="muted">
                    Tax ID: {{ $snapshot['seller']['tax_identifier'] }}
                    @if ($snapshot['seller']['email'] !== '')
                        <br>{{ $snapshot['seller']['email'] }}
                    @endif
                    @if ($snapshot['seller']['website'] !== '')
                        <br>{{ $snapshot['seller']['website'] }}
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 46%;">Item</th>
                <th style="width: 16%;">SKU</th>
                <th class="num" style="width: 8%;">Qty</th>
                <th class="num" style="width: 15%;">Unit price</th>
                <th class="num" style="width: 15%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($snapshot['items'] as $item)
                <tr>
                    <td>
                        {{ $item['name'] }}
                        @if ($item['variant'] !== '')
                            <div class="item-variant">{{ $item['variant'] }}</div>
                        @endif
                    </td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="num">{{ $item['quantity'] }}</td>
                    <td class="num">{{ number_format($item['unit_price'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
                    <td class="num">{{ number_format($item['line_total'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="num">{{ number_format($snapshot['totals']['subtotal'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
        </tr>
        @if ($snapshot['totals']['discount_total'] > 0)
            <tr>
                <td>
                    Discount
                    @if ($snapshot['totals']['coupon_code'] !== '')
                        <span class="muted">({{ $snapshot['totals']['coupon_code'] }})</span>
                    @endif
                </td>
                <td class="num">-{{ number_format($snapshot['totals']['discount_total'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
            </tr>
        @endif
        @if ($snapshot['totals']['shipping_total'] > 0)
            <tr>
                <td>Shipping</td>
                <td class="num">{{ number_format($snapshot['totals']['shipping_total'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
            </tr>
        @endif
        @if ($snapshot['totals']['tax_total'] > 0)
            <tr>
                <td>Included VAT</td>
                <td class="num">{{ number_format($snapshot['totals']['tax_total'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="num">{{ number_format($snapshot['totals']['total'], 2) }} {{ $snapshot['totals']['currency'] }}</td>
        </tr>
    </table>

    <div class="tax-note">
        {{ $snapshot['tax_note'] }}<br>
        Date of supply: {{ $snapshot['order']['supply_date'] }}.
        Payment method: {{ ucfirst($snapshot['order']['payment_method']) }} — paid.
    </div>

    @if ($snapshot['footer_note'] !== '')
        <div class="tax-note">{{ $snapshot['footer_note'] }}</div>
    @endif
</div>

<div class="footer">
    {{ $snapshot['seller']['company_name'] }} · {{ $snapshot['seller']['address_line1'] }} · {{ $snapshot['seller']['postal_code'] }} {{ $snapshot['seller']['city'] }} · Tax ID: {{ $snapshot['seller']['tax_identifier'] }}
</div>
</body>
</html>
