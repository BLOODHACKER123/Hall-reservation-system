<?php
require_once __DIR__ . '/../auth/guard.php';

requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode(['message' => 'Use POST to approve a venue.']);
    exit;
}

$id = $_POST['id'] ?? '';

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
        "UPDATE venues
         SET status = 'approved'
         WHERE id = :id AND status = 'pending'"
    );

    $statement->execute(['id' => $venueId]);

    if ($statement->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT id FROM venues WHERE id = :id'
        );

        $check->execute(['id' => $venueId]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['message' => 'Venue not found.']);
        } else {
            http_response_code(409);
            echo json_encode([
                'message' => 'Venue is no longer pending.'
            ]);
        }

        exit;
    }

    echo json_encode([
        'message' => 'Venue approved successfully.',
        'venue_id' => $venueId,
        'status' => 'approved'
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);
    echo json_encode(['message' => 'Unable to approve the venue.']);
}

exit;


?>