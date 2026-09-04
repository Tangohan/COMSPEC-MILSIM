/* Magasin local du téléphone : IndexedDB, repli mémoire / localStorage. */
(function () {
  "use strict";

  var DB_NAME = "comspec_atak_v1";
  var DB_VER = 1;
  var LS_PREFIX = "comspec_atak_v1_";
  var db = null;
  var mem = { kv: {}, tiles: {}, events: {}, queue: {} };
  var ready = null;

  function openDb() {
    if (ready) return ready;
    ready = new Promise(function (resolve) {
      var settled = false;
      function finish(value) {
        if (settled) return;
        settled = true;
        resolve(value);
      }
      setTimeout(function () { finish(db); }, 400);
      if (!window.indexedDB) {
        finish(null);
        return;
      }
      try {
        var req = indexedDB.open(DB_NAME, DB_VER);
        req.onerror = function () { finish(null); };
        req.onblocked = function () { finish(null); };
        req.onupgradeneeded = function (ev) {
          var d = ev.target.result;
          if (!d.objectStoreNames.contains("kv")) d.createObjectStore("kv");
          if (!d.objectStoreNames.contains("tiles")) d.createObjectStore("tiles");
          if (!d.objectStoreNames.contains("events")) d.createObjectStore("events", { keyPath: "event_id" });
          if (!d.objectStoreNames.contains("queue")) d.createObjectStore("queue", { keyPath: "event_id" });
        };
        req.onsuccess = function (ev) {
          db = ev.target.result;
          finish(db);
        };
      } catch (e) {
        finish(null);
      }
    });
    return ready;
  }

  function lsKey(store, key) {
    return LS_PREFIX + store + ":" + String(key || "");
  }

  function lsRead(store, key) {
    try {
      var raw = localStorage.getItem(lsKey(store, key));
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function lsWrite(store, key, value) {
    if (store === "tiles") return;
    try {
      localStorage.setItem(lsKey(store, key), JSON.stringify(value));
    } catch (e) {}
  }

  function lsRemove(store, key) {
    try {
      localStorage.removeItem(lsKey(store, key));
    } catch (e) {}
  }

  function lsAll(store) {
    var out = [];
    try {
      var prefix = LS_PREFIX + store + ":";
      var i;
      for (i = 0; i < localStorage.length; i += 1) {
        var k = localStorage.key(i);
        if (!k || k.indexOf(prefix) !== 0) continue;
        var row = lsRead(store, k.slice(prefix.length));
        if (row) out.push(row);
      }
    } catch (e) {}
    return out;
  }

  function tx(store, mode, fn) {
    return openDb().then(function (d) {
      if (!d) return fn(null);
      return new Promise(function (resolve, reject) {
        try {
          var t = d.transaction(store, mode);
          var s = t.objectStore(store);
          var r = fn(s);
          if (r && typeof r.onsuccess !== "undefined") {
            r.onsuccess = function () { resolve(r.result); };
            r.onerror = function () { reject(r.error); };
          } else {
            t.oncomplete = function () { resolve(r); };
            t.onerror = function () { reject(t.error); };
          }
        } catch (e) {
          resolve(fn(null));
        }
      });
    }).then(function (v) { return v; }, function () { return fn(null); });
  }

  function get(store, key) {
    if (!key) return Promise.resolve(null);
    if (mem[store] && mem[store][key]) return Promise.resolve(mem[store][key]);
    var cached = store === "tiles" ? null : lsRead(store, key);
    return tx(store, "readonly", function (s) {
      if (!s) return cached;
      return s.get(key);
    }).then(function (row) {
      return row || cached || null;
    });
  }

  function put(store, key, value) {
    if (store === "events" || store === "queue") {
      if (!value || !value.event_id) return Promise.resolve(false);
      mem[store][value.event_id] = value;
      lsWrite(store, value.event_id, value);
      return tx(store, "readwrite", function (s) {
        if (!s) return true;
        return s.put(value);
      });
    }
    mem[store][key] = value;
    lsWrite(store, key, value);
    return tx(store, "readwrite", function (s) {
      if (!s) return true;
      return s.put(value, key);
    });
  }

  function del(store, key) {
    if (mem[store]) delete mem[store][key];
    lsRemove(store, key);
    return tx(store, "readwrite", function (s) {
      if (!s) return true;
      return s.delete(key);
    });
  }

  function getAll(store) {
    return tx(store, "readonly", function (s) {
      if (!s) {
        var fromLs = store === "tiles" ? [] : lsAll(store);
        if (fromLs.length) return fromLs;
        var o = mem[store] || {};
        return Object.keys(o).map(function (k) { return o[k]; });
      }
      return s.getAll();
    }).then(function (rows) {
      return Array.isArray(rows) ? rows : [];
    });
  }

  window.COMSPEC_MapStore = {
    open: openDb,
    get: get,
    put: put,
    del: del,
    all: getAll
  };
})();
