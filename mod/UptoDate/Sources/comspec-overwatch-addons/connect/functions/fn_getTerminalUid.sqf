/*
    Identifiant stable du terminal ATAK pour cette installation (persisté en profil).
    Retourne une chaîne du type OW-XXXXXXXX.
*/
if (!hasInterface) exitWith { "" };

private _uid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
if (!(_uid isEqualType "")) then { _uid = ""; };
_uid = trim _uid;

if (_uid isEqualTo "") then {
    private _steam = getPlayerUID player;
    if (!(_steam isEqualType "")) then { _steam = ""; };
    private _seed = format ["%1|%2|%3", _steam, profileName, worldName];
    private _hash = 0;
    {
        _hash = (_hash + (toASCII _x)) mod 1000000007;
    } forEach toArray _seed;
    _uid = format ["OW-%1-%2", _hash mod 100000000, floor (random 1e6)];
    profileNamespace setVariable ["comspec_overwatch_terminal_uid", _uid];
    saveProfileNamespace;
};

missionNamespace setVariable ["COMSPEC_TerminalUid", _uid, false];
_uid
