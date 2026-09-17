<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle cancel order
if(isset($_POST['cancel_order'])){
    $oid = intval($_POST['order_id']);
    $conn->query("UPDATE orders SET status='Cancelled' WHERE order_id=$oid AND user_id=$user_id AND status='Pending'");
    header("Location: order_history.php");
    exit;
}

// Filter
// NOTE: admin_orders.php writes 'Pending' or 'Completed' (not 'Delivered')
// so this page must match those exact values.
$filter = $_GET['filter'] ?? 'all';
$where  = "WHERE o.user_id = $user_id";
if($filter === 'pending')   $where .= " AND o.status = 'Pending'";
if($filter === 'completed') $where .= " AND o.status = 'Delivered'";
if($filter === 'cancelled') $where .= " AND o.status = 'Cancelled'";

$orders = $conn->query("
    SELECT o.*,
           GROUP_CONCAT(oi.product_name ORDER BY oi. item_id  SEPARATOR '||') as product_names,
           GROUP_CONCAT(oi.quantity       ORDER BY oi. item_id  SEPARATOR '||') as quantities,
           GROUP_CONCAT(p.image_url    ORDER BY oi. item_id  SEPARATOR '||') as product_images
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p   ON oi.product_name = p.product_name
    $where
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");

$counts = [];
$counts['all'] = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id")->fetch_assoc()['c'];
foreach(['Pending','Delivered','Cancelled'] as $s)
    $counts[$s] = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id AND status='$s'")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order History — Valora Bazzar</title>
<link rel="icon" type="image/png" href="valoraa.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="order_history.css">
<link rel="stylesheet" href="navbar.css">
</head>
<body>
<header> <?php include 'navbar.php'; ?></header>

<main class="main-contents">
<div class="wrap">
    <h2 class="page-title">Order History</h2>

    <!-- Tabs -->
    <div class="tab-row">
        <div class="tabs">
            <h3>All Order Informations</h3>
            
            <div class="order-status">
            <a href="?filter=all"       class="tab <?= $filter==='all'       ? 'active':'' ?>">All Order(<?= $counts['all'] ?>)</a>
            <a href="?filter=pending"   class="tab <?= $filter==='pending'   ? 'active':'' ?>">Pending(<?= $counts['Pending'] ?>)</a>
            <a href="?filter=completed" class="tab <?= $filter==='completed' ? 'active':'' ?>">Completed(<?= $counts['Delivered'] ?>)</a>
            <a href="?filter=cancelled" class="tab <?= $filter==='cancelled' ? 'active':'' ?>">Cancelled(<?= $counts['Cancelled'] ?>)</a>
            </div>
        </div>
    </div>

    <!-- Orders -->
    <?php if($orders->num_rows === 0): ?>
    <div class="empty">
        <div class="empty-icon">
            <!-- <i class="fa-solid fa-basket-shopping"></i> -->
             <img src="no-product-found.png" alt="No Orders" width="130">
            </div>
        <h2>No orders found!</h2>
        <p>You haven't placed any orders<?= $filter!=='all' ? ' in this category':'' ?> yet.</p>
        <a href="index.php" class="btn-shop"><i class="fa-solid fa-store"></i> Start Shopping</a>
    </div>

    <?php else:
    $i = 0;
    while($order = $orders->fetch_assoc()):
        $i++;
        $veg_arr = explode('||', $order['product_names']   ?? '');
        $qty_arr = explode('||', $order['quantities']  ?? '');
        $img_arr = explode('||', $order['product_images']  ?? '');
        $status  = $order['status'];
        $can_cancel = ($status === 'Pending');

        // Format payment date
        $payment_date = !empty($order['created_at'])
            ? date('d M Y', strtotime($order['created_at']))
            : '—';
    ?>

    <div class="order-card" style="animation-delay:<?= $i*.07 ?>s">

        <!-- Card Header -->
        <div class="card-top">
            <div class="card-top-left">
                <div class="order-num">Order : #<?= $order['order_id'] ?></div>
            </div>
            <div class="card-top-right">
                <a href="index.php" class="btn-buy">Shop Now</a>
            </div>
        </div>

        <!-- Item rows -->
        <?php
        $item_count = count($veg_arr);
        $price_each = $item_count > 0 ? $order['total'] / $item_count : 0;

        foreach($veg_arr as $idx => $veg):
            $veg = trim($veg);
            $qty = trim($qty_arr[$idx] ?? '0');
            if(empty($veg)) continue;
            $img     = trim($img_arr[$idx] ?? '');
            $imgPath = !empty($img) ? (file_exists($img) ? $img : 'uploads/'.$img) : '';
        ?>
        <div class="item-row">

            <div class="item-thumb">
                <?php if($imgPath): ?>
                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($veg) ?>">
                <?php else: ?>
                <?php endif; ?>
            </div>

            <div class="item-body">
                <div class="item-name"><?= htmlspecialchars($veg) ?></div>
                <div class="item-sub">Qty: <span><?= $qty ?> </span></div>
                <div class="item-price">Rs <?= number_format($price_each, 2) ?></div>
            </div>

            <div class="item-status">
                <div class="status-lbl">Status</div>
                <span class="badge <?= $status ?>"><?= $status ?></span>
            </div>

            <div class="item-delivery">
                <div class="delivery-lbl">Delivery Expected by</div>
                <div class="delivery-val"><?= htmlspecialchars($order['delivery_time'] ?? '—') ?></div>
            </div>

        </div>
        <?php endforeach; ?>

        <!-- Card Footer -->
        <div class="card-footer">
            <div class="footer-left">

                <!-- Cancel button -->
                <?php if($can_cancel): ?>
                <form method="POST" action="" class="cancel-form" style="display:inline;">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <input type="hidden" name="cancel_order" value="1">
                    <button type="submit" class="btn-cancel">
                        <i class="fa-solid fa-xmark"></i> Cancel Order
                    </button>
                </form>
                <?php else: ?>
                <span class="btn-cancel disabled">
                    <i class="fa-solid fa-xmark"></i> Cancel Order
                </span>
                <?php endif; ?>

                <!--
                    Pay Now: only for eSewa orders that are still
                    unpaid and not cancelled. Reuses the same order,
                    generates a fresh transaction_uuid, and redirects
                    to eSewa to complete payment.
                -->
                <?php
                    $is_esewa_order = (stripos($order['payment_method'] ?? '', 'esewa') !== false);
                    $needs_payment  = ($order['payment_status'] !== 'Paid' && $status !== 'Cancelled');
                ?>
                <?php if($is_esewa_order && $needs_payment): ?>
                <form method="POST" action="retry_payment.php" style="display:inline;">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <button type="submit" class="btn-cancel" style="background:#0a3d1f;color:#fff;">
                        <i class="fa-solid fa-credit-card"></i> Pay Now
                    </button>
                </form>
                <?php endif; ?>

                <!--
                    Payment status: this reflects whether eSewa payment
                    was actually confirmed (payment_status column, set
                    by esewa_success.php at checkout time). This is
                    intentionally independent of order fulfillment
                    status (Pending/Completed/Cancelled) — a customer
                    can pay immediately and still wait for delivery.
                -->
                <?php if($order['payment_status'] === 'Paid'): ?>
                    <div class="payment-ok" style="color:#16a34a;">
                        <i class="fa-solid fa-circle-check"></i> Payment Successful
                    </div>
                <?php elseif($status === 'Cancelled'): ?>
                    <div class="payment-ok" style="color:#dc2626;">
                        <i class="fa-solid fa-circle-xmark"></i> Payment Cancelled
                    </div>
                <?php else: ?>
                    <div class="payment-ok" style="color:#d97706;">
                        <i class="fa-solid fa-clock"></i> Payment Pending
                    </div>
                <?php endif; ?>

                <!-- Promo savings -->
                <?php if(!empty($order['promo_discount']) && $order['promo_discount'] > 0): ?>
                <span class="promo-tag">
                    <i class="fa-solid fa-tag"></i> Saved Rs <?= number_format($order['promo_discount'],2) ?>
                </span>
                <?php endif; ?>

            </div>

            <div class="total-val">
                Total Price: Rs <?= number_format($order['total'],2) ?>
            </div>
        </div>

    </div>
    <?php endwhile; endif; ?>

</div>
                </main>


<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelectorAll('.cancel-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var currentForm = this;
        Swal.fire({
            title: 'Cancel Order?',
            text: 'Do you really want to cancel this order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#16a34a',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'No, keep it',
            customClass: {
                popup:         'swal-font',
                title:         'swal-font',
                htmlContainer: 'swal-font',
                confirmButton: 'swal-font',
                cancelButton:  'swal-font',
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                currentForm.submit();
            }
        });
    });
});
</script>
<footer> <?php include "footer.php"; ?> </footer>

</body>
</html>