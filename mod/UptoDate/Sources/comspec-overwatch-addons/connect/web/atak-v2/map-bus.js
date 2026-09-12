/* Deux flux : Ã©tat coalescÃ© (positionâ€¦) et Ã©vÃ©nements historisÃ©s (dessin, marqueurâ€¦). */
(function () {
    "use strict";
    var SCHEMA = "athena.event.v1";
    var lastState = {};
    var seq = 0;
    var listeners = [];
    function st() {
        return window.COMSPEC_ATAK_STATE || {};
    }
    function newId() {
        seq += 1;
        return "01J" + Date.now().toString(36).toUpperCase() + seq.toString(36).toUpperCase();
    }
    function envelope(type, payload, flow) {
        var s = st();
        return {
            schema: SCHEMA,
            event_id: newId(),
            type: type,
            timestamp: new Date().toISOString(),
            t: Date.now(),
            flow: flow,
            source: {
                type: "arma3",
                terminal_id: s.terminalId || s.device || "ATAK-LOCAL",
                callsign: s.callsign || "LOCAL"
            },
            context: {
                world: s.world || "altis",
                mission: s.mission || "",
                server: s.server || ""
            },
            payload: payload || {}
        };
    }
    function bucketOf(type) {
        if (type.indexOf("position") === 0 || type === "bft.snapshot" || type === "weather.update")
            return "position";
        if (type.indexOf("marker") === 0)
            return "marker";
        if (type.indexOf("drawing") === 0 || type.indexOf("map.object") === 0)
            return "drawing";
        if (type.indexOf("route") === 0)
            return "route";
        if (type.indexOf("zone") === 0)
            return "drawing";
        if (type.indexOf("intel") === 0 || type.indexOf("photo") === 0 || type.indexOf("sse") === 0)
            return "intel";
        return "other";
    }
    function notify(ev) {
        listeners.forEach(function (fn) { try {
            fn(ev);
        }
        catch (e) { } });
    }
    function sendSqf(ev) {
        if (typeof window.COMSPEC_ATAK_send !== "function")
            return;
        var obj = (ev.payload && ev.payload.object) || {};
        var id = obj.id || ev.event_id;
        var extra = obj.name || obj.type || ev.type;
        /* V2 : notification courte ; pas de scene:json (endpoints morts). */
        window.COMSPEC_ATAK_send("scene:event|" + ev.type + "|" + String(id).replace(/\|/g, " ") + "|" + String(extra).replace(/\|/g, " "));
    }
    function persistEvent(ev) {
        var store = window.COMSPEC_MapStore;
        if (!store)
            return Promise.resolve();
        return store.put("events", ev.event_id, ev).then(function () {
            return store.put("queue", ev.event_id, {
                event_id: ev.event_id,
                type: ev.type,
                bucket: bucketOf(ev.type),
                status: "pending",
                queuedAt: ev.timestamp,
                event: ev
            });
        });
    }
    function emitState(type, payload) {
        var ev = envelope(type, payload, "state");
        lastState[type] = ev;
        var store = window.COMSPEC_MapStore;
        if (store)
            store.put("kv", "state:" + type, ev);
        notify(ev);
        return ev;
    }
    function emitEvent(type, payload) {
        var ev = envelope(type, payload, "event");
        // Persister avant l'envoi : sinon un ACK SQF trÃ¨s rapide peut supprimer la
        // file avant que le put IndexedDB soit terminÃ©, puis le pending rÃ©apparaÃ®t.
        persistEvent(ev).then(function () {
            sendSqf(ev);
        });
        notify(ev);
        return ev;
    }
    function isLive() {
        var s = st();
        return s.connected === true && String(s.mode || "NONE") !== "NONE";
    }
    function ack(eventId) {
        var id = String(eventId || "");
        var store = window.COMSPEC_MapStore;
        if (!id || !store)
            return Promise.resolve(false);
        // L'Ã©vÃ©nement reste dans l'historique `events`; seule la file pending est vidÃ©e.
        return store.del("queue", id).then(function () { return true; });
    }
    function fail(eventId, reason) {
        var id = String(eventId || "");
        var store = window.COMSPEC_MapStore;
        if (!id || !store)
            return Promise.resolve(false);
        return store.get("queue", id).then(function (row) {
            if (!row)
                return false;
            row.status = "failed";
            row.error = String(reason || "ingest_failed");
            row.failedAt = new Date().toISOString();
            return store.put("queue", id, row).then(function () { return true; });
        });
    }
    function queueSummary() {
        var store = window.COMSPEC_MapStore;
        var empty = {
            live: isLive(),
            pending: 0,
            lastAt: null,
            callsign: st().callsign || "LOCAL",
            terminal: st().terminalId || st().device || "Ce terminal",
            buckets: { position: 0, marker: 0, drawing: 0, route: 0, intel: 0, other: 0 },
            tiles: 0
        };
        if (!store)
            return Promise.resolve(empty);
        return Promise.all([store.all("queue"), store.all("tiles")]).then(function (pair) {
            var rows = pair[0] || [];
            var tiles = pair[1] || [];
            var out = {
                live: isLive(),
                pending: 0,
                lastAt: null,
                callsign: empty.callsign,
                terminal: empty.terminal,
                buckets: { position: 0, marker: 0, drawing: 0, route: 0, intel: 0, other: 0 },
                tiles: tiles.length
            };
            var statePos = lastState["position.update"];
            if (statePos)
                out.lastAt = statePos.timestamp;
            rows.forEach(function (row) {
                if (!row || row.status === "sent")
                    return;
                out.pending += 1;
                var b = row.bucket || bucketOf(row.type || "");
                if (!out.buckets[b])
                    out.buckets[b] = 0;
                out.buckets[b] += 1;
                if (!out.lastAt || (row.queuedAt && row.queuedAt > out.lastAt))
                    out.lastAt = row.queuedAt;
            });
            return out;
        });
    }
    window.COMSPEC_MapBus = {
        state: emitState,
        event: emitEvent,
        lastState: function (type) { return lastState[type] || null; },
        subscribe: function (fn) { listeners.push(fn); },
        summary: queueSummary,
        ack: ack,
        fail: fail,
        bucketOf: bucketOf
    };
})();
