/*
    Mur d'images vu de la console du poste TOC (refonte 4.9.0, fn_tocLaptop, onglets MUR et SALLE). La console est la
    télécommande de l'opérateur pour le modèle d'écrans de la 4.8.0 ; elle ne le duplique pas :
      - contenu d'un écran ou d'une dalle : fn_tocScreen « apply » / « show » (inchangé) ;
      - salle, liens, scènes, allumage : fn_tocHall (inchangé, onglet SALLE : fn_tocHallUi) ;
      - diffusion d'un écran sur les autres et mire : fn_tocBroadcast « start » / « stop » / « do » (4.8.0) ;
      - mise en page des dalles d'un écran incurvé ou d'un poste à plusieurs moniteurs : fn_tocLayout « list » / « apply »
        / « current » (4.8.0) ;
      - luminosité : fn_tocScreen « view », clé « opt » (4.8.0) ; tableau de tous les écrans : fn_tocManager « open » (4.8.0).
    Chaque appel de la 4.8.0 est gardé : sur un PBO plus ancien le bouton est grisé et la raison s'écrit sur la ligne d'aide.
    Cible des actions « sur les écrans » de toute la console : les dalles 16:9 de la salle du poste ; sans salle, celles
    des écrans du camp à moins de 30 m (avant la 4.9.0 : toujours « à moins de 30 m »).
    Modes :
      ["screens"]            dalles 16:9 visées (objets) ; ["screens", "all"] : toutes les dalles ; ["screens", "hosts"] : écrans hôtes
      ["act", clé]           boutons de la console (table _btnDefs de fn_tocLaptop, cible W)
      ["refresh"]            état du panneau (onglets MUR et SALLE)
*/
params [["_mode", "refresh"], ["_a", ""]];
disableSerialization;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
private _ui = _st getOrDefault ["ui", createHashMap];
private _side = str side group player;
private _pc = _st getOrDefault ["obj", objNull];
private _hid = ["of", _pc] call comspec_atak_native_fnc_tocHall;
private _status = { ["status", _this] call comspec_atak_native_fnc_tocLaptop; };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _nameOf = { params ["_o"]; (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"] };
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
private _hosts = {
    if (_hid isNotEqualTo "") exitWith { ["screens", _hid] call comspec_atak_native_fnc_tocHall };
    (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select {
        !isNull _x && {alive _x} && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} && {(player distance _x) < 30}
            && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]}
    }
};
private _panels = {
    params [["_std", true]];
    private _out = [];
    {
        private _h = _x;
        private _np = (getNumber (configOf _h >> "comspec_screenPanels")) max 1;
        for "_k" from 0 to (_np - 1) do {
            private _po = ["panelOf", [_h, _k]] call comspec_atak_native_fnc_tocScreen;
            if (!isNull _po && {!_std || {((["format", _po] call comspec_atak_native_fnc_tocScreen) param [3, "STD"]) isEqualTo "STD"}}) then { _out pushBackUnique _po; };
        };
    } forEach (call _hosts);
    _out
};
// Fonctions de la 4.8.0 présentes dans ce PBO ?
private _has48 = { !isNil (format ["comspec_atak_native_fnc_%1", _this]) };
private _need48 = "Cette commande demande la version 4.8.0 du mod sur ce poste et sur le serveur";
private _selObj = { objectFromNetId (_st getOrDefault ["selH", ""]) };
switch (_mode) do {
    case "screens": {
        if (_a isEqualTo "hosts") exitWith { call _hosts };
        [_a isNotEqualTo "all"] call _panels
    };
    case "act": {
        if ((count _ui) isEqualTo 0) exitWith { false };
        private _o = call _selObj;
        private _all = [true] call _panels;
        private _apply = {
            params ["_po", "_view"];
            ["apply", [_po, _view]] call comspec_atak_native_fnc_tocScreen;
            if (_po getVariable ["COMSPEC_ATAK_TocOff", false]) then { ["do", ["power", [_po, true]]] call comspec_atak_native_fnc_tocHall; };
        };
        switch (_a) do {
            case "wSit"; case "wMap"; case "wOps": {
                if ((count _all) isEqualTo 0) exitWith { "Aucun écran 16:9 dans la salle de ce poste ni à moins de 30 m" call _status; };
                // Une diffusion en cours est arrêtée d'abord : chaque écran reprend la main.
                if (_hid isNotEqualTo "" && {"tocBroadcast" call _has48}) then { ["stop", ["hall", _hid]] call comspec_atak_native_fnc_tocBroadcast; };
                private _cycle = ["SPLIT", "LOG", "EXCHECK", "REQ", "CLOCK", "FEED"];
                { [_x, switch (_a) do { case "wSit": { "SPLIT" }; case "wMap": { "MAP" }; default { _cycle select (_forEachIndex mod (count _cycle)) }; }] call _apply; } forEach _all;
                (format [switch (_a) do { case "wSit": { "%1 écran(s) : carte et flux" }; case "wMap": { "%1 écran(s) : carte seule" }; default { "%1 écran(s) : carte, main courante, exécution, demandes, horloge, flux" }; }, count _all]) call _status;
            };
            case "wBcGo": {
                if !("tocBroadcast" call _has48) exitWith { _need48 call _status; };
                if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC (onglet SALLE)" call _status; };
                private _sc = _ui get "wSrc";
                private _src = objectFromNetId (_sc lbData ((lbCurSel _sc) max 0));
                if (isNull _src) exitWith { "Choisissez l'écran source de la diffusion" call _status; };
                private _n = ["start", [_src, "hall", _hid, []]] call comspec_atak_native_fnc_tocBroadcast;
                // « start » rend le nombre d'écrans servis (0 : source sans identifiant encore, ou aucun autre écran 16:9 lié).
                if (!isNil "_n" && {_n isEqualType 0} && {_n <= 0}) exitWith { "Diffusion refusée : aucun autre écran 16:9 lié à la salle, ou écran source pas encore prêt" call _status; };
                (format ["%1 est diffusé sur les écrans 16:9 de la salle", [_src] call _nameOf]) call _status;
            };
            case "wBcStop": {
                if !("tocBroadcast" call _has48) exitWith { _need48 call _status; };
                if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC (onglet SALLE)" call _status; };
                ["stop", ["hall", _hid]] call comspec_atak_native_fnc_tocBroadcast;
                "Diffusion arrêtée : chaque écran reprend son affichage d'avant" call _status;
            };
            case "wMire": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                if !("tocBroadcast" call _has48) exitWith { _need48 call _status; };
                if ((((["format", _o] call comspec_atak_native_fnc_tocScreen) param [3, "STD"])) isNotEqualTo "STD") exitWith { "La mire ne se montre que sur un écran ou une dalle 16:9" call _status; };
                ["do", [_o, "bars"]] call comspec_atak_native_fnc_tocBroadcast;
                (format ["%1 : mire", [_o] call _nameOf]) call _status;
            };
            case "wText": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                ["apply", [_o, "TEXT"]] call comspec_atak_native_fnc_tocScreen;
                ["edit", _o] call comspec_atak_native_fnc_tocText;
            };
            case "wManager": {
                if !("tocManager" call _has48) exitWith { _need48 call _status; };
                if (isNull _o) then { ["open"] call comspec_atak_native_fnc_tocManager; } else { ["open", [_o]] call comspec_atak_native_fnc_tocManager; };
            };
            case "wLayoutGo": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                if !("tocLayout" call _has48) exitWith { _need48 call _status; };
                private _lc = _ui get "wLayout";
                private _key = _lc lbData ((lbCurSel _lc) max 0);
                if (_key isEqualTo "") exitWith { "Cet écran n'a qu'une dalle : choisissez son contenu au-dessus" call _status; };
                ["apply", [[_o] call _hostOf, _key]] call comspec_atak_native_fnc_tocLayout;
                (format ["%1 : mise en page « %2 »", [[_o] call _hostOf] call _nameOf, _lc lbText (lbCurSel _lc)]) call _status;
            };
            case "wLumGo": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                if !("tocLayout" call _has48) exitWith { _need48 call _status; };
                private _lc = _ui get "wLum";
                private _lum = parseNumber (_lc lbData ((lbCurSel _lc) max 0));
                private _host = [_o] call _hostOf;
                ["view", [_host, "opt", [_lum, (_host getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0]]]] call comspec_atak_native_fnc_tocScreen;
                (format ["%1 : luminosité %2 %%", [_host] call _nameOf, _lum]) call _status;
            };
        };
        _st set ["hallSig", ""];
        _st set ["sig", ""];
        [{ ["refresh"] call comspec_atak_native_fnc_tocLaptop; }, [], 0.4] call comspec_atak_native_fnc_waitAndExec;
    };
    case "refresh": {
        if ((count _ui) isEqualTo 0) exitWith {};
        private _tab = _st getOrDefault ["tab", ""];
        private _o = call _selObj;
        private _has = !isNull _o;
        private _bc = "tocBroadcast" call _has48;
        private _lay = "tocLayout" call _has48;
        if (_tab isEqualTo "SALLE") then {
            // Écran choisi : texte, mire, tableau, mise en page de ses dalles, luminosité.
            { (_ui get _x) ctrlEnable _has; (_ui get _x) setVariable ["why", "Choisissez d'abord un écran sur le plan"]; } forEach ["wText", "wMire", "wLayoutGo", "wLumGo"];
            if (!_bc) then { (_ui get "wMire") ctrlEnable false; (_ui get "wMire") setVariable ["why", _need48]; };
            (_ui get "wManager") ctrlEnable ("tocManager" call _has48);
            (_ui get "wManager") setVariable ["why", _need48];
            if (!_lay) then { { (_ui get _x) ctrlEnable false; (_ui get _x) setVariable ["why", _need48]; } forEach ["wLayoutGo", "wLumGo"]; };
            private _opts = [["Une seule dalle : pas de mise en page", ""]];
            private _cur = "";
            if (_has && {_lay}) then {
                private _host = [_o] call _hostOf;
                private _l = ["list", _host] call comspec_atak_native_fnc_tocLayout;
                if (!isNil "_l" && {_l isEqualType []} && {(count _l) > 0}) then { _opts = _l apply { [_x select 0, _x select 1] }; };
                private _c = ["current", _host] call comspec_atak_native_fnc_tocLayout;
                if (!isNil "_c" && {_c isEqualType ""}) then { _cur = _c; };
            };
            private _lc = _ui get "wLayout";
            private _sig = [netId _o, _opts, _cur];
            if ((_lc getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _sig) then {
                _lc setVariable ["COMSPEC_ATAK_Sig", _sig];
                lbClear _lc;
                { private _i = _lc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _lc lbSetData [_i, _x select 1]; } forEach _opts;
                _lc lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _cur }) max 0);
                // Luminosité en cours de l'écran choisi.
                if (_has) then {
                    private _b = (([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [0, 100];
                    (_ui get "wLum") lbSetCurSel (([100, 75, 50, 25] findIf { _b >= _x - 12 }) max 0);
                };
            };
        };
        if (_tab isEqualTo "MUR") then {
            private _std = [true] call _panels;
            private _hall = ["get", _hid] call comspec_atak_native_fnc_tocHall;
            // Diffusions en cours (4.8.0) : écrans en affichage BROADCAST.
            private _nBc = { ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "BROADCAST" } count _std;
            private _txt = format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='@muted'>%2 écran(s) ou dalle(s) 16:9%3</t><br/><t size='0.85' color='@text2'>TOUT SUR SITUATION : carte et flux partout. MUR DE CONDUITE : carte, main courante, exécution, demandes, horloge et flux, dans l'ordre des écrans. TOUT SUR CARTE : carte seule partout.<br/>Un écran précis, son texte, sa mise en page ou sa luminosité : onglet SALLE, en cliquant l'écran sur le plan.</t>%4",
                [[_hall param [1, ""], "Sans salle : écrans à moins de 30 m"] select (_hid isEqualTo "")] call _esc, count _std,
                ["", format [" · %1 en diffusion", _nBc]] select (_nBc > 0),
                ["", "<br/><t size='0.85' color='@amber'>Diffusion et mire : version 4.8.0 du mod requise sur ce poste et sur le serveur.</t>"] select !_bc];
            private _wi = _ui get "wInfo";
            if ((_wi getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _txt) then {
                _wi setVariable ["COMSPEC_ATAK_Txt", _txt];
                _wi ctrlSetStructuredText parseText (["ink", _txt call comspec_atak_native_fnc_tr] call comspec_atak_native_fnc_tocTheme);
            };
            // Écran source de la diffusion : chaque dalle 16:9 de la salle.
            private _opts = _std apply { [format ["%1 · %2", [[_x] call _hostOf] call _nameOf, (["title", (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, "SPLIT"]] call comspec_atak_native_fnc_tocScreen) param [1, ""]], netId _x] };
            if ((count _opts) isEqualTo 0) then { _opts = [["Aucun écran 16:9 dans la salle", ""]]; };
            private _sc = _ui get "wSrc";
            if ((_sc getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
                _sc setVariable ["COMSPEC_ATAK_Sig", _opts];
                private _keep = _sc lbData ((lbCurSel _sc) max 0);
                lbClear _sc;
                { private _i = _sc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _sc lbSetData [_i, _x select 1]; } forEach _opts;
                _sc lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
            };
            { (_ui get _x) ctrlEnable (_bc && {_hid isNotEqualTo ""}); (_ui get _x) setVariable ["why", [_need48, "Créez d'abord la salle de ce PC (onglet SALLE)"] select _bc]; } forEach ["wBcGo", "wBcStop"];
            { (_ui get _x) ctrlEnable ((count _std) > 0); (_ui get _x) setVariable ["why", "Aucun écran 16:9 dans la salle de ce poste ni à moins de 30 m"]; } forEach ["wSit", "wOps", "wMap"];
        };
    };
    default { false };
};
