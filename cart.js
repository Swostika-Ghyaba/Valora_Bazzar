document.querySelectorAll(".quantity-controls").forEach(box => {
    const input = box.querySelector(".quantity-input");
    const plus = box.querySelector(".increment");
    const minus = box.querySelector(".decrement");
    const form = box.closest("form");

    if(!input || !plus || !minus || !form) return;

    plus.addEventListener("click", () => {
        let value = parseFloat(input.value) || 0;
        let max = parseFloat(input.max) || 20;
        if(value + 1 <= max){
            input.value = value + 1;
            submitUpdate(form);
        }
    });

    minus.addEventListener("click", () => {
        let value = parseFloat(input.value) || 0;
        let min = parseFloat(input.min) || 1;
        if(value - 1 >= min){
            input.value = value - 1;
            submitUpdate(form);
        }
    });

    // Prevent typing invalid characters, submit on Enter
    input.addEventListener("keydown", e => {
        if(e.key === "-" || e.key === "e") {
            e.preventDefault();
            return;
        }
        if(e.key === "Enter"){
            e.preventDefault();
            submitUpdate(form);
        }
    });

    // Enforce min/max, and auto-submit when user clicks away after typing
    input.addEventListener("input", () => {
        let value = parseFloat(input.value) || 0;
        let min = parseFloat(input.min) || 1;
        let max = parseFloat(input.max) || 20;
        if(value > max) input.value = max;
        if(value < min) input.value = min;
    });

    input.addEventListener("blur", () => {
        submitUpdate(form);
    });
});

// Shared submit logic
function submitUpdate(form){
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'update_cart';
    hidden.value = '1';
    form.appendChild(hidden);
    form.submit();
}

function updateCart(button) {
    const form = button.closest('form');
    submitUpdate(form);
}