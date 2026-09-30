/*
    Tuile P2P — Réseau local : écran IceMan ATAK_Message (contacts + fil).
    Ne pas masquer le groupe : c’est justement la page à alimenter.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

["message"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

if (!isNull _group) then {
    _group ctrlShow true;
    _group ctrlEnable true;
};

// Init native BCE / IceMan (liste contacts + messages).
if (!isNil "BCE_fnc_ATAK_message_Init") then {
    [_group, _interfaceInit, _isDialog, _settings] call BCE_fnc_ATAK_message_Init;
} else {
    if (!isNil "Iceman_fnc_message_onOpened") then {
        [_group, _interfaceInit, _isDialog, _settings] call Iceman_fnc_message_onOpened;
    };
};
