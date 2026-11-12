<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config.php";

function checkoutResponse(
    bool $success,
    string $message,
    int $status = 200,
    array $extra = []
): void {
    http_response_code($status);

    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    checkoutResponse(false, "POST requests only.", 405);
}

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {
    checkoutResponse(
        false,
        "Please log in with a customer account before checking out.",
        401
    );
}

$data = json_decode(file_get_contents("php://input"), true);
$items = $data["items"] ?? null;

if (!is_array($items) || count($items) === 0) {
    checkoutResponse(false, "Your cart is empty.", 400);
}

$quantities = [];

foreach ($items as $item) {
    $productId = filter_var(
        $item["product_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantity = filter_var(
        $item["quantity"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !$productId ||
        !$quantity ||
        $quantity < 1 ||
        $quantity > 99
    ) {
        checkoutResponse(
            false,
            "Invalid product or quantity.",
            400
        );
    }

    $quantities[$productId] =
        ($quantities[$productId] ?? 0) + $quantity;
}

try {
    $pdo->beginTransaction();

    $findProduct = $pdo->prepare("
        SELECT id, name, price
        FROM products
        WHERE id = ? AND is_available = 1
        LIMIT 1
    ");

    $insertOrder = $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, status)
        VALUES (?, ?, 'Pending')
    ");

    $insertItem = $pdo->prepare("
        INSERT INTO order_items
        (order_id, product_id, product_name, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $total = 0;
    $resolved = [];

    foreach ($quantities as $productId => $quantity) {
        $findProduct->execute([$productId]);

        $product = $findProduct->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException(
                "One of the selected products is no longer available."
            );
        }

        $price = (float) $product["price"];
        $subtotal = $price * $quantity;

        $total += $subtotal;

        $resolved[] = [
            $product,
            $quantity,
            $price,
            $subtotal
        ];
    }

    $insertOrder->execute([
        (int) $_SESSION["user_id"],
        $total
    ]);

    $orderId = (int) $pdo->lastInsertId();

    foreach ($resolved as [$product, $quantity, $price, $subtotal]) {
        $insertItem->execute([
            $orderId,
            (int) $product["id"],
            $product["name"],
            $quantity,
            $price,
            $subtotal
        ]);
    }

    $pdo->commit();

    checkoutResponse(
        true,
        "Your order has been saved successfully.",
        201,
        [
            "order_id" => $orderId,
            "total_amount" => $total
        ]
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Checkout error: " . $e->getMessage());

    checkoutResponse(
        false,
        $e instanceof RuntimeException
            ? $e->getMessage()
            : "Unable to save your order. Check the database table structure and PHP error log.",
        500
    );
}