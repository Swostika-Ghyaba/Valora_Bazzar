<?php

session_start();

/*
 * Payment failed or was cancelled on eSewa's side.
 * The order row still exists as Pending/Unpaid — that's fine,
 * it just means the user can retry checkout. The cart was
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed — Valora Bazzar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="place_order.css">
    <style>
        .status-wrapper {
            max-width: 520px;
            margin: 80px auto;
            text-align: center;
            padding: 40px 30px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .status-wrapper i { font-size: 56px; margin-bottom: 16px; color: #e74c3c; }
        .status-wrapper h2 { margin: 10px 0; color: #5A0E24 !important; margin-top: 0 !important; font-weight: bold; font-size: 20px !important; font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif; }
        .status-wrapper p { color: #666; margin-bottom: 24px;font-family: 'Times New Roman', Times, serif;font-size: 17px; }
        .status-wrapper a {
            font-family: 'Times New Roman', Times, serif;
            display: inline-block;
            padding: 12px 28px;
            background: green;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin: 0 6px;
        }
        .status-wrapper a:hover{ background: darkgreen;}
        .status-wrapper a.secondary { background: blue;}
        .status-wrapper a.secondary:hover{ backgroun: darkblue;}
    </style>
</head>
<body>

<header>
    <?php include "navbar.php"; ?>
</header>

<div class="status-wrapper">
    <i class="fa-solid fa-circle-xmark"></i>
    <h2>Payment Failed</h2>
    <p>
        Your payment could not be completed, or was cancelled.
        Your cart is still saved, so you can try again.
    </p>
    <a href="place_order.php">Try Again</a>
    <a href="cart.php" class="secondary">Back to Cart</a>
</div>

<footer>
    <?php include 'footer.php'; ?>
</footer>

</body>
</html>