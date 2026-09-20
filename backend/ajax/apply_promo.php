<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to apply discount codes.']);
    exit;
}

$hall_id = intval($_POST['hall_id'] ?? 0);
$promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));
$event_date = trim($_POST['event_date'] ?? date('Y-m-d'));

if ($hall_id <= 0 || empty($promo_code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid promo code.']);
    exit;
}

try {
    // 1. Fetch promo for this specific venue
    $stmt = $pdo->prepare("
        SELECT promo_id, promo_code, discount_rate, start_date, end_date, status 
        FROM promotions 
        WHERE hall_id = ? AND promo_code = ?
    ");
    $stmt->execute([$hall_id, $promo_code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Invalid promo code for this venue.']);
        exit;
    }

    // 2. Status verification
    if (strtolower($promo['status']) !== 'active') {
        echo json_encode(['success' => false, 'message' => 'This promo code is currently inactive.']);
        exit;
    }

    // 3. Date validity check (against event date or today's date)
    $today = date('Y-m-d');
    if ($today < $promo['start_date']) {
        echo json_encode(['success' => false, 'message' => "Promo code is not valid until {$promo['start_date']}."]);
        exit;
    }

    if ($today > $promo['end_date']) {
        // Mark as expired in DB
        $pdo->prepare("UPDATE promotions SET status = 'Expired' WHERE promo_id = ?")->execute([$promo['promo_id']]);
        echo json_encode(['success' => false, 'message' => 'This promo code has expired.']);
        exit;
    }

    // 4. Return valid discount percentage
    $discount_rate = floatval($promo['discount_rate']);

    echo json_encode([
        'success' => true,
        'promo_code' => $promo['promo_code'],
        'discount_rate' => $discount_rate,
        'message' => "Promo applied! {$discount_rate}% discount on base price."
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}