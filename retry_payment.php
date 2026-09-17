<?php

session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];


//    eSEWA CONFIGURATION (must match place_order.php exactly)
$esewa_url = "https://rc-epay.esewa.com.np/api/epay/main/v2/form";
$esewa_product_code = "EPAYTEST";
$esewa_secret_key = "8gBm/:&EnhH.1/q";


//    GET & VALIDATE THE ORDER
$order_id = intval($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid order.");
}

$stmt = $conn->prepare("
    SELECT order_id, user_id, total, delivery_charge, promo_discount,
           payment_method, payment_status, status
    FROM orders
    WHERE order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

// Security: only the order's own owner can retry payment on it
if ((int)$order['user_id'] !== $user_id) {
    die("You are not authorized to access this order.");
}

// Only eSewa orders can be retried through this flow
if (stripos($order['payment_method'], 'esewa') === false) {
    die("This order is not an eSewa payment.");
}

// Already paid — nothing to do
if ($order['payment_status'] === 'Paid') {
    header("Location: order_history.php");
    exit;
}

// Cancelled orders can't be paid for
if ($order['status'] === 'Cancelled') {
    die("This order was cancelled and can no longer be paid for.");
}


/* GENERATE A FRESH TRANSACTION UUID
   (eSewa requires a unique uuid per payment attempt) */

$transaction_uuid =
    'VALORA-' .
    $user_id . '-' .
    time() . '-' .
    bin2hex(random_bytes(5));

$update_stmt = $conn->prepare("
    UPDATE orders SET transaction_uuid = ? WHERE order_id = ?
");
$update_stmt->bind_param("si", $transaction_uuid, $order_id);
$update_stmt->execute();


//    BUILD PAYMENT AMOUNTS
$delivery_charge = (float)$order['delivery_charge'];
$total_amount_num = (float)$order['total'];
$product_amount_num = $total_amount_num - $delivery_charge;

$amount = number_format($product_amount_num, 2, '.', '');
$tax_amount = "0";
$product_service_charge = "0";
$product_delivery_charge = number_format($delivery_charge, 2, '.', '');
$total_amount = number_format($total_amount_num, 2, '.', '');


//    SIGNATURE
$signed_field_names = "total_amount,transaction_uuid,product_code";

$message =
    "total_amount={$total_amount}," .
    "transaction_uuid={$transaction_uuid}," .
    "product_code={$esewa_product_code}";

$signature = base64_encode(
    hash_hmac('sha256', $message, $esewa_secret_key, true)
);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting to eSewa...</title>
</head>
<body>

<form
    id="esewaForm"
    action="<?= htmlspecialchars($esewa_url) ?>"
    method="POST"
    style="display:none;"
>
    <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="tax_amount" value="<?= htmlspecialchars($tax_amount) ?>">
    <input type="hidden" name="total_amount" value="<?= htmlspecialchars($total_amount) ?>">
    <input type="hidden" name="transaction_uuid" value="<?= htmlspecialchars($transaction_uuid) ?>">
    <input type="hidden" name="product_code" value="<?= htmlspecialchars($esewa_product_code) ?>">
    <input type="hidden" name="product_service_charge" value="<?= htmlspecialchars($product_service_charge) ?>">
    <input type="hidden" name="product_delivery_charge" value="<?= htmlspecialchars($product_delivery_charge) ?>">
    <input type="hidden" name="success_url" value="http://localhost:8080/valora bazzar/esewa_success.php">
    <input type="hidden" name="failure_url" value="http://localhost:8080/valora bazzar/esewa_failure.php">
    <input type="hidden" name="signed_field_names" value="<?= htmlspecialchars($signed_field_names) ?>">
    <input type="hidden" name="signature" value="<?= htmlspecialchars($signature) ?>">
</form>

<p style="font-family:sans-serif;text-align:center;margin-top:60px;">
    Redirecting to eSewa, please wait...
</p>

<script>
    document.getElementById('esewaForm').submit();
</script>

</body>
</html>