<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode(['message' => 'Use GET to browse venues.']);
    exit;
}

$city = $_GET['city'] ?? '';

if (!is_string($city)) {
    http_response_code(400);
    echo json_encode(['message' => 'City must be text.']);
    exit;
}

$city = trim($city);

if (mb_strlen($city, 'UTF-8') > 100) {
    http_response_code(400);
    echo json_encode(['message' => 'City must be 100 characters or fewer.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
   $sql = "SELECT v.id, v.name, v.type, v.city,
               v.min_capacity, v.max_capacity, v.base_price
        FROM venues AS v
        INNER JOIN users AS u ON u.id = v.owner_id
        WHERE v.status = 'approved'
          AND u.status = 'active'
          AND u.role = 'owner'";

$params = [];

if ($city !== '') {
    $sql .= ' AND v.city = :city';
    $params['city'] = $city;
}

$sql .= ' ORDER BY v.created_at DESC, v.id DESC LIMIT 50';

$statement = $pdo->prepare($sql);
$statement->execute($params);

    $venues = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'Approved venues retrieved successfully.',
        'count' => count($venues),
        'venues' => $venues
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        'message' => 'Unable to retrieve venues.'
    ]);
}

exit;