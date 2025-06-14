document.addEventListener("DOMContentLoaded", function () {
    const openModalButtons = document.querySelectorAll(".open-points-modal");
    const closeModal = document.querySelector(".close-modal");
    const modal = document.getElementById("points-modal");
    const overlay = document.createElement("div");
    overlay.classList.add("modal-overlay");

    // Ajouter l'overlay au body (caché par défaut)
    document.body.appendChild(overlay);

    if (modal) {
        openModalButtons.forEach(button => {
            button.addEventListener("click", function () {
                modal.style.display = "block";
                overlay.style.display = "block";
            });
        });

        if (closeModal) {
            closeModal.addEventListener("click", function () {
                modal.style.display = "none";
                overlay.style.display = "none";
            });
        }

        overlay.addEventListener("click", function () {
            modal.style.display = "none";
            overlay.style.display = "none";
        });
    }
});
