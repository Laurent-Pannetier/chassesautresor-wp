(function () {
  "use strict";

  var root = document.querySelector("[data-team-performance]");
  if (!root) {
    return;
  }

  root.addEventListener("click", function (event) {
    var button = event.target.closest("[data-team-ranking-page]");
    if (!button || button.disabled) {
      return;
    }

    var target = root.querySelector("[data-team-ranking]");
    if (!target) {
      return;
    }

    var body = new URLSearchParams({
      action: "cat_team_performance_ranking",
      nonce: root.getAttribute("data-nonce") || "",
      hunt_id: root.getAttribute("data-hunt-id") || "0",
      page: button.getAttribute("data-team-ranking-page") || "1",
    });
    target.setAttribute("aria-busy", "true");
    window
      .fetch(root.getAttribute("data-ajax-url") || "", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error("team_ranking_request_failed");
        }
        target.innerHTML = payload.data.html || "";
      })
      .catch(function () {
        var message = root.getAttribute("data-error-message") || "";
        target.innerHTML = '<p class="equipe-note" role="alert">' + message + "</p>";
      })
      .finally(function () {
        target.removeAttribute("aria-busy");
      });
  });
})();
