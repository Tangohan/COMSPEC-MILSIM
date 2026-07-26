/*

    Ferme le menu hub puis ouvre la vue tablette demandée (plus de dialogs annexes).

    params: ["chat"|"cas"|"briefing"|"phone"|"manifest"|"ping"|"medical"|"webbrowser"|...]

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

        case "webbrowser";

        case "tablet": {

            ["bft"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "chat": {

            ["chat"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "cas": {

            ["cas"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "briefing": {

            ["briefing"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "phone": {

            [] call comspec_overwatch_connect_fnc_phoneConnectShow;

        };

        case "account": {

            [] call comspec_overwatch_connect_fnc_accountLinkShow;

        };

        case "callsign": {

            ["callsign"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "orders": {

            ["orders"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "manifest": {

            ["manifest"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "medical": {

            if ([] call comspec_overwatch_connect_fnc_canTriageMedical) then {

                ["medical"] call comspec_overwatch_connect_fnc_openTabletView;

            } else {

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

                if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
                    systemChat "Bilan de santé transmis.";
                };

                ["status"] call comspec_overwatch_connect_fnc_openTabletView;

            };

        };

        case "tactical";

        case "tactical_alert": {

            ["tactical"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "ping": {

            [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;

            ["Point d'intérêt transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;

            ["bft"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        case "forcesync": {

            [] call comspec_overwatch_connect_fnc_forceSyncData;

            ["status"] call comspec_overwatch_connect_fnc_openTabletView;

        };

        default {

            ["apps"] call comspec_overwatch_connect_fnc_openTabletView;

        };

    };

};


