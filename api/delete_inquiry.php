    <?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM inquiries WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $response['success'] = true;
        } catch (PDOException $e) {
            $response['error'] = $e->getMessage();
        }
    }
}

echo json_encode($response);
