<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>sidebar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="icon" type="image/png" href="valoraa.png">
</head>
<body>
<div class="sidebar-page">
<div class="sidebar">
    <div class="web-name">
    <img src="valoraa.png" width="120"/>
    </div>
<ul>
    <li><a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i>Dashboard</a></li>

    <li>
        <a href="products.php" class="<?= in_array($current_page, ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-basket-shopping"></i>Products
        </a>
    </li>


<li><a href="admin_orders.php" class="<?= in_array($current_page, ['admin_orders.php', 'admin_order_history.php']) ? 'active' : '' ?>">
    <i class="fa-solid fa-file-pen"></i> Order Info
</a></li>

    <li><a href="users.php" class="<?= ($current_page == 'users.php') ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> Users</a></li>

    <li><a href="settings.php" class="<?= ($current_page == 'settings.php') ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i>Settings</a></li>
    <!-- <li> -->
        <form action="admin_logout.php" method="post">
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    <!-- </li> -->
</ul>
</div> 
</div>
</body>
</html>
