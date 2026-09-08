<?php

session_start();

if($_SERVER['REQUEST_METHOD']==='POST'){
header('Content-Type: text/plain; charset=utf-8');

  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  if (!is_string($email) || !is_string($password)) {
      http_response_code(400);
      exit('Invalid form data.');
  }

  $email = trim($email);

   if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        http_response_code(422);
        exit('Enter a valid email and your password.');
    }

     require_once __DIR__ . '/../backend/config/database.php';

    try {
        $statement = $pdo->prepare(
            'SELECT id, name, password, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);


        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            exit('Invalid email or password.');
        }

        if ($user['status'] !== 'active') {
            http_response_code(403);
            exit('Your account is blocked.');
        }

                
          session_regenerate_id(true);

          $_SESSION['user_id'] = $user['id'];
          $_SESSION['user_name'] = $user['name'];
          $_SESSION['user_role'] = $user['role'];

          echo 'Logged in successfully!';

    }catch(PDOException $error){
        error_log($error->getMessage());

        http_response_code(500);
        echo 'Unable to log in. Please try again.';
    }

    exit;
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
  <link rel="stylesheet" href="signup.css">
</head>
<body>
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <nav id="nav-links">
          <a href="search.php">Browse Venues</a>
          <a href="list.php">List a Venue</a>
          <a href="ownerdashboard.php">Owners Dashboard</a>
          <a href="admin.php">Admin</a>
        </nav>
        <div id="nav-buttons">
          <a id="list-venue-button" href="list.php">List a Venue</a>
          <a id="login-button" href="loginchoice.php">Login</a>
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
      <form action="login.php" method="post">
        <label for="role">Sign in as</label>
        <select id="role" name="role">
          <option value="customer">Customer</option>
          <option value="owner">Venue owner</option>
          <option value="admin">Administrator</option>
        </select>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
        <div class="password-label"><label for="password">Password</label><a href="#">Forgot password?</a></div>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
        <label class="remember"><input type="checkbox" name="remember"><span>Remember me</span></label>
        <button type="submit">Sign in <span aria-hidden="true">→</span></button>
      </form>
      <p class="signup">Don't have an account? <a href="signup.php">Create one</a></p>
    </section>
  </main>
</body>
</html>
