document.addEventListener("DOMContentLoaded", function () {
    const closeBtn = document.querySelector(".close-tooltip");
    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            this.parentElement.style.display = "none";
        });
    }
});
