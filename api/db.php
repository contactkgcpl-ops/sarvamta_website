<?php
/**
 * SARVAMTA SPICES - Database Connection Helper
 * Reads configuration directly from .env file.
 * Automatically creates empty database tables if they do not exist.
 * No sample/dummy data is inserted.
 */

// PHP < 8.0 polyfills for server compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn = null;

    private static $tablesInitialized = false;

    public function __construct() {
        $this->loadEnv();
    }

    /**
     * Parse .env file from project root
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        $envData = [];

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    // Remove surrounding quotes if any
                    $v = trim($v, "\"'");
                    $envData[$k] = $v;
                }
            }
        }

        // Set DB credentials with fallbacks
        $this->host = $envData['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $this->port = (int)($envData['DB_PORT'] ?? getenv('DB_PORT') ?: 3307);
        $this->db_name = $envData['DB_NAME'] ?? $envData['DB_DATABASE'] ?? getenv('DB_NAME') ?: 'sarvamta_shop';
        $this->username = $envData['DB_USER'] ?? $envData['DB_USERNAME'] ?? getenv('DB_USER') ?: 'root';
        $this->password = $envData['DB_PASS'] ?? $envData['DB_PASSWORD'] ?? getenv('DB_PASS') ?: '';
    }

    public function getConnection() {
        $this->conn = null;
        $this->lastError = '';

        // On Hostinger, 'localhost' uses the local Unix socket, whereas '127.0.0.1' uses TCP.
        // We try the configured host first, then automatically fallback to localhost and 127.0.0.1.
        $hostsToTry = array_unique([$this->host, 'localhost', '127.0.0.1']);
        $portsToTry = array_unique([$this->port, 3306, 3307]);

        foreach ($hostsToTry as $h) {
            foreach ($portsToTry as $port) {
                $dsnList = [
                    "mysql:host=" . $h . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    "mysql:host=" . $h . ";port=" . $port . ";dbname=" . $this->db_name . ";charset=utf8mb4"
                ];

                foreach ($dsnList as $dsn) {
                    try {
                        $options = [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ];

                        $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                        if ($this->conn) {
                            if (!self::$tablesInitialized) {
                                $this->autoInitTables();
                                self::$tablesInitialized = true;
                            }
                            return $this->conn;
                        }
                    } catch(PDOException $exception) {
                        $this->lastError = $exception->getMessage();
                        $this->conn = null;
                    }
                }
            }
        }

        return $this->conn;
    }

    /**
     * Automatically creates required empty tables if not exist (NO DUMMY INSERT)
     */
    public function autoInitTables() {
        if (!$this->conn) return;

        try {
            // 1. Products Table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `category` VARCHAR(100) NOT NULL,
                `image` VARCHAR(255) NOT NULL,
                `images` TEXT DEFAULT NULL,
                `rating` DECIMAL(2,1) DEFAULT 4.5,
                `review_count` INT DEFAULT 0,
                `description` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                `is_favorite` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 2. Product Images Table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `product_images` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT NOT NULL,
                `image_url` VARCHAR(255) NOT NULL,
                `sort_order` INT DEFAULT 0,
                `is_primary` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 3. Product Variants Table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `product_variants` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT NOT NULL,
                `package_size` VARCHAR(50) NOT NULL,
                `actual_price` DECIMAL(10,2) DEFAULT NULL,
                `price` DECIMAL(10,2) NOT NULL,
                `is_default` TINYINT(1) DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 4. Inquiries Table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `inquiries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NOT NULL,
                `product_name` VARCHAR(255) NOT NULL,
                `packaging_size` VARCHAR(100) NOT NULL,
                `quantity` INT DEFAULT 1,
                `status` ENUM('Pending', 'Ongoing', 'Completed') DEFAULT 'Pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        } catch (Exception $e) {
            error_log("Database auto-init notice: " . $e->getMessage());
        }
    }

    public function getConfigInfo() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'db_name' => $this->db_name,
            'user' => $this->username
        ];
    }
}
?>
