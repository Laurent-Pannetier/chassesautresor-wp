/**
 * Upload and removal of the account avatar on /mon-compte/.
 * Checks size and format, then updates the profile and top-bar images.
 */
var DEBUG = window.DEBUG || false;

document.addEventListener("DOMContentLoaded", function () {
    const fileInput = document.getElementById("upload-avatar");
    const onAccount = window.location.pathname.indexOf("/mon-compte") !== -1;
    if (!onAccount && !fileInput) {
        return;
    }

    const trigger = document.getElementById("upload-avatar-trigger");
    const removeButton = document.getElementById("remove-avatar");
    const sizeMessage = document.querySelector(".message-size-file-avatar");
    const formatMessage = document.querySelector(".message-format-file-avatar");
    const messageContainer = document.getElementById("avatar-upload-message");
    const avatarImg = document.querySelector(".profile-avatar img");
    const avatarRoot = document.querySelector(".account-avatar");
    const config = window.avatarUpload || {};
    let pending = false;

    if (!fileInput || !avatarImg) {
        return;
    }

    if (trigger) {
        trigger.addEventListener("click", function () {
            if (!pending) {
                fileInput.click();
            }
        });
    }

    fileInput.addEventListener("change", function () {
        if (pending || fileInput.files.length === 0) {
            return;
        }

        const file = fileInput.files[0];
        const maxSize = 2 * 1024 * 1024;
        const allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];

        hideValidation();
        if (file.size > maxSize) {
            showValidation(sizeMessage);
            fileInput.value = "";
            return;
        }
        if (!allowedTypes.includes(file.type)) {
            showValidation(formatMessage);
            fileInput.value = "";
            return;
        }

        const formData = new FormData();
        formData.append("action", "upload_user_avatar");
        formData.append("nonce", (window.avatarUpload && avatarUpload.nonce) || "");
        formData.append("avatar", file);

        DEBUG && console.log("📤 Envoi de la requête AJAX...");
        sendAvatarRequest(formData).finally(function () {
            fileInput.value = "";
        });
    });

    if (removeButton) {
        removeButton.addEventListener("click", function () {
            if (pending) {
                return;
            }
            const confirmText = config.messages && config.messages.confirmRemove;
            if (confirmText && !window.confirm(confirmText)) {
                return;
            }

            const formData = new FormData();
            formData.append("action", "remove_user_avatar");
            formData.append("nonce", config.removeNonce || "");
            sendAvatarRequest(formData);
        });
    }

    function sendAvatarRequest(formData) {
        setBusy(true);
        hideValidation();
        showStatus("", false);

        return fetch(config.ajaxUrl || window.ajaxurl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                DEBUG && console.log("🔍 Réponse complète du serveur :", data);
                if (data.success && data.data && data.data.new_avatar_url) {
                    applyAvatar(data.data.new_avatar_url, !!data.data.is_default);
                    showStatus(data.data.message || "", false);
                    return;
                }
                showStatus(errorText(data), true);
            })
            .catch(function (error) {
                showStatus((config.messages && config.messages.network) || "", true);
                console.error("❌ Erreur AJAX :", error);
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function applyAvatar(url, isDefault) {
        document.querySelectorAll(".profile-avatar img, .app-topbar__profile img").forEach(function (img) {
            img.src = url;
            img.classList.toggle("avatar-pirate", isDefault);
        });
        if (removeButton) {
            removeButton.hidden = isDefault;
        }
    }

    function showValidation(element) {
        if (!element) {
            return;
        }
        element.classList.add("message-avatar-visible");
        showStatus(element.textContent || "", true);
    }

    function hideValidation() {
        if (sizeMessage) {
            sizeMessage.classList.remove("message-avatar-visible");
        }
        if (formatMessage) {
            formatMessage.classList.remove("message-avatar-visible");
        }
    }

    function showStatus(text, isError) {
        if (!messageContainer) {
            return;
        }
        messageContainer.textContent = (text || "").trim();
        messageContainer.classList.toggle("is-error", isError && messageContainer.textContent !== "");
        messageContainer.classList.toggle("is-success", !isError && messageContainer.textContent !== "");
    }

    function errorText(data) {
        if (data && data.data && typeof data.data.message === "string") {
            return data.data.message;
        }
        if (data && typeof data.message === "string") {
            return data.message;
        }
        return (config.messages && config.messages.network) || "";
    }

    function setBusy(isBusy) {
        pending = isBusy;
        fileInput.disabled = isBusy;
        if (trigger) {
            trigger.disabled = isBusy;
        }
        if (removeButton) {
            removeButton.disabled = isBusy;
        }
        if (avatarRoot) {
            avatarRoot.classList.toggle("is-busy", isBusy);
            avatarRoot.setAttribute("aria-busy", isBusy ? "true" : "false");
        }
    }
});
