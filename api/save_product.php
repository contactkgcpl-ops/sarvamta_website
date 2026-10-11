<?php
/**
 * SARVAMTA SPICES - Save / Update Product API
 * Endpoint: POST /api/save_product.php
 * Handles creation and editing of products, multi-image upload,
 * preserves old images when new ones are added, and updates variants/prices.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        'message' => 'Database connection failed. Please ensure MySQL is running.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method. Only POST allowed.'
    ]);
    exit;
}

try {
    $pId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'Ground Spices';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $isFavorite = (isset($_POST['is_favorite']) && (int)$_POST['is_favorite'] === 1) ? 1 : 0;

    if (empty($name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Product Name is required.'
        ]);
        exit;
    }

    // 1. Fetch Existing Images if editing
    $existingImages = [];
    if ($pId > 0) {
        $stmtOld = $db->prepare("SELECT image, images FROM products WHERE id = :id");
        $stmtOld->execute([':id' => $pId]);
        $rowOld = $stmtOld->fetch(PDO::FETCH_ASSOC);
        if ($rowOld) {
            if (!empty($rowOld['images'])) {
                $decoded = json_decode($rowOld['images'], true);
                if (is_array($decoded)) {
                    $existingImages = $decoded;
                }
            }
            if (empty($existingImages) && !empty($rowOld['image'])) {
                $existingImages = [$rowOld['image']];
            }
        }
    }

    // Also support retained_images from frontend if specified
    if (isset($_POST['retained_images'])) {
        $rawRetained = is_array($_POST['retained_images']) ? $_POST['retained_images'] : json_decode($_POST['retained_images'], true);
        if (is_array($rawRetained)) {
            $existingImages = $rawRetained;
        }
    }

    // Clean up existing images: strip leading ../ and filter out data: URLs
    $cleanedExisting = [];
    foreach ($existingImages as $img) {
        if (is_string($img)) {
            $imgClean = trim($img);
            $imgClean = preg_replace('/^\.\.\//', '', $imgClean);
            if ($imgClean !== '' && !str_starts_with($imgClean, 'data:')) {
                $cleanedExisting[] = $imgClean;
            }
        }
    }
    $existingImages = array_values(array_unique($cleanedExisting));

    // 2. Process Newly Uploaded Images
    $newUploadedPaths = [];
    $uploadDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'])) {
        $names = (array)$_FILES['product_images']['name'];
        $tmpNames = (array)$_FILES['product_images']['tmp_name'];
        $errors = (array)$_FILES['product_images']['error'];
        $timestamp = time();

        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
        $slug = trim($slug, '_') ?: 'product';

        for ($i = 0; $i < count($names); $i++) {
            if (!empty($names[$i]) && $errors[$i] === UPLOAD_ERR_OK) {
                $orig = basename($names[$i]);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION)) ?: 'jpg';
                $cleanName = $slug . '_' . $timestamp . '_' . ($i + 1) . '.' . $ext;
                $dest = $uploadDir . $cleanName;
                if (move_uploaded_file($tmpNames[$i], $dest)) {
                    $newUploadedPaths[] = 'uploads/' . $cleanName;
                }
            }
        }
    }

    // 3. COMBINE IMAGES: Old images + New images
    // If a product has 1 image, and a 2nd is uploaded, BOTH are preserved (Total: 2)
    if (!empty($newUploadedPaths)) {
        // Merge existing and newly uploaded images (preserving both)
        $combinedImages = array_values(array_unique(array_merge($existingImages, $newUploadedPaths)));
    } else if (!empty($existingImages)) {
        // Keep all existing images
        $combinedImages = $existingImages;
    } else if (!empty($_POST['image'])) {
        $cleanedSingle = preg_replace('/^\.\.\//', '', trim($_POST['image']));
        $combinedImages = [$cleanedSingle];
    } else {
        $combinedImages = ['images/sarvamta_chilli_powder.jpg'];
    }

    $primaryImage = !empty($combinedImages[0]) ? $combinedImages[0] : 'images/sarvamta_chilli_powder.jpg';
    $imagesJson = json_encode($combinedImages, JSON_UNESCAPED_SLASHES);

    // 4. Insert or Update Products table
    if ($pId > 0) {
        $stmt = $db->prepare("UPDATE products SET 
            name = :name, 
            category = :category, 
            image = :image, 
            images = :images, 
            description = :description, 
            is_favorite = :is_fav 
            WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':image' => $primaryImage,
            ':images' => $imagesJson,
            ':description' => $description,
            ':is_fav' => $isFavorite,
            ':id' => $pId
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO products (name, category, image, images, description, is_favorite, is_active) 
            VALUES (:name, :category, :image, :images, :description, :is_fav, 1)");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':image' => $primaryImage,
            ':images' => $imagesJson,
            ':description' => $description,
            ':is_fav' => $isFavorite
        ]);
        $pId = (int)$db->lastInsertId();
    }

    // 5. Update product_images table
    $stmtDelImg = $db->prepare("DELETE FROM product_images WHERE product_id = :id");
    $stmtDelImg->execute([':id' => $pId]);

    $stmtInsImg = $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_primary) VALUES (:pid, :url, :sort, :is_prim)");
    foreach ($combinedImages as $idx => $imgPath) {
        $stmtInsImg->execute([
            ':pid' => $pId,
            ':url' => $imgPath,
            ':sort' => $idx + 1,
            ':is_prim' => ($idx === 0 ? 1 : 0)
        ]);
    }

    // 6. Update product_variants (Packages and Pricing)
    $sizes = isset($_POST['pkg_size']) ? (array)$_POST['pkg_size'] : [];
    $actualPrices = isset($_POST['pkg_actual_price']) ? (array)$_POST['pkg_actual_price'] : [];
    $prices = isset($_POST['pkg_price']) ? (array)$_POST['pkg_price'] : [];

    if (!empty($sizes)) {
        $stmtDelVar = $db->prepare("DELETE FROM product_variants WHERE product_id = :id");
        $stmtDelVar->execute([':id' => $pId]);

        $stmtInsVar = $db->prepare("INSERT INTO product_variants (product_id, package_size, actual_price, price, is_default, sort_order) VALUES (:pid, :size, :actual_price, :price, :is_default, :sort)");
        
        $validIndex = 0;
        for ($i = 0; $i < count($sizes); $i++) {
            $sz = trim($sizes[$i]);
            if ($sz === '') continue;

            $pr = isset($prices[$i]) ? (float)$prices[$i] : 0.0;
            $actPr = isset($actualPrices[$i]) && (float)$actualPrices[$i] > 0 ? (float)$actualPrices[$i] : ($pr > 0 ? $pr : 0.0);
            $isDef = ($validIndex === 0 ? 1 : 0);

            $stmtInsVar->execute([
                ':pid' => $pId,
                ':size' => $sz,
                ':actual_price' => $actPr,
                ':price' => $pr,
                ':is_default' => $isDef,
                ':sort' => $validIndex + 1
            ]);
            $validIndex++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Product saved successfully to database',
        'product_id' => $pId,
        'image' => $primaryImage,
        'images' => $combinedImages,
        'image_count' => count($combinedImages)
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save product: ' . $e->getMessage()
    ]);
}
?>
