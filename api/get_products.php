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
        $products = []; // Fallback will trigger
    }
}

// Fallback Mock Data ONLY if DB connection completely failed
if (empty($products) && $db === null) {
    $fallbackProducts = [
        [
            'id' => 1,
            'name' => 'Resham Patti Chilli Powder',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_chilli_powder.jpg',
            'rating' => 4.8,
            'review_count' => 96,
            'description' => 'Vibrant red Resham Patti Chilli Powder processed under hygienic conditions.',
            'packages' => [
                ['id' => 101, 'size' => '200g', 'price' => 180, 'is_default' => true],
                ['id' => 102, 'size' => '500g', 'price' => 420, 'is_default' => false],
                ['id' => 103, 'size' => '1kg', 'price' => 800, 'is_default' => false],
                ['id' => 104, 'size' => '5kg', 'price' => 3800, 'is_default' => false],
                ['id' => 105, 'size' => '20kg', 'price' => 14500, 'is_default' => false]
            ]
        ],
        [
            'id' => 2,
            'name' => 'Mix Achar Masala Powder',
            'category' => 'Masala Mix',
            'image' => 'images/sarvamta_cloves_powder.jpg',
            'rating' => 4.7,
            'review_count' => 93,
            'description' => 'Traditional Gujarati Achar Masala mix prepared with mustard seeds, fenugreek and chili.',
            'packages' => [
                ['id' => 201, 'size' => '500g', 'price' => 245, 'is_default' => true],
                ['id' => 202, 'size' => '200g', 'price' => 110, 'is_default' => false],
                ['id' => 203, 'size' => '150g', 'price' => 85, 'is_default' => false],
                ['id' => 204, 'size' => '5kg', 'price' => 2200, 'is_default' => false]
            ]
        ],
        [
            'id' => 3,
            'name' => 'Selam Haldi Powder',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta-turmeric_powder.jpg',
            'rating' => 4.9,
            'review_count' => 66,
            'description' => '100% Pure Selam grade Turmeric Powder high in natural curcumin content.',
            'packages' => [
                ['id' => 301, 'size' => '500g', 'price' => 270, 'is_default' => true],
                ['id' => 302, 'size' => '1kg', 'price' => 510, 'is_default' => false],
                ['id' => 303, 'size' => '5kg', 'price' => 2400, 'is_default' => false],
                ['id' => 304, 'size' => '20kg', 'price' => 9200, 'is_default' => false]
            ]
        ],
        [
            'id' => 4,
            'name' => 'Red Chilli Powder (Lal Mirch) - Pure Grade',
            'category' => 'Ground Spices',
            'image' => 'images/your-brand-redchilli-powder.png',
            'rating' => 4.8,
            'review_count' => 66,
            'description' => 'Premium Stemless Lal Mirch Powder for deep natural red color and balanced heat.',
            'packages' => [
                ['id' => 401, 'size' => '1kg', 'price' => 575, 'is_default' => true],
                ['id' => 402, 'size' => '500g', 'price' => 300, 'is_default' => false],
                ['id' => 403, 'size' => '5kg', 'price' => 2750, 'is_default' => false],
                ['id' => 404, 'size' => '20kg', 'price' => 10500, 'is_default' => false],
                ['id' => 405, 'size' => '200g', 'price' => 125, 'is_default' => false]
            ]
        ],
        [
            'id' => 5,
            'name' => 'Coriander Powder (Dhania)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_coriander_powder.jpg',
            'rating' => 4.8,
            'review_count' => 84,
            'description' => 'Freshly milled Coriander seeds offering rich aromatic citrusy herbal flavor.',
            'packages' => [
                ['id' => 501, 'size' => '200g', 'price' => 140, 'is_default' => true],
                ['id' => 502, 'size' => '500g', 'price' => 320, 'is_default' => false],
                ['id' => 503, 'size' => '1kg', 'price' => 600, 'is_default' => false],
                ['id' => 504, 'size' => '5kg', 'price' => 2800, 'is_default' => false]
            ]
        ],
        [
            'id' => 6,
            'name' => 'Cumin Powder (Jeera)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_cumin_powder.jpg',
            'rating' => 4.7,
            'review_count' => 72,
            'description' => 'Pure ground Jeera powder made from premium roasted cumin seeds.',
            'packages' => [
                ['id' => 601, 'size' => '200g', 'price' => 210, 'is_default' => true],
                ['id' => 602, 'size' => '500g', 'price' => 490, 'is_default' => false],
                ['id' => 603, 'size' => '1kg', 'price' => 950, 'is_default' => false],
                ['id' => 604, 'size' => '5kg', 'price' => 4500, 'is_default' => false]
            ]
        ],
        [
            'id' => 7,
            'name' => 'Black Pepper Powder',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_black_papper_powder.jpg',
            'rating' => 4.9,
            'review_count' => 110,
            'description' => 'Strong Malabar Black Pepper Powder rich in piperine for bold pungency.',
            'packages' => [
                ['id' => 701, 'size' => '100g', 'price' => 190, 'is_default' => true],
                ['id' => 702, 'size' => '250g', 'price' => 440, 'is_default' => false],
                ['id' => 703, 'size' => '500g', 'price' => 850, 'is_default' => false],
                ['id' => 704, 'size' => '1kg', 'price' => 1650, 'is_default' => false]
            ]
        ],
        [
            'id' => 8,
            'name' => 'Cloves Powder (Laung)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_cloves_powder.jpg',
            'rating' => 4.8,
            'review_count' => 45,
            'description' => 'Freshly ground Cloves powder filled with natural clove oil.',
            'packages' => [
                ['id' => 801, 'size' => '100g', 'price' => 280, 'is_default' => true],
                ['id' => 802, 'size' => '250g', 'price' => 650, 'is_default' => false],
                ['id' => 803, 'size' => '500g', 'price' => 1250, 'is_default' => false]
            ]
        ],
        [
            'id' => 9,
            'name' => 'Fennel Powder (Saunf)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_fennel_powder.jpg',
            'rating' => 4.6,
            'review_count' => 53,
            'description' => 'Sweet and soothing Saunf powder milled from selected green fennel seeds.',
            'packages' => [
                ['id' => 901, 'size' => '200g', 'price' => 130, 'is_default' => true],
                ['id' => 902, 'size' => '500g', 'price' => 290, 'is_default' => false],
                ['id' => 903, 'size' => '1kg', 'price' => 550, 'is_default' => false]
            ]
        ],
        [
            'id' => 10,
            'name' => 'Cinnamon Powder (Dalchini)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_cinnamon_powder.jpg',
            'rating' => 4.9,
            'review_count' => 61,
            'description' => 'Pure Ceylon-grade Cinnamon Powder with sweet warm aroma.',
            'packages' => [
                ['id' => 1001, 'size' => '100g', 'price' => 220, 'is_default' => true],
                ['id' => 1002, 'size' => '250g', 'price' => 510, 'is_default' => false],
                ['id' => 1003, 'size' => '500g', 'price' => 980, 'is_default' => false]
            ]
        ],
        [
            'id' => 11,
            'name' => 'Nutmeg Powder (Jaiphal)',
            'category' => 'Ground Spices',
            'image' => 'images/sarvamta_nutmeg_powder.jpg',
            'rating' => 4.8,
            'review_count' => 38,
            'description' => 'Aromatic Jaiphal powder ideal for rich gravies and spice formulations.',
            'packages' => [
                ['id' => 1101, 'size' => '50g', 'price' => 250, 'is_default' => true],
                ['id' => 1102, 'size' => '100g', 'price' => 480, 'is_default' => false],
                ['id' => 1103, 'size' => '250g', 'price' => 1150, 'is_default' => false]
            ]
        ],
        [
            'id' => 12,
            'name' => 'Makhana (Fox Nuts) Premium Grade',
            'category' => 'Makhana',
            'image' => 'images/sarvamta_makhana.jpg',
            'rating' => 4.9,
            'review_count' => 128,
            'description' => 'Handpicked crispy Phool Makhana rich in protein and calcium.',
            'packages' => [
                ['id' => 1201, 'size' => '250g', 'price' => 380, 'is_default' => true],
                ['id' => 1202, 'size' => '500g', 'price' => 720, 'is_default' => false],
                ['id' => 1203, 'size' => '1kg', 'price' => 1390, 'is_default' => false]
            ]
        ],
        [
            'id' => 13,
            'name' => 'Whole Cumin Seeds (Jeera)',
            'category' => 'Whole Spices',
            'image' => 'images/Whole-Spices.webp',
            'rating' => 4.7,
            'review_count' => 89,
            'description' => 'Sun-dried premium whole cumin seeds with intense essential oil content.',
            'packages' => [
                ['id' => 1301, 'size' => '250g', 'price' => 190, 'is_default' => true],
                ['id' => 1302, 'size' => '500g', 'price' => 360, 'is_default' => false],
                ['id' => 1303, 'size' => '1kg', 'price' => 690, 'is_default' => false]
            ]
        ],
        [
            'id' => 14,
            'name' => 'Whole Green Cardamom (Elaichi)',
            'category' => 'Whole Spices',
            'image' => 'images/Whole-Spices.webp',
            'rating' => 5.0,
            'review_count' => 104,
            'description' => 'Plump 8mm bold green cardamom pods with intense aroma.',
            'packages' => [
                ['id' => 1401, 'size' => '100g', 'price' => 450, 'is_default' => true],
                ['id' => 1402, 'size' => '250g', 'price' => 1050, 'is_default' => false],
                ['id' => 1403, 'size' => '500g', 'price' => 2000, 'is_default' => false]
            ]
        ],
        [
            'id' => 15,
            'name' => 'Royal Garam Masala Blend',
            'category' => 'Masala Mix',
            'image' => 'images/Blended-Spices.jpeg',
            'rating' => 4.8,
            'review_count' => 77,
            'description' => 'Masterfully blended 15 whole spice mix for authentic royal aroma.',
            'packages' => [
                ['id' => 1501, 'size' => '100g', 'price' => 160, 'is_default' => true],
                ['id' => 1502, 'size' => '200g', 'price' => 295, 'is_default' => false],
                ['id' => 1503, 'size' => '500g', 'price' => 680, 'is_default' => false]
            ]
        ]
    ];

    // Filter fallback data according to parameters
    $products = array_filter($fallbackProducts, function($p) use ($category, $search, $size_filter) {
        if (!empty($category) && strtolower($category) !== 'all' && strtolower($p['category']) !== strtolower($category)) {
            return false;
        }
        if (!empty($search)) {
            $q = strtolower($search);
            if (strpos(strtolower($p['name']), $q) === false && strpos(strtolower($p['description']), $q) === false) {
                return false;
            }
        }
        if (!empty($size_filter)) {
            $hasSize = false;
            foreach ($p['packages'] as $pkg) {
                if (strtolower($pkg['size']) === strtolower($size_filter)) {
                    $hasSize = true;
                    break;
                }
            }
            if (!$hasSize) return false;
        }
        return true;
    });

    $products = array_values($products);
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
