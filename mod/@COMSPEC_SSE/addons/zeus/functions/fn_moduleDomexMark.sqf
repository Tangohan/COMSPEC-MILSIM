/*
    Module « Intelligence numérique (DOMEX) » : pose le contrat d'intelligence
    numérique sur un objet (ordinateur, téléphone, radio…). Même schéma qu'Eden.
    Zeus : formulaire complet à la pose. Eden : attributs du module.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = (_ctx get "targets") select { !(_x isKindOf "CAManBase") };
if (_targets isEqualTo []) exitWith {
    ["Intelligence numérique : posez le module sur un objet (ordinateur, téléphone, radio…), pas sur une personne.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _keys = ["node_id", "device_type", "owner", "organization", "network", "security", "profile", "duration", "access_remote", "packet_type", "packet_text", "packet_quality", "packet_entities"];

private _apply = {
    params ["_targets", "_keys", "_values"];
    private _overrides = createHashMapFromArray [["enabled", true]];
    { _overrides set [_x, _values select _forEachIndex]; } forEach _keys;
    _overrides set ["duration", str (_overrides getOrDefault ["duration", 180])];
    { [_x, _overrides] call comspec_sse_fnc_domexApplyObject; } forEach _targets;
    [_targets, 1] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["Intelligence numérique posée sur %1 objet(s).", count _targets]] call comspec_sse_fnc_zeusNotify;
};

private _cur = [
    _logic getVariable ["NodeId", ""],
    _logic getVariable ["DeviceType", "ordinateur"],
    _logic getVariable ["Owner", ""],
    _logic getVariable ["Organization", ""],
    _logic getVariable ["Network", ""],
    _logic getVariable ["Security", "moyenne"],
    _logic getVariable ["Profile", "generique"],
    _logic getVariable ["Duration", 180],
    _logic getVariable ["AccessRemote", false],
    _logic getVariable ["PacketType", ""],
    _logic getVariable ["PacketText", ""],
    _logic getVariable ["PacketQuality", "complet"],
    _logic getVariable ["PacketEntities", ""]
];
if ((_cur select 7) isEqualType "") then { _cur set [7, parseNumber (_cur select 7)]; };

if (_ctx get "zeus") then {
    [
        "Intelligence numérique (DOMEX)",
        [
            ["EDIT", "Identifiant", "Ex. PC-KESTREL-04. Vide = identifiant automatique.", _cur select 0],
            ["COMBO", "Type de support", "Nature du support numérique.", ["device", _cur select 1] call comspec_sse_fnc_zeusComboData],
            ["EDIT", "Propriétaire apparent", "Ce que le support laisse croire, pas forcément la vérité.", _cur select 2],
            ["EDIT", "Organisation", "Groupe ou structure fictive.", _cur select 3],
            ["EDIT", "Réseau fictif", "Nom du réseau / SSID / canal.", _cur select 4],
            ["COMBO", "Sécurité scénarisée", "Plus élevée = accès plus long.", ["security", _cur select 5] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Profil de contenu", "Oriente les contenus proposés.", ["domexProfile", _cur select 6] call comspec_sse_fnc_zeusComboData],
            ["SLIDER", "Durée d'accès (s)", "Temps d'exploitation scénarisé.", [10, 900, _cur select 7, 0]],
            ["CHECK", "Accès distant scénarisé", "Le laboratoire peut poursuivre l'exploitation à distance.", _cur select 8],
            ["COMBO", "Paquet — type", "Premier renseignement contenu (optionnel).", ["packetType", _cur select 9] call comspec_sse_fnc_zeusComboData],
            ["EDIT", "Paquet — texte", "Renseignement scénarisé. Ce n'est pas une preuve.", _cur select 10],
            ["COMBO", "Paquet — qualité", "Un fragment ou un leurre devra être corroboré.", ["quality", _cur select 11] call comspec_sse_fnc_zeusComboData],
            ["EDIT", "Paquet — entités", "Format : Nom | type (lieu, personne, organisation…).", _cur select 12]
        ],
        {
            params ["_values", "_args"];
            _args params ["_targets", "_keys", "_apply"];
            [_targets, _keys, _values] call _apply;
        },
        [_targets, _keys, _apply],
        "Contrat scénarisé : aucun moteur technique, le laboratoire lit ce que vous saisissez."
    ] call comspec_sse_fnc_uiForm;
} else {
    [_targets, _keys, _cur] call _apply;
};

deleteVehicle _logic;
true
