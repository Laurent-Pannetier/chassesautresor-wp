document.addEventListener("DOMContentLoaded", () => {
    const DEBUG = window.DEBUG || false;
    DEBUG && console.log("✅ autocomplete-utilisateurs.js chargé");

    const init = () => {
        const userInput = document.getElementById("utilisateur-points");
        const hiddenInput = document.getElementById("utilisateur-id");
        if (!userInput || !hiddenInput) {
            DEBUG && console.log("❌ Élément introuvable : Vérifie les champs input.");
            return;
        }
        if (userInput.dataset.autocompleteInit) {
            return;
        }
        userInput.dataset.autocompleteInit = "1";
        DEBUG && console.log("✅ Élément trouvé : utilisateur-points");

        let suggestionsList = document.getElementById("suggestions-list");
        if (!suggestionsList) {
            suggestionsList = document.createElement("ul");
            suggestionsList.id = "suggestions-list";
            const parent = userInput.parentNode;
            if (parent && parent.style.position === "") {
                parent.style.position = "relative";
            }
            suggestionsList.style.position = "absolute";
            suggestionsList.style.left = "0";
            suggestionsList.style.top = userInput.offsetHeight + "px";
            suggestionsList.style.background = "white";
            suggestionsList.style.border = "1px solid #ccc";
            suggestionsList.style.width = userInput.offsetWidth + "px";
            suggestionsList.style.maxHeight = "200px";
            suggestionsList.style.overflowY = "auto";
            suggestionsList.style.display = "none";
            suggestionsList.style.zIndex = "1000";
            parent.insertBefore(suggestionsList, userInput.nextSibling);
            DEBUG && console.log("✅ Élément #suggestions-list ajouté au DOM.");
        }

        userInput.addEventListener("input", () => {
            hiddenInput.value = "";
            const searchTerm = userInput.value.trim();
            if (searchTerm.length < 1) {
                DEBUG && console.log("❌ Trop court, pas de requête AJAX");
                suggestionsList.innerHTML = "";
                suggestionsList.style.display = "none";
                return;
            }

            DEBUG && console.log("🔍 Recherche AJAX envoyée :", searchTerm);

            fetch(
                ajax_object.ajax_url +
                    "?action=rechercher_utilisateur&term=" +
                    encodeURIComponent(searchTerm),
                { credentials: "same-origin" }
            )
                .then((response) => response.json())
                .then((data) => {
                    DEBUG && console.log("✅ Réponse AJAX reçue :", data);

                    suggestionsList.innerHTML = "";
                    suggestionsList.style.display = "block";

                    if (data.success && data.data.length > 0) {
                        data.data.forEach((user) => {
                            const listItem = document.createElement("li");
                            listItem.textContent = user.text;
                            listItem.dataset.userId = user.id;
                            listItem.style.padding = "8px";
                            listItem.style.cursor = "pointer";
                            listItem.style.listStyle = "none";

                            listItem.addEventListener("click", () => {
                                userInput.value = user.text;
                                hiddenInput.value = user.id;
                                suggestionsList.innerHTML = "";
                                suggestionsList.style.display = "none";
                            });

                            suggestionsList.appendChild(listItem);
                        });

                        DEBUG && console.log("✅ Suggestions mises à jour.");
                    } else {
                        DEBUG && console.log("❌ Aucune donnée reçue.");
                        suggestionsList.style.display = "none";
                    }
                })
                .catch((error) => {
                    console.error("❌ Erreur AJAX :", error);
                    suggestionsList.style.display = "none";
                });
        });
    };

    document.addEventListener("click", (e) => {
        const suggestionsList = document.getElementById("suggestions-list");
        const userInput = document.getElementById("utilisateur-points");
        if (
            suggestionsList &&
            userInput &&
            e.target !== userInput &&
            !suggestionsList.contains(e.target)
        ) {
            suggestionsList.style.display = "none";
        }
    });

    init();

    document.addEventListener("myaccountSectionLoaded", (e) => {
        if (e.detail && e.detail.section === "points") {
            init();
        }
    });
});

