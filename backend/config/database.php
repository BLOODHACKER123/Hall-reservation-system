<?php

//db connection

$host = "127.0.0.1";
$username = "root";
$password ="";
$dbname = "venuevista";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $error) {
    die("Database connection failed: " . $error->getMessage());
}

echo "Connected";

?>