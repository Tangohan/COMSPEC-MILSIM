/*
    Ouvre un chapitre du manuel SSE.
      Hors Zeus : manuel de terrain (BIS_fnc_openFieldManual, catégorie COMSPEC_SSE).
      Zeus ouvert : le manuel de terrain s'ouvre sur l'écran de mission, derrière l'interface Zeus, et
                    le module semblait ne rien faire. Le chapitre s'affiche donc dans une fenêtre posée
                    sur l'écran Zeus lui-même, avec le renvoi vers le manuel complet.
      Eden (menu COMSPEC SSE) : BIS_fnc_openFieldManual lancé depuis la barre de menus d'Eden cherchait un écran
                    parent et tombait sur un booléen (erreur createDisplay dans fn_openFieldManual.sqf, ligne 40),
                    aucun chapitre ne s'ouvrait. Le manuel s'affiche donc dans une fenêtre de l'éditeur : liste des
                    chapitres à gauche, texte à droite, ouverte sur le chapitre choisi dans le menu.
    ["Zeus"] call comspec_sse_fnc_openGuide
*/
params [["_topic", "Start", [""]]];
if (!hasInterface) exitWith {};
disableSerialization;

// Codes réservés de CfgHints : %1 saut de ligne, %2 puce, %3 / %4 surlignage.
private _chapter = {
    params ["_cfg", ["_withMore", true]];
    private _body = format [getText (_cfg >> "description"), "<br/>", "• ", "<t color='#7fd08a'>", "</t>"];
    private _tip = getText (_cfg >> "tip");
    if (_tip isNotEqualTo "") then { _body = _body + format ["<br/><br/><t color='#c9d3dc' size='0.9'>%1</t>", _tip call comspec_sse_fnc_tr]; };
    if (_withMore) then {
        _body = _body + format ["<br/><br/><t color='#8a96a3' size='0.85'>%1</t>", ("Manuel complet : Échap › Manuel de terrain › COMSPEC SSE.") call comspec_sse_fnc_tr];
    };
    _body
};

if (is3DEN) exitWith {
    // L'action d'un menu d'Eden s'exécute pendant que la liste déroulante est encore ouverte : une fenêtre créée à ce
    // moment resterait invisible et garderait la souris. On la crée juste après, une fois le menu refermé.
    if (!canSuspend) exitWith { [_topic] spawn { uiSleep 0.05; _this call comspec_sse_fnc_openGuide; }; };
    private _parent = findDisplay 313;
    if (isNull _parent) exitWith {};
    private _root = configFile >> "CfgHints" >> "COMSPEC_SSE";
    // Chapitres dans l'ordre du menu d'Eden, puis ceux qui n'y figurent pas.
    private _ids = (getArray (configFile >> "Display3DEN" >> "Controls" >> "MenuStrip" >> "Items" >> "COMSPEC_SSE_Menu" >> "items")) apply { _x select [count "COMSPEC_SSE_Menu"] };
    {
        if !((configName _x) in _ids) then { _ids pushBack (configName _x); };
    } forEach ("true" configClasses _root);
    _ids = _ids select { isClass (_root >> _x) };
    if (_ids isEqualTo []) exitWith {};
    if !(_topic in _ids) then { _topic = _ids select 0; };

    private _d = _parent createDisplay "RscDisplayEmpty";
    if (isNull _d) exitWith {};
    private _w = safeZoneW * 0.62;
    private _h = safeZoneH * 0.7;
    private _x0 = safeZoneX + (safeZoneW - _w) / 2;
    private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
    private _u = safeZoneH * 0.006;
    private _dim = _d ctrlCreate ["ctrlStatic", -1];
    _dim ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
    _dim ctrlSetBackgroundColor [0, 0, 0, 0.45];
    _dim ctrlCommit 0;
    private _bg = _d ctrlCreate ["ctrlStatic", -1];
    _bg ctrlSetPosition [_x0, _y0, _w, _h];
    _bg ctrlSetBackgroundColor [0.13, 0.14, 0.13, 0.97];
    _bg ctrlCommit 0;
    private _title = _d ctrlCreate ["ctrlStatic", -1];
    _title ctrlSetPosition [_x0, _y0 - 5 * _u, _w, 5 * _u];
    _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
    _title ctrlSetTextColor [1, 1, 1, 1];
    _title ctrlSetText ("  " + getText (_root >> "displayName"));
    _title ctrlCommit 0;

    private _lw = _w * 0.3;
    private _list = _d ctrlCreate ["ctrlListbox", -1];
    _list ctrlSetPosition [_x0 + 2 * _u, _y0 + 2 * _u, _lw, _h - 10 * _u];
    _list ctrlCommit 0;
    private _grp = _d ctrlCreate ["ctrlControlsGroup", -1];
    private _gx = _x0 + _lw + 4 * _u;
    private _gw = _w - _lw - 6 * _u;
    _grp ctrlSetPosition [_gx, _y0 + 2 * _u, _gw, _h - 10 * _u];
    _grp ctrlCommit 0;
    private _txt = _d ctrlCreate ["ctrlStructuredText", -1, _grp];
    _txt ctrlCommit 0;
    _d setVariable ["comspec_sse_guide", [_txt, _gw - 3 * _u, _u, _chapter]];
    {
        private _i = _list lbAdd getText (_root >> _x >> "displayName");
        _list lbSetData [_i, _x];
    } forEach _ids;
    _list ctrlAddEventHandler ["LBSelChanged", {
        params ["_ctrl", "_i"];
        private _d = ctrlParent _ctrl;
        (_d getVariable ["comspec_sse_guide", []]) params ["_txt", "_tw", "_u", "_chapter"];
        if (isNil "_txt") exitWith {};
        private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> (_ctrl lbData _i);
        _txt ctrlSetStructuredText parseText format ["<t size='1.15' font='RobotoCondensedBold'>%1</t><br/><br/>%2",
            getText (_cfg >> "displayName"), [_cfg, false] call _chapter];
        _txt ctrlSetPosition [0, 0, _tw, 1];
        _txt ctrlCommit 0;
        _txt ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _txt) + 2 * _u];
        _txt ctrlCommit 0;
        (ctrlParentControlsGroup _txt) ctrlSetScrollValues [0, -1];
    }];
    _list lbSetCurSel (_ids find _topic);

    private _bw = _w * 0.18;
    private _b = _d ctrlCreate ["ctrlButton", -1];
    _b ctrlSetPosition [_x0 + _w - _bw - _u, _y0 + _h - 6 * _u, _bw, 5 * _u];
    _b ctrlSetText (("Fermer") call comspec_sse_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
    _b ctrlCommit 0;
    ctrlSetFocus _list;
};

private _zeus = findDisplay 312;
if (isNull _zeus) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };

private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> _topic;
if (!isClass _cfg) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };
[parseText ([_cfg] call _chapter), getText (_cfg >> "displayName"), true, false, _zeus, false, false] spawn BIS_fnc_guiMessage;
