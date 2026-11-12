<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in to view your orders."
    ]);

    exit;
}

try {
    $ordersStmt = $pdo->prepare("
        SELECT id, total_amount, status, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
    ");

    $ordersStmt->execute([
        (int) $_SESSION["user_id"]
    ]);

    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsStmt = $pdo->prepare("
        SELECT product_id, product_name, quantity, price, subtotal
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ");

    foreach ($orders as &$order) {
        $itemsStmt->execute([
            (int) $order["id"]
        ]);

        $order["items"] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    unset($order);

    echo json_encode([
        "success" => true,
        "orders" => $orders
    ]);

} catch (PDOException $e) {
    error_log("My orders error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve your orders."
    ]);
}