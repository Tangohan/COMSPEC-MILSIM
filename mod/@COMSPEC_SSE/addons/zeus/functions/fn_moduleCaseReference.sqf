/*
    Module « Référence de dossier SSE » (nouveau, V0.8).
    Fixe la référence de dossier utilisée pour les transmissions (Athena).
    Même rôle que « Dossier SSE actif » d'Overwatch, pour le pack autonome.
*/
// Aussi appelé par l'ancien module Eden « Dossier SSE actif » (is3DEN = 1) : ["init", [logic, activé, Zeus]].
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "_units", "_activated"];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_ref"];
    _ref = toUpper (trim _ref);
    // Diffusé à tous (et aux joueurs qui rejoignent ensuite).
    [_ref] remoteExecCall ["comspec_sse_fnc_setCaseReference", 2];
    if (_ref isEqualTo "") then {
        ["Référence de dossier effacée : les fiches partent sans dossier.", "warn"] call comspec_sse_fnc_zeusNotify;
    } else {
        [format ["Dossier SSE actif : %1", _ref]] call comspec_sse_fnc_zeusNotify;
    };
};

private _ref = _logic getVariable ["Reference", ""];
// Eden : le dossier choisi dans la liste du site l'emporte sur la référence saisie.
private _listed = _logic getVariable ["ReferenceList", ""];
if (_listed isEqualType "" && {(trim _listed) isNotEqualTo ""}) then { _ref = _listed; };
if (_ref isEqualTo "" && {!isNil "comspec_sse_fnc_getCaseReference"}) then {
    _ref = [] call comspec_sse_fnc_getCaseReference;
};

if (_ctx get "zeus") then {
    [
        "Référence de dossier SSE",
        [
            // Dossiers ouverts sur le site de la communauté ; la saisie reste possible pour un dossier pas encore créé.
            ["CATALOG", "Dossier du site", "Dossiers ouverts sur le site de la communauté.", ["cases", [], [], toUpper (trim _ref), "Aucun (référence saisie ci-dessous)"]],
            ["EDIT", "Autre référence", "Ex. SSE-2026-0007. Sert si aucun dossier n'est choisi dans la liste. Les deux vides : efface la référence active.", ""]
        ],
        {
            params ["_values", "_args"];
            _values params ["_listed", "_typed"];
            [[_typed, _listed] select (_listed isNotEqualTo "")] call (_args select 0);
        },
        [_apply],
        "Toutes les fiches transmises ensuite sont classées dans ce dossier. La liste vient du site de la communauté."
    ] call comspec_sse_fnc_uiForm;
} else {
    if ((trim _ref) isNotEqualTo "") then { [_ref] call _apply; };
};

deleteVehicle _logic;
true
