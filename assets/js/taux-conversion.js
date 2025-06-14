document.addEventListener("DOMContentLoaded", function () {

    /** 🔹 Gestion de l'affichage du formulaire d'édition */
    let editerBtn = document.getElementById("editer-taux");
    let formEdition = document.getElementById("form-edition-taux");
    let enregistrerBtn = document.getElementById("enregistrer-taux");

    console.log("🔎 Bouton 'Mettre à jour' détecté :", enregistrerBtn);

    if (editerBtn && formEdition && enregistrerBtn) {
        editerBtn.addEventListener("click", function () {
            formEdition.style.display = "block";
        });

        enregistrerBtn.addEventListener("click", function () {
            let nouveauTaux = document.getElementById("nouveau-taux").value;

            if (!nouveauTaux || isNaN(nouveauTaux) || nouveauTaux <= 0) {
                alert("❌ Veuillez entrer un taux valide.");
                return;
            }

            if (!confirm("Confirmez-vous la mise à jour du taux à " + nouveauTaux + " € pour 1000 points ?")) {
                return;
            }

            console.log("📤 Envoi du formulaire...");
            formEdition.submit(); // 🚀 Soumission classique du formulaire
        });
    }

    /** 🔹 Gestion du modal */
    function activerModal() {
        const openModal = document.getElementById("open-taux-modal");
        const modal = document.getElementById("conversion-modal");
        const closeModal = modal ? modal.querySelector(".close-modal") : null;
        const overlay = document.querySelector(".modal-overlay");

        if (!modal) {
            console.error("❌ ERREUR : Le modal #conversion-modal est introuvable !");
            return;
        }

        if (openModal) {
            openModal.addEventListener("click", function () {
                modal.style.display = "block";
                overlay.style.display = "block";
            });
        }

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

    activerModal(); // 🔹 Réactiver la gestion du modal
});
document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 Script taux-conversion.js chargé");

    /** 🔹 Gestion de l'affichage du formulaire d'édition */
    let modifierBtn = document.getElementById("modifier-taux");
    let overlay = document.querySelector(".overlay-taux");
    let formTaux = document.getElementById("form-taux-conversion");

    if (modifierBtn && overlay && formTaux) {
        modifierBtn.addEventListener("click", function () {
            console.log("✅ Affichage du formulaire de taux.");
            overlay.style.display = "none"; // Retirer l’overlay
            formTaux.style.display = "block"; // Afficher le formulaire
        });
    }
});
