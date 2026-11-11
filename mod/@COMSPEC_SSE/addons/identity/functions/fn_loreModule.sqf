/*
    Module « Synchroniser les IA avec Athena » (Eden et Zeus, modules_identity.hpp), lore de session ICD-SSE-LORE-001.
    Eden (exécuté sur le serveur, 10 s après le lancement) : réglages du module -> fn_loreServer « eden » :
      Portée (all | synced | sse), Code d'opération (vide : COMSPEC SSE › Identité › Opération Athena), Envoyer au
      démarrage, Récupérer au démarrage, Rafraîchissement automatique (s ; 0 : désactivé ; minimum 60).
    Zeus (poste du curateur) : fenêtre du module (comspec_sse_fnc_uiForm) avec l'état de la dernière synchro (date,
      créées / complétées / ignorées / verrou), l'action, la portée (toutes, groupe de l'unité sous le module, sélection
      Zeus, IA créées par un module SSE), le code d'opération et l'intervalle ; la demande part au serveur, qui répond
      dans cette même fenêtre (fn_loreClient).
    ["window", [texte du dernier retour, unité visée]] call comspec_sse_fnc_loreModule : rouvre la fenêtre (Zeus).
*/
if (_this isEqualType [] && {(_this param [0, ""]) isEqualTo "window"}) exitWith {
    if (!hasInterface) exitWith { false };
    (_this param [1, []]) params [["_last", "", [""]], ["_target", objNull, [objNull]]];
    if (isNull _target) then { _target = missionNamespace getVariable ["comspec_sse_loreTarget", objNull]; };
    missionNamespace setVariable ["comspec_sse_loreTarget", _target];
    private _esc = { [[[_this, "&", "&amp;"] call CBA_fnc_replace, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
    private _st = missionNamespace getVariable ["comspec_sse_loreStatus", []];
    _st params [["_sOp", ""], ["_pushAt", ""], ["_pushTxt", ""], ["_pullAt", ""], ["_pullTxt", ""], ["_autoS", 0], ["_meta", []]];
    private _never = localize "STR_COMSPEC_SSE_LORE_Never";
    private _lines = [
        format [localize "STR_COMSPEC_SSE_LORE_DescSession", [_sOp, _never] select (_sOp isEqualTo ""), [(_meta param [0, ""]) call _esc, "—"] select ((_meta param [0, ""]) isEqualTo "")],
        format [localize "STR_COMSPEC_SSE_LORE_DescPush", [_pushAt, _never] select (_pushAt isEqualTo ""), _pushTxt call _esc],
        format [localize "STR_COMSPEC_SSE_LORE_DescPull", [_pullAt, _never] select (_pullAt isEqualTo ""), _pullTxt call _esc],
        format [localize "STR_COMSPEC_SSE_LORE_DescAuto", [str _autoS, localize "STR_COMSPEC_SSE_LORE_Off"] select (_autoS <= 0)]
    ];
    if (_last isNotEqualTo "") then { _lines pushBack format ["<t color='#7fd08a'>%1</t>", _last call _esc]; };
    private _defOp = [_sOp, missionNamespace getVariable ["comspec_sse_id_opKey", ""]] select (_sOp isEqualTo "");
    [
        localize "STR_COMSPEC_SSE_LORE_Module",
        [
            ["COMBO", localize "STR_COMSPEC_SSE_LORE_Action", localize "STR_COMSPEC_SSE_LORE_ActionTip",
                [["both", "push", "pull", "auto", "status"], [localize "STR_COMSPEC_SSE_LORE_ActBoth", localize "STR_COMSPEC_SSE_LORE_ActPush", localize "STR_COMSPEC_SSE_LORE_ActPull", localize "STR_COMSPEC_SSE_LORE_ActAuto", localize "STR_COMSPEC_SSE_LORE_ActStatus"], 0]],
            ["COMBO", localize "STR_COMSPEC_SSE_LORE_Scope", localize "STR_COMSPEC_SSE_LORE_ScopeZeusTip",
                [["all", "group", "selection", "sse"], [localize "STR_COMSPEC_SSE_LORE_ScopeAll", localize "STR_COMSPEC_SSE_LORE_ScopeGroup", localize "STR_COMSPEC_SSE_LORE_ScopeSel", localize "STR_COMSPEC_SSE_LORE_ScopeSse"], [0, 1] select (!isNull _target)]],
            ["EDIT", localize "STR_COMSPEC_SSE_LORE_Op", localize "STR_COMSPEC_SSE_LORE_OpTip", _defOp],
            ["NUMBER", localize "STR_COMSPEC_SSE_LORE_Refresh", localize "STR_COMSPEC_SSE_LORE_RefreshTip", [_autoS max 0, 0, 3600]]
        ],
        {
            params ["_v", "_args"];
            _v params ["_act", "_scope", "_op", "_sec"];
            _args params ["_target"];
            private _lic = isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]};
            if (!_lic) exitWith { [localize "STR_COMSPEC_SSE_LORE_NoLicence", "error"] call comspec_sse_fnc_zeusNotify; };
            private _units = switch (_scope) do {
                case "group": { [[], [_target]] select (!isNull _target) };
                case "selection": { ([] call comspec_sse_fnc_curatorSelectedObjects) select { _x isKindOf "CAManBase" } };
                default { [] };
            };
            if (_scope in ["group", "selection"] && {_units isEqualTo []}) exitWith { [localize "STR_COMSPEC_SSE_LORE_NoTarget", "warn"] call comspec_sse_fnc_zeusNotify; };
            if !(_sec isEqualType 0) then { _sec = 0; };
            if (_sec > 0 && {_sec < 60}) then { _sec = 60; };
            private _spec = createHashMapFromArray [["op", trim _op], ["scope", _scope], ["units", _units select [0, 500]], ["interval", round _sec]];
            [_act, [player, _spec]] remoteExecCall ["comspec_sse_fnc_loreServer", 2];
            [localize "STR_COMSPEC_SSE_LORE_Sent", "info"] call comspec_sse_fnc_zeusNotify;
        },
        [_target],
        _lines joinString "<br/>"
    ] call comspec_sse_fnc_uiForm
};

private _m = _this call comspec_sse_fnc_moduleArgs;
if (_m isEqualTo []) exitWith { true };
_m params ["_logic", "_units", "_activated"];
private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

if (_ctx get "zeus") exitWith {
    private _t = _ctx get "target";
    ["window", ["", [objNull, _t] select (_t isKindOf "CAManBase")]] call comspec_sse_fnc_loreModule;
    deleteVehicle _logic;
    true
};
if (!isServer) exitWith { true };
private _num = { if (_this isEqualType 0) then { _this } else { parseNumber str _this } };
private _sec = (_logic getVariable ["Refresh", 0]) call _num;
if (_sec > 0 && {_sec < 60}) then { _sec = 60; };
private _spec = createHashMapFromArray [
    ["op", trim ((_logic getVariable ["OpKey", ""]) call { if (_this isEqualType "") then { _this } else { "" } })],
    ["scope", _logic getVariable ["Scope", "all"]],
    ["units", (_ctx get "targets") select { _x isKindOf "CAManBase" }],
    ["push", (_logic getVariable ["PushStart", true]) in [true, 1]],
    ["pull", (_logic getVariable ["PullStart", true]) in [true, 1]],
    ["interval", round _sec]
];
// Portée « synchronisées » sans unité synchronisée : rien plutôt que toute la mission.
if ((_spec get "scope") isEqualTo "synced" && {(_spec get "units") isEqualTo []}) then {
    diag_log text "[COMSPEC SSE] Lore Athena : module Eden en portée « IA synchronisées » sans unité synchronisée : aucun envoi.";
    _spec set ["push", false];
};
// Laisse aux IA le temps d'apparaître (identifiants, papiers, profils RP posés par leurs attributs).
[{ ["eden", [_this]] call comspec_sse_fnc_loreServer; }, _spec, 10] call CBA_fnc_waitAndExecute;
true
