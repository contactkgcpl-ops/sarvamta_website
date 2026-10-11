<?php
/**
 * SARVAMTA SPICES - ADMIN LOGIN
 */

session_start();

$error = '';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Admin credentials
    if (($username === 'sarvamta' && $password === 'sarvamta@2026') || ($username === 'admin' && $password === 'admin123')) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid Username or Password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login | SARVAMTA Spices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../images/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
    body { background-color: #491101; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .login-card { background: white; border-radius: 16px; width: 100%; max-width: 420px; padding: 40px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center; }
    .logo-img { height: 60px; margin-bottom: 20px; object-fit: contain; }
    h2 { font-size: 1.4rem; color: #111827; font-weight: 700; margin-bottom: 6px; }
    p { color: #6b7280; font-size: 0.9rem; margin-bottom: 24px; }
    .form-group { margin-bottom: 18px; text-align: left; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #374151; display: block; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
    .form-control:focus { border-color: #d48806; }
    .btn-login { width: 100%; background: linear-gradient(135deg, #d48806, #ff9800); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: transform 0.2s; margin-top: 10px; }
    .btn-login:hover { transform: translateY(-1px); }
    .error-msg { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 16px; font-weight: 600; }
  </style>
</head>
<body>

  <div class="login-card">
    <img src="../images/logo.png" alt="Sarvamta Spices" class="logo-img" onerror="this.src='../images/logo.png'">
    <h2>Admin Panel Access</h2>
    <p>Sign in to manage products & packaging sizes</p>

    <?php if (!empty($error)): ?>
      <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required class="form-control" placeholder="Enter username" value="sarvamta">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required class="form-control" placeholder="Enter password" value="sarvamta@2026">
      </div>
      <button type="submit" class="btn-login">Sign In to Dashboard</button>
    </form>
  </div>

</body>
</html>
