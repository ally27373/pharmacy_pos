<?php
/**
 * ==========================================================
 * NICA XANDRA PHARMACY POS SYSTEM
 * Database Configuration (PDO)
 * ==========================================================
 */

declare(strict_types=1);

date_default_timezone_set('UTC');

class Database
{
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private int $port;

    private ?PDO $connection = null;

    public function __construct()
    {
        /*
         * VPS defaults (Hostinger KVM 2 - Ubuntu 26.04)
         *
         * Override through environment variables without
         * changing the code:
         * DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_PORT
         */

        $this->host = getenv('DB_HOST') ?: '127.0.0.1';

        $this->db_name = getenv('DB_DATABASE') ?: 'pharmacy_pos';

        $this->username = getenv('DB_USERNAME') ?: 'pharma';

        $this->password = getenv('DB_PASSWORD') ?: 'ALLYSA';

        $this->port = (int)(getenv('DB_PORT') ?: 3306);
    }

    public function connect(): PDO
    {
        if ($this->connection === null) {

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->db_name
            );

            try {

                $this->connection = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );

                // Keep the DB session in UTC so TIMESTAMP values are
                // stored/read consistently on any server.
                $this->connection->exec("SET time_zone = '+00:00'");

            } catch (PDOException $e) {

                die(
                    "<h2>Database Connection Failed</h2>" .
                    "<p>" .
                    htmlspecialchars($e->getMessage()) .
                    "</p>"
                );
            }
        }

        return $this->connection;
    }
}

if (!function_exists('toPhTime')) {

    /**
     * Format a UTC datetime string for Philippine display (UTC+8).
     * Defined here as well so pages that load the database layer
     * directly (without session.php) still have it.
     */
    function toPhTime(?string $utcDateTime, string $format = 'M d, Y h:i A'): string
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return 'N/A';
        }

        try {
            $dt = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            return $dt->format($format);
        } catch (Throwable $e) {
            return 'N/A';
        }
    }

}
