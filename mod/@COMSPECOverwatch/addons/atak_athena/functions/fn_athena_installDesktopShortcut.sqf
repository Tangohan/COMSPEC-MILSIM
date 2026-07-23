/*
    Icône « Connexion Athena » sur l’écran Desktop d’ATAK Enhanced (cTab Android),
    placée à côté du raccourci Elevation (pattern Iceman).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutPFH", -1] >= 0) exitWith {};

COMSPEC_Athena_desktopShortcutPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};

    private _existing = _display displayCtrl 198710;
    if (!isNull _existing) exitWith {};

    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;

    private _ctrl = _display ctrlCreate ["ctrlButtonPictureKeepAspect", 198710];
    _ctrl ctrlSetText "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa";
    _ctrl ctrlSetTooltip "Connexion Athena — lier le compte (code ou Steam)";

    private _phoneW = safezoneW * 0.8;
    private _phoneH = safezoneH * 1.2;
    private _customPhoneH = _phoneW * 4 / 3;
    private _customPhoneX = safezoneX + (safezoneW - _phoneW) / 2;
    private _customPhoneY = safezoneY + (safezoneH - _customPhoneH) / 2;
    private _phoneSizeX = (452 / 2048) * _phoneW + _customPhoneX;
    private _phoneSizeY = ((713 + 60) / 2048) * _customPhoneH + _customPhoneY;

    // À droite du raccourci Elevation (x≈150) — même bande Desktop.
    _ctrl ctrlSetPosition [
        _phoneSizeX + ((270 / 2048) * (_phoneH * 3 / 4)),
        _phoneSizeY + ((25 / 2048) * _phoneH),
        (100 / 2048) * (_phoneH * 3 / 4),
        (100 / 2048) * _phoneH
    ];
    _ctrl ctrlSetBackgroundColor [0, 0, 0, 0];
    _ctrl ctrlSetActiveColor [1, 1, 1, 1];
    _ctrl ctrlShow (_mode == "DESKTOP");
    _ctrl ctrlCommit 0;
    _ctrl ctrlSetEventHandler [
        "ButtonClick",
        "[] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog;"
    ];

    // Libellé sous l’icône (lisible, hors jargon).
    private _lbl = _display ctrlCreate ["RscStructuredText", 198711];
    _lbl ctrlSetPosition [
        _phoneSizeX + ((250 / 2048) * (_phoneH * 3 / 4)),
        _phoneSizeY + ((125 / 2048) * _phoneH),
        (140 / 2048) * (_phoneH * 3 / 4),
        (55 / 2048) * _phoneH
    ];
    _lbl ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Connexion<br/>Athena</t>";
    _lbl ctrlSetBackgroundColor [0, 0, 0, 0];
    _lbl ctrlShow (_mode == "DESKTOP");
    _lbl ctrlCommit 0;

    _display setVariable ["COMSPEC_Athena_desktopShortcut", _ctrl];
    _display setVariable ["COMSPEC_Athena_desktopShortcutLabel", _lbl];
}, 0.25] call CBA_fnc_addPerFrameHandler;

COMSPEC_Athena_desktopShortcutShowPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};
    private _ctrl = _display getVariable ["COMSPEC_Athena_desktopShortcut", controlNull];
    private _lbl = _display getVariable ["COMSPEC_Athena_desktopShortcutLabel", controlNull];
    if (isNull _ctrl && {isNull _lbl}) exitWith {};
    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    private _show = (_mode == "DESKTOP");
    if (!isNull _ctrl) then { _ctrl ctrlShow _show; };
    if (!isNull _lbl) then { _lbl ctrlShow _show; };
}, 0.25] call CBA_fnc_addPerFrameHandler;
