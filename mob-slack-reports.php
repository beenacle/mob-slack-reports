<?php
/**
 * Plugin Name: MOB Slack Reports
 * Description: Sends daily Inventory and Profitability report PDFs to configurable Slack channels.
 * Version:     1.1.0
 * Author:      Beenacle
 * Author URI:  https://beenacle.com
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 * Text Domain: mob-slack-reports
 */

defined('ABSPATH') || exit;

define('MOB_REPORTS_VERSION', '1.1.0');
define('MOB_REPORTS_FILE', __FILE__);
define('MOB_REPORTS_PATH', plugin_dir_path(__FILE__));
define('MOB_REPORTS_URL', plugin_dir_url(__FILE__));

require_once MOB_REPORTS_PATH . 'vendor/autoload.php';
require_once MOB_REPORTS_PATH . 'vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php';

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$mobReportsUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/beenacle/mob-slack-reports/',
    __FILE__,
    'mob-slack-reports'
);
$mobReportsUpdateChecker->setBranch('main');
$mobReportsUpdateChecker->getVcsApi()->enableReleaseAssets();
require_once MOB_REPORTS_PATH . 'includes/class-settings.php';
require_once MOB_REPORTS_PATH . 'includes/class-pdf-generator.php';
require_once MOB_REPORTS_PATH . 'includes/class-slack-sender.php';
require_once MOB_REPORTS_PATH . 'includes/class-inventory-report.php';
require_once MOB_REPORTS_PATH . 'includes/class-profitability-report.php';
require_once MOB_REPORTS_PATH . 'includes/class-scheduler.php';

final class MOB_Slack_Reports {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
        add_action('woocommerce_init', [$this, 'init']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function declare_hpos_compatibility(): void {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', MOB_REPORTS_FILE, true);
        }
    }

    public function init(): void {
        MOB_Reports_Settings::instance();
        MOB_Reports_Scheduler::instance();
    }

    public function admin_notices(): void {
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p><strong>MOB Slack Reports</strong> requires WooCommerce to be installed and active.</p></div>';
            return;
        }

        if (!$this->is_cogs_enabled()) {
            echo '<div class="notice notice-warning"><p><strong>MOB Slack Reports:</strong> The Profitability Report requires WooCommerce Cost of Goods Sold (COGS) to be enabled. Go to <strong>WooCommerce &gt; Settings &gt; Advanced &gt; Features</strong> to enable it.</p></div>';
        }
    }

    public function is_cogs_enabled(): bool {
        if (!function_exists('wc_get_container')) {
            return false;
        }
        try {
            $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController::class);
            return $controller->feature_is_enabled();
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function activate(): void {
        if (!wp_next_scheduled('mob_inventory_report_event')) {
            MOB_Reports_Scheduler::schedule_events();
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('mob_inventory_report_event');
        wp_clear_scheduled_hook('mob_profit_report_event');
    }
}

register_activation_hook(MOB_REPORTS_FILE, ['MOB_Slack_Reports', 'activate']);
register_deactivation_hook(MOB_REPORTS_FILE, ['MOB_Slack_Reports', 'deactivate']);

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce') || defined('WC_ABSPATH')) {
        MOB_Slack_Reports::instance();
    }
});
