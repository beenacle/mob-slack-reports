# MOB Slack Reports

A WooCommerce plugin that generates daily **Inventory** and **Profitability** report PDFs and delivers them to configurable Slack channels.

## Features

- **Inventory Status Report** — stock levels, weekly sales averages, projected demand (10% growth factor), weeks of supply, and color-coded zone indicators (Danger / Safe / Ideal)
- **Profitability Report** — per-product revenue, cost of goods, gross sales, net sales, and margin summary using WooCommerce's built-in COGS feature
- **PDF output** — clean, branded reports with site logo, date, styled tables, and summary statistics via DOMPDF
- **Slack delivery** — uploads PDFs directly to Slack channels using the modern `files.uploadV2` API flow
- **Configurable scheduling** — daily delivery at a time and timezone you choose (default: 9:00 AM Central)
- **Separate channels** — route inventory and profitability reports to different Slack channels
- **Manual trigger** — "Send Now" buttons in the settings page for instant testing
- **Multi-site ready** — install on multiple WooCommerce stores, each with independent settings pointing to the same or different Slack channels

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+
- [WooCommerce Cost of Goods Sold (COGS)](https://woocommerce.com/document/woocommerce-cost-of-goods-sold-cogs/) enabled (for the profitability report)
- A Slack Bot Token with `files:write` and `chat:write` scopes

## Installation

1. Download or clone this repository into `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/beenacle/mob-slack-reports.git
   ```

2. Install PHP dependencies:
   ```bash
   cd mob-slack-reports
   composer install --no-dev
   ```

3. Activate the plugin in **WordPress Admin > Plugins**.

4. Go to **WooCommerce > Settings > Slack Reports** and configure:
   - Slack Bot Token
   - Channel IDs for each report
   - Delivery time and timezone
   - Report period for profitability (Previous Day, Last 7/30 Days, Month to Date)

## Configuration

All settings are managed under **WooCommerce > Settings > Slack Reports**:

| Setting | Default | Description |
|---|---|---|
| Slack Bot Token | — | `xoxb-*` token with `files:write` and `chat:write` scopes |
| Inventory Report Channel ID | — | Slack channel for inventory reports |
| Profitability Report Channel ID | — | Slack channel for profitability reports |
| Delivery Time | `09:00` | 24-hour format, daily send time |
| Timezone | `America/Chicago` | Timezone for scheduling |
| Profitability Report Period | Previous Day | Time window for order data |
| Inventory Sales Window | 28 days | Lookback period for weekly sales averages |

## Slack Bot Setup

1. Create a Slack App at [api.slack.com/apps](https://api.slack.com/apps)
2. Add Bot Token Scopes: `files:write`, `chat:write`
3. Install the app to your workspace
4. Copy the **Bot User OAuth Token** (`xoxb-...`) into the plugin settings
5. Invite the bot to both report channels (`/invite @YourBot`)

## Plugin Structure

```
mob-slack-reports/
├── mob-slack-reports.php              # Main plugin bootstrap
├── composer.json                      # DOMPDF dependency
├── includes/
│   ├── class-settings.php             # WooCommerce settings tab
│   ├── class-scheduler.php            # WP-Cron scheduling
│   ├── class-slack-sender.php         # Slack file upload API
│   ├── class-pdf-generator.php        # HTML-to-PDF via DOMPDF
│   ├── class-inventory-report.php     # Inventory data collection
│   └── class-profitability-report.php # Profitability data collection
├── templates/
│   ├── inventory-report.php           # Inventory PDF template
│   └── profitability-report.php       # Profitability PDF template
└── assets/
    └── css/admin.css                  # Settings page styles
```

## License

Proprietary — (c) [Beenacle](https://beenacle.com). All rights reserved.
