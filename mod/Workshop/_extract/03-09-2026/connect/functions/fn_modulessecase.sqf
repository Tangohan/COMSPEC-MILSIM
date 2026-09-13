/*
    Module Zeus/Eden : dossier SSE actif.

    Pose la référence de dossier pour tout l'élément. Toutes les fiches transmises
    ensuite s'y classent sans ressaisie — c'est le contexte de travail, pas une
    étiquette à recopier sur chaque fiche.

    Le module ne crée pas le dossier côté portail : il désigne un dossier ouvert par
    le poste de commandement. Une référence inconnue laisse simplement les fiches non
    classées, et l'automatisme de classement les rattrape s'il n'existe qu'un dossier
    ouvert. Inventer un dossier depuis le terrain reviendrait à créer des références
    fantômes que personne ne retrouve au débriefing.
*/
private _logic = objNull;
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        _activated = _this param [2, true];
    } else {
        if (_a0 isEqualType "" && { (_this param [1, objNull]) isEqualType objNull }) then {
            _logic = _this param [1, objNull];
            _activated = _this param [3, true];
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

if (!isServer && { isMultiplayer }) exitWith {
    deleteVehicle _logic;
    true
};

private _ref = _logic getVariable ["Reference", ""];
if (!(_ref isEqualType "")) then { _ref = ""; };
_ref = toUpper (trim _ref);

if (_ref isEqualTo "") exitWith {
    ["WARN", "SSE", "Module dossier SSE : référence vide, ignoré"] call comspec_overwatch_connect_fnc_log;
    deleteVehicle _logic;
    false
};

// Diffusion publique : un opérateur qui rejoint la partie après la pose du module
// récupère la référence sans manipulation.
missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", _ref, true];

["INFO", "SSE", format ["Dossier SSE actif imposé par module : %1", _ref]] call comspec_overwatch_connect_fnc_log;

deleteVehicle _logic;
true
