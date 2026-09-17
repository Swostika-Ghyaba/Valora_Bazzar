<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Make sure the product_sizes table exists (product can now have multiple sizes)
$conn->query("
    CREATE TABLE IF NOT EXISTS product_sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size VARCHAR(20) NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )
");

$message = "";

// Fetch top-level categories (Clothing, Shoes, Accessories, Bags, Glasses)
$topCategories = [];
$topResult = $conn->query("SELECT category_id, category_name FROM categories WHERE parent_id IS NULL ORDER BY category_name");
while ($row = $topResult->fetch_assoc()) {
    $topCategories[] = $row;
}

// Fetch ALL subcategories grouped by parent_id, so JS can populate the second dropdown instantly
$subcategoriesByParent = [];
$subResult = $conn->query("SELECT category_id, category_name, parent_id FROM categories WHERE parent_id IS NOT NULL ORDER BY category_name");
while ($row = $subResult->fetch_assoc()) {
    $subcategoriesByParent[$row['parent_id']][] = [
        'id' => $row['category_id'],
        'name' => $row['category_name']
    ];
}

if (isset($_POST['submit'])) {
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $category_id = intval($_POST['subcategory_id']); // the actual leaf subcategory chosen
    $gender = $conn->real_escape_string($_POST['gender']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $description = $conn->real_escape_string($_POST['description']);

    // Multiple sizes now come in as an array (checkboxes: sizes[])
    $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? $_POST['sizes'] : [];
    $sizes = array_values(array_unique(array_filter(array_map('trim', $sizes))));

    if ($category_id <= 0) {
        $message = "Please select a valid category and subcategory.";
    } else {
        // Create images folder if not exists
        if (!is_dir("images")) {
            mkdir("images", 0777, true);
        }

        $image = $_FILES['image']['name'];
        $target_dir = "images/";
        $target_file = $target_dir . basename($image);

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {

            // Insert into products table using category_id instead of free-text category, no brand
            // (size is no longer a single column - stored separately in product_sizes)
            $stmt = $conn->prepare("INSERT INTO products (product_name, category_id, gender, price, stock_quantity, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdiss", $product_name, $category_id, $gender, $price, $stock, $description, $target_file);
            $stmt->execute();
            $new_product_id = $stmt->insert_id;
            $stmt->close();

            // Insert each selected size as its own row
            if (!empty($sizes)) {
                $sizeStmt = $conn->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?, ?)");
                foreach ($sizes as $sizeValue) {
                    $sizeStmt->bind_param("is", $new_product_id, $sizeValue);
                    $sizeStmt->execute();
                }
                $sizeStmt->close();
            }

            // Handle optional Image 2 and Image 3 (only shown in productDetails.php gallery)
            $optionalImageFields = ['image2', 'image3'];
            $imgStmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            foreach ($optionalImageFields as $fieldName) {
                if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                    $extraName = basename($_FILES[$fieldName]['name']);
                    $extraTarget = $target_dir . uniqid() . '_' . $extraName;
                    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $extraTarget)) {
                        $imgStmt->bind_param("is", $new_product_id, $extraTarget);
                        $imgStmt->execute();
                    }
                }
            }
            $imgStmt->close();

            header("Location: products.php");
            exit();

        } else {
            $message = "Failed to upload image.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product - Valora Bazzar</title>
    <link rel="stylesheet" href="add_product.css">
    <style>
        .size-group { display: none; }
        .size-group.active { display: block; }
        .size-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        .size-checkboxes label {
            font-family: "Display", playfair;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            user-select: none;
        }

        .size-checkboxes label:hover{
            border: 1px solid #4f081c;
            color: #4f081c;
        }
        .size-checkboxes input[type="checkbox"] {
            margin: 0;
        }
        .size-checkboxes label:has(input:checked) {
            font-family: "Display", playfair;
            background: #2c3e50;
            color: #fff;
            border-color: #2c3e50;
        }
        .no-size-note {
            font-size: 13px;
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="side"><?php include 'sidebar.php'; ?></div>

    <div class="main-contents">
        <h2>Add New Product</h2>

        <?php if ($message): ?>
            <div class="message" style="background:#e74c3c;color:white;padding:10px;border-radius:6px;margin-bottom:15px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="productForm" class="product-form" enctype="multipart/form-data" style="height:auto; padding-bottom:30px;">
            <div class="product-detail">

            <h3>Product Details</h3>
            <button type="submit" name="submit" class="add-btn">Add Product</button>
            </div>
            <small style="color:#888; display:block; margin-top:-10px; margin-bottom:15px; font-style: italic; text-align:center;">
                (* Represent Required Fields! that must be filled before submission)
            </small>
            <div class="row">
                
                <div class="field">
                    <h5>Product Name *</h5>
                    <input type="text" name="product_name" placeholder="e.g., Adidas Ultraboost" autocomplete="off" required>
                </div>

                <div class="field">
                    <h5>Gender *</h5>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Men">Men</option>
                        <option value="Women">Women</option>
                        <option value="All">All (Unisex)</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Category *</h5>
                    <select name="category_id" id="category" required>
                        <option value="">Select Category</option>
                        <?php foreach ($topCategories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <h5>Subcategory *</h5>
                    <select name="subcategory_id" id="subcategory" required>
                        <option value="">Select Category First</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field" style="flex:1 1 100%;">
                    <h5>Available Sizes</h5>

                    <p class="no-size-note" id="noSizeNote">Select a category to show its size options.</p>

                    <div class="size-group" id="clothingSizes">
                        <div class="size-checkboxes">
                            <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'One Size'] as $s): ?>
                                <label><input type="checkbox" name="sizes[]" value="<?= $s ?>"> <?= $s ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="size-group" id="shoeSizes">
                        <div class="size-checkboxes">
                            <?php foreach (['5','6','7','8','9','10','11','12'] as $s): ?>
                                <label><input type="checkbox" name="sizes[]" value="<?= $s ?>"> <?= $s ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Price (NPR) *</h5>
                    <input type="number" name="price" id="price" step="0.01" min="0" placeholder="Enter price" autocomplete="off" required>
                </div>

                <div class="field">
                    <h5>Stock Quantity *</h5>
                    <input type="number" name="stock" min="0" placeholder="Enter stock quantity" autocomplete="off" required>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Product Image *</h5>
                    <input type="file" name="image" accept="image/*" required>
                </div>

                <div class="field">
                    <h5>Product Image (optional)</h5>
                    <input type="file" name="image2" accept="image/*">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Product Image (optional)</h5>
                    <input type="file" name="image3" accept="image/*">
                </div>
            </div>


            <div class="row1">
                <div class="field">
                    <h5>Description</h5>
                    <textarea name="description" id="description" placeholder="Product description, features, materials, etc..."></textarea>
                </div>
            </div>


        </form>
    </div>
</div>

<script>
    // Prevent negative values in price input
    const priceInput = document.getElementById('price');
    priceInput.addEventListener('input', function() {
        if (this.value < 0) {
            this.value = 0;
        }
    });

    // Prevent negative values in all number inputs
    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === '-') {
                e.preventDefault();
            }
        });
    });

    // Subcategories grouped by their parent category_id, injected directly from PHP/database
    const subcategoriesByParent = <?= json_encode($subcategoriesByParent) ?>;

    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');

    const clothingSizes = document.getElementById('clothingSizes');
    const shoeSizes = document.getElementById('shoeSizes');
    const noSizeNote = document.getElementById('noSizeNote');

    function updateSizeGroup() {
        const categoryName = (categorySelect.options[categorySelect.selectedIndex]?.text || '').toLowerCase();

        clothingSizes.classList.remove('active');
        shoeSizes.classList.remove('active');

        // Uncheck any boxes in a group that's about to be hidden, so
        // stale sizes from a previous category never get submitted.
        document.querySelectorAll('#clothingSizes input, #shoeSizes input').forEach(cb => cb.checked = false);

        if (categoryName.includes('shoe')) {
            shoeSizes.classList.add('active');
            noSizeNote.style.display = 'none';
        } else if (categoryName.includes('cloth')) {
            clothingSizes.classList.add('active');
            noSizeNote.style.display = 'none';
        } else if (categoryName) {
            // e.g. Accessories, Bags, Glasses - no sizing needed
            noSizeNote.textContent = 'This category does not require a size.';
            noSizeNote.style.display = 'block';
        } else {
            noSizeNote.textContent = 'Select a category to show its size options.';
            noSizeNote.style.display = 'block';
        }
    }

    categorySelect.addEventListener('change', function() {
        const parentId = this.value;
        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

        if (parentId && subcategoriesByParent[parentId]) {
            subcategoriesByParent[parentId].forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.id;
                option.textContent = sub.name;
                subcategorySelect.appendChild(option);
            });
        }

        updateSizeGroup();
    });
</script>

</body>
</html>

<?php $conn->close(); ?>