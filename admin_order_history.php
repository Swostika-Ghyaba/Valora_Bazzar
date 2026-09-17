<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FILTER (all completed / delivered only / cancelled only)
$filter = $_GET['status'] ?? 'all';
$valid_filters = ['all', 'delivered', 'cancelled'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

if ($filter === 'delivered') {
    $where = "WHERE LOWER(o.status) = 'delivered'";
} elseif ($filter === 'cancelled') {
    $where = "WHERE LOWER(o.status) = 'cancelled'";
} else {
    $where = "WHERE LOWER(o.status) IN ('delivered', 'cancelled')";
}

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
    $where
    GROUP BY
        o.order_id, o.full_name, o.phone, o.address, o.status,
        o.payment_method, o.payment_status, o.total, o.promo_discount, o.created_at

    ORDER BY o.order_id DESC
";

$result = $conn->query($query);

$counts = $conn->query("
    SELECT
        SUM(LOWER(status) = 'delivered') AS delivered_count,
        SUM(LOWER(status) = 'cancelled') AS cancelled_count
    FROM orders
")->fetch_assoc();

$delivered_count = $counts['delivered_count'] ?? 0;
$cancelled_count = $counts['cancelled_count'] ?? 0;
$total_count = $delivered_count + $cancelled_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Valora Bazzar Admin</title>
    <link rel="icon" type="image/png" href="valora_bazzar.png">
    <link rel="stylesheet" href="admin_order_history.css">
    <style>

        .title-content{
            display: flex;
            /* justify-content: center; */
            align-items: center;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin: 15px 0 20px 0;
            flex-wrap: wrap;
        }
        .filter-tabs a {
            padding: 8px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #ccc;
            color: #333;
            background: #fff;
        }
        .filter-tabs a.active {
            background: #5A0E24;
            border-color: #5A0E24;
            color: #fff;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .back-btn {
            font-family: 'Times New Roman', Times, serif;
            width: 16%;
            height: 40px;
            margin: 50px 10px 30px 20px;
            background: #333;
            color: #fff;
            padding: 13px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .back-btn:hover { background: #5A0E24; }
        title-content h2{
            width: 85%;
        }
    </style>
</head>

<body>

<div class="admin-page">

    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="title-content">
            <h2>Order History</h2>
            <a href="admin_orders.php" class="back-btn">Back to Pending</a>
        </div>

        <div class="filter-tabs">
            <a href="admin_order_history.php?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">
                All (<?= $total_count ?>)
            </a>
            <a href="admin_order_history.php?status=delivered" class="<?= $filter === 'delivered' ? 'active' : '' ?>">
                Delivered (<?= $delivered_count ?>)
            </a>
            <a href="admin_order_history.php?status=cancelled" class="<?= $filter === 'cancelled' ? 'active' : '' ?>">
                Cancelled (<?= $cancelled_count ?>)
            </a>
        </div>

        <?php if ($result->num_rows > 0): ?>

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
                <?php while ($order = $result->fetch_assoc()):
                    $is_paid = ($order['payment_status'] === 'Paid');
                    $status_lower = strtolower($order['status']);
                ?>
                <tr>
                    <td><?= $order['order_id']; ?></td>
                    <td class="name"><?= htmlspecialchars($order['full_name']); ?></td>
                    <td class="name"><?= htmlspecialchars($order['products']); ?></td>
                    <td class="price"><?= number_format($order['total'], 2); ?></td>
                    <td><?= htmlspecialchars($order['promo_discount']); ?></td>

                    <td>
                        <div style="font-family:'Times New Roman', Times, serif;"><?= htmlspecialchars($order['payment_method']); ?></div>
                        <span style="color: <?= $is_paid ? 'green' : 'orange' ?>; font-family:'Times New Roman', Times, serif; font-weight:600;">
                            <?= htmlspecialchars($order['payment_status']); ?>
                        </span>
                    </td>

                    <td>
                        <span class="status-badge status-<?= $status_lower ?>">
                            <?= htmlspecialchars($order['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php else: ?>

        <div class="no-orders">
            <img src="no-order.png" alt="No orders" width="110">
            <h3>No Orders Found!</h3>
            <p>There are no completed or cancelled orders yet.</p>
        </div>

        <?php endif; ?>
    </div>
</div>
</body>
</html>