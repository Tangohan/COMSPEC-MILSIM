/*

    Identifiant stable du terminal ATAK pour cette installation (persisté en profil).

    Retourne une chaîne du type OW-XXXXXXXX (jamais nil / "<null>").

*/

if (!hasInterface) exitWith { "" };



private _isBadUid = {

    if (isNil "_this") exitWith { true };

    private _v = _this;

    if (!(_v isEqualType "")) exitWith { true };

    private _t = toLower (trim _v);

    if (_t isEqualTo "") exitWith { true };

    if (_t in ["null", "<null>", "<nul>", "nil", "any", "undefined"]) exitWith { true };

    if ((count _t) >= 6 && {(_t select [0, 6]) isEqualTo "<null"}) exitWith { true };

    false

};



private _uid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];

if (_uid call _isBadUid) then { _uid = ""; } else { _uid = trim _uid; };



if (_uid isEqualTo "") then {

    private _steam = getPlayerUID player;

    if (!(_steam isEqualType "")) then { _steam = ""; };

    private _seed = format ["%1|%2|%3|%4", _steam, profileName, worldName, diag_tickTime];

    private _hash = 0;

    {

        _hash = (_hash + _x) mod 1000000007;

    } forEach toArray _seed;

    _uid = format ["OW-%1-%2", _hash mod 100000000, floor (random 1e6)];

    profileNamespace setVariable ["comspec_overwatch_terminal_uid", _uid];

    saveProfileNamespace;

};



missionNamespace setVariable ["COMSPEC_TerminalUid", _uid, false];

_uid

