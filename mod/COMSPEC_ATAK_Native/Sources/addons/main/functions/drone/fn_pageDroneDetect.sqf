/*
    App Détecteur de drones (page DRONEDETECT), dans l'esprit des capteurs RF portables : écoute passive des liaisons
    vidéo 5,8 GHz et C2 2,4 GHz. En tête, un radar cap en haut (couronne tournée selon mon cap, une aiguille par drone
    avec son cône d'erreur), dessous la liste : AMI / INCONNU, relèvement et position horaire, barres de signal, bande,
    distance seulement si le signal est fort, depuis quand on l'entend.
    Balayage : fn_droneDetectScan. Carte : fn_droneDetectDraw. Réglages profil : COMSPEC_ATAK_DroneAuto (balayage
    automatique), COMSPEC_ATAK_LayerDrone (relèvements sur la carte).
    Tant que l'app est ouverte, un PFH qui se retire seul fait tourner le radar avec le cap et, en automatique,
    balaie toutes les 3 s (la liste n'est redessinée que si elle change).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = pixelH / pixelW;
private _now = time;
private _auto = profileNamespace getVariable ["COMSPEC_ATAK_DroneAuto", false];
private _layer = profileNamespace getVariable ["COMSPEC_ATAK_LayerDrone", true];
private _sensor = player getVariable ["COMSPEC_ATAK_DroneSensor", missionNamespace getVariable ["comspec_atak_native_drone_sensor", true]];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_DroneDetect", []];
private _h = getDir (vehicle player);
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _ago = { params ["_s"]; _s = round (_s max 0); [format ["%1 s", _s], format ["%1 min %2 s", floor (_s / 60), _s mod 60]] select (_s >= 60) };
private _deg3 = { params ["_d"]; private _t = str ((round _d) mod 360); while { (count _t) < 3 } do { _t = "0" + _t; }; _t };
private _rerender = "[{ if (((uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['activePage', '']) isEqualTo 'DRONEDETECT') then { ['DRONEDETECT'] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;";

// Ordre : inconnus d'abord, puis les plus forts.
private _keys = [];
for "_i" from 0 to ((count _list) - 1) do {
    private _e = _list select _i;
    _keys pushBack [([10, 0] select (_e select 8)) + (_e select 2) - ([0, 5] select ((_now - (_e select 7)) > 8)), _i];
};
_keys sort false;
private _sorted = _keys apply { _list select (_x select 1) };

// --- Radar ---
private _vh = _bh * 0.36;
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _bg = ["COMSPEC_RscPanel", [0, 0, _bw, _vh]] call _mk;
_bg ctrlSetBackgroundColor [0.02, 0.03, 0.025, 0.98];
private _rh = _vh - _pad * 2;
private _rw = _rh / _ratio;
private _rx = _pad * 2;
private _ry = _pad;
private _ring = ["COMSPEC_RscIcon", [_rx, _ry, _rw, _rh], _dir + "compass_ring.paa"] call _mk;
_ring ctrlSetTextColor [0.75, 0.8, 0.77, 0.7];
_ring ctrlSetAngle [-_h, 0.5, 0.5];
private _needles = [];
{
    _x params ["", "", "_bars", "_brg", "_err", "", "", "_last", "_friend"];
    private _rgb = [[0.9, 0.28, 0.23], [0.3, 0.6, 1]] select _friend;
    private _a = [1, 0.4] select ((_now - _last) > 8);
    // Cône d'erreur : deux aiguilles pâles de part et d'autre.
    {
        private _c = ["COMSPEC_RscIcon", [_rx, _ry, _rw, _rh], _dir + "compass_needle.paa"] call _mk;
        _c ctrlSetTextColor (_rgb + [_a * 0.3]);
        _c ctrlSetAngle [_brg + _x - _h, 0.5, 0.5];
        _needles pushBack [_c, _brg + _x];
    } forEach [-_err, _err];
    private _c = ["COMSPEC_RscIcon", [_rx, _ry, _rw, _rh], _dir + "compass_needle.paa"] call _mk;
    _c ctrlSetTextColor (_rgb + [_a * (0.55 + 0.1 * _bars)]);
    _c ctrlSetAngle [_brg - _h, 0.5, 0.5];
    _needles pushBack [_c, _brg];
} forEach (_sorted select [0, 6]);
private _side = ["COMSPEC_RscStructuredText", [_rx + _rw + _pad * 2, _ry, _bw - _rw - _pad * 5, _rh]] call _mk;
// Bandeau : cap, inconnus entendus, mode et dernier balayage (autonome, rappelé par le PFH).
private _summary = {
    params ["_hd"];
    private _t = time;
    private _unk = { !(_x select 8) && {(_t - (_x select 7)) <= 8} } count (missionNamespace getVariable ["COMSPEC_ATAK_DroneDetect", []]);
    private _at = uiNamespace getVariable ["COMSPEC_ATAK_DroneScanAt", -1];
    private _s = round ((diag_tickTime - _at) max 0);
    private _d3 = str ((round _hd) mod 360);
    while { (count _d3) < 3 } do { _d3 = "0" + _d3; };
    format ["<t size='0.75' color='#8a9a93'>CAP</t><br/><t size='1.6' font='RobotoCondensedBold'>%1°</t> <t color='#8a9a93'>%2</t><br/>%3<br/><t size='0.8' color='#8a9a93'>%4<br/>%5</t>",
        _d3, ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_hd / 45)) mod 8),
        [format ["<t color='#e5483a' font='RobotoCondensedBold'>%1 DRONE(S) INCONNU(S)</t>", _unk], "<t color='#5cc76b'>Aucun drone inconnu</t>"] select (_unk isEqualTo 0),
        ["Balayage manuel", "<t color='#5cc76b'>● Balayage automatique</t>"] select (profileNamespace getVariable ["COMSPEC_ATAK_DroneAuto", false]),
        ["Pas encore balayé", format ["Dernier balayage il y a %1", [format ["%1 s", _s], format ["%1 min %2 s", floor (_s / 60), _s mod 60]] select (_s >= 60)]] select (_at >= 0)]
};
_side ctrlSetStructuredText parseText ([_h] call _summary);
uiNamespace setVariable ["COMSPEC_ATAK_DroneRadar", [_ring, _needles, _side, _summary]];

// --- Liste ---
private _rows = [
    ["buttons", [["BALAYER", { ["manual"] call comspec_atak_native_fnc_droneDetectScan; }, true], ["EFFACER", compile format ["['clear'] call comspec_atak_native_fnc_droneDetectScan; %1", _rerender]]]],
    ["switch", "Balayage automatique", _auto, compile format ["profileNamespace setVariable ['COMSPEC_ATAK_DroneAuto', %1]; saveProfileNamespace; %2", !_auto, _rerender], "Toutes les 5 s, même l'app fermée ; alerte et vibration si un drone inconnu est proche"],
    ["switch", "Relèvements sur la carte", _layer, compile format ["profileNamespace setVariable ['COMSPEC_ATAK_LayerDrone', %1]; saveProfileNamespace; %2", !_layer, _rerender], "Cône d'erreur tracé depuis le point de relevé"],
    ["section", "Détections", format ["%1 liaison(s) · récepteur passif, n'émet rien", count _list]]
];
{
    _x params ["_nid", "_band", "_bars", "_brg", "_err", "_rb", "_first", "_last", "_friend", ["_from", []], ["_dbm", -90]];
    private _lost = (_now - _last) > 8;
    private _rel = (_brg - _h + 360) mod 360;
    private _clock = (round (_rel / 30)) mod 12;
    if (_clock isEqualTo 0) then { _clock = 12; };
    private _barTxt = format ["<t color='%1'>%2</t><t color='#3a4440'>%3</t>", ["#5cc76b", "#8a9a93"] select _lost, ["", "●", "●●", "●●●", "●●●●"] select _bars, ["", "●", "●●", "●●●", "●●●●"] select (4 - _bars)];
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='1.15' font='RobotoCondensedBold'>%3° %4</t>  <t color='#8a9a93'>±%5°</t>  <t size='0.9'>à %6 h</t><br/><t size='0.9'>%7  %8 dBm · %9</t><br/><t size='0.8' color='#8a9a93'>%10 · entendu depuis %11 · %12</t>",
        ["#e5483a", "#4d9aff"] select _friend, ["INCONNU", "AMI"] select _friend, [_brg] call _deg3, [_brg] call _card, round _err, _clock,
        _barTxt, _dbm, _band,
        [format ["distance estimée %1", _rb], "distance inconnue (signal trop faible)"] select (_rb isEqualTo ""),
        [_last - _first] call _ago,
        [format ["<t color='#f2ab33'>signal perdu il y a %1</t>", [_now - _last] call _ago], "signal présent"] select !_lost]];
} forEach _sorted;
if ((count _list) isEqualTo 0) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Aucune liaison drone entendue. BALAYER pour écouter.</t>"];
} else {
    _rows pushBack ["buttons", [["VOIR SUR LA CARTE", { ["MAP"] call comspec_atak_native_fnc_navigate; [{ [player, 0.08] call comspec_atak_native_fnc_mapCenter; }] call CBA_fnc_execNextFrame; }]]];
};
_rows append [
    ["section", "Capteur", ""],
    ["info", "Antenne", ["téléphone seul (portée réduite de moitié)", "capteur RF externe"] select _sensor],
    ["info", "Portée en vue directe", ["1 km quadri · 2 km voilure fixe", "2 km quadri · 4 km voilure fixe"] select _sensor],
    ["text", "<t size='0.8' color='#8a9a93'>Seuls les drones en vol ou moteur en marche émettent. Relief, bâtiments, véhicule, pluie et brouillage réduisent la portée. Relèvement approximatif, jamais de position ; la distance n'est estimée que sur un signal fort, d'après sa puissance (un drone masqué paraît plus loin). AMI seulement pour un drone piloté depuis votre groupe, tout le reste est INCONNU.</t>"]
];
[_rows, [0, _vh, _bw, _bh - _vh]] call comspec_atak_native_fnc_formRender;

// Radar et balayage automatique tant que l'app est ouverte.
if ((uiNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]) < 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_DronePfh", [{
        params ["", "_id"];
        if (isNull ([] call comspec_atak_native_fnc_display) || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "DRONEDETECT"}) exitWith {
            [_id] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_DronePfh", -1];
        };
        private _hd = getDir (vehicle player);
        (uiNamespace getVariable ["COMSPEC_ATAK_DroneRadar", []]) params [["_ring", controlNull], ["_needles", []], ["_side", controlNull], ["_summary", {""}]];
        if (!isNull _ring) then {
            _ring ctrlSetAngle [-_hd, 0.5, 0.5];
            { (_x select 0) ctrlSetAngle [(_x select 1) - _hd, 0.5, 0.5]; } forEach _needles;
            _side ctrlSetStructuredText parseText ([_hd] call _summary);
        };
        if !(profileNamespace getVariable ["COMSPEC_ATAK_DroneAuto", false]) exitWith {};
        if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_DronePageScan", 0])) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_DronePageScan", diag_tickTime + 3];
        ["auto"] call comspec_atak_native_fnc_droneDetectScan;
        private _sig = str ((missionNamespace getVariable ["COMSPEC_ATAK_DroneDetect", []]) apply { [_x select 0, _x select 2, _x select 5, _x select 8, (time - (_x select 7)) > 8] });
        if (_sig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_DroneSig", ""])) then {
            uiNamespace setVariable ["COMSPEC_ATAK_DroneSig", _sig];
            ["DRONEDETECT"] call comspec_atak_native_fnc_pageRender;
        };
    }, 0.2] call CBA_fnc_addPerFrameHandler];
};
true
