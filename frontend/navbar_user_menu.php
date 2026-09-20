<?php
// Determine session user details safely
$nav_user_id = $_SESSION['user_id'] ?? null;
$nav_user_type = $_SESSION['user_type'] ?? null;
$nav_user_name = $_SESSION['user_name'] ?? 'My Account';

// Fetch wishlist count for customers if not already available
$nav_wishlist_count = 0;
if ($nav_user_type === 'Customer' && isset($pdo)) {
    try {
        $w_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ?");
        $w_count_stmt->execute([$nav_user_id]);
        $nav_wishlist_count = (int)$w_count_stmt->fetchColumn();
    } catch (Throwable $e) {}
}
?>

<style>
  /* User Menu Dropdown Styles */
  .user-menu-container {
    position: relative;
    display: inline-block;
  }

  .user-menu-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #523530;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background 0.2s ease;
  }

  .user-menu-trigger:hover {
    background: #3d2723;
  }

  .user-avatar {
    width: 26px;
    height: 26px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
  }

  .user-dropdown-panel {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    background: #fff;
    min-width: 220px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    border-radius: 8px;
    border: 1px solid #eee;
    overflow: hidden;
    z-index: 1000;
    animation: dropdownFade 0.15s ease-out;
  }

  .user-dropdown-panel.show {
    display: block;
  }

  .user-dropdown-header {
    padding: 12px 16px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
  }

  .user-dropdown-header .role-tag {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #888;
    letter-spacing: 0.5px;
    font-weight: bold;
  }

  .user-dropdown-header .user-name {
    margin: 2px 0 0 0;
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
  }

  .user-dropdown-panel ul {
    list-style: none;
    margin: 0;
    padding: 6px 0;
  }

  .user-dropdown-panel li a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    color: #444;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background 0.15s;
  }

  .user-dropdown-panel li a:hover {
    background: #f5f5f5;
    color: #523530;
  }

  .user-dropdown-panel .divider {
    height: 1px;
    background: #eee;
    margin: 6px 0;
  }

  .user-dropdown-panel .logout-link {
    color: #c62828 !important;
  }

  .user-dropdown-panel .logout-link:hover {
    background: #fce8e6 !important;
  }

  .badge-counter {
    background: #e53935;
    color: #fff;
    font-size: 0.75rem;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
    line-height: 1;
  }

  @keyframes dropdownFade {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<?php if ($nav_user_id): ?>
  <div class="user-menu-container" id="userMenuContainer">
    <button type="button" class="user-menu-trigger" id="userMenuTrigger" aria-expanded="false">
      <span class="user-avatar">👤</span>
      <span><?= htmlspecialchars($nav_user_name) ?></span>
      <span style="font-size: 0.75rem;">▼</span>
    </button>

    <div class="user-dropdown-panel" id="userDropdownPanel">
      <div class="user-dropdown-header">
        <div class="role-tag"><?= htmlspecialchars($nav_user_type) ?> Account</div>
        <div class="user-name"><?= htmlspecialchars($nav_user_name) ?></div>
      </div>

      <ul>
        <!-- CUSTOMER SPECIFIC SHORTCUTS -->
        <?php if ($nav_user_type === 'Customer'): ?>
          <li><a href="search.php">🔍 Browse Venues</a></li>
          <li><a href="mybookings.php">📅 My Bookings</a></li>
          <li>
            <a href="wishlist.php">
              <span>❤️ My Wishlist</span>
              <span class="badge-counter" id="menu-wishlist-count"><?= $nav_wishlist_count ?></span>
            </a>
          </li>
          <li>
            <a href="#" style="opacity: 0.6; cursor: not-allowed;" title="Coming Soon">
              <span>🔔 Notifications</span>
              <small style="color: #999;">(Soon)</small>
            </a>
          </li>

        <!-- VENDOR SPECIFIC SHORTCUTS -->
        <?php elseif ($nav_user_type === 'Vendor'): ?>
          <li><a href="ownerdashboard.php#overview">📊 Dashboard Overview</a></li>
          <li><a href="ownerdashboard.php#venues">🏢 Manage Venues</a></li>
          <li><a href="ownerdashboard.php#bookings">📑 Booking Requests</a></li>
          <li><a href="ownerdashboard.php#financials">💰 Financials</a></li>
          <li><a href="list.php">➕ List New Venue</a></li>
          <li><a href="ownerdashboard.php#settings">⚙️ Business Settings</a></li>
          <li>
            <a href="#" style="opacity: 0.6; cursor: not-allowed;" title="Coming Soon">
              <span>🔔 Notifications</span>
              <small style="color: #999;">(Soon)</small>
            </a>
          </li>

        <!-- ADMIN SPECIFIC SHORTCUTS -->
        <?php elseif ($nav_user_type === 'Admin'): ?>
          <li><a href="admin.php">🛡️ Admin Dashboard</a></li>
          <li><a href="admin.php#approvals">⏳ Pending Approvals</a></li>
        <?php endif; ?>

        <div class="divider"></div>
        <li><a href="../backend/config/logout.php" class="logout-link">🚪 Logout</a></li>
      </ul>
    </div>
  </div>

  <script>
    (function() {
      const trigger = document.getElementById('userMenuTrigger');
      const panel = document.getElementById('userDropdownPanel');

      if (trigger && panel) {
        trigger.addEventListener('click', function(e) {
          e.stopPropagation();
          const isOpen = panel.classList.toggle('show');
          trigger.setAttribute('aria-expanded', isOpen);
        });

        document.addEventListener('click', function(e) {
          if (!panel.contains(e.target) && !trigger.contains(e.target)) {
            panel.classList.remove('show');
            trigger.setAttribute('aria-expanded', 'false');
          }
        });
      }
    })();
  </script>
<?php else: ?>
  <a id="login-button" href="loginchoice.php">Login</a>
<?php endif; ?>