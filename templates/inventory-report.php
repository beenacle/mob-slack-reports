<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; padding: 20px 24px; }
    .header { display: table; width: 100%; margin-bottom: 16px; border-bottom: 2px solid #2563eb; padding-bottom: 12px; }
    .header-left { display: table-cell; vertical-align: middle; width: 60%; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 40%; }
    .header img { max-height: 40px; max-width: 140px; }
    .header h1 { font-size: 16px; color: #1e293b; margin: 4px 0 2px; }
    .header .subtitle { font-size: 9px; color: #64748b; }
    .meta { font-size: 8px; color: #64748b; margin-bottom: 10px; }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th { background: #1e293b; color: #fff; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; padding: 6px 5px; text-align: left; }
    td { padding: 5px; font-size: 8.5px; border-bottom: 1px solid #e2e8f0; }
    tr:nth-child(even) td { background: #f8fafc; }
    tr:last-child td { border-bottom: 2px solid #1e293b; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .nowrap { white-space: nowrap; }

    .zone-danger { color: #dc2626; font-weight: 700; }
    .zone-safe   { color: #ca8a04; font-weight: 600; }
    .zone-ideal  { color: #16a34a; font-weight: 600; }

    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 3px; vertical-align: middle; }
    .dot-danger { background: #dc2626; }
    .dot-safe   { background: #ca8a04; }
    .dot-ideal  { background: #16a34a; }

    .footer { margin-top: 12px; font-size: 7px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <?php if (!empty($site_logo_url)): ?>
            <img src="<?php echo esc_url($site_logo_url); ?>" alt="Logo">
        <?php endif; ?>
        <h1>Inventory Status Report</h1>
        <div class="subtitle"><?php echo esc_html($site_name); ?></div>
    </div>
    <div class="header-right">
        <div class="subtitle">Generated: <?php echo esc_html($generated_at); ?></div>
        <div class="subtitle">Sales Window: <?php echo (int) $sales_window; ?> days | Growth Factor: <?php echo esc_html($growth_pct); ?>%</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:28%">Product Name</th>
            <th class="text-right" style="width:9%">In Stock</th>
            <th class="text-right" style="width:11%">Avg Weekly Orders</th>
            <th class="text-center" style="width:10%">Order Status</th>
            <th class="text-right" style="width:13%">Projected Weekly Sales</th>
            <th class="text-right" style="width:12%">Weeks of Supply</th>
            <th class="text-center" style="width:17%">Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $zone_key = $r['zone_key'];
            $zone_class = 'zone-' . $zone_key;
            $dot_class  = 'dot-' . $zone_key;
        ?>
        <tr>
            <td><?php echo esc_html($r['name']); ?></td>
            <td class="text-right"><?php echo ($r['stock'] === null) ? '<span style="color:#94a3b8">N/A</span>' : (int) $r['stock']; ?></td>
            <td class="text-right"><?php echo number_format($r['weekly_orders'], 2); ?></td>
            <td class="text-center"><?php echo esc_html($r['order_status']); ?></td>
            <td class="text-right"><?php echo number_format($r['projected'], 2); ?></td>
            <td class="text-right"><?php echo ($r['weeks_supply'] === null) ? '<span style="color:#94a3b8">N/A</span>' : number_format($r['weeks_supply'], 1); ?></td>
            <td class="text-center <?php echo $zone_class; ?>"><span class="dot <?php echo $dot_class; ?>"></span><?php echo esc_html($r['status']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="meta" style="margin-top:8px;">
    Total Products: <?php echo count($rows); ?> &nbsp;|&nbsp;
    Danger Zone: <?php echo count(array_filter($rows, fn($r) => strpos($r['status'], 'Danger') !== false)); ?> &nbsp;|&nbsp;
    Safe Zone: <?php echo count(array_filter($rows, fn($r) => strpos($r['status'], 'Safe') !== false)); ?> &nbsp;|&nbsp;
    Ideal Zone: <?php echo count(array_filter($rows, fn($r) => strpos($r['status'], 'Ideal') !== false)); ?>
</div>

<div class="footer">
    <?php echo esc_html($site_name); ?> &mdash; Inventory Status Report &mdash; <?php echo esc_html($generated_at); ?>
</div>

</body>
</html>
