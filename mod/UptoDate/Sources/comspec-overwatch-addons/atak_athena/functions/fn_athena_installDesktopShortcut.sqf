/*

    Icônes COMSPEC sur l’écran Desktop d’ATAK Enhanced (cTab Android),

    alignées sur la grille Iceman (bande Desktop, à côté d’Elevation).

    Toutes ouvrent l’app Athena / couche cTAB — jamais la tablette Overwatch.

*/

if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["COMSPEC_Athena_desktopShortcutPFH", -1] >= 0) exitWith {};



// [idcIcon, idcLabel, x2048, texture, tooltip, labelHtml, tab, y2048?]

private _shortcuts = [

    [

        198710, 198711, 270,

        "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa",

        "Connexion Athena — lier le compte (code ou Steam)",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Connexion<br/>Athena</t>",

        "liaison"

    ],

    [

        198712, 198713, 390,

        "\A3\ui_f\data\map\markers\military\warning_CA.paa",

        "Messages d’urgence — alertes et signalements reçus",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Messages<br/>d’urgence</t>",

        "urgences"

    ],

    [

        198714, 198715, 510,

        "\cTab\img\icon_mail_ca.paa",

        "Messages Athena — messagerie opérationnelle",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Messages<br/>Athena</t>",

        "messages"

    ],

    [

        198716, 198717, 630,

        "\A3\ui_f\data\gui\rsc\rscdisplayarsenal\radio_ca.paa",

        "Liaison mobile — adresse et code pour le téléphone",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Liaison<br/>mobile</t>",

        "liaison"

    ],

    [

        198718, 198719, 750,

        "\A3\ui_f\data\map\markers\military\pickup_CA.paa",

        "Ordres C2 — app TASK (liste et réponses)",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>TASK<br/>Ordres</t>",

        "order"

    ],

    [

        198720, 198721, 870,

        "\A3\ui_f\data\gui\rsc\rscdisplayarsenal\binoculars_ca.paa",

        "Photos Athena — les captures remontent seules vers le commandement",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Photos<br/>Athena</t>",

        "photo"

    ],

    [

        198724, 198725, 1230,

        "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa",

        "Briefing — diaporama et slides de mission",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Briefing</t>",

        "briefing"

    ],

    [

        198722, 198723, 1110,

        "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa",

        "État ATAK — liaison, latence, débit, stabilité, paquets perdus",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>État<br/>ATAK</t>",

        "atak_status"

    ],

    [

        198726, 198727, 990,

        "\A3\ui_f\data\gui\cfg\communicationmenu\call_ca.paa",

        "Sons ATAK — style d’alerte, volumes, mode discret",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Sons</t>",

        "atak_sound"

    ],

    [

        198728, 198729, 1350,

        "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa",

        "Fiche de renseignement — noter et remonter un constat avec pièces jointes",

        "<t align='center' size='0.55' color='#e8f4f0' shadow='1'>Fiche<br/>RENS</t>",

        "note"

    ]

];



missionNamespace setVariable ["COMSPEC_Athena_desktopShortcutsDef", _shortcuts];



// Handler partagé (évite les chaînes EH fragiles)

missionNamespace setVariable ["COMSPEC_Athena_desktopClick", {

    params [["_ctrl", controlNull]];

    private _tab = "all";

    if (!isNull _ctrl) then {

        _tab = _ctrl getVariable ["COMSPEC_AthenaDesktopTab", "all"];

    };

    switch (_tab) do {
        case "intel_note";
        case "note": { [""] call comspec_overwatch_atak_athena_fnc_athena_openNote; };
        case "account": { [] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog; };
        case "order": { [] call comspec_overwatch_atak_athena_fnc_athena_openTask; };
        case "briefing": { [] call comspec_overwatch_atak_athena_fnc_athena_openBriefing; };
        case "atak_status";
        case "status": { [] call comspec_overwatch_atak_athena_fnc_athena_openStatus; };
        case "atak_sound";
        case "sound";
        case "sons": { [] call comspec_overwatch_atak_athena_fnc_athena_openSound; };
        default { [_tab] call comspec_overwatch_atak_athena_fnc_athena_openFeature; };
    };

}, false];



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

    private _lblW = (160 / 2048) * (_phoneH * 3 / 4);

    private _lblH = (62 / 2048) * _phoneH;

    // Zone cliquable = icône + libellé (évite que le texte mange le clic)

    private _hitH = _iconH + _lblH + ((8 / 2048) * _phoneH);



    private _created = [];

    {

        _x params ["_idcIcon", "_idcLbl", "_xOff", "_tex", "_tip", "_lblHtml", "_tab", ["_yOff", 25, [0]]];



        private _xPos = _phoneSizeX + ((_xOff / 2048) * (_phoneH * 3 / 4));

        private _yPos = _phoneSizeY + ((_yOff / 2048) * _phoneH);



        private _ctrl = _display ctrlCreate ["ctrlButtonPictureKeepAspect", _idcIcon];

        _ctrl ctrlSetText _tex;

        _ctrl ctrlSetTooltip _tip;

        // Hitbox haute : icône + zone libellé

        _ctrl ctrlSetPosition [_xPos, _yPos, _iconW, _hitH];

        _ctrl ctrlSetBackgroundColor [0, 0, 0, 0];

        _ctrl ctrlSetActiveColor [1, 1, 1, 1];

        _ctrl ctrlShow _show;

        _ctrl ctrlCommit 0;

        _ctrl setVariable ["COMSPEC_AthenaDesktopTab", _tab];

        // Première icône = Connexion compte (pas seulement liaison mobile)

        if (_idcIcon == 198710) then {

            _ctrl setVariable ["COMSPEC_AthenaDesktopTab", "account"];

        };

        _ctrl ctrlSetEventHandler ["ButtonClick", "(_this select 0) call (missionNamespace getVariable ['COMSPEC_Athena_desktopClick', {}]); true"];

        _ctrl ctrlAddEventHandler ["MouseButtonUp", {

            params ["_c", "_btn"];

            if (_btn != 0) exitWith {};

            _c call (missionNamespace getVariable ["COMSPEC_Athena_desktopClick", {}]);

        }];



        private _lbl = _display ctrlCreate ["RscStructuredText", _idcLbl];

        _lbl ctrlSetPosition [

            _xPos - ((_lblW - _iconW) / 2),

            _yPos + _iconH + ((4 / 2048) * _phoneH),

            _lblW,

            _lblH

        ];

        _lbl ctrlSetStructuredText parseText _lblHtml;

        _lbl ctrlSetBackgroundColor [0, 0, 0, 0];

        // Ne pas voler les clics : le bouton sous-jacent (hitbox haute) reçoit l’événement

        _lbl ctrlEnable false;

        _lbl ctrlShow _show;

        _lbl ctrlCommit 0;



        _created pushBack [_ctrl, _lbl];

    } forEach _defs;



    _display setVariable ["COMSPEC_Athena_desktopShortcuts", _created];



    if (count _created > 0) then {

        _display setVariable ["COMSPEC_Athena_desktopShortcut", (_created select 0) select 0];

        _display setVariable ["COMSPEC_Athena_desktopShortcutLabel", (_created select 0) select 1];

    };

}, 0.25] call CBA_fnc_addPerFrameHandler;



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

}, 0.25] call CBA_fnc_addPerFrameHandler;


