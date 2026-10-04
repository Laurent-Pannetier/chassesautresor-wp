/**
 * 📌 Gestion de l'upload d'avatar via AJAX sur /mon-compte/
 * - Vérifie la taille et le format du fichier
 * - Envoie l'image en AJAX
 * - Met à jour dynamiquement l'avatar après l'upload
*/
var DEBUG = window.DEBUG || false;

document.addEventListener("DOMContentLoaded", function () {
    // 🔹 Vérification de l'URL avant d'exécuter le script
    if (!window.location.pathname.startsWith("/mon-compte")) {
        return; // 🛑 Stoppe l'exécution si l’URL ne commence pas par "/mon-compte"
    }

    // 🔹 Sélection des éléments HTML
    const fileInput = document.getElementById("upload-avatar");
    const sizeMessage = document.querySelector(".message-size-file-avatar");
    const formatMessage = document.querySelector(".message-format-file-avatar");
    const messageContainer = document.getElementById("avatar-upload-message");
    const avatarImg = document.querySelector(".profile-avatar img");

    // 🛑 Vérification des éléments nécessaires
    if (!fileInput || !avatarImg) {
        return;
    }

    fileInput.addEventListener("change", function () {
        if (fileInput.files.length > 0) {
            let file = fileInput.files[0];
            let maxSize = 2 * 1024 * 1024; // 🔹 2 Mo en octets
            let allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];

            // 🛑 Vérification de la taille
            if (sizeMessage) {
                if (file.size > maxSize) {
                    sizeMessage.classList.add("message-avatar-visible");
                    return;
                } else {
                    sizeMessage.classList.remove("message-avatar-visible");
                }
            }

            // 🛑 Vérification du format
            if (formatMessage) {
                if (!allowedTypes.includes(file.type)) {
                    formatMessage.classList.add("message-avatar-visible");
                    return;
                } else {
                    formatMessage.classList.remove("message-avatar-visible");
                }
            }

            let formData = new FormData();
            formData.append("action", "upload_user_avatar");
            formData.append("nonce", (window.avatarUpload && avatarUpload.nonce) || "");
            formData.append("avatar", file);

            DEBUG && console.log("📤 Envoi de la requête AJAX...");
            
            fetch((window.avatarUpload && avatarUpload.ajaxUrl) || ajaxurl, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
            .then(response => response.json())
            .then(data => {
                DEBUG && console.log("🔍 Réponse complète du serveur :", data);

                if (data.success && data.data.new_avatar_url) {
                    DEBUG && console.log("✅ Nouvelle URL de l'avatar :", data.data.new_avatar_url);

                    // ✅ Vérifier que l'image est bien trouvée avant de modifier `src`
                    avatarImg.src = data.data.new_avatar_url;

                    // ✅ Affichage du message si messageContainer existe
                    if (messageContainer) {
                        messageContainer.innerHTML = "<p style='color: green;'>✅ Image mise à jour !</p>";
                    }

                    // ✅ Recharge la page après 1 seconde pour finaliser l'affichage
                    setTimeout(() => location.reload(), 1000);
                } else {
                    if (messageContainer) {
                        messageContainer.innerHTML = "<p style='color: red;'>❌ " + data.message + "</p>";
                    }
                    console.error("❌ Erreur dans la réponse serveur :", data);
                }
            })
            .catch(error => {
                if (messageContainer) {
                    messageContainer.innerHTML = "<p style='color: red;'>❌ Erreur AJAX.</p>";
                }
                console.error("❌ Erreur AJAX :", error);
            });
        }
    });
});
