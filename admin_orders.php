<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// UPDATE ORDER STATUS 
if (isset($_POST['update_status'])) {

    $order_id = intval($_POST['order_id']);
    $status = trim($_POST['status']);

    // Get customer name and payment info for validation
    $res = $conn->query(
    "SELECT full_name, payment_method, payment_status, status FROM orders WHERE order_id = $order_id");
    $row = $res->fetch_assoc();

    $customer_name  = $row['full_name'] ?? "Customer";
    $payment_method = $row['payment_method'] ?? '';
    $payment_status = $row['payment_status'] ?? 'Unpaid';
    $previous_status = $row['status'] ?? '';
    $is_cod   = (stripos($payment_method, 'cash') !== false);
    $is_esewa = (stripos($payment_method, 'esewa') !== false);


    
    // VALIDATION: an eSewa order can only be marked Delivered
    if ($status === 'Delivered' && $is_esewa && $payment_status !== 'Paid') {

        header(
            "Location: admin_orders.php?error=" .
            urlencode("Cannot complete order #$order_id — eSewa payment was not confirmed (still $payment_status).")
        );
        exit();
    }


    // Update status
    $stmt = $conn->prepare(
        "UPDATE orders SET status = ? WHERE order_id = ?"
    );
    $stmt->bind_param("si", $status, $order_id);

    if (!$stmt->execute()) {
        die("Error updating order status: " . $stmt->error);
    }
    $stmt->close();

    // Only increment sales_count the FIRST time an order becomes Delivered
if ($status === 'Delivered' && $previous_status !== 'Delivered') {

    $items_stmt = $conn->prepare(
        "SELECT product_id, quantity FROM order_items WHERE order_id = ?"
    );
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    $sales_stmt = $conn->prepare(
        "UPDATE products SET sales_count = sales_count + ? WHERE product_id = ?"
    );

    while ($item = $items_result->fetch_assoc()) {
        $sales_stmt->bind_param("di", $item['quantity'], $item['product_id']);
        $sales_stmt->execute();
    }

    $items_stmt->close();
    $sales_stmt->close();
}


    // For Cash on Delivery orders, completing the order means
    if ($status === 'Delivered' && $is_cod && $payment_status !== 'Paid') {

        $pay_stmt = $conn->prepare(
            "UPDATE orders SET payment_status = 'Paid' WHERE order_id = ?"
        );
        $pay_stmt->bind_param("i", $order_id);
        $pay_stmt->execute();
        $pay_stmt->close();
    }


    // If completed, show message
    if ($status === "Delivered") {

        header(
            "Location: admin_orders.php?msg=" .
            urlencode("$customer_name's order has been completed.")
        );

    } elseif ($status === "Cancelled") {

        header(
            "Location: admin_orders.php?msg=" .
            urlencode("$customer_name's order has been cancelled.")
        );

    } else {

        header("Location: admin_orders.php");
    }

    exit();
}


// FETCH NON-COMPLETED, NON-CANCELLED ORDERS (pending queue only)

// Show latest 15 orders by default
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

$query = "
    SELECT 
        o.order_id,
        o.full_name,
        o.phone,
        o.address,
        o.status,
        o.payment_method,
        o.payment_status,
        o.total,
        o.promo_discount,
        o.created_at,

        GROUP_CONCAT(
            CONCAT(oi.product_name, ' (Qty: ', oi.quantity, ')')
            SEPARATOR ', '
        ) AS products

    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id

    WHERE LOWER(o.status) NOT IN ('delivered', 'cancelled')

    GROUP BY
        o.order_id,
        o.full_name,
        o.phone,
        o.address,
        o.status,
        o.payment_method,
        o.payment_status,
        o.total,
        o.promo_discount,
        o.created_at

    ORDER BY o.order_id DESC
";

if (!$view_all) {
    $query .= " LIMIT 15";
}

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Valora Bazzar Admin</title>
    <link rel="icon" type="image/png" href="valora_bazzar.png">
    <link rel="stylesheet" href="admin_orders.css">
</head>

<body>

<div class="admin-page">

    <!-- SIDEBAR -->
    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>


    <!--  MAIN CONTENT  -->
    <div class="main-content">
        <div class="title-content">
            <h2>Orders Management</h2>

            <div class="order-buttons">
                <?php if (!$view_all): ?>
                    <a href="admin_orders.php?view=all" class="view-all-btn">View All</a>
                <?php else: ?>
                    <a href="admin_orders.php" class="view-all-btn">Show Less</a>
                <?php endif; ?>

                <a href="admin_order_history.php" class="history-btn">
                    Order History
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['msg'])): ?>
            <script>
                alert(
                    "<?= htmlspecialchars($_GET['msg']); ?>"
                );
            </script>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (isset($_GET['error'])): ?>
            <script>
                alert(
                    "<?= htmlspecialchars($_GET['error']); ?>"
                );
            </script>
        <?php endif; ?>

        <!-- ORDERS TABLE  -->
       <?php if($result->num_rows > 0): ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Products</th>
            <th>Total (Rs)</th>
            <th>Promo Code</th>
            <th>Payment</th>
            <th>Status</th>
        </tr>
    </thead>

    <tbody>
        <?php while($order = $result->fetch_assoc()):
            $is_esewa = (stripos($order['payment_method'] ?? '', 'esewa') !== false);
            $is_paid  = ($order['payment_status'] === 'Paid');
        ?>
        <tr>
            <td><?= $order['order_id']; ?></td>
            <td class="name"><?= htmlspecialchars($order['full_name']); ?></td>
            <td class="name"><?= htmlspecialchars($order['products']); ?></td>
            <td class="price"><?= number_format($order['total'], 2); ?></td>
            <td><?= htmlspecialchars($order['promo_discount']); ?></td>

            <td>
                <div style=" font-family:'Times New Roman', Times, serif;"><?= htmlspecialchars($order['payment_method']); ?></div>
                <span style="color: <?= $is_paid ? 'green' : 'orange' ?>; font-family:'Times New Roman', Times, serif; font-weight:600;">
                    <?= htmlspecialchars($order['payment_status']); ?>
                </span>
            </td>

            <td>
                <form method="POST" class="order-status">
                    <input type="hidden" name="order_id" value="<?= $order['order_id']; ?>">

                    <select name="status" required>
                        <option value="Pending" <?= $order['status']=='Pending' ? 'selected' : '' ?>>
                            Pending
                        </option>
                        <option value="Delivered"
                            <?= ($is_esewa && !$is_paid) ? 'disabled title="eSewa payment not confirmed yet"' : '' ?>>Completed<?= ($is_esewa && !$is_paid) ? ' (unpaid)' : '' ?>
                        </option>
                        <option value="Cancelled">Cancel Order</option>
                    </select>

                    <button type="submit" name="update_status" class="update-btn"
                        onclick="return confirm('Are you sure you want to update this order?');">
                        Update
                    </button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php else: ?>

<div class="no-orders">
    <img src="no-order.png" alt="No orders" width="110">
    <!-- <i class="fa-solid fa-box-open"></i> -->
    <h3>No Orders Found!</h3>
    <p>There are currently no pending orders.</p>
</div>

<?php endif; ?>
    </div>
</div>
</body>
</html>