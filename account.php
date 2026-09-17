<?php
session_start();

$conn = new mysqli("localhost", "root", "", "valora_bazzar");
if ($conn->connect_error) {
    die("DB Error");
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

/* ---------- CSRF token ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function csrf_ok(array $post, string $sessionToken): bool
{
    return isset($post['csrf_token']) && hash_equals($sessionToken, $post['csrf_token']);
}

$flash = ['type' => '', 'msg' => ''];

/* ---------- Profile picture upload (hardened) ---------- */
if ($user_id && isset($_POST['upload']) && isset($_FILES['profile'])) {

    if (!csrf_ok($_POST, $csrf_token)) {
        $flash = ['type' => 'error', 'msg' => 'Session expired, please refresh and try again.'];
    } else {
        $file = $_FILES['profile'];

        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/webp' => ['webp'],
        ];
        $maxBytes = 5 * 1024 * 1024; // 5MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $flash = ['type' => 'error', 'msg' => 'Upload failed. Please try again.'];
        } elseif ($file['size'] > $maxBytes) {
            $flash = ['type' => 'error', 'msg' => 'Image must be smaller than 5MB.'];
        } else {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $mimeAllowed = isset($allowedMime[$mimeType]);
            $extAllowed  = in_array($ext, $allowedExt, true);
            $extMatchesMime = $mimeAllowed && in_array($ext, $allowedMime[$mimeType], true);

            if (!$mimeAllowed || !$extAllowed || !$extMatchesMime) {
                $flash = ['type' => 'error', 'msg' => 'Only JPG, PNG or WEBP images are allowed.'];
            } else {
                $folder = "uploads/";
                if (!is_dir($folder)) {
                    mkdir($folder, 0755, true);
                    // Prevent execution of any script that ever ends up in this folder
                    file_put_contents($folder . ".htaccess", "php_flag engine off\n");
                }

                // Random, non-guessable filename - never trust the client-supplied name
                $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                $path    = $folder . $newName;

                if (move_uploaded_file($file['tmp_name'], $path)) {
                    // Remove the old picture so uploads/ doesn't fill up with orphans
                    $old = $conn->prepare("SELECT profile FROM account WHERE id=?");
                    $old->bind_param("i", $user_id);
                    $old->execute();
                    $oldPath = $old->get_result()->fetch_assoc()['profile'] ?? '';
                    if ($oldPath && is_file($oldPath) && strpos($oldPath, $folder) === 0) {
                        @unlink($oldPath);
                    }

                    $stmt = $conn->prepare("UPDATE account SET profile=? WHERE id=?");
                    $stmt->bind_param("si", $path, $user_id);
                    $stmt->execute();
                    $flash = ['type' => 'success', 'msg' => 'Profile photo updated.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Could not save the image. Please try again.'];
                }
            }
        }
    }
}

/* ---------- Save account details ---------- */
if ($user_id && isset($_POST['saveAll'])) {

    if (!csrf_ok($_POST, $csrf_token)) {
        $flash = ['type' => 'error', 'msg' => 'Session expired, please refresh and try again.'];
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = "Username can't be empty.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Enter a valid email address.";
        }
        if (!preg_match('/^\d{10}$/', $phone)) {
            $errors[] = "Phone number must be exactly 10 digits.";
        }

        if ($errors) {
            $flash = ['type' => 'error', 'msg' => implode(' ', $errors)];
        } else {
            $stmt = $conn->prepare("UPDATE account
                                    SET name=?, email=?, phone=?, address=?
                                    WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $address, $user_id);
            $stmt->execute();

            $_SESSION['user_name'] = $name;

            // MySQL reports 0 affected rows when the new values are identical
            // to what's already stored, so this correctly detects "nothing changed"
            if ($stmt->affected_rows > 0) {
                $flash = ['type' => 'success', 'msg' => 'Your details have been updated successfully.'];
            } else {
                $flash = ['type' => 'info', 'msg' => 'No changes were made.'];
            }
        }
    }
}

/* ---------- Load user ---------- */
$user = [
    'name'    => '',
    'email'   => '',
    'phone'   => '',
    'address' => '',
    'profile' => '',
];

if ($user_id) {
    $stmt = $conn->prepare("SELECT name, phone, email, address, profile FROM account WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
}

$initials = '';
if (!empty($user['name'])) {
    $parts = preg_split('/\s+/', trim($user['name']));
    $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Account · Valora Bazaar</title>
<link rel="icon" type="image/png" href="valoraa.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="account.css">
</head>
<body>

<?php if (!$user_id): ?>
    <div class="empty-msg">
        <div class="error">
            <img src="no-accounts.png" width="100" alt="No Account Image">
            <h2>No account is logged in right now</h2>
            <h3>Sign in to access your account Or create an account to get started.</h3>
            <div class="msg">
                <a href="login.php" class="login"><button>Login</button></a>
                <a href="signup.php" class="signup"><button>Sign Up</button></a>
            </div>
        </div>
        <div class="error-pic">
            <img src="no-accounts.png" width="420" alt="">
        </div>
    </div>

<?php else: ?>

    <div class="user">

        <div class="account">
            <h2>Account Information</h2>

            <div class="order-information">
            <div class="order-view">
                <div class="home"><a href="index.php" title="Go to homepage"><i class="fa-solid fa-house"></i>Home</a></div>
                <div class="cart"><a href="cart.php" title="Cart"><i class="fa-solid fa-cart-shopping"></i> Cart</a></div>
                <div class="Order-history"><a href="order_history.php" title="View order history"><i class="fa-solid fa-clock-rotate-left"></i>Order History</a></div>
                <div class="wishlist"><a href="wishlist.php" title="Wishlist"><i class="fa-solid fa-heart"></i> Wishlist</a></div>
                <div class="shop-now"><a href="index.php#products"><i class="fa-solid fa-store"></i> Shop Now</a></div>
                <div class="logout"><a href="logout.php" id="logoutLink" title="Log out of your account">Logout</a></div>
            </div>

            <div class="header">
                <div class="profile-header">
            <?php if ($flash['msg'] && $flash['type'] === 'success'): ?>
                <div class="toast">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($flash['msg']); ?></span>
                </div>
            <?php endif; ?>
                    <div class="cover-pic">
                        <form method="post" enctype="multipart/form-data" id="imageForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload" value="1">
                            <div class="profile-pic">
                                <?php if (!empty($user['profile'])): ?>
                                    <img id="preview" src="<?php echo htmlspecialchars($user['profile']); ?>" alt="Profile photo" onclick="openPhotoModal(this.src)">
                                <?php else: ?>
                                    <span id="preview-initials" style="color:#760031;font-family:'Fraunces',serif;font-size:32px;"><?php echo htmlspecialchars($initials ?: '?'); ?></span>
                                <?php endif; ?>
                                <label for="profileInput" class="upload-icon" title="Change photo">
                                    <i class="fa-solid fa-camera"></i>
                                </label>
                            </div>

                            <input type="file" name="profile" id="profileInput"
                                   accept="image/png, image/jpeg, image/webp" style="display:none;">
                        </form>
                    </div>

                    <div class="user-info">
                        <h1><?php echo htmlspecialchars($user['name'] ?: 'Your Name'); ?></h1>
                        <p><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?: 'Your email'); ?></p>
                        <p><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?: 'Your phone'); ?></p>
                    </div>
                </div>

                <div class="profile-card">
                    <form method="post" id="accountForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="row">
                            <div class="field">
                                <h5>Username</h5>
                                <label>
                                    <input type="text" name="name" id="name"
                                           value="<?php echo htmlspecialchars($user['name']); ?>"
                                           placeholder="Enter your username" required autocomplete="off">
                                    <i class="fa-solid fa-circle-user"></i>
                                </label>
                            </div>

                            <div class="field">
                                <h5>Phone</h5>
                                <label>
                                    <input type="tel" name="phone" id="phone"
                                           value="<?php echo htmlspecialchars($user['phone']); ?>"
                                           placeholder="Enter your phone" required autocomplete="off"
                                           pattern="\d{10}" maxlength="10" minlength="10"
                                           title="Enter a 10-digit phone number">
                                    <i class="fa-solid fa-phone"></i>
                                </label>
                            </div>
                        </div>

                        <div class="row1">
                            <div class="field">
                                <h5>Email</h5>
                                <label>
                                    <input type="email" name="email" id="email"
                                           value="<?php echo htmlspecialchars($user['email']); ?>"
                                           placeholder="Enter your email" required autocomplete="off">
                                    <i class="fa-regular fa-envelope"></i>
                                </label>
                            </div>

                            <div class="field">
                                <h5>Address</h5>
                                <label>
                                    <input type="text" name="address" id="address"
                                           value="<?php echo htmlspecialchars($user['address']); ?>"
                                           placeholder="Enter your address" autocomplete="off">
                                    <i class="fa-solid fa-location-dot"></i>
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="saveAll">
                            <i class="fa-solid fa-download"></i> Save Changes
                        </button>
                        <a href="change_password.php" id="password-change"><i class="fa-solid fa-pen-to-square"></i> Change Password</a>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <div class="photo-modal" id="photoModal" onclick="closePhotoModal(event)">
        <span class="photo-modal-close" onclick="closePhotoModal(event)" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </span>
        <img id="photoModalImg" src="" alt="Profile photo, enlarged">
    </div>
</div>
<?php endif; ?>

<script>
    // Auto-submit the photo form as soon as a file is chosen, with basic client-side checks
const profileInput = document.getElementById('profileInput');
if (profileInput) {
    profileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File',
                text: 'Please choose a JPG, PNG or WEBP image.',
                confirmButtonText: 'OK',
                didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = '#dc2626';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'bold';
                    btn.style.fontSize = '16px';
                    btn.style.width = '100px';
                    btn.style.height = '40px';
                    btn.style.borderRadius = '4px';
                    btn.style.padding = '8px 24px';
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
            this.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Image must be smaller than 5MB.',
                confirmButtonText: 'OK',
                didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = '#dc2626';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'bold';
                    btn.style.fontSize = '16px';
                    btn.style.width = '100px';
                    btn.style.height = '40px';
                    btn.style.borderRadius = '4px';
                    btn.style.padding = '8px 24px';
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
            this.value = '';
            return;
        }
        document.getElementById('imageForm').submit();
    });
}

// Friendly inline validation before the account form submits
const accountForm = document.getElementById('accountForm');
if (accountForm) {
    accountForm.addEventListener('submit', function (e) {

        const name    = document.getElementById('name');
        const phone   = document.getElementById('phone');
        const email   = document.getElementById('email');
        const address = document.getElementById('address');

        let errors = [];

        // Username: letters, spaces, - and _ only, min 3 chars
        if (!/^[a-zA-Z _-]{8,}$/.test(name.value.trim())) {
            errors.push({ field: name, msg: "Username must be at least 3 characters!" });
        }

        // Phone: must start with 97 or 98, followed by 8 more digits (valid Nepal mobile prefix)
        if (!/^(97|98)\d{8}$/.test(phone.value.trim())) {
            errors.push({ field: phone, msg: "Please, enter a valid phone number!" });
        }

        // Email: must be a Gmail address, matching signup.php's rule
        if (!/^[a-z]+[0-9]*[a-z]*@gmail\.com$/.test(email.value.trim())) {
            errors.push({ field: email, msg: "Please, enter a valid Gmail address!" });
        }

        // Address: optional, but flag if suspiciously short when provided
        if (address.value.trim().length > 0 && address.value.trim().length < 5) {
            errors.push({ field: address, msg: "Address seems too short — please enter a full address." });
        }

        if (errors.length > 0) {
            e.preventDefault();

            const listHtml = '<ul style=" margin:0; padding-left:20px;">'
                + errors.map(err => '<li>' + err.msg + '</li>').join('')
                + '</ul>';

            Swal.fire({
                icon: 'error',
                title: 'Please Check Your Details',
                html: listHtml,
                confirmButtonText: 'OK',
                didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    btn.style.backgroundColor = '#dc2626';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'bold';
                    btn.style.fontSize = '16px';
                    btn.style.width = '100px';
                    btn.style.height = '40px';
                    btn.style.borderRadius = '4px';
                    btn.style.padding = '8px 24px';
                    btn.style.border = 'none';
                    btn.style.outline = 'none';
                    btn.style.cursor = 'pointer';
                    btn.style.setProperty('outline', 'none', 'important');
                    btn.style.setProperty('box-shadow', 'none', 'important');
                    btn.onmouseenter = () => btn.style.backgroundColor = '#15803d';
                    btn.onmouseleave = () => btn.style.backgroundColor = '#dc2626';

                    const content = Swal.getHtmlContainer();
                    content.style.color = 'red';

                    errors[0].field.focus();
                }
            });
        }

    });
}

// Click the profile photo to view it enlarged
function openPhotoModal(src) {
    const modal = document.getElementById('photoModal');
    const img = document.getElementById('photoModalImg');
    if (!modal || !img) return;
    img.src = src;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePhotoModal(e) {
    // Only close when clicking the backdrop or the close button, not the image itself
    if (e.target.id === 'photoModalImg') return;
    const modal = document.getElementById('photoModal');
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('photoModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});
</script>
</body>
</html>