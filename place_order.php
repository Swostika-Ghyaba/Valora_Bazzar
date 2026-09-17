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

//    eSEWA CONFIGURATION
$esewa_url = "https://rc-epay.esewa.com.np/api/epay/main/v2/form";
$esewa_product_code = "EPAYTEST";
$esewa_secret_key = "8gBm/:&EnhH.1/q";


//    USER INFORMATION
$stmt = $conn->prepare("
    SELECT name, phone, email, address
    FROM account
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    die("User information not found.");
}

//    CART
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$cart = $_SESSION['cart'];

//    DEFAULT VALUES
$full_name = $user['name'] ?? '';
$phone = $user['phone'] ?? '';
$address = $user['address'] ?? '';

$alt_phone = '';

$delivery_method = 'delivery';
$delivery_zone = 'inside';

$delivery_charge = 250;
$promo_discount = 0;

$errors = [];       // errors from a POSTed order submission (shown via SweetAlert)
$cart_errors = [];  // errors from loading the cart itself (shown inline, any request)


//    CALCULATE CART TOTAL
$subtotal = 0;
$cart_products = [];

foreach ($cart as $cart_key => $item) {

    /* Be defensive about how the cart is keyed.Some flows store product_id as the array key, others store it inside the item itself.*/
    if (isset($item['product_id'])) {
        $product_id = (int)$item['product_id'];
    } else {
        $product_id = (int)$cart_key;
    }

    if ($product_id <= 0) {
        $cart_errors[] = "A product in your cart has an invalid ID.";
        continue;
    }

    $product_stmt = $conn->prepare("
        SELECT
            product_id,
            product_name,
            price,
            stock_quantity,
            image_url
        FROM products
        WHERE product_id = ?
    ");

    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();

    $product_result = $product_stmt->get_result();
    $product = $product_result->fetch_assoc();

    if (!$product) {
        $cart_errors[] = "A product in your cart no longer exists (ID: {$product_id}).";
        continue;
    }

    $quantity = (float)($item['quantity'] ?? 0);

    if ($quantity <= 0) {
        $cart_errors[] = "Invalid quantity for " . $product['product_name'];
        continue;
    }

    if ($quantity > $product['stock_quantity']) {
        $cart_errors[] = "Not enough stock for " . $product['product_name'];
        continue;
    }

    $item_total = (float)$product['price'] * $quantity;

    $subtotal += $item_total;

    $cart_products[] = [
        'product_id'   => $product['product_id'],
        'product_name' => $product['product_name'],
        'price'        => (float)$product['price'],
        'quantity'     => $quantity,
        'image_url'    => $product['image_url']
    ];
}


//    PROMO DISCOUNT
if (isset($_SESSION['promo_discount'])) {
    $promo_discount = (float)$_SESSION['promo_discount'];
}

if ($promo_discount > $subtotal) {
    $promo_discount = $subtotal;
}


//    INITIAL FINAL TOTAL
$final_total = $subtotal - $promo_discount + $delivery_charge;

if ($final_total < 0) {
    $final_total = 0;
}


//    POST REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $alt_phone = trim($_POST['alt_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $delivery_method = $_POST['delivery_method'] ?? 'delivery';
    $delivery_zone = $_POST['delivery_zone'] ?? 'inside';
    $place_order = $_POST['place_order'] ?? '';

    //    VALIDATION
    if ($place_order !== '1') {
        // This prevents the promo form or any other POST, from creating an order.
    } else {

        // Block order placement if the cart itself has problems
        if (!empty($cart_errors)) {
            $errors = array_merge($errors, $cart_errors);
        }

        if ($full_name === '') {
            $errors[] = "Please enter your full name.";
        }

        if (!preg_match('/^(97|98)\d{8}$/', $phone)) {
            $errors[] = "Please enter a valid phone number.";
        }

        if ($delivery_method === 'delivery' && $address === '') {
            $errors[] = "Please enter your delivery address.";
        }

        //    DELIVERY CHARGE
        if ($delivery_method === 'pickup') {
            $delivery_charge = 0;
        } elseif ($delivery_method === 'delivery') {
            if ($delivery_zone === 'inside') {
                $delivery_charge = 250;
            } elseif ($delivery_zone === 'outside') {
                $delivery_charge = 500;
            } else {
                $delivery_charge = 0;
            }
        } else {
            $delivery_charge = 0;
        }

        //    FINAL TOTAL
        $final_total =
            $subtotal
            - $promo_discount
            + $delivery_charge;

        if ($final_total < 0) {
            $final_total = 0;
        }


        //    CREATE ORDER
        if (empty($errors)) {
            $transaction_uuid = 'VALORA-' . $user_id . '-' . time() . '-' . bin2hex(random_bytes(5));
            $conn->begin_transaction();
            try {

                $payment_method = 'eSewa';

                //  * New order: status = Pending, payment_status = Unpaid
                $order_stmt = $conn->prepare("
                    INSERT INTO orders
                    (
                        user_id,
                        full_name,
                        phone,
                        alt_phone,
                        address,
                        delivery_method,
                        delivery_zone,
                        delivery_charge,
                        payment_method,
                        transaction_uuid,
                        total,
                        promo_discount,
                        status,
                        payment_status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Unpaid'
                    )
                ");

                $order_stmt->bind_param(
                    "issssssdssdd",
                    $user_id,
                    $full_name,
                    $phone,
                    $alt_phone,
                    $address,
                    $delivery_method,
                    $delivery_zone,
                    $delivery_charge,
                    $payment_method,
                    $transaction_uuid,
                    $final_total,
                    $promo_discount
                );

                if (!$order_stmt->execute()) {
                    throw new Exception("Could not create order.");
                }

                $order_id = $conn->insert_id;


                //    SAVE ORDER ITEMS
     $item_stmt = $conn->prepare("
    INSERT INTO order_items
    (
        order_id,
        product_id,
        product_name,
        quantity,
        price,
        subtotal
    )
    VALUES (?, ?, ?, ?, ?, ?)
");

foreach ($cart_products as $product) {

    $subtotal_item = $product['price'] * $product['quantity'];

    $item_stmt->bind_param(
        "iisddd",
        $order_id,
        $product['product_id'],
        $product['product_name'],
        $product['quantity'],
        $product['price'],
        $subtotal_item
    );

    if (!$item_stmt->execute()) {
        throw new Exception("Could not save order item.");
    }
}

                //  DO NOT REDUCE STOCK HERE... Payment has not yet been confirmed.
                $conn->commit();


                //    eSEWA PAYMENT DATA
                $amount = number_format($subtotal - $promo_discount, 2, '.', '');
                $tax_amount = "0";
                $product_service_charge = "0";
                $product_delivery_charge = number_format($delivery_charge, 2, '.', '');
                $total_amount = number_format($final_total,2,'.','');


                //    eSEWA SIGNATURE
                $signed_field_names = "total_amount,transaction_uuid,product_code";
                $message = "total_amount={$total_amount}," . "transaction_uuid={$transaction_uuid}," . "product_code={$esewa_product_code}";
                $signature = base64_encode(hash_hmac('sha256', $message, $esewa_secret_key, true));


                /*
                 * DO NOT clear the cart here.
                 *
                 * The order row is created as Pending/Unpaid,
                 * but the user hasn't actually paid yet. Only
                 * clear the cart in esewa_success.php, after
                 * eSewa confirms payment succeeded. If the user
                 * cancels or backs out, the cart must remain
                 * intact so they can retry checkout.
                 */

                //    REDIRECT TO eSEWA
                ?>

                <form id="esewaForm" action="<?= htmlspecialchars($esewa_url) ?>" method="POST" style="display:none;">
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

                <script>
                    document.getElementById('esewaForm').submit();
                </script>

                <?php
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout — Valora Bazzar</title>
    <link rel="icon" type="image/png" href="valoraa.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="place_order.css">
</head>

<body>
<header><?php include "navbar.php"; ?></header>

<!-- Page Header -->
<div class="page-headers">
    <h2>Checkout</h2>
    <a href="cart.php">Back To Cart</a>
</div>

<!-- SHOW PHP ERRORS ONLY AFTER A REAL ORDER SUBMISSION -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['place_order'] ?? '') === '1' && !empty($errors)): ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        icon: 'error',
        title: 'Order Error',
        html: `
            <?= implode(
                '<br>',
                array_map(
                    'htmlspecialchars',
                    $errors
                )
            ) ?>
        `,
        confirmButtonColor: '#e67e00',
        customClass: {
            confirmButton: 'my-custom-btn',
            title: 'swal-title'
        }
    });
});
</script>
<?php endif; ?>

<!-- CART LOADING ISSUES (out of stock / deleted products) Shown inline regardless of request method -->

<?php if (!empty($cart_errors)): ?>

    <div class="page-headers" style="padding-top:0;">
        <div style="background:#fdecea;border:1px solid #f5c2c0;color:#c0392b;padding:12px 16px;border-radius:6px;width:100%;">
            <?php foreach ($cart_errors as $err): ?>
                <p style="margin:4px 0;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?= htmlspecialchars($err) ?>
                </p>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<div class="checkout-wrapper" style="align-items: start;">
<div class="left-col">
    <!-- Cart items information -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-basket-shopping"></i>
            Order Summary
        </div>

        <div class="card-body">
            <?php foreach ($cart as $item): ?>
                <?php $sub = (float)$item['price'] * (float)$item['quantity'];
                $imgPath = file_exists($item['image']) ? $item['image'] : 'uploads/' . $item['image'];
                ?>

                <div class="summary-item">
                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($item['name']) ?>">

                    <div class="summary-item-info">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>

                        <p><?= $item['quantity'] ?> × Rs <?= $item['price'] ?></p>
                    </div>

                    <div class="summary-item-price">Rs <?= number_format($sub, 2) ?></div>
                </div>

            <?php endforeach; ?>
            <!-- Total amount -->
            <div class="summary-totals">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rs <?= number_format($subtotal, 2) ?></span>
                </div>

                <?php if ($promo_discount > 0): ?>
                    <div class="summary-row discount">
                        <span>Promo Discount</span>
                        <span> - Rs <?= number_format($promo_discount, 2) ?></span>
                    </div>

                <?php endif; ?>
                <div class="summary-row">
                    <span>Delivery</span>
                    <span style="color:#888;" id="delivery-label-display">Rs 250 — Inside Valley</span>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <span style="color:var(--red);">
                        Rs
                        <span id="final-total-display"><?= number_format($final_total, 2) ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Promo Code -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-tag"></i>
            Promo Code
        </div>

        <div class="card-body">
            <form method="POST">
                <div class="promo-row">
                    <input type="text" name="promo_code" placeholder="ENTER CODE" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>">
                    <button type="submit" name="apply_promo" class="promo-btn">Apply</button>
                </div>

                <?php if (isset($promo_error)): ?>
                    <p class="promo-error"><i class="fa-solid fa-xmark"></i><?= htmlspecialchars($promo_error) ?></p>

                <?php elseif ($promo_discount > 0): ?>
                    <p class="promo-success"><i class="fa-solid fa-check"></i>Promo applied! You saved Rs. <?= number_format($promo_discount, 2) ?></p>

                <?php endif; ?>
                <p style="font-size:11px;color:#aaa;margin-top:8px;">Try: SAVE10 · OFF20 · BUY15 · FLAT50 · FLAT100</p>
            </form>
        </div>
    </div>
</div>

<!-- DELIVERY & ORDER FORM -->

<div class="right-col">
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-truck"></i>
            Delivery Details
        </div>

        <div class="card-body">
            <form method="POST" id="checkout-form">
                <!-- Delivery Method -->
                <div class="section-label">
                    <i class="fa-solid fa-motorcycle"></i>
                    Delivery Method
                </div>

                <div class="method-options">
                    <div class="method-option">
                        <input type="radio" name="delivery_method"  id="pickup" value="pickup" onchange="updateDeliverySummary()">
                        <label for="pickup"> <i class="fa-solid fa-store"></i> Pickup</label>
                    </div>

                    <div class="method-option">
                        <input type="radio" name="delivery_method" id="delivery" value="delivery" checked onchange="updateDeliverySummary()">
                        <label for="delivery"><i class="fa-solid fa-truck"></i> Delivery </label>
                    </div>
                </div>

                <div class="form-group" id="zone-group">
                    <label> Delivery Zone *</label>
                    <select name="delivery_zone" id="delivery_zone" onchange="updateDeliverySummary()">
                        <option value="inside">Inside Kathmandu Valley — Rs 250 (1–2 days) </option>
                        <option value="outside">Outside Kathmandu Valley — Rs 500 (3–5 days)</option>
                    </select>
                </div>

                <!-- Customer Personal Info -->
                <div class="section-label"><i class="fa-solid fa-user"></i> Personal Info</div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? $user['name'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label> Phone * </label>
                        <input type="tel" name="phone" placeholder="98XXXXXXXX" autocomplete="off" maxlength="10" minlength="10" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                    </div>


                    <div class="form-group">
                        <label> Alt Phone</label>
                        <input type="tel" name="alt_phone" autocomplete="off" maxlength="10" minlength="10" placeholder="Optional"  value="<?= htmlspecialchars($_POST['alt_phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" id="address-group">
                    <label>Delivery Address *</label>
                    <textarea name="address" placeholder="Street, Area, City..."><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                </div>

                <!-- <input type="hidden" name="delivery_time" id="selected_time_input" value=""
                > -->

                <!-- Payment -->
                <div class="section-label" style="margin-top:20px;"><i class="fa-solid fa-credit-card"></i> Payment Method</div>
                <div class="payment-options">
                    <div class="payment-option esewa-only">
                        <input type="hidden" name="payment_method" value="esewa">

                        <div class="esewa-label">
                            <!-- <i class="fa-solid fa-wallet"></i> -->
                             <img  src="esewa.png" width='30'/>
                            <span>eSewa</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="order_total" id="order_total" value="<?= $final_total ?>">
                <button type="button" class="place-order-btn" onclick="submitOrder()"> Place Order</button>
                <input type="hidden" name="place_order" id="place_order_input" value="">
            </form>
        </div>
    </div>
</div>
</div>

<footer>

    <?php include 'footer.php'; ?>

</footer>


<script>

//    CHECKOUT TOTAL
const subtotal =
    <?= json_encode($subtotal) ?>;

const promoDiscount =
    <?= json_encode($promo_discount) ?>;


const deliveryRates = {

    pickup: {
        charge: 0,
        label: 'Free — Ready for pickup next business day'
    },

    inside: {
        charge: 250,
        label: 'Rs 250 — Inside Valley (1–2 business days)'
    },

    outside: {
        charge: 500,
        label: 'Rs 500 — Outside Valley (3–5 business days)'
    }

};


//    UPDATE DELIVERY
function updateDeliverySummary() {

    const selectedMethod =
        document.querySelector(
            '[name="delivery_method"]:checked'
        );

    if (!selectedMethod) {
        return;
    }

    const method =
        selectedMethod.value;

    const zoneGroup =
        document.getElementById('zone-group');

    let rate;


  const addressGroup = document.getElementById('address-group');

if (method === 'pickup') {
    zoneGroup.style.display = 'none';
    addressGroup.style.display = 'none';
    rate = deliveryRates.pickup;
} else {
    zoneGroup.style.display = '';
    addressGroup.style.display = '';
    const zone = document.getElementById('delivery_zone').value;
    rate = deliveryRates[zone];
}


    document.getElementById(
        'delivery-label-display'
    ).textContent = rate.label;


    const total =
        subtotal -
        promoDiscount +
        rate.charge;


    document.getElementById(
        'final-total-display'
    ).textContent =
        total.toFixed(2);


    document.getElementById(
        'order_total'
    ).value =
        total.toFixed(2);
}


//    PAGE LOAD
document.addEventListener(
    'DOMContentLoaded',
    function() {

        updateDeliverySummary();

    }
);


//    SUBMIT ORDER
function submitOrder() {

    const name =
        document
        .querySelector('[name="full_name"]')
        .value
        .trim();


    const phone =
        document
        .querySelector('[name="phone"]')
        .value
        .trim();


    const address =
        document
        .querySelector('[name="address"]')
        .value
        .trim();


    //    NAME / PHONE / ADDRESS
   const deliveryMethod = document.querySelector('[name="delivery_method"]:checked').value;

if (!name || !phone || (deliveryMethod === 'delivery' && !address)) {
    Swal.fire({
        icon: 'warning',
        title: 'Missing Details!',
        text: deliveryMethod === 'delivery'
            ? 'Please fill in your name, phone and delivery address.'
            : 'Please fill in your name and phone.',
        confirmButtonColor: '#0a3d1f',
        customClass: {
            confirmButton: 'my-custom-btn',
            title: 'swal-title'
        }
    });

    return;
}


    //    NAME VALIDATION
    if (!/^[a-zA-Z\s]{3,50}$/.test(name)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Name!',
            text: 'Please enter your full name.',
            confirmButtonColor: '#0a3d1f',
            customClass: {
                confirmButton: 'my-custom-btn',
                title: 'swal-title'
            }
        });
        return;
    }

    //    PHONE VALIDATION
    if (!/^(97|98)\d{8}$/.test(phone)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Phone!',
            text: 'Please enter a valid phone number.',
            confirmButtonColor: '#0a3d1f',
            customClass: {
                confirmButton: 'my-custom-btn',
                title: 'swal-title'
            }
        });
        return;
    }

    //    EVERYTHING IS VALID
    document.getElementById('place_order_input').value = '1';
    document.getElementById('checkout-form').submit();
}
</script>

</body>
</html>