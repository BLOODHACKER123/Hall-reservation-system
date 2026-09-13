<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="loginchoice.css">
  
</head>
<body class="account-page">

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
    <section class="login-choice" aria-labelledby="login-title">
      <p class="brand-title">VenueVista</p>
      <h1 id="login-title">Welcome back</h1>
      <p class="subtitle">Choose how you'd like to sign in</p>
      <div class="choice-card">
        <a class="google-button" href="#"><span class="google-mark" aria-hidden="true"><img src="images/google.png" alt="Google"></span>Continue with Google</a>
        <div class="divider"><span>OR</span></div>
        <a class="email-button" href="login.php"><span aria-hidden="true">✉</span>Continue with Email</a>
      </div>
      <p class="signup">Don't have an account? <a href="signup.php">Sign up</a></p>
      <a class="back-home" href="index.php">← Back to home</a>
    </section>
  </main>

  <?php require __DIR__ . '/partials/account-footer.php'; ?>
</body>
</html>
