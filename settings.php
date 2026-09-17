<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$totalProducts = 0;
$totalOrders = 0;
$totalUsers = 0;

$res1 = $conn->query("SELECT COUNT(*) AS total FROM products");

if ($res1) {
    $row = $res1->fetch_assoc();
    $totalProducts = $row['total'];
}

$res2 = $conn->query("SELECT COUNT(*) AS total FROM orders");

if ($res2) {
    $row = $res2->fetch_assoc();
    $totalOrders = $row['total'];
}

$res3 = $conn->query("SELECT COUNT(*) AS total FROM account");

if ($res3) {
    $row = $res3->fetch_assoc();
    $totalUsers = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
    <link rel="stylesheet" href="settings.css">
    <link rel="icon" type="image/png" href="valora_bazzar.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<div class="admin-page">

    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main-content">
        <h2>Settings</h2>

        <div class="status">
            <div class="products">
                <div class="card" id="card-product">
                    <div class="card-all-product">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <h3>Manage Products</h3>
                    </div>

                    <div class="card-total-product">
                        <p>Total Products (<?= $totalProducts; ?>)</p>
                    </div>
                </div>

                <div class="view-products">
                    <a href="products.php">
                        <button type="button" class="view-btn">
                            Manage All
                            <i class="fa-solid fa-angle-right"></i>
                        </button>
                    </a>
                </div>

            </div>

            <div class="orders">

                <div class="card" id="card-order">

                    <div class="card-all-order">
                        <i class="fa-solid fa-file-pen"></i>
                        <h3>Manage Orders</h3>
                    </div>

                    <div class="card-total-order">
                        <p>Total Orders (<?= $totalOrders; ?>)</p>
                    </div>

                </div>

                <div class="view-orders">
                    <a href="admin_orders.php">
                        <button type="button" class="view-btn">
                            Manage All
                            <i class="fa-solid fa-angle-right"></i>
                        </button>
                    </a>
                </div>

            </div>

            <div class="users">

                <div class="card" id="card-user">

                    <div class="card-all-user">
                        <i class="fa-solid fa-user-group"></i>
                        <h3>Manage Users</h3>
                    </div>

                    <div class="card-total-user">
                        <p>Total Users (<?= $totalUsers; ?>)</p>
                    </div>

                </div>

                <div class="view-users">
                    <a href="users.php">
                        <button type="button" class="view-btn">
                            Manage All
                            <i class="fa-solid fa-angle-right"></i>
                        </button>
                    </a>
                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>