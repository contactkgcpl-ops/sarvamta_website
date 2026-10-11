<?php
/**
 * SARVAMTA SPICES - Database Connection Helper
 */

class Database {
    private $host = '127.0.0.1';
    private $db_name = 'sarvamta_shop';
    private $username = 'root';
    private $password = '';
    public $conn = null;

    public function getConnection() {
        $this->conn = null;

        // Try Port 3307 first (User's XAMPP port), then fallback to 3306
        $ports = [3307, 3306];

        foreach ($ports as $port) {
            try {
                $dsn = "mysql:host=" . $this->host . ";port=" . $port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                if ($this->conn) {
                    break;
                }
            } catch(PDOException $exception) {
                $this->conn = null;
            }
        }

        return $this->conn;
    }
}
?>
