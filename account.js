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
        if (!/^[a-zA-Z _-]{3,}$/.test(name.value.trim())) {
            errors.push({ field: name, msg: "Username must be at least 3 characters and contain only letters, spaces, - or _." });
        }

        // Phone: must start with 97 or 98, followed by 8 more digits (valid Nepal mobile prefix)
        if (!/^(97|98)\d{8}$/.test(phone.value.trim())) {
            errors.push({ field: phone, msg: "Please enter a valid phone number (must start with 97 or 98, 10 digits total)." });
        }

        // Email: must be a Gmail address, matching signup.php's rule
        if (!/^[a-z]+[0-9]*[a-z]*@gmail\.com$/.test(email.value.trim())) {
            errors.push({ field: email, msg: "Please enter a valid Gmail address." });
        }

        // Address: optional, but flag if suspiciously short when provided
        if (address.value.trim().length > 0 && address.value.trim().length < 5) {
            errors.push({ field: address, msg: "Address seems too short — please enter a full address." });
        }

        if (errors.length > 0) {
            e.preventDefault();

            const listHtml = '<ul style="text-align:left; margin:0; padding-left:20px;">'
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