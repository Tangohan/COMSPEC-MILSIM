/*
    Calque Wave Relay sur la carte (appelé depuis mapOnDraw), réglage profil COMSPEC_ATAK_MeshOnMap :
    - couverture réseau calculée (fn_relayNet "coverage") : vert débit fort, jaune, orange, rouge faible, gris zone blanche ;
    - liens du maillage entre téléphones, vert au-dessus de 60 %, orange au-dessus de 30 %, rouge en dessous ; au plus un
      trait par paire d'unités, tous systèmes confondus (liste de l'image : uiNamespace COMSPEC_ATAK_MeshDrawn) ;
    - hors maillage (réglage mesh_miss_map) : un trait rouge en tirets vers le nœud qui manque, avec la cause (source "miss").
    Calque « Relais » (réglage profil COMSPEC_ATAK_RelaysLayer, affiché par défaut, masquable dans CALQUES) : relais et
    passerelles de véhicule ou PC mobiles en pastille (anneau de couleur sur fond sombre, antenne), nom puis
    « portée · débit · places » en dessous ; vert : relais utilisé par mon téléphone (trait pointillé vers moi, portée
    tracée), bleu : disponible, orange : saturé ou batterie faible, gris : hors service avec la raison, rouge barré :
    détruit ; répéteurs reliés en pointillé au relais qui les raccorde ; portées des autres relais en léger quand on zoome,
    textes masqués en vue très large. L'étiquette donne aussi la fréquence, la perte et la santé si elle n'est pas nominale ;
    troisième anneau : relais préféré. Clic gauche sur une pastille : fiche du relais (fn_mapCtxMenu).
    Toujours affichées (même calque masqué) : les triangulations récentes (fn_relayNet "locate"), cercles de distance
    estimée depuis chaque source et zone d'incertitude autour de la position trouvée, pâlissant sur 10 min.
    Params : [contrôle carte]
*/
params ["_map"];
private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
// Cercles de taille constante à l'écran, quel que soit le zoom.
private _r = (_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [0.008, 0]);
// Ma position affichée (GPS simulé) : mes cercles et mes traits restent centrés sur mon point (fn_gpsPos).
private _selfP = [] call comspec_atak_native_fnc_gpsPos;
private _selfObjs = [player, vehicle player];
// Traits entre deux unités déjà dessinés dans cette image (maillage, trait vers mon relais, répéteur vers son relais) :
// une paire = un seul trait, quel que soit le sens ou le système qui le demande ; moi et mon véhicule comptent pour un.
// Liste [objet A, objet B, source] exposée à chaque image (uiNamespace COMSPEC_ATAK_MeshDrawn), lue par l'autotest.
private _pairs = createHashMap;
private _pairList = [];
private _pairAdd = {
    params ["_oa", "_ob", "_src"];
    if (_oa in _selfObjs) then { _oa = player; };
    if (_ob in _selfObjs) then { _ob = player; };
    if (_oa isEqualTo _ob) exitWith { false };
    private _ka = str _oa;
    private _kb = str _ob;
    if ((_ka + "|" + _kb) in _pairs || {(_kb + "|" + _ka) in _pairs}) exitWith { false };
    _pairs set [_ka + "|" + _kb, _src];
    _pairList pushBack [_oa, _ob, _src];
    true
};
if (profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then {
    private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
    if ((count _cv) >= 8) then {
        _cv params ["", "_cell", "_cells"];
        private _a = _cell / 2;
        (ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
        private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
        private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
        private _x0 = ((_tl select 0) min (_br select 0)) - _cell;
        private _x1 = ((_tl select 0) max (_br select 0)) + _cell;
        private _y0 = ((_tl select 1) min (_br select 1)) - _cell;
        private _y1 = ((_tl select 1) max (_br select 1)) + _cell;
        {
            _x params ["_p", "_mb"];
            if ((_p select 0) < _x0 || {(_p select 0) > _x1} || {(_p select 1) < _y0} || {(_p select 1) > _y1}) then { continue };
            private _col = switch (true) do {
                case (_mb >= 4): { [0.2, 0.85, 0.3, 0.28] };
                case (_mb >= 1.5): { [0.75, 0.85, 0.2, 0.28] };
                case (_mb >= 0.5): { [1, 0.6, 0.15, 0.28] };
                case (_mb > 0): { [0.9, 0.2, 0.15, 0.3] };
                default { [0.15, 0.15, 0.15, 0.3] };
            };
            _map drawRectangle [_p, _a, _a, 0, _col, _fill];
        } forEach _cells;
    };
    // Lien manquant (4.2.1, réglage mesh_miss_map) : hors maillage, un seul trait rouge en tirets entre mon îlot et le nœud le plus proche à
    // joindre, avec sa cause au milieu (fn_meshGraph "view" -> "miss"). Aucun calcul ici : lecture de la dernière vue du maillage.
    if ((missionNamespace getVariable ["comspec_atak_native_mesh_miss_map", true]) isEqualTo true) then {
        private _mm = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        (_mm getOrDefault ["miss", []]) params [["_oa", objNull], ["_ob", objNull], ["_lbl", ""]];
        if (!isNull _oa && {!isNull _ob} && {diag_tickTime - (_mm getOrDefault ["at", -100]) < 20} && {[_oa, _ob, "miss"] call _pairAdd}) then {
            private _pa = if (_oa in _selfObjs) then { _selfP } else { getPosWorld _oa };
            private _pb = getPosWorld _ob;
            private _len = _pa distance2D _pb;
            private _dir = _pa getDir _pb;
            private _dl = (_r * 2) max (_len / 160);
            for "_k" from 0 to (floor (_len / _dl)) step 2 do {
                _map drawLine [_pa getPos [_k * _dl, _dir], _pa getPos [((_k + 1) * _dl) min _len, _dir], [0.9, 0.25, 0.2, 0.9]];
            };
            private _mid = _pa getPos [_len / 2, _dir];
            private _t = _lbl call comspec_atak_native_fnc_tr;
            ["nameBgDraw", [_map, _mid, _t, COMSPEC_ATAK_FontSet select 0, 0.024 * _ts]] call comspec_atak_native_fnc_mapPrefs;
            _map drawIcon [_none, [1, 0.45, 0.35, 1], _mid, 0, 0, 0, _t, 2, 0.024 * _ts, COMSPEC_ATAK_FontSet select 0, "right"];
        };
    };
    // Traits entre alliés et cercles des téléphones : bascule « Traits entre alliés » (masqués par défaut, 2.9.38) ;
    // masqués, rien n'est calculé ni dessiné.
    if !(profileNamespace getVariable ["COMSPEC_ATAK_MeshLinks", false]) exitWith {};
    // Le calcul (lignes de vue entre antennes) est mis en cache 3 s par l'action « scan ».
    private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
    private _nodes = _mesh getOrDefault ["nodes", []];
    private _hops = _mesh getOrDefault ["hops", []];
    // Positions relues à chaque image : les traits suivent les joueurs entre deux calculs. Même point que leur symbole :
    // moi à ma position affichée, un allié joueur à son dernier point reçu (avant : vraie position, cercles décalés du symbole).
    private _pliOn = ["pli"] call comspec_atak_native_fnc_netCond;
    private _pos = _nodes apply {
        private _u = _x select 0;
        if (!alive _u) then { _x select 1 } else {
            if (_u in _selfObjs) then { _selfP } else {
                private _pl = [[], _u getVariable ["COMSPEC_ATAK_Pli", []]] select _pliOn;
                if (_pl isEqualType [] && {(count _pl) >= 3}) then { _pl select 0 } else { getPosWorld _u }
            }
        }
    };
    // Moins de traits : un seul chemin (les meilleurs liens) dans chaque groupe, puis un seul entre groupes, au lieu d'un
    // trait par paire à portée. Ossature entre groupes : un seul trait par liaison (avant : un second trait décalé, vu comme « deux traits » entre deux alliés) ; traits internes, plus discrets, seulement pour mon
    // groupe et en zoom rapproché. Tri refait quand les liens changent (au plus toutes les 3 s, action « scan »).
    private _lnk = _mesh getOrDefault ["links", []];
    private _drawn = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeep", [[], []]];
    if ((_drawn select 0) isNotEqualTo _lnk) then {
        private _parent = [];
        for "_i" from 0 to ((count _nodes) - 1) do { _parent pushBack _i; };
        private _root = {
            params ["_i", "_tree"];
            while { (_tree select _i) isNotEqualTo _i } do { _i = _tree select _i; };
            _i
        };
        private _grpOf = _nodes apply { group (_x select 0) };
        private _best = _lnk apply { [-(_x select 2), _x select 0, _x select 1] };
        _best sort true;
        private _keep = [];
        {
            private _inner = _x;
            {
                _x params ["_nq", "_a", "_b"];
                if (_inner isEqualTo ((_grpOf select _a) isEqualTo (_grpOf select _b))) then {
                    private _ra = [_a, _parent] call _root;
                    private _rb = [_b, _parent] call _root;
                    if (_ra isNotEqualTo _rb) then {
                        _parent set [_ra, _rb];
                        _keep pushBack [_a, _b, -_nq, _inner];
                    };
                };
            } forEach _best;
        } forEach [true, false];
        _drawn = [+_lnk, _keep];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshKeep", _drawn];
    };
    private _nearZoom = (ctrlMapScale _map) < 0.06;
    private _myG = group player;
    {
        _x params ["_a", "_b", "_q", "_inner"];
        if ((count _pos) <= (_a max _b)) then { continue };
        if (_inner && {!_nearZoom} && {(group ((_nodes select _a) select 0)) isNotEqualTo _myG}) then { continue };
        // Un seul trait par paire d'unités : deux nœuds du même porteur (moi et mon véhicule, radio portée) ou un lien
        // reçu dans les deux sens (A-B puis B-A) ne redessinent pas un second trait, décalé de quelques mètres.
        if !([(_nodes select _a) select 0, (_nodes select _b) select 0, "mesh"] call _pairAdd) then { continue };
        private _col = switch (true) do { case (_q >= 60): { [0.36, 0.85, 0.42] }; case (_q >= 30): { [1, 0.65, 0.2] }; default { [0.9, 0.25, 0.2] }; };
        _col pushBack ([0.95, 0.5] select _inner);
        _map drawLine [_pos select _a, _pos select _b, _col];
    } forEach (_drawn select 1);
    {
        _x params ["", "", "_name", "_kind"];
        if (_kind isEqualTo "gw") then { continue };
        private _p = _pos select _forEachIndex;
        private _isolated = (_hops param [_forEachIndex, -1]) < 0;
        _map drawEllipse [_p, _r, _r, 0, [[0.36, 0.85, 0.42, 0.9], [0.9, 0.25, 0.2, 0.9]] select _isolated, ""];
        // Chef de groupe servant de passerelle faute de relais.
        if (_forEachIndex isEqualTo (_mesh getOrDefault ["gw", -1])) then { _map drawEllipse [_p, _r * 2.2, _r * 2.2, 0, [0.28, 0.70, 1, 1], ""]; };
    } forEach _nodes;
};
if (profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true]) then {
    private _scale = ctrlMapScale _map;
    private _txt = _scale < 0.25;
    private _mine = uiNamespace getVariable ["COMSPEC_ATAK_RelayLast", objNull];
    private _font = COMSPEC_ATAK_FontSet select 0;
    private _fontB = COMSPEC_ATAK_FontSet select 1;
    // En combat ou téléphone porté : un trait plein au lieu de dizaines de tirets (SQF-C02).
    private _hudLite = !(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap] getOrDefault ["interactive", false])
        || {(getSuppression player) > 0.08}
        || {(behaviour player) isEqualTo "COMBAT"};
    // Ligne sous l'étiquette : même point décalé d'une hauteur de texte à l'écran.
    private _below = { params ["_p", "_k"]; _map ctrlMapScreenToWorld ((_map ctrlMapWorldToScreen _p) vectorAdd [0, 0.024 * _ts * _k]) };
    private _pliR = ["pli"] call comspec_atak_native_fnc_netCond;
    {
        _x params ["_o", "_ap", "_name", "_kind", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "_up", "_fq", "", "_loss", "", "_hl", "", "_co"];
        // Relais effacé pendant les 2 s de cache de la liste (Zeus, nettoyage) : position [0,0,0], dessiné à l'origine.
        if (isNull _o) then { continue };
        private _p = [getPosASLVisual _o, _selfP] select (_o in _selfObjs);
        // Radio MPU5 portée par un allié : au point de son symbole (dernier point reçu), comme les traits du maillage ; avant,
        // la vraie position, quelques mètres à côté : en zoom rapproché, deux traits vers le même allié.
        if (_kind isEqualTo "mpu5" && {_pliR} && {!(_o in _selfObjs)}) then {
            private _pl = _o getVariable ["COMSPEC_ATAK_Pli", []];
            if (_pl isEqualType [] && {(count _pl) >= 3}) then { _p = _pl select 0; };
        };
        private _use = _ok && {_o isEqualTo _mine};
        private _sat = _sl > 0 && {_u > _sl};
        private _col = switch (true) do {
            case (_st isEqualTo "détruit"): { [0.92, 0.30, 0.25, 1] };
            case (!_ok): { [0.62, 0.66, 0.64, 1] };
            case (_use): { [0.36, 0.85, 0.42, 1] };
            case (_sat || {_bt >= 0 && {_bt < 20}} || {_hl isEqualTo "critique"}): { [1, 0.65, 0.2, 1] };
            default { [0.28, 0.70, 1, 1] };
        };
        // Portée : celle du relais utilisé toujours, les autres en léger quand on zoome.
        if (_ok && {_use || {_scale < 0.12}}) then {
            _map drawEllipse [_p, _rng, _rng, 0, [_col select 0, _col select 1, _col select 2, [0.18, 0.45] select _use], ""];
        };
        if (_st in ["éteint", "batterie vide", "brouillé", "sans liaison"] && {_scale < 0.12}) then {
            _map drawEllipse [_p, _rng, _rng, 0, [0.5, 0.5, 0.5, 0.25], ""];
        };
        if (_ok && {!isNull _up} && {[_o, _up, "relay"] call _pairAdd}) then {
            private _q = getPosASLVisual _up;
            if (_hudLite) then {
                _map drawLine [_p, _q, [0.28, 0.70, 1, 0.55]];
            } else {
                private _len = _p distance2D _q;
                private _dir = _p getDir _q;
                // Au plus ~80 traits (tirets allongés au-delà) : le coût ne croît plus avec le zoom.
                private _dl = (_r * 3) max (_len / 160);
                for "_k" from 0 to (floor (_len / _dl)) step 2 do {
                    _map drawLine [_p getPos [_k * _dl, _dir], _p getPos [((_k + 1) * _dl) min _len, _dir], [0.28, 0.70, 1, 0.7]];
                };
            };
        };
        // Relais utilisé : pointillé de mon téléphone à son antenne (trait plein en mode léger), bascule « Trait vers mon
        // relais » (masqué par défaut, 2.9.38). Relais déjà relié à moi par le maillage (radio d'un allié) : pas de second trait.
        if (_use && {profileNamespace getVariable ["COMSPEC_ATAK_RelayLine", false]} && {[player, _o, "relay"] call _pairAdd}) then {
            private _me = _selfP;
            if (_hudLite) then {
                _map drawLine [_me, _p, [0.36, 0.85, 0.42, 0.65]];
            } else {
                private _len = _me distance2D _p;
                private _dir = _me getDir _p;
                private _dl = (_r * 2) max (_len / 160);
                for "_k" from 0 to (floor (_len / _dl)) step 2 do {
                    _map drawLine [_me getPos [_k * _dl, _dir], _me getPos [((_k + 1) * _dl) min _len, _dir], [0.36, 0.85, 0.42, 0.75]];
                };
            };
        };
        // Pastille : fond sombre, anneau de couleur (double si utilisé), antenne ou symbole OTAN du véhicule / PC.
        _map drawEllipse [_p, _r * 1.6, _r * 1.6, 0, [0.04, 0.06, 0.05, 0.82], _fill];
        _map drawEllipse [_p, _r * 1.6, _r * 1.6, 0, _col, ""];
        if (_use) then { _map drawEllipse [_p, _r * 2.1, _r * 2.1, 0, _col, ""]; };
        // Relais préféré (fiche du relais) : troisième anneau.
        if (_o isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull])) then { _map drawEllipse [_p, _r * 2.6, _r * 2.6, 0, [_col select 0, _col select 1, _col select 2, 0.6], ""]; };
        private _icon = switch (_kind) do {
            case "cp": { "\A3\ui_f\data\map\markers\nato\b_hq.paa" };
            case "gw": { "\A3\ui_f\data\map\markers\nato\b_motor_inf.paa" };
            case "mpu5": { "\A3\ui_f\data\map\markers\nato\b_inf.paa" };
            default { "\A3\ui_f\data\map\mapcontrol\transmitter_CA.paa" };
        };
        _map drawIcon [_icon, _col, _p, 15, 15, 0, "", 0, 0, _font, "center"];
        if (_st isEqualTo "détruit") then {
            _map drawLine [_p getPos [_r * 2.2, 45], _p getPos [_r * 2.2, 225], _col];
            _map drawLine [_p getPos [_r * 2.2, 135], _p getPos [_r * 2.2, 315], _col];
        };
        if (!_txt) then { continue };
        // Étiquette : nom (gras), puis « portée · débit · places » ou la raison de la panne.
        private _lp = _p getPos [_r * 2.4, 90];
        private _l1 = _name + (["", "  " + ("(utilisé)" call comspec_atak_native_fnc_tr)] select _use);
        // Fond des noms (Réglages > Carte) sous le nom et sous la ligne de détail.
        ["nameBgDraw", [_map, _lp, _l1, _fontB, 0.026 * _ts]] call comspec_atak_native_fnc_mapPrefs;
        _map drawIcon [_none, [1, 1, 1, [0.75, 1] select _ok], _lp, 0, 0, 0, _l1, 2, 0.026 * _ts, _fontB, "right"];
        private _sub = if (_ok) then {
            private _e = format ["%1 km · %2 Mbit/s", (_rng / 1000) toFixed 1, _mb toFixed 1];
            if (_sl > 0) then { _e = _e + format [" · %1/%2", _u, _sl]; };
            if (_bt >= 0) then { _e = _e + " · " + format [("batterie %1 %%") call comspec_atak_native_fnc_tr, round _bt]; };
            _e = _e + format [" · %1 MHz · ", _fq] + format [("perte %1 %%") call comspec_atak_native_fnc_tr, _loss];
            if (_co > 0) then { _e = _e + " · " + ("canal partagé" call comspec_atak_native_fnc_tr); };
            if (_hl in ["dégradé", "critique"]) then { _e = _e + " · " + (_hl call comspec_atak_native_fnc_tr); };
            _e
        } else { _st call comspec_atak_native_fnc_tr };
        private _lp2 = [_lp, 1] call _below;
        ["nameBgDraw", [_map, _lp2, _sub, _font, 0.022 * _ts]] call comspec_atak_native_fnc_mapPrefs;
        _map drawIcon [_none, [_col select 0, _col select 1, _col select 2, 0.95], _lp2, 0, 0, 0, _sub, 2, 0.022 * _ts, _font, "right"];
    } forEach (["list"] call comspec_atak_native_fnc_relayNet);
};
uiNamespace setVariable ["COMSPEC_ATAK_MeshDrawn", _pairList];
private _now = [time, serverTime] select isMultiplayer;
{
    _x params ["_name", "_est", "_rad", "_src", "_t"];
    private _age = _now - _t;
    if (_age > 600) then { continue };
    private _al = 1 - (_age / 600) * 0.7;
    {
        _x params ["_sp", "_d"];
        _map drawEllipse [_sp, _d, _d, 0, [1, 0.75, 0.2, 0.55 * _al], ""];
        if (_est isNotEqualTo []) then { _map drawLine [_sp, _est, [1, 0.75, 0.2, 0.25 * _al]]; };
    } forEach _src;
    if (_est isNotEqualTo []) then {
        _map drawEllipse [_est, _rad, _rad, 0, [1, 0.55, 0.1, 0.25 * _al], _fill];
        _map drawEllipse [_est, _rad, _rad, 0, [1, 0.55, 0.1, _al], ""];
        private _tl = format ["%1 ± %2 m", _name, _rad];
        ["nameBgDraw", [_map, _est, _tl, COMSPEC_ATAK_FontSet select 1, 0.028 * _ts, 20]] call comspec_atak_native_fnc_mapPrefs;
        _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa", [1, 0.55, 0.1, _al], _est, 20, 20, 0, _tl, 1, 0.028 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    } else {
        private _tl = format [("%1 : cercle %2 m") call comspec_atak_native_fnc_tr, _name, round ((_src select 0) select 1)];
        ["nameBgDraw", [_map, (_src select 0) select 0, _tl, COMSPEC_ATAK_FontSet select 1, 0.026 * _ts]] call comspec_atak_native_fnc_mapPrefs;
        _map drawIcon [_none, [1, 0.75, 0.2, _al], (_src select 0) select 0, 0, 0, 0, _tl, 1, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []]);
