<?php
defined('ABSPATH') || exit;

class MOB_Inventory_Report {

    private const WEEKLY_GROWTH  = 0.10;
    private const DANGER_WEEKS   = 10.0;
    private const SAFE_WEEKS     = 15.0;

    /**
     * Generate the inventory report PDF and return its temp file path.
     *
     * @return array{ok: bool, file?: string, filename?: string, error?: string}
     */
    public static function generate(): array {
        if (!function_exists('wc_get_products')) {
            return ['ok' => false, 'error' => 'woocommerce_not_loaded'];
        }

        $tz           = MOB_Reports_Settings::get_timezone();
        $sales_window = MOB_Reports_Settings::get_inventory_sales_window();

        $rows = self::build_rows($sales_window, $tz);

        $pdf_path = MOB_PDF_Generator::generate('inventory-report.php', [
            'rows'       => $rows,
            'sales_window' => $sales_window,
            'growth_pct' => self::WEEKLY_GROWTH * 100,
        ]);

        if (!$pdf_path) {
            return ['ok' => false, 'error' => 'pdf_generation_failed'];
        }

        $filename = 'Inventory_Report_' . wp_date('Y-m-d', null, new DateTimeZone($tz)) . '.pdf';

        return ['ok' => true, 'file' => $pdf_path, 'filename' => $filename];
    }

    private static function build_rows(int $sales_window, string $tz): array {
        $products  = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $sales_map = self::get_sales_qty_map($sales_window, $tz);
        $weeks_div = max(1, $sales_window / 7);

        $rows = [];
        foreach ($products as $p) {
            $pid   = $p->get_id();
            $name  = $p->get_name();
            $stock = self::stock_qty_or_null($p);

            $sold_in_window = (float) ($sales_map[$pid] ?? 0.0);
            $weekly_orders  = round($sold_in_window / $weeks_div, 2);
            $projected      = round($weekly_orders * (1 + self::WEEKLY_GROWTH), 2);

            $weeks_supply = null;
            if ($stock !== null) {
                $weeks_supply = ($projected > 0) ? round($stock / $projected, 1) : 0.0;
            }

            [$zone_key, $zone] = self::zone_from_weeks($weeks_supply);

            $rows[] = [
                'name'          => $name,
                'stock'         => $stock,
                'weekly_orders' => $weekly_orders,
                'order_status'  => ($weekly_orders > 0) ? 'Active' : 'No Orders',
                'projected'     => $projected,
                'weeks_supply'  => $weeks_supply,
                'status'        => $zone,
                'zone_key'      => $zone_key,
                'in_sort'       => ($stock !== null && $stock > 0) ? 1 : 0,
                'qty_sort'      => ($stock === null) ? -1 : $stock,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a['in_sort'] !== $b['in_sort']) return $b['in_sort'] <=> $a['in_sort'];
            if ($a['qty_sort'] !== $b['qty_sort']) return $b['qty_sort'] <=> $a['qty_sort'];
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    private static function stock_qty_or_null(\WC_Product $p): ?int {
        if (!$p->managing_stock()) return null;

        $qty = $p->get_stock_quantity();
        if ($qty === null || $qty === '') {
            $meta = get_post_meta($p->get_id(), '_stock', true);
            $qty  = ($meta === '' || $meta === null) ? 0 : (float) $meta;
        }

        $qty = (float) $qty;
        if ($qty < 0) $qty = 0;
        return (int) round($qty);
    }

    private static function zone_from_weeks(?float $weeks): array {
        if ($weeks === null)              return ['danger', 'Danger Zone'];
        if ($weeks <= self::DANGER_WEEKS) return ['danger', 'Danger Zone'];
        if ($weeks <= self::SAFE_WEEKS)   return ['safe', 'Safe Zone'];
        return ['ideal', 'Ideal Zone'];
    }

    private static function get_sales_qty_map(int $days, string $tz): array {
        $after = (new DateTime('now', new DateTimeZone($tz)))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        $order_ids = wc_get_orders([
            'limit'      => -1,
            'status'     => ['wc-completed', 'wc-processing'],
            'date_after' => $after,
            'return'     => 'ids',
        ]);

        $map = [];
        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            foreach ($order->get_items() as $item) {
                $pid = (int) $item->get_product_id();
                if (!$pid) continue;
                $map[$pid] = ($map[$pid] ?? 0) + (float) $item->get_quantity();
            }
        }

        return $map;
    }
}
