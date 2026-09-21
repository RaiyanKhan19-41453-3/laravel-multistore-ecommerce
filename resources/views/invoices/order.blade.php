<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->order_number }} - {{ $store['name'] }}</title>
<style>
  *{box-sizing:border-box} body{font-family: ui-sans-serif,system-ui, Arial; color:#111; margin:0; padding:24px; font-size:13px}
  .sheet{max-width:800px; margin:0 auto; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden}
  .header{padding:20px 24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center}
  .brand{font-size:18px; font-weight:800}
  .meta{color:#6b7280; font-size:12px}
  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:16px}
  .box{padding:16px 24px; border-bottom:1px solid #e5e7eb}
  .label{font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#6b7280; margin-bottom:6px}
  table{width:100%; border-collapse:collapse}
  th{font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#6b7280; text-align:left; padding:8px 24px; border-bottom:1px solid #e5e7eb; background:#f9fafb}
  td{padding:10px 24px; border-bottom:1px solid #f3f4f6}
  .right{text-align:right}
  .totals{padding:16px 24px}
  .totals .row{display:flex; justify-content:space-between; padding:6px 0}
  .totals .grand{font-weight:800; font-size:16px; border-top:2px solid #111; margin-top:8px; padding-top:12px}
  .qr{display:flex; gap:16px; align-items:center; padding:16px 24px; background:#f9fafb; border-top:1px solid #e5e7eb}
  .foot{padding:12px 24px; text-align:center; font-size:11px; color:#6b7280}
  @media print{ body{padding:0} .sheet{border:none} .no-print{display:none} }
</style>
</head>
<body>
<div class="sheet">
  <div class="header">
    <div>
      <div class="brand">{{ $store['name'] }}</div>
      <div class="meta">Invoice {{ $order->order_number }} · {{ $order->created_at->format('Y-m-d H:i') }} · {{ strtoupper($order->status) }}</div>
    </div>
    <div class="right">
      <div class="label">Amount due</div>
      <div style="font-size:20px; font-weight:800">{{ $store['currency'] }} {{ number_format($totals['grand'], 2) }}</div>
      <div class="meta">{{ $isVat ? 'VAT included' : 'Tax:' }} {{ number_format($totals['tax'], 2) }}</div>
    </div>
  </div>

  <div class="box grid2">
    <div>
      <div class="label">Bill / Ship to</div>
      <div style="font-weight:600">{{ $order->shipping_name }}</div>
      <div>{{ $order->shipping_phone }}</div>
      <div>{{ $order->shipping_address }}, {{ $order->shipping_city }} {{ $order->shipping_state ? ', '.$order->shipping_state : '' }}</div>
      <div>{{ $order->shipping_country }} {{ $order->shipping_postal_code }}</div>
      @if($order->user)
        <div class="meta">{{ $order->user->email }}</div>
      @elseif($order->guest_email)
        <div class="meta">{{ $order->guest_email }}</div>
      @endif
    </div>
    <div>
      <div class="label">Payment & Shipping</div>
      <div>Method: {{ $order->payments->first()?->method ?? '-' }}</div>
      <div>Shipping: {{ $order->shipping_method_name ?? '-' }} @if($order->shipping_estimated_days) ({{ $order->shipping_estimated_days }} days) @endif</div>
      <div>Ship cost: {{ number_format($totals['shipping'], 2) }}</div>
      @if($order->coupon_code)<div>Coupon: {{ $order->coupon_code }} (-{{ number_format($totals['discount'], 2) }})</div>@endif
      @if($order->notes)<div class="meta">Note: {{ $order->notes }}</div>@endif
    </div>
  </div>

  <table>
    <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
    <tbody>
    @foreach($lines as $line)
      <tr>
        <td><div style="font-weight:600">{{ $line->name }}</div><div class="meta">{{ $line->sku }}</div></td>
        <td class="right">{{ $line->quantity }}</td>
        <td class="right">{{ number_format($line->unit_price, 2) }}</td>
        <td class="right" style="font-weight:600">{{ number_format($line->total, 2) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span>Subtotal</span><span>{{ number_format($totals['subtotal'], 2) }}</span></div>
    @if($totals['discount'] > 0)<div class="row" style="color:#059669"><span>Discount</span><span>-{{ number_format($totals['discount'], 2) }}</span></div>@endif
    <div class="row"><span>Shipping</span><span>{{ number_format($totals['shipping'], 2) }}</span></div>
    <div class="row"><span>{{ $isVat ? 'VAT' : 'Tax' }}</span><span>{{ number_format($totals['tax'], 2) }}</span></div>
    <div class="row grand"><span>Grand total ({{ $store['currency'] }})</span><span>{{ number_format($totals['grand'], 2) }}</span></div>
  </div>

  @if($qrSvg)
  <div class="qr">
    <div style="width:180px; height:180px; background:#fff; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; padding:8px">{!! $qrSvg !!}</div>
    <div>
      <div class="label">ZATCA QR: TLV Base64</div>
      <div style="font-family:monospace; font-size:10px; word-break:break-all; max-width:520px">{{ $qrPayload }}</div>
      <div class="meta">Scan to verify seller, VAT, amount per ZATCA Phase 1.</div>
    </div>
  </div>
  @endif

  <div class="foot">Thank you for shopping with {{ $store['name'] }}. For support, reply to your order confirmation email with {{ $order->order_number }}.</div>
</div>
<div class="no-print" style="max-width:800px; margin:12px auto; display:flex; gap:8px; justify-content:flex-end">
  <a href="javascript:window.print()" style="border:1px solid #e5e7eb; padding:8px 12px; border-radius:10px; text-decoration:none; color:#111">Print</a>
</div>
</body>
</html>
