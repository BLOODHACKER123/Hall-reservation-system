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
  <style>
      .inbox-wrapper { max-width: 1100px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: 320px 1fr; border: 1px solid #ddd; border-radius: 8px; background: #fff; height: 75vh; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.05); }
      @media (max-width: 768px) { .inbox-wrapper { grid-template-columns: 1fr; height: auto; } }
      
      /* Left Sidebar List */
      .threads-list { border-right: 1px solid #eee; overflow-y: auto; background: #fafafa; }
      .threads-header { padding: 15px 20px; border-bottom: 1px solid #eee; font-weight: bold; color: #523530; background: #fff; }
      .thread-item { display: block; padding: 14px 18px; border-bottom: 1px solid #eee; text-decoration: none; color: inherit; transition: background 0.15s; }
      .thread-item:hover { background: #f2f2f2; }
      .thread-item.active { background: #fff; border-left: 4px solid #523530; }
      .thread-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
      .thread-name { font-weight: bold; font-size: 0.95rem; color: #333; }
      .thread-badge { background: #e53935; color: #fff; font-size: 0.7rem; font-weight: bold; border-radius: 10px; padding: 2px 6px; }
      .thread-venue { font-size: 0.8rem; color: #888; margin-bottom: 4px; }
      .thread-snippet { font-size: 0.85rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

      /* Right Chat Window */
      .chat-window { display: flex; flex-direction: column; height: 100%; }
      .chat-header { padding: 15px 20px; border-bottom: 1px solid #eee; background: #fff; display: flex; justify-content: space-between; align-items: center; }
      .chat-header h3 { margin: 0; color: #333; font-size: 1.05rem; }
      .chat-header small { color: #888; font-size: 0.85rem; }
      .messages-body { flex: 1; padding: 20px; overflow-y: auto; background: #fdfdfd; display: flex; flex-direction: column; gap: 12px; }
      
      .msg-bubble { max-width: 65%; padding: 10px 14px; border-radius: 8px; font-size: 0.92rem; line-height: 1.4; position: relative; word-wrap: break-word; }
      .msg-mine { align-self: flex-end; background: #523530; color: #fff; border-bottom-right-radius: 2px; }
      .msg-theirs { align-self: flex-start; background: #f0f0f0; color: #333; border-bottom-left-radius: 2px; }
      .msg-time { display: block; font-size: 0.7rem; margin-top: 4px; text-align: right; opacity: 0.75; }
      
      .chat-footer { padding: 12px 15px; border-top: 1px solid #eee; background: #fff; display: flex; gap: 10px; }
      .chat-footer input { flex: 1; padding: 10px 14px; border: 1px solid #ccc; border-radius: 20px; outline: none; font-family: inherit; font-size: 0.92rem; }
      .chat-footer button { background: #523530; color: #fff; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: bold; transition: background 0.15s; }
      .chat-footer button:hover { background: #3d2723; }
      .no-chat-selected { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #888; }
  </style>
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