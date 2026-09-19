<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Fetch Categories for the Search Dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT venue_type as name FROM halls WHERE venue_type IS NOT NULL AND venue_type != '' ORDER BY venue_type ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = []; 
}

// 2. Fetch 3 Featured Venues
try {
    $featured_stmt = $pdo->query("
        SELECT h.*, hi.image_url 
        FROM halls h
        LEFT JOIN hall_images hi ON h.hall_id = hi.hall_id AND hi.is_primary = 1
        WHERE h.venue_type IS NOT NULL
        ORDER BY h.hall_id DESC 
        LIMIT 3
    ");
    $featured_venues = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featured_venues = [];
}

// 3. Fetch Venue Types for the Category Cards
try {
    $type_stmt = $pdo->query("
        SELECT h.venue_type, MAX(hi.image_url) as image_url
        FROM halls h
        LEFT JOIN hall_images hi ON h.hall_id = hi.hall_id
        WHERE h.venue_type IS NOT NULL AND h.venue_type != ''
        GROUP BY h.venue_type
        LIMIT 8
    ");
    $venue_type_cards = $type_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $venue_type_cards = [];
}

// 4. Fetch Top 3 Popular Venue Types by Order/Reservation Count
try {
    $popular_stmt = $pdo->query("
        SELECT h.venue_type, COUNT(r.reservation_id) AS order_count
        FROM halls h
        LEFT JOIN reservations r ON h.hall_id = r.hall_id
        WHERE h.venue_type IS NOT NULL AND h.venue_type != ''
        GROUP BY h.venue_type
        ORDER BY order_count DESC, h.venue_type ASC
        LIMIT 3
    ");
    $popular_categories = $popular_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $popular_categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>VenueVista</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
    <link rel="stylesheet" href="common.css" />
    <script src="navigation.js" defer></script>
    <link rel="stylesheet" href="style.css" />
    <script src="../backend/js/locationSearch.js" defer></script>
  </head>
  <body>

    <!-- DYNAMIC NAVIGATION BAR -->
    <section id="navigation-section" class="home-navigation">
      <div id="container">
        <div id="nav-bar" class="site-nav">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                    <a href="list.php">List a Venue</a>
                    <a href="ownerdashboard.php">Owners Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                    <a href="admin.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                    <a href="mybookings.php">My Bookings</a>
                <?php endif; ?>
            <?php endif; ?>
          </nav>
          
          <div id="nav-buttons">
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Vendor'): ?>
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

    <section id="hero-section">
      <div id="hero-text">
        <p id="hero-title">DISCOVER EXTRAORDINARY SPACES</p>
        <h1 id="hero-heading">Every Moment Deserves a Perfect Venue</h1>
        <p id="hero-description">
          From intimate gatherings to grand celebrations, find and book the
          space that tells your story.
        </p>

        <form id="venue-search" action="search.php" method="get">
          <div id="venue-search-inputs">
            <div id="location-search">
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                Location
              </p>
              <input
                id="location-input"
                name="location"
                type="text"
                list="city-suggestions"
                placeholder="City or Area"
                autocomplete="on"
                required
              />
              <datalist id="city-suggestions"></datalist>
            </div>

            <div id="venue-type-search">
              <p>
                <i class="fa-solid fa-building" aria-hidden="true"></i> Venue
                Type
              </p>
              <select
                id="venue-type-select"
                name="venueType"
                title="Select a venue type"
                required
              >
                <option value="" selected disabled>Select a venue type</option>
                <?php if(!empty($categories)): ?>
                    <?php foreach($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>No categories available</option>
                <?php endif; ?>
              </select>
            </div>

            <div id="date-search">
              <p>
                <i class="fa-regular fa-calendar" aria-hidden="true"></i> Date
              </p>
              <input
                id="event-date-input"
                type="date"
                name="Date"
                placeholder="Select a date"
                required
              />
            </div>
          </div>

          <div id="search-btn-container">
            <button id="search-venues-button" type="submit">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              Search Venues
            </button>
          </div>
          <p id="search-message" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>

    <section id="featured-venue">
      <div>
        <p>Handpicked for You</p>
        <h1>Featured Venues</h1>
        <a href="search.php">View All Venues →</a>

        <div id="featured-venue-cards">
          <?php if (!empty($featured_venues)): ?>
            <?php foreach ($featured_venues as $venue): ?>
              <div class="featured-venue-card">
                <div class="featured-venue-card-image">
                  <?php 
                    $imgSrc = !empty($venue['image_url']) ? $venue['image_url'] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; 
                  ?>
                  <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['name'] ?? 'Venue') ?>" />
                  <div class="hall-type-tag"><?= htmlspecialchars($venue['venue_type'] ?? 'Venue') ?></div>
                  <div class="featured-tag">Featured</div>
                  <div class="rating-tag">★4.8</div>
                </div>

                <div class="location-details">
                  <p><?= htmlspecialchars($venue['name'] ?? 'Unnamed Venue') ?></p>
                  <p class="cost">$<?= number_format($venue['price'] ?? $venue['base_price_per_hour'] ?? 0, 2) ?>/day</p>
                  <br />
                  <p>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i> 
                    <?= htmlspecialchars($venue['district'] ?? 'Unknown Location') ?>
                  </p>
                </div>
                <div class="hall-details">
                  <p>
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                    Up to <?= htmlspecialchars($venue['capacity'] ?? 'N/A') ?> guests
                  </p>
                  <p>
                    <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 0 reviews
                  </p>
                  <a href="search.php?hall_id=<?= urlencode($venue['hall_id'] ?? '') ?>">View Details → </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p>No featured venues available at the moment.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section id="browse-venue-type">
      <div id="venue-card-container">
        <p>Every Occasion</p>
        <h1>Browse by Venue Type</h1>

        <div id="venue-type-cards">
          <?php if (!empty($venue_type_cards)): ?>
            <?php foreach ($venue_type_cards as $card): ?>
              <a class="venue-type-card" href="search.php?venueType=<?= urlencode($card['venue_type']) ?>">
                <?php 
                  $cardImg = !empty($card['image_url']) ? $card['image_url'] : 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=400&auto=format&fit=crop&q=60'; 
                ?>
                <img src="<?= htmlspecialchars($cardImg) ?>" alt="<?= htmlspecialchars($card['venue_type']) ?>" />
                <p><?= htmlspecialchars($card['venue_type']) ?></p>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <p>No categories available.</p>
          <?php endif; ?>
        </div>

      </div>
    </section>

    <section id="about-page">
      <div id="about-container">
        <div id="about-image-container">
          <img src="https://images.unsplash.com/photo-1478146059778-26028b07395a?w=800&auto=format&fit=crop&q=70" alt="about-image">
          <div class="details-box">4.9★<small>Average Rating</small></div>
          <div class="details-box">2,000+<small>Events Hosted</small></div>
          <div class="details-box">1,500+<small>Happy Clients</small></div>
          <div class="details-box">500+<small>Venues Listed</small></div>
        </div>

        <div id="about-details">
          <p>Why VenueVista</p>
          <h1>The Art of Finding the Perfect Space</h1>

          <div class="detail-text">
            <div>✦</div>
            <div>
              <h3>Curated Listings</h3>
              <p>Every venue is personally reviewed and approved by our team to ensure the highest standards.</p>
            </div>
          </div>
          <br>
          <div class="detail-text">
            <div>◎</div>
            <div>
              <h3>Instant Booking</h3>
              <p>Reserve your dream venue in minutes. Real-time availability, no back-and-forth emails.</p>
            </div>
          </div>
          <br>
          <div class="detail-text">
            <div>⬡</div>
            <div>
              <h3>Transparent Pricing</h3>
              <p>All packages, packages and catering options clearly laid out. No hidden fees, ever.</p>
            </div>
          </div>
          <br>
          <div class="detail-text">
            <div>❖</div>
            <div>
              <h3>Verified Reviews</h3>
              <p>Every review is tied to a real booking — authentic experiences from real guests.</p>
            </div>
          </div>
            
        </div>  
      </div>
    </section>

    <section id="list-venue-section">
      <div id="list-venue-body">
        <p>OWN A VENUE?</p>
        <p>List Your Space</p>
        <h1>Reach Thousands</h1>
        <p>Join hundreds of venue owners who trust VenueVista to
            connect them with the right clients.</p>
        <a href="loginchoice.php">Start Listing Today →</a>
      </div>
    </section>

    <section id="footer-section">
      <div id="footer-body">
        <div id="footer-top">

          <div id="footer-details-block">
            <h1>VenueVista</h1>
            <p>Discover extraordinary spaces for life's most meaningful moments. Where every venue tells a story.</p>
            <div class="social-links">
              <a href="">INSTAGRAM</a>
              <a href="">PINTEREST</a>
              <a href="">FACEBOOK</a>
            </div>
          </div>

          <!-- DYNAMIC DISCOVER SECTION -->
          <div class="footer-nav-links">
            <p>DISCOVER</p>
            <a href="search.php">Browse Venues</a>
            <?php foreach ($popular_categories as $pop_category): ?>
              <a href="search.php?venueType=<?= urlencode($pop_category) ?>">
                <?= htmlspecialchars($pop_category) ?>
              </a>
            <?php endforeach; ?>
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

    <script src="script.js" defer></script>
  </body>
</html>