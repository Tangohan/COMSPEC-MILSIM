/*
    Si le compte Athena est lié, récupère l’indicatif plateforme (GetPlayerAvatarInfo)
    et l’applique localement quand le profil jeu est encore vide, ou pour rafraîchir
    après liaison. Ne force pas d’écrasement si le joueur a déjà un indicatif local
    plus récent (sauf _force).
*/
params [["_force", false, [true]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _local = trim (missionNamespace getVariable ["COMSPEC_Callsign", ""]);
if (_local isEqualTo "") then {
    _local = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
};

private _info = [] call comspec_overwatch_connect_fnc_getPlayerAvatarInfo;
if (count _info < 2) exitWith { false };
private _displayName = trim (_info select 0);
private _callsign = trim (_info select 1);
private _atakId = if (count _info >= 5) then { trim (_info select 4) } else { "" };
private _mid = if (count _info >= 8) then { trim (_info select 7) } else { "" };
if (_mid != "") then {
    missionNamespace setVariable ["COMSPEC_MilitaryId", _mid, false];
    profileNamespace setVariable ["COMSPEC_MilitaryId", _mid];
};
if (_atakId != "") then {
    missionNamespace setVariable ["COMSPEC_AtakId", _atakId, false];
};
if (_callsign isEqualTo "") then { _callsign = _atakId; };
if (_callsign isEqualTo "") then { _callsign = _displayName; };
if (_callsign isEqualTo "") exitWith { false };

// L’ID BFT reste lié à l’indicatif même si on ne change pas l’indicatif local.
if (_mid != "") then {
    missionNamespace setVariable ["COMSPEC_BftId", _mid, false];
};

if (!_force && {!(_local isEqualTo "")} && {!(_local isEqualTo (name player))} && {!((toLower _local) in ["unknown", "inconnu", "operateur"])}) exitWith {
    // Indicatif local déjà choisi — on garde, Athena reste source pour le site
    false
};

[_callsign, true, "athena"] call comspec_overwatch_connect_fnc_setCallsign;
true
