<?php
require_once __DIR__ . '/../auth/guard.php';

requireRole('owner');

header('Content-Type: application/json; charset=utf-8'); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode(['message' => 'Use POST to create a venue.']);
    exit;
}

$limits = [
    'name' => 150,
    'type' => 50,
    'city' => 100,
    'description' => 5000
];

$data = [];
$errors = [];

foreach ($limits as $field => $maxLength) {
    $value = $_POST[$field] ?? '';

    if (!is_string($value)) {
        $errors[] = "$field must be text.";
        continue;
    }

    $value = trim($value);

    if ($value === '') {
        $errors[] = "$field is required.";
    } elseif (mb_strlen($value, 'UTF-8') > $maxLength) {
        $errors[] = "$field must be $maxLength characters or fewer.";
    }

    $data[$field] = $value;
}


foreach (['min_capacity', 'max_capacity'] as $field) {
    $value = $_POST[$field] ?? '';

    if (!is_string($value)) {
        $errors[] = "$field must be a whole number.";
        continue;
    }

    $capacity = filter_var(
        trim($value),
        FILTER_VALIDATE_INT,
        ['options' => [
            'min_range' => 1,
            'max_range' => 1000000
        ]]
    );

    if ($capacity === false) {
        $errors[] = "$field must be a whole number from 1 to 1000000.";
    } else {
        $data[$field] = $capacity;
    }
}

if (
    isset($data['min_capacity'], $data['max_capacity']) &&
    $data['min_capacity'] > $data['max_capacity']
) {
    $errors[] = 'Minimum capacity cannot exceed maximum capacity.';
}


$price = $_POST['base_price'] ?? '';

if (!is_string($price)) {
    $errors[] = 'Base price must be a decimal number.';
} else {
    $price = trim($price);

    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $price)) {
        $errors[] = 'Base price must be between 0 and 99999999.99, with up to 2 decimal places.';
    } else {
        $data['base_price'] = $price;
    }
}

if (!empty($errors)) {
    http_response_code(422);

    echo json_encode([
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $statement = $pdo->prepare(
        'INSERT INTO venues (
            owner_id, name, type, city, description,
            min_capacity, max_capacity, base_price
        ) VALUES (
            :owner_id, :name, :type, :city, :description,
            :min_capacity, :max_capacity, :base_price
        )'
    );

    $statement->execute([
        'owner_id' => $_SESSION['user_id'],
        'name' => $data['name'],
        'type' => $data['type'],
        'city' => $data['city'],
        'description' => $data['description'],
        'min_capacity' => $data['min_capacity'],
        'max_capacity' => $data['max_capacity'],
        'base_price' => $data['base_price']
    ]);

    $venueId = (int) $pdo->lastInsertId();

    http_response_code(201);

    echo json_encode([
        'message' => 'Venue created successfully. Awaiting approval.',
        'venue_id' => $venueId,
        'status' => 'pending'
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        'message' => 'Unable to create the venue. Please try again.'
    ]);
}

exit;