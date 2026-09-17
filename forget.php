<?php
$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Database Connection Failed");
}

$showMsg = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // STEP 1: Check if email exists first
    $stmt = $conn->prepare("SELECT id FROM account WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $showMsg = "Email not found!";
        $msgType = "error";

    } else {

        $user    = $result->fetch_assoc();
        $user_id = $user['id'];

        // Email confirmed — now check password strength
        if (!preg_match('/^(?=.*\d)(?=.*[@$!%*?&]).{5,}$/', $password)) {

            $showMsg = "Password must be at least 5 characters with 1 number and 1 special character.";
            $msgType = "error";

        } else {

            // Both checks passed — update the password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("UPDATE account SET password = ? WHERE id = ?");
            $stmt2->bind_param("si", $hashed, $user_id);

            if ($stmt2->execute()) {

                $showMsg = "Password updated successfully!";
                $msgType = "success";

            } else {

                $showMsg = "Error updating password.";
                $msgType = "error";
            }

            $stmt2->close();
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="forget.css">
    <link rel="icon" type="image/png" href="valoraa.png">
    <title>Forgot Password | Valora Bazaar</title>
</head>

<body>
<div class="forget">
    <div class="forget-pass">

        <form method="post" id="forgetForm">

            <h2 class="forget-title">Forgot Password</h2>

            <div class="account-info">
                <h3>Reset Password</h3>
                <div class="back-login"> <a href="login.php">Back to Login</a></div>
            </div>

            <p class="forget-subtitle">(Enter your email and create a new password.)</p>
            <!-- Email -->
            <div class="form-input">
                <label for="email"><i class="fa-regular fa-envelope"></i></label>
                <input type="email" name="email" id="email" placeholder="Email..." autocomplete="off" required>
            </div>

            <!-- New Password -->

            <div class="form-input">
                <label for="password">
                    <i class="fa-solid fa-lock"></i>
                </label>

                <input type="password" name="password" id="password" placeholder="New Password..." autocomplete="new-password" required>

            </div>

            <button type="submit">Reset Password</button>
        </form>

    </div>
</div>

<?php if ($showMsg): ?>

<script>

Swal.fire({
    icon: <?= json_encode($msgType) ?>,

    title: <?= json_encode($msgType === "success"
        ? "Password Changed Successfully :)"
        : "Password Not Changed :(") ?>,

    html: <?= json_encode($showMsg) ?>,

    confirmButtonText: 'OK',

    customClass: {
        title: 'my-title',
        htmlContainer: 'my-text',
        confirmButton: 'my-btn'
    }

}).then((result) => {

    <?php if ($msgType === "success"): ?>

    if (result.isConfirmed) {
        window.location.href = "login.php";
    }

    <?php endif; ?>

});

</script>

<?php endif; ?>

</body>
</html>