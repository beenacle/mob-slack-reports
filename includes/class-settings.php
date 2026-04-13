<?php
defined('ABSPATH') || exit;

class MOB_Reports_Settings {

    private static ?self $instance = null;
    const OPTION_PREFIX = 'mob_reports_';

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_slack_reports', [$this, 'render_settings']);
        add_action('woocommerce_update_options_slack_reports', [$this, 'save_settings']);
        add_action('woocommerce_admin_field_mob_send_now_buttons', [$this, 'render_send_now_buttons']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_mob_send_report_now', [$this, 'ajax_send_now']);
    }

    public function add_settings_tab(array $tabs): array {
        $tabs['slack_reports'] = __('Slack Reports', 'mob-slack-reports');
        return $tabs;
    }

    public function render_settings(): void {
        woocommerce_admin_fields($this->get_settings());
    }

    public function save_settings(): void {
        woocommerce_update_options($this->get_settings());

        MOB_Reports_Scheduler::reschedule_events();
    }

    private function get_settings(): array {
        return [
            'section_slack' => [
                'name' => __('Slack Configuration', 'mob-slack-reports'),
                'type' => 'title',
                'desc' => __('Configure the Slack bot token and channel IDs for report delivery.', 'mob-slack-reports'),
                'id'   => self::OPTION_PREFIX . 'slack_section',
            ],
            'bot_token' => [
                'name'     => __('Slack Bot Token', 'mob-slack-reports'),
                'type'     => 'password',
                'desc'     => __('The xoxb-* bot token with <code>files:write</code> and <code>chat:write</code> scopes.', 'mob-slack-reports'),
                'id'       => self::OPTION_PREFIX . 'bot_token',
                'desc_tip' => true,
                'default'  => '',
            ],
            'inventory_channel' => [
                'name'    => __('Inventory Report Channel ID', 'mob-slack-reports'),
                'type'    => 'text',
                'desc'    => __('Slack channel ID (e.g. C08RK3WSNLX) for inventory reports.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'inventory_channel',
                'default' => '',
            ],
            'profit_channel' => [
                'name'    => __('Profitability Report Channel ID', 'mob-slack-reports'),
                'type'    => 'text',
                'desc'    => __('Slack channel ID for profitability reports.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'profit_channel',
                'default' => '',
            ],
            'section_slack_end' => ['type' => 'sectionend', 'id' => self::OPTION_PREFIX . 'slack_section'],

            'section_schedule' => [
                'name' => __('Schedule & Reports', 'mob-slack-reports'),
                'type' => 'title',
                'desc' => __('Configure delivery time and report options.', 'mob-slack-reports'),
                'id'   => self::OPTION_PREFIX . 'schedule_section',
            ],
            'delivery_time' => [
                'name'    => __('Delivery Time', 'mob-slack-reports'),
                'type'    => 'text',
                'desc'    => __('24-hour format, e.g. 09:00. Reports are sent daily at this time.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'delivery_time',
                'default' => '08:00',
                'css'     => 'width:80px;',
            ],
            'timezone' => [
                'name'    => __('Timezone', 'mob-slack-reports'),
                'type'    => 'select',
                'desc'    => __('Timezone for the delivery schedule.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'timezone',
                'default' => self::get_site_timezone(),
                'options' => self::get_timezone_options(),
            ],
            'inventory_enabled' => [
                'name'    => __('Enable Inventory Report', 'mob-slack-reports'),
                'type'    => 'checkbox',
                'desc'    => __('Send daily inventory status report to Slack.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'inventory_enabled',
                'default' => 'yes',
            ],
            'profit_enabled' => [
                'name'    => __('Enable Profitability Report', 'mob-slack-reports'),
                'type'    => 'checkbox',
                'desc'    => __('Send daily profitability report to Slack.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'profit_enabled',
                'default' => 'yes',
            ],
            'profit_period' => [
                'name'    => __('Profitability Report Period', 'mob-slack-reports'),
                'type'    => 'select',
                'desc'    => __('The time window for order data in the profitability report.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'profit_period',
                'default' => 'previous_day',
                'options' => [
                    'previous_day' => __('Previous Day', 'mob-slack-reports'),
                    'last_7_days'  => __('Last 7 Days', 'mob-slack-reports'),
                    'last_30_days' => __('Last 30 Days', 'mob-slack-reports'),
                    'month_to_date' => __('Month to Date', 'mob-slack-reports'),
                ],
            ],
            'inventory_sales_window' => [
                'name'    => __('Inventory Sales Window (days)', 'mob-slack-reports'),
                'type'    => 'number',
                'desc'    => __('Number of days used to calculate average weekly sales for inventory projections.', 'mob-slack-reports'),
                'id'      => self::OPTION_PREFIX . 'inventory_sales_window',
                'default' => '28',
                'css'     => 'width:80px;',
                'custom_attributes' => ['min' => '7', 'step' => '1'],
            ],
            'section_schedule_end' => ['type' => 'sectionend', 'id' => self::OPTION_PREFIX . 'schedule_section'],

            'section_actions' => [
                'name' => __('Manual Actions', 'mob-slack-reports'),
                'type' => 'title',
                'desc' => __('Manually trigger reports for testing.', 'mob-slack-reports'),
                'id'   => self::OPTION_PREFIX . 'actions_section',
            ],
            'send_now_buttons' => [
                'type' => 'mob_send_now_buttons',
                'id'   => self::OPTION_PREFIX . 'send_now_buttons',
            ],
            'section_actions_end' => ['type' => 'sectionend', 'id' => self::OPTION_PREFIX . 'actions_section'],
        ];
    }

    public function render_send_now_buttons(): void {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e('Send Reports Now', 'mob-slack-reports'); ?></th>
            <td class="forminp">
                <button type="button" class="button mob-send-now" data-report="inventory">
                    <?php esc_html_e('Send Inventory Report', 'mob-slack-reports'); ?>
                </button>
                <button type="button" class="button mob-send-now" data-report="profitability" style="margin-left:8px;">
                    <?php esc_html_e('Send Profitability Report', 'mob-slack-reports'); ?>
                </button>
                <span class="mob-send-status" style="margin-left:12px;"></span>
            </td>
        </tr>
        <?php
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'slack_reports') {
            return;
        }

        wp_enqueue_style('mob-reports-admin', MOB_REPORTS_URL . 'assets/css/admin.css', [], MOB_REPORTS_VERSION);
        wp_add_inline_script('jquery', $this->get_send_now_js());
    }

    private function get_send_now_js(): string {
        $nonce = wp_create_nonce('mob_send_report_now');
        return <<<JS
jQuery(function($){
    $('.mob-send-now').on('click', function(){
        var btn = $(this), report = btn.data('report'), status = $('.mob-send-status');
        btn.prop('disabled', true);
        status.text('Sending ' + report + ' report…');
        $.post(ajaxurl, {
            action: 'mob_send_report_now',
            report: report,
            _wpnonce: '{$nonce}'
        }, function(res){
            status.text(res.success ? res.data : 'Error: ' + res.data);
            btn.prop('disabled', false);
        }).fail(function(){
            status.text('Request failed.');
            btn.prop('disabled', false);
        });
    });
});
JS;
    }

    public function ajax_send_now(): void {
        check_ajax_referer('mob_send_report_now');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied.');
        }

        $report = sanitize_text_field($_POST['report'] ?? '');

        if ($report === 'inventory') {
            $result = MOB_Reports_Scheduler::send_inventory_report();
        } elseif ($report === 'profitability') {
            $result = MOB_Reports_Scheduler::send_profitability_report();
        } else {
            wp_send_json_error('Invalid report type.');
            return;
        }

        if ($result['ok']) {
            wp_send_json_success('Report sent successfully!');
        } else {
            wp_send_json_error($result['error'] ?? 'Unknown error');
        }
    }

    private static function get_timezone_options(): array {
        $zones = [
            'America/New_York'    => 'Eastern (ET)',
            'America/Chicago'     => 'Central (CT)',
            'America/Denver'      => 'Mountain (MT)',
            'America/Los_Angeles' => 'Pacific (PT)',
            'America/Anchorage'   => 'Alaska (AKT)',
            'Pacific/Honolulu'    => 'Hawaii (HT)',
            'UTC'                 => 'UTC',
        ];

        $wp_tz = wp_timezone_string();
        if ($wp_tz && !isset($zones[$wp_tz])) {
            $zones[$wp_tz] = $wp_tz . ' (WordPress)';
        }

        return $zones;
    }

    // --- Helpers to retrieve options ---

    public static function get(string $key, $default = '') {
        return get_option(self::OPTION_PREFIX . $key, $default);
    }

    public static function get_bot_token(): string {
        return (string) self::get('bot_token', '');
    }

    public static function get_timezone(): string {
        return (string) self::get('timezone', self::get_site_timezone());
    }

    private static function get_site_timezone(): string {
        $tz = wp_timezone_string();
        return ($tz !== '' && $tz !== '0') ? $tz : 'America/Chicago';
    }

    public static function get_delivery_time(): string {
        return (string) self::get('delivery_time', '08:00');
    }

    public static function get_inventory_channel(): string {
        return (string) self::get('inventory_channel', '');
    }

    public static function get_profit_channel(): string {
        return (string) self::get('profit_channel', '');
    }

    public static function is_inventory_enabled(): bool {
        return self::get('inventory_enabled', 'yes') === 'yes';
    }

    public static function is_profit_enabled(): bool {
        return self::get('profit_enabled', 'yes') === 'yes';
    }

    public static function get_profit_period(): string {
        return (string) self::get('profit_period', 'previous_day');
    }

    public static function get_inventory_sales_window(): int {
        return max(7, (int) self::get('inventory_sales_window', 28));
    }
}
