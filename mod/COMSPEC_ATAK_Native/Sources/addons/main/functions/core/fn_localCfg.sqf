/*
    Configuration locale du joueur (mode full local, sans Athena) : fichier JSON modifiable à la main,
    Documents\Arma 3\COMSPEC_Local\config.json (DLL native, LocalConfig.cs), copie dans le profil Arma (sans la DLL,
    ou si le fichier est illisible). Contenu :
      callsign  indicatif personnel (gardé aussi hors ligne)
      phone     { plan (format des numéros générés : FR, US, UK, DE, BE, RU, ES), numbers [{number, type, label, primary, shared}] }
      contacts  [{name, number, type, note}]   carnet personnel
      toc       { name, callsign }             nom du poste TOC quand je tiens un PC de TOC (messages « TOC · OVERWATCH »)
    Types de numéro : mobile, satellite, landline, radio_ip, secure, pager, emergency (fn_localCfg "types").
    Params : [action, argument]
      "get"            -> HashMap de la config (chargée au premier appel)
      "save"           enregistre (fichier + profil) puis "apply"
      "reload"         relit le fichier (modifié à la main pendant la partie) puis "apply"
      "apply"          publie mes numéros aux autres machines (variables d'unité COMSPEC_ATAK_LocalNum, COMSPEC_ATAK_Numbers)
      "status"         -> [source "file" | "profile" | "default", dossier, erreur]
      "open"           ouvre le dossier dans l'explorateur Windows
      "types"          -> [[code, libellé, icône, couleur]...]
      "type" code      -> [code, libellé, icône, couleur]
      "generate" [type, plan] -> numéro fictif au bon format
*/
params [["_act", "get"], ["_arg", []]];
private _defaults = {
    createHashMapFromArray [
        ["version", 1], ["callsign", ""],
        ["phone", createHashMapFromArray [["plan", "FR"], ["numbers", []]]],
        ["contacts", []],
        ["toc", createHashMapFromArray [["name", "TOC"], ["callsign", "OVERWATCH"]]]
    ]
};
// Tableau SQF de la DLL (objet = ["#obj", [clé, valeur]...]) <-> HashMap.
private _dec = {
    if !(_this isEqualType []) exitWith { _this };
    if ((count _this) > 0 && {(_this select 0) isEqualTo "#obj"}) exitWith {
        private _h = createHashMap;
        { if (_x isEqualType [] && {(count _x) isEqualTo 2}) then { _h set [_x select 0, (_x select 1) call _dec]; }; } forEach (_this select [1]);
        _h
    };
    _this apply { _x call _dec }
};
private _enc = {
    if (_this isEqualType createHashMap) exitWith { ["#obj"] + ((keys _this) apply { [_x, (_this get _x) call _enc] }) };
    if (_this isEqualType []) exitWith { _this apply { _x call _enc } };
    _this
};
private _str = { params ["_h", "_k", ["_def", ""]]; private _v = _h getOrDefault [_k, _def]; if (_v isEqualType "") then { trim _v } else { _def } };
private _types = [
    ["mobile", "Mobile", "\z\comspec_atak_native\addons\main\data\item_phone.paa", "#5cc76b"],
    ["satellite", "Satellite", "\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa", "#4aa8e8"],
    ["landline", "Ligne fixe (poste, FOB)", "\a3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa", "#c9d4cf"],
    ["radio_ip", "Radio IP (MPU5, maillage)", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "#9a7fe0"],
    ["secure", "Ligne chiffrée", "\a3\ui_f\data\igui\cfg\simpletasks\types\defend_ca.paa", "#e8b84a"],
    ["pager", "Bip / pager", "\z\comspec_atak_native\addons\main\data\app_alerts.paa", "#8a9a93"],
    ["emergency", "Urgence", "\a3\ui_f\data\igui\cfg\simpletasks\types\heal_ca.paa", "#e04038"]
];
// Remet chaque champ au bon type (fichier modifié à la main) ; garde au plus 8 numéros et 100 contacts.
private _norm = {
    params ["_raw"];
    private _c = call _defaults;
    if !(_raw isEqualType createHashMap) exitWith { _c };
    _c set ["callsign", [_raw, "callsign"] call _str];
    private _p = _raw getOrDefault ["phone", createHashMap];
    if !(_p isEqualType createHashMap) then { _p = createHashMap; };
    private _plan = toUpper ([_p, "plan", "FR"] call _str);
    private _codes = _types apply { _x select 0 };
    private _nums = [];
    {
        if (_x isEqualType createHashMap && {([_x, "number"] call _str) isNotEqualTo ""} && {(count _nums) < 8}) then {
            private _t = toLower ([_x, "type", "mobile"] call _str);
            _nums pushBack createHashMapFromArray [
                ["number", ([_x, "number"] call _str) select [0, 32]], ["type", ["mobile", _t] select (_t in _codes)],
                ["label", ([_x, "label"] call _str) select [0, 40]], ["primary", (_x getOrDefault ["primary", false]) isEqualTo true],
                ["shared", (_x getOrDefault ["shared", true]) isNotEqualTo false]
            ];
        };
    } forEach ([_p getOrDefault ["numbers", []], []] select !((_p getOrDefault ["numbers", []]) isEqualType []));
    // Un seul numéro principal : le premier coché, sinon le premier mobile, sinon le premier.
    private _pi = _nums findIf { _x get "primary" };
    if (_pi < 0) then { _pi = _nums findIf { (_x get "type") isEqualTo "mobile" }; };
    if (_pi < 0 && {_nums isNotEqualTo []}) then { _pi = 0; };
    { _x set ["primary", _forEachIndex isEqualTo _pi]; } forEach _nums;
    _c set ["phone", createHashMapFromArray [["plan", [_plan, "FR"] select !(_plan in ["FR", "US", "UK", "DE", "BE", "RU", "ES"])], ["numbers", _nums]]];
    private _ct = [];
    {
        if (_x isEqualType createHashMap && {([_x, "name"] call _str) isNotEqualTo "" || {([_x, "number"] call _str) isNotEqualTo ""}} && {(count _ct) < 100}) then {
            private _t = toLower ([_x, "type", "mobile"] call _str);
            _ct pushBack createHashMapFromArray [
                ["name", ([_x, "name"] call _str) select [0, 40]], ["number", ([_x, "number"] call _str) select [0, 32]],
                ["type", ["mobile", _t] select (_t in _codes)], ["note", ([_x, "note"] call _str) select [0, 120]]
            ];
        };
    } forEach ([_raw getOrDefault ["contacts", []], []] select !((_raw getOrDefault ["contacts", []]) isEqualType []));
    _c set ["contacts", _ct];
    private _toc = _raw getOrDefault ["toc", createHashMap];
    if (_toc isEqualType createHashMap) then {
        (_c get "toc") set ["name", ([[_toc, "name"] call _str, "TOC"] select (([_toc, "name"] call _str) isEqualTo "")) select [0, 24]];
        (_c get "toc") set ["callsign", ([[_toc, "callsign"] call _str, "OVERWATCH"] select (([_toc, "callsign"] call _str) isEqualTo "")) select [0, 24]];
    };
    _c
};
// Lecture du fichier par la DLL, par morceaux (la réponse d'Arma est bornée) : ["ok", HashMap] | ["absent"] | ["json", message] | ["nodll"].
private _readFile = {
    private _out = "";
    private _off = 0;
    private _total = 1;
    private _err = [];
    while { _off < _total && {_off < 500000} } do {
        private _r = ["LocalGet", ["config", str _off]] call comspec_atak_native_fnc_extensionCall;
        if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {
            _err = switch (true) do {
                case ((_r select [0, 10]) isEqualTo "ERR|absent"): { ["absent"] };
                case ((_r select [0, 9]) isEqualTo "ERR|json|"): { ["json", _r select [9]] };
                default { ["nodll"] };
            };
        };
        private _rest = _r select [3];
        private _bar = _rest find "|";
        if (_bar < 0) exitWith { _err = ["nodll"]; };
        _total = parseNumber (_rest select [0, _bar]);
        private _chunk = _rest select [_bar + 1];
        if (_chunk isEqualTo "") exitWith {};
        _out = _out + _chunk;
        // La DLL compte en caractères UTF-16 (Substring) ; sans forceUnicode, count renvoie des octets UTF-8 (accents).
        // Caractère hors plan de base (émoji) : deux unités UTF-16.
        forceUnicode 0;
        private _n = count _chunk;
        _off = _off + _n + ({ _x > 65535 } count (toArray _chunk));
    };
    if (_err isNotEqualTo []) exitWith { _err };
    private _arr = parseSimpleArray _out;
    if !(_arr isEqualType [] && {(_arr param [0, ""]) isEqualTo "#obj"}) exitWith { ["json", "le fichier doit contenir un objet { … }"] };
    ["ok", _arr call _dec]
};
private _load = {
    private _r = call _readFile;
    private _src = "default";
    private _err = "";
    private _raw = createHashMap;
    switch (_r select 0) do {
        case "ok": { _raw = _r select 1; _src = "file"; };
        case "json": { _err = _r select 1; };
    };
    // Pas de fichier lisible (pas de DLL, premier lancement, JSON cassé) : copie du profil.
    if (_src isNotEqualTo "file") then {
        private _p = profileNamespace getVariable ["COMSPEC_ATAK_LocalCfgSave", []];
        if (_p isEqualType [] && {(_p param [0, ""]) isEqualTo "#obj"}) then { _raw = _p call _dec; _src = "profile"; };
    };
    private _cfg = [_raw] call _norm;
    missionNamespace setVariable ["COMSPEC_ATAK_LocalCfg", _cfg];
    missionNamespace setVariable ["COMSPEC_ATAK_LocalCfgState", [_src, _err, (_r select 0) isNotEqualTo "nodll"]];
    // Premier lancement avec la DLL : le fichier modèle est créé tout de suite, prêt à être modifié.
    if ((_r select 0) isEqualTo "absent") then { ["save"] call comspec_atak_native_fnc_localCfg; };
    _cfg
};
switch (_act) do {
    case "get": {
        private _c = missionNamespace getVariable ["COMSPEC_ATAK_LocalCfg", nil];
        if (isNil "_c") then { _c = call _load; };
        _c
    };
    case "reload": {
        call _load;
        ["apply"] call comspec_atak_native_fnc_localCfg;
        (missionNamespace getVariable ["COMSPEC_ATAK_LocalCfgState", ["default", "", false]]) params ["_src", "_err"];
        if (_err isNotEqualTo "") then {
            ["WARNING", format ["config.json illisible (%1) : copie du profil gardée", _err], 6, 40] call comspec_atak_native_fnc_notify;
        } else {
            ["INFO", ["Configuration locale rechargée (profil)", "Configuration locale rechargée depuis config.json"] select (_src isEqualTo "file"), 3, 20] call comspec_atak_native_fnc_notify;
        };
        true
    };
    case "save": {
        private _cfg = [["get"] call comspec_atak_native_fnc_localCfg] call _norm;
        missionNamespace setVariable ["COMSPEC_ATAK_LocalCfg", _cfg];
        profileNamespace setVariable ["COMSPEC_ATAK_LocalCfgSave", _cfg call _enc];
        saveProfileNamespace;
        private _json = [_cfg] call comspec_atak_native_fnc_json;
        private _ok = false;
        if (((["LocalBegin"] call comspec_atak_native_fnc_extensionCall) select [0, 3]) isEqualTo "OK|") then {
            // Découpe en caractères, pas en octets : un accent coupé en deux écrivait U+FFFD dans le fichier.
            forceUnicode 0;
            private _len = count _json;
            for "_i" from 0 to (_len - 1) step 2000 do {
                forceUnicode 0;
                private _part = _json select [_i, 2000];
                ["LocalAppend", [_part]] call comspec_atak_native_fnc_extensionCall;
            };
            _ok = ((["LocalCommit", ["config"]] call comspec_atak_native_fnc_extensionCall) select [0, 3]) isEqualTo "OK|";
        };
        private _st = missionNamespace getVariable ["COMSPEC_ATAK_LocalCfgState", ["default", "", false]];
        missionNamespace setVariable ["COMSPEC_ATAK_LocalCfgState", [["profile", "file"] select _ok, "", _ok || {_st param [2, false]}]];
        ["apply"] call comspec_atak_native_fnc_localCfg;
        _ok
    };
    case "apply": {
        if (!hasInterface || {isNull player}) exitWith { false };
        private _cfg = ["get"] call comspec_atak_native_fnc_localCfg;
        private _nums = (_cfg get "phone") get "numbers";
        if !(_nums isEqualType []) then { _nums = []; };
        // Numéro principal : seulement en ATAK local (avec Athena, le numéro vient du poste).
        // Les deux côtés d'un select sont calculés : indexer -1 ici plantait à chaque arrivée en mission.
        private _local = ([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on";
        private _pi = _nums findIf { _x isEqualType createHashMap && { _x get "primary" } };
        private _prim = "";
        if (_local && {_pi >= 0}) then { _prim = (_nums select _pi) get "number"; };
        if ((player getVariable ["COMSPEC_ATAK_LocalNum", ""]) isNotEqualTo _prim) then { player setVariable ["COMSPEC_ATAK_LocalNum", _prim, true]; };
        private _pub = (_nums select { _x isEqualType createHashMap && { _x get "shared" } }) apply { [_x get "number", _x get "type", _x get "label"] };
        if ((player getVariable ["COMSPEC_ATAK_Numbers", []]) isNotEqualTo _pub) then { player setVariable ["COMSPEC_ATAK_Numbers", _pub, true]; };
        true
    };
    case "status": {
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LocalCfg" }) then { call _load; };
        (missionNamespace getVariable ["COMSPEC_ATAK_LocalCfgState", ["default", "", false]]) params ["_src", "_err", "_dll"];
        private _dir = "";
        if (_dll) then {
            private _r = ["LocalFolder"] call comspec_atak_native_fnc_extensionCall;
            if ((_r select [0, 3]) isEqualTo "OK|") then { _dir = _r select [3]; };
        };
        [_src, _dir, _err]
    };
    case "open": {
        private _r = ["LocalOpenFolder"] call comspec_atak_native_fnc_extensionCall;
        if ((_r select [0, 3]) isNotEqualTo "OK|") then { ["WARNING", "Dossier indisponible : DLL native à mettre à jour (COMSPEC Link)", 4, 30] call comspec_atak_native_fnc_notify; };
        true
    };
    case "types": { _types };
    case "type": {
        private _i = _types findIf { (_x select 0) isEqualTo _arg };
        _types select (_i max 0)
    };
    case "generate": {
        _arg params [["_type", "mobile"], ["_plan", "FR"]];
        private _d = { private _o = ""; for "_i" from 1 to _this do { _o = _o + str (floor random 10); }; _o };
        private _nz = { str (2 + floor random 8) };
        switch (_type) do {
            case "satellite": { format ["+8816 %1 %2", 4 call _d, 4 call _d] };
            case "radio_ip": { format ["10.%1.%2.%3", 1 + floor random 250, floor random 255, 2 + floor random 250] };
            case "secure": { format ["DSN 3%1-%2-%3", 2 call _d, 3 call _d, 4 call _d] };
            case "pager": { format ["BIP %1", 6 call _d] };
            case "emergency": { ["112", "911"] select (_plan isEqualTo "US") };
            case "landline": {
                switch (_plan) do {
                    case "US": { format ["(%1%2) %3%4-%5", call _nz, 2 call _d, call _nz, 2 call _d, 4 call _d] };
                    case "UK": { format ["020 %1 %2", 4 call _d, 4 call _d] };
                    case "DE": { format ["030 %1", 8 call _d] };
                    case "BE": { format ["02 %1 %2 %3", 3 call _d, 2 call _d, 2 call _d] };
                    case "RU": { format ["+7 495 %1-%2-%3", 3 call _d, 2 call _d, 2 call _d] };
                    case "ES": { format ["91%1 %2 %3", 1 call _d, 3 call _d, 3 call _d] };
                    default { format ["0%1 %2 %3 %4 %5", 1 + floor random 5, 2 call _d, 2 call _d, 2 call _d, 2 call _d] };
                }
            };
            default {
                switch (_plan) do {
                    case "US": { format ["(%1%2) %3%4-%5", call _nz, 2 call _d, call _nz, 2 call _d, 4 call _d] };
                    case "UK": { format ["07%1 %2", 3 call _d, 6 call _d] };
                    case "DE": { format ["015%1 %2", 1 call _d, 8 call _d] };
                    case "BE": { format ["04%1%2 %3 %4 %5", 7 + floor random 3, 1 call _d, 2 call _d, 2 call _d, 2 call _d] };
                    case "RU": { format ["+7 9%1 %2-%3-%4", 2 call _d, 3 call _d, 2 call _d, 2 call _d] };
                    case "ES": { format ["6%1 %2 %3", 2 call _d, 3 call _d, 3 call _d] };
                    default { format ["0%1 %2 %3 %4 %5", 6 + floor random 2, 2 call _d, 2 call _d, 2 call _d, 2 call _d] };
                }
            };
        }
    };
    default { false };
}
