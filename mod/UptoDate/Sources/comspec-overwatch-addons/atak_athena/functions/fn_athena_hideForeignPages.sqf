/*
    Une seule page ATAK COMSPEC / Comptes-rendus visible à la fois.
    Sans ça, Athena reste dessinée par-dessus TIC, RENS, TASK, le bureau, etc.
*/
params [["_keep", "", [""]]];

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _display) exitWith {};

_keep = toLower _keep;

if (_keep isEqualTo "") then {
    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    if (_mode isEqualTo "DESKTOP") then {
        _keep = "desktop";
    } else {
        private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
        _keep = switch (_page) do {
            case "athena": { "athena" };
            case "reports": { "reports" };
            case "ataknote": { "note" };
            case "ataktask": { "task" };
            case "atakbriefing": { "briefing" };
            case "ataksettings": { "settings" };
            case "atakstatus": { "status" };
            case "ataksound": { "sound" };
            case "bii_identifi": { "bii" };
            case "atakresynch": { "resynch" };
            case "bda_report": { "bda" };
            default {
                if (_page isEqualTo "") then { "" } else { "desktop" }
            };
        };
    };
};

private _needles = [
    ["athena", "comspec_atak_athena"],
    ["reports", "iceman_atak_reports"],
    ["note", "comspec_atak_note"],
    ["task", "comspec_atak_task"],
    ["briefing", "comspec_atak_briefing"],
    ["settings", "comspec_atak_settings"],
    ["status", "comspec_atak_status"],
    ["sound", "comspec_atak_sound"],
    ["bii", "comspec_atak_bii"],
    ["resynch", "comspec_atak_resynch"],
    ["bda", "comspec_atak_bdahost"]
];

private _keepNeedle = "";
{
    if ((_x select 0) isEqualTo _keep) then { _keepNeedle = _x select 1; };
} forEach _needles;

if (_keep isEqualTo "") exitWith {};

private _apps = _display displayCtrl (17000 + 4650);
if (isNull _apps) exitWith {};

{
    private _cls = toLower (ctrlClassName _x);
    if (_cls isEqualTo "") then {
        // skip
    } else {
        private _ours = false;
        {
            if ((_cls find (_x select 1)) >= 0) then { _ours = true; };
        } forEach _needles;
        if (_ours) then {
            private _show = (_keepNeedle isNotEqualTo "") && {(_cls find _keepNeedle) >= 0};
            _x ctrlShow _show;
        };
    };
} forEach (allControls _apps);
