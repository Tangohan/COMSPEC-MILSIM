/*
    Plus de raccourcis COMSPEC sur le bureau ATAK.
    Connexion, messagerie, ordres et le reste restent dans le menu d’applications
    et dans le menu ACE.
*/
if (!hasInterface) exitWith {};

private _pfh = missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutPFH", -1];
if (_pfh isEqualType 0 && {_pfh >= 0}) then {
    [_pfh] call CBA_fnc_removePerFrameHandler;
};
missionNamespace setVariable ["COMSPEC_Athena_desktopShortcutPFH", -1];

private _showPfh = missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutShowPFH", -1];
if (_showPfh isEqualType 0 && {_showPfh >= 0}) then {
    [_showPfh] call CBA_fnc_removePerFrameHandler;
};
missionNamespace setVariable ["COMSPEC_Athena_desktopShortcutShowPFH", -1];
missionNamespace setVariable ["COMSPEC_Athena_desktopShortcutsDef", []];

private _idcs = [
    198710, 198711, 198712, 198713, 198716, 198717, 198718, 198719,
    198726, 198727, 198728, 198729, 198730, 198731, 198734, 198735,
    198736, 198737, 198740, 198741
];
{
    private _d = uiNamespace getVariable [_x, displayNull];
    if (isNull _d) then { continue };
    {
        private _c = _d displayCtrl _x;
        if (!isNull _c) then { ctrlDelete _c; };
    } forEach _idcs;
} forEach ["cTab_Android_dlg", "cTab_Android_dsp"];
