(function () {
    "use strict";
    var MAPS = { "altis": { "title": "Altis", "worldName": "Altis", "slug": "altis", "tileSize": 212, "worldSize": 30720, "maxZoom": 6, "defaultZoom": 3, "center": [15000, 15000], "source": "jetelain/Arma3Map" }, "stratis": { "title": "Stratis", "worldName": "Stratis", "slug": "stratis", "tileSize": 226, "worldSize": 8192, "maxZoom": 4, "defaultZoom": 2, "center": [4100, 4100], "source": "jetelain/Arma3Map" }, "enoch": { "title": "Livonia", "worldName": "Enoch", "slug": "enoch", "aliases": ["livonia"], "tileSize": 356, "worldSize": 12800, "maxZoom": 4, "defaultZoom": 2, "center": [7100, 7100], "source": "jetelain/Arma3Map" }, "tanoa": { "title": "Tanoa", "worldName": "Tanoa", "slug": "tanoa", "tileSize": 213, "worldSize": 15360, "maxZoom": 5, "defaultZoom": 2, "center": [7000, 7000], "source": "jetelain/Arma3Map" }, "malden": { "title": "Malden", "worldName": "Malden", "slug": "malden", "tileSize": 256, "worldSize": 12800, "maxZoom": 5, "defaultZoom": 2, "center": [6400, 6400], "source": "jetelain/Arma3Map" }, "vr": { "title": "Virtual Reality", "worldName": "VR", "slug": "vr", "tileSize": 256, "worldSize": 8192, "maxZoom": 5, "defaultZoom": 2, "center": [4096, 4096], "source": "fallback" }, "kunduz": { "title": "Kunduz, Afghanistan", "worldName": "kunduz", "slug": "kunduz", "tileSize": 323, "worldSize": 5120, "maxZoom": 3, "defaultZoom": 2, "center": [2560, 2560], "source": "jetelain/Arma3Map" }, "sze_kimmirut": { "title": "Kimmirut", "worldName": "sze_kimmirut", "slug": "sze_kimmirut", "aliases": ["kimmirut"], "tileSize": 323, "worldSize": 20480, "maxZoom": 6, "defaultZoom": 2, "center": [10240, 10240], "source": "jetelain/Arma3Map" } };
    function key(v) { return String(v || "altis").toLowerCase(); }
    function resolve(v) {
        var k = key(v);
        if (MAPS[k])
            return MAPS[k];
        var found = null;
        Object.keys(MAPS).some(function (id) { var m = MAPS[id]; var a = Array.isArray(m.aliases) ? m.aliases : []; if (a.indexOf(k) >= 0) {
            found = m;
            return true;
        } return false; });
        return found || { title: String(v || "Terrain"), worldName: String(v || "Altis"), slug: k, tileSize: 256, worldSize: 30720, maxZoom: 7, defaultZoom: 3, center: [15360, 15360], source: "fallback" };
    }
    window.COMSPEC_MapTheatres = { all: MAPS, resolve: resolve, slug: function (v) { return resolve(v).slug; } };
})();
