/*
    ENROLL du SEEK II (touche A2) : enregistrement d'un inconnu sans ressaisie.

    SUJET prérempli depuis le document présenté au contrôle d'identité, sinon l'identité SSE de la
    cible (déjà posée à l'ouverture), toujours modifiable ; CONTEXTE prérempli (circonstance
    « contrôle », grille, heure, unité affichées) ; TERRAIN optionnel. Les onglets se cochent seuls
    (fn_ssePersonRefreshPanels) ; SIGN n'exige que le minimum (fn_seekState).

    [_op] call comspec_overwatch_connect_fnc_seekEnroll
      "start"    préremplit puis ouvre la page à compléter (SUJET, ou la biométrie si elle manque)
      "unknown"  bascule « identité inconnue » (on peut signer sans nom)
*/
params [["_op", "start", [""]]];
if (!hasInterface) exitWith {};
if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith { ["render"] call comspec_overwatch_connect_fnc_seekBattery; };

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

if (_op isEqualTo "unknown") exitWith {
    uiNamespace setVariable ["COMSPEC_SeekUnknown", !(uiNamespace getVariable ["COMSPEC_SeekUnknown", false])];
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
};

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _doc = ["doc", [_target]] call comspec_overwatch_connect_fnc_seekState;
private _set = {
    params ["_idc", "_val"];
    private _c = _disp displayCtrl _idc;
    if (!isNull _c && {_val isEqualType ""} && {_val isNotEqualTo ""}) then { _c ctrlSetText _val; };
};
// Le document présenté fait foi pour ce qu'il déclare (c'est ce que l'opérateur a sous les yeux).
if ((count _doc) > 0) then {
    [9501, _doc getOrDefault ["last", ""]] call _set;
    [9502, _doc getOrDefault ["first", ""]] call _set;
    [9507, _doc getOrDefault ["country", ""]] call _set;
    private _dob = _doc getOrDefault ["dob", ""];
    if (_dob isEqualType "" && {_dob isNotEqualTo ""}) then { uiNamespace setVariable ["COMSPEC_SeekDob", _dob]; };
};
if ((trim (ctrlText (_disp displayCtrl 9501))) isNotEqualTo "" || {(trim (ctrlText (_disp displayCtrl 9502))) isNotEqualTo ""}) then {
    uiNamespace setVariable ["COMSPEC_SeekUnknown", false];
};
// Circonstance par défaut : contrôle.
private _circ = _disp displayCtrl 9506;
if (!isNull _circ && {(lbCurSel _circ) < 0}) then { _circ lbSetCurSel 0; };
uiNamespace setVariable ["COMSPEC_SsePerson_IdentityCache", [
    trim (ctrlText (_disp displayCtrl 9501)), trim (ctrlText (_disp displayCtrl 9502)), trim (ctrlText (_disp displayCtrl 9503)),
    trim (ctrlText (_disp displayCtrl 9504)), trim (ctrlText (_disp displayCtrl 9507)), trim (ctrlText (_disp displayCtrl 9508))
]];
uiNamespace setVariable ["COMSPEC_SeekEnrolling", true];
[1] call comspec_overwatch_connect_fnc_sseTerminalPage;
