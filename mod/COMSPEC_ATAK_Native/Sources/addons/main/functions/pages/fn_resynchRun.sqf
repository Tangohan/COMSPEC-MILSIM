/*
    App Resynch : renvoie toutes les données du terminal vers Athena (position, marqueurs, groupe, messages récents)
    avec la fonction d'Overwatch connect (forceSyncData), puis rafraîchit les données affichées par le téléphone.
*/
if (!hasInterface) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_ATAK_ResynchBusy", false]) exitWith { false };
if (isNil "comspec_overwatch_connect_fnc_forceSyncData") exitWith {
    missionNamespace setVariable ["COMSPEC_LastResynchSummary", ["<t color='#e5483a'>Resynch indisponible : Overwatch connect n'est pas chargé.</t>"], false];
    false
};
missionNamespace setVariable ["COMSPEC_ATAK_ResynchBusy", true, false];
missionNamespace setVariable ["COMSPEC_LastResynchSummary", ["Renvoi de toutes les données vers le poste de commandement…"], false];
[{ ["RESYNCH"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
[] spawn {
    [] call comspec_overwatch_connect_fnc_forceSyncData;
    [] call comspec_atak_native_fnc_localDataRefresh;
    missionNamespace setVariable ["COMSPEC_ATAK_ResynchAt", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, false];
    missionNamespace setVariable ["COMSPEC_ATAK_ResynchBusy", false, false];
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "RESYNCH") then { ["RESYNCH"] call comspec_atak_native_fnc_pageRender; };
    }] call CBA_fnc_execNextFrame;
};
true
