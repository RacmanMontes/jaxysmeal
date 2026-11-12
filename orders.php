<?php
require_once "config.php";
require_once "admin_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $stmt = $pdo->query(
        "SELECT
            o.id,
            o.user_id,
            o.total_amount,
            o.status,
            o.created_at,
            u.full_name AS customer_name,
            u.email AS customer_email
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC"
    );

    $orders = $stmt->fetchAll();

    $itemStmt = $pdo->prepare(
        "SELECT product_name, quantity, price, subtotal
         FROM order_items
         WHERE order_id = ?
         ORDER BY id ASC"
    );

    foreach ($orders as &$order) {
        $itemStmt->execute([$order["id"]]);
        $items = $itemStmt->fetchAll();

        $order["items"] = array_map(
            function ($item) {
                return [
                    "name" => $item["product_name"],
                    "quantity" => (int)$item["quantity"],
                    "price" => (float)$item["price"],
                    "subtotal" => (float)$item["subtotal"]
                ];
            },
            $items
        );

        $order["id"] = (int)$order["id"];
        $order["total"] = (float)$order["total_amount"];
        $order["customerName"] = $order["customer_name"];
        $order["email"] = $order["customer_email"];
        $order["createdAt"] = $order["created_at"];

        unset(
            $order["user_id"],
            $order["total_amount"],
            $order["customer_name"],
            $order["customer_email"],
            $order["created_at"]
        );
    }

    unset($order);

    echo json_encode([
        "success" => true,
        "orders" => $orders
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve customer orders."
    ]);
}