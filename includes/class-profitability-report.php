<?php
defined('ABSPATH') || exit;

class MOB_Profitability_Report {

    private const BATCH_SIZE = 200;

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

        // Convert to UTC for WooCommerce query — wc_get_orders compares against
        // date_created_gmt, and strtotime() runs in PHP's default TZ (UTC in WP).
        $start->setTimezone(new DateTimeZone('UTC'));
        $end->setTimezone(new DateTimeZone('UTC'));

        return [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $label,
            $range_display,
        ];
    }

    private static function build_rows(string $date_after, string $date_before, string $tz): array {
        // Aggregate per product: name, qty, gross revenue, COGS, last-sold date.
        $aggregated = [];

        $page = 1;
        do {
            $orders = wc_get_orders([
                'limit'        => self::BATCH_SIZE,
                'page'         => $page,
                'status'       => ['wc-completed', 'wc-processing'],
                'date_after'   => $date_after,
                'date_before'  => $date_before,
                'orderby'      => 'ID',
                'order'        => 'ASC',
            ]);

            foreach ($orders as $order) {
                if (!$order) continue;

                $order_date = $order->get_date_created();
                if ($order_date) {
                    $local_date = clone $order_date;
                    $local_date->setTimezone(new DateTimeZone($tz));
                    $date_str = $local_date->format('M j');
                    $date_ts  = $order_date->getTimestamp();
                } else {
                    $date_str = '';
                    $date_ts  = 0;
                }

                foreach ($order->get_items() as $item) {
                    $pid     = (int) $item->get_product_id();
                    $product = $item->get_product();
                    if (!$pid || !$product) continue;

                    $qty        = (float) $item->get_quantity();
                    $line_total = (float) $item->get_total();
                    $line_cogs  = self::line_cogs($item, $product, $qty);

                    if (!isset($aggregated[$pid])) {
                        $aggregated[$pid] = [
                            'product'     => $product->get_name(),
                            'qty_sold'    => 0.0,
                            'gross_sales' => 0.0,
                            'cogs_total'  => 0.0,
                            'date'        => $date_str,
                            'date_ts'     => $date_ts,
                        ];
                    } elseif ($date_ts > $aggregated[$pid]['date_ts']) {
                        // Track the most recent sale date for the product.
                        $aggregated[$pid]['date']    = $date_str;
                        $aggregated[$pid]['date_ts'] = $date_ts;
                    }

                    $aggregated[$pid]['qty_sold']    += $qty;
                    $aggregated[$pid]['gross_sales'] += $line_total;
                    $aggregated[$pid]['cogs_total']  += $line_cogs;
                }
            }

            $page++;
        } while (count($orders) === self::BATCH_SIZE);

        $rows = [];
        foreach ($aggregated as $data) {
            $qty = $data['qty_sold'];

            $rows[] = [
                'date'        => $data['date'],
                'product'     => $data['product'],
                'unit_price'  => $qty > 0 ? $data['gross_sales'] / $qty : 0.0,
                'qty_sold'    => $qty,
                'cogs_total'  => $data['cogs_total'],
                'gross_sales' => $data['gross_sales'],
                'net_sales'   => $data['gross_sales'] - $data['cogs_total'],
            ];
        }

        usort($rows, fn($a, $b) => $b['gross_sales'] <=> $a['gross_sales']);

        return $rows;
    }

    /**
     * Resolve the cost of goods for a single order line.
     *
     * Prefers the COGS captured on the order item at the time of sale, which
     * stays accurate even after the product's cost is later changed. Falls
     * back to the product's current per-unit COGS × quantity (for orders that
     * predate the COGS feature), and to 0.0 when COGS is unavailable — so the
     * report never fatals on a WooCommerce version without COGS support.
     */
    private static function line_cogs(\WC_Order_Item $item, \WC_Product $product, float $qty): float {
        if (method_exists($item, 'get_cogs_value')) {
            $recorded = (float) $item->get_cogs_value();
            if ($recorded > 0) {
                return $recorded;
            }
        }
        if (method_exists($product, 'get_cogs_total_value')) {
            return (float) $product->get_cogs_total_value() * $qty;
        }
        if (method_exists($product, 'get_cogs_value')) {
            return (float) $product->get_cogs_value() * $qty;
        }
        return 0.0;
    }

    private static function compute_totals(array $rows): array {
        $totals = ['qty_sold' => 0, 'total_cogs' => 0.0, 'gross_sales' => 0.0, 'net_sales' => 0.0];

        foreach ($rows as $r) {
            $totals['qty_sold']    += $r['qty_sold'];
            $totals['total_cogs']  += $r['cogs_total'];
            $totals['gross_sales'] += $r['gross_sales'];
            $totals['net_sales']   += $r['net_sales'];
        }

        return $totals;
    }
}
