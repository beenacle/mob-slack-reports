<?php
defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class MOB_PDF_Generator {

    /**
     * Render an HTML template to a PDF file and return the temp file path.
     *
     * @param string $template  Template filename inside /templates/ (e.g. 'inventory-report.php').
     * @param array  $data      Variables extracted into the template scope.
     * @param string $orientation 'portrait' or 'landscape'.
     * @return string|null  Path to the generated temp PDF, or null on failure.
     */
    public static function generate(string $template, array $data, string $orientation = 'landscape'): ?string {
        $template_path = MOB_REPORTS_PATH . 'templates/' . $template;
        if (!file_exists($template_path)) {
            error_log('[MOB Reports] Template not found: ' . $template_path);
            return null;
        }

        $data['site_name'] = get_bloginfo('name');
        $data['site_logo_url'] = self::get_site_logo_url();
        $data['generated_at'] = wp_date('F j, Y \a\t g:i A', null, new DateTimeZone(MOB_Reports_Settings::get_timezone()));

        ob_start();
        extract($data, EXTR_SKIP);
        include $template_path;
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $tmp = wp_tempnam('mob-report-') . '.pdf';
        $written = file_put_contents($tmp, $dompdf->output());

        if ($written === false) {
            error_log('[MOB Reports] Failed to write PDF to temp file.');
            return null;
        }

        return $tmp;
    }

    private static function get_site_logo_url(): string {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $url = wp_get_attachment_image_url($custom_logo_id, 'medium');
            if ($url) {
                return $url;
            }
        }

        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $url = wp_get_attachment_image_url($site_icon_id, 'full');
            if ($url) {
                return $url;
            }
        }

        return '';
    }
}
