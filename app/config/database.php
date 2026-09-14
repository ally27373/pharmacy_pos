<?php
/**
 * ==========================================================
 * NICA XANDRA PHARMACY POS SYSTEM
 * Database Configuration (PDO)
 * ==========================================================
 */

declare(strict_types=1);

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
         * Local XAMPP defaults
         *
         * Railway can override these through environment
         * variables without changing the code.
         */

        $this->host = getenv('DB_HOST') ?: '127.0.0.1';

        $this->db_name = getenv('DB_DATABASE') ?: 'pharmacy_pos';

        $this->username = getenv('DB_USERNAME') ?: 'root';

        $this->password = getenv('DB_PASSWORD') ?: '';

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
