/*
    Panneau SITUATION de la carte : sans sélection, résumé de la mission ; avec une unité sélectionnée (clic),
    sa fiche ATAK : indicatif, nom, grade et fonction, groupe, état, position, distance et gisement, déplacement,
    téléphone (en ligne, batterie, signal), identité ORBAT. Les calques sont des boutons sous ce texte (fn_pageMap).
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith {};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = _s getOrDefault ["selectedEntity", createHashMap];
private _dim = { format ["<t color='#8a9a93'>%1</t>", _this] };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _text = "";
if ((count _e) isEqualTo 0) then {
    private _units = values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
    private _fr = { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" } count _units;
    private _ho = { (_x getOrDefault ["affiliation", ""]) isEqualTo "hostile" } count _units;
    // Résumé : une ligne par mesure, libellé à gauche, valeur alignée à droite.
    private _net = _s getOrDefault ["networkState", "OFFLINE"];
    private _netTxt = createHashMapFromArray [["CONNECTED", "<t color='#5cc76b'>connecté</t>"], ["DEGRADED", "<t color='#f2ab33'>dégradé</t>"]] getOrDefault [_net, "<t color='#e5483a'>hors ligne</t>"];
    private _w = wind;
    private _from = (((_w select 0) atan2 (_w select 1)) + 180) mod 360;
    private _kv = { params ["_k", "_v"]; format ["<t align='left' color='#8a9a93'>%1</t><t align='right'>%2</t>", _k, _v] };
    private _tile = { params ["_n", "_lab", "_col"]; format ["<t size='1.6' font='RobotoCondensedBold' color='%3'>%1</t><t size='0.8' color='#8a9a93'> %2</t>", _n, _lab, _col] };
    _text = format ["<t size='0.85'>%1   %2<br/><br/>%3<br/>%4<br/>%5<br/>%6<br/>%7</t><br/><br/><t size='0.75' color='#8a9a93'>Cliquez sur une unité pour sa fiche.</t>",
        [_fr, "alliés", "#47b3ff"] call _tile, [_ho, "contacts", ["#8a9a93", "#e5483a"] select (_ho > 0)] call _tile,
        ["Réseau", _netTxt] call _kv,
        ["Groupe", [player, true] call comspec_atak_native_fnc_unitGroup] call _kv,
        ["Terrain", worldName] call _kv,
        ["Heure", [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _kv,
        ["Météo", format ["pluie %1 %% · vent %2 m/s du %3", round (rain * 100), round (vectorMagnitude [_w select 0, _w select 1, 0]), [_from] call _card]] call _kv];
} else {
    private _p = _e getOrDefault ["position", [0, 0, 0]];
    private _o = _e getOrDefault ["object", objNull];
    private _aff = _e getOrDefault ["affiliation", "unknown"];
    private _affTxt = createHashMapFromArray [["friend", "<t color='#47b3ff'>ami</t>"], ["hostile", "<t color='#e5483a'>ennemi</t>"], ["neutral", "<t color='#5cc76b'>neutre</t>"]] getOrDefault [_aff, "inconnu"];
    private _fresh = createHashMapFromArray [["LIVE", "<t color='#5cc76b'>en direct</t>"], ["STALE", "<t color='#f2ab33'>retardée</t>"], ["LOST", "<t color='#e5483a'>perdue</t>"], ["OFFLINE", "<t color='#8a9a93'>hors ligne</t>"]] getOrDefault [_e getOrDefault ["freshness", "LIVE"], "?"];
    private _lines = [format ["<t size='1.15' font='RobotoCondensedBold' color='#5cc76b'>%1</t>  %2", _e getOrDefault ["callsign", "CONTACT"], _affTxt]];
    if (!isNull _o) then {
        if (isPlayer _o && {_aff isEqualTo "friend"}) then { _lines pushBack format ["%1 %2", "Nom :" call _dim, name _o]; };
        private _role = roleDescription _o;
        if (_role isEqualTo "") then { _role = getText (configOf _o >> "displayName"); };
        _lines pushBack format ["%1 %2 · %3", "Rang :" call _dim, createHashMapFromArray [["PRIVATE", "Soldat"], ["CORPORAL", "Caporal"], ["SERGEANT", "Sergent"], ["LIEUTENANT", "Lieutenant"], ["CAPTAIN", "Capitaine"], ["MAJOR", "Commandant"], ["COLONEL", "Colonel"]] getOrDefault [rank _o, rank _o], _role];
        if (_aff isEqualTo "friend") then { _lines pushBack format ["%1 %2%3", "Groupe :" call _dim, [_o, true] call comspec_atak_native_fnc_unitGroup, ["", " (chef)"] select (leader group _o isEqualTo _o)]; };
        private _life = switch (true) do {
            case (!alive _o): { "<t color='#e5483a'>mort</t>" };
            case ((lifeState _o) isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>inconscient</t>" };
            case ((damage _o) > 0.4): { "<t color='#f2ab33'>blessé</t>" };
            default { "<t color='#5cc76b'>valide</t>" };
        };
        _lines pushBack format ["%1 %2", "État :" call _dim, _life];
    };
    _lines pushBack format ["%1 <t font='EtelkaMonospacePro'>%2</t> · %3 m", "Position :" call _dim, [_p, 8] call comspec_atak_native_fnc_gridRef, round (_p select 2)];
    _lines pushBack format ["%1 %2 · %3° %4", "Distance :" call _dim, [player distance2D _p] call _fmt, round (player getDir _p), [player getDir _p] call _card];
    if (!isNull _o && {alive _o}) then {
        private _v = vehicle _o;
        _lines pushBack format ["%1 %2 km/h · cap %3°%4", "Mouvement :" call _dim, round ((speed _v) max 0), round getDir _v,
            ["", format [" · à bord : %1", getText (configOf _v >> "displayName")]] select (_v isNotEqualTo _o)];
    };
    if (!isNull _o && {_aff isEqualTo "friend"} && {isPlayer _o}) then {
        (_o getVariable ["COMSPEC_ATAK_Pub", []]) params [["_bat", -1], ["_bars", -1], ["_st", ""]];
        private _on = _o getVariable ["COMSPEC_ATAK_Beacon", true];
        _lines pushBack format ["%1 %2%3%4", "Téléphone :" call _dim, ["<t color='#e5483a'>hors ligne</t>", "<t color='#5cc76b'>en ligne</t>"] select _on,
            ["", format [" · batterie %1 %%", _bat]] select (_bat >= 0), ["", format [" · signal %1/4", _bars]] select (_bars >= 0)];
        if (_st in ["DAMAGED", "BROKEN"]) then { _lines pushBack format ["%1 %2", "Appareil :" call _dim, ["écran fêlé", "détruit"] select (_st isEqualTo "BROKEN")]; };
        private _orbat = _o getVariable ["COMSPEC_ATAK_Orbat", ""];
        if (_orbat isNotEqualTo "") then { _lines pushBack format ["%1 %2", "ORBAT :" call _dim, _orbat]; };
    };
    _lines pushBack format ["%1 %2 · il y a %3 s", "Liaison :" call _dim, _fresh, round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]))];
    _text = format ["<t size='0.9'>%1</t>", _lines joinString "<br/>"];
};
// Deux lignes vides : le bandeau « SITUATION · REPLIER » recouvre le haut du panneau.
private _ctl = _d displayCtrl 88541;
_ctl ctrlSetStructuredText parseText ("<t size='1.1'> </t><br/><br/>" + _text);
// Le texte s'arrête au-dessus des calques (posés en bas du panneau par fn_pageMap) au lieu de passer dessous.
private _bottom = uiNamespace getVariable ["COMSPEC_ATAK_InspTextBottom", -1];
if (_bottom > 0) then {
    (ctrlPosition _ctl) params ["_cx", "_cy", "_cw"];
    _ctl ctrlSetPosition [_cx, _cy, _cw, (_bottom - _cy) max 0];
    _ctl ctrlCommit 0;
};
if ((count _e) > 0 && {([] call comspec_atak_native_fnc_layoutGet) get "mini"}) then {
    ["INFO", format ["%1 · %2", _e getOrDefault ["callsign", "CONTACT"], [_e getOrDefault ["position", [0, 0, 0]], 8] call comspec_atak_native_fnc_gridRef], 4, 20] call comspec_atak_native_fnc_notify;
};
