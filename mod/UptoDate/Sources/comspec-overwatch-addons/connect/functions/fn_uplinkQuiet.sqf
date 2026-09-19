/*
    True si la liaison ne doit pas pousser d’écran ni d’acquittement :
    arsenal ouvert, ou téléphone tout juste pris.
*/
if (!hasInterface) exitWith { true };
if (!isNull (uiNamespace getVariable ["ace_arsenal_display", displayNull])) exitWith { true };
if (!isNull (findDisplay 31)) exitWith { true };
if (!isNull (findDisplay 112)) exitWith { true };
private _acq = missionNamespace getVariable ["COMSPEC_TerminalAcquiredAt", -1];
if (_acq > 0 && {(diag_tickTime - _acq) < 8}) exitWith { true };
false
