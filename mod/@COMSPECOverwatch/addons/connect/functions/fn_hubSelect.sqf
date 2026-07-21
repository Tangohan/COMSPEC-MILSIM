/*
    Ferme le menu hub puis ouvre la vue demandée (ou exécute l'action rapide).
    params: ["chat"|"cas"|"briefing"|"phone"|"manifest"|"ping"|"medical"]
*/
    params [["_view", ""]];
if (_view isEqualTo "") exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

[_view] spawn {
    params ["_view"];

    if (!isNull (findDisplay 9969)) then { closeDialog 0; };
    uiSleep 0.05;

    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

    switch (_view) do {
        case "chat": {
            createDialog "COMSPEC_Chat_Dialog";
        };
        case "cas": {
            [] call comspec_overwatch_connect_fnc_openCASDialog;
        };
        case "briefing": {
            [] call comspec_overwatch_connect_fnc_openBriefingBoard;
        };
        case "phone": {
            [] call comspec_overwatch_connect_fnc_phoneConnectShow;
        };
        case "account": {
            [] call comspec_overwatch_connect_fnc_accountLinkShow;
        };
        case "manifest": {
            createDialog "COMSPEC_FlightManifest_Dialog";
        };
        case "ping": {
            [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
            systemChat "Point d'intérêt transmis.";
        };
        case "medical": {
            private _state = [player] call comspec_overwatch_connect_fnc_getMedicalState;
            private _parts = _state splitString "|";
            private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
            private _blood = if (count _parts >= 2) then { _parts select 1 } else { "?" };
            private _hr = if (count _parts >= 4) then { _parts select 3 } else { "?" };
            private _status = switch (_health) do {
                case "cardiac_arrest": { "Arrêt cardiaque" };
                case "unconscious": { "Inconscient" };
                case "wounded": { "Blessé" };
                default { "Stable" };
            };
            [player, "CHAT", format ["WIA|%1|sang≈%2%%|FC=%3", _status, _blood, _hr], "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
            systemChat "Bilan de santé transmis.";
        };
        default {};
    };
};
