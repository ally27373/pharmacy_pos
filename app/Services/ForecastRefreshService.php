<?php

namespace App\Services;

use PDO;
use RuntimeException;

class ForecastRefreshService
{
    private PDO $conn;
    private string $projectRoot;
    private string $sarimaRoot;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->projectRoot = dirname(__DIR__, 2);
        $this->sarimaRoot = $this->projectRoot . DIRECTORY_SEPARATOR . 'sarima_forecasting';
    }

    /**
     * Export completed POS demand as a daily series.
     * No missing dates are invented as zero-sales observations.
     */
    public function exportLatestDailyDemand(): array
    {
        $sql = "
            SELECT
                DATE(s.created_at) AS date,
                SUM(si.quantity) AS quantity_sold
            FROM sales s
            INNER JOIN sale_items si ON s.sale_id = si.sale_id
            WHERE s.transaction_status = 'Completed'
            GROUP BY DATE(s.created_at)
            ORDER BY date ASC
        ";

        $rows = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            throw new RuntimeException('No completed POS sales are available for SARIMA demand input.');
        }

        $outputDir = $this->sarimaRoot . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'deployment';
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Unable to create SARIMA deployment output directory.');
        }

        $path = $outputDir . DIRECTORY_SEPARATOR . 'latest_daily_demand.csv';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write latest_daily_demand.csv.');
        }

        fputcsv($handle, ['date', 'quantity_sold']);
        foreach ($rows as $row) {
            fputcsv($handle, [$row['date'], (float) $row['quantity_sold']]);
        }
        fclose($handle);

        return [
            'path' => $path,
            'records' => count($rows),
            'first_date' => $rows[0]['date'],
            'last_date' => $rows[count($rows) - 1]['date']
        ];
    }

    /**
     * Run the Python forecast generator.
     * PYTHON_BIN may be set to a full Python executable path when needed.
     */
    public function refresh(): array
    {
        $input = $this->exportLatestDailyDemand();
        $script = $this->sarimaRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . '07_forecast_generation.py';

        if (!is_file($script) || filesize($script) === 0) {
            throw new RuntimeException('SARIMA forecast generation script is missing or empty.');
        }

        $python = getenv('PYTHON_BIN') ?: 'python';
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("SARIMA forecast generation failed:\n" . implode(PHP_EOL, $output));
        }

        // Refresh the current-year annual outlook from the same deployed
        // SARIMA model and latest legitimate POS demand input. This is a
        // presentation artifact and must not block the operational 30-day
        // forecast if annual generation fails.
        $annualScript = $this->sarimaRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . '09_annual_forecast.py';
        $annualOutput = [];
        $annualExitCode = 0;
        $annualError = null;

        if (is_file($annualScript) && filesize($annualScript) > 0) {
            $annualCommand = escapeshellarg($python) . ' ' . escapeshellarg($annualScript) . ' 2>&1';
            exec($annualCommand, $annualOutput, $annualExitCode);
            if ($annualExitCode !== 0) {
                $annualError = implode(PHP_EOL, $annualOutput);
            }
        } else {
            $annualExitCode = 1;
            $annualError = 'Annual SARIMA forecast generation script is missing or empty.';
        }

        $statusPath = $this->sarimaRoot . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'deployment' . DIRECTORY_SEPARATOR . 'forecast_status.json';
        $status = null;
        if (is_file($statusPath) && filesize($statusPath) > 0) {
            $statusJson = file_get_contents($statusPath);
            $status = $statusJson !== false ? json_decode($statusJson, true) : null;
        }

        return [
            'success' => (bool) ($status['ready'] ?? true),
            'status' => $status,
            'input' => $input,
            'output' => implode(PHP_EOL, $output),
            'annual_forecast' => [
                'success' => $annualExitCode === 0,
                'output' => implode(PHP_EOL, $annualOutput),
                'error' => $annualError,
            ],
        ];
    }
}
