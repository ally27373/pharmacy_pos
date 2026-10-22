#!/bin/bash
# Auto-refresh SARIMA forecast. Called by cron every 5 minutes.
LOG_FILE="/var/www/html/sarima_forecasting/outputs/deployment/auto_refresh.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

cd /var/www/html || exit 1
source venv/bin/activate >> "$LOG_FILE" 2>&1
php app/tests/test_forecast_refresh.php >> "$LOG_FILE" 2>&1
echo "[$DATE] Auto-refresh executed." >> "$LOG_FILE"
