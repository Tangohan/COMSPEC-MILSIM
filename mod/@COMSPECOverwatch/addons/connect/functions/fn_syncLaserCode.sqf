/*
    Sync current unit's laser code to overlay. Call from designator or periodically.
    Params: optional [laserCode]. If omitted, tries to read from unit variable or default 1688.
*/
params [["_laserCode", ""]];
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _unit = player;
private _callSign = missionNamespace getVariable ["COMSPEC_Callsign", name _unit];
if (_callSign isEqualTo "") then { _callSign = name _unit };
if (_laserCode isEqualTo "") then {
    _laserCode = _unit getVariable ["COMSPEC_LaserCode", "1688"];
};
if (_laserCode isEqualTo "") then { _laserCode = "1688" };

private _pos = getPos _unit;
private _posX = str (_pos select 0);
private _posY = str (_pos select 1);
"COMSPECExtension" callExtension ["SyncLaserCode", [_callSign, _laserCode, _posX, _posY, "ACTIVE"]];
