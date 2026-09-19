<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

$error_message = '';
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id, first_name, password, user_type, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => trim($email)]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                $error_message = 'Invalid email or password.';
            } elseif ($user['is_active'] == 0) {
                $error_message = 'Your account is deactivated. Please contact support.';
            } else {
                // Success: Establish Session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['first_name'];
                $_SESSION['user_type'] = $user['user_type'];

                // Update last login timestamp
                $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?")->execute([$user['user_id']]);

                // Route automatically based on database role
                if ($user['user_type'] === 'Admin') {
                    header("Location: admin.php");
                } elseif ($user['user_type'] === 'Vendor') {
                    header("Location: ownerdashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } catch (PDOException $e) {
            $error_message = 'System error: Unable to log in at this time.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | VenueVista</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="signup.css">
</head>
<body class="account-page">
  
  <!-- DYNAMIC NAVIGATION BAR -->
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <nav id="nav-links">
          <a href="search.php">Browse Venues</a>
        
          <?php if (isset($_SESSION['user_type'])): ?>
              <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                  <a href="ownerdashboard.php">Owners Dashboard</a>
              <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                  <a href="admin.php">Admin Dashboard</a>
              <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                  <a href="mybookings.php">My Bookings</a>
              <?php endif; ?>
          <?php endif; ?>
        </nav>
        
        <div id="nav-buttons">
          <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin'): ?>
              <a id="list-venue-button" href="list.php">List a Venue</a>
          <?php endif; ?>
          
          <?php if(isset($_SESSION['user_id'])): ?>
              <a id="login-button" href="../backend/config/logout.php">Logout</a>
          <?php else: ?>
              <a id="login-button" href="loginchoice.php">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="login-area">
    <section class="login-card" aria-labelledby="login-title">
      <div id="image-logo"><img src="images/venuevista-logo.png" alt="VenueVista"></div>
      <p class="eyebrow">WELCOME BACK</p>
      <h1 id="login-title">Sign in to VenueVista</h1>
      <p class="subtitle">Manage your venues and reservations in one place.</p>

      <?php if (!empty($error_message)): ?>
          <div style="background: #fce8e6; color: #c5221f; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; text-align: center;">
              <?= htmlspecialchars($error_message) ?>
          </div>
      <?php endif; ?>

      <form action="login.php" method="post">
        
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= htmlspecialchars($email) ?>" required>
        
        <div class="password-label">
            <label for="password">Password</label>
            <a href="#">Forgot password?</a>
        </div>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
        
        <label class="remember"><input type="checkbox" name="remember"><span>Remember me</span></label>
        <button type="submit">Sign in <span aria-hidden="true">→</span></button>
      </form>
      <p class="signup">Don't have an account? <a href="signup.php">Create one</a></p>
    </section>
  </main>

     <section id="footer-section">
      <div id="footer-body">
        <div id="footer-top">
          <div id="footer-details-block">
            <h1>VenueVista</h1>
            <p>Discover extraordinary spaces for life's most meaningful moments.</p>
          </div>
        </div>
        <div id="footer-bottom">
            <p>@ 2026 VenueVista. All rights reserved.</p>
        </div>
      </div>
    </section> 
</body>
</html>