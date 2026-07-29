/*
    Marque une fenêtre pendant laquelle les aperçus casque/drone automatiques
    ne doivent pas envoyer « la dernière capture » (évite doublon avec photo ATAK).
*/
if (!hasInterface) exitWith {};
private _sec = 35;
if (_this isEqualType 0) then { _sec = _this max 10; };
if (_this isEqualType []) then {
    if ((count _this) > 0 && {(_this select 0) isEqualType 0}) then {
        _sec = (_this select 0) max 10;
    };
};
missionNamespace setVariable [
    "COMSPEC_SuppressFeedSnapshotUntil",
    diag_tickTime + _sec,
    false
];
