[
    "comspec_overwatch_enabled", "CHECKBOX",
    ["Activer Overwatch", "Liaison Athena"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_api_url", "EDITBOX",
    ["URL Athena", "Serveur Node.js"],
    "COMSPEC Overwatch", "http://localhost:3001"
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_update_interval", "SLIDER",
    ["Fréquence (sec)", "Délai PLI"],
    "COMSPEC Overwatch", [1, 60, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_interval", "SLIDER",
    ["Intervalle position (s)", "PerFrameHandler position"],
    "COMSPEC Overwatch", [0.1, 2, 0.25, 2]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_batch_interval", "SLIDER",
    ["Batching réseau (s)", "Envoi positions max 1/s"],
    "COMSPEC Overwatch", [0.5, 5, 1, 1]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_threshold", "SLIDER",
    ["Seuil distance (m)", "Envoi si déplacement > X m"],
    "COMSPEC Overwatch", [1, 50, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_mission_id", "EDITBOX",
    ["Mission ID", "Identifiant mission transmis aux rapports intel"],
    "COMSPEC Overwatch", "DEFAULT_MISSION"
] call CBA_fnc_addSetting;

[
    "COMSPEC Overwatch", "comspec_open_chat", ["Ouvrir Chat", "Console"],
    {
        if (comspec_overwatch_enabled) then { createDialog "COMSPEC_Chat_Dialog"; };
    },
    { false },
    [0x25, [false, false, false]]
] call CBA_fnc_addKeybind;

// Cache pour TASK 3 (position tracking) — initialisation globale
missionNamespace setVariable ["COMSPEC_lastPos", [0,0,0], true];
missionNamespace setVariable ["COMSPEC_lastName", "", true];
missionNamespace setVariable ["COMSPEC_lastRole", "", true];
missionNamespace setVariable ["COMSPEC_lastRadio", "", true];
missionNamespace setVariable ["COMSPEC_lastMedical", "", true];
missionNamespace setVariable ["COMSPEC_lastSendTime", 0, true];
