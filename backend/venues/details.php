<?php
header('Content-Type: application/json; charset=utf-8');

if(!$_SERVER['REQUEST_METHOD']==='GET'){
 http_response_code(405);
    header('Allow: GET');

    echo json_encode(['message' => 'Use GET to view venue details.']);
    exit;  
}

$id = $_GET['id'] ?? '';

if (!is_string($id)) {
    http_response_code(400);
    echo json_encode(['message' => 'A valid venue ID is required.']);
    exit;
}

$venueId = filter_var($id,FILTER_VALIDATE_INT,['options' => ['min_range' => 1]]);

if ($venueId === false) {
    http_response_code(400);
    echo json_encode(['message' => 'A valid venue ID is required.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $statement = $pdo->prepare(
        "SELECT v.id, v.name, v.type, v.city, v.address,
                v.description, v.min_capacity, v.max_capacity,
                v.base_price, v.rules, v.cancellation_policy
         FROM venues AS v
         INNER JOIN users AS u ON u.id = v.owner_id
         WHERE v.id = :id
           AND v.status = 'approved'
           AND u.status = 'active'
           AND u.role = 'owner'
         LIMIT 1"
    );

    $statement->execute(['id' => $venueId]);

    $venue = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        http_response_code(404);
        echo json_encode(['message' => 'Venue not found.']);
        exit;
    }

    echo json_encode([
        'message' => 'Venue details retrieved successfully.',
        'venue' => $venue
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);
    echo json_encode(['message' => 'Unable to retrieve venue details.']);
}

exit;

?>