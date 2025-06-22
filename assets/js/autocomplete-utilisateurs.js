document.addEventListener("DOMContentLoaded", function () {
    var DEBUG = window.DEBUG || false;
    DEBUG && console.log("✅ gestion-points.js chargé");

    setTimeout(function() {
        const userInput = document.getElementById("utilisateur-points");

        if (!userInput) {
            DEBUG && console.log("❌ Élément introuvable : Vérifie l'ID du champ input.");
            return;
        }

        DEBUG && console.log("✅ Élément trouvé : utilisateur-points");

        // ✅ Vérifier si #suggestions-list existe, sinon le créer dynamiquement
        let suggestionsList = document.getElementById("suggestions-list");
        if (!suggestionsList) {
            suggestionsList = document.createElement("ul");
            suggestionsList.id = "suggestions-list";
            suggestionsList.style.position = "absolute";
            suggestionsList.style.background = "white";
            suggestionsList.style.border = "1px solid #ccc";
            suggestionsList.style.width = userInput.offsetWidth + "px";
            suggestionsList.style.maxHeight = "200px";
            suggestionsList.style.overflowY = "auto";
            suggestionsList.style.display = "none";
            suggestionsList.style.zIndex = "1000";
            userInput.parentNode.insertBefore(suggestionsList, userInput.nextSibling);
            DEBUG && console.log("✅ Élément #suggestions-list ajouté au DOM.");
        }

        userInput.addEventListener("input", function () {
            let searchTerm = userInput.value.trim();
            if (searchTerm.length < 2) {
                DEBUG && console.log("❌ Trop court, pas de requête AJAX");
                suggestionsList.innerHTML = ""; // Effacer la liste si trop court
                suggestionsList.style.display = "none"; // Cacher la liste
                return;
            }

            DEBUG && console.log("🔍 Recherche AJAX envoyée :", searchTerm);

            fetch(ajax_object.ajax_url + "?action=rechercher_utilisateur&term=" + encodeURIComponent(searchTerm))
                .then(response => response.json())
                .then(data => {
                    DEBUG && console.log("✅ Réponse AJAX reçue :", data);

                    suggestionsList.innerHTML = ""; // Réinitialiser la liste
                    suggestionsList.style.display = "block"; // Afficher la liste

                    if (data.success && data.data.length > 0) {
                        data.data.forEach(user => {
                            let listItem = document.createElement("li");
                            listItem.textContent = user.text;
                            listItem.dataset.userId = user.id;
                            listItem.style.padding = "8px";
                            listItem.style.cursor = "pointer";
                            listItem.style.listStyle = "none";

                            listItem.addEventListener("click", function () {
                                userInput.value = user.id; // ✅ Insère l'ID utilisateur directement
                                suggestionsList.innerHTML = ""; // Effacer la liste
                                suggestionsList.style.display = "none"; // Cacher la liste
                            });

                            suggestionsList.appendChild(listItem);
                        });

                        DEBUG && console.log("✅ Suggestions mises à jour.");
                    } else {
                        DEBUG && console.log("❌ Aucune donnée reçue.");
                        suggestionsList.style.display = "none"; // Cacher la liste si vide
                    }
                })
                .catch(error => {
                    console.error("❌ Erreur AJAX :", error);
                    suggestionsList.style.display = "none"; // Cacher la liste en cas d'erreur
                });
        });

        // Cacher la liste si on clique ailleurs
        document.addEventListener("click", function (e) {
            if (e.target !== userInput && e.target !== suggestionsList) {
                suggestionsList.style.display = "none";
            }
        });

    }, 500);
});
