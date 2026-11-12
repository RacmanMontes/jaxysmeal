<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "config.php";
require_once "admin_guard.php";

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST requests only."
    ]);
    exit;
}

$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");
$description = trim($_POST["description"] ?? "");
$price = $_POST["price"] ?? "";

$allowedCategories = [
    "Meals",
    "Snacks",
    "Drinks",
    "Desserts",
    "Others"
];

if (
    $name === "" ||
    !in_array($category, $allowedCategories, true) ||
    !is_numeric($price) ||
    (float)$price <= 0 ||
    (float)$price > 99999999
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a valid name, category, and price."
    ]);
    exit;
}

$imagePath = null;

try {
    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "Image upload failed. Error code: " . $_FILES["image"]["error"]
            );
        }

        if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            throw new RuntimeException("Image must be 5 MB or smaller.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES["image"]["tmp_name"]);

        $extensions = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($extensions[$mime])) {
            throw new RuntimeException(
                "Use a JPG, PNG, or WEBP image."
            );
        }

        $uploadDir = __DIR__ . "/uploads/";

        if (!is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            throw new RuntimeException(
                "Unable to create the uploads folder."
            );
        }

        $filename = bin2hex(random_bytes(16)) .
            "." . $extensions[$mime];

        if (!move_uploaded_file(
            $_FILES["image"]["tmp_name"],
            $uploadDir . $filename
        )) {
            throw new RuntimeException(
                "Unable to save the uploaded image."
            );
        }

        $imagePath = "uploads/" . $filename;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO products
        (name, category, price, image, description, is_available)
        VALUES (:name, :category, :price, :image, :description, 1)"
    );

    $stmt->execute([
        ":name" => $name,
        ":category" => $category,
        ":price" => (float)$price,
        ":image" => $imagePath,
        ":description" => $description !== "" ? $description : null
    ]);

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Product saved to MySQL successfully.",
        "id" => $pdo->lastInsertId()
    ]);

} catch (Throwable $e) {
    if (
        $imagePath !== null &&
        is_file(__DIR__ . "/" . $imagePath)
    ) {
        unlink(__DIR__ . "/" . $imagePath);
    }

    error_log("Add product error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to save the product. Check the PHP error log.",
        "error" => $e instanceof PDOException
            ? "Database operation failed."
            : $e->getMessage()
    ]);
}