<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<?php
// Only connect if the including page hasn't already created $conn
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "", "valora_bazzar");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}
?>
<header>

<!-- Nav Bar -->
    <div class="navbar">
       
<!-- Navigation Logo -->
 <div class="nav-title">
    <a href="index.php">
     <div class="nav-logo"></div>
</a>
     </div>
    

   
<div class="nav-home <?= $currentPage == 'index.php' ? 'active' : '' ?>">
    <p>
        <a href="index.php">
            <i class="fa-solid fa-house"></i> HOME
        </a>
    </p>
</div>

     <!-- Navigation Search Bar -->
      <div class="nav-search">
      <div class="dropdown">
        <button id="category" class="search-select" >ALL <i class="fa-solid fa-caret-down" ></i></button>
        <div class="category-boxes">
            <a href="category.php?category_id=44">Clothing</a>
            <a href="category.php?category_id=45">Shoes</a>
            <a href="category.php?category_id=47">Bags</a>
            <a href="category.php?category_id=46">Accessories</a>
            <a href="category.php?category_id=48">Glasses</a>
        </div>
    </div>
<div class="nsearch">
    <div class="se">
        <form method="get" action="">
            <input type="search" name="search" id="input-box" class="search-input" placeholder="SEARCH FOR..." autocomplete="off">
            <button type="submit" class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>     
    <div class="product-info-box"></div>
</div>
</div>



<!-- Nav Signin -->
    <div class="nav-signin">
<?php if(isset($_SESSION['user_id'])): ?>
    <a href="logout.php" id="loginLink">
        <i class="fa-solid fa-right-from-bracket"></i> LOGOUT
    </a>
<?php else: ?>
    <a href="login.php" id="loginLink">
        <i class="fa-solid fa-right-to-bracket"></i> SIGN IN
    </a>
<?php endif; ?>
</div>

<!-- Nav Account -->
<div class="nav-account <?= $currentPage == 'order_history.php' ? 'active' : '' ?>">
    <p>
        <a href="account.php" class="account">
            <i class="fa-regular fa-user"></i> ACCOUNT
        </a>
    </p>
</div>

<!-- Nav Favorite -->
<div class="nav-favorite <?= $currentPage == 'wishlist.php' ? 'active' : '' ?>" id="wishlist">
    <p>
        <a href="wishlist.php">
            <i class="fa-regular fa-heart"></i> WISHLIST

            <?php
            $wishlistCount = !empty($_SESSION['wishlist'])
                ? count($_SESSION['wishlist'])
                : 0;

            if ($wishlistCount > 0):
            ?>
                <span class="wishlist-count"><?= $wishlistCount ?></span>
            <?php endif; ?>
        </a>
    </p>
</div>


<!-- Nav Cart -->
<div class="nav-cart <?= $currentPage == 'cart.php' ? 'active' : '' ?>">
        <p><a href="cart.php">
        <i class="fa-solid fa-cart-shopping"></i> CART
        <?php 
        $cartCount = !empty($_SESSION['cart']) 
            ? array_sum(array_column($_SESSION['cart'], 'quantity')) 
            : 0;
        if($cartCount > 0): ?>
            <span class="cart-count"><?= $cartCount ?></span>
        <?php endif; ?>
    </a></p>
</div>
</div>

</header>

<?php
// Product & category data for the search dropdown, injected directly from PHP
$productListQuery = $conn->query("SELECT product_id, product_name FROM products");
$jsProducts = [];
while ($row = $productListQuery->fetch_assoc()) {
    $jsProducts[] = $row['product_id'] . '|' . $row['product_name'];
}

$categoryListQuery = $conn->query("SELECT category_id, category_name FROM categories");
$jsCategories = [];
while ($row = $categoryListQuery->fetch_assoc()) {
    $jsCategories[] = $row['category_id'] . '|' . $row['category_name'];
}
?>

<script>
    window.available_products = [
        <?php foreach ($jsProducts as $entry) {
            list($id, $name) = explode('|', $entry, 2);
            echo "{ type: 'product', id: " . intval($id) . ", name: \"" . htmlspecialchars($name, ENT_QUOTES) . "\" },\n";
        } ?>
    ];

    window.available_categories = [
        <?php foreach ($jsCategories as $entry) {
            list($id, $name) = explode('|', $entry, 2);
            echo "{ type: 'category', id: " . intval($id) . ", name: \"" . htmlspecialchars($name, ENT_QUOTES) . "\" },\n";
        } ?>
    ];
</script>

<script src="navbar.js"></script>