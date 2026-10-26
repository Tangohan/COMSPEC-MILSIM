/*
    Dépannage liaison : auto-test complet, lancé à l'ouverture de la fenêtre (fn_diagChecks) et par « Revérifier ».
    La DLL (Link.SelfTest, via Async : le jeu ne fige pas) demande à Athena ses propres contrôles, rejoue une fois
    chaque canal sondé pendant la partie et lit sa file d'envoi. Résultat rangé dans COMSPEC_DiagSelfTest
    [heure (diag_tickTime), lignes [niveau, titre, valeur, conseil, ""], erreur], puis la fenêtre est rafraîchie.
      [] call comspec_overwatch_connect_fnc_diagSelfTest
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_DiagSelfTestRunning", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_DiagSelfTestRunning", true, false];
[] spawn {
    private _map = missionNamespace getVariable ["comspec_overwatch_map_id", 1];
    if (!(_map isEqualType "")) then { _map = str _map; };
    private _raw = [["COMSPECExtension", "Link.SelfTest", [_map], 60] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    if (!(_raw isEqualType "")) then { _raw = str _raw; };
    private _rows = [];
    private _err = "";
    if ((_raw select [0, 3]) isEqualTo "OK|") then {
        private _origin = createHashMapFromArray [["S", ""], ["C", ("Canal" call comspec_overwatch_connect_fnc_tr) + " "], ["L", ""]];
        {
            private _f = _x splitString (toString [9]);
            if ((count _f) >= 4) then {
                _f params ["_o", "_lvl", "_title", "_value", ["_hint", ""]];
                if !(_lvl in ["ok", "info", "warn", "ko"]) then { _lvl = "info"; };
                _rows pushBack [_lvl, (_origin getOrDefault [_o, ""]) + (_title call comspec_overwatch_connect_fnc_tr), _value call comspec_overwatch_connect_fnc_tr, _hint call comspec_overwatch_connect_fnc_tr, ""];
            };
        } forEach ((_raw select [3]) splitString (toString [10]));
    } else {
        _err = _raw;
    };
    missionNamespace setVariable ["COMSPEC_DiagSelfTest", [diag_tickTime, _rows, _err], false];
    missionNamespace setVariable ["COMSPEC_DiagSelfTestRunning", false, false];
    ["INFO", "Diag", format ["Auto-test liaison : %1 contrôle(s)%2", count _rows, [format [", erreur %1", _err], ""] select (_err isEqualTo "")]] call comspec_overwatch_connect_fnc_log;
    private _d = uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull];
    if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_diagIsolateOnLoad; };
};
