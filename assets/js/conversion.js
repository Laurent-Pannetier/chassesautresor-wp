document.addEventListener("DOMContentLoaded", () => {
    const openModal = document.getElementById("open-conversion-modal");
    const modal = document.getElementById("conversion-modal");

    if (!modal) {
        return;
    }

    const modalContent = modal.querySelector(".points-modal-content");

    let overlay = document.querySelector(".modal-overlay");
    if (!overlay) {
        overlay = document.createElement("div");
        overlay.classList.add("modal-overlay");
        document.body.appendChild(overlay);
    }

    const attachCloseHandlers = () => {
        modal.querySelectorAll(".close-modal").forEach((btn) => {
            btn.addEventListener("click", () => {
                modal.style.display = "none";
                overlay.style.display = "none";
            });
        });
    };

    const initForm = () => {
        const inputPoints = document.getElementById("points-a-convertir");
        const montantEquivalent = document.getElementById("montant-equivalent");
        const submitBtn = modal.querySelector(".modal-actions button[type='submit']");

        if (inputPoints && montantEquivalent && submitBtn) {
            const tauxConversion = parseFloat(inputPoints.dataset.taux) || 85;
            const min = parseInt(inputPoints.min) || 0;
            const max = parseInt(inputPoints.max) || Infinity;

            const equivalentContainer = montantEquivalent.closest(".conversion-equivalent");
            const feedback = document.createElement("p");
            feedback.className = "points-feedback";
            equivalentContainer.after(feedback);

            let debounceTimer;
            let messageTimer;

            const showMessage = (text) => {
                feedback.textContent = text;
                feedback.style.display = "block";
                clearTimeout(messageTimer);
                messageTimer = setTimeout(() => {
                    feedback.style.display = "none";
                }, 3000);
            };

            const updateEquivalent = () => {
                const points = parseInt(inputPoints.value, 10);
                const montant = isNaN(points)
                    ? "0.00"
                    : ((points / 1000) * tauxConversion).toFixed(2);
                montantEquivalent.textContent = montant;
            };

            const toggleButton = () => {
                submitBtn.disabled = inputPoints.value.trim() === "";
            };

            const validateAndClamp = () => {
                const raw = inputPoints.value.trim();
                if (raw === "") {
                    updateEquivalent();
                    toggleButton();
                    return;
                }
                let points = parseInt(raw, 10);
                if (isNaN(points) || points < min) {
                    points = min;
                    showMessage(`points minimum : ${min} points`);
                } else if (points > max) {
                    points = max;
                    showMessage(`points maximum : ${max} points`);
                }
                inputPoints.value = points;
                updateEquivalent();
                toggleButton();
            };

            inputPoints.addEventListener("input", () => {
                updateEquivalent();
                toggleButton();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(validateAndClamp, 500);
            });

            inputPoints.addEventListener("blur", validateAndClamp);

            updateEquivalent();
            toggleButton();
        }
    };

    const openConversionModal = () => {
        fetch("/wp-admin/admin-ajax.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ action: "conversion_modal_content" })
        })
            .then((res) => res.json())
            .then((res) => {
                if (res.success && res.data?.html) {
                    modalContent.innerHTML = res.data.html;
                    attachCloseHandlers();
                    initForm();
                    modal.style.display = "block";
                    overlay.style.display = "block";
                }
            });
    };

    if (openModal) {
        openModal.addEventListener("click", (e) => {
            e.preventDefault();
            openConversionModal();
        });
    }

    overlay.addEventListener("click", () => {
        modal.style.display = "none";
        overlay.style.display = "none";
    });
});
