/*
    App Bluetooth : marche / arrêt (indépendant du mode avion), appairage par code avec un ATAK proche,
    appareils appairés (portée, oubli), demandes de son reçues (accepter / refuser), envoi d'un son de la bibliothèque
    (sons d'Arma, des mods et de la mission, pistes de l'app Musique) à un appareil appairé.
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]. Actions : fn_btAction.
*/
params [["_page", "BLUETOOTH"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bt = ["state"] call comspec_atak_native_fnc_btAction;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BtUi", _ui];
private _now = [time, serverTime] select isMultiplayer;
private _range = missionNamespace getVariable ["comspec_atak_native_bt_range", 15];
private _on = _bt get "on";
private _usable = ["on"] call comspec_atak_native_fnc_btAction;
private _air = [] call comspec_atak_native_fnc_airplaneMode;
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa";
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_btAction;", str _a, str _v] };
private _rows = [
    ["switch", "Bluetooth", _on, ["toggle"] call _act, format ["ATAK de %1 · visible des téléphones à moins de %2 m", [name player] call _esc, round _range]],
    ["switch", "Mode avion", _air, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; }, "Coupe le réseau (synchro, SMS, BFT). Le Bluetooth reste disponible."]
];
if (!_on) exitWith {
    _rows pushBack ["text", "<t color='#8a9a93'>Bluetooth désactivé. Activez-le pour appairer un ATAK proche et échanger des sons.</t>"];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
if (!_usable) then { _rows pushBack ["text", "<t color='#f2ab33'>Téléphone indisponible : Bluetooth inactif.</t>"]; };

// Appairage par code
private _code = _bt get "code";
private _codeOn = _code isNotEqualTo "" && {_now < (_bt get "codeUntil")};
_rows pushBack ["section", "Appairer un appareil", format ["Les deux téléphones à moins de %1 m", round _range]];
if (_codeOn) then {
    _rows pushBack ["text", format ["<t align='center' size='2' font='RobotoCondensedBold' color='%1'>%2 %3</t><br/><t align='center' size='0.8' color='#8a9a93'>Code d'appairage · valable encore %4 s · à saisir sur l'autre téléphone</t>",
        _accHex, _code select [0, 3], _code select [3, 3], round ((_bt get "codeUntil") - _now)]];
    _rows pushBack ["buttons", [["CACHER LE CODE", ["hideCode"] call _act]]];
} else {
    _rows pushBack ["buttons", [["AFFICHER MON CODE", ["showCode"] call _act, true, _usable]]];
};
_rows append [
    ["edit", "btCode", "Code affiché par l'autre téléphone", ""],
    ["buttons", [["APPAIRER", ["enterCode"] call _act, true, _usable]]]
];

// Demandes reçues
private _pend = (_bt get "pending") select { (_now - (_x select 6)) <= 60 };
if ((count _pend) > 0) then {
    _rows pushBack ["section", "Demandes reçues", "Le son sortira du haut-parleur : les joueurs proches l'entendront"];
    {
        _x params ["_id", "", "_name", "_kind", "", "_title", "_at"];
        _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · %3 · expire dans %4 s</t>",
            [_title] call _esc, [_name] call _esc, ["son", "musique"] select (_kind isEqualTo "mus"), round (60 - (_now - _at))],
            [["ACCEPTER", ["accept", _id] call _act, true], ["REFUSER", ["refuse", _id] call _act]], _acc];
    } forEach _pend;
};
if ((_bt get "playing") isNotEqualTo "") then { _rows pushBack ["buttons", [["ARRÊTER LE SON EN COURS", ["stopPlay"] call _act, true]]]; };

// Appareils appairés
private _paired = _bt get "paired";
private _target = _ui getOrDefault ["target", ""];
if !(_target in _paired) then { _target = ""; _ui set ["target", ""]; };
_rows pushBack ["section", "Appareils appairés", format ["%1 appareil(s) · gardés pour la mission", count _paired]];
if ((count _paired) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun. Affichez votre code, ou saisissez celui d'un équipier proche.</t>"]; };
{
    private _uid = _x;
    private _name = _y;
    private _i = allPlayers findIf { getPlayerUID _x isEqualTo _uid };
    private _u = if (_i < 0) then { objNull } else { allPlayers select _i };
    private _st = switch (true) do {
        case (isNull _u): { "<t color='#8a9a93'>déconnecté</t>" };
        case !(_u getVariable ["COMSPEC_ATAK_BtOn", false]): { "<t color='#8a9a93'>Bluetooth éteint</t>" };
        case ((_u distance player) > _range): { format ["<t color='#f2ab33'>hors de portée · %1 m</t>", round (_u distance player)] };
        default { format ["<t color='%1'>connecté · %2 m</t>", _accHex, round (_u distance player)] };
    };
    _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold'>ATAK de %1</t><br/><t size='0.8'>%2</t>", [_name] call _esc, _st],
        [[["SONS", "FERMER"] select (_target isEqualTo _uid), ["target", _uid] call _act, _target isNotEqualTo _uid], ["OUBLIER", ["unpair", _uid] call _act]],
        [[0.55, 0.6, 0.58, 1], _acc] select (_target isEqualTo _uid)];
} forEach _paired;

// Envoi d'un son à l'appareil choisi
if (_target isNotEqualTo "") then {
    private _tab = _ui getOrDefault ["tab", "snd"];
    private _q = toLower (_ui getOrDefault ["q", ""]);
    private _all = ([] call comspec_atak_native_fnc_btSounds) select { (_x select 0) isEqualTo _tab && {_q isEqualTo "" || {((toLower (_x select 2)) find _q) >= 0} || {((toLower (_x select 1)) find _q) >= 0}} };
    uiNamespace setVariable ["COMSPEC_ATAK_BtList", _all];
    _rows append [
        ["section", format ["Envoyer à %1", [_paired getOrDefault [_target, ""]] call _esc], "Il accepte ou refuse ; le son sort de son téléphone"],
        ["segment", "", [["SONS", ["tab", "snd"] call _act, _tab isEqualTo "snd"], ["MUSIQUE", ["tab", "mus"] call _act, _tab isEqualTo "mus"]]],
        ["edit", "btSearch", "Rechercher", _ui getOrDefault ["q", ""]],
        ["buttons", [["RECHERCHER", ["search"] call _act, true], ["EFFACER", { (uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap]) set ["q", ""]; ["page", 0] call comspec_atak_native_fnc_btAction; }]]]
    ];
    private _per = 15;
    private _pg = (_ui getOrDefault ["page", 0]) min (floor (((count _all) - 1) / _per)) max 0;
    if ((count _all) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun son trouvé.</t>"]; };
    for "_i" from (_pg * _per) to (((_pg + 1) * _per min (count _all)) - 1) do {
        (_all select _i) params ["_kind", "_ref", "_title", "_cat", ["_len", 0]];
        _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_music.paa",
            format ["%1<br/><t size='0.75' color='#8a9a93'>%2%3</t>", [_title] call _esc, [_cat] call _esc, ["", format [" · %1:%2", floor (_len / 60), [str (floor _len mod 60), "0" + str (floor _len mod 60)] select ((floor _len mod 60) < 10)]] select (_len > 0)],
            [["ENVOYER", ["send", _i] call _act, true, _usable]]];
    };
    if ((count _all) > _per) then {
        _rows pushBack ["buttons", [
            ["PRÉCÉDENTS", ["page", _pg - 1] call _act, false, _pg > 0],
            [format ["%1 / %2", _pg + 1, ceil ((count _all) / _per)], {}, false, false],
            ["SUIVANTS", ["page", _pg + 1] call _act, false, ((_pg + 1) * _per) < (count _all)]
        ]];
    };
};

// Envois récents
private _sent = _bt get "sent";
if ((count _sent) > 0) then {
    _rows pushBack ["section", "Envois récents", ""];
    _rows pushBack ["text", ((+_sent) apply { format ["<t size='0.85'>%1 → %2 · <t color='%3'>%4</t></t>", [_x select 2] call _esc, [_x select 1] call _esc,
        switch (_x select 3) do { case "ACCEPTÉ": { _accHex }; case "REFUSÉ": { "#e5483a" }; default { "#8a9a93" }; }, toLower (_x select 3)] }) joinString "<br/>"];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
