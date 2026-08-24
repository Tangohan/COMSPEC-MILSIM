/*
    Dialogue Zeus : activer le suivi téléphone et choisir les données visibles sur l’ATAK.
    Params: [_unit, _delay]
*/
params [
    ["_unit", objNull, [objNull]],
    ["_delay", 0],
    ["_retried", false]
];

if (!hasInterface) exitWith { false };
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
    ["Sélectionnez une personne (joueur ou IA).", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

if (_delay > 0 && {!isNil "CBA_fnc_waitAndExecute"}) exitWith {
    [
        { [_this, 0] call comspec_overwatch_connect_fnc_phoneTrackConfigure },
        _unit,
        _delay
    ] call CBA_fnc_waitAndExecute;
    true
};

private _currentlyOn = [_unit, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag;
private _who = name _unit;

private _apply = {
    params ["_unit", "_enabled", "_keys"];
    if (isNull _unit) exitWith {};
    if (!_enabled) then {
        [_unit, false] call comspec_overwatch_connect_fnc_setPhoneTrack;
        [format ["Géolocalisation coupée pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    } else {
        [_unit, true, _keys] call comspec_overwatch_connect_fnc_setPhoneTrack;
        _unit setVariable ["COMSPEC_PhoneTrackLastAt", -1e9, false];
        [_unit] call comspec_overwatch_connect_fnc_reportPhonePosition;
        private _n = count _keys;
        if (_n < 1) then {
            [format ["Téléphone localisé — %1 apparaît sans détail.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        } else {
            [format ["Téléphone localisé — %1, %2 donnée(s) visible(s).", name _unit, _n], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
    };
};

if (isNil "zen_dialog_fnc_create") exitWith {
    [_unit, !_currentlyOn, []] call _apply;
    if (!_currentlyOn) then {
        ["Les détails restent masqués. Zeus Enhanced permet de choisir ce qui apparaît.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    true
};

private _opened = [
    format ["Téléphone — %1", _who],
    [
        ["CHECKBOX", ["Activer le suivi", "Le contact apparaît sur la carte de commandement. Décochez pour couper."], true],
        ["CHECKBOX", ["Nom", "Le nom de la personne s’affiche sur la carte et dans la fiche."], [_unit, "identity"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Position (grille)", "Les coordonnées chiffrées s’affichent. Le point sur la carte reste visible dans tous les cas."], [_unit, "grid"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Altitude", "Altitude au-dessus du sol."], [_unit, "altitude"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Cap", "Direction vers laquelle la personne est tournée."], [_unit, "heading"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Heure du dernier signal", "Depuis quand le dernier point a été reçu."], [_unit, "updated"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Camp", "Allié, hostile, neutre ou inconnu — change aussi la couleur du symbole."], [_unit, "affiliation"] call comspec_overwatch_connect_fnc_phoneRevealHas],
        ["CHECKBOX", ["Dans un véhicule", "Indique si la personne est montée dans un véhicule."], [_unit, "vehicle"] call comspec_overwatch_connect_fnc_phoneRevealHas]
    ],
    {
        params ["_values", "_args"];
        _values params ["_enabled", "_identity", "_grid", "_altitude", "_heading", "_updated", "_affiliation", "_vehicle"];
        _args params ["_unit", "_apply"];
        private _keys = [];
        if (_identity) then { _keys pushBack "identity" };
        if (_grid) then { _keys pushBack "grid" };
        if (_altitude) then { _keys pushBack "altitude" };
        if (_heading) then { _keys pushBack "heading" };
        if (_updated) then { _keys pushBack "updated" };
        if (_affiliation) then { _keys pushBack "affiliation" };
        if (_vehicle) then { _keys pushBack "vehicle" };
        [_unit, _enabled, _keys] call _apply;
    },
    {},
    [_unit, _apply]
] call zen_dialog_fnc_create;

if (_opened isEqualTo false) exitWith {
    if (!_retried && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [
            { [_this, 0, true] call comspec_overwatch_connect_fnc_phoneTrackConfigure },
            _unit,
            0.2
        ] call CBA_fnc_waitAndExecute;
    };
    true
};

true
