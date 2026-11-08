/*
    Charges du joueur : déclencheur réseau, ACE (clacker, minuterie, téléphone, homme mort, pièges), raccordées ATAK par Overwatch, charges vanilla.
    Renvoie [HashMap...] : obj, key, kind, label, cid, placedAt, fuse, remaining, grid, trig, det, range, clackFuse, armed.
      kind : "net" (déclencheur réseau) | "atak" (Overwatch) | "command" (clacker ACE) | "timer" | "cell" (téléphone ACE) |
             "dead" (homme mort) | "mine" (piège : plaque, fil, capteur) | "vanilla" (télécommandée sans ACE)
      det, range, clackFuse : clacker requis, sa portée, délai de la charge (clacker ACE) ; armed : charge réseau armée.
    Le type vient de ce qu'ACE sait vraiment (liste de clackers), pas d'une supposition : une mine à plaque n'est plus
    présentée comme télécommandée. Mise en cache une demi-seconde (la carte l'appelle à chaque image).
*/
params [["_force", false]];
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
if (!_force && {(diag_tickTime - (_cache select 0)) < 0.5}) exitWith { _cache select 1 };
private _seen = [];
private _out = [];
private _hasAce = isClass (configFile >> "CfgPatches" >> "ace_explosives");
private _clackers = (player getVariable ["ace_explosives_clackers", []]) select { (_x isEqualType []) && {(count _x) >= 5} };
private _cellObjs = [];
{ _cellObjs pushBack (_y param [0, objNull]); } forEach (missionNamespace getVariable ["ace_explosives_cellphoneIEDs", createHashMap]);
private _uid = getPlayerUID player;
private _add = {
    params ["_e"];
    if (isNull _e || {!alive _e} || {_e in _seen}) exitWith {};
    if (_e getVariable ["COMSPEC_detonateFired", false]) exitWith {};
    // Mine navale désamorcée : ACE la laisse en place, inactive.
    if (_e isKindOf "UnderwaterMine_Range_Ammo" && {!mineActive _e}) exitWith {};
    _seen pushBack _e;
    private _rec = _e getVariable ["COMSPEC_ATAK_NetCharge", []];
    private _ck = _clackers param [_clackers findIf { (_x select 0) isEqualTo _e }, []];
    private _ow = toLower (_e getVariable ["COMSPEC_triggerKind", ""]);
    private _placed = _e getVariable ["COMSPEC_ATAK_PlacedAt", -1];
    private _fuse = _e getVariable ["COMSPEC_ATAK_Fuse", _e getVariable ["COMSPEC_fuseSeconds", 0]];
    if !(_fuse isEqualType 0) then { _fuse = 0; };
    private _trig = "";
    private _det = "";
    private _range = 0;
    private _kind = switch (true) do {
        case (_rec isNotEqualTo []): { "net" };
        case (_ow isEqualTo "atak"): { "atak" };
        case (_ck isNotEqualTo []): {
            _trig = _ck select 4;
            _det = (getArray (configFile >> "ACE_Triggers" >> _trig >> "requires")) param [0, ""];
            _range = getNumber (configFile >> "CfgWeapons" >> _det >> "ace_explosives_range");
            ["command", "dead"] select ((toLower _trig) isEqualTo "deadmanswitch")
        };
        case (_fuse > 0 || {_ow isEqualTo "timer"}): { "timer" };
        case (_e in _cellObjs): { "cell" };
        case (!_hasAce && {_e isKindOf "PipeBombBase"}): { "vanilla" };
        default { "mine" };
    };
    private _mag = if (_rec isNotEqualTo []) then { _rec select 2 } else { _ck param [3, ""] };
    if (_mag isEqualTo "") then { _mag = getText (configOf _e >> "ace_explosives_magazine"); };
    if (_mag isEqualTo "") then { _mag = _e getVariable ["ace_explosives_class", ""]; };
    if !(_mag isEqualType "") then { _mag = ""; };
    private _label = if (_mag isNotEqualTo "") then { getText (configFile >> "CfgMagazines" >> _mag >> "displayName") } else { "" };
    if (_label isEqualTo "") then { _label = getText (configOf _e >> "displayName"); };
    if (_label isEqualTo "") then { _label = "Charge"; };
    private _rem = if (_kind isEqualTo "timer" && {_fuse > 0} && {_placed >= 0}) then { (_fuse - (time - _placed)) max 0 } else { -1 };
    private _cid = _e getVariable ["COMSPEC_chargeId", ""];
    if !(_cid isEqualType "") then { _cid = ""; };
    _out pushBack createHashMapFromArray [["obj", _e], ["key", _e call BIS_fnc_netId], ["kind", _kind], ["label", _label], ["cid", _cid],
        ["placedAt", _placed], ["fuse", _fuse], ["remaining", _rem], ["grid", [getPosASL _e, 8] call comspec_atak_native_fnc_gridRef],
        ["trig", _trig], ["det", _det], ["range", _range], ["armed", _rec param [4, false]], ["clackFuse", _ck param [1, 0]]];
};
// Ordre de pose (suivi par l'événement ace_explosives_place), puis ce qu'ACE, le serveur et Overwatch connaissent en plus.
{ [_x] call _add; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MyCharges", []]);
// Liste du serveur, reçue aussi par ceux qui rejoignent : les charges réseau sont retrouvées par l'UID.
{ if (!isNull _x && {((_x getVariable ["COMSPEC_ATAK_NetCharge", []]) param [0, ""]) isEqualTo _uid}) then { [_x] call _add; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_NetCharges", []]);
{ [_x select 0] call _add; } forEach _clackers;
if (!isNil "comspec_overwatch_connect_fnc_chargeOwnedAtak") then { { [_x select 1] call _add; } forEach ([player] call comspec_overwatch_connect_fnc_chargeOwnedAtak); };
{ [_x] call _add; } forEach (getAllOwnedMines player);
uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [diag_tickTime, _out]];
_out
