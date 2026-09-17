<?php
session_start();

$error = $_SESSION['login_error'] ?? '';
session_unset();

function showError($error){
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/png" href="vbzr.png">
    <link rel="stylesheet" href="admin_page.css">
</head>
<body>

<div class="container">
     <div class="login-image">
        <img src="https://fashionweekonline.com/wp-content/uploads/2025/02/Germanier_HCSS25_2x3_26-copy.jpg">
</div>
    <div class="form-box active">
        <form action="admin_login.php" method="post" autocomplete="off">
            <h2>Valora Bazzar</h2>
            <?= showError($error); ?>
            <input type="text" name="email" placeholder="Email..." autocomplete="off" >
            <input type="password" name="password" placeholder="Password..." autocomplete="new-password">
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</div>
</body>
</html>
