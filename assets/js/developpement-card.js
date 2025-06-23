document.addEventListener("DOMContentLoaded", function() {
    const btn = document.getElementById("afficher-champs-acf");
    const container = document.getElementById("acf-fields-container");
    const output = document.getElementById("acf-fields-output");

    if (!btn || !container || !output) return;

    btn.addEventListener("click", function(e) {
        e.preventDefault();
        fetch(ajax_object.ajax_url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: "action=recuperer_details_acf"
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                output.value = data.data;
                container.style.display = "block";
            } else {
                alert("Erreur : " + data.data);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Erreur AJAX");
        });
    });
});
