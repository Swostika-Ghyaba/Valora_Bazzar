<?php
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) {
    http_response_code(500);
    exit;
}

$result = $conn->query("SELECT product_id, product_name FROM products");

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        "id"   => $row["product_id"],
        "name" => $row["name"]
    ];
}

header('Content-Type: application/json');
echo json_encode($products);

$conn->close();