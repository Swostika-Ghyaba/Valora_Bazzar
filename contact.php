<?php

session_start();
$conn = new mysqli("localhost", "root", "", "valora_bazzar");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Add to Cart submission
if(isset($_POST['add_cart'])){
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $image = $_POST['image'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $stock = $_POST['stock'] ?? 0;


    $_SESSION['cart'][] = [
        "name" => $name,
        "price" => $price,
        "image" => $image,
        "quantity" => $quantity,
        "stock" => $stock,
    ];

    // Optional: set a temporary message
    $_SESSION['added_cart'] = "$name added to cart";

    // Redirect back to same page to prevent form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Optional: get the message to show once
$cart_message = $_SESSION['added_cart'] ?? '';
unset($_SESSION['added_cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Valora Bazzar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="valoraa.png">
   <link rel="stylesheet" href="contact.css">
   <link rel="stylesheet" href="navbar.css">
</head>
<body>
<header>
<!-- Nav Bar -->
<?php include 'navbar.php'; ?>
</header>

<section class="contact-section">
    <div class="section-title">
        <h2>Contact Us</h2>
        <h4>Have questions? Reach out to us and we'll get back to you soon!</h4>
    </div>
    
    <div class="contact-us">
        <div class="contact-information">
        <div class="visit-us">
            <div class="visit-logo" title="Visit Us!"><a href="https://maps.app.goo.gl/Q7prVVTurd37BuSbA"><i class="fa-solid fa-location-dot"></i></a></div>
            <h3>Visit Us</h3>
            <p><a href="https://maps.app.goo.gl/zMdZATeZFTgypd2KA" title="Please Visit Our Store Located at Godawari-11 Chapagaun,Lalitpur"> Godawari-11 Chapagaun, Lalitpur</a></p>
            <p><a href="https://maps.app.goo.gl/SJxXg2yyeb7pPGn4A" title="Please Visit Our Store Located At KMC-32 Koteswor, Kathmandu">KMC-32 Koteshwor, Kathmandu</a></p>
        </div>
            
        <div class="call">
            <div class="call-logo" title="Call Us!"><a href="9877777777"><i class="fa-solid fa-phone"></i></a></div>
           
            <div class="call-info">
                <h3>Call Us</h3>
                <p title="If You Have Any Problem Feel Free To Ask Directly In Phone Number +9779800000000. Stay Tune, Stay Connected.">Phone Number: +977 9800000000</p>
                <p title="Have Any Feedback For Our Service  +9779766891520. Stay Tune, Stay Connected.">Phone Number: +977 9766891520</p>
            </div>
        </div>
            
        <div class="email-info">
            <div class="email-logo" title="Email Us!"> <a href="https://mail.google.com/mail/?view=cm&fs=1&to=manisharanamagar2078@gmail.com"> <i class="fa-solid fa-envelope"></i></a></div>

            <div class="email-info-detail">
                <h3>Email Us</h3>
                <p><a href="https://mail.google.com/mail/?view=cm&fs=1&to=valorabazzar.vb@gmail.com&su=More%20Information" target="_blank" title="Email Us For More Information!">For More Info: valorabazzar.vb@gmail.com</a></p>
                <p><a href="https://mail.google.com/mail/?view=cm&fs=1&to=support@valorabazaar.com&su=Order%20Issue" target="_blank" title="Facing an issue with your order? Email us: support@valorabazaar.com">For any Support:  support@valorabazaar.com</a></p>
            </div>
        </div>
    </div>

    <div class="contact-container">
        <div class="contact-info">
            <div class="store-location">
                <div class="store">
                    <h2>Store Location Map</h2>
                </div>
               <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d43049.776836355275!2d85.25057100835065!3d27.708350139904173!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb198a307baabf%3A0xb5137c1bf18db1ea!2sKathmandu%2044600!5e1!3m2!1sen!2snp!4v1773751812441!5m2!1sen!2snp" width="800" height="400" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            
            <ul>
                <div class="link-logo" id="facebook">
                    <a href="https://www.facebook.com/manisha.rana.magar.562608" id="fb"><i class="fa-brands fa-facebook-f"></i></a>
                </div>
            
                <div class="link-logo" id="gmail">
                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=manisharanamagar2078@gmail.com" id="mail" ><i class="fa-regular fa-envelope"></i></a>
                </div>
            
                <div class="link-logo" id="instagram">
                    <a href="https://www.instagram.com/manisharanamagar2078?igsh=MTBtZDkxeWY0YnhncA==" id="insta"><i class="fa-brands fa-instagram"></i></a>
                </div>

                    <!-- <a href="mailto:manisharanamagar2078@gmail.com"class="gr2"><i class="fa-duotone fa-regular fa-envelope" ></i></a> -->
            </ul>

        </div>

    </div>
</section>

<!-- Footer -->
 <footer>
    <?php include 'footer.php'; ?>

 </footer>
</body>
</html>
