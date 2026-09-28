document.addEventListener("DOMContentLoaded", function () {
    const openModalButtons = document.querySelectorAll(".open-points-modal");
    const closeModalButton = document.querySelector(".close-modal");
    const modal = document.getElementById("points-modal");
    let overlay = document.querySelector(".modal-overlay");
    let lastFocusedElement;

    if (!overlay) {
        overlay = document.createElement("div");
        overlay.classList.add("modal-overlay");
        overlay.setAttribute("aria-hidden", "true");
        document.body.appendChild(overlay);
    }

    function handleEscape(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    }

    function openModal() {
        lastFocusedElement = document.activeElement;
        modal.classList.add("is-open");
        overlay.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        modal.setAttribute("tabindex", "-1");
        modal.focus();
        document.addEventListener("keydown", handleEscape);
    }

    function closeModal() {
        modal.classList.remove("is-open");
        overlay.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.removeEventListener("keydown", handleEscape);
        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }

    if (modal) {
        modal.setAttribute("aria-hidden", "true");
        openModalButtons.forEach(button => {
            button.addEventListener("click", openModal);
        });

        if (closeModalButton) {
            closeModalButton.addEventListener("click", closeModal);
        }

        overlay.addEventListener("click", closeModal);
    }
});
