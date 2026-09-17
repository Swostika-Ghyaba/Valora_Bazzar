<?php

session_start();
if(isset($_POST['login'])){

    $email = $_POST['email'];
    $password = $_POST['password'];

    $default_email = "valorabazzar@gmail.com";
    $default_password = "valora@32";

    if($email === $default_email && $password === $default_password){

        $_SESSION['name'] = "Admin";   
        $_SESSION['email'] = $email;   

        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['login_error'] = 'Incorrect email or password';
        header("Location: admin_page.php");
        exit();
    }
}
?>