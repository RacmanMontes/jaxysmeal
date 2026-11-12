<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/config.php";

try {
    $stmt = $pdo->query("
        SELECT id, name, category, price, image, description
        FROM products
        WHERE is_available = 1
        ORDER BY id DESC
    ");

    echo json_encode([
        "success" => true,
        "products" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (PDOException $e) {
    error_log("Products error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve products from the database."
    ]);
}