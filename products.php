<?php
require_once 'admin_auth.php';

$conn = new mysqli('localhost', 'root', '', 'valora_bazzar');
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}


// Handle Delete Product
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    
    // Get image path before deleting
    $img_result = $conn->query("SELECT image_url FROM products WHERE product_id = $id");
    $img = $img_result->fetch_assoc();
    if($img && file_exists($img['image_url'])){
        unlink($img['image_url']);
    }
    
    $conn->query("DELETE FROM products WHERE product_id = $id");
    header("Location: products.php");
    exit();
}

//  FIXED QUERY: join categories table, aggregate all sizes 
$result = $conn->query("
    SELECT p.*, b.brand_name, c.category_name,
        GROUP_CONCAT(
            ps.size
            ORDER BY
                CASE ps.size
                    WHEN 'XS' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 WHEN 'L' THEN 4
                    WHEN 'XL' THEN 5 WHEN 'XXL' THEN 6 ELSE 99
                END,
                CAST(ps.size AS UNSIGNED),
                ps.size
            SEPARATOR ', '
        ) AS sizes_list
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.brand_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
    GROUP BY p.product_id
    ORDER BY p.product_id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products - Valora Bazzar</title>
    <link rel="icon" type="image/png" href="vb.png">
    <link rel="stylesheet" href="products.css">
    
</head>
<body>

<div class="admin-page">
    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    
    <div class="main-content">

    <div class="title-content">
        <h2>Products Information</h2>

        <a href="add_product.php">
            <button class="add-btn">Add New Product</button>
        </a>
    </div>
    
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Gender</th>
                    <th>Size</th>
                    <th>Price (Rs)</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if($result && $result->num_rows > 0): ?>
                    <?php while($product = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['product_id']; ?></td>
                            <td>
                                <?php if($product['image_url'] && file_exists($product['image_url'])): ?>
                                    <img src="<?= $product['image_url']; ?>" class="product-img">
                                <?php else: ?>
                                    <span style="color:#999;">No image</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($product['product_name']); ?></strong></td>
                            <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <span class="badge 
                                    <?= $product['gender'] == 'Men' ? 'badge-men' : ($product['gender'] == 'Women' ? 'badge-women' : 'badge-all'); ?>">
                                    <?= $product['gender']; ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($product['sizes_list'] ?: '-'); ?></td>
                            <td class="price"><?= number_format($product['price'], 2); ?></td>
                            <td><?= $product['stock_quantity']; ?></td>
                            <td>
                                <a href="edit_product.php?id=<?= $product['product_id']; ?>">
                                    <button class="edit-btn">Edit</button>
                                </a>
                                <a href="?delete=<?= $product['product_id']; ?>" onclick="return confirm('Delete this product?')">
                                    <button class="delete-btn">Delete</button>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">No products found. Add your first product!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

<?php $conn->close(); ?>