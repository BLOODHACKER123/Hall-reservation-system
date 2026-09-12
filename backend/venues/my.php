<?php
require_once __DIR__ . '/../auth/guard.php';

requireRole('owner');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode(['message' => 'Use GET to view your venues.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $statement = $pdo->prepare(
        'SELECT id, name, type, city, min_capacity,
                max_capacity, base_price, status, created_at
         FROM venues
         WHERE owner_id = :owner_id
         ORDER BY created_at DESC, id DESC'
    );

    $statement->execute([
        'owner_id' => $_SESSION['user_id']
    ]);

    $venues = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'Your venues retrieved successfully.',
        'count' => count($venues),
        'venues' => $venues
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        'message' => 'Unable to retrieve your venues.'
    ]);
}

exit;