<?php
require_once __DIR__. '/../auth/guard.php';

requireRole('owner');

header('Content-Type: application/json; charset=utf-8');
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['message' => 'Use POST to update a venue.']);
    exit;
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';

if (!is_string($id) || !is_string($name)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid form data.']);
    exit;
}

$venueId = filter_var($id,FILTER_VALIDATE_INT,['options' => ['min_range' => 1]]);

$name = trim($name);

if ($venueId === false || $name === '' || mb_strlen($name, 'UTF-8') > 150) {
    http_response_code(422);
    echo json_encode([
        'message' => 'Provide a valid venue ID and a name of 1–150 characters.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$details=[];
$errors=[];

$limits = [
    'type' => 50,
    'city' => 100,
    'description' => 5000
];

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

    $details[$field] = $value;
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
        $details[$field] = $capacity;
    }
}

if (
    isset($details['min_capacity'], $details['max_capacity']) &&
    $details['min_capacity'] > $details['max_capacity']
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
        $details['base_price'] = $price;
    }
}

$optionalLimits = [
    'address' => 255,
    'rules' => 5000,
    'cancellation_policy' => 5000
];

foreach ($optionalLimits as $field => $maxLength) {
    $value = $_POST[$field] ?? '';

    if (!is_string($value)) {
        $errors[] = "$field must be text.";
        continue;
    }

    $value = trim($value);

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        $errors[] = "$field must be $maxLength characters or fewer.";
    }

    $details[$field] = $value === '' ? null : $value;
}

if (!empty($errors)) {
    http_response_code(422);

    echo json_encode([
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}


try {
   $statement = $pdo->prepare(
    "UPDATE venues
     SET name = :name,
         type = :type,
         city = :city,
         description = :description,
         min_capacity = :min_capacity,
         max_capacity = :max_capacity,
         base_price = :base_price,
         address = :address,
         rules = :rules,
         cancellation_policy = :cancellation_policy,
         status = 'pending',
         is_featured = 0
     WHERE id = :id AND owner_id = :owner_id"
);

   $statement->execute([
    'name' => $name,
    'type' => $details['type'],
    'city' => $details['city'],
    'description' => $details['description'],
    'min_capacity' => $details['min_capacity'],
    'max_capacity' => $details['max_capacity'],
    'base_price' => $details['base_price'],
    'address' => $details['address'],
    'rules' => $details['rules'],
    'cancellation_policy' => $details['cancellation_policy'],
    'id' => $venueId,
    'owner_id' => $_SESSION['user_id']
]);

    if ($statement->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT id FROM venues
             WHERE id = :id AND owner_id = :owner_id'
        );

        $check->execute([
            'id' => $venueId,
            'owner_id' => $_SESSION['user_id']
        ]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['message' => 'Venue not found.']);
            exit;
        }
    }

    echo json_encode([
        'message' => 'Venue saved. Awaiting approval.',
        'venue_id' => $venueId,
        'status' => 'pending'
    ]);

} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Unable to update the venue.']);
}

exit;


?>