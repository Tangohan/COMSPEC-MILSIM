/*
    Zeus (ZEN) : bouton « SSE » de la fenêtre d'édition d'une unité (double-clic sur l'unité : « ÉDITER … ») et petite
    fenêtre « SSE · <nom de l'IA> » qui ouvre, pour CETTE IA, les fenêtres SSE déjà existantes.

    La fenêtre d'édition est celle de ZEN (zen_attributes, type d'affichage « Object », boîte zen_common_RscDisplayScrollbars),
    pas une fenêtre du jeu de base. ZEN y pose ses boutons (Arsenal, Compétences, Talents…) et ceux des autres mods par
    zen_attributes_fnc_addButton ; on passe par la même porte : aucun fichier d'un autre mod n'est touché.
      Placement (ZEN, fnc_open) : 3 boutons par rangée, de droite à gauche puis rangée suivante ; bouton n° i en
      x = POS_X(23.4) - POS_W(5.1) * (i mod 3), y = Y0 + POS_H(1.1) * floor (i / 3), largeur POS_W(5), hauteur POS_H(1).
      Écart entre deux boutons : POS_W(0.1) en largeur, POS_H(0.1) en hauteur, toujours > 0 quelle que soit la taille
      d'interface ou le format d'écran (même unité de grille pour la position et la taille) : jamais de chevauchement.

    [op, arguments] call comspec_sse_fnc_iarpZenHub
      register      une fois par mission (XEH_postInitClient, ZEN chargé) : le bouton, visible seulement pour une IA
                    vivante (homme, pas un joueur : les profils SSE d'IA ne s'appliquent pas aux joueurs) et seulement si
                    le menu « SSE · Roleplay de l'IA » de fn_iarpZen est en place.
      open [ia]     ouvre la fenêtre PAR-DESSUS la fenêtre d'édition, qui reste ouverte (bouton ZEN sans fermeture) :
                    ce que le Zeus a tapé reste en attente, il le retrouve en fermant la fenêtre SSE ; OK ou ANNULER de
                    ZEN décident ensuite, comme d'habitude. Les fenêtres ouvertes depuis la fenêtre SSE s'empilent de même.
      onLoad [écran] · run [n° d'entrée] · onUnload
    Entrées : les actions du menu ZEN « SSE · Roleplay de l'IA » (table comspec_sse_iarpZenActs remplie par fn_iarpZen),
    lancées avec l'IA comme cible : mêmes fenêtres, mêmes vérifications serveur (Zeus ou administrateur), multijoueur
    compris. Une entrée dont l'action n'existe pas (addon identity absent, version sans émotions…) n'est pas affichée.
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
private _L = { localize ("STR_COMSPEC_IARP_H_" + _this) };
// [identifiant de l'action ZEN (fn_iarpZen), suffixe des textes : titre « X », explication « X_D »]
private _entries = [
    ["_editor", "Id"], ["_truth", "Truth"], ["_workshop", "Workshop"], ["_answers", "Answers"],
    ["_emotions", "Emo"], ["_traits", "Control"], ["_know", "Know"]
];
private _target = {
    params ["_u"];
    !isNull _u && {alive _u} && {_u isKindOf "CAManBase"} && {!isPlayer _u}
};
// Paramètres d'une action ZEN : [position ASL, objets, groupes, points de passage, marqueurs, entité survolée, argument].
private _params = {
    params ["_u", "_arg"];
    [getPosASL _u, [_u], [], [], [], _u, _arg]
};
private _available = {
    params ["_u"];
    private _acts = missionNamespace getVariable ["comspec_sse_iarpZenActs", createHashMap];
    _entries select {
        private _act = _acts getOrDefault [_x select 0, []];
        _act isNotEqualTo [] && {([_u, _act select 2] call _params) call (_act select 1)}
    }
};

switch (_op) do {
    case "register": {
        if (isNil "zen_attributes_fnc_addButton") exitWith { false };
        if (missionNamespace getVariable ["comspec_sse_iarpZenHubOn", false]) exitWith { true };
        missionNamespace setVariable ["comspec_sse_iarpZenHubOn", true];
        // Condition et action appelées par ZEN avec l'unité éditée (_this).
        ["Object", ["STR_COMSPEC_IARP_H_Btn", "STR_COMSPEC_IARP_H_Btn_D"],
            { ["open", [_this]] call comspec_sse_fnc_iarpZenHub },
            { _this isEqualType objNull && {!isNull _this} && {alive _this} && {_this isKindOf "CAManBase"} && {!isPlayer _this}
                && {!isNil "comspec_sse_iarpZenActs"} },
            false
        ] call zen_attributes_fnc_addButton
    };
    case "open": {
        _a params [["_u", objNull, [objNull]]];
        if !([_u] call _target) exitWith { false };
        uiNamespace setVariable ["comspec_sse_iarpHubUnit", _u];
        createDialog "COMSPEC_SSE_IarpHub"
    };
    case "onLoad": {
        _a params [["_d", displayNull]];
        private _u = uiNamespace getVariable ["comspec_sse_iarpHubUnit", objNull];
        if (isNull _d) exitWith {};
        if !([_u] call _target) exitWith { [{ (_this select 0) closeDisplay 2; }, [_d]] call CBA_fnc_execNextFrame; };
        (_d displayCtrl 94901) ctrlSetText format [localize "STR_COMSPEC_IARP_H_Title", ["shown", _u] call comspec_sse_fnc_iarpName];
        // Grille SSE 40 x 25 (comspec_sse_fnc_uiGrid) ; fenêtre de 16 cases centrée, hauteur selon le nombre d'entrées.
        ([] call comspec_sse_fnc_uiGrid) params ["_gx", "_gy", "_gw", "_gh"];
        private _list = [_u] call _available;
        private _x0 = 12;
        private _w = 16;
        private _rowH = 2.45;
        private _h = 2.3 + (count _list) * _rowH + 1.7;
        private _y0 = (25 - _h) / 2;
        private _pos = { params ["_px", "_py", "_cw", "_ch"]; [_gx + _px * _gw, _gy + _py * _gh, _cw * _gw, _ch * _gh] };
        { (_d displayCtrl (_x select 0)) ctrlSetPosition ((_x select 1) call _pos); (_d displayCtrl (_x select 0)) ctrlCommit 0; } forEach [
            [94902, [_x0, _y0, _w, _h]],
            [94903, [_x0, _y0, _w, 1.4]],
            [94901, [_x0 + 0.3, _y0 + 0.15, _w - 0.6, 1.1]],
            [94904, [_x0, _y0 + 1.4, _w, 0.06]],
            [94905, [_x0 + _w - 4.3, _y0 + _h - 1.45, 4, 1.15]]
        ];
        // Une entrée : un gros bouton et une ligne d'explication dessous.
        {
            _x params ["_id", "_k"];
            private _y = _y0 + 2 + _forEachIndex * _rowH;
            private _b = _d ctrlCreate ["COMSPEC_SSE_RscButtonNav", 94910 + _forEachIndex];
            _b ctrlSetPosition ([_x0 + 0.4, _y, _w - 0.8, 1.25] call _pos);
            _b ctrlSetText (_k call _L);
            _b ctrlSetTooltip ((_k + "_D") call _L);
            _b setVariable ["comspec_sse_iarpHubAct", _id];
            _b ctrlAddEventHandler ["ButtonClick", { ["run", [(_this select 0) getVariable ["comspec_sse_iarpHubAct", ""]]] call comspec_sse_fnc_iarpZenHub; }];
            _b ctrlCommit 0;
            private _t = _d ctrlCreate ["COMSPEC_SSE_RscLabel", 94930 + _forEachIndex];
            _t ctrlSetPosition ([_x0 + 0.4, _y + 1.3, _w - 0.8, 0.85] call _pos);
            _t ctrlSetText ((_k + "_D") call _L);
            _t ctrlCommit 0;
        } forEach _list;
    };
    case "run": {
        _a params [["_id", ""]];
        private _u = uiNamespace getVariable ["comspec_sse_iarpHubUnit", objNull];
        private _act = (missionNamespace getVariable ["comspec_sse_iarpZenActs", createHashMap]) getOrDefault [_id, []];
        if (_act isEqualTo [] || {!([_u] call _target)}) exitWith { false };
        _act params ["_code", "_cond", "_arg"];
        private _p = [_u, _arg] call _params;
        if !(_p call _cond) exitWith { false };
        // La fenêtre SSE reste ouverte : la fenêtre choisie s'ouvre par-dessus, on y revient en la fermant.
        _p call _code;
        true
    };
    case "onUnload": {
        uiNamespace setVariable ["comspec_sse_iarpHubUnit", objNull];
    };
};
