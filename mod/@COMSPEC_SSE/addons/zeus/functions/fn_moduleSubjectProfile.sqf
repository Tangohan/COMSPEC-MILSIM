/*
    Module Zeus/Eden : profil d'identité SSE.

    Pose sur un ou plusieurs sujets ce que le terminal SEEK trouvera en base :
    état civil de couverture, verdict de requête, référence de dossier.

    L'entrée est volontairement tolérante — BI passe [logic, units, activated],
    parfois un libellé d'événement en tête côté Eden. Le module ne doit pas mourir
    sur une forme d'appel : c'est ce qui produisait des « Type Chaîne, Objet attendu ».
*/
// Toutes les formes d'appel de BI, dont ["init", [logic, activé, posé par Zeus]] des modules Eden (is3DEN = 1).
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { false };
if (is3DEN) exitWith { true };

// Un seul écrivain : les variables sont diffusées, il ne faut pas que chaque
// client réapplique le profil de son côté.
// Client : le serveur applique et supprime la logique (un deleteVehicle ici l'effaçait avant lui).
if (!isServer && { isMultiplayer }) exitWith { true };

private _targets = [_logic, _units, 15] call comspec_sse_fnc_moduleTargets;
if (_targets isEqualTo []) exitWith {
    ["Module profil SSE : aucun sujet sous le module", "WARN"] call comspec_sse_fnc_log;
    deleteVehicle _logic;
    false
};

private _fnc_txt = {
    params ["_short", "_prefixed"];
    private _v = _logic getVariable [_prefixed, ""];
    if (!(_v isEqualType "") || {(trim str _v) isEqualTo ""}) then {
        _v = _logic getVariable [_short, ""];
    };
    if (!(_v isEqualType "")) then { _v = ""; };
    trim _v
};

private _preset = [_logic] call {
    params ["_l"];
    private _v = _l getVariable ["COMSPEC_SSE_Preset", ""];
    if (!(_v isEqualType "") || {(trim _v) isEqualTo ""}) then {
        _v = _l getVariable ["Preset", "auto"];
    };
    if (!(_v isEqualType "")) then { _v = "auto"; };
    private _p = toLower (trim _v);
    if (_p isEqualTo "") then { _p = "auto"; };
    _p
};

private _profile = [_preset] call comspec_sse_fnc_profilePreset;

// Les champs saisis à la main complètent le preset et le remplacent en cas de conflit.
{
    _x params ["_short", "_prefixed", "_key"];
    private _v = [_short, _prefixed] call _fnc_txt;
    if (_v isNotEqualTo "") then {
        _profile pushBack [_key, _v];
    };
} forEach [
    ["LastName", "COMSPEC_SSE_LastName", "last_name"],
    ["FirstName", "COMSPEC_SSE_FirstName", "first_name"],
    ["Alias", "COMSPEC_SSE_Alias", "alias"],
    ["Nationality", "COMSPEC_SSE_Nationality", "nationality"],
    ["Language", "COMSPEC_SSE_Language", "language"],
    ["RecordRef", "COMSPEC_SSE_RecordRef", "record_ref"]
];

private _seed = _logic getVariable ["COMSPEC_SSE_Seed", 0];
if (!(_seed isEqualType 0) || {_seed <= 0}) then {
    _seed = _logic getVariable ["Seed", 0];
};
if (_seed isEqualType 0 && { _seed > 0 }) then { _profile pushBack ["seed", _seed]; };

{
    [_x, _profile] call comspec_sse_fnc_applyProfile;
} forEach _targets;

[format ["Profil SSE « %1 » appliqué à %2 sujet(s)", _preset, count _targets], "INFO"] call comspec_sse_fnc_log;

deleteVehicle _logic;
true
