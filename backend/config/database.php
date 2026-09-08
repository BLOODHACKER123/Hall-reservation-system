<?php

$host = "127.0.0.1";
$username = "root";
$dbpassword ="Dimuth";
$dbname = "venuevista";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $dbpassword,
        [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $error) {
    die("Database connection failed: " . $error->getMessage());
}


?>