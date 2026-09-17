<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ADD TO CART HANDLER (with stock validation)
if (isset($_POST['add_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $name       = $_POST['name'] ?? '';
    $price      = floatval($_POST['price'] ?? 0);
    $image      = $_POST['image'] ?? '';
    $size       = $_POST['size'] ?? '';
    $qty        = intval($_POST['quantity'] ?? 1);

    // If nothing came through (shouldn't happen since the radio has a default checked), fall back
    if ($size === '') {
        $size = 'One Size';
    }

    // Fetch current stock from database (fresh)
    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $stockStmt->bind_param("i", $product_id);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult->fetch_assoc();
    $availableStock = $stockRow ? $stockRow['stock_quantity'] : 0;
    $stockStmt->close();

    // Calculate how many of this product+size are already in cart
    $existingQty = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            if ((string)$item['product_id'] === (string)$product_id && $item['size'] === $size) {
                $existingQty += $item['quantity'];
            }
        }
    }

    $totalRequested = $existingQty + $qty;

    if ($availableStock <= 0) {
        $_SESSION['cart_error'] = "This product is out of stock.";
    } elseif ($qty <= 0) {
        $_SESSION['cart_error'] = "Quantity must be at least 1.";
    } elseif ($totalRequested > $availableStock) {
        $_SESSION['cart_error'] = "Not enough stock. Available: $availableStock (you already have $existingQty in cart).";
    } else {
        // Valid – add to cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ((string)$item['product_id'] === (string)$product_id && $item['size'] === $size) {
                $_SESSION['cart'][$key]['quantity'] += $qty;
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
                "quantity"   => $qty,
                "size"       => $size,
                "stock"      => $availableStock,
            ];
        }

        $_SESSION['cart_success'] = "$name added to cart!";
        // Optionally: if you want to decrease stock immediately, uncomment next line:
        // $conn->query("UPDATE products SET stock_quantity = stock_quantity - $qty WHERE product_id = $product_id");
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// ============================================================
// MAIN PRODUCT FETCH
// ============================================================
$product_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT p.*, b.brand_name, c.category_name
                         FROM products p
                         LEFT JOIN brands b ON p.brand_id = b.brand_id
                         LEFT JOIN categories c ON p.category_id = c.category_id
                         WHERE p.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// Fetch extra images
$extraImages = [];
$stmtExtra = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY image_id");
$stmtExtra->bind_param("i", $product_id);
$stmtExtra->execute();
$resultExtra = $stmtExtra->get_result();
while ($row = $resultExtra->fetch_assoc()) {
    $extraImages[] = $row['image_url'];
}
$stmtExtra->close();

// ============================================================
// FETCH AVAILABLE SIZES FOR THIS PRODUCT
// ============================================================
$productSizes = [];
$sizeStmt = $conn->prepare("
    SELECT size FROM product_sizes
    WHERE product_id = ?
    ORDER BY
        CASE size
            WHEN 'XS' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 WHEN 'L' THEN 4
            WHEN 'XL' THEN 5 WHEN 'XXL' THEN 6 ELSE 99
        END,
        CAST(size AS UNSIGNED),
        size
");
$sizeStmt->bind_param("i", $product_id);
$sizeStmt->execute();
$sizeResult = $sizeStmt->get_result();
while ($row = $sizeResult->fetch_assoc()) {
    $productSizes[] = $row['size'];
}
$sizeStmt->close();

// Default when this product has no sizes configured (e.g. Bags, Glasses, Accessories)
if (empty($productSizes)) {
    $productSizes = ['One Size'];
}

// ============================================================
// WISHLIST HANDLER (unchanged)
// ============================================================
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['product_name']) ?> | Valora Bazzar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="navbar.css">
    <style>
        .product-details-wrapper { display: flex; gap: 40px; padding: 40px; max-width: 98%; margin: 0 auto; }
        .product-image-col { display: flex; flex-direction: column; align-items: center; }
        .main-image-container { width: 400px; height: 380px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .main-image-container img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.2s ease; }
        .thumbnail-container { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
        .thumbnail { width: 70px; height: 70px; object-fit: cover; border: 2px solid transparent; border-radius: 4px; cursor: pointer; transition: border-color 0.2s, transform 0.2s; }
        .thumbnail:hover { border-color: #5A0E24; transform: scale(1.05); }
        .thumbnail.active { border-color: #5A0E24; border-width: 2px; }

        .product-info-col { flex: 1; }
        .product-info-col h1 { font-size: 26px; color: #222; margin-bottom: 10px; }
        .product-brand { font-family: "Display", playfair; color: #888; margin-bottom: 25px; margin-top: 30px; }
        .product-brand h5{ margin-top: 20px; font-weight: 400;}
        .product-price { font-size: 30px; color: #5A0E24; font-weight: bold; margin-bottom: 15px; font-family: "Display", playfair;}
        .product-description { font-family: "Display", playfair; margin-top: 20px; margin-bottom: 20px; color: #444; line-height: 1.5; }
        .product-description h5 { color: black; font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif; font-size: 18px; }

        .stock-info { margin-bottom: 15px; font-weight: 600; font-size: 20px;font-family: "Display", playfair;}
        .in-stock { color:green; }
        .out-stock { color: red; }

        .product-size { margin-bottom: 10px; display: flex; align-items: center;}
        .product-size h5 { color: black; font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif; font-size: 18px; margin-right: 10px;}
        .size-select {
            border: 1px solid #5A0E24;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            color: #5A0E24;
            background: #fff;
            min-width: 180px;
            cursor: pointer;
        }
        .size-select:focus { outline: none; box-shadow: 0 0 0 2px rgba(90,14,36,0.2); }

        .quantity-box { display: flex; align-items: center;  margin-bottom: 20px; height: 100px; flex-wrap: wrap; }
        .quantity-box h4{ font-size: 18px; margin-right: 10px; }
        .quantity-update{border: 1px solid #5A0E24; border-radius: 4px; }
        .quantity-box .decrement { width: 32px;margin: 0;height: 40px; Outline: 1px solid #5A0E24;border:none !important; background: #f5f5f5; color: #5A0E24; cursor: pointer; font-size: 16px;  border-top-left-radius: 4px; border-bottom-left-radius: 4px;}
        .quantity-box .increment { width: 32px;margin: 0;height: 40px;Outline: 1px solid #5A0E24;border:none !important; background: #f5f5f5; color: #5A0E24; cursor: pointer; font-size: 16px;  border-top-right-radius: 4px; border-bottom-right-radius: 4px;}
        .quantity-box button:hover{background: #5A0E24; color: white;}
        .quantity-box input { width: 60px; text-align: center; height: 40px;border:none !important; color: #5A0E24; border-radius: 4px; margin:0; }
        .quantity-box .add-cart-btn { background-color: #5A0E24; color: white; border: none; font-size: 16px; border-radius: 5px; cursor: pointer; width: 200px; height: 40px; margin-left: 20px }
        .quantity-box .add-cart-btn:hover { background-color: #7a1230; }
        .quantity-box .add-cart-btn:disabled { background-color: #aaa; cursor: not-allowed; }

        .wishlist-btn { float: right; margin-right: 20px; background: none; border: none; font-size: 23px; cursor: pointer; transition: color 0.2s; }
        .wishlist-btn:hover { color: #e74c3c; }
        .heart-red { color: #e74c3c; }

        /* Error/Success messages */
        .message { padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="product-details-wrapper">

    <div class="product-image-col">
        <div class="main-image-container">
            <img id="mainImage" src="<?= htmlspecialchars($product['image_url']) ?>" 
                 alt="<?= htmlspecialchars($product['product_name']) ?>">
        </div>
        <?php if (!empty($extraImages)): ?>
            <div class="thumbnail-container">
                <img class="thumbnail" src="<?= htmlspecialchars($product['image_url']) ?>" 
                     alt="Main" data-src="<?= htmlspecialchars($product['image_url']) ?>">
                <?php foreach ($extraImages as $imgUrl): ?>
                    <img class="thumbnail" src="<?= htmlspecialchars($imgUrl) ?>" 
                         alt="Extra" data-src="<?= htmlspecialchars($imgUrl) ?>">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="product-info-col">
        <h1><?= htmlspecialchars($product['product_name']) ?></h1>
        <?php if (!empty($product['category_name'])): ?>
            <div class="product-brand">Category: <?= htmlspecialchars($product['category_name']) ?>
            <h5>Brand: No Brand</h5>
            </div>
        <?php endif; ?>
        
        <!-- Wishlist Form -->
        <form method="POST" style="margin-top:5px;">
            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
            <input type="hidden" name="name" value="<?= htmlspecialchars($product['product_name']) ?>">
            <input type="hidden" name="price" value="<?= $product['price'] ?>">
            <input type="hidden" name="image" value="<?= htmlspecialchars($product['image_url']) ?>">
            <input type="hidden" name="size" value="<?= htmlspecialchars($productSizes[0]) ?>">

            <?php
            $wishlisted = false;
            if (isset($_SESSION['wishlist'])) {
                foreach ($_SESSION['wishlist'] as $wishItem) {
                    if ($wishItem['product_id'] == $product['product_id']) {
                        $wishlisted = true;
                        break;
                    }
                }
            }
            ?>
            <button type="submit" name="add_wishlist" class="wishlist-btn">
                <i class="<?= $wishlisted ? 'fa-solid heart-red' : 'fa-regular'; ?> fa-heart"></i>
            </button>
        </form>

        <div class="product-price">Rs. <?= htmlspecialchars($product['price']) ?></div>

        <!-- Display messages (error / success) -->
        <?php if (isset($_SESSION['cart_error'])): ?>
            <div class="message error"><?= htmlspecialchars($_SESSION['cart_error']) ?></div>
            <?php unset($_SESSION['cart_error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['cart_success'])): ?>
            <div class="message success"><?= htmlspecialchars($_SESSION['cart_success']) ?></div>
            <?php unset($_SESSION['cart_success']); ?>
        <?php endif; ?>

        <form method="POST" id="addToCartForm">
    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
    <input type="hidden" name="name" value="<?= htmlspecialchars($product['product_name']) ?>">
    <input type="hidden" name="price" value="<?= htmlspecialchars($product['price']) ?>">
    <input type="hidden" name="image" value="<?= htmlspecialchars($product['image_url']) ?>">
    <!-- THIS IS THE MISSING PIECE -->
    <input type="hidden" name="add_cart" value="1">

    <div class="stock-info <?= $product['stock_quantity'] > 0 ? 'in-stock' : 'out-stock' ?>">
        <?= $product['stock_quantity'] > 0 ? "Stock ({$product['stock_quantity']} available)" : "Out of Stock" ?>
    </div>

    <div class="product-description">
        <h5>Description</h5>
        <?= nl2br(htmlspecialchars($product['description'])) ?>
    </div>

    <div class="product-size">
        <h5>Size<?= count($productSizes) > 1 ? '' : '' ?></h5>
        <select name="size" class="size-select" required>
            <?php foreach ($productSizes as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="quantity-box">
        <h4>Quantity</h4>

        <div class="quantity-update">
        <button type="button" class="decrement">-</button>
        <input type="number" class="quantity-input" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" 
               <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
        <button type="button" class="increment">+</button>
        </div>
        <button type="button" class="add-cart-btn" onclick="addToCart(this)" 
            <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
            Add to Cart
        </button>
    </div>
</form>
    </div>
</div>

<footer><?php include "footer.php"; ?></footer>

<script>
    // Thumbnail hover swap
    document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('mainImage');
    const thumbnails = document.querySelectorAll('.thumbnail');
    const cartImageInput = document.querySelector('#addToCartForm input[name="image"]');

    thumbnails.forEach(thumb => {
        // hover preview
        thumb.addEventListener('mouseenter', function() {
            mainImage.src = this.dataset.src;
        });

        // click = select this image for cart
        thumb.addEventListener('click', function() {
            mainImage.src = this.dataset.src;
            if (cartImageInput) {
                cartImageInput.value = this.dataset.src;
            }
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });

        // Quantity + / - with stock limit
        document.querySelectorAll('.quantity-box').forEach(function(box) {
            const input = box.querySelector('.quantity-input');
            const decrement = box.querySelector('.decrement');
            const increment = box.querySelector('.increment');
            const maxStock = parseInt(input.max) || 999;

            decrement.addEventListener('click', function() {
                let val = parseInt(input.value) || 1;
                if (val > 1) input.value = val - 1;
            });

            increment.addEventListener('click', function() {
                let val = parseInt(input.value) || 1;
                if (val < maxStock) input.value = val + 1;
            });

            // Prevent manual entry above max
            input.addEventListener('change', function() {
                let val = parseInt(this.value) || 1;
                if (val < 1) this.value = 1;
                if (val > maxStock) this.value = maxStock;
            });
        });
    });

    // Add to Cart function
    function addToCart(btn) {
        const form = btn.closest('form');
        if (!form) return;

        // Make sure a size is selected (should always be true since the select defaults to the first option)
        const sizeSelect = form.querySelector('select[name="size"]');
        if (!sizeSelect || !sizeSelect.value) {
            alert("Please select a size.");
            return;
        }

        // Optional client-side check (server will also check)
        const qtyInput = form.querySelector('.quantity-input');
        const max = parseInt(qtyInput.max) || 0;
        const qty = parseInt(qtyInput.value) || 0;
        if (qty > max) {
            alert("Not enough stock. Available: " + max);
            return;
        }
        form.submit();
    }
</script>
<script src="script.js"></script>
</body>
</html>