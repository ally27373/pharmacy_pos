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
    private string $host = "altaria.proxy.rlwy.net";
    private string $db_name = "pharmacy_pos";
    private string $username = "root";
    private string $password = "hefoVTYeVrmYqzmVamHIXhEzwnzIgzAk";

    private ?PDO $connection = null;

    public function connect(): PDO
    {
        if ($this->connection === null) {

            $dsn = "mysql:host={$this->host};port=16087;dbname={$this->db_name};charset=utf8mb4";

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
                    "<p>" . htmlspecialchars($e->getMessage()) . "</p>"
                );
            }
        }

        return $this->connection;
    }
}
