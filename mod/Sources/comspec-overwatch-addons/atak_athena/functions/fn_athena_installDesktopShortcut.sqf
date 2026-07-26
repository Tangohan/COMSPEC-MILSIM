/*
    Icônes COMSPEC sur l’écran Desktop d’ATAK Enhanced (cTab Android),
    alignées sur la grille Iceman (bande Desktop, à côté d’Elevation).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutPFH", -1] >= 0) exitWith {};

// [idcIcon, idcLabel, x2048, texture, tooltip, labelHtml, clickHandler]
private _shortcuts = [
    [
        198710, 198711, 270,
        "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa",
        "Connexion Athena — lier le compte (code ou Steam)",
        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Connexion<br/>Athena</t>",
        "[] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog;"
    ],
    [
        198712, 198713, 390,
        "\A3\ui_f\data\map\markers\military\warning_CA.paa",
        "Messages d’urgence — alertes médicales et signalements reçus",
        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Messages<br/>d’urgence</t>",
        "['alerts', true] call comspec_overwatch_connect_fnc_openTabletView;"
    ],
    [
        198714, 198715, 510,
        "\cTab\img\icon_mail_ca.paa",
        "Tchat Athena — messagerie opérationnelle",
        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Tchat<br/>Athena</t>",
        "['chat', true] call comspec_overwatch_connect_fnc_openTabletView;"
    ]
];

missionNamespace setVariable ["COMSPEC_Athena_desktopShortcutsDef", _shortcuts];

COMSPEC_Athena_desktopShortcutPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};

    private _defs = missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutsDef", []];
    if (_defs isEqualTo []) exitWith {};

    private _firstIdc = _defs select 0 select 0;
    if (!isNull (_display displayCtrl _firstIdc)) exitWith {};

    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    private _show = (_mode == "DESKTOP");

    private _phoneW = safezoneW * 0.8;
    private _phoneH = safezoneH * 1.2;
    private _customPhoneH = _phoneW * 4 / 3;
    private _customPhoneX = safezoneX + (safezoneW - _phoneW) / 2;
    private _customPhoneY = safezoneY + (safezoneH - _customPhoneH) / 2;
    private _phoneSizeX = (452 / 2048) * _phoneW + _customPhoneX;
    private _phoneSizeY = ((713 + 60) / 2048) * _customPhoneH + _customPhoneY;
    private _iconW = (100 / 2048) * (_phoneH * 3 / 4);
    private _iconH = (100 / 2048) * _phoneH;
    private _lblW = (140 / 2048) * (_phoneH * 3 / 4);
    private _lblH = (55 / 2048) * _phoneH;

    private _created = [];
    {
        _x params ["_idcIcon", "_idcLbl", "_xOff", "_tex", "_tip", "_lblHtml", "_click"];

        private _ctrl = _display ctrlCreate ["ctrlButtonPictureKeepAspect", _idcIcon];
        _ctrl ctrlSetText _tex;
        _ctrl ctrlSetTooltip _tip;
        _ctrl ctrlSetPosition [
            _phoneSizeX + ((_xOff / 2048) * (_phoneH * 3 / 4)),
            _phoneSizeY + ((25 / 2048) * _phoneH),
            _iconW,
            _iconH
        ];
        _ctrl ctrlSetBackgroundColor [0, 0, 0, 0];
        _ctrl ctrlSetActiveColor [1, 1, 1, 1];
        _ctrl ctrlShow _show;
        _ctrl ctrlCommit 0;
        _ctrl ctrlSetEventHandler ["ButtonClick", _click];

        private _lbl = _display ctrlCreate ["RscStructuredText", _idcLbl];
        _lbl ctrlSetPosition [
            _phoneSizeX + (((_xOff - 20) / 2048) * (_phoneH * 3 / 4)),
            _phoneSizeY + ((125 / 2048) * _phoneH),
            _lblW,
            _lblH
        ];
        _lbl ctrlSetStructuredText parseText _lblHtml;
        _lbl ctrlSetBackgroundColor [0, 0, 0, 0];
        _lbl ctrlShow _show;
        _lbl ctrlCommit 0;

        _created pushBack [_ctrl, _lbl];
    } forEach _defs;

    _display setVariable ["COMSPEC_Athena_desktopShortcuts", _created];

    // Compat ancienne variable (Connexion Athena seule)
    if (count _created > 0) then {
        _display setVariable ["COMSPEC_Athena_desktopShortcut", (_created select 0) select 0];
        _display setVariable ["COMSPEC_Athena_desktopShortcutLabel", (_created select 0) select 1];
    };
// 1 s : détection de l'ouverture du téléphone cTab (pas d'event "display opened" disponible côté
// cTab) — un délai d'1 s avant l'apparition des icônes reste imperceptible pour l'utilisateur.
}, 1] call CBA_fnc_addPerFrameHandler;

COMSPEC_Athena_desktopShortcutShowPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};

    private _pairs = _display getVariable ["COMSPEC_Athena_desktopShortcuts", []];
    if (_pairs isEqualTo []) exitWith {
        private _ctrl = _display getVariable ["COMSPEC_Athena_desktopShortcut", controlNull];
        private _lbl = _display getVariable ["COMSPEC_Athena_desktopShortcutLabel", controlNull];
        if (isNull _ctrl && {isNull _lbl}) exitWith {};
        private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
        private _show = (_mode == "DESKTOP");
        if (!isNull _ctrl) then { _ctrl ctrlShow _show; };
        if (!isNull _lbl) then { _lbl ctrlShow _show; };
    };

    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    private _show = (_mode == "DESKTOP");
    {
        _x params ["_ctrl", "_lbl"];
        if (!isNull _ctrl) then { _ctrl ctrlShow _show; };
        if (!isNull _lbl) then { _lbl ctrlShow _show; };
    } forEach _pairs;
// 0.5 s : suffisant pour suivre un changement de mode desktop/app sans le ressentir, pour 4x
// moins de travail par seconde que le polling précédent (0.25 s).
}, 0.5] call CBA_fnc_addPerFrameHandler;
