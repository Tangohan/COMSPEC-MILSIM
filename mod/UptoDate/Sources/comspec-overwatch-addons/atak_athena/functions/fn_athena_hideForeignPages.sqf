/*
    Une seule page ATAK COMSPEC / Comptes-rendus visible à la fois.
    Sans ça, Athena reste dessinée par-dessus TIC, RENS, TASK, le bureau, etc.
*/
params [["_keep", "", [""]]];

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
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
            case "atakcas": { "cas" };
            case "comspec_atak_cas": { "cas" };
            case "atakmanifest": { "manifest" };
            case "comspec_atak_manifest": { "manifest" };
            case "atakcomms": { "comms" };
            case "group": { "msghub" };
            case "message": { "message" };
            case "atakp2p": { "message" };
            case "atakbriefing": { "briefing" };
            case "atakwiki": { "wiki" };
            case "ataksettings": { "settings" };
            case "atakstatus": { "status" };
            case "waverelay": { "relay" };
            case "atakrelay": { "relay" };
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
    ["cas", "comspec_atak_cas"],
    ["manifest", "comspec_atak_manifest"],
    ["comms", "comspec_atak_comms"],
    ["msghub", "comspec_atak_messagehub"],
    ["briefing", "comspec_atak_briefing"],
    ["wiki", "comspec_atak_wiki"],
    ["settings", "comspec_atak_settings"],
    ["status", "comspec_atak_status"],
    ["relay", "comspec_atak_relay"],
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
        if (_cls isEqualTo "atak_message") then {
            private _showMsg = _keep isEqualTo "message";
            _x ctrlShow _showMsg;
            _x ctrlEnable _showMsg;
        } else {
        private _ours = false;
        {
            if ((_cls find (_x select 1)) >= 0) then { _ours = true; };
        } forEach _needles;
        if (_ours) then {
            private _show = (_keepNeedle isNotEqualTo "") && {(_cls find _keepNeedle) >= 0};
            _x ctrlShow _show;
        } else {
            if ((_cls find "iceman_atak_waverelay") >= 0) then {
                private _showWr = _keep isEqualTo "relay";
                _x ctrlShow _showWr;
                _x ctrlEnable _showWr;
            } else {
            if (
                ((_keep isEqualTo "comms") || {_keep isEqualTo "msghub"})
                && {(_cls find "iceman") >= 0 && {(_cls find "group") >= 0}}
            ) then {
                _x ctrlShow false;
                _x ctrlEnable false;
            };
            };
        };
        };
    };
} forEach (allControls _apps);
