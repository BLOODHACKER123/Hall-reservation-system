<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Bookings | VenueVista</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="mybookings.css">
</head>
<body class="account-page">

	<section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            <a href="list.php">List A Venue</a>
            <a href="ownerdashboard.php">Owners Dashboard</a>
            <a href="admin.php">Admin</a>
          </nav>
          <div id="nav-buttons">
            <a id="list-venue-button" href="list.php">List A Venue</a>
            <a id="login-button" href="loginchoice.php">Login</a>
          </div>
        </div>
      </div>
    </section>

	<main class="page-area">
		<section class="booking-card" aria-labelledby="booking-title">
			<div class="calendar-icon" aria-hidden="true"><span></span><i></i><b></b></div>
			<h1 id="booking-title">My Bookings</h1>
			<p class="intro">Enter the email you used when booking to<br>view your reservations.</p>
			<form action="mybookings.php" method="get">
				<label class="sr-only" for="booking-email">Booking email</label>
				<input id="booking-email" name="email" type="email" autocomplete="email" placeholder="your@email.com" required>
				<button type="submit">View Bookings</button>
			</form>
		</section>
	</main>
	
  <?php require __DIR__ . '/partials/account-footer.php'; ?>
</body>
</html>
