<?php
$nav_user_id =$_SESSION['user_id'] ?? null;
$nav_user_type =$_SESSION['user_type'] ?? null;
$nav_user_name =$_SESSION['user_name'] ?? 'My Account';

$nav_wishlist_count = 0;
$nav_unread_notifications = 0;

if ($nav_user_id && isset($pdo)) {
    try {
        if ($nav_user_type === 'Customer') {
            $w_count =$pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ?");
            $w_count->execute([$nav_user_id]);
            $nav_wishlist_count = (int)$w_count->fetchColumn();
        }

        $n_count =$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
        $n_count->execute([$nav_user_id]);
        $nav_unread_notifications = (int)$n_count->fetchColumn();
    } catch (Throwable $e) {}
}
?>

<style>
  .nav-user-bar {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .nav-icon-container {
    position: relative;
    display: inline-block;
  }

  .nav-bell-btn {
    background: transparent;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 50%;
    transition: background 0.2s;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .nav-bell-btn:hover {
    background: rgba(0,0,0,0.06);
  }

  .nav-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #e53935;
    color: #fff;
    font-size: 0.7rem;
    font-weight: bold;
    border-radius: 10px;
    padding: 1px 5px;
    min-width: 14px;
    text-align: center;
    line-height: 1.2;
  }

  /* Notifications Dropdown */
  .notif-dropdown-panel {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 320px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    z-index: 1000;
    overflow: hidden;
  }
  .notif-dropdown-panel.show { display: block; }
  .notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
  }
  .notif-header h4 { margin: 0; color: #523530; font-size: 0.95rem; }
  .notif-mark-read {
    background: none;
    border: none;
    color: #1a73e8;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 500;
  }
  .notif-mark-read:hover { text-decoration: underline; }
  .notif-list {
    max-height: 350px;
    overflow-y: auto;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .notif-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f2f2f2;
    transition: background 0.15s;
  }
  .notif-item.unread { background: #fdf6f0; }
  .notif-item:hover { background: #f9f9f9; }
  .notif-title { font-weight: bold; font-size: 0.88rem; color: #333; margin-bottom: 3px; }
  .notif-msg { font-size: 0.82rem; color: #666; margin: 0 0 5px 0; line-height: 1.4; }
  .notif-time { font-size: 0.75rem; color: #999; }
  .notif-empty { padding: 30px 20px; text-align: center; color: #888; font-size: 0.88rem; }

  /* User Menu Dropdown */
  .user-menu-container { position: relative; display: inline-block; }
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
  .user-menu-trigger:hover { background: #3d2723; }
  .user-avatar {
    width: 26px; height: 26px;
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
  }
  .user-dropdown-panel.show { display: block; }
  .user-dropdown-header {
    padding: 12px 16px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
  }
  .user-dropdown-header .role-tag {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #888;
    font-weight: bold;
  }
  .user-dropdown-header .user-name { margin: 2px 0 0 0; font-weight: 600; color: #333; font-size: 0.95rem; }
  .user-dropdown-panel ul { list-style: none; margin: 0; padding: 6px 0; }
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
  .user-dropdown-panel li a:hover { background: #f5f5f5; color: #523530; }
  .user-dropdown-panel .divider { height: 1px; background: #eee; margin: 6px 0; }
  .user-dropdown-panel .logout-link { color: #c62828 !important; }
  .user-dropdown-panel .logout-link:hover { background: #fce8e6 !important; }
</style>

<?php if ($nav_user_id): ?>
  <div class="nav-user-bar">
    <!-- NOTIFICATIONS BELL & DROPDOWN -->
    <div class="nav-icon-container">
      <button type="button" class="nav-bell-btn" id="notifBellBtn" aria-label="Notifications" title="Notifications">
        🔔
        <span class="nav-badge" id="notifBadge" style="<?= $nav_unread_notifications > 0 ? '' : 'display:none;' ?>">
          <?= $nav_unread_notifications ?>
        </span>
      </button>

      <div class="notif-dropdown-panel" id="notifDropdownPanel">
        <div class="notif-header">
          <h4>Notifications</h4>
          <button type="button" class="notif-mark-read" id="notifMarkReadBtn">Mark all as read</button>
        </div>
        <ul class="notif-list" id="notifList">
          <li class="notif-empty">Loading notifications...</li>
        </ul>
      </div>
    </div>

    <!-- USER PROFILE MENU -->
    <div class="user-menu-container">
      <button type="button" class="user-menu-trigger" id="userMenuTrigger">
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
          <?php if ($nav_user_type === 'Customer'): ?>
            <li><a href="search.php">🔍 Browse Venues</a></li>
            <li><a href="mybookings.php">📅 My Bookings</a></li>
            <li>
              <a href="wishlist.php">
                <span>❤️ My Wishlist</span>
                <span class="nav-badge" style="position:static;"><?= $nav_wishlist_count ?></span>
              </a>
            </li>
            <li><a href="messages.php">💬 Messages</a></li>
          <?php elseif ($nav_user_type === 'Vendor'): ?>
            <li><a href="ownerdashboard.php#overview">📊 Dashboard Overview</a></li>
            <li><a href="ownerdashboard.php#venues">🏢 Manage Venues</a></li>
            <li><a href="ownerdashboard.php#bookings">📑 Booking Requests</a></li>
            <li><a href="messages.php">💬 Guest Inquiries</a></li>
            <li><a href="ownerdashboard.php#financials">💰 Financials</a></li>
            <li><a href="list.php">➕ List New Venue</a></li>
            <li><a href="ownerdashboard.php#settings">⚙️ Settings</a></li>
          <?php elseif ($nav_user_type === 'Admin'): ?>
            <li><a href="admin.php">🛡️ Admin Dashboard</a></li>
          <?php endif; ?>

          <div class="divider"></div>
          <li><a href="../backend/config/logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const bellBtn = document.getElementById('notifBellBtn');
      const notifPanel = document.getElementById('notifDropdownPanel');
      const notifList = document.getElementById('notifList');
      const notifBadge = document.getElementById('notifBadge');
      const markReadBtn = document.getElementById('notifMarkReadBtn');

      const userTrigger = document.getElementById('userMenuTrigger');
      const userPanel = document.getElementById('userDropdownPanel');

      // Fetch & populate notifications via AJAX
      function loadNotifications() {
        fetch('../backend/ajax/notifications_handler.php?action=fetch')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              // Update badge
              if (data.unread_count > 0) {
                notifBadge.textContent = data.unread_count;
                notifBadge.style.display = 'block';
              } else {
                notifBadge.style.display = 'none';
              }

              // Render list
              if (data.notifications.length === 0) {
                notifList.innerHTML = '<li class="notif-empty">No notifications yet.</li>';
              } else {
                notifList.innerHTML = data.notifications.map(n => `
                  <li class="notif-item ${parseInt(n.read_status) === 0 ? 'unread' : ''}">
                    <div class="notif-title">${escapeHtml(n.title)}</div>
                    <p class="notif-msg">${escapeHtml(n.message)}</p>
                    <span class="notif-time">${new Date(n.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})}</span>
                  </li>
                `).join('');
              }
            }
          })
          .catch(err => console.error('Failed loading notifications:', err));
      }

      function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
      }

      // Bell click toggle
      if (bellBtn && notifPanel) {
        bellBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          if (userPanel) userPanel.classList.remove('show');
          const isVisible = notifPanel.classList.toggle('show');
          if (isVisible) loadNotifications();
        });
      }

      // Mark all as read
      if (markReadBtn) {
        markReadBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          fetch('../backend/ajax/notifications_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_read'
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              notifBadge.style.display = 'none';
              document.querySelectorAll('.notif-item.unread').forEach(item => item.classList.remove('unread'));
            }
          });
        });
      }

      // User Menu toggle
      if (userTrigger && userPanel) {
        userTrigger.addEventListener('click', (e) => {
          e.stopPropagation();
          if (notifPanel) notifPanel.classList.remove('show');
          userPanel.classList.toggle('show');
        });
      }

      // Dismiss on click outside
      document.addEventListener('click', (e) => {
        if (notifPanel && !notifPanel.contains(e.target) && !bellBtn.contains(e.target)) {
          notifPanel.classList.remove('show');
        }
        if (userPanel && !userPanel.contains(e.target) && !userTrigger.contains(e.target)) {
          userPanel.classList.remove('show');
        }
      });
    })();
  </script>
<?php else: ?>
  <a id="login-button" href="loginchoice.php">Login</a>
<?php endif; ?>