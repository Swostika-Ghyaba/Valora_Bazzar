<?php
session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) die("DB Error");

$showSuccess = false;
$showError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);

    // check duplicate phone or email
    $stmt = $conn->prepare("SELECT id FROM account WHERE phone=? OR email=?");
    $stmt->bind_param("ss", $phone, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $showError = "Account already exists with this phone or email";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("INSERT INTO account(name,email,phone,password) VALUES(?,?,?,?)");
        $stmt2->bind_param("ssss", $name, $email, $phone, $hashed);
        if($stmt2->execute()){
            $showSuccess = true;
        } else {
            $showError = "Something went wrong!";
        }
        $stmt2->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signup - Fashion Bazaar</title>
<link rel="icon" type="image/png" href="valoraa.png">
<link rel="stylesheet" href="sign.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- Signup -->
 <div class="signup">
    <div class="sign-in">
        <div class="signing-part">
            <div class="form-part">

            <form action="signup.php" method="post" id="signupForm">
            <h2>Create Account</h2>
            <input type="text" name="name" id="name" placeholder="User Name..." autocomplete="off">
            <input type="text" name="phone" placeholder="Phone Number..." id="phone" maxlength="10" minlength="10" autocomplete="off" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">  
            <input type="email" name="email" id="email"  placeholder="Email..." autocomplete="off">
            <input type="password" name="password" id="password"  placeholder="Password..." autocomplete="off" >
            <button type="submit" value="Sign Up" class="signup_button" name="signup" id="sign">Sign Up</button>
            
            <p class="login">Already Have An Account? <a href="login.php">login</a></p>
            </form>
            </div>
        </div>
        <!-- For the image part -->
        <div class="page">
            <a href="index.php" class="home"><img src="valoraa.png" alt="FashionBazaar Logo" width="30"></a>
            <h2>
                Style Yourself with Valora Bazzar!
            </h2>
            <h4>Trendy fashion made easy. Contact us today and start your stylish shopping habit with quick and convenient doorstep delivery.</h4>
            <a href="contact.php"><button type="submit" id="contact">Contact Us</button></a>
            <a href="clothing.php"><button type="submit" id="order-now">Order Now</button></a>
        </div>
    </div>

</div>
<script>

document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("signupForm");
    if (!form) return;

    form.addEventListener("submit", function (e) {

        const name = document.getElementById("name");
        const phone = document.getElementById("phone");
        const email = document.getElementById("email");
        const password = document.getElementById("password");

        let errors = [];

        if (!/^[a-zA-Z _-]{8,}$/.test(name.value.trim())) {
            errors.push({ field: name, msg: "Username must be at least 8 characters." });
        }

        if (!/^(97|98)\d{8}$/.test(phone.value.trim())) {
            errors.push({ field: phone, msg: "Please enter a valid phone number." });
        }

        if (!/^[a-z]+[0-9]*[a-z]*@gmail\.com$/.test(email.value.trim())) {
            errors.push({ field: email, msg: "Please enter a valid Gmail address." });
        }

        if (!/^(?=.*\d)(?=.*[@$!%*?&]).{5,}$/.test(password.value.trim())) {
            errors.push({ field: password, msg: "Password must be at least 5 characters with 1 number and 1 special character." });
        }

        if (errors.length > 0) {
            e.preventDefault();

            Swal.fire({
                icon: "error",
                title: "Signup Failed 😔",
                text: errors[0].msg,

                confirmButtonText: "OK",
                didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = "#dc2626";
                    btn.style.color = "#fff";
                    btn.style.fontWeight = "bold";
                    btn.style.fontSize = "16px";
                    btn.style.width = "100px";
                    btn.style.height = "40px"
                    btn.style.borderRadius = "4px";
                    btn.style.padding = "8px 24px";
                    btn.style.marginButton = "40px";
                    btn.style.border = "none";
                    btn.style.outline = "none";
                    btn.style.cursor = "pointer";
                    btn.style.setProperty('outline', 'none', 'important');
                    btn.style.setProperty('box-shadow', 'none', 'important');
                    btn.onmouseenter = () => btn.style.backgroundColor = "#15803d";
                    btn.onmouseleave = () => btn.style.backgroundColor = "#dc2626";

                    const content = Swal.getHtmlContainer();
                    content.style.color = "red";

                    errors[0].field.focus();
                }
            });
        }

    });

});
</script>

<?php


// Error alert
if ($showError) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Signup Failed 😔',
            text: '$showError',
            confirmButtonText: 'OK',
            didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = '#dc2626';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'bold';
                    btn.style.fontSize = '16px';
                    btn.style.width = '100px';
                    btn.style.height = '40px'
                    btn.style.borderRadius = '4px';
                    btn.style.padding = '8px 24px';
                    btn.style.marginButton = '40px';
                    btn.style.border = 'none';
                    btn.style.outline = 'none';
                    btn.style.cursor = 'pointer';
                    btn.style.setProperty('outline', 'none', 'important');
                    btn.style.setProperty('box-shadow', 'none', 'important');
                    btn.onmouseenter = () => btn.style.backgroundColor = '#15803d';
                    btn.onmouseleave = () => btn.style.backgroundColor = '#dc2626';

                    const content = Swal.getHtmlContainer();
                    content.style.color = 'red';
            }
        });
    </script>";
}
// Success alert
elseif ($showSuccess) {
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Signup Successful! 🎉',
            text: 'You can now go to main page.',
            confirmButtonText: 'OK',
            didOpen: () => {
                const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = 'green';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'bold';
                    btn.style.fontSize = '16px';
                    btn.style.width = '100px';
                    btn.style.height = '40px'
                    btn.style.borderRadius = '4px';
                    btn.style.padding = '8px 24px';
                    btn.style.marginButton = '40px';
                    btn.style.border = 'none';
                    btn.style.outline = 'none';
                    btn.style.cursor = 'pointer';
                    

                    const content = Swal.getHtmlContainer();
                    content.style.color = 'green';

            }
        }).then(() => { window.location.href='index.php'; });
    </script>";
}
?>

</body>
</html>