<?php
require_once __DIR__ .'/../auth/guard.php';
requireRole('admin');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode(['message' => 'Use GET to view pending venues.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $statement = $pdo->prepare(
        "SELECT id, owner_id, name, type, city,
                min_capacity, max_capacity, base_price,
                status, created_at
         FROM venues
         WHERE status = 'pending'
         ORDER BY created_at ASC, id ASC"
    );

    $statement->execute();

    $venues = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'Pending venues retrieved successfully.',
        'count' => count($venues),
        'venues' => $venues
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        'message' => 'Unable to retrieve pending venues.'
    ]);
}

exit;
?>
