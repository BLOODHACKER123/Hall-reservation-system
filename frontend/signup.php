<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/auditLogger.php';

$error_message = '';
$success_message = '';

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$role = $_POST['role'] ?? 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error_message = 'All fields are required.';
        logAudit("Signup failed: Missing required fields (Email attempted: " . htmlspecialchars($email) . ")", null, 'Guest');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
        logAudit("Signup failed: Invalid email format ({$email})", null, 'Guest');
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
        logAudit("Signup failed: Password shorter than 8 characters (Email: {$email})", null, 'Guest');
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
        logAudit("Signup failed: Passwords did not match (Email: {$email})", null, 'Guest');
    } elseif (!in_array($role, ['customer', 'owner'], true)) {
        $error_message = 'Invalid account type selected.';
        logAudit("Signup failed: Invalid account role selected ({$role})", null, 'Guest');
    } else {
        try {
            $pdo->beginTransaction();

            // Check if email or phone already exists
            $check = $pdo->prepare('SELECT user_id FROM users WHERE email = :email OR phone = :phone LIMIT 1');
            $check->execute(['email' => $email, 'phone' => $phone]);
            if ($check->fetch()) {
                logAudit("Signup failed: Duplicate account attempt for email {$email} or phone {$phone}", null, 'Guest');
                throw new Exception('An account with this email or phone number already exists.');
            }

            // Split Name into First and Last
            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';

            // Map frontend role to database ENUM
            $db_role = ($role === 'owner') ? 'Vendor' : 'Customer';
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert Base User
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password, user_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$first_name, $last_name, $email, $phone, $passwordHash, $db_role]);
            $user_id = (int)$pdo->lastInsertId();

            // Insert into respective subclass table
            if ($db_role === 'Customer') {
                $pdo->prepare('INSERT INTO customers (user_id) VALUES (?)')->execute([$user_id]);
            } elseif ($db_role === 'Vendor') {
                // Insert placeholder business details to satisfy DB constraints. Will be updated later.
                $pdo->prepare('INSERT INTO vendors (user_id, business_name, business_address) VALUES (?, ?, ?)')
                    ->execute([$user_id, $first_name . "'s Venue", 'Address pending']);
            }

            $pdo->commit();

            // AUDIT LOG: Successful account creation
            logAudit(
                "New account registered: {$first_name} {$last_name} ({$db_role}, User ID: #{$user_id}, Email: {$email})",
                $user_id,
                $db_role
            );
            
            // Auto-login after signup
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $first_name;
            $_SESSION['user_type'] = $db_role;

            // Redirect based on role
            $redirect = ($db_role === 'Vendor') ? 'ownerdashboard.php' : 'index.php';
            header("Location: " . $redirect);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!isset($check) || !$check->rowCount()) {
                logAudit("Signup transaction error for {$email}: " . $e->getMessage(), null, 'Guest');
            }
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign up | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="signup.css">
</head>
<body class="account-page">
 <section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <div id="nav-buttons">
            <a id="login-button" href="loginchoice.php">Login</a>
          </div>
        </div>
      </div>
    </section>

  <main class="login-area">
    <section class="login-card" aria-labelledby="login-title">
      <div id="image-logo"><img src="images/venuevista-logo.png" alt="VenueVista"></div>
      <p class="eyebrow">JOIN VENUEVISTA</p>
      <h1 id="login-title">Create your account</h1>
      <p class="subtitle">Manage your venues and reservations in one place.</p>

      <?php if (!empty($error_message)): ?>
          <div style="background: #fce8e6; color: #c5221f; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; text-align: center;">
              <?= htmlspecialchars($error_message) ?>
          </div>
      <?php endif; ?>

      <form action="signup.php" method="post">
        <label for="role">Account type</label>
        <select id="role" name="role">
          <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
          <option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Venue owner</option>
        </select>
        
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" autocomplete="name" placeholder="Your full name" value="<?= htmlspecialchars($name) ?>" required>
        
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+94 77 123 4567" value="<?= htmlspecialchars($phone) ?>" required>
        
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= htmlspecialchars($email) ?>" required>
        
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
        
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" placeholder="Re-enter your password" required>
        
        <button type="submit">Create account <span aria-hidden="true">→</span></button>
      </form>
      <p class="signup">Already have an account? <a href="login.php">Sign in</a></p>
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