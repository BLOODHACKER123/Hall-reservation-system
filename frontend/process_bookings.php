<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/config/notify.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

if ($_SESSION['user_type'] !== 'Customer') {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['user_id'];$error_msg = '';

$hall_id = intval($_POST['hall_id'] ?? $_GET['hall_id'] ?? 0);

if ($hall_id <= 0) {
    header("Location: search.php");
    exit;
}

try {
    $stmt =$pdo->prepare("SELECT * FROM halls WHERE hall_id = ? AND is_active = 1");
    $stmt->execute([$hall_id]);
    $venue =$stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        die("
        <h2 style='text-align:center; color:#c5221f; margin-top:50px;'>Venue not available or does not exist.</h2>"
        );
    }

    $cust_stmt =$pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?");
    $cust_stmt->execute([$customer_id]);
    $customer_info =$cust_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Database Error: " . $e->getMessage());
}

$base_price = floatval($venue['base_price_per_hour']);$security_deposit = floatval($venue['security_deposit']);$total_amount = $base_price +$security_deposit;

$event_date = trim($_POST['event_date'] ?? '');
$start_time = trim($_POST['start_time'] ?? '09:00');
$end_time = trim($_POST['end_time'] ?? '22:00');
$guest_count = intval($_POST['guest_count'] ?? 0);
$special_requests = trim($_POST['special_requests'] ?? '');
$applied_promo_code = strtoupper(trim($_POST['applied_promo_code'] ?? ''));

$billing_name = trim($_POST['billing_name'] ?? ($customer_info['first_name'] . ' ' .$customer_info['last_name']));
$billing_phone = trim($_POST['billing_phone'] ?? $customer_info['phone']);$billing_email = trim($_POST['billing_email'] ?? $customer_info['email']);

// Process booking and payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $card_holder = trim($_POST['card_holder'] ?? '');
    $card_number_raw = preg_replace('/\D/', '',$_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');
    $card_type_selected = trim($_POST['card_type'] ?? 'Visa');

    // Validation
    if (empty($event_date) || empty($start_time) || empty($end_time) || $guest_count <= 0) {$error_msg = "Please fill in all event details (date, times, and guest count).";
    } elseif (strtotime($event_date) < strtotime(date('Y-m-d'))) {$error_msg = "Event date cannot be in the past.";
    } elseif (strtotime("$event_date$end_time") <= strtotime("$event_date$start_time")) {
        $error_msg = "Event end time must be after the start time.";
    } elseif ($guest_count > $venue['capacity']) {$error_msg = "Guest count exceeds the venue maximum capacity ({$venue['capacity']}).";
    } elseif (empty($card_holder) || strlen($card_number_raw) < 13 || empty($card_expiry) || empty($card_cvv)) {$error_msg = "Please enter valid credit or debit card details.";
    } else {
        try {
            // Verify and recalculate promo discount server-side
            $discount_amount = 0;
            if (!empty($applied_promo_code)) {
                $p_stmt =$pdo->prepare("
                    SELECT discount_rate FROM promotions 
                    WHERE hall_id = ? AND promo_code = ? AND status = 'Active' 
                      AND CURDATE() BETWEEN start_date AND end_date
                ");
                $p_stmt->execute([$hall_id,$applied_promo_code]);
                $promo_rate =$p_stmt->fetchColumn();
                if ($promo_rate) {$discount_amount = ($base_price * floatval($promo_rate)) / 100;
                }
            }

            $final_total = max(0, ($base_price - $discount_amount) +$security_deposit);

            $pdo->beginTransaction();

            $start_datetime = "$event_date$start_time:00";
            $end_datetime = "$event_date$end_time:00";

            // 1. Insert Reservation (Table: reservations)
            $res_stmt =$pdo->prepare("
                INSERT INTO reservations (
                    customer_id, hall_id, start_datetime, end_datetime, 
                    status, locked_price_per_hour, total_booking_amount, 
                    guest_count, special_requests, created_at
                ) VALUES (?, ?, ?, ?, 'Pending', ?, ?, ?, ?, NOW())
            ");
            $res_stmt->execute([
                $customer_id,$hall_id, $start_datetime,$end_datetime,
                $base_price,$final_total, $guest_count,$special_requests
            ]);
            $reservation_id =$pdo->lastInsertId();

            // 2. Insert Base Payment (Table: payments)
            $pay_stmt =$pdo->prepare("
                INSERT INTO payments (
                    reservation_id, amount, payment_type, payment_method, status, paid_at
                ) VALUES (?, ?, 'advance', 'card', 'Success', NOW())
            ");
            $pay_stmt->execute([
                $reservation_id,$final_total
            ]);
            $payment_id =$pdo->lastInsertId();

            // 3. Insert Card Specific Subclass (Table: payment_card)
            $transaction_ref = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
            $card_last4 = substr($card_number_raw, -4);
            
            $card_stmt =$pdo->prepare("
                INSERT INTO payment_card (
                    payment_id, transaction_reference, card_last4, card_type
                ) VALUES (?, ?, ?, ?)
            ");
            $card_stmt->execute([
                $payment_id,$transaction_ref, $card_last4,$card_type_selected
            ]);

            // 4. Update Customer stats
            $pdo->prepare(" UPDATE customers SET booking_count = booking_count + 1,loyalty_points = loyalty_points + 10 WHERE user_id = ?")->execute([$customer_id]);

            $pdo->commit();

            // 5. Send notifications BEFORE redirecting
            createNotification(
                $pdo,$customer_id, 
                "Booking Pending", 
                "Your reservation #$reservation_id for {$venue['name']} was placed successfully.", 
                "booking"
            );

            createNotification(
                $pdo, 
                (int)$venue['vendor_id'], 
                "New Booking Request", 
                "You have a new reservation request #$reservation_id for {$venue['name']}.", 
                "booking"
            );

            // 6. Redirect to dashboard
            header("Location: mybookings.php");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {$pdo->rollBack();
            }
            $error_msg = "Payment processing failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complete Reservation | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <style>

      .checkout-wrapper { 
        max-width: 1100px; 
        margin: 40px auto; 
        padding: 0 20px; 
        display: grid; 
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 40px; 
    }

      .checkout-wrapper > *, 
      .form-row 
      .form-group { 
        min-width: 0; 
    }

      .summary-item, 
      .summary-total { 
        gap: 12px; 
        flex-wrap: wrap; 
        overflow-wrap: anywhere; 
    }
    
      .summary-item span:last-child, 
      .summary-total span:last-child { 
        margin-left: auto; 
        text-align: right; 
    }

      @media (max-width: 900px) { 
        .checkout-wrapper { 
            grid-template-columns: 1fr; 
        } 
    }

      .checkout-panel { 
        background: #fff; 
        border: 1px solid #ddd; 
        border-radius: 8px; 
        padding: 30px; 
        box-shadow: 0 4px 14px rgba(0,0,0,0.03); 
    }

      .checkout-panel h2 { 
        margin-top: 0; 
        color: #523530; 
        border-bottom: 1px solid #eee; 
        padding-bottom: 12px; 
        margin-bottom: 20px; 
        font-size: 1.3rem; 
    }

      .form-section-title { 
        font-weight: bold; 
        color: #523530; 
        font-size: 1.05rem; 
        margin: 25px 0 15px 0; 
        border-bottom: 1px dashed #ccc; 
        padding-bottom: 5px; 
    }

      .form-group { 
        margin-bottom: 15px; 
    }

      .form-group label { 
        display: block; 
        font-weight: bold; 
        margin-bottom: 5px; 
        font-size: 0.9rem; 
        color: #333; 
    }

      .form-group input, 
      .form-group select, 
      .form-group textarea { 
        width: 100%; 
        padding: 11px; 
        border: 1px solid #ccc; 
        border-radius: 6px; 
        box-sizing: border-box; 
        font-family: inherit; 
    }

      .form-row { 
        display: flex; 
        gap: 15px; 
    }

      .form-row .form-group { 
        flex: 1; 
    }

      .summary-item { 
        display: flex; 
        justify-content: space-between; 
        margin-bottom: 12px; 
        font-size: 0.95rem; 
        color: #555; 
    }

      .summary-total { 
        display: flex; 
        justify-content: space-between; 
        margin-top: 15px; 
        padding-top: 15px; 
        border-top: 2px solid #523530; 
        font-size: 1.25rem; 
        font-weight: bold; 
        color: #333; 
    }

      .btn-pay { 
        width: 100%; 
        background: #523530; 
        color: #fff; 
        border: none; 
        padding: 15px; 
        font-size: 1.1rem; 
        font-weight: bold; 
        border-radius: 6px; 
        cursor: pointer; 
        margin-top: 20px; 
        transition: background 0.2s; 
    }

      .btn-pay:hover { 
        background: #3d2723; 
    }

      .badge-info { 
        background: #e8f0fe; 
        color: #1a73e8; 
        padding: 8px 12px; 
        border-radius: 4px; 
        font-size: 0.85rem; 
        margin-bottom: 20px; 
        display: block; 
    }
      
      /* Promo Code Component */

      .promo-box { 
        display: flex; 
        gap: 8px; 
        margin-top: 15px; 
    }

      .promo-box input { 
        flex: 1; 
        padding: 10px; 
        border: 1px dashed #523530; 
        border-radius: 6px; 
        text-transform: uppercase; 
        font-weight: bold; 
        font-family: inherit; 
    }

      .promo-box button { 
        background: #523530; 
        color: #fff; 
        border: none; 
        padding: 10px 16px; 
        border-radius: 6px; 
        cursor: pointer; 
        font-weight: bold; 
    }

      .promo-msg { 
        font-size: 0.82rem; 
        margin-top: 6px; 
        display: none; 
    }

      .promo-msg.success { 
        color: #2e7d32; 
        display: block; 
    }

      .promo-msg.error { 
        color: #c5221f; 
        display: block; 
    }

      @media (max-width: 600px) {
          .checkout-wrapper { 
            margin: 24px auto; 
            padding: 0 16px; 
            gap: 24px; 
        }
          .checkout-panel { 
            padding: 20px; 
        }
        
          .form-row { 
            flex-direction: column; 
            gap: 0; 
        }
      }
  </style>
</head>
<body>

  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <nav id="nav-links">
          <a href="search.php">Browse Venues</a>
          <a href="wishlist.php">Wishlist</a>
          <a href="mybookings.php">My Bookings</a>
        </nav>
        <div id="nav-buttons">
          <?php include __DIR__ . '/navbar_user_menu.php'; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="checkout-wrapper">
    <div class="checkout-panel">
        <h2>Complete Your Reservation</h2>

        <?php if (!empty($error_msg)): ?>
            <div style="background: #fce8e6; color: #c5221f; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem;">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <form action="process_bookings.php" method="POST" id="checkoutForm">
            <input type="hidden" name="confirm_payment" value="1">
            <input type="hidden" name="hall_id" id="form_hall_id" value="<?= $venue['hall_id'] ?>">
            <input type="hidden" name="applied_promo_code" id="form_applied_promo" value="">

            <!-- 1. EVENT PARTICULARS -->
            <div class="form-section-title" style="margin-top: 0;">1. Event Particulars</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_date">Event Date <em>*</em></label>
                    <input type="date" id="event_date" name="event_date" value="<?= htmlspecialchars($event_date) ?>" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="guest_count">
                        Estimated Guests <em>*</em> 
                        <small style="color: #666; font-weight: normal;">(Max: <?= htmlspecialchars($venue['capacity']) ?>)</small>
                    </label>
                    <input 
                        type="number" 
                        id="guest_count" 
                        name="guest_count" 
                        value="<?= $guest_count > 0 ? htmlspecialchars($guest_count) : '' ?>" 
                        placeholder="Max <?= htmlspecialchars($venue['capacity']) ?>" 
                        min="1" 
                        max="<?= htmlspecialchars($venue['capacity']) ?>" 
                        oninput="if(parseInt(this.value) > <?= (int)$venue['capacity'] ?>) { this.setCustomValidity('Guest count cannot exceed maximum venue capacity of <?= (int)$venue['capacity'] ?>.'); } else { this.setCustomValidity(''); }"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_time">Start Time <em>*</em></label>
                    <input type="time" id="start_time" name="start_time" value="<?= htmlspecialchars($start_time) ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time <em>*</em></label>
                    <input type="time" id="end_time" name="end_time" value="<?= htmlspecialchars($end_time) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="special_requests">Special Requests / Layout Needs (Optional)</label>
                <textarea id="special_requests" name="special_requests" rows="3" placeholder="Stage setup, catering arrangements, parking notes..."><?= htmlspecialchars($special_requests) ?></textarea>
            </div>

            <!-- 2. CONTACT DETAILS -->
            <div class="form-section-title">2. Contact &amp; Billing Info</div>

            <div class="form-group">
                <label for="billing_name">Primary Contact Name <em>*</em></label>
                <input type="text" id="billing_name" name="billing_name" value="<?= htmlspecialchars($billing_name) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="billing_phone">Phone Number <em>*</em></label>
                    <input type="text" id="billing_phone" name="billing_phone" value="<?= htmlspecialchars($billing_phone) ?>" required>
                </div>
                <div class="form-group">
                    <label for="billing_email">Confirmation Email <em>*</em></label>
                    <input type="email" id="billing_email" name="billing_email" value="<?= htmlspecialchars($billing_email) ?>" required>
                </div>
            </div>

            <!-- 3. PAYMENT PARTICULARS (payment_card subclass) -->
            <div class="form-section-title">3. Card Payment Details</div>

            <div class="form-group">
                <label for="card_type">Card Type</label>
                <select id="card_type" name="card_type">
                    <option value="Visa">Visa</option>
                    <option value="Mastercard">Mastercard</option>
                    <option value="Amex">American Express</option>
                </select>
            </div>

            <div class="form-group">
                <label for="card_holder">Cardholder Name <em>*</em></label>
                <input type="text" id="card_holder" name="card_holder" placeholder="Name as printed on card" required>
            </div>

            <div class="form-group">
                <label for="card_number">Card Number <em>*</em></label>
                <input type="text" id="card_number" name="card_number" placeholder="•••• •••• •••• ••••" maxlength="19" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="card_expiry">Expiry Date <em>*</em></label>
                    <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label for="card_cvv">CVV / CVC <em>*</em></label>
                    <input type="password" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4" required>
                </div>
            </div>

            <button type="submit" class="btn-pay" id="btnPaySubmit">Pay $<?= number_format($total_amount, 2) ?> &amp; Confirm Reservation</button>
        </form>
    </div>

    <!-- Right Column: Order Summary -->
    <div>
        <div class="checkout-panel" style="background: #fafafa; position: sticky; top: 100px;">
            <h2>Reservation Summary</h2>

            <div class="summary-item">
                <span><strong>Venue:</strong></span>
                <span><?= htmlspecialchars($venue['name']) ?></span>
            </div>
            <div class="summary-item">
                <span><strong>Type:</strong></span>
                <span><?= htmlspecialchars($venue['venue_type']) ?></span>
            </div>
            <div class="summary-item">
                <span><strong>Location:</strong></span>
                <span><?= htmlspecialchars($venue['district']) ?></span>
            </div>
            <div class="summary-item">
                <span><strong>Max Capacity:</strong></span>
                <span><?= htmlspecialchars($venue['capacity']) ?> guests</span>
            </div>

            <hr style="border:0; border-top: 1px dashed #ccc; margin: 15px 0;">

            <div class="summary-item">
                <span>Base Rate</span>
                <span>$<?= number_format($base_price, 2) ?></span>
            </div>
            <div class="summary-item" id="discountSummaryRow" style="display: none; color: #2e7d32; font-weight: bold;">
                <span id="discountLabel">Promo Discount</span>
                <span id="discountValue">-$0.00</span>
            </div>
            <div class="summary-item">
                <span>Refundable Deposit</span>
                <span>$<?= number_format($security_deposit, 2) ?></span>
            </div>

            <div class="summary-total">
                <span>Total Due Now</span>
                <span id="displayTotalDue">$<?= number_format($total_amount, 2) ?></span>
            </div>

            <!-- PROMO CODE INPUT BOX -->
            <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px;">
                <label style="font-size: 0.88rem; font-weight: bold; color: #523530;">Have a Promo Code?</label>
                <div class="promo-box">
                    <input type="text" id="promoInput" placeholder="ENTER CODE" maxlength="50">
                    <button type="button" id="btnApplyPromo">Apply</button>
                </div>
                <div id="promoMsg" class="promo-msg"></div>
            </div>

            <div class="badge-info" style="margin-top: 20px;">
                🛡️ <strong>Booking Guarantee:</strong> Your deposit is held securely and refunded subject to the venue's cancellation policy.
            </div>
            
            <a href="search.php?hall_id=<?= $venue['hall_id'] ?>" style="display:block; text-align: center; color: #666; font-size: 0.9rem; text-decoration: none;">← Cancel and return to venue specs</a>
        </div>
    </div>
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

  <script>
    // Format card number & expiry
    document.getElementById('card_number').addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/[^\d]/g, '').replace(/(.{4})/g, '$1 ').trim();
    });

    document.getElementById('card_expiry').addEventListener('input', function (e) {
        let val = e.target.value.replace(/[^\d]/g, '');
        if (val.length >= 2) {
            e.target.value = val.substring(0, 2) + '/' + val.substring(2, 4);
        } else {
            e.target.value = val;
        }
    });

    // Asynchronous Promo Code Calculation
    const basePrice = <?= json_encode($base_price) ?>;
    const securityDeposit = <?= json_encode($security_deposit) ?>;
    const hallId = <?= json_encode($venue['hall_id']) ?>;

    document.getElementById('btnApplyPromo').addEventListener('click', function() {
        const promoInput = document.getElementById('promoInput');
        const code = promoInput.value.trim().toUpperCase();
        const promoMsg = document.getElementById('promoMsg');
        const eventDate = document.getElementById('event_date').value;

        if (!code) {
            promoMsg.textContent = 'Please enter a code.';
            promoMsg.className = 'promo-msg error';
            return;
        }

        const fd = new FormData();
        fd.append('hall_id', hallId);
        fd.append('promo_code', code);
        fd.append('event_date', eventDate);

        fetch('../backend/ajax/apply_promo.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                promoMsg.textContent = data.message;
                promoMsg.className = 'promo-msg success';

                // Calculate discounted amounts
                const discountAmount = (basePrice * data.discount_rate) / 100;
                const newTotal = (basePrice - discountAmount) + securityDeposit;

                // Update UI Summary
                document.getElementById('discountSummaryRow').style.display = 'flex';
                document.getElementById('discountLabel').textContent = `Promo (${data.discount_rate}%)`;
                document.getElementById('discountValue').textContent = `-$${discountAmount.toFixed(2)}`;
                document.getElementById('displayTotalDue').textContent = `$${newTotal.toFixed(2)}`;
                document.getElementById('btnPaySubmit').textContent = `Pay $${newTotal.toFixed(2)} & Confirm Reservation`;

                // Set hidden field to pass code to backend
                document.getElementById('form_applied_promo').value = data.promo_code;
                promoInput.disabled = true;
                this.disabled = true;
                this.textContent = 'Applied ✓';
            } else {
                promoMsg.textContent = data.message;
                promoMsg.className = 'promo-msg error';
            }
        })
        .catch(err => {
            console.error('Promo error:', err);
            promoMsg.textContent = 'Failed to apply promo code.';
            promoMsg.className = 'promo-msg error';
        });
    });
  </script>
</body>
</html>
