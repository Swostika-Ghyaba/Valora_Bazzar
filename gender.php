<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$gender = isset($_GET['gender']) ? $_GET['gender'] : '';
if (!in_array($gender, ['Men', 'Women'])) {
    die("Invalid gender selection.");
}

// Fetch the 5 top-level categories
$topCategories = [];
$result = $conn->query("SELECT category_id, category_name FROM categories WHERE parent_id IS NULL ORDER BY category_name");
while ($row = $result->fetch_assoc()) {
    $topCategories[] = $row;
}

// Count products per top-level category for this gender (aggregated across all its subcategories)
$categoryCounts = [];
foreach ($topCategories as $cat) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE c.parent_id = ? AND p.gender = ?
    ");
    $stmt->bind_param("is", $cat['category_id'], $gender);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $categoryCounts[$cat['category_id']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($gender) ?>'s Fashion | Valora Bazzar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="navbar.css">
<style>
    .gender-page-wrapper {
        padding: 30px 40px;
    }
    .gender-heading {
        font-size: 26px;
        color: #5A0E24;
        margin-bottom: 25px;
    }
    .category-tile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
    }
    .category-tile {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 25px 15px;
        text-align: center;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .category-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .category-tile h3 {
        font-size: 16px;
        color: #222;
        margin-bottom: 6px;
    }
    .category-tile .count {
        font-size: 13px;
        color: #888;
    }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="gender-page-wrapper">
    <h1 class="gender-heading"><?= htmlspecialchars($gender) ?>'s Fashion</h1>

    <div class="category-tile-grid">
        <?php foreach ($topCategories as $cat): ?>
            <div class="category-tile"
                 onclick="window.location.href='category.php?category_id=<?= $cat['category_id'] ?>&gender=<?= urlencode($gender) ?>'">
                <h3><?= htmlspecialchars($cat['category_name']) ?></h3>
                <div class="count"><?= $categoryCounts[$cat['category_id']] ?> items</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>