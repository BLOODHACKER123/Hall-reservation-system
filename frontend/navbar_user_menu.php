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
    min-width: 0;
    max-width: 100%;
    align-items: center;
    gap: 12px;
  }

  .nav-icon-container {
    position: relative;
    display: inline-block;
  }

  .nav-bell-btn {
    width:36px ;
    height: 36px;
    background: #8c5e58;
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
    transition: background 0.2s ease, transform 0.15s ease;
    box-shadow: 0 4px 10px rgba(82, 53, 48, 0.12);
  }

  .nav-bell-btn:hover {
    background: #523530;
    transform: translateY(-1px);
  
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

  .divider-wrap {
  list-style: none;
  padding: 0;

}

  #audit-logs-btn{
    display: flex;
    align-items: center;
    gap: 10px;
    background: #8c5e58;
    color: #fff;
    border: none;
    padding: 6px 14px 6px 8px;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 500;
    line-height: 1;
    transition: background 0.2s ease, transform 0.15s ease;
    box-shadow: 0 4px 10px rgba(82, 53, 48, 0.12);
  }

  #audit-logs-btn:hover{
    background-color: #523530;
    transform: translateY(-1px);
  }

    /* Notifications Dropdown */
  .notif-dropdown-panel {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 340px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.14);
    z-index: 1000;
    overflow: hidden;
  }

  .notif-dropdown-panel.show {
    display: block;
  }

  .notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
  }

  .notif-header h4 {
    margin: 0;
    color: #523530;
    font-size: 1rem;
    font-weight: 600;
  }

  .notif-mark-read {
    background: transparent;
    border: none;
    color: #8c5e58;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 600;
    padding: 4px 6px;
    border-radius: 4px;
  }

  .notif-mark-read:hover {
    background: #f3e8e5;
    text-decoration: none;
  }

  .notif-list {
    background-color: #fff;
    max-height: 350px;
    overflow-y: auto;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .notif-item {
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
    background: #fff;
    transition: background 0.15s ease;
  }

  .notif-item.unread {
    background: #fdf6f0;
    border-left: 3px solid #8c5e58;
    padding-left: 13px;
  }

  .notif-item:hover {
    background: #f8f3f1;
  }

  .notif-title {
    font-weight: 600;
    font-size: 0.92rem;
    color: #3d302d;
    margin-bottom: 5px;
    line-height: 1.3;
  }

  .notif-msg {
    font-size: 0.84rem;
    color: #5f5754;
    margin: 0 0 6px 0;
    line-height: 1.5;
  }

  .notif-time {
    font-size: 0.75rem;
    color: #8a817e;
  }

  .notif-empty {
    padding: 30px 20px;
    text-align: center;
    color: #777;
    font-size: 0.88rem;
    background: #fff;
  }

  /* User Menu Dropdown */
  .user-menu-container { position: relative; display: inline-block; min-width: 0; max-width: 100%; }
  .user-menu-trigger {
    display: flex;
    max-width: 100%;
    align-items: center;
    gap: 10px;
    background: #8c5e58;
    color: #fff;
    border: none;
    padding: 6px 14px 6px 8px;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 500;
    line-height: 1;
    transition: background 0.2s ease, transform 0.15s ease;
    box-shadow: 0 4px 10px rgba(82, 53, 48, 0.12);
  }
  .user-menu-trigger:hover { 
    background: #3d2723; 
    transform: translateY(-1px); 
}
  .user-avatar {
    width: 28px;
    height: 28px;
    min-width: 28px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    overflow: hidden;
    box-sizing: border-box;
  }

  .nav-icon-image,
  .menu-icon-image,
  .menu-item-icon {
    display: block;
    width: 18px;
    height: 18px;
    object-fit: contain;
  }

  .menu-item-icon {
    width: 16px;
    height: 16px;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .user-menu-trigger > span:nth-child(2) {
    display: inline-block;
    min-width: 0;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
  .user-dropdown-panel
  .show { 
    display: block; 
  }

  .user-dropdown-header {
    padding: 12px 16px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
  }
  .user-dropdown-header 
  .role-tag {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #888;
    font-weight: bold;
  }
  .user-dropdown-header 
  .user-name { 
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
    gap: 8px;
    padding: 10px 16px;
    color: #444;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background 0.15s;
  }

  .user-dropdown-panel li a 
  .menu-link-label {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
  }

  .user-dropdown-panel li a:hover { 
    background: #f5f5f5; 
    color: #523530; 
}

  .user-dropdown-panel 
  .divider { 
    height: 1px; 
    background: #eee; 
    margin: 6px 0; 
}

  .user-dropdown-panel 
  .logout-link { 
    color: #c62828 !important; 
  }

  .user-dropdown-panel 
  .logout-link:hover { 
    background: #fce8e6 !important; 
  }

  .menu-disabled-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    background: transparent;
    border: none;
    padding: 10px 16px;
    color: #999;
    text-decoration: none;
    font: inherit;
    font-size: 0.9rem;
    text-align: left;
    cursor: not-allowed;
    opacity: 0.75;
  }

 
  @media (max-width: 1100px) {

    .nav-user-bar { 
      display: grid; 
      grid-template-columns: 36px minmax(0, 1fr); 
      width: 100%; 
    }

    .nav-icon-container, 
    .user-menu-container { 
      display: contents; 
    }

    .nav-bell-btn { 
      grid-column: 1; 
      grid-row: 1; 
    }

    .user-menu-trigger { 
      grid-column: 2; 
      grid-row: 1; 
      justify-self: start; 
    }

    .notif-dropdown-panel, 
    .user-dropdown-panel {
      position: static;
      grid-column: 1 / -1;
      grid-row: 2;
      width: 100%;
      min-width: 0;
    }

    .notif-header { 
      flex-wrap: wrap; 
      gap: 8px; 
    }
  }
</style>

<?php if ($nav_user_id): ?>
  <div class="nav-user-bar">

    <!-- NOTIFICATIONS BELL & DROPDOWN -->
    <div class="nav-icon-container">
      <button type="button" class="nav-bell-btn" id="notifBellBtn" aria-label="Notifications" title="Notifications">
        <img src="./images/notifacation.png" alt="Notifications" class="nav-icon-image" />
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
        <span class="user-avatar"><img src="./images/user.png" alt="User" class="menu-icon-image" /></span>
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
            <li>
              <a href="search.php">
                <span class="menu-link-label">
                <img src="./images/search-interface-symbol.png" alt="Browse Venues" class="menu-item-icon" />
                <span>Browse Venues</span>
              </span>
            </a>
          </li>

          <li>
            <a href="mybookings.php">
              <span class="menu-link-label">
                <img src="./images/book.png" alt="My Bookings" class="menu-item-icon" />
                <span>My Bookings</span>
              </span>
            </a>
          </li>
          <li>
            <a href="wishlist.php">
              <span class="menu-link-label">
                <img src="./images/wishlist.png" alt="My Wishlist" class="menu-item-icon">
                <span>My Wishlist</span>
              </span>
                <span class="nav-badge" style="position:static;"><?= $nav_wishlist_count ?></span>
              </a>
            </li>
            <li>
              <a href="messages.php">
                <span class="menu-link-label">
                  <img src="./images/message.png" alt="Messages" class="menu-item-icon">
                  <span>Messages</span>
                </span>
              </a>
            </li>

          <?php elseif ($nav_user_type === 'Vendor'): ?>
            <li>
              <a href="ownerdashboard.php#overview">
                <span class="menu-link-label">
                  <img src="./images/setting.png" alt="Dashboard Overview" class="menu-item-icon">
                  <span>Dashboard Overview</span>
                </span>
              </a>
            </li>
            <li>
              <a href="ownerdashboard.php#venues">
                <span class="menu-link-label">
                  <img src="./images/mall.png" alt="Manage Venues" class="menu-item-icon"/>
                  <span>Manage Venues</span>
                </span>
              </a>
            </li>
            <li>
              <a href="ownerdashboard.php#bookings">
                <span class="menu-link-label">
                  <img src="./images/book.png" alt="Booking Requests" class="menu-item-icon" />
                  <span>Booking Requests</span>
                </span>
              </a>
            </li>
            <li>
              <a href="messages.php">
                <span class="menu-link-label">
                  <img src="./images/history.png" alt="Guest Inquiries" class="menu-item-icon" />
                  <span>Guest Inquiries</span>
                </span>
              </a>
            </li>
            <li>
              <a href="ownerdashboard.php#financials">
                <span class="menu-link-label">
                  <img src="./images/financial-analysis.png" alt="Financials" class="menu-item-icon"/>
                  <span>Financials</span>
                </span>
              </a>
            </li>
            <li>
              <a href="list.php">
                <span class="menu-link-label">
                  <img src="./images/plus.png" alt="List New Venue" class="menu-item-icon"/>
                  <span>List New Venue</span>
                </span>
              </a>
            </li>
            <li>
              <a href="ownerdashboard.php#settings">
                <span class="menu-link-label">
                  <img src="./images/settings.png" alt="Settings" class="menu-item-icon" />
                  <span>Settings</span>
                </span>
              </a>
            </li>

          <?php elseif ($nav_user_type === 'Admin'):?>
            <li>
              <a href="admin_audit_logs.php">
                <span class="menu-link-label">
                  <img src="./images/history.png" alt="" class="menu-item-icon" />
                  <span>Audit Logs</span>
                </span>
              </a>
            </li>
            <li>
              <a href="admin.php">
                <span class="menu-link-label">
                  <img src="./images/administrator.png" alt="Admin Dashboard" class="menu-item-icon" />
                  <span>Admin Dashboard</span>
                </span>
              </a>
            </li>

            <li>
              <a href="search.php">
                <span class="menu-link-label">
                  <img src="./images/mall.png" alt="Admin Dashboard" class="menu-item-icon" />
                  <span>Browse Venues</span>
                </span>
              </a>
            </li>
          <?php endif; ?>

          <li class="divider-wrap">
            <div class="divider"></div>
          </li>
          <li><a href="../backend/config/logout.php" class="logout-link"><span class="menu-link-label"><img src="./images/logout.png" alt="Logout" class="menu-item-icon" /><span>Logout</span></span></a></li>
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
