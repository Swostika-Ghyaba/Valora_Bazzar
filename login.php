<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("DB Error");

$showError = "";
$showSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, name, password FROM account WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $showSuccess = true;
        } else {
            $showError = "Wrong password!";
        }
    } else {
        $showError = "Phone number not matched!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Fashion Bazaar</title>
<link rel="icon" type="image/png" href="valoraa.png">
<link rel="stylesheet" href="login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="login">
    <div class="login-page"></div>

    <div class="logging">
        <div class="login-form">
        <div class="content">
            <h3>Welcome Back!!</h3>
        </div>
            <!-- <h3>Log into Valora Bazaar</h3> -->
             <p>Welcome back! Please enter your details.</p>
            <form action="login.php" method="post" id="loginForm">
                <div class="form-input">
                    <label for="phone">
                        <i class="fa-solid fa-phone"></i>
                    </label>
                    <input type="tel" name="phone" placeholder="Phone Number" id="phone" maxlength="10" minlength="10" autocomplete="off" required>
                </div>

                <!-- <div class="form-input">
                    <label for="email">
                        <i class="fa-regular fa-envelope"></i>
                    </label>
                    <input type="email" name="email" id="email" placeholder="Email" autocomplete="off" required>
                </div> -->

                <div class="form-input">
                    <label for="password">
                        <i class="fa-solid fa-lock"></i>
                    </label>
                    <input type="password" name="password" id="password" placeholder="Password" autocomplete="off" required>
                </div>

                <button type="submit" class="log">Log in</button>
                <button type="submit" id="forget-password"><a href="forget.php" >Forget password?</a></button>
            </form>
            <button type="submit" id="signup"><a href="signup.php">Create new account</a> </button>

        </div>
    </div>
</div>

<?php if ($showError): ?>
<script>
Swal.fire({ icon:'error', title:'Login Failed :(', text:'<?= $showError ?>', customClass: {
      confirmButton: 'my-btn',
      title: 'my-text' 
  } });
</script>
<?php endif; ?>

<?php if ($showSuccess): ?>
<script>
  // 1. Show the success alert
  Swal.fire({
    icon: 'success',
    title: 'Login Successful',
    text: 'Redirecting...',
    showConfirmButton: false,
    timer: 1500
  });

  // 2. Force redirect after the timer (even if SweetAlert fails)
  setTimeout(function() {
    window.location.href = "index.php";
  }, 1600); // slightly longer than the timer
</script>
<?php endif; ?>

</body>
</html>