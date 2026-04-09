<?php
defined('ABSPATH') || exit;

class MOB_Profitability_Report {

    /**
     * Generate the profitability report PDF and return its temp file path.
     *
     * @return array{ok: bool, file?: string, filename?: string, error?: string}
     */
    public static function generate(): array {
        if (!function_exists('wc_get_products')) {
            return ['ok' => false, 'error' => 'woocommerce_not_loaded'];
        }

        $tz     = MOB_Reports_Settings::get_timezone();
        $period = MOB_Reports_Settings::get_profit_period();

        [$date_after, $date_before, $period_label, $date_range] = self::resolve_period($period, $tz);

        $rows   = self::build_rows($date_after, $date_before, $tz);
        $totals = self::compute_totals($rows);

        $pdf_path = MOB_PDF_Generator::generate('profitability-report.php', [
            'rows'         => $rows,
            'totals'       => $totals,
            'period_label' => $period_label,
            'date_range'   => $date_range,
        ]);

        if (!$pdf_path) {
            return ['ok' => false, 'error' => 'pdf_generation_failed'];
        }

        $filename = 'Profitability_Report_' . wp_date('Y-m-d', null, new DateTimeZone($tz)) . '.pdf';

        return ['ok' => true, 'file' => $pdf_path, 'filename' => $filename];
    }

    /**
     * @return array{string, string, string, string} [date_after, date_before, label, range_display]
     */
    private static function resolve_period(string $period, string $tz): array {
        $timezone = new DateTimeZone($tz);
        $now      = new DateTime('now', $timezone);

        switch ($period) {
            case 'last_7_days':
                $start = (clone $now)->modify('-7 days')->setTime(0, 0, 0);
                $end   = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                $label = 'Last 7 Days';
                break;

            case 'last_30_days':
                $start = (clone $now)->modify('-30 days')->setTime(0, 0, 0);
                $end   = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                $label = 'Last 30 Days';
                break;

            case 'month_to_date':
                $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                $end   = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                if ($start > $end) {
                    $end = clone $start;
                    $end->setTime(23, 59, 59);
                }
                $label = 'Month to Date';
                break;

            case 'previous_day':
            default:
                $start = (clone $now)->modify('-1 day')->setTime(0, 0, 0);
                $end   = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                $label = 'Previous Day';
                break;
        }

        $range_display = $start->format('M j, Y');
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            $range_display .= ' – ' . $end->format('M j, Y');
        }

        return [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $label,
            $range_display,
        ];
    }

    private static function build_rows(string $date_after, string $date_before, string $tz): array {
        $order_ids = wc_get_orders([
            'limit'        => -1,
            'status'       => ['wc-completed', 'wc-processing'],
            'date_after'   => $date_after,
            'date_before'  => $date_before,
            'return'       => 'ids',
        ]);

        // Aggregate: product_id => [qty, revenue, cogs_total, unit_price, cogs_per_unit, name, date]
        $aggregated = [];

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;

            $order_date = $order->get_date_created();
            $date_str   = $order_date ? $order_date->date('M j') : '';

            foreach ($order->get_items() as $item) {
                $pid     = (int) $item->get_product_id();
                $product = $item->get_product();
                if (!$pid || !$product) continue;

                $qty        = (float) $item->get_quantity();
                $line_total = (float) $item->get_total();
                $unit_price = $qty > 0 ? $line_total / $qty : (float) $product->get_price();

                $cogs_per_unit = (float) $product->get_cogs_total_value();

                if (!isset($aggregated[$pid])) {
                    $aggregated[$pid] = [
                        'product'       => $product->get_name(),
                        'unit_price'    => $unit_price,
                        'cogs_per_unit' => $cogs_per_unit,
                        'qty_sold'      => 0,
                        'gross_sales'   => 0.0,
                        'date'          => $date_str,
                    ];
                }

                $aggregated[$pid]['qty_sold']    += $qty;
                $aggregated[$pid]['gross_sales'] += $line_total;
            }
        }

        $rows = [];
        foreach ($aggregated as $pid => $data) {
            $total_cogs = $data['cogs_per_unit'] * $data['qty_sold'];
            $net_sales  = $data['gross_sales'] - $total_cogs;

            $rows[] = [
                'date'          => $data['date'],
                'product'       => $data['product'],
                'unit_price'    => $data['unit_price'],
                'qty_sold'      => $data['qty_sold'],
                'cogs_per_unit' => $data['cogs_per_unit'],
                'gross_sales'   => $data['gross_sales'],
                'net_sales'     => $net_sales,
            ];
        }

        usort($rows, fn($a, $b) => $b['gross_sales'] <=> $a['gross_sales']);

        return $rows;
    }

    private static function compute_totals(array $rows): array {
        $totals = ['qty_sold' => 0, 'total_cogs' => 0.0, 'gross_sales' => 0.0, 'net_sales' => 0.0];

        foreach ($rows as $r) {
            $totals['qty_sold']    += $r['qty_sold'];
            $totals['total_cogs']  += $r['cogs_per_unit'] * $r['qty_sold'];
            $totals['gross_sales'] += $r['gross_sales'];
            $totals['net_sales']   += $r['net_sales'];
        }

        return $totals;
    }
}
