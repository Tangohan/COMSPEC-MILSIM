/**
 * Modales dossier SSE (synthèse / identité / pièces).
 */
(function () {
  function openModal(id) {
    var el = document.getElementById(id);
    if (!el || typeof el.showModal !== "function") return;
    el.showModal();
  }

  function closeModal(el) {
    if (!el) return;
    if (typeof el.close === "function") el.close();
  }

  document.addEventListener("click", function (ev) {
    var openBtn = ev.target.closest("[data-sse-modal-open]");
    if (openBtn) {
      ev.preventDefault();
      openModal(openBtn.getAttribute("data-sse-modal-open"));
      return;
    }
    var closeBtn = ev.target.closest("[data-sse-modal-close]");
    if (closeBtn) {
      ev.preventDefault();
      closeModal(closeBtn.closest("dialog.sse-modal"));
      return;
    }
    var tabBtn = ev.target.closest("[data-sse-tab]");
    if (tabBtn) {
      var modal = tabBtn.closest("dialog.sse-modal");
      if (!modal) return;
      var key = tabBtn.getAttribute("data-sse-tab");
      modal.querySelectorAll("[data-sse-tab]").forEach(function (b) {
        b.classList.toggle("is-active", b === tabBtn);
      });
      modal.querySelectorAll("[data-sse-tab-panel]").forEach(function (p) {
        p.hidden = p.getAttribute("data-sse-tab-panel") !== key;
      });
      return;
    }
    var idPreset = ev.target.closest("[data-id-preset]");
    if (idPreset) {
      ev.preventDefault();
      var alias = document.getElementById("id-alias");
      var status = document.getElementById("id-status");
      var circ = document.getElementById("id-circ");
      if (alias) alias.value = idPreset.getAttribute("data-alias") || "";
      if (status) status.value = idPreset.getAttribute("data-status") || "civil";
      if (circ) circ.value = idPreset.getAttribute("data-circumstances") || "";
      return;
    }
    var evPreset = ev.target.closest("[data-ev-preset]");
    if (evPreset) {
      ev.preventDefault();
      var label = document.getElementById("ev-label");
      var caption = document.getElementById("ev-caption");
      if (label) label.value = evPreset.getAttribute("data-label") || "";
      if (caption) caption.value = evPreset.getAttribute("data-caption") || "";
    }
  });

  document.querySelectorAll("dialog.sse-modal").forEach(function (dlg) {
    dlg.addEventListener("click", function (ev) {
      if (ev.target === dlg) closeModal(dlg);
    });
  });
})();
