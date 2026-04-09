<?php
defined('ABSPATH') || exit;
$fmt = function($v) { return '$' . number_format((float) $v, 2); };
?>
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

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .nowrap { white-space: nowrap; }

    .totals td { background: #1e293b !important; color: #fff; font-weight: 700; font-size: 9px; border: none; }

    .positive { color: #16a34a; }
    .negative { color: #dc2626; }

    .footer { margin-top: 12px; font-size: 7px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <?php if (!empty($site_logo_url)): ?>
            <img src="<?php echo esc_url($site_logo_url); ?>" alt="Logo">
        <?php endif; ?>
        <h1>Profitability Report</h1>
        <div class="subtitle"><?php echo esc_html($site_name); ?></div>
    </div>
    <div class="header-right">
        <div class="subtitle">Generated: <?php echo esc_html($generated_at); ?></div>
        <div class="subtitle">Period: <?php echo esc_html($period_label); ?></div>
        <div class="subtitle"><?php echo esc_html($date_range); ?></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:8%">Date</th>
            <th style="width:28%">Product</th>
            <th class="text-right" style="width:11%">Per Unit Sale</th>
            <th class="text-right" style="width:10%">Qty Sold</th>
            <th class="text-right" style="width:12%">Cost of Goods</th>
            <th class="text-right" style="width:14%">Gross Sales</th>
            <th class="text-right" style="width:14%">Net Sales</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="nowrap"><?php echo esc_html($r['date']); ?></td>
            <td><?php echo esc_html($r['product']); ?></td>
            <td class="text-right"><?php echo $fmt($r['unit_price']); ?></td>
            <td class="text-right"><?php echo (int) $r['qty_sold']; ?></td>
            <td class="text-right"><?php echo $fmt($r['cogs_per_unit']); ?></td>
            <td class="text-right"><?php echo $fmt($r['gross_sales']); ?></td>
            <td class="text-right <?php echo $r['net_sales'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $fmt($r['net_sales']); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <tr class="totals">
        <td colspan="3">Totals</td>
        <td class="text-right"><?php echo (int) $totals['qty_sold']; ?></td>
        <td class="text-right"><?php echo $fmt($totals['total_cogs']); ?></td>
        <td class="text-right"><?php echo $fmt($totals['gross_sales']); ?></td>
        <td class="text-right"><?php echo $fmt($totals['net_sales']); ?></td>
    </tr>
    </tbody>
</table>

<div class="meta" style="margin-top:8px;">
    Products Sold: <?php echo count($rows); ?> &nbsp;|&nbsp;
    Total Units: <?php echo (int) $totals['qty_sold']; ?> &nbsp;|&nbsp;
    Gross Margin: <?php echo $totals['gross_sales'] > 0 ? number_format(($totals['net_sales'] / $totals['gross_sales']) * 100, 1) : '0.0'; ?>%
</div>

<div class="footer">
    <?php echo esc_html($site_name); ?> &mdash; Profitability Report &mdash; <?php echo esc_html($generated_at); ?>
</div>

</body>
</html>
