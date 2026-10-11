<?php
/**
 * SARVAMTA SPICES - Toggle Favorite API
 * Endpoint: POST/GET /api/toggle_favorite.php?id=X&fav=1|0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$pId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$fav = isset($_REQUEST['fav']) ? (int)$_REQUEST['fav'] : 0;

if ($pId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid product ID'
    ]);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE products SET is_favorite = :fav WHERE id = :id");
    $stmt->execute([':fav' => $fav, ':id' => $pId]);

    echo json_encode([
        'status' => 'success',
        'id' => $pId,
        'is_favorite' => $fav,
        'message' => 'Favorite status updated successfully'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
