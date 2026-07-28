/*
    Panneau Zeus : infos + actions ATAK sur un joueur.
    Params: [_unit]
*/
params [["_unit", objNull, [objNull]]];

if (!hasInterface) exitWith { false };
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
    ["Sélectionnez un fantassin / joueur.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};
if !(isPlayer _unit) exitWith {
    ["Cette unité n’est pas un joueur — effets ATAK indisponibles.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

[] remoteExecCall ["comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars", _unit];

private _steam = getPlayerUID _unit;
private _name = name _unit;
private _terminal = _unit getVariable ["COMSPEC_TerminalUid", ""];
private _atakId = _unit getVariable ["COMSPEC_AtakId", ""];
private _mid = _unit getVariable ["COMSPEC_MilitaryId", ""];
private _callsign = _unit getVariable ["COMSPEC_CallsignPublic", ""];
private _cert = _unit getVariable ["COMSPEC_CertStatus", ""];
private _link = _unit getVariable ["COMSPEC_LinkState", ""];
private _state = _unit getVariable ["COMSPEC_AtakState", createHashMap];

private _dash = {
    params ["_v"];
    if (!(_v isEqualType "") || {_v isEqualTo ""}) then { "—" } else { _v }
};

private _stateTxt = "inconnu";
if (_state isEqualType createHashMap) then {
    _stateTxt = call {
        if (_state getOrDefault ["device_destroyed", false]) exitWith { "détruit" };
        if (_state getOrDefault ["screen_destroyed", false]) exitWith { "écran cassé" };
        if (_state getOrDefault ["device_crashed", false]) exitWith { "planté" };
        if (!(_state getOrDefault ["powered_on", true])) exitWith { "éteint" };
        "OK"
    };
};

private _nl = toString [10];
private _info = format [
    "%1%2Indicatif : %3%2Steam : %4%2Terminal : %5%2ID ATAK : %6%2ID militaire : %7%2Certificat : %8%2Liaison : %9%2État appareil : %10",
    _name,
    _nl,
    [_callsign] call _dash,
    [_steam] call _dash,
    [_terminal] call _dash,
    [_atakId] call _dash,
    [_mid] call _dash,
    [_cert] call _dash,
    [_link] call _dash,
    _stateTxt
];

private _apply = {
    params ["_action", "_unit", ["_duration", 30]];
    if (isNull _unit || {!isPlayer _unit}) exitWith {};
    [_action, _duration] remoteExecCall ["comspec_overwatch_connect_fnc_applyZeusAtakEffect", _unit];
    private _label = switch (_action) do {
        case "power_off": { "ATAK éteint" };
        case "screen_break": { "écran endommagé" };
        case "device_destroy": { "appareil hors service" };
        case "crash": { "crash déclenché" };
        case "jam": { "brouillage lancé" };
        case "capture": { "appareil capturé" };
        case "compromise": { "appareil compromis" };
        case "clear_compromise": { "contrôle rétabli" };
        case "repair";
        case "clear": { "ATAK rétabli" };
        default { _action };
    };
    [format ["Zeus → %1 : %2", name _unit, _label], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};

if (!isNil "zen_dialog_fnc_create") exitWith {
    // Format ZEN : COMBO = [values, labels, defaultIndex]
    // EDIT:MULTI = [defaultText, sanitizer CODE, height NUMBER]
    private _actionValues = [
        "",
        "power_off",
        "screen_break",
        "device_destroy",
        "crash",
        "jam",
        "capture",
        "compromise",
        "clear_compromise",
        "repair"
    ];
    private _actionLabels = [
        "Infos seulement (aucune action)",
        "Éteindre l’ATAK",
        "Casser l’écran",
        "Détruire l’appareil",
        "Crash / gel",
        "Brouiller la liaison",
        "Capturer l’appareil (illisible)",
        "Compromettre l’appareil",
        "Lever capture / compromission",
        "Réparer / rétablir"
    ];
    [
        format ["ATAK — %1", _name],
        [
            ["EDIT:MULTI", ["Identifiants", "Steam, terminal, ID ATAK, certificat, état…"], [_info, {}, 7]],
            ["COMBO", ["Action", "Effet appliqué immédiatement sur le joueur."], [_actionValues, _actionLabels, 0]],
            ["SLIDER", ["Durée crash / brouillage (s)", "Pour crash et brouillage uniquement."], [5, 300, 45, 0]]
        ],
        {
            params ["_values", "_args"];
            _values params ["", "_action", "_duration"];
            _args params ["_unit", "_apply"];
            if (!(_action isEqualType "") || {_action isEqualTo ""}) exitWith {};
            [_action, _unit, _duration] call _apply;
        },
        {},
        [_unit, _apply]
    ] call zen_dialog_fnc_create;
    true
};

// Repli sans ZEN
copyToClipboard _info;
hint parseText format [
    "<t size='1.05' color='#e8f4f0'>ATAK — %1</t><br/><t align='left' size='0.85'>%2</t><br/><t size='0.75' color='#8aa0b4'>Identifiants copiés.</t>",
    _name,
    [_info, toString [10], "<br/>"] call BIS_fnc_replaceString
];
systemChat format ["[COMSPEC ATAK] %1 | Steam %2 | Terminal %3 | ATAK %4 | État %5", _name, _steam, _terminal, _atakId, _stateTxt];

private _okJam = ["Brouiller ce joueur 45 s ? (Annuler = infos seulement)", "COMSPEC ATAK", true, true, findDisplay 312] call BIS_fnc_guiMessage;
if (_okJam) then {
    ["jam", _unit, 45] call _apply;
};

true
