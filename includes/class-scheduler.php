<?php
defined('ABSPATH') || exit;

class MOB_Reports_Scheduler {

    private static ?self $instance = null;

    const INVENTORY_HOOK = 'mob_inventory_report_event';
    const PROFIT_HOOK    = 'mob_profit_report_event';

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action(self::INVENTORY_HOOK, [__CLASS__, 'send_inventory_report']);
        add_action(self::PROFIT_HOOK, [__CLASS__, 'send_profitability_report']);
        add_action('init', [__CLASS__, 'ensure_scheduled']);
    }

    /**
     * Ensure both cron events are scheduled. Called on every init.
     */
    public static function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::INVENTORY_HOOK)) {
            self::schedule_events();
        }
    }

    /**
     * Schedule (or reschedule) both daily cron events at the configured time.
     */
    public static function schedule_events(): void {
        wp_clear_scheduled_hook(self::INVENTORY_HOOK);
        wp_clear_scheduled_hook(self::PROFIT_HOOK);

        $timestamp = self::next_run_timestamp();

        wp_schedule_event($timestamp, 'daily', self::INVENTORY_HOOK);
        wp_schedule_event($timestamp, 'daily', self::PROFIT_HOOK);
    }

    /**
     * Called when settings are saved to reschedule at the new time.
     */
    public static function reschedule_events(): void {
        self::schedule_events();
    }

    /**
     * Calculate the next Unix timestamp for the configured delivery time.
     */
    private static function next_run_timestamp(): int {
        $tz       = new DateTimeZone(MOB_Reports_Settings::get_timezone());
        $time_str = MOB_Reports_Settings::get_delivery_time();
        $now      = new DateTime('now', $tz);

        $parts = array_map('intval', explode(':', $time_str));
        $hour  = $parts[0] ?? 9;
        $min   = $parts[1] ?? 0;

        $run = new DateTime('now', $tz);
        $run->setTime($hour, $min, 0);

        if ($run <= $now) {
            $run->modify('+1 day');
        }

        return $run->getTimestamp();
    }

    /**
     * Generate and send the inventory report to Slack.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function send_inventory_report(): array {
        if (!MOB_Reports_Settings::is_inventory_enabled()) {
            return ['ok' => false, 'error' => 'inventory_report_disabled'];
        }

        $channel = MOB_Reports_Settings::get_inventory_channel();
        $token   = MOB_Reports_Settings::get_bot_token();

        if (!$channel || !$token) {
            return ['ok' => false, 'error' => 'missing_slack_config'];
        }

        $report = MOB_Inventory_Report::generate();
        if (!$report['ok']) {
            return $report;
        }

        $result = MOB_Slack_Sender::upload_file(
            $report['file'],
            $report['filename'],
            'Inventory Status Report — ' . get_bloginfo('name'),
            $channel,
            $token
        );

        @unlink($report['file']);

        return $result;
    }

    /**
     * Generate and send the profitability report to Slack.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function send_profitability_report(): array {
        if (!MOB_Reports_Settings::is_profit_enabled()) {
            return ['ok' => false, 'error' => 'profit_report_disabled'];
        }

        $channel = MOB_Reports_Settings::get_profit_channel();
        $token   = MOB_Reports_Settings::get_bot_token();

        if (!$channel || !$token) {
            return ['ok' => false, 'error' => 'missing_slack_config'];
        }

        $report = MOB_Profitability_Report::generate();
        if (!$report['ok']) {
            return $report;
        }

        $result = MOB_Slack_Sender::upload_file(
            $report['file'],
            $report['filename'],
            'Profitability Report — ' . get_bloginfo('name'),
            $channel,
            $token
        );

        @unlink($report['file']);

        return $result;
    }
}
