document.addEventListener("DOMContentLoaded", function () {
    const openModal = document.getElementById("open-conversion-modal");
    const modal = document.getElementById("conversion-modal");
    const closeModal = document.querySelector(".close-modal");

    if (!modal) {
        console.error("❌ ERREUR : Le modal #conversion-modal est introuvable !");
        return;
    }

    // Création et ajout de l'overlay pour assombrir l'arrière-plan
    const overlay = document.createElement("div");
    overlay.classList.add("modal-overlay");
    document.body.appendChild(overlay);

    // Ouverture du modal
    if (openModal) {
        openModal.addEventListener("click", function () {
            modal.style.display = "block";
            overlay.style.display = "block";
        });
    }

    // Fermeture du modal via la croix
    if (closeModal) {
        closeModal.addEventListener("click", function () {
            modal.style.display = "none";
            overlay.style.display = "none";
        });
    }

    // Fermeture du modal en cliquant en dehors
    overlay.addEventListener("click", function () {
        modal.style.display = "none";
        overlay.style.display = "none";
    });
});
document.addEventListener("DOMContentLoaded", function () {
    const inputPoints = document.getElementById("points-a-convertir");
    const montantEquivalent = document.getElementById("montant-equivalent");

    if (inputPoints && montantEquivalent) {
        inputPoints.addEventListener("input", function () {
            const tauxConversion = parseFloat(inputPoints.dataset.taux) || 85; // Valeur par défaut si non défini
            const points = parseInt(inputPoints.value) || 0;
            montantEquivalent.textContent = (points / 1000 * tauxConversion).toFixed(2);
        });
    }
});
