/*
    Centre Zeus : point d'entrée unique sur l'écran Zeus (display 312). État des ATAK de tous les joueurs, étiquettes 3D,
    actions de fn_zeusMenu, outils du SSE. Réservé à qui a l'écran Zeus ; le relevé est vérifié par le serveur (fn_zeusState).
    Rien ne tourne tant que le panneau est fermé : le bouton est un simple contrôle ; la boucle (2 s) et le Draw3D n'existent que
    panneau ouvert et meurent avec l'écran Zeus.
    Sources : variables déjà publiques (COMSPEC_ATAK_Pub, Spk, MeshNode via fn_meshRead, NetUp, NetLoad, ZeusFx, SimSos, Airplane,
    Gateway du véhicule, COMSPEC_DroneLock, caméras) ; le reste vient de la fiche à la demande (fn_zeusState).
    Un état non reçu n'est pas affiché : la fiche écrit « relevé en attente ».
    Actions :
      "hook"    (fn_zeusClient, chaque seconde, écran Zeus ouvert) : pose le bouton et les infobulles de la liste des modules
      "toggle"  ouvre ou ferme le panneau (bouton, touche CBA ZeusCentre)
      "close"   ferme le panneau        "closed" : écran Zeus fermé, tout s'arrête
      "refresh" lit les variables, recalcule la liste et les étiquettes (boucle de 2 s)
      "filter" [clé]   "select" [uid]   "act" [id de fn_zeusMenu]   "admin" fiche dans l'app Admin   "pin" retirer le code
      "draw"    étiquettes (Draw3D)     "tips" infobulles de la liste des modules
*/
#define DISP (findDisplay 312)
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _ui = { uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMap] };
switch (_act) do {
    case "hook": {
        private _d = DISP;
        if (isNull _d || {_d getVariable ["COMSPEC_ATAK_CentreHook", false]}) exitWith {};
        _d setVariable ["COMSPEC_ATAK_CentreHook", true];
        _d displayAddEventHandler ["Unload", { ["closed"] call comspec_atak_native_fnc_zeusCentre; }];
        // Double-clic : fenêtre de configuration des objets du mod (fn_zeusObject). Un seul gestionnaire par module de curateur.
        private _cur = getAssignedCuratorLogic player;
        if (!isNull _cur && {!(_cur getVariable ["COMSPEC_ATAK_CentreDbl", false])}) then {
            _cur setVariable ["COMSPEC_ATAK_CentreDbl", true];
            _cur addEventHandler ["CuratorObjectDoubleClicked", { ["double", [_this select 1]] call comspec_atak_native_fnc_zeusObject; }];
        };
        [{ ["tips"] call comspec_atak_native_fnc_zeusCentre; }, [], 2] call comspec_atak_native_fnc_waitAndExec;
        [{ ["tips"] call comspec_atak_native_fnc_zeusCentre; }, [], 8] call comspec_atak_native_fnc_waitAndExec;
        if !(missionNamespace getVariable ["comspec_atak_native_zeus_centre_btn", true]) exitWith {};
        private _h = safeZoneH;
        private _kw = pixelW / pixelH;
        // Bord gauche de la liste de droite de Zeus : le Centre s'y adosse, sans la couvrir.
        private _right = safeZoneX + safeZoneW - 0.16 * _h * _kw;
        {
            if (ctrlType _x isEqualTo 12 && {isNull ctrlParentControlsGroup _x}) then {
                private _px = (ctrlPosition _x) select 0;
                if (_px > safeZoneX + safeZoneW * 0.6 && {_px < _right}) then { _right = _px; };
            };
        } forEach (allControls _d);
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreRight", _right];
        private _bw = 0.13 * _h * _kw;
        private _b = _d ctrlCreate ["COMSPEC_RscButton", -1];
        _b ctrlSetPosition [_right - _bw - 0.004 * _h * _kw, safeZoneY + 0.008 * _h, _bw, 0.034 * _h];
        _b ctrlSetFontHeight (0.021 * _h);
        _b ctrlSetText (("CENTRE ZEUS") call comspec_atak_native_fnc_tr);
        _b ctrlSetTooltip (("État des ATAK, actions et outils Zeus") call comspec_atak_native_fnc_tr);
        _b ctrlCommit 0;
        _b ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_atak_native_fnc_zeusCentre; }];
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreBtn", _b];
    };
    case "toggle": {
        private _d = DISP;
        if (isNull _d) exitWith { false };
        if ((count (call _ui)) > 0) exitWith { ["close"] call comspec_atak_native_fnc_zeusCentre; true };
        // Touche CBA avec le bouton masqué par le réglage : position calculée au besoin.
        private _h = safeZoneH;
        private _kw = pixelW / pixelH;
        private _right = uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreRight", safeZoneX + safeZoneW - 0.16 * _h * _kw];
        private _w = 0.46 * _h * _kw;
        private _x0 = _right - _w - 0.004 * _h * _kw;
        private _y = safeZoneY + 0.048 * _h;
        private _p = 0.004 * _h;
        private _pw = _p * _kw;
        private _mk = {
            params ["_cls", "_pos", ["_txt", ""], ["_tip", ""], ["_ev", ""], ["_grp", controlNull]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlSetPosition _pos;
            if (_txt isNotEqualTo "") then { _c ctrlSetText (_txt call comspec_atak_native_fnc_tr); };
            if (_tip isNotEqualTo "") then { _c ctrlSetTooltip (_tip call comspec_atak_native_fnc_tr); };
            if (_cls isEqualTo "COMSPEC_RscButton") then { _c ctrlSetFontHeight (0.019 * _h); };
            _c ctrlCommit 0;
            if (_ev isNotEqualTo "") then { _c ctrlAddEventHandler ["ButtonClick", _ev]; };
            _c
        };
        private _ctrls = [];
        private _bg = ["COMSPEC_RscPanel", [_x0, _y, _w, 0.782 * _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.035, 0.045, 0.04, 0.93];
        _bg ctrlCommit 0;
        _ctrls pushBack _bg;
        private _head = ["COMSPEC_RscStructuredText", [_x0 + _pw, _y + _p, _w - 2 * _pw, 0.034 * _h]] call _mk;
        _ctrls pushBack _head;
        _y = _y + 0.038 * _h;
        // Filtres : deux rangées de trois.
        private _fb = createHashMap;
        private _fw = (_w - 4 * _pw) / 3;
        {
            _x params ["_k", "_tip"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + (_forEachIndex % 3) * (_fw + _pw), _y + (floor (_forEachIndex / 3)) * (0.032 * _h), _fw, 0.030 * _h], "", _tip] call _mk;
            _c setVariable ["k", _k];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["filter", [_c getVariable "k"]] call comspec_atak_native_fnc_zeusCentre; }];
            _fb set [_k, _c];
            _ctrls pushBack _c;
        } forEach [
            ["all", "Tous les joueurs"], ["link", "Session Athena ouverte"], ["nolink", "Sans session Athena, ou relevé pas encore reçu"],
            ["relay", "Porte une radio qui relaie, a posé une radio ou est à bord d'une passerelle"], ["spk", "Musique en haut-parleur"], ["alert", "PANIQUE, téléphone détruit, code bloqué, SIM en secours"]
        ];
        _y = _y + 0.066 * _h;
        private _list = ["COMSPEC_RscListBoxDense", [_x0 + _pw, _y, _w - 2 * _pw, 0.27 * _h]] call _mk;
        _list ctrlSetBackgroundColor [0, 0, 0, 0.35];
        _list ctrlAddEventHandler ["LBSelChanged", {
            params ["_c", "_i"];
            if (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreFill", false]) exitWith {};
            ["select", [_c lbData _i]] call comspec_atak_native_fnc_zeusCentre;
        }];
        _ctrls pushBack _list;
        _y = _y + 0.274 * _h;
        private _grp = ["COMSPEC_RscControlsGroup", [_x0 + _pw, _y, _w - 2 * _pw, 0.24 * _h]] call _mk;
        _ctrls pushBack _grp;
        private _card = ["COMSPEC_RscStructuredText", [0, 0, _w - 2 * _pw - 0.018 * _h * _kw, 0.24 * _h], "", "", "", _grp] call _mk;
        _y = _y + 0.244 * _h;
        // Actions de fn_zeusMenu sur le joueur choisi : les plus utilisées, puis tout le catalogue.
        private _qw = (_w - 5 * _pw) / 4;
        {
            _x params ["_t", "_tip", "_code"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_qw + _pw), _y, _qw, 0.030 * _h], _t, _tip, _code] call _mk;
            _ctrls pushBack _c;
        } forEach [
            ["COUPER LE SON", "Coupe ses haut-parleurs", "['act', ['mute_on']] call comspec_atak_native_fnc_zeusCentre;"],
            ["RÉTABLIR LE SON", "Rétablit ses haut-parleurs", "['act', ['mute_off']] call comspec_atak_native_fnc_zeusCentre;"],
            ["RETIRER LE CODE", "Retire le code de son téléphone et remet ses essais à zéro", "['pin'] call comspec_atak_native_fnc_zeusCentre;"],
            ["FICHE ADMIN", "Ouvre sa fiche dans l'app Admin du téléphone", "['admin'] call comspec_atak_native_fnc_zeusCentre;"]
        ];
        _y = _y + 0.034 * _h;
        private _cat = ["COMSPEC_RscCombo", [_x0 + _pw, _y, (_w - 3 * _pw) * 0.72, 0.030 * _h]] call _mk;
        _cat ctrlSetFontHeight (0.019 * _h);
        {
            _x params ["_id", "_g", "_lbl", "", ["_modOnly", false]];
            if (!_modOnly) then {
                private _i = _cat lbAdd format ["%1 : %2", _g call comspec_atak_native_fnc_tr, _lbl call comspec_atak_native_fnc_tr];
                _cat lbSetData [_i, _id];
            };
        } forEach (["catalog"] call comspec_atak_native_fnc_zeusMenu);
        _cat lbSetCurSel 0;
        _ctrls pushBack _cat;
        _ctrls pushBack (["COMSPEC_RscButton", [_x0 + 2 * _pw + (_w - 3 * _pw) * 0.72, _y, (_w - 3 * _pw) * 0.28, 0.030 * _h], "APPLIQUER", "Applique l'action au joueur choisi",
            "private _c = (uiNamespace getVariable 'COMSPEC_ATAK_ZeusCentreUi') get 'cat'; ['act', [_c lbData (lbCurSel _c)]] call comspec_atak_native_fnc_zeusCentre;"] call _mk);
        _y = _y + 0.034 * _h;
        // Outils : présents seulement si la fonction existe (SSE chargé, fenêtre de configuration des IA livrée).
        private _tools = [["ÉTIQUETTES 3D", "Étiquettes au-dessus des joueurs", "['labels'] call comspec_atak_native_fnc_zeusCentre;"],
            ["CONFIGURER", "Ouvre la fenêtre de l'objet sélectionné dans Zeus (écran, preuve, caisse)", "['request', [((curatorSelected param [0, []]) select { !(_x isKindOf 'CAManBase') }) param [0, objNull]]] call comspec_atak_native_fnc_zeusObject;"]];
        if (!isNil "comspec_sse_fnc_aiConfigOpen") then { _tools pushBack ["IA (SSE)", "Configuration des IA", "[] call comspec_sse_fnc_aiConfigOpen;"]; };
        if (!isNil "comspec_sse_fnc_editor") then { _tools pushBack ["ÉDITEUR SSE", "Ouvre l'éditeur de renseignement", "['open', [screenToWorld [0.5, 0.5], objNull]] call comspec_sse_fnc_editor;"]; };
        if (!isNil "comspec_sse_fnc_chartOpen") then { _tools pushBack ["TABLEAU DES LIENS", "Ouvre le tableau des liens", "[true] call comspec_sse_fnc_chartOpen;"]; };
        if (!isNil "comspec_sse_fnc_openGuide") then { _tools pushBack ["MANUEL SSE", "Ouvre le manuel du Zeus", "['Zeus'] call comspec_sse_fnc_openGuide;"]; };
        private _tw = (_w - (1 + count _tools) * _pw) / (count _tools);
        private _lab = controlNull;
        {
            _x params ["_t", "_tip", "_code"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_tw + _pw), _y, _tw, 0.030 * _h], _t, _tip, _code] call _mk;
            if (_forEachIndex isEqualTo 0) then { _lab = _c; };
            _ctrls pushBack _c;
        } forEach _tools;
        _y = _y + 0.034 * _h;
        // Raccourcis déjà en place, rappelés ici.
        private _keys = ["COMSPEC_RscStructuredText", [_x0 + _pw, _y, _w - 2 * _pw, 0.026 * _h]] call _mk;
        _keys ctrlSetStructuredText parseText (("<t size='0.72' color='#8a9a93'>Clic droit (ZEN) : ATAK · Contrôle Zeus, ATAK · Relais, ATAK · Maillage</t>") call comspec_atak_native_fnc_tr);
        _ctrls pushBack _keys;
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMapFromArray [
            ["all", _ctrls], ["head", _head], ["filters", _fb], ["list", _list], ["card", _card], ["cat", _cat], ["labels", _lab], ["grp", _grp]
        ]];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDenied", false];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLabels", missionNamespace getVariable ["comspec_atak_native_zeus_centre_3d", true]];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreAsked", -100];
        ["refresh"] call comspec_atak_native_fnc_zeusCentre;
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePfh", [{ ["refresh"] call comspec_atak_native_fnc_zeusCentre; }, 2] call CBA_fnc_addPerFrameHandler];
        ["labels", [true]] call comspec_atak_native_fnc_zeusCentre;
        true
    };
    case "close";
    case "closed": {
        private _pfh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentrePfh", -1];
        if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePfh", -1]; };
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1]; };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", []];
        { ctrlDelete _x; } forEach ((call _ui) getOrDefault ["all", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreUi", nil];
    };
    case "labels": {
        // ["labels"] bascule ; ["labels", [true]] : applique l'état en cours (ouverture du panneau).
        if (_arg isEqualTo []) then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLabels", !(missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true])]; };
        private _on = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true];
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        if (_on && {_eh < 0}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_atak_native_fnc_zeusCentre; }]];
        };
        if (!_on && {_eh >= 0}) then {
            removeMissionEventHandler ["Draw3D", _eh];
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        };
        private _c = (call _ui) getOrDefault ["labels", controlNull];
        if (!isNull _c) then { _c ctrlSetTextColor ([[0.55, 0.6, 0.57, 1], [0.46, 0.88, 0.52, 1]] select _on); };
    };
    case "draw": {
        // Sécurité : l'événement Unload ne s'est pas produit (changement de mission).
        if (isNull DISP) exitWith { ["closed"] call comspec_atak_native_fnc_zeusCentre; };
        {
            _x params ["_o", "_col", "_txt", "_top"];
            if (!isNull _o) then {
                private _p = ASLToAGL (getPosASLVisual _o);
                _p set [2, (_p select 2) + _top];
                drawIcon3D ["\a3\ui_f\data\map\markers\military\dot_CA.paa", _col, _p, 0.45, 0.45, 0, _txt, 2, 0.024, "RobotoCondensedBold", "center"];
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreMarks", []]);
    };
    case "filter": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFilter", _arg param [0, "all"]];
        ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre;
    };
    case "select": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreSel", _arg param [0, ""]];
        ["card"] call comspec_atak_native_fnc_zeusCentre;
    };
    case "refresh": {
        if (isNull DISP) exitWith { ["closed"] call comspec_atak_native_fnc_zeusCentre; };
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _now = diag_tickTime;
        // Demande au serveur : toutes les 5 s, et jamais vue fermée (cette boucle n'existe alors plus).
        if (_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreAsked", -100]) >= 5) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreAsked", _now];
            ["request"] call comspec_atak_native_fnc_zeusState;
        };
        private _rows = missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap];
        private _srv = [time, serverTime] select isMultiplayer;
        // Drones : qui pilote (COMSPEC_DroneLock : uid), à qui appartient-il. Caméras posées.
        private _pilot = createHashMap;
        private _owned = createHashMap;
        {
            private _nm = _x getVariable ["COMSPEC_DroneName", getText (configOf _x >> "displayName")];
            private _lk = _x getVariable ["COMSPEC_DroneLock", ""];
            if (_lk isEqualType "" && {_lk isNotEqualTo ""}) then { _pilot set [_lk, _nm]; };
            private _ow = _x getVariable ["COMSPEC_DroneOwner", ""];
            if (_ow isEqualType "" && {_ow isNotEqualTo ""}) then { _owned set [_ow, (_owned getOrDefault [_ow, 0]) + 1]; };
        } forEach allUnitsUAV;
        private _cams = createHashMap;
        {
            private _o = _x getVariable ["COMSPEC_ATAK_CamOwnerUid", ""];
            if (!isNull _x && {_o isEqualType ""}) then { _cams set [_o, (_cams getOrDefault [_o, 0]) + 1]; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]);
        private _model = [];
        {
            private _u = _x;
            if (_u isKindOf "HeadlessClient_F") then { continue; };
            private _uid = getPlayerUID _u;
            private _rec = _rows getOrDefault [_uid, []];
            private _row = _rec param [0, []];
            private _fresh = _rec isNotEqualTo [] && {_now - (_rec select 1) < 60};
            private _pub = _u getVariable ["COMSPEC_ATAK_Pub", []];
            if !(_pub isEqualType []) then { _pub = []; };
            private _spk = _u getVariable ["COMSPEC_ATAK_Spk", []];
            if !(_spk isEqualType []) then { _spk = []; };
            private _node = ["node", _u] call comspec_atak_native_fnc_meshRead;
            private _mpu = ["mpu5", _u] call comspec_atak_native_fnc_meshRead;
            private _gw = (vehicle _u) getVariable ["COMSPEC_ATAK_Gateway", []];
            if !(_gw isEqualType []) then { _gw = []; };
            private _dropped = _node isNotEqualTo [] && {(_node select 2) isNotEqualTo _u};
            private _isRelay = _mpu isNotEqualTo [] || {_dropped} || {_gw isNotEqualTo []};
            private _dev = _pub param [2, ""];
            if !(_dev isEqualType "") then { _dev = ""; };
            private _pin = if (_fresh) then { _row select 11 } else { 0 };
            private _panic = if (_fresh) then { _row select 12 } else { -1 };
            private _sos = _u getVariable ["COMSPEC_ATAK_SimSos", false];
            private _alerts = [];
            if (_panic >= 0) then { _alerts pushBack "PANIQUE"; };
            if (_dev isEqualTo "BROKEN") then { _alerts pushBack "TÉLÉPHONE DÉTRUIT"; };
            if (_pin > 0) then { _alerts pushBack "CODE BLOQUÉ"; };
            if (_sos isEqualTo true) then { _alerts pushBack "SIM EN SECOURS"; };
            private _logged = _fresh && {_row select 6};
            _model pushBack (createHashMapFromArray [
                ["u", _u], ["uid", _uid], ["name", name _u], ["row", _row], ["fresh", _fresh], ["pub", _pub], ["dev", _dev], ["spk", _spk], ["node", _node], ["mpu", _mpu],
                ["gw", _gw], ["dropped", _dropped], ["relay", _isRelay], ["alerts", _alerts], ["logged", _logged], ["pin", _pin], ["panic", _panic],
                ["pilot", _pilot getOrDefault [_uid, ""]], ["drones", _owned getOrDefault [_uid, 0]], ["cams", _cams getOrDefault [_uid, 0]]
            ]);
        } forEach allPlayers;
        private _pred = createHashMapFromArray [
            ["all", { true }], ["link", { _x get "logged" }], ["nolink", { !(_x get "logged") }], ["relay", { _x get "relay" }],
            ["spk", { (_x get "spk") isNotEqualTo [] }], ["alert", { (_x get "alerts") isNotEqualTo [] }]
        ];
        private _n = createHashMap;
        { private _p = _y; _n set [_x, { call _p } count _model]; } forEach _pred;
        private _flt = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreFilter", "all"];
        private _names = createHashMapFromArray [["all", "TOUS"], ["link", "CONNECTÉS"], ["nolink", "HORS LIAISON"], ["relay", "RELAIS"], ["spk", "HAUT-PARLEUR"], ["alert", "ALERTES"]];
        {
            private _k = _x;
            _y ctrlSetText format ["%1 (%2)", (_names get _k) call comspec_atak_native_fnc_tr, _n get _k];
            _y ctrlSetBackgroundColor ([[0.10, 0.13, 0.115, 1], [0.18, 0.36, 0.22, 1]] select (_k isEqualTo _flt));
        } forEach (_t get "filters");
        private _denied = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateDenied", false];
        (_t get "head") ctrlSetStructuredText parseText (format ["<t size='0.95' font='RobotoCondensedBold'>%1</t><t size='0.8' color='#8a9a93' align='right'>%2</t>",
            ("CENTRE ZEUS") call comspec_atak_native_fnc_tr,
            [format [("%1 joueur(s)") call comspec_atak_native_fnc_tr, count _model], ("Relevé refusé par le serveur") call comspec_atak_native_fnc_tr] select _denied]);
        // Joueur choisi dans Zeus : sa fiche s'ouvre ici (le clic sur l'unité vaut clic dans la liste).
        private _picked = ((curatorSelected param [0, []]) select { isPlayer _x }) param [0, objNull];
        if (!isNull _picked) then {
            private _pu = getPlayerUID _picked;
            if (_pu isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentrePicked", ""])) then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreSel", _pu]; };
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePicked", _pu];
        } else { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePicked", ""]; };
        private _test = _pred get _flt;
        private _shown = _model select { call _test };
        _shown = [_shown, [], { toLower (_x get "name") }] call BIS_fnc_sortBy;
        // La liste n'est remplie à nouveau que si elle a changé : sa position de défilement et la sélection restent.
        private _lines = _shown apply {
            private _m = _x;
            private _tok = [];
            (_m get "pub") params [["_b", -1], ["_s", -1]];
            private _bat = if (_m get "fresh") then { (_m get "row") select 4 } else { _b };
            if (_bat isEqualType 0 && {_bat >= 0}) then { _tok pushBack format ["%1 %%", _bat]; };
            if (_s isEqualType 0 && {_s >= 0}) then { _tok pushBack format ["S%1", _s]; };
            if ((_m get "spk") isNotEqualTo []) then { _tok pushBack "HP"; };
            if (_m get "relay") then { _tok pushBack "REL"; };
            if ((_m get "pilot") isNotEqualTo "") then { _tok pushBack "UAV"; };
            if ((_m get "alerts") isNotEqualTo []) then { _tok pushBack ((_m get "alerts") select 0); };
            private _col = switch (true) do {
                case ((_m get "alerts") isNotEqualTo []): { [0.95, 0.35, 0.28, 1] };
                case (!(_m get "fresh")): { [0.6, 0.64, 0.62, 1] };
                case (!(_m get "logged")): { [0.95, 0.67, 0.2, 1] };
                default { [0.79, 0.88, 0.82, 1] };
            };
            [format ["%1  ·  %2", _m get "name", (_tok apply { _x call comspec_atak_native_fnc_tr }) joinString " · "], _m get "uid", _col]
        };
        private _list = _t get "list";
        if (_lines isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLines", []]) || {_arg isEqualTo [true]}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLines", _lines];
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFill", true];
            lbClear _list;
            private _sel = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
            private _at = -1;
            {
                _x params ["_txt", "_id", "_col"];
                private _i = _list lbAdd _txt;
                _list lbSetData [_i, _id];
                _list lbSetColor [_i, _col];
                if (_id isEqualTo _sel) then { _at = _i; };
            } forEach _lines;
            if (_at >= 0) then { _list lbSetCurSel _at; };
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFill", false];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreModel", _model];
        ["card"] call comspec_atak_native_fnc_zeusCentre;
        // Étiquettes 3D : 40 au plus, les plus proches de la caméra ; un équipage = une étiquette.
        if (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true]) then {
            private _cam = [curatorCamera, player] select (isNull curatorCamera);
            private _groups = createHashMap;
            {
                private _o = vehicle (_x get "u");
                private _k = hashValue _o;
                private _l = _groups getOrDefault [_k, [_o, []]];
                (_l select 1) pushBack _x;
                _groups set [_k, _l];
            } forEach _shown;
            private _marks = (values _groups) apply { [(_x select 0) distance _cam, _x select 0, _x select 1] };
            _marks sort true;
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", (_marks select [0, 40]) apply {
                _x params ["_dist", "_o", "_ms"];
                private _bad = _ms select { (_x get "alerts") isNotEqualTo [] };
                private _first = _ms select 0;
                private _txt = if ((count _ms) > 1) then { format [("%1 joueurs") call comspec_atak_native_fnc_tr, count _ms] } else { "" };
                if ((count _ms) isEqualTo 1) then {
                    (_first get "pub") params [["_b", -1], ["_s", -1]];
                    private _tk = [];
                    if (_b isEqualType 0 && {_b >= 0}) then { _tk pushBack format ["%1 %%", _b]; };
                    if ((_first get "spk") isNotEqualTo []) then { _tk pushBack "HP"; };
                    if (_first get "relay") then { _tk pushBack "REL"; };
                    _txt = _tk joinString " · ";
                };
                if (_bad isNotEqualTo []) then { _txt = ((_bad select 0) get "alerts") select 0; };
                // Au loin, le point seul : le texte reste pour les alertes.
                if (_dist > 400 && {_bad isEqualTo []}) then { _txt = ""; };
                private _col = switch (true) do {
                    case (_bad isNotEqualTo []): { [0.95, 0.3, 0.25, 1] };
                    case ((_ms findIf { !(_x get "logged") }) >= 0): { [0.95, 0.67, 0.2, 0.95] };
                    default { [0.45, 0.88, 0.52, 0.95] };
                };
                private _top = [2.3, ((boundingBoxReal _o) select 1 select 2) + 1] select (_o isNotEqualTo (_first get "u"));
                [_o, _col, _txt call comspec_atak_native_fnc_tr, _top]
            }];
        } else { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", []]; };
    };
    case "card": {
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        private _model = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreModel", []];
        private _i = _model findIf { (_x get "uid") isEqualTo _uid };
        private _card = _t get "card";
        if (_i < 0) exitWith { _card ctrlSetStructuredText parseText (("<t size='0.85' color='#8a9a93'>Choisissez un joueur dans la liste, ou sélectionnez-le dans Zeus.</t>") call comspec_atak_native_fnc_tr); };
        private _m = _model select _i;
        private _u = _m get "u";
        private _esc = { [[_this, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
        private _tr = { _this call comspec_atak_native_fnc_tr };
        private _yes = { [("non") call _tr, ("oui") call _tr] select _this };
        private _row = _m get "row";
        private _fresh = _m get "fresh";
        (_m get "pub") params [["_bat", -1], ["_bars", -1]];
        private _L = [format ["<t size='1' font='RobotoCondensedBold'>%1</t> <t size='0.8' color='#8a9a93'>%2 · %3</t>", (_m get "name") call _esc, [_u] call comspec_atak_native_fnc_unitCallsign, groupId group _u]];
        private _sec = { _L pushBack format ["<t size='0.75' color='#7fd08a'>%1</t>", _this call _tr]; };
        private _line = { params ["_k", "_v"]; _L pushBack format ["<t size='0.82' color='#8a9a93'>%1 : </t><t size='0.82'>%2</t>", _k call _tr, _v]; };
        if ((_m get "alerts") isNotEqualTo []) then { _L pushBack format ["<t size='0.9' color='#f25a48'>%1</t>", ((_m get "alerts") apply { _x call _tr }) joinString " · "]; };
        "TÉLÉPHONE" call _sec;
        if (_fresh) then {
            ["Possédé", (_row select 1) call _yes] call _line;
            ["Écran", (["rangé", "porté", "en main"] select ((_row select 2) max 0 min 2)) call _tr] call _line;
            ["Batterie", format ["%1 %%", _row select 4]] call _line;
            ["Dégâts", format ["%1 %%", _row select 3]] call _line;
        } else {
            if (_bat isEqualType 0 && {_bat >= 0}) then { ["Batterie", format ["%1 %%", _bat]] call _line; };
            _L pushBack format ["<t size='0.82' color='#8a9a93'>%1</t>", ("Relevé en attente (joueur sans le mod, ou réponse en route).") call _tr];
        };
        if ((_m get "dev") isNotEqualTo "") then {
            ["État", ((createHashMapFromArray [["OK", "allumé"], ["CRACKED", "allumé, écran fêlé"], ["OFF", "éteint"], ["BROKEN", "détruit"]]) getOrDefault [_m get "dev", _m get "dev"]) call _tr] call _line;
        };
        if ((_u getVariable ["COMSPEC_ATAK_Airplane", false]) isEqualTo true) then { ["Mode", ("avion") call _tr] call _line; };
        private _fx = (_u getVariable ["COMSPEC_ATAK_ZeusFx", []]) apply { _x param [0, ""] };
        if (_fx isNotEqualTo []) then { ["Effets Zeus", _fx joinString ", "] call _line; };
        if (_fresh) then {
            "ATHENA" call _sec;
            ["Liaison", ((createHashMapFromArray [
                ["connected", "connecté"], ["degraded", "connecté, liaison dégradée"], ["channel", "connecté, sans canal"], ["connecting", "connexion en cours"], ["syncing", "synchronisation"],
                ["code", "code en attente"], ["unlinked", "compte non relié"], ["disconnected", "hors liaison"], ["off", "service coupé"], ["battleye", "bloqué par BattlEye"],
                ["maintenance", "maintenance"], ["error", "erreur"], ["unset", "non configuré"]
            ]) getOrDefault [_row select 5, _row select 5]) call _tr] call _line;
            ["Mod", format ["%1 · Link %2", ["?", _row select 7] select ((_row select 7) isNotEqualTo ""), ["?", _row select 8] select ((_row select 8) isNotEqualTo "")]] call _line;
        };
        "RÉSEAU" call _sec;
        if (_bars isEqualType 0 && {_bars >= 0}) then { ["Signal", format ["%1", _bars]] call _line; };
        (_u getVariable ["COMSPEC_ATAK_NetUp", []]) params [["_kb", -1], ["_loss", -1], ["_via", ""]];
        if (_kb isEqualType 0 && {_kb >= 0}) then { ["Liaison montante", format ["%1 kbit/s, %2 %% de pertes", _kb, _loss]] call _line; };
        if (_via isEqualType "" && {_via isNotEqualTo ""}) then { ["Passerelle", _via call _esc] call _line; };
        private _node = _m get "node";
        if (_node isNotEqualTo []) then {
            ["Nœud du maillage", format ["%1, %2 m%3", _node select 0, _node select 1, [("") , format [", %1", ("écoute seule") call _tr]] select ((_node select 5) isEqualTo 0)]] call _line;
        };
        private _mpu = _m get "mpu";
        private _carry = [];
        if (_mpu isNotEqualTo []) then { _carry pushBack format [("%1 (%2 saut(s))") call _tr, (_mpu select 3) call _esc, _mpu select 1]; };
        if (_m get "dropped") then { _carry pushBack (("radio posée au sol") call _tr); };
        if ((_m get "gw") isNotEqualTo []) then { _carry pushBack format [("passerelle de véhicule : %1") call _tr, ((_m get "gw") param [2, ""]) call _esc]; };
        private _kits = { _x in ["COMSPEC_ATAK_SatAntennaKit", "COMSPEC_ATAK_MastAntennaKit"] } count (items _u);
        if (_kits > 0) then { _carry pushBack format [("%1 kit(s) d'antenne") call _tr, _kits]; };
        if (_carry isNotEqualTo []) then { ["Relais portés", _carry joinString " · "] call _line; };
        "ÉMISSION" call _sec;
        private _spk = _m get "spk";
        if (_spk isNotEqualTo []) then {
            private _title = _spk param [2, ""];
            if !(_title isEqualType "") then { _title = ""; };
            ["Haut-parleur", format ["%1 (%2 %%)", [("sans titre") call _tr, (_title select [0, 60]) call _esc] select (_title isNotEqualTo ""), _spk param [4, 0]]] call _line;
        } else { ["Haut-parleur", ("non") call _tr] call _line; };
        private _voice = ["voice", _u] call comspec_atak_native_fnc_meshRead;
        if (_voice isNotEqualTo []) then { ["Voix relayée", format [("%1 (%2 saut(s))") call _tr, (_voice select 6) call _esc, _voice select 5]] call _line; };
        if (_fresh && {_row select 10}) then { ["Radio", ("en émission au dernier relevé") call _tr] call _line; };
        private _use = [];
        if ((_m get "pilot") isNotEqualTo "") then { _use pushBack format [("pilote : %1") call _tr, (_m get "pilot") call _esc]; };
        if ((_m get "drones") > 0) then { _use pushBack format [("%1 drone(s) appairé(s)") call _tr, _m get "drones"]; };
        if ((_m get "cams") > 0) then { _use pushBack format [("%1 caméra(s) posée(s)") call _tr, _m get "cams"]; };
        if (_fresh && {(_row select 9) isNotEqualTo ""}) then {
            _use pushBack (((createHashMapFromArray [["drone", "regarde la vue d'un drone"], ["livecam", "regarde une caméra"], ["photo", "appareil photo ouvert"]]) getOrDefault [_row select 9, _row select 9]) call _tr);
        };
        if (_use isNotEqualTo []) then {
            "DRONE ET CAMÉRA" call _sec;
            { _L pushBack format ["<t size='0.82'>%1</t>", _x]; } forEach _use;
        };
        _card ctrlSetStructuredText parseText (_L joinString "<br/>");
        private _pos = ctrlPosition _card;
        _pos set [3, (ctrlTextHeight _card) max ((ctrlPosition (_t get "grp")) select 3)];
        _card ctrlSetPosition _pos;
        _card ctrlCommit 0;
    };
    case "act": {
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_i < 0) exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        ["apply", [_arg param [0, ""], [allPlayers select _i]]] call comspec_atak_native_fnc_zeusMenu;
    };
    case "pin": {
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        if (_uid isEqualTo "") exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        ["server", ["phonePin", [_uid]]] call comspec_atak_native_fnc_adminClient;
        ["feedback", ["Demande envoyée au serveur : code retiré."]] call comspec_atak_native_fnc_zeusMenu;
    };
    case "admin": {
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        if (_uid isEqualTo "") exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        if (isNull ([] call comspec_atak_native_fnc_display)) then { [true] call comspec_atak_native_fnc_open; };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["admTab", "GAME"];
        _s set ["admOpen", "P" + _uid];
        ["ADMIN"] call comspec_atak_native_fnc_navigate;
    };
    case "tips": {
        // Infobulles de la liste des modules : par texte affiché, ZEN n'en propose pas et la config ne s'y affiche pas.
        private _d = DISP;
        if (isNull _d) exitWith {};
        private _tips = createHashMap;
        {
            _x params ["_k", "_tip"];
            private _cfg = configFile >> "CfgVehicles" >> _k;
            _tips set [if (isClass _cfg) then { getText (_cfg >> "displayName") } else { _k call comspec_atak_native_fnc_tr }, _tip call comspec_atak_native_fnc_tr];
        } forEach (["tipsTable"] call comspec_atak_native_fnc_zeusCentre);
        {
            private _tv = _x;
            if (ctrlType _tv isEqualTo 12) then {
                for "_c" from 0 to ((_tv tvCount []) - 1) do {
                    if (((_tv tvText [_c]) find "COMSPEC · ") isEqualTo 0) then {
                        for "_m" from 0 to ((_tv tvCount [_c]) - 1) do {
                            private _tip = _tips getOrDefault [_tv tvText [_c, _m], ""];
                            if (_tip isNotEqualTo "") then { _tv tvSetTooltip [[_c, _m], _tip]; };
                        };
                    };
                };
            };
        } forEach (allControls _d);
    };
    case "tipsTable": {
        [
            ["COMSPEC_ATAK_Module_ZeusControl", "Applique un effet au téléphone. À poser sur un joueur ou au sol (rayon)."],
            ["COMSPEC_ATAK_Module_ZeusJammer", "Brouille le réseau autour du module. À poser au sol."],
            ["COMSPEC_ATAK_Module_Relay", "Crée un relais du réseau. À poser au sol ou sur un objet."],
            ["COMSPEC_ATAK_Module_Screen", "Fait d'un objet un écran de briefing. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_TocScreen", "Fait d'un objet un écran du poste TOC. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_AiAtak", "Donne ou retire l'ATAK à des IA. À poser sur une IA ou un groupe."],
            ["COMSPEC_ATAK_Module_DatingProfile", "Rend une IA visible dans Tinder. À poser sur l'IA."],
            ["COMSPEC_ATAK_Module_DataNode", "Rend un objet extractible au câble. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_DataFile", "Ajoute un fichier à extraire. À poser sur l'objet déjà préparé."],
            ["COMSPEC_ATAK_Module_DataReset", "Remet les données de l'objet à zéro. À poser sur l'objet."],
            ["COMSPEC_ModuleScanRelays", "Liste les relais de la carte."],
            ["COMSPEC_ModuleResyncRelay", "Republie l'état d'un relais. À poser sur le relais."],
            ["COMSPEC_SSE_Module_GenerateData", "Crée la fiche SSE d'une unité. À poser sur l'unité."],
            ["COMSPEC_SSE_Module_GenerateSite", "Génère un site et ses preuves. À poser au sol."],
            ["COMSPEC_SSE_Module_InitTarget", "Rend un objet ou un corps fouillable. À poser dessus."],
            ["COMSPEC_SSE_Module_PlaceEvidence", "Pose une preuve. À poser au sol."],
            ["COMSPEC_SSE_Module_ApplyModel", "Applique un modèle enregistré. À poser sur la cible."],
            ["COMSPEC_SSE_Module_SaveModel", "Enregistre la cible comme modèle. À poser sur la cible."],
            ["COMSPEC_SSE_Module_SandboxSite", "Pose un site d'exercice. À poser au sol."],
            ["COMSPEC_SSE_Module_LinkEntities", "Relie deux entités SSE. Sélectionnez-les d'abord."],
            ["COMSPEC_SSE_Module_ZeusControl", "Ouvre le contrôle Zeus de l'entité. À poser dessus."],
            ["COMSPEC_SSE_Module_ViewData", "Affiche les données d'une entité. À poser dessus."],
            ["COMSPEC_SSE_Module_SpoilView", "Compare ce que savent les joueurs et la vérité. À poser sur l'entité."],
            ["COMSPEC_SSE_Module_SiteManager", "Liste et pilote les sites."],
            ["COMSPEC_SSE_Module_AfterAction", "Ouvre le compte rendu et le graphe."],
            ["COMSPEC_SSE_Module_ListModels", "Liste les modèles enregistrés."],
            ["COMSPEC_SSE_Module_ClearData", "Efface les données SSE de la cible. À poser dessus."],
            ["COMSPEC_SSE_Module_ResetSite", "Remet un site à zéro. À poser sur le site."],
            ["COMSPEC_SSE_Module_DebugInspector", "Détail technique de l'entité. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexMark", "Rend un appareil exploitable. À poser sur l'objet."],
            ["COMSPEC_SSE_Module_DomexAddIntel", "Ajoute un renseignement à un appareil. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexSetStage", "Règle le palier d'accès d'un appareil. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexMapPoint", "Ajoute un point carte au renseignement. À poser sur l'objet."],
            ["COMSPEC_SSE_Module_PlaceSite", "Pose un site préparé. À poser au sol."],
            ["COMSPEC_SSE_Module_PopulateBuilding", "Remplit un bâtiment. À poser sur le bâtiment."],
            ["COMSPEC_SSE_Module_SeedEvidence", "Répartit des preuves. À poser au sol."],
            ["COMSPEC_SSE_Module_SiteNetwork", "Crée un réseau de sites. À poser au sol."],
            ["COMSPEC_SSE_Module_PlaceKit", "Pose une caisse de matériel. À poser au sol."],
            ["COMSPEC_SSE_Module_GenerateBrief", "Génère depuis un scénario. À poser au sol."],
            ["COMSPEC_SSE_Module_ScenarioDirector", "Applique un jeu de données ou un niveau. À poser au sol."],
            ["COMSPEC_SSE_Module_CaseReference", "Rattache la partie à un dossier. À poser au sol."],
            ["Capturer la carte", "Crée les tuiles de la carte autour du point."],
            ["Affecter à une organisation", "Sélectionnez des unités."],
            ["Générer un réseau", "Sélectionnez au moins deux unités."],
            ["Rattacher des objets", "Sélectionnez des objets."],
            ["Révéler une organisation", "La montre aux joueurs. Sélectionnez un membre."],
            ["Retirer d'une organisation", "Sélectionnez des unités."],
            ["Ouvrir le tableau des liens", "Vue Zeus, rien à sélectionner."],
            ["Altérer un scellé (exercice)", "Sélectionnez la pièce scellée."],
            ["Charger une campagne", "Charge ou crée la campagne."],
            ["Sauvegarder la campagne", "Sauvegarde maintenant."],
            ["Voir l'état de la campagne", "Affiche l'état et le journal."],
            ["Remettre la campagne à zéro", "Remet à zéro pour un exercice."],
            ["Clore la mission", "Sauvegarde ou vide à la fin."],
            ["Rendre extractible au câble", "Sélectionnez les objets."],
            ["Ouvrir le manuel", "Manuel du Zeus."],
            ["Poser un exercice", "Pose un exercice complet au point visé."],
            ["Ouvrir l'éditeur", "Éditeur de renseignement sur le point ou l'objet."],
            ["Donner des papiers", "À poser sur une personne."],
            ["Révoquer des papiers", "À poser sur une personne."],
            ["Voir les vrais papiers", "Réservé au Zeus. À poser sur une personne."],
            ["Poser un checkpoint IA", "À poser sur le point à contrôler."],
            ["Retirer un checkpoint IA", "À poser sur le checkpoint."],
            ["Déclencher un contrôle", "À poser sur la personne à contrôler."],
            ["Forcer un état IA", "À poser sur une IA."],
            ["Imposer un couvre-feu", "Règle le couvre-feu."],
            ["Donner une couverture", "À poser sur une personne."],
            ["Poser une zone interdite", "À poser au centre de la zone."],
            ["Désigner une HVT", "À poser sur une IA."],
            ["Lancer un BOLO", "À poser sur une personne, ou au sol."],
            ["Lever un BOLO", "Retire une alerte de la liste."],
            ["Définir l'identité", "À poser sur une personne."],
            ["Régler la biométrie", "À poser sur une personne."],
            ["Doter d'un terminal SEEK", "À poser sur un joueur."],
            ["Régler la présentation des documents", "Style des documents de la partie."],
            ["Poser une zone sans couverture", "À poser au centre de la zone."],
            ["Poser une zone d'interférence", "À poser au centre de la zone."],
            ["Poser une zone dégradée", "À poser au centre de la zone."],
            ["Poser un émetteur RF", "À poser au sol."],
            ["Relever la carte du théâtre", "Prépare la carte du site. Rien à sélectionner."],
            ["Relever routes et villes", "Prépare les routes et villes du site. Rien à sélectionner."],
            ["Poser une balise GPS", "À poser sur un véhicule."],
            ["Localiser un téléphone", "À poser sur une personne."],
            ["Ajouter une IA alliée à l'ATAK", "À poser sur une IA."],
            ["Afficher les IA ennemies", "Montre ou masque les IA ennemies sur l'ATAK."]
        ]
    };
};
