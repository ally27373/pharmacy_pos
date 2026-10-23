#!/bin/bash
# Auto-refresh SARIMA forecast. Called by cron every 5 minutes.
LOG_FILE="/var/www/html/sarima_forecasting/outputs/deployment/auto_refresh.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

cd /var/www/html || exit 1
# Activate the project venv when present. ForecastRefreshService also resolves
# ./venv/bin/python itself, so this activation is a convenience (e.g. for any
# direct python calls) and must not abort the refresh when the venv is absent.
if [ -f venv/bin/activate ]; then
    # shellcheck disable=SC1091
    source venv/bin/activate
fi
php app/tests/test_forecast_refresh.php >> "$LOG_FILE" 2>&1
echo "[$DATE] Auto-refresh executed." >> "$LOG_FILE"
