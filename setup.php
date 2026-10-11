<?php
/**
 * SARVAMTA SPICES - Automated Database Setup Script
 * Reads credentials from .env and creates required empty tables.
 * Access via browser: http://your-domain.com/setup.php
 */

require_once __DIR__ . '/api/db.php';

$database = new Database();
$db = $database->getConnection();
$config = $database->getConfigInfo();

$status = [];
$isSuccess = false;
$error = '';

if ($db) {
    try {
        $database->autoInitTables();

        // Verify tables
        $tables = ['products', 'product_images', 'product_variants', 'inquiries'];
        foreach ($tables as $t) {
            $check = $db->query("SHOW TABLES LIKE '$t'")->rowCount();
            $status[$t] = ($check > 0) ? 'Ready' : 'Failed';
        }

        $isSuccess = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = "Could not connect to database: " . ($database->lastError ?: "Please check your DB credentials in .env file.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Database Setup | SARVAMTA Spices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin:0; padding:0; font-family:'Plus Jakarta Sans', sans-serif; }
    body { background: #fdfbf7; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .setup-card { background: #fff; width: 100%; max-width: 580px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
    .setup-header { background: #260802; color: #fff; padding: 28px; text-align: center; }
    .setup-header h1 { font-size: 1.35rem; font-weight: 800; margin-top: 10px; }
    .setup-body { padding: 32px; }
    .env-box { background: #f1f5f9; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #475569; display: flex; justify-content: space-between; font-weight: 600; }
    .status-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; margin-bottom: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.95rem; font-weight: 600; }
    .badge-ok { color: #16a34a; font-weight: 800; display: flex; align-items: center; gap: 6px; }
    .badge-err { color: #dc2626; font-weight: 800; }
    .btn-group { display: flex; gap: 12px; margin-top: 24px; }
    .btn { flex: 1; padding: 12px 20px; border-radius: 8px; font-weight: 700; text-align: center; text-decoration: none; font-size: 0.92rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-primary { background: #d48806; color: #fff; }
    .btn-primary:hover { background: #b87300; }
    .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
    .btn-secondary:hover { background: #e2e8f0; }
  </style>
</head>
<body>
  <div class="setup-card">
    <div class="setup-header">
      <i class="fa-solid fa-database" style="font-size:2.2rem; color:#d48806;"></i>
      <h1>SARVAMTA Database Setup (.env)</h1>
    </div>
    <div class="setup-body">
      <div class="env-box">
        <span>Host: <strong><?php echo htmlspecialchars($config['host'] . ':' . $config['port']); ?></strong></span>
        <span>DB: <strong><?php echo htmlspecialchars($config['db_name']); ?></strong></span>
        <span>User: <strong><?php echo htmlspecialchars($config['user']); ?></strong></span>
      </div>

      <?php if ($isSuccess): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:700; font-size:0.95rem;">
          <i class="fa-solid fa-circle-check"></i> Database Tables Created Successfully (No sample data inserted)!
        </div>

        <div class="status-item">
          <span>Products Table</span>
          <span class="badge-ok"><i class="fa-solid fa-check"></i> <?php echo $status['products']; ?></span>
        </div>
        <div class="status-item">
          <span>Product Images Table</span>
          <span class="badge-ok"><i class="fa-solid fa-check"></i> <?php echo $status['product_images']; ?></span>
        </div>
        <div class="status-item">
          <span>Product Variants Table</span>
          <span class="badge-ok"><i class="fa-solid fa-check"></i> <?php echo $status['product_variants']; ?></span>
        </div>
        <div class="status-item">
          <span>Customer Inquiries Table</span>
          <span class="badge-ok"><i class="fa-solid fa-check"></i> <?php echo $status['inquiries']; ?></span>
        </div>

        <div class="btn-group">
          <a href="admin/index.php" class="btn btn-primary"><i class="fa-solid fa-gauge"></i> Go to Admin Panel</a>
          <a href="index.html" class="btn btn-secondary"><i class="fa-solid fa-store"></i> View Website</a>
        </div>
      <?php else: ?>
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:16px; border-radius:8px; margin-bottom:20px;">
          <div style="font-weight:800; font-size:1rem; margin-bottom:6px;"><i class="fa-solid fa-triangle-exclamation"></i> Connection Failed</div>
          <div style="font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
        </div>
        <div style="font-size:0.88rem; color:#64748b; line-height:1.5;">
          Please update your <strong>.env</strong> file in the root folder with your database credentials and refresh this page.
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
