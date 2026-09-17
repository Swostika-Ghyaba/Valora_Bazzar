<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ============================================================
// ADD TO CART HANDLER (with stock validation)  <-- ADDED HERE
// ============================================================
if (isset($_POST['add_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $name       = $_POST['name'] ?? '';
    $price      = floatval($_POST['price'] ?? 0);
    $image      = $_POST['image'] ?? '';
    $size       = $_POST['size'] ?? '';
    $qty        = intval($_POST['quantity'] ?? 1); // default 1

    // Fetch current stock from database
    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $stockStmt->bind_param("i", $product_id);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult->fetch_assoc();
    $availableStock = $stockRow ? $stockRow['stock_quantity'] : 0;
    $stockStmt->close();

    // Calculate already in cart for this product+size
    $existingQty = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            if ((string)$item['product_id'] === (string)$product_id && $item['size'] === $size) {
                $existingQty += $item['quantity'];
            }
        }
    }

    $totalRequested = $existingQty + $qty;

    // Validation
    if ($availableStock <= 0) {
        $_SESSION['cart_error'] = "This product is out of stock.";
    } elseif ($qty <= 0) {
        $_SESSION['cart_error'] = "Quantity must be at least 1.";
    } elseif ($totalRequested > $availableStock) {
        $_SESSION['cart_error'] = "Not enough stock. Available: $availableStock (you already have $existingQty in cart).";
    } else {
        // Add to cart
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
    }

    // Redirect back to same page
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// WISHLIST HANDLER (unchanged)
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

// FETCH PRODUCTS (unchanged)
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$where_conditions = [];
if($selected_category) {
    $where_conditions[] = "p.category = '$selected_category'";
}

$where_sql = "";
if(count($where_conditions) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

$filtered_products = $conn->query("
    SELECT p.* 
    FROM products p 
    $where_sql 
    ORDER BY p.product_id DESC
    LIMIT 10
");
echo "<!-- DEBUG: rows returned = " . $filtered_products->num_rows . " -->";

$categories = ['Clothing', 'Shoes', 'Bags', 'Accessories', 'Glasses'];

$best_sellers = $conn->query("
    SELECT p.*
    FROM products p
    ORDER BY p.sales_count DESC
    LIMIT 10
");

function getTopProductsForCategory($conn, $parent_id, $limit = 4) {
    $subStmt = $conn->prepare("SELECT category_id FROM categories WHERE parent_id = ?");
    $subStmt->bind_param("i", $parent_id);
    $subStmt->execute();
    $subResult = $subStmt->get_result();

    $categoryIds = [$parent_id];
    while ($row = $subResult->fetch_assoc()) {
        $categoryIds[] = $row['category_id'];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $types = str_repeat('i', count($categoryIds));

    $sql = "SELECT product_id, product_name, price, image_url, stock_quantity
            FROM products
            WHERE category_id IN ($placeholders)
            ORDER BY sales_count DESC
            LIMIT $limit";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$categoryIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

$suggested_clothing    = getTopProductsForCategory($conn, 44);
$suggested_shoes       = getTopProductsForCategory($conn, 45);
$suggested_bags        = getTopProductsForCategory($conn, 47);
$suggested_accessories = getTopProductsForCategory($conn, 46);
$suggested_glasses     = getTopProductsForCategory($conn, 48);

$suggested_all_stmt = $conn->prepare("
    SELECT product_id, product_name, price, image_url, stock_quantity
    FROM products
    ORDER BY sales_count DESC
    LIMIT 4
");
$suggested_all_stmt->execute();
$suggested_all = $suggested_all_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Valora Bazzar | Online Shopping Your One-Stop Shop</title>
<link rel="icon" type="image/png" href="valoraa.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="index.css">  
<link rel="stylesheet" href="navbar.css">  
</head>
<body>
<header>
<div class="nav">
    <div class="store-information-contact">
        <div class="location-phone">
            <p id="number1" title="Contact Us Directly!"><i class="fa-duotone fa-solid fa-phone" ></i> Kathmandu: +977 9800000000</p>
            <p id="number1">|</p>
            <p id="number2" title="Contact Us Directly!"><i class="fa-duotone fa-solid fa-phone" ></i> Lalitpur: +977 9766891520</p>
        </div>
        <div class="nav-email">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=valorabazzar.vb@gmail.com&su=More%20Information" target="_blank" title="Email Us For More Information!"><i class="fa-duotone fa-regular fa-envelope" ></i>valorabazzar.vb@gmail.com</a>
        </div>
    </div>
</div>
<?php include 'navbar.php'; ?>
</header>

<main>
    <!-- ========== DISPLAY SUCCESS/ERROR MESSAGES ========== -->
    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="message error" style="max-width:90%; margin: 10px auto; padding:10px 20px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:4px;">
            <?= htmlspecialchars($_SESSION['cart_error']) ?>
            <?php unset($_SESSION['cart_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_success'])): ?>
        <div class="message success" style="max-width:90%; margin: 10px auto; padding:10px 20px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:4px;">
            <?= htmlspecialchars($_SESSION['cart_success']) ?>
            <?php unset($_SESSION['cart_success']); ?>
        </div>
    <?php endif; ?>

    <div class="main-poster">
        <div class="poster"><img src="main-poster.png" width="400"> </div>
        <div class="informations">
            <div class="buy"><p>Buy More, Save More And Get More...</p></div>
            <div class="discount"><h1>Buy 2 and get 12% off for all products!!</h1>
            <p>“Valora Bazzar – Celebrate your style, cherish your confidence, wear your story.”</p>
            </div>
            <div class="delivers"><button class="deliver-now"><a href="signup.php">Get Started !</a></button></div>
        </div>
        <div class="poster1"><img src="fa.png" width="394"> </div>
    </div>

    <div class="whatsapp" data-phone="9766891520">
        <a href="#" target="_blank"><i class="fa-brands fa-whatsapp"></i> Whatsapp</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.whatsapp').forEach(el => {
            const phone = el.getAttribute('data-phone');
            const message = encodeURIComponent('Hello, there!');
            const link = el.querySelector('a');
            if (link) link.href = `https://wa.me/977${phone}?text=${message}`;
        });
    });
    </script>

    <div class="shop-now">
        <a href="#products"><i class="fa-solid fa-store"></i>Shop Now</a>
    </div>

    <div class="categories" id="category">
        <h2>CATEGORIES</h2>
        <div class="category">    
            <a href="category.php?category_id=44"><button class="item"><img src="dress.png" alt="clothing" width="40"><p>Clothing</p></button></a>
            <a href="category.php?category_id=45"><button class="item"><img src="shoes.png" alt="shoes" width="60"><p>Shoes</p></button></a>
            <a href="category.php?category_id=47"><button class="item"><img src="bags.png" alt="bags" width="40"><p>Bags</p></button></a>
            <a href="category.php?category_id=46"><button class="item"><img src="Accessories.png" alt="Accessories" width="50"><p>Accessories</p></button></a>
            <a href="category.php?category_id=48"><button class="item"><img src="glass.png" alt="glasses" width="40"><p>Glasses</p></button></a>
            <!-- <a href="index.php"><button class="item">All<p>Products</p></button></a> -->
        </div>
    </div>

    <!-- Most Popular Products -->
    <div class="best-selling" id="selling">
        <div class="shop-items">
            <div class="best-products">
                <h2>BEST SELLING PRODUCTS</h2>
            </div>
            <div class="slider-wrapper">
                <button class="slide-btn prev" onclick="slideProducts('left')">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="best-selling-track" id="bestSellingTrack">
                    <?php if($best_sellers && $best_sellers->num_rows > 0): ?>
                        <?php while($product = $best_sellers->fetch_assoc()): ?>
                            <form method="POST" class="slide-item">
                                <div class="best-selling-box box">
                                    <div class="product-status">
                                         <?php if($product['stock_quantity'] > 0): ?>
                                        <span class="badge sale">ON SALE</span>
                                        <?php else: ?>
                                        <span class="badge out">OUT OF STOCK</span>
                                         <?php endif; ?>
                                    </div>
                                    <div class="box-img">
                                        <?php if($product['image_url'] && file_exists($product['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="<?= htmlspecialchars($product['product_name']); ?>">
                                        <?php else: ?>
                                            <img src="default.png" alt="Product">
                                        <?php endif; ?>
                                        <div class="hover-actions">
                                            <a href="productDetails.php?id=<?= htmlspecialchars($product['product_id']) ?>" class="action-btn view-btn" title="View Details">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                            <button type="submit" name="add_cart" class="action-btn cart-btn" title="Add to Cart" <?= $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                                                <i class="fa-solid fa-cart-shopping"></i>
                                            </button>
                                            <?php
                                            $wishlisted = false;
                                            if(isset($_SESSION['wishlist'])){
                                                foreach($_SESSION['wishlist'] as $item){
                                                    if($item['product_id'] == $product['product_id']){
                                                        $wishlisted = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>
                                            <button type="submit" name="add_wishlist" class="action-btn wishlist-btn" title="Add to Wishlist"><i class="<?= $wishlisted ? 'fa-solid heart-red' : 'fa-regular'; ?> fa-heart"></i></button>
                                        </div>
                                    </div>  
                                    <div class="box-information">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($product['product_name']); ?>">
                                        <div class="box-content"><a href="productDetails.php?id=<?= htmlspecialchars($product['product_id']) ?>"><?= htmlspecialchars($product['product_name']) ?></a></div>
                                        <div class="prices">
                                            <p id="price"><a href="product.php?name=<?= htmlspecialchars($product['product_name']) ?>"><strong>Rs. <?= number_format($product['price'], 2); ?></strong></a></p>
                                        </div>
                                        <input type="hidden" name="product_id" value="<?= $product['product_id']; ?>">
                                        <input type="hidden" name="price" value="<?= $product['price']; ?>">
                                        <input type="hidden" name="image" value="<?= $product['image_url']; ?>">
                                        <input type="hidden" name="size" value="<?= $product['size']; ?>">
                                        <input type="hidden" name="stock" value="<?= $product['stock_quantity']; ?>">
                                    </div>
                                </div>
                            </form>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; width:100%;">No sales data yet.</p>
                    <?php endif; ?>
                </div>
                <button class="slide-btn next" onclick="slideProducts('right')">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="best-selling-image">
            <img src="join-us.png">
        </div>
    </div>

    <!-- Latest Collections -->
    <div class="valora-products" id="products">
        <div class="all-products"></div>
        <div class="latest">
            <h2>LATEST COLLECTIONS</h2>
            <div class="all-items">
                <?php if($filtered_products && $filtered_products->num_rows > 0): ?>
                <?php while($product = $filtered_products->fetch_assoc()): ?>
                    <form method="POST">
                        <div class="box-latest">
                            <div class="product-status">
                                <?php if($product['stock_quantity'] > 0): ?>
                                    <span class="badge sale">ON SALE</span>
                                <?php else: ?>
                                    <span class="badge out">OUT OF STOCK</span>
                                <?php endif; ?>
                            </div>
                            <div class="box-img">
                                <?php if($product['image_url'] && file_exists($product['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="<?= htmlspecialchars($product['product_name']); ?>">
                                <?php else: ?>
                                    <img src="default.png" alt="Product">
                                <?php endif; ?>
                                <div class="hover-actions">
                                    <a href="productDetails.php?id=<?= htmlspecialchars($product['product_id']) ?>" class="action-btn view-btn" title="View Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                    <button type="submit" name="add_cart" class="action-btn cart-btn" title="Add to Cart" <?= $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-cart-shopping"></i>
                                    </button>
                                    <?php
                                    $wishlisted = false;
                                    if(isset($_SESSION['wishlist'])){
                                        foreach($_SESSION['wishlist'] as $item){
                                            if($item['product_id'] == $product['product_id']){
                                                $wishlisted = true;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <button type="submit" name="add_wishlist" class="action-btn wishlist-btn" title="Add to Wishlist"><i class="<?= $wishlisted ? 'fa-solid heart-red' : 'fa-regular'; ?> fa-heart"></i></button>                      
                                </div>
                            </div>
                            <div class="box-information">
                                <div class="box-content">
                                    <a href="productDetails.php?id=<?= htmlspecialchars($product['product_id']) ?>">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </a>
                                </div>
                                <input type="hidden" name="product_id" value="<?= $product['product_id']; ?>">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($product['product_name']); ?>">
                                <input type="hidden" name="price" value="<?= $product['price']; ?>">
                                <input type="hidden" name="image" value="<?= $product['image_url']; ?>">
                                <input type="hidden" name="size" value="<?= $product['size']; ?>">
                                <input type="hidden" name="stock" value="<?= $product['stock_quantity']; ?>">
                                <p id="stock">
                                    <?php if($product['stock_quantity'] == 0): ?>
                                        <span style="color:red;">Out of Stock</span>
                                    <?php elseif($product['stock_quantity'] <= 5): ?>
                                        <span style="color:#e67e00;">Low Stock: <?= $product['stock_quantity']; ?></span>
                                    <?php else: ?>
                                        <span style="color:green;">In Stock: <?= $product['stock_quantity']; ?></span>
                                    <?php endif; ?>
                                </p>
                                <div class="prices">
                                    <p id="price"><strong>Rs. <?= number_format($product['price'], 2); ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; width:100%; padding:50px;">
                        <h3>No products found.</h3>
                        <a href="index.php">Clear filters to see all products</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Suggestions -->
    <div class="suggestion-product">
        <div class="suggestion">
            <h2>SUGGESTIONS FOR YOU</h2>
            <div class="products">
                <div class="box-dress boxes">
                    <div class="product-type">
                        <h3>Clothing</h3>
                        <button class="view-all"><a href="category.php?category_id=44">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="dress product">
                        <?php if (count($suggested_clothing) > 0): ?>
                            <?php foreach ($suggested_clothing as $item): ?>
                                <div class="girl goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products in this category yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box-shoes boxes">
                    <div class="product-type">
                        <h3>Shoes</h3>
                        <button class="view-all"><a href="category.php?category_id=45">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="shoes product">
                        <?php if (count($suggested_shoes) > 0): ?>
                            <?php foreach ($suggested_shoes as $item): ?>
                                <div class="girl goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products in this category yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box-bags boxes">
                    <div class="product-type">
                        <h3>Bags</h3>
                        <button class="view-all"><a href="category.php?category_id=47">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="bags product">
                        <?php if (count($suggested_bags) > 0): ?>
                            <?php foreach ($suggested_bags as $item): ?>
                                <div class="hand bags goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products in this category yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box-accessories boxes">
                    <div class="product-type">
                        <h3>Accessories</h3>
                        <button class="view-all"><a href="category.php?category_id=46">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="accessories product">
                        <?php if (count($suggested_accessories) > 0): ?>
                            <?php foreach ($suggested_accessories as $item): ?>
                                <div class="girl accessories goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products in this category yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box-glasses boxes">
                    <div class="product-type">
                        <h3>Glasses</h3>
                        <button class="view-all"><a href="category.php?category_id=48">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="glasses product">
                        <?php if (count($suggested_glasses) > 0): ?>
                            <?php foreach ($suggested_glasses as $item): ?>
                                <div class="girl glasses goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products in this category yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box-all boxes">
                    <div class="product-type">
                        <h3>Products</h3>
                        <button class="view-all"><a href="index.php">View All <i class="fa-solid fa-angle-right"></i></a></button>
                    </div>
                    <div class="all product">
                        <?php if (count($suggested_all) > 0): ?>
                            <?php foreach ($suggested_all as $item): ?>
                                <div class="girl all goods" onclick="window.location.href='productDetails.php?id=<?= $item['product_id'] ?>'">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <h3>Rs. <?= number_format($item['price'], 2) ?></h3>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; width:100%; padding:20px; color:#888;">No products found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>          

    <!-- Sign in -->
    <div class="ref-border">
        <div class="ref-slover">
            <h2 style="font-weight: 700; font-size: 24px; line-height: 32px;padding-bottom: 4px;">See personalized informations</h2>
            <span>
                <a class="sign-in" href="login.php">
                    <button id="button">Sign in</button>
                </a>
            </span>
            <p style="font-size: 12px; ">New customer! <a href="signup.php" id="start-sign">Start here</a></p>
        </div>
    </div>

    <div class="features">
        <section class="delivery">
            <div class="truck"><i class="fa-solid fa-truck-fast"></i></div>
            <h3>Fast Delivery all over the Nepal</h3>
        </section>
        <section class="payment">
            <div class="truck"><i class="fa-regular fa-credit-card"></i></div>
            <h3>Safe Payment</h3>
        </section>
        <section class="quality">
            <div class="truck"><i class="fa-solid fa-medal"></i></div>
            <h3>100% Authentic Products</h3>
        </section>
    </div>
</main>

<footer>
    <div class="bhm">
        <a href="index.php" style="color: aliceblue;"  id="home">Back to top</a>     
    </div>
    <?php include 'footer.php'; ?>
</footer>

<script>
    let currentIndex = 0;
    const visibleCount = 4;

    function slideProducts(direction) {
        const track = document.getElementById('bestSellingTrack');
        if (!track) return;
        const items = track.querySelectorAll('.slide-item');
        const total = items.length;
        const maxIndex = total - visibleCount;
        if (direction === 'right') {
            if (currentIndex < maxIndex) currentIndex++;
        } else {
            if (currentIndex > 0) currentIndex--;
        }
        track.style.transform = `translateX(-${currentIndex * 25}%)`;
    }
</script>

</body>
</html>
<?php $conn->close(); ?>