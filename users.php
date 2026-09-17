<?php
require_once 'admin_auth.php';

$conn = new mysqli('localhost', 'root', '', 'valora_bazzar');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Show only 10 users initially
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

if ($view_all) {
    $result = $conn->query("SELECT * FROM account ORDER BY id ASC");
} else {
    $result = $conn->query("SELECT * FROM account ORDER BY id ASC LIMIT 10");
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Valora Bazzar Admin</title>
    <link rel="icon" type="image/png" href="valora_bazzar.png">
    <link rel="stylesheet" href="users.css">
</head>

<body>
<div class="admin-page">
    <!-- Sidebar -->
    <div class="side">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-contents">

        <div class="title-content">
    <h2>Users Information</h2>

    <?php if (!$view_all): ?>
        <a href="users.php?view=all" class="view-all-btn">
            View All
        </a>
    <?php else: ?>
        <a href="users.php" class="view-all-btn">
            Show Less
        </a>
    <?php endif; ?>
    </div>
        <table class="admin-table">

            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Profile</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Joined At</th>
                    <th>Address</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($user = $result->fetch_assoc()): ?>

                <tr>

                    <!-- User ID -->
                    <td>
                        <?= $user['id']; ?>
                    </td>

                    <!-- Profile -->
                    <td>

                        <?php if (!empty($user['profile']) && file_exists($user['profile'])): ?>

                            <img 
                                src="<?= htmlspecialchars($user['profile']); ?>"
                                alt="<?= htmlspecialchars($user['name']); ?>"
                                width="70"
                                height="70"
                            >

                        <?php else: ?>

                            <span style="font-family:'Times New Roman', Times, serif;">No Image</span>

                        <?php endif; ?>

                    </td>

                    <!-- Name -->
                    <td>
                        <?= htmlspecialchars($user['name']); ?>
                    </td>

                    <!-- Phone -->
                    <td>
                        <?= htmlspecialchars($user['phone']); ?>
                    </td>

                    <!-- Email -->
                    <td>
                        <?= htmlspecialchars($user['email']); ?>
                    </td>

                    <!-- Joined At -->
                    <td>
                        <?= htmlspecialchars($user['created_at']); ?>
                    </td>

                    <!-- Address -->
                    <td>
                        <?php
                        if (!empty($user['address'])) {
                            echo htmlspecialchars($user['address']);
                        } else {
                            echo "No Address";
                        }
                        ?>
                    </td>

                </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>