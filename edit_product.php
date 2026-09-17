<?php
require_once 'admin_auth.php';

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Make sure the product_sizes table exists (product can have multiple sizes)
$conn->query("
    CREATE TABLE IF NOT EXISTS product_sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size VARCHAR(20) NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )
");

$message = "";

//    GET PRODUCT ID
$product_id = intval($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    die("Invalid product.");
}

//    FETCH CATEGORIES

$topCategories = [];
$topResult = $conn->query("SELECT category_id, category_name FROM categories WHERE parent_id IS NULL ORDER BY category_name");
while ($row = $topResult->fetch_assoc()) {
    $topCategories[] = $row;
}

$subcategoriesByParent = [];
$subResult = $conn->query("SELECT category_id, category_name, parent_id FROM categories WHERE parent_id IS NOT NULL ORDER BY category_name");
while ($row = $subResult->fetch_assoc()) {
    $subcategoriesByParent[$row['parent_id']][] = [
        'id' => $row['category_id'],
        'name' => $row['category_name']
    ];
}


//    HANDLE UPDATE
if (isset($_POST['submit'])) {

    $product_name = $conn->real_escape_string($_POST['product_name']);
    $category_id  = intval($_POST['subcategory_id']); // leaf subcategory, same convention as add_product.php
    $gender       = $conn->real_escape_string($_POST['gender']);
    $price        = floatval($_POST['price']);
    $stock        = intval($_POST['stock']);
    $description  = $conn->real_escape_string($_POST['description']);

    // Multiple sizes come in as an array (checkboxes: sizes[])
    $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? $_POST['sizes'] : [];
    $sizes = array_values(array_unique(array_filter(array_map('trim', $sizes))));

    if ($category_id <= 0) {
        $message = "Please select a valid category and subcategory.";
    } else {

        if (!is_dir("images")) {
            mkdir("images", 0777, true);
        }

        $target_dir = "images/";

        // Fetch current main image so we know what to keep/replace
        $current_stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
        $current_stmt->bind_param("i", $product_id);
        $current_stmt->execute();
        $current_image = $current_stmt->get_result()->fetch_assoc()['image_url'] ?? '';
        $current_stmt->close();

        $target_file = $current_image; // keep existing unless replaced below

        // Replace main image only if a new one was uploaded
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['image']['name'];
            $new_target_file = $target_dir . uniqid() . '_' . basename($image);

            if (move_uploaded_file($_FILES['image']['tmp_name'], $new_target_file)) {
                $target_file = $new_target_file;
                // Remove old main image file if it exists and differs
                if ($current_image && $current_image !== $target_file && file_exists($current_image)) {
                    unlink($current_image);
                }
            } else {
                $message = "Failed to upload new main image. Keeping the existing one.";
            }
        }

        // Update product record (size is no longer a single column here)
        $stmt = $conn->prepare("
            UPDATE products
            SET product_name = ?, category_id = ?, gender = ?,
                price = ?, stock_quantity = ?, description = ?, image_url = ?
            WHERE product_id = ?
        ");
        $stmt->bind_param(
            "sisdissi",
            $product_name, $category_id, $gender,
            $price, $stock, $description, $target_file, $product_id
        );
        $stmt->execute();
        $stmt->close();

        //    REPLACE SIZES
        // Simplest reliable approach: wipe old rows for this product, then re-insert the current selection.
        $del_size_stmt = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
        $del_size_stmt->bind_param("i", $product_id);
        $del_size_stmt->execute();
        $del_size_stmt->close();

        if (!empty($sizes)) {
            $sizeStmt = $conn->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?, ?)");
            foreach ($sizes as $sizeValue) {
                $sizeStmt->bind_param("is", $product_id, $sizeValue);
                $sizeStmt->execute();
            }
            $sizeStmt->close();
        }


        //    DELETE SELECTED EXTRA IMAGES
        if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            $del_stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ? AND image_url = ?");
            foreach ($_POST['delete_images'] as $img_to_delete) {
                $img_to_delete = $conn->real_escape_string($img_to_delete);
                $del_stmt->bind_param("is", $product_id, $img_to_delete);
                $del_stmt->execute();
                if (file_exists($img_to_delete)) {
                    unlink($img_to_delete);
                }
            }
            $del_stmt->close();
        }


        //    ADD NEW EXTRA IMAGES (image2 / image3)
        $optionalImageFields = ['image2', 'image3'];
        $imgStmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
        foreach ($optionalImageFields as $fieldName) {
            if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $extraName = basename($_FILES[$fieldName]['name']);
                $extraTarget = $target_dir . uniqid() . '_' . $extraName;
                if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $extraTarget)) {
                    $imgStmt->bind_param("is", $product_id, $extraTarget);
                    $imgStmt->execute();
                }
            }
        }
        $imgStmt->close();

        if (empty($message)) {
            header("Location: products.php?updated=1");
            exit();
        }
    }
}


//    FETCH PRODUCT DATA FOR THE FORM
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found.");
}

// Determine which top-level category this product's subcategory belongs to,
// so we can pre-select both dropdowns correctly.
$selected_subcategory_id = (int)$product['category_id'];
$selected_top_category_id = 0;

$parent_stmt = $conn->prepare("SELECT parent_id FROM categories WHERE category_id = ?");
$parent_stmt->bind_param("i", $selected_subcategory_id);
$parent_stmt->execute();
$parent_row = $parent_stmt->get_result()->fetch_assoc();
$parent_stmt->close();

if ($parent_row) {
    $selected_top_category_id = (int)$parent_row['parent_id'];
}

// Fetch this product's currently selected sizes
$selected_sizes = [];
$size_stmt = $conn->prepare("SELECT size FROM product_sizes WHERE product_id = ?");
$size_stmt->bind_param("i", $product_id);
$size_stmt->execute();
$size_result = $size_stmt->get_result();
while ($row = $size_result->fetch_assoc()) {
    $selected_sizes[] = $row['size'];
}
$size_stmt->close();

// Fetch existing extra gallery images
$extra_images = [];
$extra_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
$extra_stmt->bind_param("i", $product_id);
$extra_stmt->execute();
$extra_result = $extra_stmt->get_result();
while ($row = $extra_result->fetch_assoc()) {
    $extra_images[] = $row['image_url'];
}
$extra_stmt->close();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product - Valora Bazzar</title>
    <link rel="stylesheet" href="add_product.css">
    <link rel="icon" type="image/png" href="valoraa.png">
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
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            user-select: none;
            font-family: "Display", playfair;
        }
        .size-checkboxes input[type="checkbox"] {
            margin: 0;
        }
        .size-checkboxes label:has(input:checked) {
            /* background: #4f081c; */
            color: #4f081c;
            /* color: #fff; */
            border-color: #4f081c;
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
    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main-contents">
        <h2>Edit Product</h2>

        <?php if ($message): ?>
            <div class="message" style="background:#e74c3c;color:white;padding:10px;border-radius:6px;margin-bottom:15px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="productForm" class="product-form" enctype="multipart/form-data" style="height:auto; padding-bottom:30px;">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">

            <div class="product-detail">
                <h3>Product Details</h3>
                <button type="submit" name="submit" class="add-btn">Save Changes</button>
            </div>
            <small style="color:#888; display:block; margin-top:-10px; margin-bottom:15px; font-style: italic; text-align:center;">
                (* Represent Required Fields! that must be filled before submission)
            </small>

            <div class="row">
                <div class="field">
                    <h5>Product Name *</h5>
                    <input type="text" name="product_name" autocomplete="off" required
                           value="<?= htmlspecialchars($product['product_name']) ?>">
                </div>

                <div class="field">
                    <h5>Gender *</h5>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Men" <?= $product['gender']=='Men' ? 'selected' : '' ?>>Men</option>
                        <option value="Women" <?= $product['gender']=='Women' ? 'selected' : '' ?>>Women</option>
                        <option value="All" <?= $product['gender']=='All' ? 'selected' : '' ?>>All (Unisex)</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Category *</h5>
                    <select name="category_id" id="category" required>
                        <option value="">Select Category</option>
                        <?php foreach ($topCategories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"
                                <?= $cat['category_id'] == $selected_top_category_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
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
                                <label>
                                    <input type="checkbox" name="sizes[]" value="<?= $s ?>"
                                        <?= in_array($s, $selected_sizes) ? 'checked' : '' ?>>
                                    <?= $s ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="size-group" id="shoeSizes">
                        <div class="size-checkboxes">
                            <?php foreach (['5','6','7','8','9','10','11','12'] as $s): ?>
                                <label>
                                    <input type="checkbox" name="sizes[]" value="<?= $s ?>"
                                        <?= in_array($s, $selected_sizes) ? 'checked' : '' ?>>
                                    <?= $s ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Price (NPR) *</h5>
                    <input type="number" name="price" id="price" step="0.01" min="0" autocomplete="off" required
                           value="<?= htmlspecialchars($product['price']) ?>">
                </div>

                <div class="field">
                    <h5>Stock Quantity *</h5>
                    <input type="number" name="stock" min="0" autocomplete="off" required
                           value="<?= htmlspecialchars($product['stock_quantity']) ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <h5>Main Product Image</h5>
                    <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Current image"
                             style="width:80px;height:80px;object-fit:cover;border-radius:6px;display:block;margin-bottom:8px;">
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small style="color:#888;">Leave empty to keep the current image.</small>
                </div>

                <div class="field"></div>
            </div>

            <?php if (!empty($extra_images)): ?>
            <div class="row1">
                <div class="field">
                    <h5>Existing Gallery Images</h5>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <?php foreach ($extra_images as $img): ?>
                            <label style="text-align:center; font-size:12px; color:#555;">
                                <?php if (file_exists($img)): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" alt="Gallery image"
                                        style="width:200px;height:200px;object-fit:cover;border-radius:6px;display:block;margin-bottom:4px;">
                                <?php endif; ?>
                                <input type="checkbox" name="delete_images[]" value="<?= htmlspecialchars($img) ?>" style=" font-family: 'Display', playfair;">
                                Delete
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="field">
                    <h5>Add Gallery Image (optional)</h5>
                    <input type="file" name="image2" accept="image/*">
                </div>

                <div class="field">
                    <h5>Add Gallery Image (optional)</h5>
                    <input type="file" name="image3" accept="image/*">
                </div>
            </div>

            <div class="row1">
                <div class="field">
                    <h5>Description</h5>
                    <textarea name="description" id="description"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
    const priceInput = document.getElementById('price');
    priceInput.addEventListener('input', function() {
        if (this.value < 0) this.value = 0;
    });

    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === '-') e.preventDefault();
        });
    });

    const subcategoriesByParent = <?= json_encode($subcategoriesByParent) ?>;
    const selectedTopCategoryId = <?= json_encode((string)$selected_top_category_id) ?>;
    const selectedSubcategoryId = <?= json_encode((string)$selected_subcategory_id) ?>;

    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');

    const clothingSizes = document.getElementById('clothingSizes');
    const shoeSizes = document.getElementById('shoeSizes');
    const noSizeNote = document.getElementById('noSizeNote');

    function populateSubcategories(parentId, preselectId) {
        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
        if (parentId && subcategoriesByParent[parentId]) {
            subcategoriesByParent[parentId].forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.id;
                option.textContent = sub.name;
                if (String(sub.id) === String(preselectId)) {
                    option.selected = true;
                }
                subcategorySelect.appendChild(option);
            });
        }
    }

    // Shows/hides the correct size checkbox group based on the selected top category's name.
    // resetChecks=true clears any checked boxes (used when the user actively changes category);
    // resetChecks=false preserves the server-rendered checked state (used on initial page load).
    function updateSizeGroup(resetChecks) {
        const categoryName = (categorySelect.options[categorySelect.selectedIndex]?.text || '').toLowerCase();

        clothingSizes.classList.remove('active');
        shoeSizes.classList.remove('active');

        if (resetChecks) {
            document.querySelectorAll('#clothingSizes input, #shoeSizes input').forEach(cb => cb.checked = false);
        }

        if (categoryName.includes('shoe')) {
            shoeSizes.classList.add('active');
            noSizeNote.style.display = 'none';
        } else if (categoryName.includes('cloth')) {
            clothingSizes.classList.add('active');
            noSizeNote.style.display = 'none';
        } else if (categoryName) {
            noSizeNote.textContent = 'This category does not require a size.';
            noSizeNote.style.display = 'block';
        } else {
            noSizeNote.textContent = 'Select a category to show its size options.';
            noSizeNote.style.display = 'block';
        }
    }

    // Pre-populate subcategory dropdown on page load with the product's current values
    populateSubcategories(selectedTopCategoryId, selectedSubcategoryId);
    // Show the right size group on load WITHOUT wiping the pre-checked sizes
    updateSizeGroup(false);

    categorySelect.addEventListener('change', function() {
        populateSubcategories(this.value, null);
        updateSizeGroup(true);
    });
</script>

</body>
</html>

<?php $conn->close(); ?>