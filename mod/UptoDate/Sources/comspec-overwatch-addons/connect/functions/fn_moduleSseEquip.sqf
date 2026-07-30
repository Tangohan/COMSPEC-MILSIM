/*
    Module Zeus/Eden : doter en terminal SEEK.

    Sans terminal dans le sac, aucune fiche n'est ouvrable : c'est le module qui
    débloque le module SSE en cours de partie, quand une équipe se retrouve à devoir
    exploiter un objectif qui n'était pas prévu pour.

    Cible : les unités attachées ou synchronisées, sinon les personnes proches. Une
    portée volontairement courte (25 m), pour ne pas distribuer le terminal à toute
    une compagnie d'un clic — et notamment pas aux PNJ qu'on est en train de fouiller.
*/
private _logic = objNull;
private _units = [];
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        _units = _this param [1, []];
        _activated = _this param [2, true];
    } else {
        if (_a0 isEqualType "" && { (_this param [1, objNull]) isEqualType objNull }) then {
            _logic = _this param [1, objNull];
            _units = _this param [2, []];
            _activated = _this param [3, true];
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_units isEqualType [])) then { _units = []; };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

if (!isServer && { isMultiplayer }) exitWith {
    deleteVehicle _logic;
    true
};

private _targets = [_logic, _units, 25] call comspec_overwatch_connect_fnc_sseModuleTargets;

// Seuls les joueurs sont dotés : donner le terminal à un PNJ ne sert à rien et
// le rend récupérable sur son corps, ce qui n'est pas l'effet recherché.
private _players = _targets select { isPlayer _x };
if (_players isEqualTo []) exitWith {
    ["WARN", "SSE", "Module terminal SEEK : aucun joueur sous le module"] call comspec_overwatch_connect_fnc_log;
    deleteVehicle _logic;
    false
};

{
    // L'ajout doit se faire là où l'unité est locale, sinon l'inventaire ne suit pas.
    [_x, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _x];
} forEach _players;

[
    "INFO",
    "SSE",
    format ["Terminal SEEK distribué à %1 joueur(s)", count _players]
] call comspec_overwatch_connect_fnc_log;

deleteVehicle _logic;
true
