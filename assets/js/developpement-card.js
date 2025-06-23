document.addEventListener("DOMContentLoaded", function() {
    const btn = document.getElementById("afficher-champs-acf");
    if (!btn) return;

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
                alert(data.data);
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
