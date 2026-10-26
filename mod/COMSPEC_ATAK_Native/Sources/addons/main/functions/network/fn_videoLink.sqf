/*
    Flux vidéo selon le chemin radio : drone, caméra casque, Live cam et caméras fixes passent par les relais de la mission.
    Inactif (comportement « arcade » d'avant, rien ne change) quand aucun relais ni passerelle du camp n'est connu
    (fn_relayNet "list" vide) ou quand le réglage serveur comspec_atak_native_video_relays est décoché.
      ["path", [clé, position ASL de la source, qualité directe 0-1, Mbit/s direct max]] → HashMap :
          active  : faux = mode arcade (les autres clés valent un flux parfait) ;
          ok, q (0-1, règle les défauts TNT de fn_droneStatic), mbps, lat (ms), loss (%), hops (relais traversés,
          0 = liaison directe), via (relais côté source), viaMe (relais côté téléphone), target (palier visé 0-4), why.
          Chemins comparés, le meilleur débit l'emporte :
            direct          source → téléphone (qualité directe donnée par l'appelant, 0 = pas de lien direct) ;
            un relais       source → relais → téléphone ;
            réseau          source → relais A → cœur de réseau (répéteurs déjà comptés par fn_relayNet) → relais B → téléphone.
          Chaque bond multiplie le débit par la qualité de la liaison radio (1 - (d/portée)², portée réduite à 35 %
          derrière le relief), traverser le cœur de réseau coûte 20 % et 60 ms ; la réception du téléphone est en plus
          bornée par son débit simulé (fn_linkQuality : bâtiment, brouillage, antenne, mode avion...).
          Recalculé au plus une fois par seconde par clé (quelques terrainIntersectASL, pas de coût par image).
      ["adapt", [clé, HashMap de "path"]] → [palier retenu, texte du changement ou ""] : débit adaptatif, comme un
          vrai lien vidéo : baisse de palier après 2 s sous le seuil, remontée après 6 s au-dessus, perte immédiate.
      ["tier", palier] → [libellé, images/s, couleur] ; paliers : 0 NO SIGNAL, 1 image par image, 2 480p, 3 720p, 4 1080p.
      ["osd", HashMap de "path", palier] → incrustation courte (« 720p · 3,4 Mbit/s · via Relais A · 120 ms »), "" en arcade.
    Seuils de débit vidéo : 1080p dès 7 Mbit/s, 720p dès 3,5, 480p dès 1,2, image par image dès 0,15 Mbit/s.
    État : uiNamespace COMSPEC_ATAK_VideoPath (cache [heure, HashMap] par clé), COMSPEC_ATAK_VideoTier ([palier,
    palier en attente, depuis] par clé).
*/
params [["_act", "path"], ["_arg", []]];
private _tiers = [["NO SIGNAL", 0, "#e5483a"], ["IMAGE PAR IMAGE", 1.5, "#e5483a"], ["480p", 6, "#f2ab33"], ["720p", 25, "#c9d4cf"], ["1080p", 30, "#5cc76b"]];
switch (_act) do {
    case "tier": { _tiers select (((_arg max 0) min 4)) };
    case "path": {
        _arg params [["_key", ""], ["_src", [0, 0, 0]], ["_qd", 0], ["_mbd", 20]];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_VideoPath", createHashMap];
        private _hit = _cache getOrDefault [_key, []];
        if ((count _hit) isEqualTo 2 && {diag_tickTime - (_hit select 0) < 1}) exitWith { _hit select 1 };
        private _relays = [];
        if (["videoRelays"] call comspec_atak_native_fnc_netCond) then {
            _relays = ["list"] call comspec_atak_native_fnc_relayNet;
        };
        // Mode arcade : pas de relais dans la mission, flux parfait tant que l'appelant dit que la liaison passe.
        if (_relays isEqualTo []) exitWith {
            private _r = createHashMapFromArray [["active", false], ["ok", _qd > 0], ["q", _qd], ["mbps", 0], ["lat", 0], ["loss", 0], ["hops", 0], ["via", ""], ["viaMe", ""], ["target", 4], ["why", ""]];
            _cache set [_key, [diag_tickTime, _r]];
            uiNamespace setVariable ["COMSPEC_ATAK_VideoPath", _cache];
            _r
        };
        _relays = _relays select { _x select 6 };
        private _eye = eyePos player;
        // Réception du téléphone : bornée par son débit simulé (bâtiment, brouillage, antenne, mode avion).
        private _lq = [] call comspec_atak_native_fnc_linkQuality;
        private _meCap = ((_lq getOrDefault ["kbps", 0]) / 1000) max 0;
        if ((_lq getOrDefault ["bars", 0]) isEqualTo 0) then { _meCap = 0; };
        // Meilleur relais vu de la source et du téléphone : [entrée, qualité, Mbit/s].
        private _bestFrom = {
            params ["_p"];
            private _b = [[], 0, 0];
            {
                if (((_x select 1) distance2D _p) > (_x select 4)) then { continue };
                private _l = ["link", [_x select 1, _x select 4, _x select 5, _p]] call comspec_atak_native_fnc_relayNet;
                if ((_l select 1) > (_b select 2)) then { _b = [_x, _l select 0, _l select 1]; };
            } forEach _relays;
            _b
        };
        ([_src] call _bestFrom) params ["_rs", "_qs", "_ms"];
        ([_eye] call _bestFrom) params ["_rm", "_qm", "_mm"];
        // Chemins candidats : [Mbit/s, qualité, latence, sauts, relais source, relais téléphone].
        private _cands = [];
        if (_qd > 0) then { _cands pushBack [_mbd * (0.3 + 0.7 * _qd), _qd, 30, 0, "", ""]; };
        if (_rs isNotEqualTo [] && {_rm isNotEqualTo []} && {_meCap > 0}) then {
            if ((_rs select 0) isEqualTo (_rm select 0)) then {
                _cands pushBack [(_ms min _mm) min _meCap, _qs min _qm, 70, 1, _rs select 2, ""];
            } else {
                _cands pushBack [((_ms min _mm) * 0.8) min _meCap, (_qs min _qm) * 0.95, 130, 2, _rs select 2, _rm select 2];
            };
        };
        private _best = [0, 0, 0, 0, "", ""];
        { if ((_x select 0) > (_best select 0)) then { _best = _x; }; } forEach _cands;
        _best params ["_mb", "_q", "_lat", "_hops", "_via", "_viaMe"];
        private _ok = _mb >= 0.15;
        private _target = switch (true) do { case (!_ok): { 0 }; case (_mb < 1.2): { 1 }; case (_mb < 3.5): { 2 }; case (_mb < 7): { 3 }; default { 4 }; };
        _lat = round (_lat + (1 - _q) * 400 + ([0, 250] select (_target < 2)));
        // Raison en clair, pour le journal et l'incrustation.
        private _why = switch (true) do {
            case (!_ok && {_meCap <= 0} && {_qd <= 0}): { "téléphone sans réseau" };
            case (!_ok && {_rs isEqualTo []} && {_qd <= 0}): { "source hors de portée des relais" };
            case (!_ok && {_rm isEqualTo []} && {_qd <= 0}): { "aucun relais reçu par le téléphone" };
            case (!_ok): { "liaison trop faible" };
            case (_hops isEqualTo 0): { format ["liaison directe à %1 m", round (_src distance _eye)] };
            case (_hops isEqualTo 1): { format ["via %1 à %2 m de la source", _via, round (((_rs select 1)) distance _src)] };
            default { format ["via %1 puis %2, par le réseau", _via, _viaMe] };
        };
        if (_ok && {_hops > 0} && {(_mm min _meCap) < _ms * 0.6}) then { _why = format ["%1, réception du téléphone faible", _why]; };
        private _r = createHashMapFromArray [["active", true], ["ok", _ok], ["q", [0, (_q max 0.02) min 1] select _ok], ["mbps", [0, _mb] select _ok], ["lat", _lat],
            ["loss", [100, round ((1 - _q) * 30)] select _ok], ["hops", _hops], ["via", _via], ["viaMe", _viaMe], ["target", _target], ["why", _why]];
        _cache set [_key, [diag_tickTime, _r]];
        uiNamespace setVariable ["COMSPEC_ATAK_VideoPath", _cache];
        _r
    };
    case "adapt": {
        _arg params [["_key", ""], ["_p", createHashMap]];
        if !(_p getOrDefault ["active", false]) exitWith { [4, ""] };
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_VideoTier", createHashMap];
        (_st getOrDefault [_key, [-1, -1, 0]]) params ["_cur", "_pend", "_since"];
        private _tgt = _p getOrDefault ["target", 0];
        private _now = diag_tickTime;
        private _new = _cur;
        switch (true) do {
            case (_cur < 0): { _new = _tgt; };
            case (_tgt isEqualTo _cur): { _pend = -1; };
            // Perte : immédiate. Baisse : 2 s sous le seuil. Remontée : 6 s au-dessus (évite le yoyo de qualité).
            case (_tgt isEqualTo 0): { _new = 0; };
            default {
                if (_pend isNotEqualTo _tgt) then { _pend = _tgt; _since = _now; };
                if (_now - _since >= ([6, 2] select (_tgt < _cur))) then { _new = _tgt; };
            };
        };
        private _txt = "";
        if (_new isNotEqualTo _cur) then {
            _pend = -1;
            if (_cur >= 0) then {
                _txt = format ["Flux vidéo %1 » %2 : %3%4", (["tier", _cur] call comspec_atak_native_fnc_videoLink) select 0, (["tier", _new] call comspec_atak_native_fnc_videoLink) select 0,
                    _p getOrDefault ["why", ""], ["", format [" (%1 Mbit/s)", (_p getOrDefault ["mbps", 0]) toFixed 1]] select (_new > 0)];
            };
        };
        _st set [_key, [_new, _pend, _since]];
        uiNamespace setVariable ["COMSPEC_ATAK_VideoTier", _st];
        [_new, _txt]
    };
    case "osd": {
        _arg params [["_p", createHashMap], ["_tier", 4]];
        if !(_p getOrDefault ["active", false]) exitWith { "" };
        (_tiers select ((_tier max 0) min 4)) params ["_lb", "", "_c"];
        if (_tier isEqualTo 0) exitWith { format ["<t color='%1'>%2</t>", _c, _lb] };
        private _hops = _p getOrDefault ["hops", 0];
        format ["<t color='%1'>%2</t> · %3 Mbit/s · %4 · %5 ms", _c, _lb, (_p getOrDefault ["mbps", 0]) toFixed 1,
            [format ["via %1%2", _p getOrDefault ["via", ""], ["", format [" + %1", _p getOrDefault ["viaMe", ""]]] select (_hops > 1)], "liaison directe"] select (_hops isEqualTo 0),
            _p getOrDefault ["lat", 0]]
    };
};
