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
      <div id="image-logo"><img src="images/venuevista-logo.png"></div>
      <p class="eyebrow">JOIN VENUEVISTA</p>
      <h1 id="login-title">Create your account</h1>
      <p class="subtitle">Manage your venues and reservations in one place.</p>
      <form action="signup.php" method="post">
        <label for="role">Account type</label>
        <select id="role" name="role">
          <option value="customer">Customer</option>
          <option value="owner">Venue owner</option>
        </select>
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" autocomplete="name" placeholder="Your full name" required>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
        <label for="confirm-password">Confirm password</label>
        <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" placeholder="Re-enter your password" required>
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
            <p>Discover extraordinary spaces for life's most meaningful moments. Where every venue tells 
              a story.</p>

            <div class="social-links">
              <a href="">INSTAGRAM</a>
              <a href="">PINTEREST</a>
              <a href="">FACEBOOK</a>
            </div>
          </div>
        
          <div class="footer-nav-links">

            <p>DISCOVER</p>
            <a href="search.php">Browse Venues</a>
            <a href="search.php">Wedding Venues</a>
            <a href="search.php">Banquet Halls</a>
            <a href="search.php">Conference Halls</a>
          
          </div>

          <div class="footer-nav-links">
            <p>FOR OWNERS</p> 
            
                <a href="list.php">List Your Venue</a>
                <a href="ownerdashboard.php">Owner Dashboard</a>
                <a href="mybookings.php">My Bookings</a>
              
          </div>
        
        </div>
        <div id="footer-bottom">
            <p>@ 2026 VenueVista. All rights reserved.</p>

            <div class="footer-links">
            <a href="">Privacy Policy</a>
            <a href="">Terms of Service</a>
            <a href="">Contact</a>
            </div>
          </div>
      </div>
    </section> 
</body>
</html>
