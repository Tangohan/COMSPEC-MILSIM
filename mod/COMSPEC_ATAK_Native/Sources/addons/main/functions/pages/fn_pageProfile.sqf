/*
    App Profil : fiche opérateur Athena (photo, indicatif, grade, unité ORBAT, fonction),
    certificat du terminal (état, échéance, renouvellement), état du téléphone (allumé, écran, détruit)
    avec réparations Overwatch, batterie (autonomie, recharge, rechange) et relais le plus proche.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _v = { params ["_k", ["_d", "—"]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { [_r, _d] select (_r isEqualTo "") } else { str _r } };
private _rows = [];
private _unitFull = missionNamespace getVariable ["comspec_profile_unit", ""];
if !(_unitFull isEqualType "") then { _unitFull = ""; };
private _unitShort = [player, true] call comspec_atak_native_fnc_unitGroup;
if (_unitFull isEqualTo "") then { _unitShort = ""; };

// Opérateur
private _logo = [player] call comspec_atak_native_fnc_avatarPath;
if (_logo isEqualTo "") then { _logo = "\z\comspec_atak_native\addons\main\data\logo_atak.paa"; };
_rows pushBack ["hero", _logo, format ["<t size='1.3' font='RobotoCondensedBold' color='#5cc76b'>%1</t>  <t size='1.05'>%2</t><br/><t color='#c9d4cf'>%3 · %4</t><br/><t size='0.85' color='#8a9a93'>%5</t>",
    ["comspec_profile_callsign", [player, true] call comspec_atak_native_fnc_unitCallsign] call _v, ["comspec_profile_name", name player] call _v,
    ["comspec_profile_grade"] call _v, [["comspec_profile_role", roleDescription player] call _v, [_unitFull]] call comspec_atak_native_fnc_abbrev, ["comspec_tenant_name", "Hors ligne"] call _v]];
// Unité et fonction abrégées (règle Athena) ; le nom complet suit quand il diffère.
_rows pushBack ["info", "Unité (ORBAT)", [_unitShort, "—"] select (_unitShort isEqualTo "")];
if (_unitShort isNotEqualTo _unitFull) then { _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", _unitFull]]; };
private _fn = ["comspec_profile_function"] call _v;
_rows pushBack ["info", "Fonction", [[_fn, [_unitFull]] call comspec_atak_native_fnc_abbrev, _fn] select (_fn isEqualTo "—")];
private _grp = [player, true] call comspec_atak_native_fnc_unitGroup;
if (_grp isNotEqualTo _unitShort) then { _rows pushBack ["info", "Groupe", _grp]; };
_rows append [
    ["info", "Compte Steam lié", ["non", "oui"] select (missionNamespace getVariable ["COMSPEC_SteamLinked", false])]
];
// Identité du téléphone (roleplay, traçable en GÉOLOC par les autres camps selon la mission).
([player] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei", "_mac"];
_rows append [
    ["section", "Mon téléphone", (if (((missionNamespace getVariable ["comspec_profile_phone", []]) param [0, ""]) isEqualTo "") then { "Hors ligne : numéro provisoire, gardé dans Athena à la connexion" } else { format ["Attribué par Athena, modèle %1", (missionNamespace getVariable ["comspec_profile_phone", []]) param [3, "FR"]] })],
    ["info", "Numéro", _num], ["info", "IMEI", _imei], ["info", "Adresse MAC", _mac],
    ["buttons", [["COPIER LE NUMÉRO", compile format ["copyToClipboard %1; ['INFO', 'Numéro copié', 2, 10] call comspec_atak_native_fnc_notify;", str _num]]]]
];

// Qualifications Athena (référentiel de la communauté, attributions en cours de validité)
private _quals = (uiNamespace getVariable ["COMSPEC_ATAK_Quals", createHashMap]) getOrDefault [getPlayerUID player, []];
_rows pushBack ["section", "Qualifications", "Attributions en cours sur Athena"];
switch (true) do {
    case (!_bridge): { _rows pushBack ["text", "<t color='#8a9a93'>Liaison Athena (COMSPEC Link) requise.</t>"]; };
    case (_quals isEqualTo "olddll"): { _rows pushBack ["text", "<t color='#8a9a93'>Mettez à jour la DLL COMSPEC Link pour afficher les qualifications.</t>"]; };
    case ((count _quals) isEqualTo 0): { _rows pushBack ["text", "<t color='#8a9a93'>Aucune qualification en cours (ou profil en cours de chargement).</t>"]; };
    default {
        {
            _x params ["_name", "_lvl", "_state", "_exp"];
            private _st = createHashMapFromArray [["expiring_soon", ["expire bientôt", "#f2ab33"]], ["expired_grace", ["période de grâce", "#e5483a"]]] getOrDefault [_state, ["valide", "#5cc76b"]];
            _rows pushBack ["info", [_name, format ["%1 · %2", _name, _lvl]] select (_lvl isNotEqualTo ""),
                format ["<t color='%1'>%2</t>%3", _st select 1, _st select 0, ["", format ["  <t color='#8a9a93'>jusqu'au %1</t>", _exp]] select (_exp isNotEqualTo "")]];
        } forEach _quals;
    };
};

// Certificat du terminal
if (_bridge) then {
    private _st = ["COMSPEC_CertStatus", ""] call _v;
    private _exp = ["COMSPEC_CertExpires", ""] call _v;
    private _label = if (!isNil "comspec_overwatch_connect_fnc_certStatusLabel") then { [_st, _exp] call comspec_overwatch_connect_fnc_certStatusLabel } else { _st };
    private _comp = missionNamespace getVariable ["COMSPEC_CompromiseState", "none"];
    private _col = switch (true) do { case (_comp in ["captured", "compromised"]): { "#e5483a" }; case (_st in ["active", "issued"]): { "#5cc76b" }; default { "#f2ab33" }; };
    _rows append [
        ["section", "Certificat du terminal", "Chiffrement de la liaison avec le poste"],
        ["info", "État", format ["<t color='%1'>%2</t>", _col, [_label, "aucun"] select (_label isEqualTo "")]],
        ["info", "Référence", ["COMSPEC_CertRef"] call _v],
        ["info", "Échéance", [_exp, "—"] select (_exp isEqualTo "")],
        ["info", "Compromission", ["aucune", "<t color='#e5483a'>appareil capturé</t>", "<t color='#e5483a'>clé compromise</t>"] select ((["none", "captured", "compromised"] find _comp) max 0)]
    ];
    if (_st in ["", "missing", "expired", "revoked"]) then {
        _rows pushBack ["buttons", [["DEMANDER UN CERTIFICAT", { ["cert"] call comspec_atak_native_fnc_profileAction; }, true]]];
    };
    if (_comp in ["captured", "compromised"]) then { _rows pushBack ["text", "<t size='0.8' color='#e5483a'>Trafic illisible : seul le poste (Zeus ou web) peut régénérer la clé.</t>"]; };
};

// État du téléphone (réalisme Overwatch)
private _fn = if (_bridge && {!isNil "comspec_overwatch_connect_fnc_isAtakFunctional"}) then { [] call comspec_overwatch_connect_fnc_isAtakFunctional } else { createHashMap };
if (_fn isEqualType createHashMap && {(count _fn) > 0}) then {
    private _ok = { params ["_b", "_t", "_f"]; format ["<t color='%1'>%2</t>", ["#e5483a", "#5cc76b"] select _b, [_f, _t] select _b] };
    private _destroyed = _fn getOrDefault ["device_destroyed", false];
    _rows append [
        ["section", "État du téléphone", format ["Réalisme niveau %1", missionNamespace getVariable ["comspec_overwatch_atak_realism", 0]]],
        ["info", "Alimentation", [_fn getOrDefault ["powered_on", true], "allumé", "éteint"] call _ok],
        ["info", "Écran", [_fn getOrDefault ["screen_ok", true], "intact", "cassé"] call _ok],
        ["info", "Liaison", [_fn getOrDefault ["connection_ok", true], "ok", "coupée"] call _ok],
        ["info", "Appareil", [!_destroyed, "fonctionnel", "détruit"] call _ok]
    ];
    if !(_destroyed) then {
        _rows pushBack ["buttons", [
            ["RALLUMER", { ["repair", "power"] call comspec_atak_native_fnc_profileAction; }],
            ["RÉPARER L'ÉCRAN", { ["repair", "screen"] call comspec_atak_native_fnc_profileAction; }],
            ["RÉVISION COMPLÈTE", { ["repair", "full"] call comspec_atak_native_fnc_profileAction; }]
        ]];
        _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Écran et révision : kit de réparation ATAK (consommé, 12 s) ou caisse à outils (20 s).</t>"];
    };
};

// Batterie
private _bat = [] call comspec_atak_native_fnc_battery;
private _charging = (vehicle player) isNotEqualTo player;
private _spares = { (toLower _x) in (((missionNamespace getVariable ["comspec_atak_native_battery_items", "ACE_UAVBattery"]) splitString ", ") apply { toLower _x }) } count (items player);
_rows append [
    ["section", "Batterie", ["Simulation coupée (réglages)", "Recharge à bord d'un véhicule"] select (profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true])],
    ["info", "Niveau", format ["<t color='%1'>%2 %%</t>", ["#e5483a", "#f2ab33", "#5cc76b"] select ((floor (_bat / 25)) min 2), round _bat]],
    ["info", "Autonomie", [format ["≈ %1 h %2 min", floor (_bat * 3 / 60), round ((_bat * 3) mod 60)], "en charge"] select _charging],
    ["info", "Batteries de rechange", str _spares],
    ["buttons", [["CHANGER LA BATTERIE", { ["battery"] call comspec_atak_native_fnc_profileAction; }, _spares > 0]]]
];

// Relais
if (_bridge && {!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay"}) then {
    private _r = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
    _rows pushBack ["section", "Relais le plus proche", ""];
    if (_r isEqualType createHashMap && {(count _r) > 0}) then {
        _rows append [
            ["info", "Relais", format ["%1 · %2", _r getOrDefault ["name", "?"], _r getOrDefault ["grid", ""]]],
            ["info", "Distance", format ["%1 m / portée %2 m %3", round (_r getOrDefault ["dist", 0]), round (_r getOrDefault ["range", 0]), ["<t color='#e5483a'>hors portée</t>", "<t color='#5cc76b'>couvert</t>"] select (_r getOrDefault ["in_range", false])]],
            ["info", "Charge", format ["%1 / %2 postes · %3 %%", _r getOrDefault ["used", 0], _r getOrDefault ["slots", 0], _r getOrDefault ["reliability_pct", 100]]]
        ];
    } else { _rows pushBack ["text", "<t color='#8a9a93'>Aucun relais déployé.</t>"]; };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
