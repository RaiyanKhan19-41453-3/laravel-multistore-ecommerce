<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Packing Slip {{ $order->order_number }}</title>
<style>
  body{font-family:system-ui,Arial; margin:0; padding:24px; font-size:13px}
  .sheet{max-width:800px; margin:0 auto; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden}
  .header{padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between}
  table{width:100%; border-collapse:collapse}
  th{font-size:11px; text-transform:uppercase; color:#6b7280; text-align:left; padding:8px 24px; background:#f9fafb; border-bottom:1px solid #e5e7eb}
  td{padding:10px 24px; border-bottom:1px solid #f3f4f6}
  .right{text-align:right}
  .box{padding:16px 24px}
  @media print{ .no-print{display:none} body{padding:0} .sheet{border:none} }
</style>
</head>
<body>
<div class="sheet">
  <div class="header">
    <div><div style="font-weight:800">{{ $store['name'] }}</div><div style="font-size:12px; color:#6b7280">Packing Slip · {{ $order->order_number }} · {{ strtoupper($order->status) }}</div></div>
    <div class="right" style="font-size:12px; color:#6b7280">{{ $order->created_at->format('Y-m-d') }}<br>{{ $order->shipping_method_name ?? '—' }}</div>
  </div>
  <div class="box grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
    <div><div style="font-size:11px; letter-spacing:.08em; color:#6b7280">SHIP TO</div><div style="font-weight:600">{{ $order->shipping_name }}</div><div>{{ $order->shipping_phone }}</div><div>{{ $order->shipping_address }}, {{ $order->shipping_city }}</div><div>{{ $order->shipping_country }}</div></div>
    <div class="right"><div style="font-size:11px; letter-spacing:.08em; color:#6b7280">ORDER</div><div>{{ $order->order_number }}</div><div>{{ $order->items->sum('quantity') }} items</div><div>{{ number_format($order->total,2) }}</div></div>
  </div>
  <table>
    <thead><tr><th>SKU</th><th>Item</th><th class="right">Qty</th></tr></thead>
    <tbody>
      @foreach($order->items as $it)
        <tr><td style="font-family:monospace">{{ $it->sku }}</td><td>{{ $it->name }}</td><td class="right" style="font-weight:700">{{ $it->quantity }}</td></tr>
      @endforeach
    </tbody>
  </table>
  <div class="box" style="font-size:12px; color:#6b7280">Check items against this slip before sealing. Include invoice in parcel.</div>
</div>
<div class="no-print" style="max-width:800px; margin:12px auto; text-align:right"><a href="javascript:window.print()" style="border:1px solid #e5e7eb; padding:8px 12px; border-radius:10px; text-decoration:none; color:#111">Print</a></div>
</body>
</html>
