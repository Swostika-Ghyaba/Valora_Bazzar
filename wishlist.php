<?php
session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// REMOVE ITEM FROM WISHLIST
if (isset($_POST['remove_wishlist'])) {
    $index = intval($_POST['remove_index'] ?? 0);
    if (isset($_SESSION['wishlist'][$index])) {
        unset($_SESSION['wishlist'][$index]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        $_SESSION['wishlist_message'] = "Item removed from wishlist";
    }
    header("Location: wishlist.php");
    exit;
}

// CLEAR ENTIRE WISHLIST
if (isset($_POST['clear_wishlist'])) {
    unset($_SESSION['wishlist']);
    $_SESSION['wishlist_message'] = "Wishlist cleared";
    header("Location: wishlist.php");
    exit;
}

// ADD WISHLIST ITEM TO CART
if (isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $name       = $_POST['name'] ?? '';
    $price      = floatval($_POST['price'] ?? 0);
    $image      = $_POST['image'] ?? '';
    $size       = $_POST['size'] ?? '';
    $qty        = 1; // add one at a time

    // Fetch current stock from database
    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $stockStmt->bind_param("i", $product_id);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult->fetch_assoc();
    $availableStock = $stockRow ? $stockRow['stock_quantity'] : 0;
    $stockStmt->close();

    if ($availableStock <= 0) {
        $_SESSION['wishlist_message'] = "Sorry, this product is out of stock.";
    } else {
        // Add to cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Check if already in cart with same size
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ((string)$item['product_id'] === (string)$product_id && $item['size'] === $size) {
                // If already in cart, increase quantity (check stock)
                $newQty = $item['quantity'] + 1;
                if ($newQty > $availableStock) {
                    $_SESSION['wishlist_message'] = "Cannot add more. Only $availableStock available in stock.";
                    header("Location: wishlist.php");
                    exit;
                }
                $_SESSION['cart'][$key]['quantity'] = $newQty;
                $found = true;
                break;

            }
        }

        if (!$found) {
            $_SESSION['cart'][] = [
                "product_id" => $product_id,
                "name"       => $name,
                "price"      => $price,
                "image"      => $image,
                "quantity"   => 1,
                "size"       => $size,
                "stock"      => $availableStock,
            ];
            $_SESSION['wishlist_message'] = "$name added to cart!";
        } else {
            $_SESSION['wishlist_message'] = "$name quantity updated in cart!";
        }
    }

    header("Location: wishlist.php");
    exit;
}

// FETCH WISHLIST ITEMS
$wishlistItems = [];
if (!empty($_SESSION['wishlist'])) {
    foreach ($_SESSION['wishlist'] as $item) {
        // Optionally fetch fresh stock and product details from DB
        $product_id = $item['product_id'];
        $stmt = $conn->prepare("SELECT stock_quantity, product_name, price, image_url FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            // Merge fresh data with session data (keep session name/price if you prefer)
            $item['stock'] = $row['stock_quantity'];
            $item['name'] = $row['product_name'];       // optional: use fresh name
            $item['price'] = $row['price'];             // optional: use fresh price
            $item['image'] = $row['image_url'];         // optional: use fresh image
        }
        $wishlistItems[] = $item;
        $stmt->close();
    }
}

$wishlist_msg = $_SESSION['wishlist_message'] ?? '';
unset($_SESSION['wishlist_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Wishlist</title>
    <link rel="icon" type="image/png" href="valoraa.png">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="wishlist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
</head>
<body>
<header>
<?php include 'navbar.php'; ?>
</header>

<div class="wishlist-wrapper">

    <?php if ($wishlist_msg): ?>
        <script>
            Swal.fire({
                icon: '<?= strpos($wishlist_msg, 'Cannot') !== false || strpos($wishlist_msg, 'Sorry') !== false ? 'error' : 'success' ?>',
                title: '<?= strpos($wishlist_msg, 'Cannot') !== false || strpos($wishlist_msg, 'Sorry') !== false ? 'Oops...' : 'Wishlist Updated' ?>',
                text: '<?= addslashes($wishlist_msg) ?>',
                confirmButtonColor: '<?= strpos($wishlist_msg, 'Cannot') !== false || strpos($wishlist_msg, 'Sorry') !== false ? '#d33' : '#5A0E24' ?>',
                customClass: {
                    title: 'my-title-class',
                    confirmButton: 'my-ok-btn'
                }
            });
        </script>
    <?php endif; ?>

    <div class="wishlist">
    <div class="wishlist-header">
        <div>
            <h2>My Wishlist (<?= count($wishlistItems) ?> Items)</h2>
        </div>
        <?php if (!empty($wishlistItems)): ?>
            <form method="POST" onsubmit="return confirm('Clear your entire wishlist?');">
                <button type="submit" name="clear_wishlist" class="clear-btn">
                    <i class="fa-regular fa-trash-can"></i> Clear All
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($wishlistItems)): ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlistItems as $index => $item): ?>
                <div class="wishlist-item">
                    <div class="image-wrapper">
                        <?php
                        $imagePath = $item['image'] ?? '';
                        if (!file_exists($imagePath) || empty($imagePath)) {
                            $imagePath = 'uploads/default.png';
                        }
                        ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>
                    <div class="item-details">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                        <div class="price">Rs. <?= number_format($item['price'], 2) ?></div>
                        <div class="stock <?= ($item['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock' ?>">
                            <?= ($item['stock'] ?? 0) > 0 ? "Stock ({$item['stock']})" : "✗ Out of Stock" ?>
                        </div>

                        <div class="item-actions">
                            <!-- Add to Cart -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($item['name']) ?>">
                                <input type="hidden" name="price" value="<?= $item['price'] ?>">
                                <input type="hidden" name="image" value="<?= htmlspecialchars($item['image']) ?>">
                                <input type="hidden" name="size" value="<?= htmlspecialchars($item['size'] ?? '') ?>">
                                <button type="submit" name="add_to_cart" class="btn-add-cart" <?= ($item['stock'] ?? 0) <= 0 ? 'disabled' : '' ?>><i class="fa-solid fa-cart-shopping"></i></button>
                            </form>

                            <!-- Remove from Wishlist -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="remove_index" value="<?= $index ?>">
                                <button type="submit" name="remove_wishlist" class="btn-remove"><i class="fa-solid fa-heart"></i></button>
                            </form>

                            <!-- View Product -->
                            <a href="productDetails.php?id=<?= $item['product_id'] ?>" class="btn-view">
                                <i class="fa-regular fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-wishlist">

        <div class="cart-empty-icon">
            <img src="wishlist-icon.png" alt="WishList Item" width="55">
        </div>
    
        <!-- <i class="fa-regular fa-heart"></i> -->
        <h2>Your wishlist is empty</h2>
        <p>Start adding your favorite items to your wishlist!</p>
        <a href="index.php#products" class="shop-btn"><i class="fa-solid fa-heart"></i> ADD NOW</a>
        </div>
    <?php endif; ?>
</div>
</div>

<footer><?php include 'footer.php'; ?></footer>

</body>
</html>