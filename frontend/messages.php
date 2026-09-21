<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_type =$_SESSION['user_type'] ?? 'Customer';

// Active chat selection from query params (e.g. from "Contact Customer" or venue card)
$active_partner_id = intval($_GET['partner_id'] ?? 0);
$active_hall_id = intval($_GET['hall_id'] ?? 0);

// Fetch conversation list
try {
    // 1. Fetch thread summaries cleanly
    $thread_stmt = $pdo->prepare("
        SELECT 
            t.partner_id,
            t.hall_id,
            h.name AS hall_name,
            u.first_name,
            u.last_name,
            u.user_type AS partner_type,
            t.last_message_time,
            last_m.content AS last_content,
            t.unread_count
        FROM (
            SELECT 
                m.partner_id,
                m.hall_id,
                MAX(m.sent_at) AS last_message_time,
                MAX(m.message_id) AS last_message_id,
                SUM(CASE WHEN m.receiver_id = :current_user_1 AND m.read_status = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM (
                SELECT 
                    message_id,
                    hall_id,
                    receiver_id,
                    sent_at,
                    read_status,
                    CASE WHEN sender_id = :current_user_2 THEN receiver_id ELSE sender_id END AS partner_id
                FROM messages
                WHERE sender_id = :current_user_3 OR receiver_id = :current_user_4
            ) m
            GROUP BY m.partner_id, m.hall_id
        ) t
        JOIN messages last_m ON t.last_message_id = last_m.message_id
        JOIN halls h ON t.hall_id = h.hall_id
        JOIN users u ON t.partner_id = u.user_id
        ORDER BY t.last_message_time DESC
    ");
    
    // Explicit named parameters eliminate any positional placeholder count bugs
    $thread_stmt->execute([
        ':current_user_1' => $user_id,
        ':current_user_2' => $user_id,
        ':current_user_3' => $user_id,
        ':current_user_4' => $user_id
    ]);
    $conversations = $thread_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Select first conversation if none selected in URL
    if ($active_partner_id <= 0 && !empty($conversations)) {
        $active_partner_id = (int)$conversations[0]['partner_id'];
        $active_hall_id = (int)$conversations[0]['hall_id'];
    }

    // 3. Fetch active chat partner header info ONLY if valid IDs exist
    $partner_info = null;
    if ($active_partner_id > 0 && $active_hall_id > 0) {
        $p_stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.user_type, h.name AS hall_name 
            FROM users u
            INNER JOIN halls h ON h.hall_id = :hall_id
            WHERE u.user_id = :partner_id
        ");
        $p_stmt->execute([
            ':hall_id'    => $active_hall_id,
            ':partner_id' => $active_partner_id
        ]);
        $partner_info = $p_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <link rel="stylesheet" href="messages.css">
</head>
<body>

  <!-- Dynamic Navigation Bar -->
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <nav id="nav-links">
          <a href="search.php">Browse Venues</a>
          <?php if ($user_type === 'Vendor'): ?>
              <a href="ownerdashboard.php">Owners Dashboard</a>
          <?php elseif ($user_type === 'Customer'): ?>
              <a href="wishlist.php">Wishlist</a>
              <a href="mybookings.php">My Bookings</a>
          <?php endif; ?>
          <a href="messages.php" class="active">Messages</a>
        </nav>
        <div id="nav-buttons">
          <?php include __DIR__ . '/navbar_user_menu.php'; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="inbox-wrapper">
    <!-- Left Conversation Sidebar -->
    <aside class="threads-list">
      <div class="threads-header">Conversations</div>
      <?php if (empty($conversations) && !$partner_info): ?>
          <p style="padding: 20px; color: #888; font-size: 0.9rem;">No active conversations.</p>
      <?php else: ?>
          <?php foreach ($conversations as$c): ?>
              <?php $isActive = ($c['partner_id'] ==$active_partner_id && $c['hall_id'] ==$active_hall_id); ?>
              <a href="messages.php?partner_id=<?= $c['partner_id'] ?>&hall_id=<?=$c['hall_id'] ?>" 
                 class="thread-item <?= $isActive ? 'active' : '' ?>">
                  <div class="thread-top">
                      <span class="thread-name"><?= htmlspecialchars($c['first_name'] . ' ' .$c['last_name']) ?></span>
                      <?php if ($c['unread_count'] > 0): ?>
                          <span class="thread-badge"><?= $c['unread_count'] ?></span>
                      <?php endif; ?>
                  </div>
                  <div class="thread-venue">📍 <?= htmlspecialchars($c['hall_name']) ?></div>
                  <div class="thread-snippet"><?= htmlspecialchars($c['last_content'] ?: 'No messages yet') ?></div>
              </a>
          <?php endforeach; ?>
      <?php endif; ?>
    </aside>

    <!-- Right Active Chat Window -->
    <section class="chat-window">
      <?php if ($partner_info): ?>
        <div class="chat-header">
          <div>
            <h3><?= htmlspecialchars($partner_info['first_name'] . ' ' . $partner_info['last_name']) ?> (<?= htmlspecialchars($partner_info['user_type']) ?>)</h3>
            <small>Regarding <strong><?= htmlspecialchars($partner_info['hall_name']) ?></strong></small>
          </div>
        </div>

        <div class="messages-body" id="messagesBody">
          <!-- Messages populated dynamically via JavaScript -->
        </div>

        <form class="chat-footer" id="chatForm">
          <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off" required>
          <button type="submit">Send</button>
        </form>
      <?php else: ?>
        <div class="no-chat-selected">
          <p>Select a conversation or initiate a message with a vendor/customer.</p>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const activePartnerId = <?= json_encode($active_partner_id) ?>;
      const activeHallId = <?= json_encode($active_hall_id) ?>;
      const currentUserId = <?= json_encode($user_id) ?>;
      const messagesBody = document.getElementById('messagesBody');
      const chatForm = document.getElementById('chatForm');
      const chatInput = document.getElementById('chatInput');

      if (!activePartnerId || !activeHallId || !messagesBody) return;

      function fetchMessages() {
        fetch(`../backend/ajax/messages_handler.php?action=fetch_thread&partner_id=${activePartnerId}&hall_id=${activeHallId}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const atBottom = messagesBody.scrollHeight - messagesBody.scrollTop <= messagesBody.clientHeight + 50;

              messagesBody.innerHTML = data.messages.map(m => {
                const isMine = parseInt(m.sender_id) === currentUserId;
                const time = new Date(m.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return `
                  <div class="msg-bubble ${isMine ? 'msg-mine' : 'msg-theirs'}">
                    <div>${escapeHtml(m.content)}</div>
                    <span class="msg-time">${time}</span>
                  </div>
                `;
              }).join('');

              if (atBottom) {
                messagesBody.scrollTop = messagesBody.scrollHeight;
              }
            }
          })
          .catch(err => console.error('Fetch messages error:', err));
      }

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }

      // Initial fetch & poll every 3 seconds
      fetchMessages();
      setInterval(fetchMessages, 3000);

      // Handle message submission
      chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const content = chatInput.value.trim();
        if (!content) return;

        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('receiver_id', activePartnerId);
        fd.append('hall_id', activeHallId);
        fd.append('content', content);

        chatInput.value = '';

        fetch('../backend/ajax/messages_handler.php', {
          method: 'POST',
          body: fd
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            fetchMessages();
          } else {
            alert(data.message || 'Failed to send message.');
          }
        })
        .catch(err => console.error('Send error:', err));
      });
    });
  </script>
</body>
</html>