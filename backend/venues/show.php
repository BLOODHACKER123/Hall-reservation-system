<?php

require_once __DIR__ . '/../auth/guard.php';

requireRole('owner');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode(['message' => 'Use GET to view a venue.']);
    exit;
}

$id = $_GET['id'] ?? '';

if (!is_string($id)) {
    http_response_code(400);
    echo json_encode(['message' => 'A valid venue ID is required.']);
    exit;
}

$venueId = filter_var($id,FILTER_VALIDATE_INT,['options' => ['min_range' => 1]]);

if($venueId === false) {
    http_response_code(400);
    echo json_encode(['message' => 'A valid venue ID is required.']);
    exit;
}

require_once __DIR__ .'/../config/database.php';

try{ 
    $statement = $pdo->prepare(
        'SELECT id, name, type, city, address, description,
                min_capacity, max_capacity, base_price,
                rules, cancellation_policy, status,
                is_featured, created_at
         FROM venues
         WHERE id = :id AND owner_id = :owner_id
         LIMIT 1'
    );

     $statement->execute([
        'id' => $venueId,
        'owner_id' => $_SESSION['user_id']
    ]);

    $venue = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        http_response_code(404);
        echo json_encode(['message' => 'Venue not found.']);
        exit;
    }

    echo json_encode([
        'message' => 'Venue retrieved successfully.',
        'venue' => $venue
    ]);
}
catch(PDOException $error){
     error_log($error->getMessage());
     http_response_code(500);
     echo json_encode(['message' => 'Unable to retrieve the venue.']);
}

exit;

?>