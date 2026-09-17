<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$view = $_GET['view'] ?? '';

$totalProducts = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
// If you have an orders table, uncomment the next line, otherwise set to 0
$totalOrders   = $conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'];
// $totalOrders = 0; // Placeholder – change if orders table exists
$totalUsers    = $conn->query("SELECT COUNT(*) AS total FROM account")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="icon" type="image/png" href="fresh_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="admin-page">

    <!-- Sidebar -->
    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Dashboard</h2>

        <!-- CARDS -->
        <div class="status">

            <div class="card-wrapper">
                <div class="cards">
                    <div class="card">
                        <h3>Total Products</h3><i class="fa-solid fa-basket-shopping"></i>
                    </div>
                    
                    <h4><?= $totalProducts; ?></h4>
                </div>
                <div class="view-link">
                    <a href="dashboard.php?view=products">
                        <button class="view-btn">View All <i class="fa-solid fa-angle-right"></i></button>
                    </a>
                </div>
            </div>

            <div class="card-wrapper">
                <div class="cards" id="card-order">
                    <div class="card">
                        <h3>Total<br>Orders</h3><i class="fa-solid fa-file-pen"></i>
                    </div>
                    
                    <h4><?= $totalOrders; ?></h4>
                </div>
                <div class="view-link">
                    <a href="dashboard.php?view=orders">
                        <button class="view-btn">View All <i class="fa-solid fa-angle-right"></i></button>
                    </a>
                </div>
            </div>

            <div class="card-wrapper">
                <div class="cards" id="card-user">
                    <div class="card">
                        <h3>Total<br>Users</h3><i class="fa-solid fa-user"></i>
                    </div>
                    <h4><?= $totalUsers; ?></h4>
                </div>
                <div class="view-link">
                    <a href="dashboard.php?view=users">
                        <button class="view-btn">View All <i class="fa-solid fa-angle-right"></i></button>
                    </a>
                </div>
            </div>

        </div> <!-- end .status -->


        <!-- Products View -->
        <?php if ($view == 'products'): ?>
        <div class="view-section">
            <div class="section-header">
                <h3>All Products (<?= $totalProducts; ?>)</h3>
                <a href="dashboard.php"><button class="back-btn">Back</button></a>
            </div>
            <div class="table-responsive">
                <?php $result = $conn->query("SELECT * FROM products ORDER BY product_id DESC"); ?>
                <table class="admin-table">
                    <caption>Products List</caption>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Price (Rs)</th>
                            <th>Stock</th>
                            <th>Gender</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['product_id']; ?></td>
                                    <td style="width: 200px;">
                                        <?php if (!empty($row['image_url']) && file_exists($row['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($row['image_url']); ?>" alt="<?= htmlspecialchars($row['product_name']); ?>" width="80" height="70">
                                        <?php else: ?>
                                            No Image
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600; width: 300px;"><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= number_format($row['price'], 2); ?></td>
                                    <td><?= $row['stock_quantity']; ?></td>
                                    <td><?= htmlspecialchars($row['gender']); ?></td>
                                    <td><?= htmlspecialchars($row['size'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;">No products found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

       <!-- Orders View -->
        <?php if ($view == 'orders'): ?>
        <div class="view-section">

            <div class="section-header">
                <h3>All Orders (<?= $totalOrders; ?>) </h3>
                <a href="dashboard.php"><button class="back-btn">Back</button></a>
            </div>

            <?php
            $result = $conn->query("SELECT * FROM orders ORDER BY order_id DESC");
            ?>

            <?php if ($result && $result->num_rows > 0): ?>

                <div class="table-responsive">

                    <table class="admin-table" id="order-table">

                        <caption>Orders</caption>

                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Order Time</th>
                                <th>Delivery Time</th>
                                <th>Total</th>
                                <th>Promo Code</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['order_id']; ?></td>
                                <td><?= htmlspecialchars($row['full_name']); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td><?= htmlspecialchars($row['address']); ?></td>
                                <td><?= $row['created_at']; ?></td>
                                <td><?= !empty($row['delivery_time'])? htmlspecialchars($row['delivery_time']): 'No Data'; ?></td>
                                <td><?= number_format($row['total'], 2); ?></td>
                                <td><?= !empty($row['promo_discount'])? htmlspecialchars($row['promo_discount']): 'No Data'; ?></td>
                                <td><?= htmlspecialchars($row['status']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>

                    </table>        
                </div>

            <?php else: ?>

            <div class="no-orders">
                <img src="no-product-found.png" alt="No orders" width="110">
                <h3>No Orders Found</h3>
                <p>There are currently no orders.</p>
            </div>

        <?php endif; ?>

        </div>

        <?php endif; ?>
        <!-- Users View -->
        <?php if ($view == 'users'): ?>
        <div class="view-section">
            <div class="section-header">
                <h3>All Users (<?= $totalUsers; ?>)</h3>
                <a href="dashboard.php"><button class="back-btn">Back</button></a>
            </div>
            <div class="table-responsive">
                <?php $result = $conn->query("SELECT * FROM account ORDER BY id ASC"); ?>
                <table class="admin-table" id="user-table">
                    <caption>Users</caption>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Joined At</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= $row['phone']; ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= $row['created_at']; ?></td>
 

                                <td><?= !empty($row['address']) ? htmlspecialchars($row['address']) : 'No Address'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div> 
</div> 

</body>
</html>

<?php $conn->close(); ?>