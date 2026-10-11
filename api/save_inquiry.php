<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if ($db) {
    try {
        // Ensure inquiries table exists
        $db->exec("CREATE TABLE IF NOT EXISTS `inquiries` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(255) NOT NULL,
          `email` VARCHAR(255) NOT NULL,
          `phone` VARCHAR(50) NOT NULL,
          `product_name` VARCHAR(255) NOT NULL,
          `packaging_size` VARCHAR(100) NOT NULL,
          `quantity` INT DEFAULT 1,
          `status` ENUM('Pending', 'Ongoing', 'Completed') DEFAULT 'Pending',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : (isset($_POST['product']) ? trim($_POST['product']) : '');
        $packaging_size = isset($_POST['packaging_size']) ? trim($_POST['packaging_size']) : '200g';
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if (!empty($name) && !empty($phone)) {
            $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, product_name, packaging_size, quantity, status) VALUES (:name, :email, :phone, :pname, :psize, :qty, 'Pending')");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':pname' => $product_name,
                ':psize' => $packaging_size,
                ':qty' => $quantity
            ]);
            $response['success'] = true;
            $response['message'] = 'Inquiry saved to database successfully!';
        } else {
            $response['message'] = 'Missing required fields (name or phone).';
        }
    } catch (PDOException $e) {
        $response['message'] = 'DB Exception: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Could not establish DB connection.';
}

echo json_encode($response);
