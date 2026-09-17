<?php
// ERROR REPORTING 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// UPDATE CART – WITH STOCK VALIDATION
if (isset($_POST['update_cart'])) {
    $index = intval($_POST['index'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);

    if (!isset($_SESSION['cart'][$index])) {
        header("Location: cart.php");
        exit;
    }

    $product_id = $_SESSION['cart'][$index]['product_id'] ?? 0;

    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $stockStmt->bind_param("i", $product_id);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult->fetch_assoc();
    $availableStock = $stockRow ? $stockRow['stock_quantity'] : 0;
    $stockStmt->close();

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart_message'] = "Item removed from cart";
    } elseif ($quantity > $availableStock) {
        $_SESSION['cart_message'] = "Cannot update. Only $availableStock items available in stock.";
    } else {
        $_SESSION['cart'][$index]['quantity'] = $quantity;
        $_SESSION['cart'][$index]['stock'] = $availableStock;
        $_SESSION['cart_message'] = "Quantity updated to $quantity";
    }

    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

// REMOVE SINGLE ITEM
if (isset($_POST['remove_item'])) {
    $index = intval($_POST['remove_index'] ?? 0);
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $_SESSION['cart_message'] = "Item removed from cart";
    }
    header("Location: cart.php");
    exit;
}

// CLEAR ENTIRE CART
if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    $_SESSION['cart_message'] = "Cart cleared";
    header("Location: cart.php");
    exit;
}

// WISHLIST TOGGLE
if (isset($_POST['add_wishlist'])) {
    $product_id = $_POST['product_id'];
    $name       = $_POST['name'];
    $price      = $_POST['price'];
    $image      = $_POST['image'];
    $size       = $_POST['size'];

    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }

    $found = false;
    foreach ($_SESSION['wishlist'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            $found = true;
            break;
        }
    }

    if (!$found) {
        $_SESSION['wishlist'][] = [
            "product_id" => $product_id,
            "name"       => $name,
            "price"      => $price,
            "image"      => $image,
            "size"       => $size
        ];
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// CALCULATE TOTALS
$total = 0;
$itemCount = 0;
if (!empty($_SESSION['cart'])) {
    $itemCount = count($_SESSION['cart']);
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
}

$cart_msg = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
    <link rel="icon" type="image/png" href="valoraa.png">
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .my-ok-btn {
            width: 100px !important;
            border: none !important;
            outline: none !important;
            border-radius: 6px !important;
            box-shadow: none !important; 
            font-family: 'Times New Roman', Times, serif; 
            background: green; 
        }
        .my-ok-btn :hover{
            background: darkgreen !important;
        }

        .my-title-class{
            font-family: font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif !important;
            color: #5A0E24;
            font-size: 20px;
        }

        .swal2-html-container{
            font-family: 'Times New Roman', Times, serif;
            font-size: 17px;
        }

        
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<?php if ($cart_msg): ?>
    <script>
        Swal.fire({
            icon: '<?= strpos($cart_msg, 'Cannot') !== false ? 'error' : 'success' ?>',
            title: '<?= strpos($cart_msg, 'Cannot') !== false ? 'Oops...' : 'Cart Updated' ?>',
            text: '<?= addslashes($cart_msg) ?>',
            confirmButtonColor: '<?= strpos($cart_msg, 'Cannot') !== false ? '#d33' : '#5A0E24' ?>',
            customClass: {
                title: 'my-title-class',      
                confirmButton: 'my-ok-btn'
            }
        });
    </script>
<?php endif; ?>

<div class="items">
    <div class="cart <?= empty($_SESSION['cart']) ? 'empty-cart' : '' ?>">
        <div class="page-header">
            <h2>Shopping Cart (<?= $itemCount ?> Items)</h2>
        </div>

        <?php if (!empty($_SESSION['cart'])): ?>
            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                <?php $subtotal = $item['price'] * $item['quantity']; ?>
                <div class="cart-item">
                    <?php
                    if (file_exists($item['image']) && !empty($item['image'])) {
                        $imagePath = $item['image'];
                    } else {
                        $imagePath = 'uploads/' . $item['image'];
                    }
                    ?>
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>

                    <div class="product">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                        <p>Size: <?= htmlspecialchars($item['size'] ?? 'N/A') ?></p>
                        <form method="POST" style="display:flex; align-items:center; gap:5px;">
                            <div class="quantity-controls">
                                <button type="button" class="decrement">-</button>
                                <input type="number" name="quantity" class="quantity-input" min="1" step="1" max="<?= $item['stock'] ?? 99 ?>" onkeydown="return event.key !== '-'" value="<?= $item['quantity'] ?>">
                                <button type="button" class="increment">+</button>
                            </div>
                            <input type="hidden" name="index" value="<?= $index ?>">
                            <input type="hidden" name="item_stock" value="<?= $item['stock'] ?? 20 ?>">
                            <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name']) ?>">
                            <button type="button" id="update_cart" onclick="updateCart(this)"><i class="fa-solid fa-arrows-rotate"></i></button>
                        </form>
                    </div>

                    <div class="details">
                        <p><strong style="color: black;">Rs. <?= number_format($subtotal, 2) ?></strong></p>
                        <p>Quantity: <?= $item['quantity'] ?></p>
                        <form method="POST" style="margin-top:5px;">
                            <?php
                            $wishlisted = false;
                            if (isset($_SESSION['wishlist'])) {
                                foreach ($_SESSION['wishlist'] as $wishItem) {
                                    if ($wishItem['product_id'] == $item['product_id']) {
                                        $wishlisted = true;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="hidden" name="product_id" value="<?= $item['product_id']; ?>">
                            <input type="hidden" name="name" value="<?= htmlspecialchars($item['name']); ?>">
                            <input type="hidden" name="price" value="<?= $item['price']; ?>">
                            <input type="hidden" name="image" value="<?= htmlspecialchars($item['image']); ?>">
                            <input type="hidden" name="size" value="<?= htmlspecialchars($item['size'] ?? ''); ?>">
                            <button type="submit" name="add_wishlist" class="wishlist-btn">
                                <i class="<?= $wishlisted ? 'fa-solid heart-red' : 'fa-regular'; ?> fa-heart"></i>
                            </button>
                            <input type="hidden" name="remove_index" value="<?= $index ?>">
                            <button type="submit" name="remove_item" class="remove-button"><i class="fa-regular fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">
                <div class="cart-empty-icon">
                    <img src="bags.png" alt="bags" width="40">
                </div>
                <div class="cart-empty">
                    <h4>Your cart is empty</h4>
                    <p>Looks like you haven't added any items to your cart yet. Start shopping to fill it up!</p>
                    <div class="continue">
                        <a href="index.php#products">SHOP NOW</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ORDER SUMMARY -->
    <div class="order-summary">
        <h3>ORDER SUMMARY</h3>
        <div class="summary-row">
            <span>Price (<?= $itemCount ?> items)</span>
            <span>Rs <?= number_format($total, 2) ?></span>
        </div>
        <div class="summary-row">
            <span>Delivery Charges</span>
            <span class="free">FREE</span>
        </div>
        <div class="summary-discount">
            <span>Discount</span>
            <span class="no-discount">-NPR 0</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-total">
            <span>Total Amount</span>
            <span>Rs <?= number_format($total, 2) ?></span>
        </div>
        <form method="POST">
            <button type="submit" name="clear_cart" id="clear-cart">CLEAR WHOLE CART</button>
        </form>
        <form action="place_order.php" method="POST">
            <button type="submit" id="place_order" name="place_order" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                PROCEED TO CHECKOUT
            </button>
        </form>
        <div class="continue-shopping"><a href="index.php#products"><i class="fa-solid fa-angle-left"></i> Continue Shopping</a></div>
    </div>
</div>

<footer>
    <?php include 'footer.php'; ?>
</footer>

<script>
    let isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
</script>
<script src="cart.js"></script>
</body>
</html>