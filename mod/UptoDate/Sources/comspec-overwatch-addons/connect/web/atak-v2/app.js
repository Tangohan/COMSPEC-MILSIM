/* COMSPEC ATAK Interface V2 — pont tablette Overwatch + moteur carte prototype. */
(function () {
  "use strict";
  var boot = window.COMSPEC_BOOT || null;
  var currentPanel = "map";
  var lastUnits = [];
  var worldHint = "altis";

  function send(cmd) {
    var msg = "COMSPEC|" + String(cmd || "");
    try { alert(msg); return; } catch (e1) {}
    try {
      if (window.A3API && typeof A3API.SendAlert === "function") A3API.SendAlert(msg);
    } catch (e2) {}
  }

  window.COMSPEC_ATAK_send = function (cmd) {
    cmd = String(cmd || "");
    if (!cmd) return;
    if (
      cmd.indexOf("marker:place|") === 0 || cmd.indexOf("chat:") === 0 ||
      cmd.indexOf("action:") === 0 || cmd.indexOf("order:") === 0 ||
      cmd.indexOf("map:") === 0 || cmd.indexOf("open:") === 0 ||
      cmd.indexOf("ui:") === 0 || cmd.indexOf("refresh") === 0 ||
      cmd.indexOf("close") === 0 || cmd.indexOf("toggle:") === 0 ||
      cmd.indexOf("callsign:") === 0 || cmd.indexOf("tactical:") === 0
    ) { send(cmd); }
  };
  window.COMSPEC_ATAK_toast = toast;

  function toast(text, kind) {
    var host = document.getElementById("toast-host");
    if (!host) return;
    var el = document.createElement("div");
    el.className = "toast" + (kind === "OK" || kind === "ok" ? " ok" : "");
    el.textContent = String(text || "");
    host.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 3200);
  }
  function escapeHtml(s) {
    return String(s == null ? "" : s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
  }
  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }
  function statusClass(status) {
    if (status === "linked") return "ok";
    if (status === "connecting") return "wait";
    return "";
  }
  function statusLabelFr(bootData) {
    if (bootData && bootData.statusLabel) return String(bootData.statusLabel);
    var s = (bootData && bootData.status) || "offline";
    if (s === "linked") return "Lié à Athena";
    if (s === "connecting") return "Connexion…";
    if (s === "disabled") return "Overwatch désactivé";
    return "Hors liaison";
  }
  function clockTick() {
    var d = new Date();
    setText("osd-clock", ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2));
  }
  function inferWorld() {
    var st = window.COMSPEC_ATAK_STATE || {};
    if (boot && boot.world) return String(boot.world).toLowerCase();
    if (st.world) return String(st.world).toLowerCase();
    return worldHint || "altis";
  }
  function unitsToTelemetry(units, bootData) {
    var contacts = [];
    var selfX = 0, selfY = 0;
    var heading = Number((bootData && bootData.heading) || 0) || 0;
    var i;
    for (i = 0; i < units.length; i += 1) {
      var u = units[i] || {};
      var x = Number(u.wx != null ? u.wx : u.x);
      var y = Number(u.wy != null ? u.wy : u.y);
      if (!Number.isFinite(x) || !Number.isFinite(y)) continue;
      if (u.self || u.isSelf) { selfX = x; selfY = y; continue; }
      contacts.push({ id: String(u.callsign || u.cs || ("c" + i)), cs: String(u.callsign || u.cs || "—"), x: x, y: y, h: Number(u.hdg || u.heading || 0) || 0, k: "g" });
    }
    if (!selfX && !selfY && units.length) {
      var first = units[0] || {};
      selfX = Number(first.wx != null ? first.wx : first.x) || 0;
      selfY = Number(first.wy != null ? first.wy : first.y) || 0;
    }
    var w = inferWorld();
    var ws = (bootData && bootData.worldSize) ? Number(bootData.worldSize) : ((window.COMSPEC_MapTheatres && window.COMSPEC_MapTheatres.resolve) ? Number(window.COMSPEC_MapTheatres.resolve(w).worldSize || 30720) : 30720);
    return { coordX: selfX, coordY: selfY, heading: heading, world: w, worldSize: ws, contacts: contacts };
  }
  function ensureMap(tel) {
    if (typeof window.COMSPEC_ATAK_liveMapShow !== "function") return;
    window.COMSPEC_ATAK_liveMapShow(tel.world || "altis", tel.worldSize || 30720);
    if (typeof window.COMSPEC_ATAK_liveMapUpdate === "function") window.COMSPEC_ATAK_liveMapUpdate(tel);
  }
  function renderContacts(units) {
    var host = document.getElementById("contacts-list");
    if (!host) return;
    if (!units || !units.length) { host.innerHTML = '<div class="empty">Aucun contact pour le moment.</div>'; return; }
    var html = "";
    var i;
    for (i = 0; i < units.length; i += 1) {
      var u = units[i] || {};
      var cs = String(u.callsign || u.cs || "—");
      var role = String(u.role || "");
      var self = !!(u.self || u.isSelf);
      var x = Number(u.wx != null ? u.wx : u.x);
      var y = Number(u.wy != null ? u.wy : u.y);
      var grid = (u.gx != null && u.gy != null) ? (String(u.gx) + " " + String(u.gy)) : "";
      html += '<button type="button" class="list-row" data-goto-x="' + (Number.isFinite(x) ? x : "") + '" data-goto-y="' + (Number.isFinite(y) ? y : "") + '" style="width:100%;text-align:left;background:transparent;border:0;border-bottom:1px solid var(--line);color:inherit">' +
        '<span class="dot' + (self ? " self" : "") + '"></span><span><span class="cs">' + escapeHtml(cs) + "</span>" +
        (role ? '<div class="meta">' + escapeHtml(role) + "</div>" : "") + "</span>" +
        '<span class="meta">' + escapeHtml(grid || (self ? "vous" : "")) + "</span></button>";
    }
    host.innerHTML = html;
  }
  function renderChat(lines) {
    var host = document.getElementById("chat-log");
    if (!host) return;
    lines = Array.isArray(lines) ? lines : [];
    if (!lines.length) { host.innerHTML = '<div class="empty">Aucun message.</div>'; return; }
    var html = "", i;
    for (i = 0; i < lines.length; i += 1) {
      var L = lines[i] || {};
      html += '<div class="chat-line"><span class="when">' + escapeHtml(L.time || L.at || "") + '</span><span class="who">' +
        escapeHtml(L.from || L.callsign || "Canal") + "</span>" + escapeHtml(L.text || L.body || L.msg || "") + "</div>";
    }
    host.innerHTML = html;
    host.scrollTop = host.scrollHeight;
  }
  function renderOrders(rows) {
    var host = document.getElementById("orders-list");
    if (!host) return;
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) { host.innerHTML = '<div class="empty">Aucun ordre en attente.</div>'; return; }
    var html = "", i;
    for (i = 0; i < rows.length; i += 1) {
      var o = rows[i] || {};
      var id = String(o.id || o.orderId || "");
      html += '<div class="order-card"><div class="title">' + escapeHtml(o.title || o.type || "Ordre") + '</div><div class="body">' +
        escapeHtml(o.body || o.text || o.summary || "") + '</div><div class="actions">' +
        '<button type="button" class="btn" data-cmd="order:status|' + escapeHtml(id) + '|ACK">Accusé</button>' +
        '<button type="button" class="btn" data-cmd="order:status|' + escapeHtml(id) + '|EXEC">En cours</button>' +
        '<button type="button" class="btn danger" data-cmd="order:status|' + escapeHtml(id) + '|FAILED">Échec</button></div></div>';
    }
    host.innerHTML = html;
  }
  function applyBoot(data) {
    boot = data || boot || {};
    window.COMSPEC_ATAK_STATE = window.COMSPEC_ATAK_STATE || {};
    window.COMSPEC_ATAK_STATE.callsign = boot.callsign || "";
    window.COMSPEC_ATAK_STATE.connected = boot.status === "linked";
    window.COMSPEC_ATAK_STATE.mode = boot.status === "linked" ? "ATHENA" : "LOCAL";
    if (boot.world) window.COMSPEC_ATAK_STATE.world = String(boot.world).toLowerCase();
    window.COMSPEC_ATAK_STATE.world = window.COMSPEC_ATAK_STATE.world || worldHint;
    if (boot.worldSize) window.COMSPEC_ATAK_STATE.worldSize = Number(boot.worldSize) || 30720;
    var dot = document.getElementById("status-dot");
    if (dot) dot.className = "status-dot " + statusClass(boot.status);
    setText("status-label", statusLabelFr(boot));
    setText("osd-callsign", boot.callsign || "—");
    setText("osd-grid", boot.grid ? "grille " + boot.grid : "grille —");
    var csIn = document.getElementById("callsign-input");
    if (csIn && boot.callsign) csIn.value = boot.callsign;
    lastUnits = Array.isArray(boot.units) ? boot.units : [];
    setText("chip-count", String(lastUnits.length));
    renderContacts(lastUnits);
    renderChat(boot.chat || []);
    renderOrders(boot.orders || []);
    var tel = unitsToTelemetry(lastUnits, boot);
    worldHint = tel.world;
    setText("chip-world", String(tel.world || "—").toUpperCase());
    setText("map-status", (boot.callsign || "Opérateur") + " · " + statusLabelFr(boot) + " · " + lastUnits.length + " contact(s)");
    ensureMap(tel);
    var foot = document.getElementById("more-footer");
    if (foot) foot.textContent = "Overwatch · Interface V2 · " + (boot.footer || statusLabelFr(boot));
  }
  function setPanel(name) {
    currentPanel = name || "map";
    var panels = ["contacts", "chat", "orders", "more"], i;
    for (i = 0; i < panels.length; i += 1) {
      var el = document.getElementById("panel-" + panels[i]);
      if (el) el.classList.toggle("open", panels[i] === currentPanel);
    }
    var dockBtns = document.querySelectorAll(".dock [data-panel]");
    for (i = 0; i < dockBtns.length; i += 1) {
      dockBtns[i].classList.toggle("active", dockBtns[i].getAttribute("data-panel") === currentPanel);
    }
    if (currentPanel === "map") {
      setTimeout(function () {
        try { var m = window.COMSPEC_ATAK_getMap && window.COMSPEC_ATAK_getMap(); if (m) m.invalidateSize(false); } catch (e) {}
      }, 40);
    }
  }
  function wireUi() {
    document.addEventListener("click", function (ev) {
      var t = ev.target; if (!t || !t.closest) return;
      var cmdEl = t.closest("[data-cmd]");
      if (cmdEl) { var cmd = cmdEl.getAttribute("data-cmd") || ""; if (cmd) send(cmd); return; }
      var panelEl = t.closest("[data-panel]");
      if (panelEl) { setPanel(panelEl.getAttribute("data-panel") || "map"); return; }
      var toolEl = t.closest("[data-tool]");
      if (toolEl && window.COMSPEC_MapEngine) {
        var tool = toolEl.getAttribute("data-tool") || "none";
        window.COMSPEC_MapEngine.setTool(tool);
        var tools = document.querySelectorAll("[data-tool]"), j;
        for (j = 0; j < tools.length; j += 1) tools[j].classList.toggle("active", tools[j].getAttribute("data-tool") === tool);
        toast(tool === "none" ? "Outil main" : "Outil : " + tool, "OK");
        return;
      }
      var gotoEl = t.closest("[data-goto-x]");
      if (gotoEl && window.COMSPEC_ATAK_liveMapGoto) {
        var gx = Number(gotoEl.getAttribute("data-goto-x"));
        var gy = Number(gotoEl.getAttribute("data-goto-y"));
        if (Number.isFinite(gx) && Number.isFinite(gy)) { window.COMSPEC_ATAK_liveMapGoto(gx, gy, 4); setPanel("map"); }
      }
    });
    var btnCenter = document.getElementById("btn-center");
    if (btnCenter) btnCenter.addEventListener("click", function () { if (window.COMSPEC_ATAK_liveMapCenter) window.COMSPEC_ATAK_liveMapCenter(); });
    var zi = document.getElementById("btn-zoom-in");
    if (zi) zi.addEventListener("click", function () { if (window.COMSPEC_ATAK_liveMapZoom) window.COMSPEC_ATAK_liveMapZoom(1); });
    var zo = document.getElementById("btn-zoom-out");
    if (zo) zo.addEventListener("click", function () { if (window.COMSPEC_ATAK_liveMapZoom) window.COMSPEC_ATAK_liveMapZoom(-1); });
    var nativeBtn = document.getElementById("btn-native-map");
    if (nativeBtn) nativeBtn.addEventListener("click", function () { send("map:show|1"); toast("Carte terrain Arma demandée", "OK"); });
    var chatSend = document.getElementById("chat-send");
    var chatInput = document.getElementById("chat-input");
    if (chatSend && chatInput) {
      chatSend.addEventListener("click", function () {
        var txt = String(chatInput.value || "").trim();
        if (!txt) return;
        send("chat:send|general|" + txt.replace(/\|/g, "/"));
        chatInput.value = "";
        toast("Message envoyé", "OK");
      });
      chatInput.addEventListener("keydown", function (ev) { if (ev.key === "Enter") chatSend.click(); });
    }
    var csSave = document.getElementById("callsign-save");
    if (csSave) csSave.addEventListener("click", function () {
      var cs = String((document.getElementById("callsign-input") || {}).value || "").trim();
      if (!cs) return;
      send("callsign:set|" + cs.replace(/\|/g, "-") + "|");
      toast("Indicatif enregistré", "OK");
    });
    document.addEventListener("comspec:tile-source", function (ev) {
      setText("chip-tiles", (ev && ev.detail && ev.detail.source) || "—");
    });
    if (window.COMSPEC_MapBus) {
      window.COMSPEC_MapBus.subscribe(function (ev) {
        if (!ev || ev.type !== "marker.created") return;
        var obj = (ev.payload && ev.payload.object) || {};
        var pts = obj.points || obj.latlngs || obj.coords;
        var x = null, y = null;
        if (Array.isArray(pts) && pts.length) {
          var p0 = pts[0];
          if (Array.isArray(p0)) { x = Number(p0[0]); y = Number(p0[1]); }
          else if (p0 && typeof p0 === "object") { x = Number(p0.x != null ? p0.x : p0.lng); y = Number(p0.y != null ? p0.y : p0.lat); }
        }
        if (obj.x != null) x = Number(obj.x);
        if (obj.y != null) y = Number(obj.y);
        if (!Number.isFinite(x) || !Number.isFinite(y)) return;
        send("marker:place|" + x.toFixed(1) + "|" + y.toFixed(1) + "|mil_dot|ColorRed");
      });
    }
  }
  window.COMSPEC_onBoot = function (data) { applyBoot(data); };
  window.COMSPEC_setView = function (view) {
    var map = { bft: "map", tactical: "map", chat: "chat", orders: "orders", alerts: "orders", apps: "more", status: "more", callsign: "more" };
    setPanel(map[view] || (view === "map" ? "map" : view) || "map");
  };
  window.COMSPEC_setChat = function (lines) { renderChat(lines); };
  window.COMSPEC_setFooterMsg = function (txt) { setText("map-status", txt || ""); };

  function previewBoot() {
    if (!/(?:\?|&)preview(?:=|$)/.test(String(location.search || ""))) return false;
    window.COMSPEC_ATAK_STATE = { world: "altis", callsign: "PREVIEW-1", connected: true, mode: "PREVIEW" };
    applyBoot({
      callsign: "PREVIEW-1", role: "Opérateur", status: "linked", statusLabel: "Aperçu navigateur", grid: "123045", heading: 42, world: "altis", worldSize: 30720,
      units: [
        { callsign: "PREVIEW-1", wx: 14600, wy: 16800, self: true, role: "Vous", gx: 123, gy: 45 },
        { callsign: "ALPHA-2", wx: 14820, wy: 16940, role: "Binôme", gx: 124, gy: 46 },
        { callsign: "TOC", wx: 15100, wy: 16500, role: "Poste", gx: 126, gy: 44 }
      ],
      chat: [{ from: "Poste", text: "Aperçu Interface V2 — carte et liaison simulées.", time: "12:00" }],
      orders: [{ id: "demo1", title: "Reconnaissance nord", body: "Observer la crête et rendre compte." }]
    });
    return true;
  }

  wireUi();
  clockTick();
  setInterval(clockTick, 15000);
  setPanel("map");
  var isPreview = previewBoot();
  if (!isPreview && boot) applyBoot(boot);
  else if (!isPreview) {
    setText("map-status", "Interface V2 prête · en attente du boot Overwatch");
    setTimeout(function () { if (typeof window.COMSPEC_ATAK_liveMapShow === "function") window.COMSPEC_ATAK_liveMapShow("altis", 30720); }, 80);
  }
})();