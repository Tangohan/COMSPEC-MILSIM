/*
    Enregistre le raccourci CBA « Ouvrir SEEK II » (Options > Contrôles > Extension Addon).
*/
if (!hasInterface) exitWith {};
if (!isNil "cba_fnc_addKeybind") then {
    [
        "COMSPEC SSE",
        "comspec_sse_open_seek_ii",
        ["Ouvrir SEEK II", "Ouvre BII-10 Identifi (onglet Identify) si le mod BII est chargé, sinon le terminal SEEK COMSPEC."],
        {
            if (!hasInterface) exitWith { false };
            if (!isNil "comspec_sse_fnc_openSeekKeybind") then {
                [] call comspec_sse_fnc_openSeekKeybind;
            } else {
                hint "Terminal SSE indisponible - le mod COMSPEC SSE n'a pas charge le raccourci.";
            };
            true
        },
        { false },
        [0x1F, [true, true, false]], // Ctrl+Shift+S
        false, 0, false
    ] call cba_fnc_addKeybind;
};
