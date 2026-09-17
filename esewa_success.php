<?php

session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


//    eSEWA CONFIGURATION (must match place_order.php exactly)

$esewa_secret_key = "8gBm/:&EnhH.1/q";
$esewa_status_check_url = "https://rc.esewa.com.np/api/epay/transaction/status/";


// DECODE THE RESPONSE
$raw_data = $_GET['data'] ?? '';

if ($raw_data === '') {
    die("Invalid payment response: no data received.");
}

$decoded = base64_decode($raw_data);
$payload = json_decode($decoded, true);

if (!$payload || !isset($payload['transaction_uuid'], $payload['signature'], $payload['status'])) {
    die("Invalid payment response: could not parse data.");
}

$transaction_uuid   = $payload['transaction_uuid'];
$total_amount       = $payload['total_amount'] ?? '';
$product_code       = $payload['product_code'] ?? '';
$status             = $payload['status'] ?? '';
$signed_field_names = $payload['signed_field_names'] ?? '';
$received_signature = $payload['signature'];


//   VERIFY SIGNATURE
$fields = explode(',', $signed_field_names);

$message_parts = [];
foreach ($fields as $field) {
    $message_parts[] = $field . '=' . ($payload[$field] ?? '');
}
$message = implode(',', $message_parts);

$expected_signature = base64_encode(
    hash_hmac('sha256', $message, $esewa_secret_key, true)
);

if (!hash_equals($expected_signature, $received_signature)) {
    die("Payment verification failed: signature mismatch. Do not trust this response.");
}


//    STEP 3: LOOK UP THE ORDER
$stmt = $conn->prepare("
    SELECT order_id, user_id, total, payment_status
    FROM orders
    WHERE transaction_uuid = ?
");
$stmt->bind_param("s", $transaction_uuid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found for this transaction.");
}


 /*  DOUBLE-CHECK WITH ESEWA'S STATUS API
   (Extra safety: don't rely on the redirect payload alone)*/

$status_check_params = http_build_query([
    'product_code'     => $product_code,
    'total_amount'     => $total_amount,
    'transaction_uuid' => $transaction_uuid,
]);

$verify_url = $esewa_status_check_url . '?' . $status_check_params;

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);

$status_data = json_decode($response, true);
$verified_status = $status_data['status'] ?? '';


// UPDATE ORDER IF PAYMENT IS CONFIRMED
$already_paid = ($order['payment_status'] === 'Paid');

if (!$already_paid && $status === 'COMPLETE' && $verified_status === 'COMPLETE') {

    /*
     * Only touch payment_status here. Fulfillment status
     * (Pending/Completed/Cancelled) is managed exclusively
     * by the admin panel — payment confirmation and order
     * fulfillment are independent concerns.
     */
    $update_stmt = $conn->prepare("
        UPDATE orders
        SET payment_status = 'Paid'
        WHERE order_id = ?
    ");
    $update_stmt->bind_param("i", $order['order_id']);
    $update_stmt->execute();

    /*
     * Now that payment is confirmed, this is the correct
     * point to reduce stock. order_items currently stores
     * product_name/quantity/price but not product_id, so
     * stock is matched by name below. Recommended: add a
     * product_id column to order_items for a reliable join.
     */
    $items_stmt = $conn->prepare("
        SELECT product_name, quantity
        FROM order_items
        WHERE order_id = ?
    ");
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items = $items_stmt->get_result();

    while ($item = $items->fetch_assoc()) {
        $reduce_stmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE product_name = ? AND stock_quantity >= ?
        ");
        $reduce_stmt->bind_param(
            "dsd",
            $item['quantity'],
            $item['product_name'],
            $item['quantity']
        );
        $reduce_stmt->execute();
    }

    // Payment confirmed — safe to clear the cart now
    unset($_SESSION['cart']);
    unset($_SESSION['promo_discount']);

    $payment_confirmed = true;

} elseif ($already_paid) {

    // Already processed (e.g. user refreshed this page) — treat as success
    $payment_confirmed = true;

} else {

    $payment_confirmed = false;
}

$order_id = $order['order_id'];
$display_total = number_format((float)$order['total'], 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation — Valora Bazzar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="place_order.css">
    <link rel="icon" type="image/png" href="valoraa.png">

    <style>
        .status-wrapper {
            max-width: 520px;
            margin: 80px auto;
            text-align: center;
            padding: 40px 30px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .status-wrapper i { font-size: 56px; margin-bottom: 16px; }
        .status-wrapper.success i { color: darkgreen; }
        .status-wrapper.pending i { color: #e67e00; }
        .status-wrapper h2 { margin: 10px 0;  color: #5A0E24 !important; margin-top: 0 !important; font-weight: bold; font-size: 20px !important; font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;}
        .status-wrapper p { color: #666; margin-bottom: 24px;font-family: 'Times New Roman', Times, serif;font-size: 17px; }

        .status-wrapper p strong{ color: #5A0E24 !important;}

        .status-wrapper a {
            display: inline-block;
            padding: 12px 28px;
            background: orangered;
            color: #fff;
            text-decoration: none;
               font-family: 'Times New Roman', Times, serif;
            border-radius: 6px;
        }
        .status-wrapper a:hover{
            background: white;
            border: 1px solid orangered;
            color: orangered;
        }
    </style>
</head>
<body>

<header>
    <?php include "navbar.php"; ?>
</header>

<?php if ($payment_confirmed): ?>

    <div class="status-wrapper success">
        <i class="fa-solid fa-circle-check"></i>
         <!-- <i class="fa-regular fa-circle-check"></i> -->
        <h2>Payment Successful!</h2>
        <p>
            Your order <strong>#<?= htmlspecialchars($order_id) ?></strong> has been confirmed.<br>
            Amount paid: <strong>Rs <?= htmlspecialchars($display_total) ?></strong>
        </p>
        <a href="order_history.php">My Orders</a>
    </div>

<?php else: ?>

    <div class="status-wrapper pending">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>Payment Not Confirmed</h2>
        <p>
            We received a response from eSewa but couldn't verify the payment for
            order <strong>#<?= htmlspecialchars($order_id) ?></strong>.
            Please contact support before assuming your payment went through.
        </p>
        <a href="order_history.php">View My Orders</a>
    </div>

<?php endif; ?>

<footer>
    <?php include 'footer.php'; ?>
</footer>

</body>
</html>