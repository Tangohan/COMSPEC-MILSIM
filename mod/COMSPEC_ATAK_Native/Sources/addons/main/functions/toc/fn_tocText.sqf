/*
    Affichage TEXTE des écrans TOC (demande de TTRD, 11/11 : « j'adore les écrans LCD horloge, faut la même chose pour
    écrire ce qu'on veut en texte et mode défilant quand c'est trop long »). Panneau à LED du même aspect que le bandeau
    horloge (fond noir, grands caractères à chasse fixe, émeraude par défaut) sur lequel l'opérateur écrit ce qu'il veut :
    bandeaux LED (4:1, 8:1, 16:1), écrans et dalles 16:9, colonnes d'un écran incurvé.
      - jusqu'à 8 messages montrés à tour de rôle, chacun avec sa couleur (vert, ambre, rouge, blanc), sa durée et son
        clignotement (alerte) ; 300 caractères par message ;
      - variables : {zulu} {zulus} {local} {date} {dtg} {h} (premier chrono « heure H », sinon premier chrono) {camp}
        {poste} (nom de l'écran) {groupe} (escouade liée à l'écran) ;
      - taille automatique : la plus grande qui tient dans la hauteur ; écran 16:9 : une ligne, sinon deux, sinon défilement ;
      - défilement : le texte plus large que le panneau défile de droite à gauche (lent, normal, rapide), en boucle avec
        un blanc entre deux passages ; « toujours défiler » : même un texte court ; un texte qui tient reste fixe ;
      - FLASH du poste : il remplace les messages (rouge, clignotant) tant qu'il dure.
    Le défilement ne coûte que devant un écran allumé : une seule boucle (30 images par seconde au plus) déplace les deux
    contrôles de texte des écrans en affichage TEXTE, allumés pour ce joueur (proches et regardés, fn_tocScreen « tick ») ;
    elle s'arrête d'elle-même quand il n'y en a plus. La position vient de l'heure du serveur (serverTime) : tous les
    joueurs voient à peu près le même endroit du texte. Largeur du texte : mesure du jeu (ctrlTextWidth) quand elle est
    plausible, sinon estimation par caractère (table _k).
    Réglage de l'écran : variable publique COMSPEC_ATAK_TocText [[[texte, couleur, durée, clignotement]...], vitesse,
    défilement, alignement, bordure], posée par le serveur (fn_tocScreen « view » clé « text »), nettoyée (« sanitize » :
    longueurs, nombres bornés, « < » et « > » remplacés : aucune balise de texte structuré), gardée avec l'écran
    (fn_tocPersist).
    Modes :
      ["schema"]                         paramètres de l'affichage [[clé, libellé, type, défaut, choix]...] (fenêtres de
                                         réglage construites dessus : éditeur ci-dessous, formulaire Zeus)
      ["default"]                        réglage par défaut
      ["sanitize", réglage]              réglage nettoyé
      ["parse", [texte, couleur, vitesse, défilement, alignement, bordure]]
                                         réglage depuis un texte de module : « message ; couleur ; durée ; clignotant | … »
      ["zeusFields"] / ["fromFields", valeurs]   champs du formulaire Zeus (fn_zeusForm) et réglage rendu
      ["marquee", [largeur du texte, largeur du panneau, vitesse, blanc, temps, toujours, passage unique]]
                                         [défile, x du premier texte, longueur de la boucle] (fonction pure)
      ["selfCheck"]                      contrôle de « marquee » sur la table _vectors : true si tout est juste
      ["draw", [écran, display, format]] image de l'écran (fn_tocScreen « draw », chaque seconde, écran allumé)
      ["edit", écran]                    fenêtre « Texte de l'écran » (aperçu en direct) : PC du poste, app Poste TOC, ACE
      ["edEvent", clé]                   événements de la fenêtre
*/
params [["_mode", "draw"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocText;
// ---- TABLE _schema (JSON) : [clé, libellé, type, défaut, choix]. Les quatre premiers : par message ; les suivants : par écran.
private _schema = [
["text", "Texte", "TEXT", "", []],
["color", "Couleur", "ENUM", "G", [["Vert", "G"], ["Ambre", "A"], ["Rouge", "R"], ["Blanc", "W"]]],
["secs", "Durée", "ENUM", 10, [["5 s", 5], ["10 s", 10], ["20 s", 20], ["30 s", 30], ["60 s", 60]]],
["blink", "Clignotement", "ENUM", 0, [["Fixe", 0], ["Clignotant (alerte)", 1]]],
["speed", "Vitesse", "ENUM", 1, [["Lent", 0], ["Normal", 1], ["Rapide", 2]]],
["scroll", "Défilement", "ENUM", 0, [["Si le texte dépasse", 0], ["Toujours défiler", 1]]],
["align", "Alignement", "ENUM", 0, [["Centré", 0], ["À gauche", 1], ["À droite", 2]]],
["border", "Bordure", "ENUM", 1, [["Sans", 0], ["Fine", 1]]]
];
// ---- FIN
// ---- TABLE _k (JSON) : charK = largeur estimée d'un caractère (part de la hauteur de police, chasse fixe grasse) ;
// cps = caractères par seconde (lent, normal, rapide) ; gapChars = blanc entre deux passages ; hz = images par seconde.
private _k = [["charK", 0.6], ["cps", [4, 7, 11]], ["gapChars", 6], ["maxLen", 300], ["maxMsgs", 8], ["hz", 30], ["blinkOn", 0.6]];
// ---- FIN
// ---- TABLE _vectors (JSON) : [largeur du texte, largeur du panneau, vitesse, blanc, temps, toujours, passage unique,
// défile attendu, x attendu, boucle attendue]. Rejoués par « selfCheck » en jeu et par tests/Unit/AtakTocTextTest.php.
private _vectors = [
[0.4, 1, 0.1, 0.05, 3, false, false, false, 0.3, 0],
[1, 1, 0.1, 0.05, 3, false, false, false, 0, 0],
[1.5, 1, 0.1, 0.05, 0, false, false, true, 0, 1.55],
[1.5, 1, 0.1, 0.05, 5, false, false, true, -0.5, 1.55],
[1.5, 1, 0.1, 0.05, 15.5, false, false, true, 0, 1.55],
[1.5, 1, 0.1, 0.05, 20.5, false, false, true, -0.5, 1.55],
[0.4, 1, 0.1, 0.05, 2, true, false, true, -0.2, 1],
[0.4, 1, 0.1, 0.05, 12, true, false, true, -0.2, 1],
[1.5, 1, 0.1, 0.05, 0, false, true, true, 1, 2.5],
[1.5, 1, 0.1, 0.05, 10, false, true, true, 0, 2.5],
[1.5, 1, 0.1, 0.05, 40, false, true, true, -1.5, 2.5]
];
// ---- FIN
private _kv = createHashMapFromArray _k;
private _tcol = { params ["_tok", ["_al", 1]]; ["rgba", [_tok, _al]] call comspec_atak_native_fnc_tocTheme };
private _tokOf = { (createHashMapFromArray [["G", "accent"], ["A", "amber"], ["R", "red"], ["W", "text"]]) getOrDefault [_this, "accent"] };
// Défilement (fonction pure) : texte qui tient et pas « toujours » : fixe, centré (x = marge de chaque côté). Boucle :
// deux textes espacés de la longueur de boucle (texte + blanc, jamais moins que le panneau), x du premier de 0 à
// -boucle. Passage unique (plusieurs messages à tour de rôle) : le texte entre par la droite et sort par la gauche.
private _marquee = {
    params ["_tw", "_pw", "_spd", "_gap", "_t", ["_always", false], ["_pass", false]];
    if (_tw <= _pw && {!_always}) exitWith { [false, (_pw - _tw) / 2, 0] };
    if (_pass) exitWith { private _len = _pw + _tw; [true, _pw - ((_t * _spd) min _len), _len] };
    private _loop = (_tw + _gap) max _pw;
    [true, -((_t * _spd) mod _loop), _loop]
};
// Texte saisi : une seule ligne, sans balise (« < » et « > » remplacés), coupé à la longueur permise.
private _clean = {
    params ["_s", ["_max", 300]];
    if !(_s isEqualType "") then { _s = str _s; };
    { _s = _s regexReplace [_x select 0, _x select 1]; } forEach [["[\r\n\t]+", " "], ["<", "‹"], [">", "›"]];
    _s = trim _s;
    forceUnicode 0;
    _s select [0, _max]
};
private _num = { params ["_v", "_def", "_lo", "_hi"]; if (_v isEqualType true) then { _v = parseNumber _v; }; if !(_v isEqualType 0) then { _v = _def; }; ((round _v) max _lo) min _hi };
private _default = [[["{poste}", "G", 10, 0]], 1, 0, 0, 1];
// Variables d'un message, remplacées à chaque image.
private _expand = {
    params ["_s", "_side", "_name", "_squad"];
    if ((_s find "{") < 0) exitWith { _s };
    private _two = { private _v = floor _this; [str _v, "0" + str _v] select (_v < 10) };
    private _mon = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
    private _zulu = ["zulu"] call comspec_atak_native_fnc_tocRoom;
    if !(_zulu isEqualType "") then { _zulu = "--:--:--"; };
    systemTimeUTC params ["_yy", "_mo", "_dd", "_hh", "_mi"];
    date params ["_my", "_mm", "_md"];
    private _tm = (["state", _side] call comspec_atak_native_fnc_tocRoom) param [1, []];
    private _ti = _tm findIf { (_x param [2, "UP"]) isEqualTo "AT" };
    private _hTxt = "--:--";
    if ((count _tm) > 0) then { _hTxt = (["timerText", _tm select (_ti max 0)] call comspec_atak_native_fnc_tocRoom) param [0, "--:--"]; };
    private _camp = ((createHashMapFromArray [["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "INDÉPENDANT"], ["CIV", "CIVIL"]]) getOrDefault [_side, _side]) call comspec_atak_native_fnc_tr;
    {
        if ((_s find (_x select 0)) >= 0) then { _s = [_s, _x select 0, _x select 1] call CBA_fnc_replace; };
    } forEach [
        ["{zulus}", _zulu], ["{zulu}", _zulu select [0, 5]], ["{local}", ([dayTime, "HH:MM"] call BIS_fnc_timeToString)],
        ["{date}", format ["%1 %2 %3", _md call _two, _mon select ((_mm - 1) max 0), _my]],
        ["{dtg}", format ["%1%2%3Z %4 %5", _dd call _two, _hh call _two, _mi call _two, _mon select ((_mo - 1) max 0), (_yy mod 100) call _two]],
        ["{h}", _hTxt], ["{camp}", _camp], ["{poste}", _name], ["{groupe}", _squad]
    ];
    _s
};
// Boucle du défilement : une seule pour tous les panneaux animés de ce poste (écrans allumés, aperçu de l'éditeur).
private _live = {
    params ["_d", "_key", "_o"];
    private _l = uiNamespace getVariable ["COMSPEC_ATAK_TxtLive", []];
    if ((_l findIf { (_x select 0) isEqualTo _d && {(_x select 1) isEqualTo _key} }) < 0) then { _l pushBack [_d, _key, _o]; uiNamespace setVariable ["COMSPEC_ATAK_TxtLive", _l]; };
    if (!isNil { missionNamespace getVariable "COMSPEC_ATAK_TxtPfh" }) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_TxtPfh", [{
        disableSerialization;
        // Panneaux encore animés : display vivant, animation posée ; écran du monde : allumé, en affichage TEXTE.
        private _l = (uiNamespace getVariable ["COMSPEC_ATAK_TxtLive", []]) select {
            _x params ["_d", "_key", "_o"];
            !isNull _d && {(count (_d getVariable [_key + "Anim", []])) > 0} && {isNull _o || {(_o getVariable ["COMSPEC_ATAK_TocOn", false]) && {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""}
                && {((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "TEXT"}}}
        };
        uiNamespace setVariable ["COMSPEC_ATAK_TxtLive", _l];
        if ((count _l) isEqualTo 0) exitWith {
            [missionNamespace getVariable "COMSPEC_ATAK_TxtPfh"] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_TxtPfh", nil];
        };
        // Plus de quatre panneaux animés : un sur deux à chaque passage (15 images par seconde chacun).
        private _fr = (uiNamespace getVariable ["COMSPEC_ATAK_TxtFrame", 0]) + 1;
        uiNamespace setVariable ["COMSPEC_ATAK_TxtFrame", _fr];
        private _half = (count _l) > 4;
        private _now = diag_tickTime;
        {
            if (_half && {((_fr + _forEachIndex) mod 2) isEqualTo 1}) then { continue };
            _x params ["_d", "_key", "_o"];
            (_d getVariable [_key + "Anim", []]) params ["_am", "_ca", "_cb", "_tw", "_pw", "_spd", "_gap", "_t0", "_tick0", "_blink", "_ya", "_ha", "_wa", "_sig", "_shown"];
            private _t = _t0 + (_now - _tick0);
            private _dirty = false;
            if (_am > 0) then {
                ([_tw, _pw, _spd, _gap, _t, true, _am isEqualTo 2] call (uiNamespace getVariable "COMSPEC_ATAK_TxtMarquee")) params ["", "_px", "_loop"];
                _ca ctrlSetPosition [_px, _ya, _wa, _ha]; _ca ctrlCommit 0;
                if (_am isEqualTo 1) then { _cb ctrlSetPosition [_px + _loop, _ya, _wa, _ha]; _cb ctrlCommit 0; };
                _dirty = true;
            };
            if (_blink) then {
                private _on = (_t mod 1) < 0.6;
                if (_on isNotEqualTo _shown) then {
                    (_d getVariable [_key + "Anim", []]) set [14, _on];
                    _ca ctrlSetFade ([1, 0] select _on); _ca ctrlCommit 0;
                    _cb ctrlSetFade ([1, 0] select _on); _cb ctrlCommit 0;
                    _dirty = true;
                };
            };
            if (_dirty && {!isNull _o}) then { displayUpdate _d; };
        } forEach _l;
    }, 1 / 30] call CBA_fnc_addPerFrameHandler];
};
uiNamespace setVariable ["COMSPEC_ATAK_TxtMarquee", _marquee];
// Panneau dessiné dans un rectangle (texture d'écran : tout le display ; éditeur : l'aperçu). _pxA : largeur / hauteur du
// rectangle en pixels réels ; _kind : "LED" (bandeau), "STD" (16:9), "COL" (colonne). Longueurs en « hauteurs de panneau ».
private _render = {
    params ["_d", "_par", "_rect", "_pxA", "_kind", "_cfg", "_ctx", "_key", "_o"];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    _cfg params ["_msgs", "_speed", "_scroll", "_align", "_border"];
    _ctx params ["_side", "_name", "_squad"];
    private _ui = _d getVariable [_key, createHashMap];
    if ((count _ui) isEqualTo 0) then {
        private _mk = { params ["_cls", "_p"]; private _c = if (isNull _p) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _p] }; _c ctrlSetPosition [0, 0, 0, 0]; _c ctrlCommit 0; _c };
        private _g = ["RscControlsGroupNoScrollbars", _par] call _mk;
        private _bg = ["RscText", _g] call _mk;
        private _bd = []; for "_i" from 1 to 4 do { _bd pushBack (["RscText", _g] call _mk); };
        private _clip = ["RscControlsGroupNoScrollbars", _g] call _mk;
        private _ca = ["COMSPEC_RscTocLabel", _clip] call _mk;
        private _cb = ["COMSPEC_RscTocLabel", _clip] call _mk;
        { _x ctrlSetFont "EtelkaMonospaceProBold"; } forEach [_ca, _cb];
        _ui = createHashMapFromArray [["g", _g], ["bg", _bg], ["bd", _bd], ["clip", _clip], ["a", _ca], ["b", _cb]];
        _d setVariable [_key, _ui];
    };
    private _g = _ui get "g";
    _g ctrlShow true;
    _g ctrlSetPosition _rect; _g ctrlCommit 0;
    // Une longueur l (en hauteurs de panneau) vaut l * _rh en y et l * _ux en x.
    private _ux = _rw / _pxA;
    (_ui get "bg") ctrlSetBackgroundColor (["bg0"] call _tcol);
    (_ui get "bg") ctrlSetPosition [0, 0, _rw, _rh]; (_ui get "bg") ctrlCommit 0;
    // Messages : FLASH du poste d'abord (il remplace tout), sinon ceux de l'écran.
    private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;
    if ((count _fl) >= 3) then { _msgs = [[format ["FLASH · %1 · %2 · %3Z", _fl select 0, _fl select 1, _fl select 2], "R", 10, 1]]; };
    if ((count _msgs) isEqualTo 0) then { _msgs = [["{poste}", "G", 10, 0]]; };
    private _ins = [0.03, 0.07] select (_kind isEqualTo "LED");
    private _bth = [0.006, 0.02] select (_kind isEqualTo "LED");
    private _padX = [0.05, 0.16] select (_kind isEqualTo "LED");
    private _f1 = (createHashMapFromArray [["LED", 0.6], ["STD", 0.26], ["COL", 0.15]]) getOrDefault [_kind, 0.26];
    private _f2 = (createHashMapFromArray [["STD", 0.19], ["COL", 0.12]]) getOrDefault [_kind, 0];
    private _ck = _kv get "charK";
    private _wi = _rw - 2 * (_ins + _bth + _padX) * _ux;
    private _hi = _rh * (1 - 2 * (_ins + _bth));
    private _spd = ((_kv get "cps") select _speed) * _ck * _f1 * _ux;
    private _gap = (_kv get "gapChars") * _ck * _f1 * _ux;
    private _always = _scroll > 0;
    // Tour de rôle : durée de chaque message (un texte qui défile dure au moins un passage), d'après l'estimation par
    // caractère, la même sur tous les postes.
    private _texts = _msgs apply { [_x select 0, _side, _name, _squad] call _expand };
    private _multi = (count _msgs) > 1;
    private _durs = [];
    {
        forceUnicode 0;
        private _est = (count _x) * _ck * _f1 * _ux;
        private _two = _f2 > 0 && {((count _x) * _ck * _f2 * _ux * 0.56) <= _wi};
        private _dur = (_msgs select _forEachIndex) select 2;
        if (_always || {_est > _wi && {!_two}}) then { _dur = _dur max ((_wi + _est) / _spd + 0.5); };
        _durs pushBack _dur;
    } forEach _texts;
    private _cycle = 0; { _cycle = _cycle + _x; } forEach _durs;
    private _ph = serverTime mod (_cycle max 1);
    private _mi = 0;
    private _acc = 0;
    { if (_ph >= _acc && {_ph < _acc + _x}) exitWith { _mi = _forEachIndex; }; _acc = _acc + _x; } forEach _durs;
    private _tLocal = _ph - _acc;
    (_msgs select _mi) params ["", "_colK", "", "_blk"];
    private _txt = _texts select _mi;
    private _tok = _colK call _tokOf;
    // Bordure fine, de la couleur du message (atténuée).
    {
        _x ctrlShow (_border > 0);
        _x ctrlSetBackgroundColor ([_tok, 0.55] call _tcol);
        _x ctrlSetPosition ([[_ins * _ux, _ins * _rh, _rw - 2 * _ins * _ux, _bth * _rh], [_ins * _ux, _rh - (_ins + _bth) * _rh, _rw - 2 * _ins * _ux, _bth * _rh],
            [_ins * _ux, _ins * _rh, _bth * _ux, _rh - 2 * _ins * _rh], [_rw - (_ins + _bth) * _ux, _ins * _rh, _bth * _ux, _rh - 2 * _ins * _rh]] select _forEachIndex);
        _x ctrlCommit 0;
    } forEach (_ui get "bd");
    private _clip = _ui get "clip";
    _clip ctrlSetPosition [(_ins + _bth + _padX) * _ux, (_ins + _bth) * _rh, _wi, _hi]; _clip ctrlCommit 0;
    private _ca = _ui get "a";
    private _cb = _ui get "b";
    // Largeur d'un texte à une taille : mesure du jeu si elle est plausible (entre la moitié et le double de l'estimation).
    private _measure = {
        params ["_c", "_s", "_f"];
        _c ctrlSetFontHeight (_f * _rh);
        _c ctrlSetText _s; // i18n:brut (texte saisi par l'opérateur)
        forceUnicode 0;
        private _est = (count _s) * _ck * _f * _ux;
        private _m = ctrlTextWidth _c;
        [_est, _m] select (_m > 0.5 * _est && {_m < 2 * _est})
    };
    { _x ctrlSetTextColor ([_tok] call _tcol); _x ctrlShow true; } forEach [_ca, _cb];
    private _tw = [_ca, _txt, _f1] call _measure;
    private _fit = _tw <= _wi && {!_always};
    private _lines = [];
    if (!_fit && {!_always} && {_f2 > 0}) then {
        // Deux lignes : coupure à l'espace le plus proche du milieu.
        forceUnicode 0;
        private _n = count _txt;
        private _best = -1;
        for "_i" from 1 to (_n - 2) do { if ((_txt select [_i, 1]) isEqualTo " " && {(abs (_i - _n / 2)) < (abs (_best - _n / 2)) || {_best < 0}}) then { _best = _i; }; };
        if (_best > 0) then {
            private _l1 = _txt select [0, _best];
            private _l2 = _txt select [_best + 1];
            private _w1 = [_ca, _l1, _f2] call _measure;
            private _w2 = [_cb, _l2, _f2] call _measure;
            if (_w1 <= _wi && {_w2 <= _wi}) then { _lines = [[_l1, _w1], [_l2, _w2]]; };
        };
        if ((count _lines) isEqualTo 0) then { _tw = [_ca, _txt, _f1] call _measure; };
    };
    private _xOf = { params ["_w"]; [(_wi - _w) / 2, 0, _wi - _w] select _align };
    private _pad = 0.2 * _f1 * _ux;
    private _sig = [_txt, _kind, _speed, _always, _multi, _mi, round (_rw * 1e4)];
    private _old = _d getVariable [_key + "Anim", []];
    private _anim = [];
    switch (true) do {
        case ((count _lines) isEqualTo 2): {
            private _lh = _f2 * 1.3 * _rh;
            private _y0 = (_hi - 2 * _lh) / 2;
            { _x params ["_c", "_ln", "_k2"]; _c ctrlSetFade 0; _c ctrlSetPosition [(([_ln select 1] call _xOf) - _pad / 2) max 0, _y0 + _k2 * _lh, (_ln select 1) + _pad, _lh]; _c ctrlCommit 0; } forEach [[_ca, _lines select 0, 0], [_cb, _lines select 1, 1]];
            if (_blk > 0) then { _anim = [0, _ca, _cb, 0, _wi, 0, 0, serverTime mod 1, diag_tickTime, true, 0, 0, 0, _sig, true]; };
        };
        case _fit: {
            private _lh = _f1 * 1.3 * _rh;
            _cb ctrlShow false;
            _ca ctrlSetFade 0;
            _ca ctrlSetPosition [(([_tw] call _xOf) - _pad / 2) max 0, (_hi - _lh) / 2, _tw + _pad, _lh]; _ca ctrlCommit 0;
            if (_blk > 0) then { _anim = [0, _ca, _cb, 0, _wi, 0, 0, serverTime mod 1, diag_tickTime, true, 0, 0, 0, _sig, true]; };
        };
        default {
            // Défilement : boucle (un seul message) ou passage unique (tour de rôle). Animation gardée telle quelle tant
            // que rien ne change et que l'heure du serveur ne s'en écarte pas (pas de saut à chaque seconde).
            private _lh = _f1 * 1.3 * _rh;
            private _am = [1, 2] select _multi;
            private _loop = (_tw + _gap) max _wi;
            private _t0 = if (_multi) then { _tLocal } else { serverTime mod (_loop / _spd) };
            _cb ctrlSetText _txt; // i18n:brut (texte saisi par l'opérateur)
            _cb ctrlSetFontHeight (_f1 * _rh);
            _cb ctrlShow (!_multi);
            private _keep = false;
            if ((count _old) >= 15 && {(_old select 13) isEqualTo _sig} && {(_old select 0) isEqualTo _am}) then {
                private _exp = (_old select 7) + (diag_tickTime - (_old select 8));
                private _dif = abs (_exp - _t0);
                if (!_multi) then { _dif = _dif min (abs ((_loop / _spd) - _dif)); };
                _keep = _dif < 0.25;
            };
            _anim = if (_keep) then { _old set [3, _tw]; _old set [9, _blk > 0]; _old } else {
                [_am, _ca, _cb, _tw, _wi, _spd, _gap, _t0, diag_tickTime, _blk > 0, (_hi - _lh) / 2, _lh, _tw + _pad, _sig, true]
            };
            if (!_keep) then {
                ([_tw, _wi, _spd, _gap, _t0, true, _multi] call _marquee) params ["", "_px", "_lp"];
                { _x ctrlSetFade 0; } forEach [_ca, _cb];
                _ca ctrlSetPosition [_px, (_hi - _lh) / 2, _tw + _pad, _lh]; _ca ctrlCommit 0;
                _cb ctrlSetPosition [_px + _lp, (_hi - _lh) / 2, _tw + _pad, _lh]; _cb ctrlCommit 0;
            };
        };
    };
    _d setVariable [_key + "Anim", _anim];
    if ((count _anim) > 0) then { [_d, _key, _o] call _live; };
    [_txt, (count _anim) > 0 && {(_anim select 0) > 0}, count _lines]
};
switch (_mode) do {
    case "schema": { _schema };
    case "default": { +_default };
    case "marquee": { _a call _marquee };
    case "selfCheck": {
        private _bad = _vectors select {
            _x params ["_tw", "_pw", "_spd", "_gap", "_t", "_always", "_pass", "_eS", "_eX", "_eL"];
            ([_tw, _pw, _spd, _gap, _t, _always, _pass] call _marquee) params ["_s", "_px", "_lp"];
            _s isNotEqualTo _eS || {(abs (_px - _eX)) > 0.001} || {(abs (_lp - _eL)) > 0.001}
        };
        if (_bad isNotEqualTo []) then { ["WARNING", "TOC", format ["Affichage TEXTE : défilement faux sur %1 cas (%2)", count _bad, _bad select 0]] call comspec_atak_native_fnc_log; };
        (count _bad) isEqualTo 0
    };
    case "sanitize": {
        private _c = _a;
        if !(_c isEqualType []) then { _c = []; };
        private _in = _c param [0, []];
        if !(_in isEqualType []) then { _in = []; };
        private _out = [];
        {
            private _m = _x;
            if (_m isEqualType "") then { _m = [_m]; };
            if (_m isEqualType [] && {(count _out) < (_kv get "maxMsgs")}) then {
                private _t = [_m param [0, ""], _kv get "maxLen"] call _clean;
                private _col = _m param [1, "G"];
                if !(_col in ["G", "A", "R", "W"]) then { _col = "G"; };
                if (_t isNotEqualTo "") then { _out pushBack [_t, _col, [_m param [2, 10], 10, 3, 600] call _num, [_m param [3, 0], 0, 0, 1] call _num]; };
            };
        } forEach _in;
        [_out, [_c param [1, 1], 1, 0, 2] call _num, [_c param [2, 0], 0, 0, 1] call _num, [_c param [3, 0], 0, 0, 2] call _num, [_c param [4, 1], 1, 0, 1] call _num]
    };
    case "parse": {
        _a params [["_s", ""], ["_col", "G"], ["_speed", 1], ["_scroll", 0], ["_align", 0], ["_border", 1]];
        if !(_s isEqualType "") then { _s = ""; };
        private _cm = createHashMapFromArray [["VERT", "G"], ["GREEN", "G"], ["G", "G"], ["AMBRE", "A"], ["AMBER", "A"], ["A", "A"], ["ROUGE", "R"], ["RED", "R"], ["R", "R"], ["BLANC", "W"], ["WHITE", "W"], ["W", "W"]];
        private _msgs = (_s splitString "|") apply {
            private _p = (_x splitString ";") apply { trim _x };
            [_p param [0, ""], _cm getOrDefault [toUpper (_p param [1, ""]), _col], [10, parseNumber (_p param [2, "10"])] select ((count _p) >= 3),
                parseNumber (((toUpper (_p param [3, ""])) select [0, 1]) in ["C", "B", "1", "O", "Y"])]
        };
        ["sanitize", [_msgs, _speed, _scroll, _align, _border]] call _fn
    };
    case "zeusFields": {
        // Champs du formulaire Zeus (fn_zeusForm) tirés de la table : texte, puis un choix par paramètre d'écran et la couleur.
        private _pick = { params ["_key", "_lbl"]; private _e = _schema select (_schema findIf { (_x select 0) isEqualTo _key }); ["COMBO", _lbl, _e select 4, _e select 3] };
        [["EDIT", "Texte (affichage Texte ; messages séparés par |, « message ; couleur ; durée ; clignotant »)", ""],
            ["color", "Texte : couleur"] call _pick, ["speed", "Texte : vitesse du défilement"] call _pick, ["scroll", "Texte : défilement"] call _pick]
    };
    case "fromFields": {
        _a params [["_s", ""], ["_col", "G"], ["_speed", 1], ["_scroll", 0]];
        if (!(_s isEqualType "") || {(trim _s) isEqualTo ""}) exitWith { [] };
        ["parse", [_s, _col, _speed, _scroll]] call _fn
    };
    case "draw": {
        _a params [["_o", objNull], ["_d", displayNull], ["_fmt", []]];
        if (isNull _o || {isNull _d}) exitWith { false };
        _fmt params [["_ratio", 1.778], "", ["_pxA", 2], ["_kind", "STD"]];
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _side = [_c param [1, ""], str side group player] select ((_c param [1, ""]) isEqualTo "");
        private _cfg = _o getVariable ["COMSPEC_ATAK_TocText", []];
        if !(_cfg isEqualType [] && {(count _cfg) >= 5} && {(count (_cfg select 0)) > 0}) then { _cfg = _default; };
        [_d, controlNull, [0, 0, 1, 1], _pxA, ["LED", _kind] select (_kind in ["STD", "COL"]), _cfg, [_side, _c param [0, "TOC"], _c param [9, ""]], "COMSPEC_ATAK_TxtUi", _o] call _render;
        true
    };
    case "edit": {
        // Fenêtre « Texte de l'écran » : liste des messages, texte, réglages (construits depuis la table _schema), aperçu
        // en direct au format de l'écran, AFFICHER SUR L'ÉCRAN. Ouverte par-dessus ce qui est ouvert (PC, téléphone, jeu).
        private _o = _a;
        if (!hasInterface || {isNull _o}) exitWith { false };
        private _tops = allDisplays select { !isNull _x && {(displayUniqueName _x) isEqualTo ""} };
        private _parent = _tops param [(count _tops) - 1, findDisplay 46];
        if (isNull _parent) exitWith { false };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        private _cfg = +(_o getVariable ["COMSPEC_ATAK_TocText", []]);
        if !(_cfg isEqualType [] && {(count _cfg) >= 5} && {(count (_cfg select 0)) > 0}) then { _cfg = [[["", "G", 10, 0]], 1, 0, 0, 1]; };
        private _fmt = ["format", _o] call comspec_atak_native_fnc_tocScreen;
        private _st = createHashMapFromArray [["o", _o], ["d", _d], ["cfg", _cfg], ["sel", 0], ["fmt", _fmt], ["busy", true]];
        uiNamespace setVariable ["COMSPEC_ATAK_TxtEd", _st];
        private _w = safeZoneW * 0.46;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _rh = safeZoneH * 0.034;
        private _gp = safeZoneH * 0.008;
        private _pd = safeZoneW * 0.012;
        private _prevH = safeZoneH * 0.13;
        private _h = _pd * 2 + _rh * 1.3 + _prevH + _rh * 4 + _rh + 4 * _rh + _rh * 1.9 + _rh * 1.15 + 11 * _gp;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _ui = createHashMap;
        private _mk = {
            params ["_cls", "_key", "_pos"];
            private _c = _d ctrlCreate [_cls, -1];
            _c ctrlSetPosition _pos; _c ctrlCommit 0;
            if (_key isNotEqualTo "") then { _ui set [_key, _c]; };
            _c
        };
        (["COMSPEC_RscText", "", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor (["bg1", 0.98] call _tcol);
        (["COMSPEC_RscText", "", [_x0, _y0, _w, safeZoneH * 0.003]] call _mk) ctrlSetBackgroundColor (["accent"] call _tcol);
        private _iw = _w - 2 * _pd;
        private _ix = _x0 + _pd;
        private _cy = _y0 + _pd;
        private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        (["COMSPEC_RscStructuredText", "title", [_ix, _cy, _iw, _rh * 1.3]] call _mk) ctrlSetStructuredText parseText (format ["<t size='1.25' font='COMSPEC_BarlowBold'>%1 · %2</t>",
            "Texte de l'écran" call comspec_atak_native_fnc_tr, [(_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, getText (configOf _o >> "displayName")]] call _esc]);
        _cy = _cy + _rh * 1.3 + _gp;
        // Aperçu : rectangle au format réel de l'écran, centré.
        private _ratio = (_fmt param [0, 1.778]) max 0.5;
        private _ph = _prevH min (_iw * pixelH / (pixelW * _ratio));
        private _pw = _ratio * _ph * pixelW / pixelH;
        (["COMSPEC_RscText", "", [_ix, _cy, _iw, _prevH]] call _mk) ctrlSetBackgroundColor (["bg2"] call _tcol);
        _st set ["prev", [_ix + (_iw - _pw) / 2, _cy + (_prevH - _ph) / 2, _pw, _ph]];
        _st set ["prevPx", _ratio];
        _cy = _cy + _prevH + _gp;
        // Messages : liste à gauche, boutons à droite.
        private _lw = _iw * 0.74;
        private _lb = ["COMSPEC_RscTocList", "list", [_ix, _cy, _lw, _rh * 4]] call _mk;
        _lb ctrlAddEventHandler ["LBSelChanged", { ["edEvent", "pick"] call comspec_atak_native_fnc_tocText; }];
        {
            _x params ["_key", "_lbl"];
            private _b = ["COMSPEC_RscTocButton", _key, [_ix + _lw + _gp, _cy + _forEachIndex * _rh, _iw - _lw - _gp, _rh - pixelH * 2]] call _mk;
            _b ctrlSetText (_lbl call comspec_atak_native_fnc_tr);
            _b setVariable ["COMSPEC_ATAK_Key", _key];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["edEvent", _c getVariable ["COMSPEC_ATAK_Key", ""]] call comspec_atak_native_fnc_tocText; }];
        } forEach [["add", "AJOUTER UN MESSAGE"], ["del", "RETIRER"], ["up", "MONTER"], ["down", "DESCENDRE"]];
        _cy = _cy + _rh * 4 + _gp;
        private _ed = ["COMSPEC_RscTocEdit", "text", [_ix, _cy, _iw, _rh]] call _mk;
        _ed ctrlAddEventHandler ["KeyUp", { ["edEvent", "change"] call comspec_atak_native_fnc_tocText; }];
        _cy = _cy + _rh + _gp;
        // Un choix par paramètre de la table (tous sauf le texte), sur deux colonnes : libellé à gauche, choix à droite.
        private _cw = (_iw - _gp) / 2;
        {
            _x params ["_key", "_lbl", "", "", "_opts"];
            private _col = _forEachIndex mod 2;
            private _fx = _ix + _col * (_cw + _gp);
            private _fy = _cy + (floor (_forEachIndex / 2)) * (_rh + _gp);
            (["COMSPEC_RscTocLbl", "", [_fx, _fy, _cw * 0.36, _rh]] call _mk) ctrlSetText (_lbl call comspec_atak_native_fnc_tr);
            private _cb = ["COMSPEC_RscTocCombo", "f_" + _key, [_fx + _cw * 0.36, _fy, _cw * 0.64, _rh]] call _mk;
            { _cb lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); } forEach _opts;
            _cb ctrlAddEventHandler ["LBSelChanged", { ["edEvent", "change"] call comspec_atak_native_fnc_tocText; }];
        } forEach (_schema select [1]);
        _cy = _cy + 4 * (_rh + _gp);
        ["COMSPEC_RscStructuredText", "hint", [_ix, _cy, _iw, _rh * 1.9]] call _mk;
        _cy = _cy + _rh * 1.9 + _gp;
        {
            _x params ["_key", "_lbl", "_cls", "_fx", "_fw"];
            private _b = [_cls, _key, [_ix + _iw * _fx, _cy, _iw * _fw - _gp, _rh * 1.15]] call _mk;
            _b ctrlSetText (_lbl call comspec_atak_native_fnc_tr);
            _b setVariable ["COMSPEC_ATAK_Key", _key];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["edEvent", _c getVariable ["COMSPEC_ATAK_Key", ""]] call comspec_atak_native_fnc_tocText; }];
        } forEach [["apply", "AFFICHER SUR L'ÉCRAN", "COMSPEC_RscTocButtonPrimary", 0, 0.62], ["close", "FERMER", "COMSPEC_RscTocButton", 0.62, 0.38]];
        _st set ["ui", _ui];
        _st set ["busy", false];
        ["edEvent", "load"] call _fn;
        ctrlSetFocus _ed;
        true
    };
    case "edEvent": {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_TxtEd", createHashMap];
        private _d = _st getOrDefault ["d", displayNull];
        if (isNull _d || {_st getOrDefault ["busy", false]}) exitWith { false };
        private _ui = _st get "ui";
        private _cfg = _st get "cfg";
        private _msgs = _cfg select 0;
        private _sel = ((_st get "sel") max 0) min ((count _msgs) - 1);
        private _o = _st get "o";
        private _note = "";
        // Champs -> réglage (message choisi et paramètres de l'écran).
        private _read = {
            private _m = _msgs select _sel;
            _m set [0, ctrlText (_ui get "text")];
            {
                _x params ["_key", "", "", "_def", "_opts"];
                private _v = ((_opts param [(lbCurSel (_ui get ("f_" + _key))) max 0, [0, _def]]) select 1);
                private _i = _forEachIndex + 1;
                if (_i <= 3) then { _m set [_i, _v]; } else { _cfg set [_i - 3, _v]; };
            } forEach (_schema select [1]);
        };
        switch (_a) do {
            case "close": { _d closeDisplay 2; };
            case "change": { call _read; };
            case "pick": { _sel = (lbCurSel (_ui get "list")) max 0; _st set ["sel", _sel]; };
            case "add": {
                call _read;
                if ((count _msgs) >= (_kv get "maxMsgs")) exitWith { _note = "Huit messages au plus"; };
                _msgs pushBack ["", "G", 10, 0];
                _sel = (count _msgs) - 1;
                _st set ["sel", _sel];
            };
            case "del": {
                if ((count _msgs) <= 1) exitWith { (_msgs select 0) set [0, ""]; };
                _msgs deleteAt _sel;
                _sel = _sel min ((count _msgs) - 1);
                _st set ["sel", _sel];
            };
            case "up"; case "down": {
                call _read;
                private _to = _sel + ([1, -1] select (_a isEqualTo "up"));
                if (_to < 0 || {_to >= (count _msgs)}) exitWith {};
                private _tmp = _msgs select _to;
                _msgs set [_to, _msgs select _sel];
                _msgs set [_sel, _tmp];
                _sel = _to;
                _st set ["sel", _sel];
            };
            case "apply": {
                call _read;
                private _send = ["sanitize", _cfg] call _fn;
                if ((count (_send select 0)) isEqualTo 0) exitWith { _note = "Écrivez d'abord un message"; };
                if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) < 8) then { ["ensure", [_o, "TEXT"]] call comspec_atak_native_fnc_tocScreen; };
                ["view", [_o, "text", _send]] call comspec_atak_native_fnc_tocScreen;
                ["apply", [_o, "TEXT"]] call comspec_atak_native_fnc_tocScreen;
                _o setVariable ["COMSPEC_ATAK_TocAt", -10];
                _note = "Texte envoyé à l'écran";
            };
        };
        if (isNull _d || {_a isEqualTo "close"}) exitWith { true };
        // Réglage -> champs (sauf pendant la frappe : le texte en cours n'est pas réécrit).
        _st set ["busy", true];
        private _m = _msgs select _sel;
        private _lb = _ui get "list";
        lbClear _lb;
        {
            forceUnicode 0;
            private _t = _x select 0;
            _lb lbAdd format ["%1 · %2", _forEachIndex + 1, [[_t, (_t select [0, 60]) + "…"] select ((count _t) > 61), "(message vide)" call comspec_atak_native_fnc_tr] select (_t isEqualTo "")]; // i18n:brut (texte saisi)
            _lb lbSetColor [_forEachIndex, [(_x select 1) call _tokOf] call _tcol];
        } forEach _msgs;
        _lb lbSetCurSel _sel;
        if (_a isNotEqualTo "change") then {
            (_ui get "text") ctrlSetText (_m select 0); // i18n:brut (texte saisi)
        };
        {
            _x params ["_key", "", "", "", "_opts"];
            private _i = _forEachIndex + 1;
            private _v = if (_i <= 3) then { _m select _i } else { _cfg select (_i - 3) };
            (_ui get ("f_" + _key)) lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _v }) max 0);
        } forEach (_schema select [1]);
        _st set ["busy", false];
        forceUnicode 0;
        private _n = count (_m select 0);
        (_ui get "hint") ctrlSetStructuredText parseText (["ink", format ["<t size='0.8' color='@muted'>%1 {zulu} {zulus} {local} {date} {dtg} {h} {camp} {poste} {groupe}</t><br/><t size='0.8' color='@%2'>%3</t><t size='0.8' align='right' color='@%4'>%5 / %6</t>",
            "Variables :" call comspec_atak_native_fnc_tr, ["text2", "amber"] select (_note isNotEqualTo ""),
            [_note, "Le texte plus large que l'écran défile ; les messages passent à tour de rôle"] select (_note isEqualTo "") call comspec_atak_native_fnc_tr,
            ["muted", "red"] select (_n > (_kv get "maxLen")), _n, _kv get "maxLen"]] call comspec_atak_native_fnc_tocTheme);
        // Aperçu : le message choisi, seul, au format de l'écran.
        private _fmt = _st get "fmt";
        private _one = ["sanitize", [[_m], _cfg select 1, _cfg select 2, _cfg select 3, _cfg select 4]] call _fn;
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _kind = _fmt param [3, "STD"];
        [_d, controlNull, _st get "prev", _st get "prevPx", ["LED", _kind] select (_kind in ["STD", "COL"]), _one,
            [[_c param [1, ""], str side group player] select ((_c param [1, ""]) isEqualTo ""), _c param [0, "TOC"], _c param [9, ""]], "COMSPEC_ATAK_TxtPrev", objNull] call _render;
        true
    };
    default { false };
};
