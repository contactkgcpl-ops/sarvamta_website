<?php
/**
 * SARVAMTA SPICES - ADMIN PANEL DASHBOARD
 * Multi-File Image Upload -> Saved in `uploads/` as `product_name_1.jpg`, `product_name_2.jpg`
 */

session_start();

// Simple Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../api/db.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Determine Active Tab
$activeTab = 'dashboard';
if (isset($_REQUEST['tab']) && in_array($_REQUEST['tab'], ['dashboard', 'products', 'inquiries'])) {
    $activeTab = $_REQUEST['tab'];
} elseif (isset($_POST['save_product']) || (isset($_GET['action']) && in_array($_GET['action'], ['delete', 'toggle_favorite']))) {
    $activeTab = 'products';
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete_inquiry') {
    $activeTab = 'inquiries';
}

// Handle Product Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $pId = (int)$_GET['id'];
    $activeTab = 'products';
    if ($db) {
        try {
            $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $pId]);
            $message = "Product deleted successfully from MySQL database!";
        } catch (PDOException $e) {
            $error = "Error deleting product: " . $e->getMessage();
        }
    }
}

// Handle Favorite Toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle_favorite' && isset($_GET['id'])) {
    $pId = (int)$_GET['id'];
    $activeTab = 'products';
    $newFav = isset($_GET['fav']) ? (int)$_GET['fav'] : 1;
    if ($db) {
        try {
            $stmt = $db->prepare("UPDATE products SET is_favorite = :fav WHERE id = :id");
            $stmt->execute([':fav' => $newFav, ':id' => $pId]);
            $message = "Product favorite status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating favorite status: " . $e->getMessage();
        }
    }
}

// Handle Inquiry Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_inquiry' && isset($_GET['id'])) {
    $inqId = (int)$_GET['id'];
    $activeTab = 'inquiries';
    if ($db) {
        try {
            $stmt = $db->prepare("DELETE FROM inquiries WHERE id = :id");
            $stmt->execute([':id' => $inqId]);
            $message = "Inquiry deleted successfully from MySQL database!";
        } catch (PDOException $e) {
            $error = "Error deleting inquiry: " . $e->getMessage();
        }
    }
}

// Handle Product Creation / Edit (Uploads & formats image names as product_name_1.ext, product_name_2.ext)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $activeTab = 'products';
    $pId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $imagePath = isset($_POST['image']) ? trim($_POST['image']) : '';
    $description = trim($_POST['description']);

    // Generate product slug for clean filenames (lowercase, spaces to underscores)
    $productSlug = strtolower(trim($name));
    $productSlug = preg_replace('/[^a-z0-9]+/', '_', $productSlug);
    $productSlug = trim($productSlug, '_');
    if (empty($productSlug)) {
        $productSlug = 'product';
    }

    // Multi-File Upload Handler with timestamp (Supports 1 to 5+ images per product)
    $uploadedPaths = [];
    $uploadFileDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadFileDir)) {
        mkdir($uploadFileDir, 0777, true);
    }

    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $fileCount = count($_FILES['product_images']['name']);
        $timestamp = time();
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['product_images']['tmp_name'][$i];
                $origName = basename($_FILES['product_images']['name'][$i]);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (empty($ext)) {
                    $ext = 'jpg';
                }

                // Custom filename format: product_name_timestamp_1.jpg, product_name_timestamp_2.jpg
                $seq = $i + 1;
                $cleanName = $productSlug . '_' . $timestamp . '_' . $seq . '.' . $ext;
                $dest_path = $uploadFileDir . $cleanName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $uploadedPaths[] = 'uploads/' . $cleanName;
                }
            }
        }
    }

    if (!empty($uploadedPaths)) {
        $imagePath = $uploadedPaths[0]; // Primary main image saved to MySQL DB
    } else if (empty($imagePath)) {
        $imagePath = 'images/sarvamta_chilli_powder.jpg';
    }

    $sizes = isset($_POST['pkg_size']) ? $_POST['pkg_size'] : [];
    $actualPrices = isset($_POST['pkg_actual_price']) ? $_POST['pkg_actual_price'] : [];
    $prices = isset($_POST['pkg_price']) ? $_POST['pkg_price'] : [];
    $isFavorite = (isset($_POST['is_favorite']) && (int)$_POST['is_favorite'] === 1) ? 1 : 0;

    if (!empty($name) && !empty($category) && $db) {
        try {
            if ($pId > 0) {
                // Update Product
                if (!empty($uploadedPaths)) {
                    $stmt = $db->prepare("UPDATE products SET name = :name, category = :category, image = :image, images = :images, description = :description, is_favorite = :is_fav WHERE id = :id");
                    $stmt->execute([
                        ':name' => $name,
                        ':category' => $category,
                        ':image' => $imagePath,
                        ':images' => json_encode($uploadedPaths),
                        ':description' => $description,
                        ':is_fav' => $isFavorite,
                        ':id' => $pId
                    ]);

                    // Replace product_images
                    $stmtImgDel = $db->prepare("DELETE FROM product_images WHERE product_id = :id");
                    $stmtImgDel->execute([':id' => $pId]);

                    $stmtImg = $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_primary) VALUES (:pid, :url, :sort, :is_prim)");
                    foreach ($uploadedPaths as $idx => $path) {
                        $stmtImg->execute([
                            ':pid' => $pId,
                            ':url' => $path,
                            ':sort' => $idx + 1,
                            ':is_prim' => ($idx === 0 ? 1 : 0)
                        ]);
                    }
                } else {
                    $stmt = $db->prepare("UPDATE products SET name = :name, category = :category, description = :description, is_favorite = :is_fav WHERE id = :id");
                    $stmt->execute([
                        ':name' => $name,
                        ':category' => $category,
                        ':description' => $description,
                        ':is_fav' => $isFavorite,
                        ':id' => $pId
                    ]);
                }

                // Clear existing variants and insert new ones
                $stmtVarDel = $db->prepare("DELETE FROM product_variants WHERE product_id = :id");
                $stmtVarDel->execute([':id' => $pId]);
            } else {
                // Insert New Product
                $imagesJson = !empty($uploadedPaths) ? json_encode($uploadedPaths) : json_encode([$imagePath]);
                $stmt = $db->prepare("INSERT INTO products (name, category, image, images, description, is_favorite) VALUES (:name, :category, :image, :images, :description, :is_fav)");
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':image' => $imagePath,
                    ':images' => $imagesJson,
                    ':description' => $description,
                    ':is_fav' => $isFavorite
                ]);
                $pId = $db->lastInsertId();

                $imagesToInsert = !empty($uploadedPaths) ? $uploadedPaths : [$imagePath];
                $stmtImg = $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_primary) VALUES (:pid, :url, :sort, :is_prim)");
                foreach ($imagesToInsert as $idx => $path) {
                    $stmtImg->execute([
                        ':pid' => $pId,
                        ':url' => $path,
                        ':sort' => $idx + 1,
                        ':is_prim' => ($idx === 0 ? 1 : 0)
                    ]);
                }
            }

            // Insert Variants with Actual Price and Offer Price
            $stmtVar = $db->prepare("INSERT INTO product_variants (product_id, package_size, actual_price, price, is_default, sort_order) VALUES (:pid, :size, :actual_price, :price, :is_default, :sort)");
            for ($i = 0; $i < count($sizes); $i++) {
                $sz = trim($sizes[$i]);
                $pr = (float)$prices[$i];
                $actPr = (isset($actualPrices[$i]) && (float)$actualPrices[$i] > 0) ? (float)$actualPrices[$i] : $pr;
                if (!empty($sz) && $pr > 0) {
                    $stmtVar->execute([
                        ':pid' => $pId,
                        ':size' => $sz,
                        ':actual_price' => $actPr,
                        ':price' => $pr,
                        ':is_default' => ($i === 0) ? 1 : 0,
                        ':sort' => $i + 1
                    ]);
                }
            }

            $imgCountMsg = !empty($uploadedPaths) ? count($uploadedPaths) : 1;
            $message = "Product saved successfully with {$imgCountMsg} photo(s) in MySQL database!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Please enter Product Name and Category.";
    }
}

// Fetch all products for display
$productsList = [];
$totalProducts = 0;
$totalVariants = 0;
$inquiriesList = [];
$totalInquiries = 0;

if ($db) {
    try {
        $stmt = $db->query("SELECT p.*, GROUP_CONCAT(CONCAT(pv.package_size, '|', IFNULL(pv.actual_price, pv.price), '|', pv.price) ORDER BY pv.sort_order ASC SEPARATOR ';;') as variants_summary 
                            FROM products p 
                            LEFT JOIN product_variants pv ON p.id = pv.product_id 
                            GROUP BY p.id 
                            ORDER BY p.id DESC");
        $productsList = $stmt->fetchAll();
        $totalProducts = count($productsList);

        // Fetch product images map
        $allImagesMap = [];
        try {
            $stmtImgs = $db->query("SELECT product_id, image_url FROM product_images ORDER BY sort_order ASC, id ASC");
            if ($stmtImgs) {
                while ($imgRow = $stmtImgs->fetch()) {
                    $allImagesMap[$imgRow['product_id']][] = $imgRow['image_url'];
                }
            }
        } catch (PDOException $e) {}

        foreach ($productsList as &$pRef) {
            $prodId = $pRef['id'];
            if (!empty($allImagesMap[$prodId])) {
                $pRef['all_images'] = $allImagesMap[$prodId];
            } else if (!empty($pRef['images'])) {
                $decoded = json_decode($pRef['images'], true);
                $pRef['all_images'] = is_array($decoded) ? $decoded : [$pRef['image']];
            } else {
                $pRef['all_images'] = !empty($pRef['image']) ? [$pRef['image']] : [];
            }
        }
        unset($pRef);

        $stmtV = $db->query("SELECT COUNT(*) FROM product_variants");
        $totalVariants = $stmtV->fetchColumn();

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

        $stmtInq = $db->query("SELECT * FROM inquiries ORDER BY id DESC");
        $inquiriesList = $stmtInq->fetchAll();
        $totalInquiries = count($inquiriesList);
    } catch (PDOException $e) {
        $error = "Failed to query database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard | SARVAMTA Spices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" href="../images/logo.png">
  
  <!-- GOOGLE FONTS & ICONS -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
    body { background-color: #faf9f6; color: #1e293b; display: flex; min-height: 100vh; }

    /* LEFT SIDEBAR */
    .admin-sidebar {
      width: 260px;
      background-color: #260802;
      color: #dfc8c0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      z-index: 100;
      border-right: 1px solid #451509;
    }

    .sidebar-header {
      padding: 18px 20px;
      border-bottom: 1px solid #451509;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #180401;
    }

    .sidebar-logo {
      height: 48px;
      max-width: 100%;
      object-fit: contain;
      background: white;
      padding: 4px 14px;
      border-radius: 6px;
    }

    .sidebar-nav {
      padding: 20px 12px;
      flex: 1;
      overflow-y: auto;
    }

    .nav-section-title {
      font-size: 0.72rem;
      font-weight: 800;
      color: #a88b83;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 12px 12px 6px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 16px;
      color: #dfc8c0;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.92rem;
      border-radius: 8px;
      margin-bottom: 4px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .nav-link i { font-size: 1.05rem; width: 20px; text-align: center; }

    .nav-link:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.08);
    }

    .nav-link.active {
      color: #ffffff;
      background-color: #180401;
      border-left: 3.5px solid #d48806;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }

    .nav-badge {
      margin-left: auto;
      background: linear-gradient(135deg, #d48806, #ff9800);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 12px;
    }

    .sidebar-user {
      padding: 16px 20px;
      border-top: 1px solid #451509;
      background-color: #180401;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, #d48806, #ff9800);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 1rem;
    }

    .user-name { font-size: 0.88rem; font-weight: 700; color: #ffffff; }
    .user-role { font-size: 0.75rem; color: #dfc8c0; }

    /* MAIN CONTENT AREA */
    .admin-main {
      margin-left: 260px;
      flex: 1;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background-color: #faf9f6;
    }

    .top-navbar {
      background: #ffffff;
      height: 70px;
      padding: 0 32px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #e2e8f0;
      position: sticky;
      top: 0;
      z-index: 90;
    }

    .page-title {
      font-size: 1.25rem;
      font-weight: 800;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .navbar-actions {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .btn-view-shop {
      color: #334155;
      text-decoration: none;
      font-size: 0.88rem;
      font-weight: 700;
      padding: 8px 16px;
      border-radius: 6px;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      transition: all 0.2s;
    }
    .btn-view-shop:hover { background: #e2e8f0; color: #0f172a; }

    .btn-add-product {
      background: linear-gradient(135deg, #d48806, #ff9800);
      color: #ffffff;
      border: none;
      padding: 10px 22px;
      border-radius: 6px;
      font-weight: 800;
      font-size: 0.88rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.25s ease;
      box-shadow: 0 4px 12px rgba(212, 136, 6, 0.3);
    }
    .btn-add-product:hover {
      background: linear-gradient(135deg, #b87300, #f57c00);
      transform: translateY(-1px);
    }

    .btn-logout-head {
      background: #dc2626;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 700;
      padding: 8px 16px;
      border-radius: 20px;
      transition: all 0.2s;
    }
    .btn-logout-head:hover { background: #b91c1c; }

    .content-body { padding: 32px; flex: 1; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ALERTS */
    .alert { padding: 14px 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* DASHBOARD WIDGETS */
    .overview-header { margin-bottom: 24px; }
    .overview-sub { font-size: 0.82rem; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 0.5px; }
    .overview-title { font-size: 1.6rem; font-weight: 800; color: #1e293b; }

    .dash-stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 32px;
    }

    .stat-card-widget {
      background: #ffffff;
      border-radius: 12px;
      padding: 24px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .stat-card-info .lbl { font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .stat-card-info .val { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 4px 0; }
    .stat-card-info .sub { font-size: 0.82rem; font-weight: 600; color: #10b981; }

    .stat-card-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      background: #fff7ed;
      color: #d48806;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .dash-widgets-row {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
    }

    .dash-panel {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      padding: 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }

    .panel-head {
      font-size: 1.05rem;
      font-weight: 800;
      color: #1e293b;
      margin-bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* PRODUCTS TABLE CONTAINER */
    .table-container-card {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 12px rgba(0,0,0,0.03);
      overflow: visible;
    }

    .table-toolbar {
      padding: 20px 24px;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
      gap: 16px;
    }

    .search-input-box {
      position: relative;
      width: 340px;
    }
    .search-input-box input {
      width: 100%;
      padding: 10px 14px 10px 40px;
      border: 1.5px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .search-input-box input:focus { border-color: #d48806; }
    .search-input-box i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }

    /* INQUIRY GRID FILTER BAR STYLES */
    .inquiry-filter-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      width: 100%;
    }

    .filter-item-box {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #f8fafc;
      padding: 6px 12px;
      border: 1.5px solid #cbd5e1;
      border-radius: 6px;
      transition: all 0.2s ease;
    }
    .filter-item-box:focus-within {
      border-color: #d48806;
      background: #ffffff;
      box-shadow: 0 0 0 2px rgba(212, 136, 6, 0.12);
    }

    .filter-item-label {
      font-size: 0.78rem;
      font-weight: 700;
      color: #475569;
      display: flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
      user-select: none;
    }

    .filter-item-label i {
      color: #d48806;
      font-size: 0.85rem;
    }

    .filter-date-input,
    .filter-select-input {
      border: none;
      background: transparent;
      font-size: 0.85rem;
      color: #1e293b;
      font-weight: 600;
      outline: none;
      font-family: inherit;
      cursor: pointer;
    }

    .btn-reset-filters {
      background: #ffffff;
      border: 1.5px solid #cbd5e1;
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 0.82rem;
      font-weight: 700;
      color: #64748b;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-reset-filters:hover {
      background: #fee2e2;
      border-color: #fca5a5;
      color: #b91c1c;
    }

    .inquiry-results-counter {
      font-size: 0.82rem;
      font-weight: 700;
      color: #64748b;
      margin-left: auto;
      white-space: nowrap;
    }

    .products-table-view {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    .products-table-view th {
      background: #f8fafc;
      padding: 14px 20px;
      font-size: 0.78rem;
      font-weight: 800;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #e2e8f0;
    }

    .products-table-view td {
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.92rem;
      vertical-align: middle;
      color: #334155;
      position: relative;
    }

    .products-table-view tr:hover { background-color: #faf9f6; }

    .tbl-prod-title { font-weight: 700; color: #1e293b; font-size: 0.95rem; }

    .cat-badge-pill {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #ffedd5;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      display: inline-block;
    }

    .btn-view-link {
      background: #ffffff;
      border: 1.5px solid #d48806;
      color: #d48806;
      padding: 7px 16px;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn-view-link:hover {
      background: linear-gradient(135deg, #d48806, #ff9800);
      color: #ffffff;
      box-shadow: 0 4px 10px rgba(212, 136, 6, 0.25);
    }

    .action-dropdown { position: relative; display: inline-block; }

    .btn-three-dots {
      width: 36px;
      height: 36px;
      border-radius: 6px;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      color: #475569;
      font-size: 1.1rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    .btn-three-dots:hover { background: #e2e8f0; color: #0f172a; }

    .dropdown-menu-box {
      position: absolute;
      right: 0;
      top: 42px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
      width: 140px;
      z-index: 500;
      display: none;
      overflow: hidden;
    }
    .dropdown-menu-box.show { display: block; }

    .dropdown-item-btn {
      width: 100%;
      padding: 10px 14px;
      background: none;
      border: none;
      text-align: left;
      font-size: 0.88rem;
      font-weight: 700;
      color: #334155;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: background 0.15s;
      text-decoration: none;
    }
    .dropdown-item-btn:hover { background: #f8fafc; color: #d48806; }
    .dropdown-item-btn.btn-del-opt:hover { background: #fee2e2; color: #dc2626; }

    /* MODAL POPUPS */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(38, 8, 2, 0.75);
      backdrop-filter: blur(4px);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
      padding: 20px;
    }
    .modal-overlay.active { opacity: 1; visibility: visible; }

    .modal-box {
      background: #ffffff;
      border-radius: 12px;
      max-width: 650px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 28px;
      position: relative;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    }
    .modal-close-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #f1f5f9;
      border: none;
      font-size: 1.2rem;
      color: #475569;
      cursor: pointer;
    }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px; color: #334155; }
    .form-input {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.92rem;
      outline: none;
    }
    .form-input:focus { border-color: #d48806; }

    .file-upload-box {
      border: 2px dashed #cbd5e1;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      background: #f8fafc;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .file-upload-box:hover {
      border-color: #d48806;
      background: #fff7ed;
    }

    .img-preview-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 14px;
    }

    .img-preview-item {
      position: relative;
      width: 80px;
      height: 80px;
      border-radius: 8px;
      border: 1.5px solid #d48806;
      overflow: hidden;
      background: #ffffff;
      padding: 4px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .img-preview-item img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .pkg-builder { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-top: 6px; }
    .pkg-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
    .pkg-row input { flex: 1; }
    .btn-row-del { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; width: 34px; height: 34px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
    .btn-row-add { background: #ffffff; border: 1.5px dashed #cbd5e1; color: #d48806; width: 100%; padding: 8px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

    .btn-modal-save {
      width: 100%;
      background: linear-gradient(135deg, #d48806, #ff9800);
      color: #ffffff;
      border: none;
      padding: 12px;
      border-radius: 6px;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      margin-top: 14px;
      box-shadow: 0 4px 15px rgba(212, 136, 6, 0.35);
    }

    .popup-prices-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }
    .popup-prices-table th {
      background: #f8fafc;
      padding: 12px 16px;
      font-size: 0.8rem;
      font-weight: 800;
      color: #475569;
      text-transform: uppercase;
      border-bottom: 2px solid #e2e8f0;
      text-align: left;
    }
    .popup-prices-table td {
      padding: 12px 16px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.92rem;
      color: #1e293b;
    }

    .images-grid-preview {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 16px;
      margin-top: 16px;
    }

    .inq-tab-btn {
      padding: 10px 20px;
      background: #ffffff;
      border: 1.5px solid #cbd5e1;
      border-radius: 0px !important;
      font-weight: 700;
      font-size: 0.9rem;
      color: #475569;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .inq-tab-btn:hover {
      background: #f8fafc;
      color: #1e293b;
      border-color: #94a3b8;
    }
    .inq-tab-btn.active {
      background: #260802;
      color: #ffffff;
      border-color: #260802;
    }
    .inq-tab-btn .badge {
      background: #f1f5f9;
      color: #475569;
      padding: 2px 8px;
      border-radius: 0px !important;
      font-size: 0.78rem;
      font-weight: 800;
    }
    .inq-tab-btn.active .badge {
      background: #d48806;
      color: #ffffff;
    }
    .img-card-preview {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 8px;
      background: #ffffff;
      text-align: center;
    }
    .img-card-preview img {
      max-width: 100%;
      height: 120px;
      object-fit: contain;
      border-radius: 4px;
    }
  </style>
</head>
<body>

  <!-- LEFT DARK SPICE BROWN SIDEBAR MENU -->
  <aside class="admin-sidebar">
    <div>
      <div class="sidebar-header">
        <img src="../images/logo.png" alt="SARVAMTA Spices Logo" class="sidebar-logo">
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section-title">GENERAL</div>
        
        <a class="nav-link <?php echo ($activeTab === 'dashboard' ? 'active' : ''); ?>" id="nav-dash" onclick="switchTab('dashboard')">
          <i class="fa-solid fa-border-all"></i>
          <span>Dashboard</span>
        </a>

        <a class="nav-link <?php echo ($activeTab === 'products' ? 'active' : ''); ?>" id="nav-prod" onclick="switchTab('products')">
          <i class="fa-solid fa-box-archive"></i>
          <span>Products</span>
        </a>

        <a class="nav-link <?php echo ($activeTab === 'inquiries' ? 'active' : ''); ?>" id="nav-inq" onclick="switchTab('inquiries')">
          <i class="fa-solid fa-envelope-open-text"></i>
          <span>Inquiries</span>
        </a>

        <div class="nav-section-title" style="margin-top:16px;">SHORTCUTS</div>

        <a href="../shop.html" target="_blank" class="nav-link">
          <i class="fa-solid fa-store"></i>
          <span>View Live Shop</span>
        </a>

        <a href="logout.php" class="nav-link" style="color:#fca5a5;">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </nav>
    </div>

    <!-- USER AVATAR FOOTER -->
    <div class="sidebar-user">
      <div class="user-avatar">A</div>
      <div class="user-info">
        <span class="user-name">Sarvamta Admin</span>
        <span class="user-role">Administrator</span>
      </div>
    </div>
  </aside>

  <!-- MAIN CONTENT AREA -->
  <main class="admin-main">
    
    <!-- TOP NAVBAR -->
    <header class="top-navbar">
      <div class="page-title" id="current-page-title">
        <?php if ($activeTab === 'products'): ?>
          <i class="fa-solid fa-boxes-stacked" style="color:#d48806;"></i> Product Catalog
        <?php elseif ($activeTab === 'inquiries'): ?>
          <i class="fa-solid fa-envelope-open-text" style="color:#d48806;"></i> Product Inquiries
        <?php else: ?>
          <i class="fa-solid fa-chart-line" style="color:#d48806;"></i> Dashboard Overview
        <?php endif; ?>
      </div>

      <div class="navbar-actions">
        <a href="../shop.html" target="_blank" class="btn-view-shop"><i class="fa-solid fa-globe"></i> View Shop</a>
        <a href="logout.php" class="btn-logout-head"><i class="fa-solid fa-power-off"></i> Logout</a>
      </div>
    </header>

    <!-- CONTENT BODY -->
    <div class="content-body">
      
      <!-- ALERTS -->
      <?php if (!empty($message)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- TAB 1: DASHBOARD OVERVIEW -->
      <div id="tab-dashboard" class="tab-content <?php echo ($activeTab === 'dashboard' ? 'active' : ''); ?>">
        <div class="overview-header">
          <div class="overview-sub">Overview</div>
          <h1 class="overview-title">Dashboard</h1>
        </div>

        <!-- STATS WIDGET CARDS -->
        <div class="dash-stats-grid">
          <div class="stat-card-widget">
            <div class="stat-card-info">
              <div class="lbl">Total Products</div>
              <div class="val"><?php echo $totalProducts; ?></div>
              <div class="sub"><i class="fa-solid fa-arrow-trend-up"></i> Active in Database</div>
            </div>
            <div class="stat-card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
          </div>

          <div class="stat-card-widget">
            <div class="stat-card-info">
              <div class="lbl">Total Packaging Sizes</div>
              <div class="val"><?php echo $totalVariants; ?></div>
              <div class="sub" style="color:#d48806;"><i class="fa-solid fa-tags"></i> Variants Configured</div>
            </div>
            <div class="stat-card-icon" style="color:#d48806; background:#fff7ed;"><i class="fa-solid fa-layer-group"></i></div>
          </div>

          <div class="stat-card-widget">
            <div class="stat-card-info">
              <div class="lbl">Database</div>
              <div class="val" style="font-size:1.4rem;">MySQL Connected</div>
              <div class="sub" style="color:#059669;"><i class="fa-solid fa-database"></i> Database: sarvamta_shop</div>
            </div>
            <div class="stat-card-icon" style="color:#059669; background:#ecfdf5;"><i class="fa-solid fa-server"></i></div>
          </div>
        </div>

        <!-- DASHBOARD PANELS -->
        <div class="dash-widgets-row">
          <div class="dash-panel">
            <div class="panel-head">
              <span><i class="fa-solid fa-clock-rotate-left"></i> Recently Added Products</span>
              <button onclick="switchTab('products')" style="background:none; border:none; color:#d48806; font-size:0.85rem; font-weight:700; cursor:pointer;">View All Products &rarr;</button>
            </div>
            <div>
              <?php if (empty($productsList)): ?>
                <div style="padding:20px; color:#64748b;">No products in database.</div>
              <?php else: ?>
                <?php foreach (array_slice($productsList, 0, 5) as $p): ?>
                  <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:12px;">
                      <img src="../<?php echo htmlspecialchars($p['image']); ?>" style="width:38px; height:38px; object-fit:contain; border-radius:6px; background:#fff; border:1px solid #e2e8f0; padding:2px;" onerror="this.src='../images/logo.png'">
                      <div>
                        <div style="font-weight:700; font-size:0.9rem; color:#1e293b;"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="font-size:0.78rem; color:#64748b;"><?php echo htmlspecialchars($p['category']); ?></div>
                      </div>
                    </div>
                    <div style="font-weight:800; color:#d48806; font-size:0.88rem;">
                      <?php 
                        if (!empty($p['variants_summary'])) {
                          $parts = explode(', ', $p['variants_summary']);
                          echo htmlspecialchars($parts[0]);
                        }
                      ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="dash-panel">
            <div class="panel-head">
              <span><i class="fa-solid fa-chart-pie"></i> Category Breakdown</span>
            </div>
            <div style="display:flex; flex-direction:column; gap:14px; margin-top:10px;">
              <div>
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; font-weight:700; margin-bottom:6px;">
                  <span>Ground Spices</span>
                  <span style="color:#d48806;">60%</span>
                </div>
                <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                  <div style="background:#d48806; width:60%; height:100%;"></div>
                </div>
              </div>

              <div>
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; font-weight:700; margin-bottom:6px;">
                  <span>Masala Mixes</span>
                  <span style="color:#c2410c;">20%</span>
                </div>
                <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                  <div style="background:#c2410c; width:20%; height:100%;"></div>
                </div>
              </div>

              <div>
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; font-weight:700; margin-bottom:6px;">
                  <span>Makhana & Dry Fruits</span>
                  <span style="color:#059669;">20%</span>
                </div>
                <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                  <div style="background:#059669; width:20%; height:100%;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- TAB 2: PRODUCTS TABLE VIEW -->
      <div id="tab-products" class="tab-content <?php echo ($activeTab === 'products' ? 'active' : ''); ?>">
        
        <div class="table-container-card">
          <!-- TOOLBAR & SEARCH -->
          <div class="table-toolbar">
            <div class="search-input-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="prod-search" placeholder="Search product name or category..." onkeyup="filterProductsTable()">
            </div>

            <button class="btn-add-product" onclick="openAddModal()">
              <i class="fa-solid fa-plus"></i> Add Product
            </button>
          </div>

          <!-- PRODUCTS TABLE VIEW -->
          <table class="products-table-view" id="main-products-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Images</th>
                <th>Product Name</th>
                <th>Category</th>
                <th style="text-align:center;"><i class="fa-solid fa-star" style="color:#d48806;"></i> Favorite</th>
                <th>Packaging Sizes & Prices</th>
                <th style="text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($productsList)): ?>
                <tr>
                  <td colspan="7" style="text-align:center; padding:40px; color:#64748b;">
                    No products currently in MySQL database. Click "+ Add Product" to add your first product!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($productsList as $p): ?>
                  <tr>
                    <td><strong>#<?php echo $p['id']; ?></strong></td>
                    <td>
                      <button type="button" class="btn-view-link" onclick='openPhpImagesModal(<?php echo json_encode($p); ?>)'>
                        <i class="fa-regular fa-image"></i> View Images (<?php echo count($p['all_images']); ?>)
                      </button>
                    </td>
                    <td><div class="tbl-prod-title"><?php echo htmlspecialchars($p['name']); ?></div></td>
                    <td><span class="cat-badge-pill"><?php echo htmlspecialchars($p['category']); ?></span></td>
                    <td style="text-align:center;">
                      <a href="index.php?action=toggle_favorite&id=<?php echo $p['id']; ?>&fav=<?php echo (!empty($p['is_favorite']) ? 0 : 1); ?>&tab=products" 
                         title="<?php echo (!empty($p['is_favorite']) ? 'Click to remove from favorites' : 'Click to mark as favorite for homepage'); ?>"
                         style="font-size:1.15rem; color:<?php echo (!empty($p['is_favorite']) ? '#d97706' : '#94a3b8'); ?>; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:6px; background:<?php echo (!empty($p['is_favorite']) ? '#fef3c7' : '#f8fafc'); ?>; border:1px solid <?php echo (!empty($p['is_favorite']) ? '#fcd34d' : '#e2e8f0'); ?>; transition:all 0.2s ease;">
                        <i class="<?php echo (!empty($p['is_favorite']) ? 'fa-solid fa-star' : 'fa-regular fa-star'); ?>"></i>
                      </a>
                    </td>
                    <td>
                      <button type="button" class="btn-view-link" onclick='openPhpPricesModal(<?php echo json_encode($p); ?>)'>
                        <i class="fa-solid fa-table-list"></i> View Prices
                      </button>
                    </td>
                    <td style="text-align:center;">
                      <!-- THREE DOTS ACTION DROPDOWN MENU -->
                      <div class="action-dropdown">
                        <button type="button" class="btn-three-dots" onclick="toggleActionMenu(event, <?php echo $p['id']; ?>)">
                          <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="dropdown-menu-box" id="action-menu-<?php echo $p['id']; ?>">
                          <button type="button" class="dropdown-item-btn" onclick='editProduct(<?php echo json_encode($p); ?>)'>
                            <i class="fa-solid fa-pen" style="color:#d48806;"></i> Edit
                          </button>
                          <a href="index.php?action=delete&id=<?php echo $p['id']; ?>&tab=products" class="dropdown-item-btn btn-del-opt" onclick="return confirm('Are you sure you want to delete this product?')">
                            <i class="fa-solid fa-trash" style="color:#dc2626;"></i> Delete
                          </a>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- TAB 3: PRODUCT INQUIRIES -->
      <div id="tab-inquiries" class="tab-content <?php echo ($activeTab === 'inquiries' ? 'active' : ''); ?>">
        <div class="inquiry-filter-tabs" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
          <button type="button" class="inq-tab-btn active" id="inq-filter-all" onclick="setInquiryStatusFilter('All')">
            All <span class="badge" id="count-inq-all">0</span>
          </button>
          <button type="button" class="inq-tab-btn" id="inq-filter-pending" onclick="setInquiryStatusFilter('Pending')">
            Pending <span class="badge" id="count-inq-pending">0</span>
          </button>
          <button type="button" class="inq-tab-btn" id="inq-filter-ongoing" onclick="setInquiryStatusFilter('Ongoing')">
            Ongoing <span class="badge" id="count-inq-ongoing">0</span>
          </button>
          <button type="button" class="inq-tab-btn" id="inq-filter-completed" onclick="setInquiryStatusFilter('Completed')">
            Completed <span class="badge" id="count-inq-completed">0</span>
          </button>
        </div>

        <div class="table-container-card">
          <div class="table-toolbar">
            <div class="inquiry-filter-bar">
              <div class="search-input-box" style="flex:1; min-width:220px; max-width:280px;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="inq-search" oninput="renderInquiriesTable()" placeholder="Search name, phone, product...">
              </div>

              <div class="filter-item-box">
                <span class="filter-item-label"><i class="fa-solid fa-filter"></i> Status:</span>
                <select id="inq-status-filter" onchange="onInquiryStatusFilterChange(this.value)" class="filter-select-input">
                  <option value="All">All Statuses</option>
                  <option value="Pending">Pending</option>
                  <option value="Ongoing">Ongoing</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>

              <div class="filter-item-box">
                <span class="filter-item-label"><i class="fa-regular fa-calendar"></i> From:</span>
                <input type="date" id="inq-from-date" onchange="renderInquiriesTable()" class="filter-date-input" title="Filter From Date">
              </div>

              <div class="filter-item-box">
                <span class="filter-item-label"><i class="fa-regular fa-calendar-check"></i> To:</span>
                <input type="date" id="inq-to-date" onchange="renderInquiriesTable()" class="filter-date-input" title="Filter To Date">
              </div>

              <button type="button" class="btn-reset-filters" onclick="resetInquiryFilters()" title="Reset All Filters">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button>

              <div class="inquiry-results-counter" id="inquiry-results-counter">
                Showing: <strong>0</strong> inquiries
              </div>
            </div>
          </div>

          <table class="products-table-view" id="main-inquiries-table">
            <thead>
              <tr>
                <th>#ID</th>
                <th>Date</th>
                <th>Customer Info</th>
                <th>Product Name</th>
                <th>Package Size</th>
                <th>Quantity</th>
                <th>Status</th>
                <th style="text-align:center;">Action</th>
              </tr>
            </thead>
            <tbody id="inquiries-table-body">
              <!-- Dynamically rendered via JS -->
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <!-- POPUP 1: ADD / EDIT PRODUCT MODAL FORM -->
  <div id="product-modal" class="modal-overlay">
    <div class="modal-box">
      <button class="modal-close-btn" onclick="closeModal('product-modal')">&times;</button>
      <h2 id="modal-heading" style="font-size:1.25rem; font-weight:800; color:#260802; margin-bottom:20px;">Add New Product</h2>

      <form method="POST" action="index.php?tab=products" enctype="multipart/form-data" id="add-product-form">
        <input type="hidden" name="tab" id="p-form-tab" value="products">
        <input type="hidden" name="save_product" value="1">
        <input type="hidden" name="product_id" id="edit-id" value="0">
        <input type="hidden" name="image" id="p-image-hidden" value="images/sarvamta_chilli_powder.jpg">

        <div class="form-group">
          <label for="p-name">Product Name *</label>
          <input type="text" name="name" id="p-name" class="form-input" placeholder="e.g. Resham Patti Chilli Powder" required>
        </div>

        <div class="form-group">
          <label for="p-category">Category *</label>
          <select name="category" id="p-category" class="form-input" required>
            <option value="Ground Spices">Ground Spices</option>
            <option value="Masala Mix">Masala Mixes</option>
            <option value="Whole Spices">Whole Spices</option>
            <option value="Makhana">Makhana</option>
          </select>
        </div>

        <div class="form-group" style="background:#fffbeb; padding:14px 16px; border:1.5px solid #fde68a; border-radius:8px; margin-bottom:18px;">
          <label style="display:block; font-weight:700; color:#92400e; margin-bottom:8px; font-size:0.9rem;">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> Mark as Customer Favorite (Show on Homepage):
          </label>
          <div style="display:flex; align-items:center; gap:24px;">
            <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; color:#1e293b; font-size:0.9rem; margin-bottom:0;">
              <input type="radio" name="is_favorite" id="p-favorite-yes" value="1" style="width:18px; height:18px; accent-color:#d48806; cursor:pointer;">
              <span style="color:#059669; display:inline-flex; align-items:center; gap:5px;"><i class="fa-solid fa-circle-check"></i> Yes (Favorite)</span>
            </label>
            <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; color:#1e293b; font-size:0.9rem; margin-bottom:0;">
              <input type="radio" name="is_favorite" id="p-favorite-no" value="0" checked style="width:18px; height:18px; accent-color:#d48806; cursor:pointer;">
              <span style="color:#64748b; display:inline-flex; align-items:center; gap:5px;"><i class="fa-solid fa-circle-xmark"></i> No (Standard)</span>
            </label>
          </div>
        </div>

        <!-- MULTIPLE FILE UPLOAD DROPBOX -->
        <div class="form-group">
          <label>Upload Product Image(s) *</label>
          <div class="file-upload-box" onclick="document.getElementById('p-image-files').click()">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:#d48806; margin-bottom:6px;"></i>
            <div style="font-weight:700; font-size:0.9rem; color:#1e293b;">Click to upload multiple image files (Select 1 to 5+ photos)</div>
            <div style="font-size:0.78rem; color:#64748b; margin-top:2px;">All selected photos are saved to database and displayed in the frontend gallery slider.</div>
            <input type="file" id="p-image-files" name="product_images[]" multiple accept="image/*" style="display:none;" onchange="handleImageSelection(event)">
          </div>

          <!-- SIDE-BY-SIDE PREVIEWS -->
          <div id="image-previews-container" class="img-preview-row"></div>
        </div>

        <div class="form-group">
          <label for="p-desc">Description</label>
          <textarea name="description" id="p-desc" class="form-input" rows="2" placeholder="100% Pure Indian Spices..."></textarea>
        </div>

        <div class="form-group">
          <label>Packaging Sizes & Prices (₹) *</label>
          <div class="pkg-builder">
            <div style="display:flex; gap:8px; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:6px; text-transform:uppercase; padding:0 4px;">
              <span style="flex:1.2;">Package Size</span>
              <span style="flex:1;">Actual Price (₹)</span>
              <span style="flex:1;">Offer Price (₹)</span>
              <span style="width:34px;"></span>
            </div>
            <div id="pkg-rows-list">
              <div class="pkg-row">
                <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="200g" placeholder="Size (e.g. 200g)" title="Package Size" required style="flex:1.2;">
                <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="240" placeholder="Actual Price (₹)" title="Actual / MRP Price" required style="flex:1;">
                <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="180" placeholder="Offer Price (₹)" title="Offer / Selling Price" required style="flex:1;">
              </div>
              <div class="pkg-row">
                <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="500g" placeholder="Size (e.g. 500g)" title="Package Size" style="flex:1.2;">
                <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="550" placeholder="Actual Price (₹)" title="Actual / MRP Price" style="flex:1;">
                <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="420" placeholder="Offer Price (₹)" title="Offer / Selling Price" style="flex:1;">
              </div>
              <div class="pkg-row">
                <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="1kg" placeholder="Size (e.g. 1kg)" title="Package Size" style="flex:1.2;">
                <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="1050" placeholder="Actual Price (₹)" title="Actual / MRP Price" style="flex:1;">
                <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="800" placeholder="Offer Price (₹)" title="Offer / Selling Price" style="flex:1;">
              </div>
            </div>
            <button type="button" class="btn-row-add" onclick="addPkgRow()"><i class="fa-solid fa-plus"></i> Add Size Option</button>
          </div>
        </div>

        <button type="submit" class="btn-modal-save">
          <i class="fa-solid fa-floppy-disk"></i> Save Product to Database
        </button>
      </form>
    </div>
  </div>

  <!-- POPUP 2: IMAGES POPUP MODAL -->
  <div id="images-modal" class="modal-overlay">
    <div class="modal-box">
      <button class="modal-close-btn" onclick="closeModal('images-modal')">&times;</button>
      <h2 id="img-modal-title" style="font-size:1.25rem; font-weight:800; color:#260802; margin-bottom:6px;">Product Images</h2>
      <div style="font-size:0.85rem; color:#64748b; margin-bottom:16px;">Viewing product image gallery</div>

      <div id="images-gallery-content" class="images-grid-preview"></div>
    </div>
  </div>

  <!-- POPUP 3: PRICES TABLE POPUP MODAL -->
  <div id="prices-modal" class="modal-overlay">
    <div class="modal-box">
      <button class="modal-close-btn" onclick="closeModal('prices-modal')">&times;</button>
      <h2 id="price-modal-title" style="font-size:1.25rem; font-weight:800; color:#260802; margin-bottom:6px;">Packaging & Pricing Details</h2>
      <div id="price-modal-sub" style="font-size:0.85rem; color:#64748b; margin-bottom:16px;">Package size, actual price and offer price</div>

      <table class="popup-prices-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Packaging Size</th>
            <th>Actual Price (₹)</th>
            <th>Offer Price (₹)</th>
            <th>Discount</th>
          </tr>
        </thead>
        <tbody id="prices-table-body"></tbody>
      </table>
    </div>
  </div>

  <!-- JAVASCRIPT LOGIC -->
  <script>
    let selectedImagePreviews = [];

    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      const tabFromUrl = urlParams.get('tab');
      const phpTab = '<?php echo $activeTab; ?>';
      let savedTab = tabFromUrl;

      if (!savedTab) {
        try {
          savedTab = localStorage.getItem('sarvamta_admin_tab');
        } catch(e) {}
      }

      const hasAction = urlParams.has('action') || <?php echo (isset($_POST['save_product']) ? 'true' : 'false'); ?>;
      const targetTab = hasAction ? phpTab : (tabFromUrl || savedTab || phpTab || 'dashboard');

      if (targetTab && ['dashboard', 'products', 'inquiries'].includes(targetTab)) {
        switchTab(targetTab);
      }

      // Clean action query params from address bar after processing so refreshing won't re-execute action
      if (hasAction) {
        try {
          const cleanUrl = new URL(window.location);
          cleanUrl.searchParams.delete('action');
          cleanUrl.searchParams.delete('id');
          cleanUrl.searchParams.delete('fav');
          cleanUrl.searchParams.set('tab', targetTab);
          window.history.replaceState({ tab: targetTab }, '', cleanUrl);
        } catch(e) {}
      }

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.action-dropdown')) {
          document.querySelectorAll('.dropdown-menu-box').forEach(box => box.classList.remove('show'));
        }
      });
    });

    let dbInquiries = <?php echo json_encode($inquiriesList); ?>;

    function switchTab(tabName) {
      try {
        localStorage.setItem('sarvamta_admin_tab', tabName);
      } catch(e) {}

      try {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        if (url.searchParams.has('action')) url.searchParams.delete('action');
        if (url.searchParams.has('id')) url.searchParams.delete('id');
        if (url.searchParams.has('fav')) url.searchParams.delete('fav');
        window.history.replaceState({ tab: tabName }, '', url);
      } catch(e) {}

      const formTab = document.getElementById('p-form-tab');
      if (formTab) formTab.value = tabName;

      document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));

      if (tabName === 'dashboard') {
        const dNav = document.getElementById('nav-dash');
        const dTab = document.getElementById('tab-dashboard');
        if (dNav) dNav.classList.add('active');
        if (dTab) dTab.classList.add('active');
        const title = document.getElementById('current-page-title');
        if (title) title.innerHTML = `<i class="fa-solid fa-chart-line" style="color:#d48806;"></i> Dashboard Overview`;
      } else if (tabName === 'products') {
        const pNav = document.getElementById('nav-prod');
        const pTab = document.getElementById('tab-products');
        if (pNav) pNav.classList.add('active');
        if (pTab) pTab.classList.add('active');
        const title = document.getElementById('current-page-title');
        if (title) title.innerHTML = `<i class="fa-solid fa-boxes-stacked" style="color:#d48806;"></i> Product Catalog`;
      } else if (tabName === 'inquiries') {
        const iNav = document.getElementById('nav-inq');
        const iTab = document.getElementById('tab-inquiries');
        if (iNav) iNav.classList.add('active');
        if (iTab) iTab.classList.add('active');
        const title = document.getElementById('current-page-title');
        if (title) title.innerHTML = `<i class="fa-solid fa-envelope-open-text" style="color:#d48806;"></i> Product Inquiries`;
        renderInquiriesTable();
      }
    }

    let currentInquiryFilter = 'All';

    function onInquiryStatusFilterChange(status) {
      setInquiryStatusFilter(status);
    }

    function setInquiryStatusFilter(status) {
      currentInquiryFilter = status;
      ['all', 'pending', 'ongoing', 'completed'].forEach(st => {
        const btn = document.getElementById('inq-filter-' + st);
        if (btn) {
          if (st === status.toLowerCase()) btn.classList.add('active');
          else btn.classList.remove('active');
        }
      });
      const statusSelect = document.getElementById('inq-status-filter');
      if (statusSelect && statusSelect.value !== status) {
        statusSelect.value = status;
      }
      renderInquiriesTable();
    }

    function resetInquiryFilters() {
      const search = document.getElementById('inq-search');
      if (search) search.value = '';
      const fromDate = document.getElementById('inq-from-date');
      if (fromDate) fromDate.value = '';
      const toDate = document.getElementById('inq-to-date');
      if (toDate) toDate.value = '';
      const statusSelect = document.getElementById('inq-status-filter');
      if (statusSelect) statusSelect.value = 'All';
      setInquiryStatusFilter('All');
    }

    function getInquiryDateStr(createdAt) {
      if (!createdAt) return '';
      const match = String(createdAt).match(/^(\d{4}-\d{2}-\d{2})/);
      if (match) return match[1];
      try {
        const d = new Date(createdAt);
        if (!isNaN(d.getTime())) {
          const y = d.getFullYear();
          const m = String(d.getMonth() + 1).padStart(2, '0');
          const day = String(d.getDate()).padStart(2, '0');
          return `${y}-${m}-${day}`;
        }
      } catch(e) {}
      return '';
    }

    function renderInquiriesTable() {
      const tbody = document.getElementById('inquiries-table-body');
      if (!tbody) return;

      let localInq = [];
      try {
        localInq = JSON.parse(localStorage.getItem('sarvamta_inquiries') || '[]');
      } catch(e) {}

      let combined = [...dbInquiries];
      localInq.forEach(linq => {
        if (!combined.some(i => i.id == linq.id || (i.name === linq.name && i.created_at === linq.created_at))) {
          combined.push(linq);
        }
      });

      const badgeCount = document.getElementById('inq-badge-count');
      if (badgeCount) badgeCount.textContent = combined.length;

      const allCount = combined.length;
      const pendingCount = combined.filter(i => (i.status || 'Pending') === 'Pending').length;
      const ongoingCount = combined.filter(i => (i.status || 'Pending') === 'Ongoing').length;
      const completedCount = combined.filter(i => (i.status || 'Pending') === 'Completed').length;

      if (document.getElementById('count-inq-all')) document.getElementById('count-inq-all').textContent = allCount;
      if (document.getElementById('count-inq-pending')) document.getElementById('count-inq-pending').textContent = pendingCount;
      if (document.getElementById('count-inq-ongoing')) document.getElementById('count-inq-ongoing').textContent = ongoingCount;
      if (document.getElementById('count-inq-completed')) document.getElementById('count-inq-completed').textContent = completedCount;

      const fromDate = document.getElementById('inq-from-date')?.value || '';
      const toDate = document.getElementById('inq-to-date')?.value || '';
      const searchTerm = (document.getElementById('inq-search')?.value || '').trim().toLowerCase();

      let filtered = combined.filter(inq => {
        // Status filter
        const inqStatus = inq.status || 'Pending';
        if (currentInquiryFilter !== 'All' && inqStatus !== currentInquiryFilter) {
          return false;
        }

        // Date filter
        const inqDate = getInquiryDateStr(inq.created_at);
        if (fromDate) {
          if (!inqDate || inqDate < fromDate) return false;
        }
        if (toDate) {
          if (!inqDate || inqDate > toDate) return false;
        }

        // Search text filter
        if (searchTerm) {
          const name = String(inq.name || '').toLowerCase();
          const email = String(inq.email || '').toLowerCase();
          const phone = String(inq.phone || '').toLowerCase();
          const prod = String(inq.product_name || inq.product || '').toLowerCase();
          const pkg = String(inq.packaging_size || '').toLowerCase();
          if (!name.includes(searchTerm) && !email.includes(searchTerm) && !phone.includes(searchTerm) && !prod.includes(searchTerm) && !pkg.includes(searchTerm)) {
            return false;
          }
        }

        return true;
      });

      const counterEl = document.getElementById('inquiry-results-counter');
      if (counterEl) {
        counterEl.innerHTML = `Showing: <strong>${filtered.length}</strong> of ${combined.length} inquiries`;
      }

      if (filtered.length === 0) {
        let msg = `No inquiries found`;
        if (currentInquiryFilter !== 'All') msg += ` with status "${currentInquiryFilter}"`;
        if (fromDate && toDate) msg += ` between ${fromDate} and ${toDate}`;
        else if (fromDate) msg += ` from ${fromDate}`;
        else if (toDate) msg += ` up to ${toDate}`;
        if (searchTerm) msg += ` matching "${searchTerm}"`;
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:#94a3b8; font-weight:600;"><i class="fa-solid fa-inbox" style="font-size:2rem; margin-bottom:10px; display:block; color:#cbd5e1;"></i>${msg}.</td></tr>`;
        return;
      }

      let html = '';
      filtered.forEach((inq, idx) => {
        const id = inq.id || (idx + 1);
        const name = inq.name || 'N/A';
        const email = inq.email || 'N/A';
        const phone = inq.phone || 'N/A';
        const prod = inq.product_name || inq.product || 'General Inquiry';
        const pkg = inq.packaging_size || '200g';
        const qty = inq.quantity || 1;
        const status = inq.status || 'Pending';
        const dateStr = inq.created_at ? inq.created_at.substring(0, 16) : 'Just now';

        let statusStyle = 'background:#fef3c7; color:#d97706; border:1px solid #fde68a;';
        if (status === 'Ongoing') {
          statusStyle = 'background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe;';
        } else if (status === 'Completed') {
          statusStyle = 'background:#d1fae5; color:#047857; border:1px solid #a7f3d0;';
        }

        html += `
          <tr>
            <td><strong>#${id}</strong></td>
            <td style="font-size:0.82rem; color:#64748b;">${dateStr}</td>
            <td>
              <div style="font-weight:700; color:#1e293b;">${name}</div>
              <div style="font-size:0.8rem; color:#64748b;"><i class="fa-solid fa-phone" style="font-size:0.75rem;"></i> ${phone} | <i class="fa-solid fa-envelope" style="font-size:0.75rem;"></i> ${email}</div>
            </td>
            <td><span class="cat-badge-pill">${prod}</span></td>
            <td><strong style="color:#d48806;">${pkg}</strong></td>
            <td><span style="font-weight:800; background:#f1f5f9; padding:4px 10px; border-radius:20px; font-size:0.85rem;">${qty} Units</span></td>
            <td>
              <select onchange="updateInquiryStatus(${id}, this.value)" style="padding:6px 12px; font-size:0.82rem; border-radius:8px; font-weight:700; cursor:pointer; outline:none; ${statusStyle}">
                <option value="Pending" ${status === 'Pending' ? 'selected' : ''}>Pending</option>
                <option value="Ongoing" ${status === 'Ongoing' ? 'selected' : ''}>Ongoing</option>
                <option value="Completed" ${status === 'Completed' ? 'selected' : ''}>Completed</option>
              </select>
            </td>
            <td style="text-align:center;">
              <button type="button" onclick="deleteInquiry(${id})" title="Delete Inquiry" style="background:#fee2e2; border:1px solid #fecaca; color:#dc2626; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.8rem; font-weight:700; display:inline-flex; align-items:center; gap:5px; transition:all 0.2s ease;">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </td>
          </tr>
        `;
      });

      tbody.innerHTML = html;
    }

    async function updateInquiryStatus(id, newStatus) {
      try {
        let localInq = JSON.parse(localStorage.getItem('sarvamta_inquiries') || '[]');
        const target = localInq.find(i => i.id == id);
        if (target) {
          target.status = newStatus;
          localStorage.setItem('sarvamta_inquiries', JSON.stringify(localInq));
        }
      } catch(e) {}

      try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', newStatus);
        await fetch('../api/update_inquiry_status.php', { method: 'POST', body: formData });
      } catch(e) {}

      renderInquiriesTable();
    }

    async function deleteInquiry(id) {
      if (!confirm('Are you sure you want to delete inquiry #' + id + '?')) return;

      try {
        let localInq = JSON.parse(localStorage.getItem('sarvamta_inquiries') || '[]');
        localInq = localInq.filter(i => i.id != id);
        localStorage.setItem('sarvamta_inquiries', JSON.stringify(localInq));
      } catch(e) {}

      dbInquiries = dbInquiries.filter(i => i.id != id);

      try {
        const formData = new FormData();
        formData.append('id', id);
        await fetch('../api/delete_inquiry.php', { method: 'POST', body: formData });
      } catch(e) {}

      renderInquiriesTable();
    }

    function filterInquiriesTable() {
      renderInquiriesTable();
    }

    function toggleActionMenu(event, id) {
      event.stopPropagation();
      const menu = document.getElementById(`action-menu-${id}`);
      const isShow = menu.classList.contains('show');
      document.querySelectorAll('.dropdown-menu-box').forEach(box => box.classList.remove('show'));
      if (!isShow) menu.classList.add('show');
    }

    function openPhpImagesModal(p) {
      document.getElementById('img-modal-title').textContent = p.name + ' - Photo Gallery';
      const gallery = document.getElementById('images-gallery-content');
      const imgs = (Array.isArray(p.all_images) && p.all_images.length > 0) 
        ? p.all_images 
        : (p.images ? (Array.isArray(p.images) ? p.images : JSON.parse(p.images || '[]')) : [p.image]);
      
      let html = '';
      imgs.forEach((img, idx) => {
        html += `
          <div class="img-card-preview" style="text-align:center; background:#ffffff; border:1.5px solid ${idx === 0 ? '#d48806' : '#e2e8f0'}; padding:12px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <img src="../${img}" onerror="this.src='../images/logo.png'" alt="${p.name} ${idx+1}" style="width:130px; height:130px; object-fit:contain; border:1px solid #cbd5e1; padding:4px; background:#ffffff; border-radius:4px;">
            <div style="font-size:0.8rem; font-weight:800; color:#1e293b; margin-top:8px;">
              ${idx === 0 ? '<span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:2px 6px; border-radius:4px; font-size:0.7rem; margin-right:4px;">[Primary Main]</span>' : ''}Photo #${idx + 1}
            </div>
            <div style="font-size:0.7rem; color:#64748b; word-break:break-all; margin-top:2px;">${img}</div>
          </div>
        `;
      });
      gallery.innerHTML = html;
      document.getElementById('images-modal').classList.add('active');
    }

    function openPhpPricesModal(p) {
      document.getElementById('price-modal-title').textContent = p.name;
      document.getElementById('price-modal-sub').textContent = `Category: ${p.category} | Package Prices`;
      
      const tbody = document.getElementById('prices-table-body');
      let html = '';
      if (p.variants_summary) {
        const parts = p.variants_summary.includes(';;') ? p.variants_summary.split(';;') : p.variants_summary.split(', ');
        parts.forEach((part, idx) => {
          if (!part.trim()) return;
          let sz = '', actPr = '0', offPr = '0';
          if (part.includes('|')) {
            const bits = part.split('|');
            sz = bits[0] || '';
            actPr = bits[1] || bits[2] || '0';
            offPr = bits[2] || bits[1] || '0';
          } else {
            const bits = part.split(': ₹');
            sz = bits[0] || '';
            offPr = bits[1] || '0';
            actPr = (parseFloat(offPr) * 1.25).toFixed(2);
          }
          const numAct = parseFloat(actPr) || 0;
          const numOff = parseFloat(offPr) || 0;
          const discount = numAct > numOff 
            ? Math.round(((numAct - numOff) / numAct) * 100) + '% OFF' 
            : '-';

          html += `
            <tr>
              <td><strong>#${idx+1}</strong></td>
              <td><span style="font-weight:700; color:#1e293b;">${sz}</span></td>
              <td><span style="text-decoration:line-through; color:#94a3b8; font-weight:600;">₹${parseFloat(actPr).toFixed(2)}</span></td>
              <td><strong style="color:#d48806; font-size:1rem;">₹${parseFloat(offPr).toFixed(2)}</strong></td>
              <td><span class="cat-badge-pill" style="background:#fef2f2; color:#dc2626; border-color:#fecaca;">${discount}</span></td>
            </tr>
          `;
        });
      } else {
        html = `<tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">No package sizes configured.</td></tr>`;
      }

      tbody.innerHTML = html;
      document.getElementById('prices-modal').classList.add('active');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
    }

    function filterProductsTable() {
      const input = document.getElementById('prod-search');
      const filter = input.value.toLowerCase();
      const table = document.getElementById('main-products-table');
      const tr = table.getElementsByTagName('tr');

      for (let i = 1; i < tr.length; i++) {
        const text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
          tr[i].style.display = '';
        } else {
          tr[i].style.display = 'none';
        }
      }
    }

    function handleImageSelection(e) {
      const files = Array.from(e.target.files);
      if (!files || files.length === 0) return;

      selectedImagePreviews = [];
      files.forEach((file, idx) => {
        const reader = new FileReader();
        reader.onload = function(evt) {
          selectedImagePreviews.push({ name: file.name, url: evt.target.result });
          renderSelectedImagePreviews();
        };
        reader.readAsDataURL(file);
      });
    }

    function renderSelectedImagePreviews() {
      const container = document.getElementById('image-previews-container');
      if (!selectedImagePreviews || selectedImagePreviews.length === 0) {
        container.innerHTML = '<div style="font-size:0.8rem; color:#94a3b8; padding:8px 0;">No image selected yet.</div>';
        return;
      }

      let html = '';
      selectedImagePreviews.forEach((imgObj, idx) => {
        html += `
          <div class="img-preview-item" style="position:relative; width:80px; height:80px; border:2px solid ${idx === 0 ? '#d48806' : '#cbd5e1'}; border-radius:6px; overflow:hidden; background:#fff; padding:2px; display:inline-block; margin-right:8px; margin-top:8px;" title="${imgObj.name}">
            <img src="${imgObj.url}" alt="Selected Image ${idx+1}" style="width:100%; height:100%; object-fit:contain;">
            <span style="position:absolute; bottom:0; left:0; right:0; background:${idx === 0 ? '#d48806' : 'rgba(0,0,0,0.7)'}; color:#fff; font-size:0.65rem; text-align:center; font-weight:700; line-height:1.4;">${idx === 0 ? 'Main' : '#' + (idx+1)}</span>
          </div>
        `;
      });
      container.innerHTML = html;
    }

    function openAddModal() {
      document.getElementById('edit-id').value = 0;
      document.getElementById('modal-heading').textContent = 'Add New Product';
      document.getElementById('p-name').value = '';
      document.getElementById('p-category').value = 'Ground Spices';
      document.getElementById('p-desc').value = '';
      document.getElementById('p-image-files').value = '';
      if (document.getElementById('p-favorite-yes')) document.getElementById('p-favorite-yes').checked = false;
      if (document.getElementById('p-favorite-no')) document.getElementById('p-favorite-no').checked = true;

      selectedImagePreviews = [];
      renderSelectedImagePreviews();

      const list = document.getElementById('pkg-rows-list');
      list.innerHTML = `
        <div class="pkg-row">
          <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="200g" placeholder="Size (e.g. 200g)" title="Package Size" required style="flex:1.2;">
          <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="240" placeholder="Actual Price (₹)" title="Actual / MRP Price" required style="flex:1;">
          <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="180" placeholder="Offer Price (₹)" title="Offer / Selling Price" required style="flex:1;">
        </div>
        <div class="pkg-row">
          <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="500g" placeholder="Size (e.g. 500g)" title="Package Size" style="flex:1.2;">
          <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="550" placeholder="Actual Price (₹)" title="Actual / MRP Price" style="flex:1;">
          <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="420" placeholder="Offer Price (₹)" title="Offer / Selling Price" style="flex:1;">
        </div>
        <div class="pkg-row">
          <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="1kg" placeholder="Size (e.g. 1kg)" title="Package Size" style="flex:1.2;">
          <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="1050" placeholder="Actual Price (₹)" title="Actual / MRP Price" style="flex:1;">
          <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="800" placeholder="Offer Price (₹)" title="Offer / Selling Price" style="flex:1;">
        </div>
      `;

      document.getElementById('product-modal').classList.add('active');
    }

    function addPkgRow(sz = '', actPr = '', offPr = '') {
      const list = document.getElementById('pkg-rows-list');
      const div = document.createElement('div');
      div.className = 'pkg-row';
      div.innerHTML = `
        <input type="text" name="pkg_size[]" class="form-input pkg-sz" value="${sz}" placeholder="Size (e.g. 5kg)" title="Package Size" required style="flex:1.2;">
        <input type="number" step="0.01" name="pkg_actual_price[]" class="form-input pkg-act-pr" value="${actPr}" placeholder="Actual Price (₹)" title="Actual / MRP Price" required style="flex:1;">
        <input type="number" step="0.01" name="pkg_price[]" class="form-input pkg-pr" value="${offPr}" placeholder="Offer Price (₹)" title="Offer / Selling Price" required style="flex:1;">
        <button type="button" class="btn-row-del" onclick="this.parentElement.remove()" title="Remove size">&times;</button>
      `;
      list.appendChild(div);
    }

    function editProduct(p) {
      document.querySelectorAll('.dropdown-menu-box').forEach(box => box.classList.remove('show'));
      document.getElementById('edit-id').value = p.id;
      document.getElementById('modal-heading').textContent = 'Edit Product #' + p.id;
      document.getElementById('p-name').value = p.name;
      document.getElementById('p-category').value = p.category;
      document.getElementById('p-desc').value = p.description || '';
      document.getElementById('p-image-hidden').value = p.image;
      const isFav = (parseInt(p.is_favorite) === 1);
      if (document.getElementById('p-favorite-yes')) document.getElementById('p-favorite-yes').checked = isFav;
      if (document.getElementById('p-favorite-no')) document.getElementById('p-favorite-no').checked = !isFav;

      selectedImagePreviews = [];
      const currentImgs = (Array.isArray(p.all_images) && p.all_images.length > 0)
        ? p.all_images
        : (p.images ? (Array.isArray(p.images) ? p.images : JSON.parse(p.images || '[]')) : [p.image]);

      currentImgs.forEach((img, idx) => {
        selectedImagePreviews.push({ name: `Photo #${idx+1}${idx === 0 ? ' [Main]' : ''}`, url: '../' + img });
      });
      renderSelectedImagePreviews();

      const list = document.getElementById('pkg-rows-list');
      list.innerHTML = '';
      
      if (p.variants_summary) {
        const parts = p.variants_summary.includes(';;') ? p.variants_summary.split(';;') : p.variants_summary.split(', ');
        parts.forEach(part => {
          if (!part.trim()) return;
          if (part.includes('|')) {
            const bits = part.split('|');
            const sz = bits[0] || '';
            const actPr = bits[1] || bits[2] || '';
            const offPr = bits[2] || bits[1] || '';
            addPkgRow(sz, actPr, offPr);
          } else {
            const [sz, prStr] = part.split(': ₹');
            const actPr = (parseFloat(prStr) * 1.25).toFixed(2);
            addPkgRow(sz, actPr, prStr);
          }
        });
      } else {
        addPkgRow('200g', '240', '180');
      }

      document.getElementById('product-modal').classList.add('active');
    }
  </script>
</body>
</html>
