// For product details show product, add cart and wishlist
function toggleWishlist(btn, productId) {
    btn.classList.toggle('active');
    const icon = btn.querySelector('i');
    if (btn.classList.contains('active')) {
        icon.classList.remove('fa-regular');
        icon.classList.add('fa-solid');
        // TODO: send an AJAX/fetch request here to save to favorites.php in the backend
    } else {
        icon.classList.remove('fa-solid');
        icon.classList.add('fa-regular');
        // TODO: send a request to remove from favorites
    }
}

// Quantity increment/decrement
document.querySelectorAll('.box').forEach(box => {
    const decrementBtn = box.querySelector('.decrement');
    const incrementBtn = box.querySelector('.increment');
    const quantityInput = box.querySelector('.quantity-input');

    if (decrementBtn && incrementBtn && quantityInput) {
        decrementBtn.addEventListener('click', () => {
            let val = parseFloat(quantityInput.value);
            let min = parseFloat(quantityInput.min) || 1;
            if (val > min) {
                quantityInput.value = val - 1;
            }
        });

        incrementBtn.addEventListener('click', () => {
            let val = parseFloat(quantityInput.value);
            let max = parseFloat(quantityInput.max) || 999;
            if (val < max) {
                quantityInput.value = val + 1;
            }
        });
    }
});


// |--------------All The Search Logic Part -------------------|
const productBox = document.querySelector(".product-info-box");
const inputBox = document.querySelector("#input-box");
const searchIcon = document.querySelector(".search-icon");

// Holds real products, injected directly by PHP in navbar.php (no fetch/JSON needed)
let available_products = window.available_products || [];

inputBox.addEventListener("keyup", searchProducts);

inputBox.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
        e.preventDefault();
        showProductInfo();
    }
});

searchIcon.addEventListener("click", function (e) {
    e.preventDefault();
    showProductInfo();
});

function searchProducts() {
    let input = inputBox.value.trim().toLowerCase();

    let results = [];
    if (input) {
        results = available_products.filter(p =>
            p.name.toLowerCase().includes(input)
        );
    }

    display(results);
}

function showProductInfo() {
    let query = inputBox.value.trim();

    let match = available_products.find(
        p => p.name.toLowerCase() === query.toLowerCase()
    );
    
    if (match) {
        // Go to dynamic PHP page using the product's real ID
        window.location.href = "productDetails.php?id=" + match.id;
    } else {
        Swal.fire({
            icon: "error",
            title: "Product Not Found 😔",
            html: `Sorry, <strong style="color:#5A0E24;">${query}</strong> is not available.`,
            confirmButtonText: "OK",
            customClass: {
                title: 'swal-title',
                htmlContainer: 'swal-text',
                confirmButton: 'cbutton'
            }
        });
    }
}

// Renders the dropdown list of matching suggestions
function display(results) {
    if (!results.length) {
        productBox.innerHTML = "";
        return;
    }

    let html = "<ul>";
    results.forEach(item => {
        html += `<li data-id="${item.id}" data-name="${item.name}">${item.name}</li>`;
    });
    html += "</ul>";

    productBox.innerHTML = html;

    // Click a suggestion to navigate straight to that product
    productBox.querySelectorAll("li").forEach(li => {
        li.addEventListener("click", () => {
            inputBox.value = li.dataset.name;
            productBox.innerHTML = "";
            window.location.href = "productDetails.php?id=" + li.dataset.id;
        });
    });
}

// Close the suggestions dropdown when clicking outside it
document.addEventListener("click", (e) => {
    if (!productBox.contains(e.target) && e.target !== inputBox) {
        productBox.innerHTML = "";
    }
});


// Account hover
const acc = document.querySelector(".account");
const icon = acc.querySelector("i");

acc.addEventListener("mouseover", () => {
    icon.classList.replace("fa-user", "fa-circle-user");
    icon.style.fontSize = '16px';
});

acc.addEventListener("mouseout", () => {
    icon.classList.replace("fa-circle-user", "fa-user");
    icon.style.fontSize = '16px';
});


// Login hover
const login = document.querySelector("#loginLink");
const iconlogin = login.querySelector("i");

login.addEventListener("mouseover", () => {
    iconlogin.classList.replace("fa-right-to-bracket", "fa-person-running");
    iconlogin.style.fontSize = '17px';
});

login.addEventListener("mouseout", () => {
    iconlogin.classList.replace("fa-person-running", "fa-right-to-bracket");
});


// Wishlist hover
const wishlist = document.querySelector("#wishlist");

if (wishlist) {
    const iconwish = wishlist.querySelector("i");

    wishlist.addEventListener("mouseenter", () => {
        iconwish.classList.replace("fa-regular", "fa-solid");
        iconwish.style.color = "red";
    });

    wishlist.addEventListener("mouseleave", () => {
        iconwish.classList.replace("fa-solid", "fa-regular");
        iconwish.style.color = "white";
    });
}




// Cart quantity increment/decrement (the ".cart" variant buttons)
document.querySelectorAll(".box").forEach(box => {
    const input = box.querySelector(".quantity-input");
    const plus = box.querySelector(".cart.increment");
    const minus = box.querySelector(".cart.decrement");

    if (!input || !plus || !minus) return;

    plus.addEventListener("click", () => {
        let value = parseFloat(input.value) || 0;
        let step = parseFloat(input.step) || 0.5;
        let max = parseFloat(input.max) || 15;
        if (value + step <= max) input.value = (value + step).toFixed(1);
    });

    minus.addEventListener("click", () => {
        let value = parseFloat(input.value) || 0;
        let step = parseFloat(input.step) || 0.5;
        let min = parseFloat(input.min) || 0.5;
        if (value - step >= min) input.value = (value - step).toFixed(1);
    });

    // Prevent typing invalid characters
    input.addEventListener("keydown", e => {
        if (e.key === "-" || e.key === "e") e.preventDefault();
    });

    // Enforce min/max on typing
    input.addEventListener("input", () => {
        let value = parseFloat(input.value) || 0;
        let min = parseFloat(input.min) || 0.5;
        let max = parseFloat(input.max) || 15;
        if (value > max) input.value = max;
        if (value < min) input.value = min;
    });
});

// Stock validation on Add to Cart
function addToCart(button) {
    const form = button.closest('form');
    const stock = parseFloat(form.querySelector('[name="stock"]').value);
    const quantity = parseFloat(form.querySelector('.quantity-input').value);
    const name = form.querySelector('[name="name"]').value;

    // Out of Stock
    if (stock == 0) {
        Swal.fire({
            icon: 'error',
            title: 'Out of Stock!',
            html: `Sorry, <strong style="color:red;">${name}</strong> is currently unavailable.`,
            confirmButtonText: "OK",
            customClass: {
                confirmButton: 'my-custom-btn',
                title: 'swal-title'
            }
        });
        return;
    }

    // Quantity exceeds stock
    if (quantity > stock) {
        Swal.fire({
            icon: 'warning',
            title: 'Not Enough Stock!',
            html: `Only <strong style="color:#e67e00 ;">${stock}</strong> Kg of <strong style="color:#e67e00 ;">${name}</strong> is available.`,
            confirmButtonText: "OK",
            customClass: {
                confirmButton: 'btn',
                title: 'enough'
            }
        });
        return;
    }

    // Success — show alert then submit
    Swal.fire({
        icon: 'success',
        title: 'Added to Cart! 🛒',
        text: `${quantity} Kg of ${name} added to cart.`,
        html: `${quantity} Kg of <strong style="color:#2e7d32;">${name}</strong> added to cart`,
        titleColor: '#2e7d32',
        confirmButtonText: 'OK',
        customClass: {
            confirmButton: 'my-btn',
            title: 'sucess'
        },
        timer: 3000,
        timerProgressBar: true
    }).then(() => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'add_cart';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    });
}