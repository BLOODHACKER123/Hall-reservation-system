<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'Customer') {
    header("Location: loginchoice.php");
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

// Handle direct remove request
if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    $del = $pdo->prepare("DELETE FROM wishlists WHERE customer_id = ? AND hall_id = ?");
    $del->execute([$customer_id, $remove_id]);
    header("Location: wishlist.php");
    exit;
}

// Fetch Wishlist Items
try {
    $stmt = $pdo->prepare("
        SELECT h.*, 
               MAX(hi.image_url) AS image_url,
               COALESCE(ROUND(AVG(rv.rating), 1), 0) AS avg_rating,
               COUNT(DISTINCT rv.review_id) AS review_count,
               MAX(w.added_at) AS added_at
        FROM wishlists w
        JOIN halls h ON w.hall_id = h.hall_id
        LEFT JOIN hall_images hi ON h.hall_id = hi.hall_id AND hi.is_primary = 1
        LEFT JOIN reservations res ON h.hall_id = res.hall_id
        LEFT JOIN reviews rv ON res.reservation_id = rv.reservation_id
        WHERE w.customer_id = ? AND h.is_active = 1
        GROUP BY h.hall_id
        ORDER BY added_at DESC
    ");
    $stmt->execute([$customer_id]);
    $wishlist_venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $wishlist_count = count($wishlist_venues);
} catch (Throwable $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Wishlist | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <link rel="stylesheet" href="search.css">
  <script src="navigation.js" defer></script>
  <style>
      .wishlist-container {
        max-width: 1200px; 
        margin: 40px auto; 
        padding: 0 20px; 
        min-height: 60vh; 
      }

      .wishlist-header { 
        border-bottom: 2px solid #eee; 
        padding-bottom: 15px; 
        margin-bottom: 30px; 
        display: flex; 
        flex-wrap: wrap; 
        gap: 16px; 
        justify-content: space-between; 
        align-items: center; 
      }

      .wishlist-header h1 { 
        font-family: 'Playfair Display', serif; 
        color: #523530; 
        margin: 0; 
      }
      
      /*nav bar badge */

      .nav-wishlist-link { 
        position: relative; 
        display: inline-flex; 
        align-items: center; 
        gap: 4px; 
        text-decoration: none; 
      }

      .wishlist-badge {
        background: #e53935; 
        color: #fff; 
        font-size: 0.75rem; 
        font-weight: bold; 
        border-radius: 10px; 
        padding: 2px 6px; 
        min-width: 16px; 
        text-align: center; 
      }

      .remove-btn { 
        color: #c62828; 
        text-decoration: none; 
        font-size: 0.85rem; 
        font-weight: bold; 
      }

      .remove-btn:hover { 
        text-decoration: underline; 
      }

  </style>
</head>
<body>

  <!-- Dynamic Navigation Bar -->
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <div id="nav-buttons">
            <?php include __DIR__ . '/navbar_user_menu.php'; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="wishlist-container">
    <div class="wishlist-header">
      <div>
        <h1>Saved Venues</h1>
        <p style="color: #666; margin: 5px 0 0 0;">Spaces you have bookmarked for your events.</p>
      </div>
      <a href="search.php" style="color: #523530; font-weight: bold; text-decoration: none;">+ Browse More</a>
    </div>

    <?php if (empty($wishlist_venues)): ?>
      <div style="text-align: center; padding: 60px 20px; background: #fafafa; border-radius: 8px; border: 1px dashed #ccc;">
        <h2 style="color: #523530; margin-bottom: 10px;">Your wishlist is empty</h2>
        <p style="color: #666; margin-bottom: 25px;">Explore our directory and tap the heart icon on any venue to save it.</p>
        <a href="search.php" style="background: #523530; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Explore Venues</a>
      </div>
    <?php else: ?>
      <div id="featured-venue-cards" 
      style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 25px;">
        <?php foreach ($wishlist_venues as $venue): ?>
          <?php $imgSrc = !empty($venue['image_url']) ? $venue['image_url'] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; ?>
          <div class="venue-card">
            <div class="venue-card-image" style="position: relative;">
              <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['name']) ?>" />
              <div class="hall-type-tag"><?= htmlspecialchars($venue['venue_type'] ?? 'Venue') ?></div>
              <div class="rating-tag">★<?= $venue['avg_rating'] > 0 ? $venue['avg_rating'] : 'New' ?></div>
            </div>

            <div class="location-details">
              <p><?= htmlspecialchars($venue['name']) ?></p>
              <p class="cost">$<?= number_format($venue['base_price_per_hour'], 2) ?>/day</p>

              <br/>

              <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($venue['district']) ?></p>

            </div>

            <div class="hall-details" 
            style="display: flex; justify-content: space-between; align-items: center;">
              <a href="search.php?hall_id=<?= urlencode($venue['hall_id']) ?>">View Details →</a>
              <a href="wishlist.php?remove=<?= $venue['hall_id'] ?>" class="remove-btn" onclick="return confirm('Remove this venue from your wishlist?');">Remove ✕</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
