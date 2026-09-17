<?php
session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Database Connection Failed");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$showMsg = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email            = trim($_POST['email']);
    $currentPassword  = $_POST['current_password'];
    $newPassword      = $_POST['new_password'];
    $confirmPassword  = $_POST['confirm_password'];

    // Verify identity (email and current password) first
    $stmt = $conn->prepare("SELECT email, password FROM account WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $emailMatches    = strcasecmp($email, $user['email']) === 0;
        $passwordMatches = password_verify($currentPassword, $user['password']);

        if (!$emailMatches && !$passwordMatches) {
            $showMsg = "Please enter registered Email!<br>Current password is also not matched!";
            $msgType = "error";

        } elseif (!$emailMatches) {
            $showMsg = "Email does not match our records!";
            $msgType = "error";

        } elseif (!$passwordMatches) {
            $showMsg = "Current password is incorrect!";
            $msgType = "error";

        } else {
            // Identity confirmed 
            if ($newPassword !== $confirmPassword) {
                $showMsg = "New passwords do not match!";
                $msgType = "error";

            } elseif (password_verify($newPassword, $user['password'])) {
                // New password same as current one
                $showMsg = "New password cannot be the same as your current password!";
                $msgType = "error";

            } else {
                // Check password strength
                // Password with min 5 chars, at least 1 letter, 1 number, 1 special char
                $isStrong = strlen($newPassword) >= 5
                    && preg_match('/[A-Za-z]/', $newPassword)
                    && preg_match('/[0-9]/', $newPassword)
                    && preg_match('/[\W_]/', $newPassword);

                if (!$isStrong) {
                    $showMsg = "Password must be at least 5 characters and include<br>a letter, a number, and a special character!";
                    $msgType = "error";

                } else {
                    // All checks passed — update the password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE account SET password = ? WHERE id = ?");
                    $update->bind_param("si", $hashedPassword, $user_id);

                    if ($update->execute()) {
                        $showMsg = "Password changed successfully!";
                        $msgType = "success";
                    } else {
                        $showMsg = "Failed to change password.";
                        $msgType = "error";
                    }
                    $update->close();
                }
            }
        }

    } else {
        $showMsg = "Account not found!";
        $msgType = "error";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="valoraa.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="change_password.css">
    <title>Change Password | Valora Bazaar</title>
    <style>
        /* SweetAlert2 custom styling */
        .my-popup { border-radius: 12px; }
        .my-title-class { font-size: 20px !important; color: #5A0E24 !important; font-weight: 700 !important;}
        .my-text-class { font-size: 17px !important; color: gray !important; line-height: 1.5;}
        .my-ok-btn { width: 100px !important; border: none !important; outline: none !important; border-radius: 6px !important; box-shadow: none !important; font-weight: 600 !important;}
    </style>
</head>

<body>

<div class="change-password">

    <form method="POST">
        <h2>Change Password</h2>
        <!-- Current Password -->
        <div class="password-info">
                <h3>Password Information</h3>
                <div class="back-account"><a href="account.php">Back to Account</a></div>
        </div>

        <p class="change-subtitle">(Enter your registered email, current password, and a new password to continue.)</p>

        <!-- Email -->
        <div class="form-input">
            <label><i class="fa-regular fa-envelope"></i></label>
            <input type="email" name="email" placeholder="Registered Email" autocomplete="off" required>
        </div>

        <div class="form-input">
            <label><i class="fa-solid fa-lock"></i></label>
            <input type="password" name="current_password" placeholder="Current Password" required>
        </div>

        <!-- New Password -->
        <div class="form-input">
            <label><i class="fa-solid fa-key"></i></label>
            <input type="password" name="new_password" placeholder="New Password" required>
        </div>

        <!-- Confirm Password -->
        <div class="form-input">
            <label><i class="fa-solid fa-key"></i></label>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        </div>

        <button type="submit">Change Password</button>
    </form>
</div>


<?php if ($showMsg): ?>
<script>
Swal.fire({
    icon: <?= json_encode($msgType) ?>,
    title: <?= json_encode($msgType === "success" ? "Successfully change the Password :)" : "Password Not Changed :(") ?>,
    html: <?= json_encode($showMsg) ?>,
    confirmButtonText: 'OK',
    confirmButtonColor: '<?= $msgType === "success" ? "#5A0E24" : "#d33" ?>',
    customClass: {
        popup: 'my-popup',
        title: 'my-title-class',
        htmlContainer: 'my-text-class',
        confirmButton: 'my-ok-btn'
    }
}).then(() => {
    <?php if ($msgType === "success"): ?>
    window.location.href = "account.php";
    <?php endif; ?>
});
</script>
<?php endif; ?>

</body>
</html>