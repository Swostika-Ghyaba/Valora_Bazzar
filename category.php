<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$requested_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$gender = isset($_GET['gender']) && in_array($_GET['gender'], ['Men', 'Women']) ? $_GET['gender'] : null;

if ($requested_id <= 0) {
    die("Invalid category.");
}

// Look up the requested category
$stmt = $conn->prepare("SELECT category_id, category_name, parent_id FROM categories WHERE category_id = ?");
$stmt->bind_param("i", $requested_id);
$stmt->execute();
$requested_category = $stmt->get_result()->fetch_assoc();

if (!$requested_category) {
    die("Category not found.");
}

// Determine whether the user landed on a TOP-LEVEL category (Clothing, Shoes, etc.)
$is_top_level = ($requested_category['parent_id'] === null);

if ($is_top_level) {
    $parent_id = $requested_category['category_id'];
    $parent_name = $requested_category['category_name'];
    $selected_sub_id = null; // no specific subcategory chosen - show everything combined
} else {
    $parent_id = $requested_category['parent_id'];
    $selected_sub_id = $requested_category['category_id'];

    $parentStmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $parentStmt->bind_param("i", $parent_id);
    $parentStmt->execute();
    $parentRow = $parentStmt->get_result()->fetch_assoc();
    $parent_name = $parentRow ? $parentRow['category_name'] : '';
}

// Fetch all subcategories under this parent (shown in sidebar)
$subStmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE parent_id = ? ORDER BY category_name");
$subStmt->bind_param("i", $parent_id);
$subStmt->execute();
$subResult = $subStmt->get_result();

$subcategories = [];
while ($row = $subResult->fetch_assoc()) {
    $subcategories[] = $row;
}

$selected_sub_name = '';
foreach ($subcategories as $sub) {
    if ($sub['category_id'] == $selected_sub_id) {
        $selected_sub_name = $sub['category_name'];
        break;
    }
}

// Build the product query:
$products = [];
$productCount = 0;

if ($selected_sub_id) {
    // Specific subcategory selected
    $sql = "SELECT product_id, product_name, price, image_url, stock_quantity, gender
            FROM products WHERE category_id = ?";
    $types = "i";
    $params = [$selected_sub_id];
} else {
    // Top-level: combine products from ALL child subcategories
    $subIds = array_column($subcategories, 'category_id');
    if (count($subIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($subIds), '?'));
        $sql = "SELECT product_id, product_name, price, image_url, stock_quantity, gender
                FROM products WHERE category_id IN ($placeholders)";
        $types = str_repeat('i', count($subIds));
        $params = $subIds;
    } else {
        $sql = null;
    }
}

if (isset($sql) && $sql) {
    if ($gender) {
        $sql .= " AND gender = ?";
        $types .= "s";
        $params[] = $gender;
    }
    $sql .= " ORDER BY product_id DESC";

    $prodStmt = $conn->prepare($sql);
    $prodStmt->bind_param($types, ...$params);
    $prodStmt->execute();
    $prodResult = $prodStmt->get_result();
    while ($row = $prodResult->fetch_assoc()) {
        $products[] = $row;
    }
    $productCount = count($products);
}

// Helper to build sidebar links that preserve the gender filter across clicks
function buildLink($categoryId, $gender) {
    $url = "category.php?category_id=" . $categoryId;
    if ($gender) {
        $url .= "&gender=" . urlencode($gender);
    }
    return $url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($parent_name) ?><?= $gender ? ' - ' . htmlspecialchars($gender) : '' ?> | Valora Bazzar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="navbar.css">
<link rel="stylesheet" href="category.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="breadcrumb">
    <a href="index.php">Home</a>
    <?php if ($gender): ?> &raquo; <a href="gender.php?gender=<?= urlencode($gender) ?>"><?= htmlspecialchars($gender) ?>'s Fashion</a><?php endif; ?>
    &raquo; <?= htmlspecialchars($parent_name) ?>
    <?php if ($selected_sub_name): ?> &raquo; <?= htmlspecialchars($selected_sub_name) ?><?php endif; ?>
</div>

<div class="page-wrapper">

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Gender toggle: All / Men / Women, preserves current category -->
        <div class="gender-toggle">
            <a href="category.php?category_id=<?= $parent_id ?>" class="<?= !$gender ? 'active' : '' ?>">All</a>
            <a href="category.php?category_id=<?= $parent_id ?>&gender=Men" class="<?= $gender === 'Men' ? 'active' : '' ?>">Men</a>
            <a href="category.php?category_id=<?= $parent_id ?>&gender=Women" class="<?= $gender === 'Women' ? 'active' : '' ?>">Women</a>
        </div>

        <div class="sidebar-section">
            <h4><?= htmlspecialchars($parent_name) ?></h4>
            <ul class="sidebar-list">
                <li>
                    <a href="<?= buildLink($parent_id, $gender) ?>" class="<?= !$selected_sub_id ? 'active' : '' ?>">
                        All <?= htmlspecialchars($parent_name) ?>
                    </a>
                </li>
                <?php foreach ($subcategories as $sub): ?>
                    <li>
                        <a href="<?= buildLink($sub['category_id'], $gender) ?>"
                           class="<?= $sub['category_id'] == $selected_sub_id ? 'active' : '' ?>">
                            <?= htmlspecialchars($sub['category_name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="results-header">
            <div>
                <h1><?= htmlspecialchars($selected_sub_name ?: $parent_name) ?><?= $gender ? ' — ' . htmlspecialchars($gender) : '' ?></h1>
                <div class="results-count"><?= $productCount ?> item<?= $productCount == 1 ? '' : 's' ?> found</div>
            </div>
        </div>

        <?php if (count($products) > 0): ?>
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card" onclick="window.location.href='productDetails.php?id=<?= $p['product_id'] ?>'">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
                        <h3><?= htmlspecialchars($p['product_name']) ?></h3>
                        <div class="price">Rs. <?= htmlspecialchars($p['price']) ?></div>
                        <?php if ($p['stock_quantity'] == 0): ?>
                            <div class="out-of-stock-tag">Out of Stock</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products-icon">
            <img src="no-product-found.png" alt="bags" width="120">
            </div>
            <div class="no-products">
            <h4>No products found in this category yet!</h4>
            <p>There is no any product in this category.</p>
            </div>
        <?php endif; ?>

    </div>

</div>
<footer>
    <?php include 'footer.php' ?>
</footer>

</body>
</html>