<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($id > 0 && in_array($status, ['Pending', 'Ongoing', 'Completed'])) {
        try {
            $stmt = $db->prepare("UPDATE inquiries SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            $response['success'] = true;
        } catch (PDOException $e) {
            $response['error'] = $e->getMessage();
        }
    }
}

echo json_encode($response);
