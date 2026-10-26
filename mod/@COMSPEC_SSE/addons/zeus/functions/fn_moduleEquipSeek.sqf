/*
    Module Zeus/Eden : doter en terminal SEEK.

    Sans terminal dans le sac, aucune fiche n'est ouvrable : c'est le module qui
    débloque le module SSE en cours de partie, quand une équipe se retrouve à devoir
    exploiter un objectif qui n'était pas prévu pour.

    Cible : les unités attachées ou synchronisées, sinon les personnes proches. Une
    portée volontairement courte (25 m par défaut, attribut Rayon), pour ne pas distribuer le terminal à toute
    une compagnie d'un clic — et notamment pas aux PNJ qu'on est en train de fouiller.
*/
// Toutes les formes d'appel de BI, dont ["init", [logic, activé, posé par Zeus]] des modules Eden (is3DEN = 1).
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { false };

// Client : le serveur applique et supprime la logique (un deleteVehicle ici l'effaçait avant lui).
if (!isServer && { isMultiplayer }) exitWith { true };

private _radius = _logic getVariable ["Radius", 25];
if (_radius isEqualType "") then { _radius = parseNumber _radius; };
if (!(_radius isEqualType 0) || { _radius <= 0 }) then { _radius = 25; };
_radius = (_radius max 1) min 500;

private _targets = [_logic, _units, _radius] call comspec_sse_fnc_moduleTargets;

// Seuls les joueurs sont dotés : donner le terminal à un PNJ ne sert à rien et
// le rend récupérable sur son corps, ce qui n'est pas l'effet recherché.
private _players = _targets select { isPlayer _x };

// Unités jouables encore sans joueur (slots JIP) : dotées quand un joueur les prend (serveur, MP).
private _pending = _targets select { !isPlayer _x && {_x in playableUnits} };
if (isMultiplayer && {_pending isNotEqualTo []}) then {
    if (isNil "comspec_sse_seekPending") then { comspec_sse_seekPending = []; };
    { comspec_sse_seekPending pushBackUnique _x; } forEach _pending;
    if (isNil "comspec_sse_seekJipEh") then {
        comspec_sse_seekJipEh = addMissionEventHandler ["OnUserSelectedPlayer", {
            params ["", ["_unit", objNull]];
            if (isNull _unit || {!(_unit in comspec_sse_seekPending)}) exitWith {};
            comspec_sse_seekPending = comspec_sse_seekPending - [_unit, objNull];
            // Attend que l'unité soit au joueur (locale chez lui) avant de remettre le terminal.
            [
                { isPlayer _this && {!local _this || {player isEqualTo _this}} },
                { [_this] remoteExecCall ["comspec_sse_fnc_giveSeek", _this]; },
                _unit,
                60
            ] call CBA_fnc_waitUntilAndExecute;
        }];
    };
    [format ["Terminal SEEK : %1 unité(s) jouable(s) en attente de joueur", count _pending], "INFO"] call comspec_sse_fnc_log;
};

if (_players isEqualTo []) exitWith {
    ["Module terminal SEEK : aucun joueur sous le module", "WARN"] call comspec_sse_fnc_log;
    deleteVehicle _logic;
    false
};

{
    // L'ajout doit se faire là où l'unité est locale, sinon l'inventaire ne suit pas.
    [_x] remoteExecCall ["comspec_sse_fnc_giveSeek", _x];
} forEach _players;

[format ["Terminal SEEK distribué à %1 joueur(s)", count _players], "INFO"] call comspec_sse_fnc_log;

deleteVehicle _logic;
true
