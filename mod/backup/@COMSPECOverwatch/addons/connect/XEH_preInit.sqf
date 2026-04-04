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
    "COMSPEC Overwatch", "comspec_open_chat", ["Ouvrir Chat", "Console"],
    {
        if (comspec_overwatch_enabled) then { createDialog "COMSPEC_Chat_Dialog"; };
    },
    { false },
    [0x25, [false, false, false]]
] call CBA_fnc_addKeybind;
