/* Centre de notifications : les 60 dernières, de la plus récente à la plus ancienne. L'ouvrir les marque comme lues. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["notifUnread", 0];
_s set ["notifications", []];
private _hist = +(_s getOrDefault ["notifHistory", []]);
reverse _hist;
private _labels = createHashMapFromArray [["INFO", "Info"], ["SUCCESS", "Réussi"], ["WARNING", "Attention"], ["ERROR", "Erreur"], ["MESSAGE", "Message"], ["TACTICAL", "Tactique"]];
private _colors = createHashMapFromArray [["WARNING", "#f2ab33"], ["ERROR", "#e5604f"], ["TACTICAL", "#e5604f"], ["MESSAGE", "#6fb6e8"]];
// Réglages rapides : mode avion et Bluetooth.
private _air = [] call comspec_atak_native_fnc_airplaneMode;
private _btOn = (["state"] call comspec_atak_native_fnc_btAction) get "on";
private _quick = ["segment", "Réglages rapides", [
    [["MODE AVION : NON", "MODE AVION : OUI"] select _air, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, _air],
    [["BLUETOOTH : NON", "BLUETOOTH : OUI"] select _btOn, { ["toggle"] call comspec_atak_native_fnc_btAction; [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, _btOn]
]];
private _rows = [["section", "Notifications", ["Aucune notification pour l'instant.", format ["%1 notification(s), la plus récente en haut", count _hist]] select ((count _hist) > 0)]];
{
    private _t = _x getOrDefault ["type", "INFO"];
    _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2</t><t size='0.8' color='#8a9a93'>  ·  %3</t><br/>%4",
        _colors getOrDefault [_t, "#5cc76b"], toUpper (_labels getOrDefault [_t, _t]), _x getOrDefault ["time", ""], _x getOrDefault ["message", ""]]];
} forEach _hist;
if ((count _hist) > 0) then {
    _rows pushBack ["buttons", [["TOUT EFFACER", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["notifHistory", []];
        [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]];
};
[[_quick] + _rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
