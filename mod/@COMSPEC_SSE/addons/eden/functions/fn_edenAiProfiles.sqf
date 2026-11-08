/*
    Attribut Eden « Profil Athena » : liste des profils IA sans taper de référence.
    ["load", [combo, valeur]] · ["save", combo] -> valeur · ["fetch"] -> lignes [id, réf., libellé, nom, camp, catégorie]
    Liste : Athena si la connexion du menu COMSPEC SSE est ouverte (SubmitIdCheck, kind ai_profiles, aucune nouvelle DLL), gardée
    dans profileNamespace ; sinon la dernière liste vue sur ce poste. Rien : seul « Générer » est proposé, avec un mot clair.
*/
params [["_op", "load"], ["_a", []]];
if (_op isEqualTo "fetch") exitWith {
    private _cache = profileNamespace getVariable ["comspec_sse_aiProfilesCache", []];
    if (diag_tickTime < (uiNamespace getVariable ["comspec_sse_aiProfilesRetry", -1])) exitWith { _cache };
    // Session Athena mémorisée : reprise une fois (Connexion Athena du menu COMSPEC SSE), comme la bibliothèque de l'éditeur.
    if (!(uiNamespace getVariable ["comspec_sse_editorRestored", false]) && {!isNil "comspec_sse_fnc_athenaEden"}) then {
        uiNamespace setVariable ["comspec_sse_editorRestored", true];
        ["restore"] call comspec_sse_fnc_athenaEden;
    };
    private _map = missionNamespace getVariable ["COMSPEC_MapId", 1];
    private _raw = "COMSPECExtension" callExtension ["SubmitIdCheck", [format ['{"kind":"ai_profiles","map":%1}', _map]]];
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if (!(_raw isEqualType "") || {(_raw select [0, 3]) isNotEqualTo "OK|"}) exitWith {
        uiNamespace setVariable ["comspec_sse_aiProfilesRetry", diag_tickTime + 60];
        _cache
    };
    private _p = _raw splitString "|";
    private _rows = parseSimpleArray (_p param [2, "[]"]);
    if (_rows isEqualType []) then {
        profileNamespace setVariable ["comspec_sse_aiProfilesCache", _rows];
        saveProfileNamespace;
        _cache = _rows;
    };
    _cache
};
disableSerialization;
if (_op isEqualTo "save") exitWith {
    private _c = _a;
    private _i = lbCurSel _c;
    if (_i < 0) exitWith { "" };
    _c lbData _i
};
_a params ["_c", ["_value", ""]];
lbClear _c;
private _rows = ["fetch"] call comspec_sse_fnc_edenAiProfiles;
private _first = _c lbAdd ((["Générer une identité", "Générer (aucune liste Athena sur ce poste)"] select (_rows isEqualTo [])) call comspec_sse_fnc_tr);
_c lbSetData [_first, ""];
private _sel = _first;
{
    _x params ["_id", "_ref", "_label", "_name"];
    private _i = _c lbAdd format ["%1 · %2%3", _ref, _label, ["", format [" (%1)", _name]] select (_name isNotEqualTo "")];
    _c lbSetData [_i, str _id];
    if ((str _id) isEqualTo _value) then { _sel = _i; };
} forEach _rows;
// Valeur enregistrée dans la mission, profil absent de la liste : gardée, jamais perdue en silence.
if (_value isNotEqualTo "" && {_sel isEqualTo _first}) then {
    private _i = _c lbAdd format ["PRF-%1 (hors liste)", _value];
    _c lbSetData [_i, _value];
    _sel = _i;
};
_c lbSetCurSel _sel;
