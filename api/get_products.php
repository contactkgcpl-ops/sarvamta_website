<?php
/**
 * SARVAMTA SPICES - Fetch Products API
 * Returns JSON product catalog with packaging sizes & prices.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

// Parse query params
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'best_selling';
$size_filter = isset($_GET['size']) ? trim($_GET['size']) : '';

$database = new Database();
$db = $database->getConnection();

$products = [];

if ($db !== null) {
    try {
        // Base Query
        $query = "SELECT p.*, 
                    pv.id as variant_id, 
                    pv.package_size, 
                    pv.actual_price,
                    pv.price, 
                    pv.is_default, 
                    pv.sort_order
                  FROM products p
                  LEFT JOIN product_variants pv ON p.id = pv.product_id
                  WHERE p.is_active = 1";

        $params = [];

        if (!empty($category) && strtolower($category) !== 'all') {
            $query .= " AND p.category = :category";
            $params[':category'] = $category;
        }

        if (!empty($search)) {
            $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $favorite = isset($_GET['favorite']) ? trim($_GET['favorite']) : '';
        if ($favorite !== '') {
            $query .= " AND p.is_favorite = :is_favorite";
            $params[':is_favorite'] = (int)$favorite;
        }

        $query .= " ORDER BY p.id ASC, pv.sort_order ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Fetch all product images from product_images table
        $productImagesMap = [];
        try {
            $imgStmt = $db->query("SELECT product_id, image_url FROM product_images ORDER BY sort_order ASC, id ASC");
            if ($imgStmt) {
                while ($imgRow = $imgStmt->fetch()) {
                    $productImagesMap[$imgRow['product_id']][] = $imgRow['image_url'];
                }
            }
        } catch (PDOException $e) {}

        // Group variants and images by product
        $grouped = [];
        foreach ($rows as $row) {
            $pId = $row['id'];
            if (!isset($grouped[$pId])) {
                $imgList = [];
                if (!empty($productImagesMap[$pId])) {
                    $imgList = $productImagesMap[$pId];
                } else if (!empty($row['images'])) {
                    $decoded = json_decode($row['images'], true);
                    if (is_array($decoded)) {
                        $imgList = $decoded;
                    }
                }

                if (empty($imgList) && !empty($row['image'])) {
                    $imgList = [$row['image']];
                }

                $mainImg = !empty($imgList) ? $imgList[0] : (!empty($row['image']) ? $row['image'] : 'images/logo.png');

                $grouped[$pId] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'image' => $mainImg,
                    'images' => $imgList,
                    'is_favorite' => isset($row['is_favorite']) ? (int)$row['is_favorite'] : 0,
                    'rating' => (float)$row['rating'],
                    'review_count' => (int)$row['review_count'],
                    'description' => $row['description'],
                    'packages' => []
                ];
            }

            if ($row['variant_id'] !== null) {
                $actPrice = !empty($row['actual_price']) ? (float)$row['actual_price'] : ((float)$row['price'] * 1.25);
                $grouped[$pId]['packages'][] = [
                    'id' => (int)$row['variant_id'],
                    'size' => $row['package_size'],
                    'actual_price' => (float)$actPrice,
                    'price' => (float)$row['price'],
                    'is_default' => (int)$row['is_default'] == 1
                ];
            }
        }

        $products = array_values($grouped);

    } catch (PDOException $e) {
        $products = [];
    }
}

// Sorting logic
usort($products, function($a, $b) use ($sort) {
    // Helper to get default price
    $getDefaultPrice = function($item) {
        foreach ($item['packages'] as $pkg) {
            if ($pkg['is_default']) return $pkg['price'];
        }
        return isset($item['packages'][0]['price']) ? $item['packages'][0]['price'] : 0;
    };

    if ($sort === 'price_asc') {
        return $getDefaultPrice($a) <=> $getDefaultPrice($b);
    } elseif ($sort === 'price_desc') {
        return $getDefaultPrice($b) <=> $getDefaultPrice($a);
    } elseif ($sort === 'rating_desc') {
        return $b['rating'] <=> $a['rating'];
    } elseif ($sort === 'name_asc') {
        return strcmp($a['name'], $b['name']);
    } else {
        // best_selling default sort by rating * review_count
        $scoreA = $a['rating'] * $a['review_count'];
        $scoreB = $b['rating'] * $b['review_count'];
        return $scoreB <=> $scoreA;
    }
});

// Output JSON
echo json_encode([
    'status' => 'success',
    'count' => count($products),
    'data' => $products
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
