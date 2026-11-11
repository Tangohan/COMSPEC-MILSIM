/*
    App Wave Relay : le réseau du téléphone, de la radio de données (MPU5) et des relais, l'état d'abord.
    Onglets : ÉTAT (carte d'état : relié, dégradé ou coupé, par quel relais, en combien de sauts ; chemin de la voix ; mini-graphe ;
    actions ; voisins directs triés et filtrés ; cartes repliables : radio de données, voix par le réseau, chiffrement, débit, mon nœud),
    RÉSEAU (graphe du réseau, schéma ou carte, fiche du nœud touché, tous les nœuds, LOCALISER, outils du maillage),
    RELAIS (couverture, zone sans réseau la plus proche, relais et passerelles, gestion Zeus / admin, graphe du camp),
    VOIX (chemin de la voix en direct, qualité, priorité maillage ou radio, dernières émissions, fréquences, équipiers reliés, groupes).
    Fiches à la place des paragraphes (fn_waveRelaySheet) : voix par le réseau étape par étape, choisir la passerelle, signaler un
    problème, état d'un relais. État bon, dégradé ou coupé, cause et action : fn_waveRelayStatus. Tutoriel, bouton « ? » : fn_waveRelayTuto.
    Règle « pas de relais, pas de réseau » : fn_relayNet "gate". Chemin et qualité de la voix : fn_voicePath. Graphe : fn_meshGraphView.
    Rien n'est calculé par image : la page lit les caches (maillage 3 s, relais 2 s) et ne change sur place que deux textes.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["wrTab", "NODE"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _qCol = { params ["_q"]; switch (true) do { case (_q >= 60): { (COMSPEC_ATAK_Th get "hexOk") }; case (_q >= 30): { (COMSPEC_ATAK_Th get "hexWarn") }; default { (COMSPEC_ATAK_Th get "hexAlert") }; } };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wrTab', '%1']; (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wrTuto', -1]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k], _tab isEqualTo _k] };
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _nodes = _mesh get "nodes";
private _links = _mesh get "links";
private _hops = _mesh get "hops";
private _gw = _mesh get "gw";
private _myHops = _hops select 0;
private _acre = !isNil "acre_api_fnc_getCurrentRadioList";
private _tfar = !isNil "TFAR_fnc_activeSwRadio";
// Choix d'affichage (tri, filtre, carte repliée, nœud touché) : rangés dans l'état de la page, la page se redessine.
private _setS = { params ["_k", "_v"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['%1', %2]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k, _v] };
private _fold = { params ["_k", "_title", "_sum"]; private _open = _s getOrDefault [_k, false]; _rows pushBack ["fold", _title, _sum, _open, [_k, str !_open] call _setS]; _open };
private _bearing = { params ["_p"]; format ["%1 %2°", [player getDir _p] call _card, round (player getDir _p)] };
private _hopTxt = { params ["_i"]; private _h = _hops param [_i, -1]; switch (true) do { case (((_nodes select _i) select 3) isEqualTo "gw" || {_i isEqualTo _gw}): { "relais" }; case (_h < 0): { "hors réseau" }; default { format ["%1 saut(s) du relais", _h] }; } };
private _nodeMeter = {
    params ["_i", "_lq", ["_lc", 0]];
    (_nodes select _i) params ["", "_p", "_name"];
    ["meter", _name, format ["%1 · %2 · %3", [player distance2D _p] call _dist, [_p] call _bearing, [_i] call _hopTxt], _lq,
        [[], [["portée", "portée", "relief", "bâtiments", "météo", "brouillage", "posture", "canal", "feuillage"] param [_lc, "portée"]]] select (_lq >= 0 && {_lq < 45} && {_lc > 0}), ["wrSel", str _i] call _setS, (_s getOrDefault ["wrSel", -1]) isEqualTo _i]
};
private _rows = [["segment", "", [["ÉTAT", "NODE"] call _tabBtn, [format ["RÉSEAU (%1)", count _nodes], "MESH"] call _tabBtn, ["RELAIS", "COV"] call _tabBtn, [["GROUPES", "VOIX"] select (_acre || {_tfar}), "TG"] call _tabBtn, ["?", { ["set", [0, -1] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["wrTuto", -1]) >= 0)] call comspec_atak_native_fnc_waveRelayTuto; }, false, "Tutoriel du réseau"]]]];
// Tutoriel (fn_waveRelayTuto) : à la place de l'onglet, sur le bouton « ? ».
private _tuto = _s getOrDefault ["wrTuto", -1];
if (_tuto >= 0) exitWith {
    _rows append (["rows", _tuto] call comspec_atak_native_fnc_waveRelayTuto);
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
// Fiche (fn_waveRelaySheet) : à la place de l'onglet, fermée par « retour ».
private _sheet = _s getOrDefault ["wrSheet", ""];
if (_sheet isNotEqualTo "") exitWith {
    [["rows", _sheet] call comspec_atak_native_fnc_waveRelaySheet, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
_rows append (["offer"] call comspec_atak_native_fnc_waveRelayTuto);
// Objet obligatoire manquant (fn_netRequire) : dit sur tous les onglets.
private _denied = ["denied"] call comspec_atak_native_fnc_netRequire;
if (_denied isNotEqualTo [] && {_tab isNotEqualTo "NODE"}) then {
    _rows pushBack ["text", format ["<t color='%1'>Vous n'êtes plus un nœud du réseau : il manque %2.</t>", COMSPEC_ATAK_Th get "hexAlert", ["labels", [_denied]] call comspec_atak_native_fnc_netRequire], COMSPEC_ATAK_Th get "surface"];
};
private _refresh = ["buttons", [
    ["ACTUALISER", { [] spawn { ["scan", "force"] call comspec_atak_native_fnc_waveRelayAction; if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }; }; }],
    ["CARTE", { if !(profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then { profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true]; }; ["MAP"] call comspec_atak_native_fnc_navigate; }]
]];
switch (_tab) do {
    case "MESH": {
        // Graphe du réseau (fn_meshGraphView) : schéma par sauts ou positions réelles ; un nœud touché ouvre sa fiche dessous.
        private _model = ["model", [""]] call comspec_atak_native_fnc_meshGraphView;
        private _gMode = _s getOrDefault ["wrGraph", "logic"];
        private _sel = _s getOrDefault ["wrSel", -1];
        if (_sel >= (count _nodes)) then { _sel = -1; };
        _rows pushBack ["section", "Mon réseau", format ["%1 nœud(s) · %2 lien(s)", count _nodes, count _links]];
        _rows pushBack ["segment", "", [["SCHÉMA", ["wrGraph", "'logic'"] call _setS, _gMode isEqualTo "logic"], ["CARTE", ["wrGraph", "'geo'"] call _setS, _gMode isEqualTo "geo"]]];
        _rows pushBack ["graph", _model, createHashMapFromArray [["mode", _gMode], ["sel", _sel], ["compact", true],
            ["click", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["wrSel", _this select 0]; [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]], 8];
        if (_sel >= 0) then {
            private _det = ["detail", [_model, _sel]] call comspec_atak_native_fnc_meshGraphView;
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/>", [(_nodes select _sel) select 2] call _esc] + ((_det apply {
                format ["<t size='0.82' color='%1'>%2 :</t> <t size='0.9' color='%3'>%4</t>", COMSPEC_ATAK_Th get "hexTextMuted", (_x select 0) call comspec_atak_native_fnc_tr,
                    COMSPEC_ATAK_Th get ("hex" + (toUpper ((_x select 2) select [0, 1])) + ((_x select 2) select [1])), [(_x select 1) call comspec_atak_native_fnc_tr] call _esc]
            }) joinString "<br/>"), COMSPEC_ATAK_Th get "surface"];
            private _selObj = (_nodes select _sel) select 0;
            _rows pushBack ["buttons", [
                ["LOCALISER", compile format ["['locate', '%1'] call comspec_atak_native_fnc_relayNet; profileNamespace setVariable ['COMSPEC_ATAK_MeshOnMap', true]; ['MAP'] call comspec_atak_native_fnc_navigate;", netId _selObj], false, ((_nodes select _sel) select 3) isEqualTo "node"],
                ["ÉTAT DU RELAIS", compile format ["['open', ['relay', objectFromNetId '%1']] call comspec_atak_native_fnc_waveRelaySheet;", netId _selObj], false, _selObj getVariable ["COMSPEC_AtakRelay", false]],
                ["FERMER", ["wrSel", "-1"] call _setS]
            ]];
        };
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", "Vert : bon lien · orange : moyen · rouge espacé : faible · triangle : relais · en bas : hors réseau, avec la raison. Touchez un nœud."]];
        // Tous les nœuds : tri au choix, les nœuds hors réseau à la fin.
        private _sort = _s getOrDefault ["wrSortM", "hops"];
        _rows pushBack ["section", format ["Tous les nœuds (%1)", count _nodes], format ["portée nominale %1", [_mesh get "range"] call _dist]];
        _rows pushBack ["segment", "", [["SAUTS", ["wrSortM", "'hops'"] call _setS, _sort isEqualTo "hops"], ["DISTANCE", ["wrSortM", "'dist'"] call _setS, _sort isEqualTo "dist"], ["QUALITÉ", ["wrSortM", "'q'"] call _setS, _sort isEqualTo "q"]]];
        // Qualité d'un nœud : son meilleur lien.
        private _bestQ = _nodes apply { -1 };
        { _x params ["_a", "_b", "_lq"]; _bestQ set [_a, (_bestQ select _a) max _lq]; _bestQ set [_b, (_bestQ select _b) max _lq]; } forEach _links;
        private _order = [];
        {
            private _h = _hops param [_forEachIndex, -1];
            _order pushBack [switch (_sort) do { case "dist": { player distance2D (_x select 1) }; case "q": { 100 - (_bestQ select _forEachIndex) }; default { [1000, _h] select (_h >= 0) }; }, _forEachIndex];
        } forEach _nodes;
        _order sort true;
        { private _i = _x select 1; if (_i isNotEqualTo 0) then { _rows pushBack ([_i, _bestQ select _i] call _nodeMeter); }; } forEach _order;
        if ((count _nodes) <= 1) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "Vous êtes seul sur le réseau", "Rapprochez-vous d'un relais ou d'un équipier équipé d'un téléphone."]; };
        _rows append (["playerRows"] call comspec_atak_native_fnc_meshTools);
        _rows pushBack _refresh;
    };
    case "COV": {
        private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
        private _busy = missionNamespace getVariable ["COMSPEC_ATAK_CoverageBusy", false];
        private _rad = _s getOrDefault ["rcRadius", 5000];
        private _opt = { params ["_t", "_v"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['rcRadius', %1]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _v], _v isEqualTo _rad] };
        private _relays = ["list"] call comspec_atak_native_fnc_relayNet;
        _rows append [
            ["section", "Couverture réseau", "Débit reçu à hauteur d'homme autour de moi, d'après les relais et passerelles du camp, relief et brouilleurs compris"],
            ["segment", "Rayon", [["2 km", 2000] call _opt, ["5 km", 5000] call _opt, ["10 km", 10000] call _opt], format ["case de %1 m", round ((2 * _rad) / 40)]]
        ];
        if (_busy) then {
            _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● Calcul en cours : %1 %%</t>", round (100 * (missionNamespace getVariable ["COMSPEC_ATAK_CoverageProgress", 0]))]];
            _rows pushBack ["buttons", [["ACTUALISER", { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]]];
        } else {
            _rows pushBack ["buttons", [
                ["CALCULER", { ["coverage"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true],
                ["EFFACER", { ["clear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]
            ]];
        };
        if ((count _cv) >= 8) then {
            _cv params ["_center", "_cell", "_cells", "_r", "_pct", "_when", "_nr", "_avg"];
            _rows append [
                ["info", "Terrain couvert", format ["<t color='%1'>%2 %%</t>", [(COMSPEC_ATAK_Th get "hexAlert"), (COMSPEC_ATAK_Th get "hexWarn"), (COMSPEC_ATAK_Th get "hexOk")] select ((floor (_pct / 34)) min 2), _pct]],
                ["info", "Zones blanches", format ["%1 %%", 100 - _pct]],
                ["info", "Débit moyen couvert", format ["%1 Mbit/s", _avg toFixed 1]],
                ["info", "Calculé", format ["%1 · %2 · %3 relais", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist, _nr]]
            ];
            private _here = ["at", getPosASL player] call comspec_atak_native_fnc_relayNet;
            if (_here isNotEqualTo []) then { _rows pushBack ["info", "À ma position", format ["%1 Mbit/s · perte %2 %%", (_here select 0) toFixed 1, _here select 1]]; };
            if ((player distance2D _center) > _cell * 3) then { _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Vous avez bougé depuis le calcul : relancez-le pour la zone où vous êtes.</t>"]; };
        };
        _rows pushBack ["text", "<t size='0.8'><t color='#33d94d'>●</t> 4 Mbit/s et plus  <t color='#bfd933'>●</t> 1,5  <t color='#ff9926'>●</t> 0,5  <t color='#e5332a'>●</t> faible  <t color='#777777'>●</t> zone blanche</t>"];
        _rows pushBack ["switch", "Couverture et liens sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], { ["mapLinks"] call comspec_atak_native_fnc_waveRelayAction; }, "Calque Wave Relay : couverture calculée, liens du maillage"];
        _rows pushBack ["switch", "Relais sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true], { ["mapRelays"] call comspec_atak_native_fnc_waveRelayAction; }, "Calque Relais : pastilles, portée du relais utilisé (aussi dans CALQUES)"];
        // Zone sans réseau la plus proche, d'après la couverture calculée (fn_relayNet "deadZone").
        private _dz = ["deadZone"] call comspec_atak_native_fnc_relayNet;
        if (_dz isNotEqualTo []) then { _rows pushBack ["info", "Zone sans réseau la plus proche", format ["%1 · %2", [_dz select 0] call _dist, [_dz select 1] call _card]]; };
        _rows pushBack ["section", format ["Relais et passerelles (%1)", count _relays], "Touchez un relais pour son état"];
        (["gate"] call comspec_atak_native_fnc_relayNet) params ["_gOpen", "", "_gWhy", "_gFix"];
        if (!_gOpen) then { _rows pushBack ["status", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", COMSPEC_ATAK_Th get "alert", _gWhy, "Sur ce serveur, le réseau du téléphone et de la MPU5 ne marche qu'avec un relais en service à portée.", _gFix]; };
        {
            _x params ["_o", "_ap", "_name", "_kind", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "_root"];
            private _l = ["link", [_ap, _rng, _mb, eyePos player]] call comspec_atak_native_fnc_relayNet;
            private _rx = _ok && {(_l select 1) > 0};
            private _det = [[player distance2D _ap] call _dist, [format ["hors service : %1", _st], [format ["reçu %1 Mbit/s", (_l select 1) toFixed 1], "hors de portée"] select !_rx] select _ok, format ["portée %1", [_rng] call _dist]];
            if (_sl > 0) then { _det pushBack format ["%1/%2 places", _u, _sl]; };
            if (_bt >= 0) then { _det pushBack format ["batterie %1 %%", round _bt]; };
            _rows pushBack ["meter", format ["%1 · %2", _name, createHashMapFromArray [["relay", ["répéteur", "relais"] select _root], ["gw", "véhicule"], ["cp", "PC mobile"], ["mpu5", "MPU5 d'un équipier"]] getOrDefault [_kind, _kind]],
                _det joinString " · ", [0, round (100 * (_l select 0))] select _rx, [[], [_st]] select (!_ok && {_st isNotEqualTo ""}),
                compile format ["['open', ['relay', objectFromNetId '%1']] call comspec_atak_native_fnc_waveRelaySheet;", netId _o]];
        } forEach _relays;
        if (_relays isEqualTo []) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "Aucun relais sur le serveur", "Posez un relais : pylône, antenne, kit ou véhicule relais."]; };
        // Zeus et admins : tous les relais de tous les camps, et le graphe du camp en plein écran.
        if (!isNull (getAssignedCuratorLogic player) || {([player] call comspec_atak_native_fnc_adminAllowed) isEqualTo true}) then {
            private _zSide = _s getOrDefault ["wrZSide", str side group player];
            private _zList = (["list", [_zSide]] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isNotEqualTo "mpu5" };
            _rows pushBack ["section", "Gestion des relais (Zeus et admin)", format ["%1 en service sur %2", { _x select 6 } count _zList, count _zList]];
            _rows pushBack ["segment", "", ([["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "INDÉP."], ["CIV", "CIVILS"]] apply { [_x select 1, ["wrZSide", format ["'%1'", _x select 0]] call _setS, (_x select 0) isEqualTo _zSide] })];
            {
                _x params ["_o", "", "_name", "", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "", "_fq", "", "_loss", "_hp", "_hl"];
                _rows pushBack ["meter", _name, format ["%1 · %2 MHz · %3 tél.%4 · %5 · perte %6 %%", [_hl, _st] select !_ok, _fq, _u, ["", format ["/%1", _sl]] select (_sl > 0), [format ["batterie %1 %%", round _bt], "secteur"] select (_bt < 0), _loss],
                    [0, _hp] select _ok, [[], [_st]] select (!_ok && {_st isNotEqualTo ""}), compile format ["['open', ['relay', objectFromNetId '%1']] call comspec_atak_native_fnc_waveRelaySheet;", netId _o]];
            } forEach _zList;
            if (_zList isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", "Aucun relais pour ce camp."]]; };
            _rows pushBack ["buttons", [["GRAPHE DU CAMP (PLEIN ÉCRAN)", compile format ["['open', ['%1']] call comspec_atak_native_fnc_meshGraphView;", _zSide]]]];
        };
        private _tri = missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []];
        if (_tri isNotEqualTo []) then {
            _rows pushBack ["section", "Triangulations", "Localisations par les relais (10 min sur la carte)"];
            { _rows pushBack ["info", _x select 0, [format ["± %1 m · %2 source(s)", _x select 2, count (_x select 3)], "une source"] select ((_x select 1) isEqualTo [])]; } forEach _tri;
            _rows pushBack ["buttons", [["EFFACER", { ["trilatClear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]]];
        };
    };
    case "TG": {
        // Chemin de la voix (fn_voicePath) : par où passe ma voix, avec quelle qualité ; change sur place pendant l'émission.
        if (_acre || {_tfar}) then {
            _rows pushBack ["text", ["cardText"] call comspec_atak_native_fnc_voicePath, COMSPEC_ATAK_Th get "surface", "COMSPEC_ATAK_WrVoiceCtl"];
            (["get"] call comspec_atak_native_fnc_voicePath) params ["_vpPath", "", "_vpRoute"];
            private _mmV = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
            if ((_mmV getOrDefault ["cause", ""]) isNotEqualTo "") then {
                _rows pushBack ["text", format ["<t size='0.88' color='%1'>%2 : %3</t>%4", COMSPEC_ATAK_Th get "hexWarn", "Maillon faible", [(_mmV get "cause") call comspec_atak_native_fnc_tr] call _esc,
                    ["", format ["<br/><t size='0.85' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", [(_mmV getOrDefault ["fix", ""]) call comspec_atak_native_fnc_tr] call _esc]] select ((_mmV getOrDefault ["fix", ""]) isNotEqualTo "")]];
            };
            (["policy"] call comspec_atak_native_fnc_voicePath) params ["_pol", "_polForced"];
            _rows pushBack ["section", "Priorité", ["votre choix", "imposée par le serveur"] select _polForced];
            _rows pushBack ["segment", "", ([[2, "MAILLAGE D'ABORD"], [1, "RADIO D'ABORD"], [0, "LES DEUX"]] apply {
                [_x select 1, compile format ["['setPolicy', [%1]] call comspec_atak_native_fnc_voicePath; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _x select 0], (_x select 0) isEqualTo _pol]
            })];
            _rows pushBack ["text", format ["<t size='0.82' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", [
                "Les deux : le meilleur des deux signaux est gardé. Si l'un tombe, l'autre prend le relais tout seul.",
                "Radio d'abord : le maillage ne sert que si la radio seule ne porte pas jusqu'à l'équipier.",
                "Maillage d'abord : dès que le maillage joint l'équipier, la voix le suit. La radio n'est jamais affaiblie : à l'oreille, même résultat que « Les deux »."] select _pol]];
            // Dernières émissions : heure, durée, chemin, qualité ; une barre par émission passée par le maillage.
            private _vlog = ["log"] call comspec_atak_native_fnc_voicePath;
            reverse _vlog;
            _rows pushBack ["section", "Dernières émissions", ["rien pour l'instant", format ["%1", count _vlog]] select (_vlog isNotEqualTo [])];
            private _bars = ((_vlog select { (_x select 4) >= 0 }) select [0, 10]) apply { format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexOk", "hexOk", "hexWarn", "hexAlert", "hexAlert"] select (_x select 5)), format ["<t size='%1'>●</t>", [0.55, 0.75, 0.95, 1.15, 1.35] select ((floor ((_x select 4) / 20)) min 4)]] };
            if (_bars isNotEqualTo []) then { reverse _bars; _rows pushBack ["text", format ["%1  <t size='0.8' color='%2'>%3</t>", _bars joinString " ", COMSPEC_ATAK_Th get "hexTextMuted", "qualité par le maillage, la plus récente à droite"]]; };
            {
                _x params ["_day", "_dur", "_p", "_net", "_pct", "_tier", "_hp"];
                _rows pushBack ["info", format ["%1 · %2", [_day, "HH:MM:SS"] call BIS_fnc_timeToString, [format ["%1 s", _dur], "reçu"] select (_dur < 0)],
                    format ["<t color='%1'>%2%3</t>", COMSPEC_ATAK_Th get (["hexTextMuted", ["hexOk", "hexOk", "hexWarn", "hexAlert", "hexAlert"] select (_tier min 4)] select (_pct >= 0)),
                        [[_net] call _esc, ((["pathName", [_p]] call comspec_atak_native_fnc_voicePath) call comspec_atak_native_fnc_tr)] select (_dur >= 0 || {_net isEqualTo ""}),
                        ["", format [" · %1 %2 %% · %3", (["tierName", [_tier]] call comspec_atak_native_fnc_voicePath) call comspec_atak_native_fnc_tr, _pct, format ["%1 saut(s)" call comspec_atak_native_fnc_tr, _hp]]] select (_pct >= 0)]];
            } forEach (_vlog select [0, 6]);
            if (_vlog isEqualTo []) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", "Appuyez sur l'alternat radio : chaque émission s'inscrit ici avec son chemin."]]; };
            _rows pushBack ["buttons", [["COMMENT ÇA MARCHE", { ["open", ["voice"]] call comspec_atak_native_fnc_waveRelaySheet; }], ["DÉPANNAGE", { ["open"] call comspec_atak_native_fnc_mpuDiag; }]]];
        };
        if (_acre) then {
            // Voix ACRE2 (fn_mpu5) : qui émet sur mes fréquences, mes radios (canal, fréquence, alternat), le réseau voix
            // publié par les téléphones du camp. Rendu à nouveau à chaque début ou fin d'émission.
            private _mhz = { params ["_f"]; [format ["%1 MHz", _f toFixed 3], "?"] select (!(_f isEqualType 0) || {_f <= 0}) };
            // Voix par le réseau (fn_mpuVoice "summary") : état dit en clair, même désactivée, avec la façon de l'activer.
            (["summary"] call comspec_atak_native_fnc_mpuVoice) params ["_vCode", "_vState", "_vHelp"];
            _rows pushBack ["section", "À quoi sert cet onglet", "En deux mots"];
            _rows pushBack ["text", format ["<t size='0.9'>%1</t>", "Cet onglet montre qui parle sur les mêmes fréquences radio que vous, et dit si votre voix ACRE peut passer par le réseau MPU5 de vos équipiers. Ex. vous réglez votre radio ACRE sur 40,0 MHz ; si 2 équipiers sont aussi sur 40,0 ils apparaissent ici et vous pouvez les entendre même hors de portée directe, via la MPU5." call comspec_atak_native_fnc_tr], COMSPEC_ATAK_Th get "surface"];
            _rows pushBack ["section", "Voix par le réseau", "Votre voix ACRE2 relayée par le maillage MPU5 vers les radios reliées"];
            _rows pushBack ["info", "État", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexOk"] select (_vCode isEqualTo "on")), [_vState] call _esc]];
            _rows pushBack ["info", "Radio ACRE2", format ["%1 · %2", format ["%1 détectée(s)" call comspec_atak_native_fnc_tr, count (["radios"] call comspec_atak_native_fnc_mpu5)], (["non reliée à la MPU5", "reliée à la MPU5"] apply { _x call comspec_atak_native_fnc_tr }) select (!((missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo []))]];
            _rows pushBack ["text", format ["<t color='%1'>%3</t> %2", COMSPEC_ATAK_Th get "hexInfo", [(createHashMapFromArray [
                ["off", "un admin ou un Zeus active « Voix relayée par le maillage » dans Échap > Réalisme (section Maillage), puis Appliquer au serveur. Autre chemin : Réglages d'addons > COMSPEC > ATAK."],
                ["cut", "rétablir le lien radio : Échap > Réalisme, bouton Rétablir le lien radio."],
                ["noreal", "activer « Liaison téléphone-radio réaliste » dans Échap > Réalisme (section Maillage) et ne pas utiliser le profil Arcade."],
                ["nobridge", "appuyez sur le bouton RELIER MA RADIO À LA MPU5 (VOIX) ci-dessous."],
                ["wait", "rapprochez-vous d'un relais ou d'un équipier avec une MPU5 pour rejoindre le maillage."],
                ["on", "rien à faire. Réglez votre radio sur la fréquence d'un équipier relié."]
            ] getOrDefault [_vCode, ""]) call comspec_atak_native_fnc_tr] call _esc, "Prochain pas" call comspec_atak_native_fnc_tr]];
            if (_vHelp isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.9' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", [_vHelp] call _esc]]; };
            // Geste du joueur, comme l'action ACE : relie ou débranche le pont radio (fn_mpuVoice "bridge"), seulement si le serveur l'autorise.
            if (_vCode in ["nobridge", "wait", "on"] && {["has", [player]] call comspec_atak_native_fnc_mpu5}) then {
                _rows pushBack ["buttons", [[["RELIER MA RADIO À LA MPU5 (VOIX)", "DÉBRANCHER MA RADIO (VOIX)"] select (_vCode isNotEqualTo "nobridge"), {
                    if (((missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo [])) then { ["bridge"] call comspec_atak_native_fnc_mpuVoice; } else { ["unbridge"] call comspec_atak_native_fnc_mpuVoice; };
                    [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                }, _vCode isEqualTo "nobridge"]]];
            };
            private _spk = ["speakers"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", format ["En émission (%1)", count _spk], "Alternats ouverts sur les fréquences que vos radios reçoivent"];
            {
                _x params ["_u", "_id", "_f", "_lbl", "_since"];
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>● ÉMET</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2%3 · depuis %4 s</t>",
                    [["", [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc] select (_u isNotEqualTo player), "Vous"] select (_u isEqualTo player),
                    [format ["%1 · ", [_lbl] call _esc], ""] select (_lbl isEqualTo ""), [_f] call _mhz, round _since]];
            } forEach _spk;
            if (_spk isEqualTo []) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Personne n'émet sur vos fréquences pour l'instant : normal si personne ne parle.</t>"]; };
            private _radios = ["radios"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", "Fréquences", "Vos radios ACRE2 : canal, fréquence et alternat (PTT), canal modifiable d'ici"];
            _rows pushBack ["text", format ["<t size='0.85'>%1</t>", "La fréquence est ce que votre radio écoute. Ex. le canal 2 de votre radio est réglé sur 40,0 MHz ; les équipiers sur 40,0 MHz sont ceux que vous entendez. Les boutons - et + changent de canal." call comspec_atak_native_fnc_tr]];
            if ((count _radios) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucune radio ACRE2 sur vous.</t>"]; };
            {
                _x params ["_id", "_name", "_ch", "_lbl", "_tx", "_rx", "_ptt", "_cur", "_on"];
                private _tags = "";
                if (_cur) then { _tags = _tags + "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>active</t>"; };
                if (_ptt > 0) then { _tags = _tags + format ["  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>PTT %1</t>", _ptt]; };
                if (_on) then { _tags = _tags + "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>ÉMET</t>"; };
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Canal %3%4 · %5%6</t>",
                    [_name] call _esc, _tags, _ch, [format [" (%1)", [_lbl] call _esc], ""] select (_lbl isEqualTo ""), [_tx] call _mhz,
                    ["", format [" · réception %1", [_rx] call _mhz]] select (_rx isEqualType 0 && {_tx isEqualType 0} && {abs (_rx - _tx) >= 0.001})],
                    [
                        ["-", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch - 1], false, _ch > 1],
                        ["+", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch + 1]]
                    ], [0.9, 0.94, 0.91, 1]];
                _rows pushBack ["segment", "", [1, 2, 3, 4, 5, 6] apply { [str _x, compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _x], _x isEqualTo _ch] }];
            } forEach _radios;
            // Réseau voix : téléphones du camp qui publient leurs fréquences (ATAK et ACRE2) ou une MPU5 ; même fréquence d'abord.
            private _peers = ["peers"] call comspec_atak_native_fnc_mpu5;
            private _ord = [];
            { _ord pushBack [[1, 0] select (_x select 3), player distance2D (_x select 0), _forEachIndex]; } forEach _peers;
            _ord sort true;
            _rows pushBack ["section", format ["Connectés (%1)", count _peers], format ["%1 sur vos fréquences · téléphones ATAK du camp", { _x select 3 } count _peers]];
            {
                (_peers select (_x select 2)) params ["_u", "_mpu", "_fs", "_same", "_talk"];
                private _fl = (_fs apply { format ["%1%2", [format ["%1 ", [_x select 1] call _esc], ""] select ((_x select 1) isEqualTo ""), [_x select 0] call _mhz] }) joinString " · ";
                _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3%4%5<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%6 · %7</t>",
                    [(COMSPEC_ATAK_Th get "hexTextMuted"), (COMSPEC_ATAK_Th get "hexOk")] select _same, [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>MPU5</t>"] select _mpu,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>ma fréquence</t>"] select _same,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>ÉMET</t>"] select _talk,
                    [_fl, "pas de radio ACRE2 publiée"] select (_fl isEqualTo ""), [player distance2D _u] call _dist]];
            } forEach _ord;
            if (_peers isEqualTo []) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Personne d'autre ne publie son réseau voix pour l'instant : normal si vous êtes seul ou si personne n'a de radio ACRE2 et de téléphone ATAK.</t>"]; };
            _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vous ne voyez émettre que les fréquences réglées sur vos radios, sans tenir compte de la portée. Les joueurs sans téléphone ATAK n'apparaissent pas.</t>"];
            _rows append (["rows"] call comspec_atak_native_fnc_meshGroups);
        } else {
            _rows append (["rows"] call comspec_atak_native_fnc_meshGroups);
        };
    };
    default {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
        private _det = ["detected"] call comspec_atak_native_fnc_mpu5;
        private _selfGw = _gw isEqualTo 0;
        // Carte d'état : relié, dégradé ou coupé, puis par quel relais, en combien de sauts, quel signal, quelle portée. Ses chiffres
        // changent ensuite sur place (fn_waveRelayStatus "live").
        private _sum = switch (true) do {
            case (_gw < 0): { "aucune passerelle" };
            case (_selfGw): { "sans relais : vous êtes votre propre passerelle" };
            case (_myHops < 0): { format ["coupé de %1", [_mesh get "gwLabel"] call _esc] };
            default { format ["via %1 · %2 saut(s)", [_mesh get "gwLabel"] call _esc, _myHops] };
        };
        _sum = format ["%1 · signal %2 · portée %3", _sum, toLower (_q getOrDefault ["label", "?"]), [[["range", [player]] call comspec_atak_native_fnc_mpu5, _det param [2, 0]] select (_det isNotEqualTo [])] call _dist];
        uiNamespace setVariable ["COMSPEC_ATAK_WrSum", _sum];
        private _st = ["read", _q] call comspec_atak_native_fnc_waveRelayStatus;
        uiNamespace setVariable ["COMSPEC_ATAK_WrStatusSig", _st get "sig"];
        uiNamespace setVariable ["COMSPEC_ATAK_WrStatusText", _st get "text"];
        _rows pushBack ["text", _st get "text", COMSPEC_ATAK_Th get "surface", "COMSPEC_ATAK_WrStatusCtl"];
        // Objet obligatoire manquant : ce qu'il faut, et rien d'autre à régler ici.
        if (_denied isNotEqualTo []) exitWith {
            _rows pushBack ["section", "Objets requis par ce serveur", "Le téléphone reste utilisable. Le réseau repart dès que l'objet est équipé."];
            _rows append (["rows"] call comspec_atak_native_fnc_netRequire);
        };
        // Chemin de la voix (fn_voicePath) : une carte, mise à jour sur place pendant l'émission ; le détail est dans l'onglet VOIX.
        (["summary"] call comspec_atak_native_fnc_mpuVoice) params ["_vCode", "_vState", "_vHelp"];
        if (_acre || {_tfar}) then { _rows pushBack ["text", ["cardText"] call comspec_atak_native_fnc_voicePath, COMSPEC_ATAK_Th get "surface", "COMSPEC_ATAK_WrVoiceCtl"]; };
        // Mini-graphe : moi, mes voisins, le relais ; liens colorés par qualité ; un nœud touché ouvre sa ligne dans RÉSEAU.
        if ((count _nodes) > 1 || {!((["gate"] call comspec_atak_native_fnc_relayNet) select 0)}) then {
            _rows pushBack ["graph", ["model", [""]] call comspec_atak_native_fnc_meshGraphView, createHashMapFromArray [["mode", "logic"], ["sel", -1], ["compact", true],
                ["click", { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["wrSel", _this select 0]; _st set ["wrTab", "MESH"]; [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]], 4.5];
        };
        // Actions, une touche chacune.
        _refresh set [1, (_refresh select 1) + [["TESTER", { ["test"] call comspec_atak_native_fnc_waveRelaySheet; }, false, true, "Latence, perte et débit jusqu'à la passerelle"]]];
        _rows pushBack _refresh;
        _rows pushBack ["buttons", [["PASSERELLE", { ["open", ["gateway"]] call comspec_atak_native_fnc_waveRelaySheet; }, false, true, "Choisir le relais préféré, règles de bascule"], ["SIGNALER UN PROBLÈME", { ["open", ["diag"]] call comspec_atak_native_fnc_waveRelaySheet; }, false, true, "Réunit un diagnostic à joindre à votre message"]]];
        private _test = _s getOrDefault ["wrTest", []];
        if (_test isNotEqualTo []) then { _rows pushBack ["text", format ["<t size='0.82' color='%1'>%2</t><br/>%3", COMSPEC_ATAK_Th get "hexTextMuted", format ["Test de liaison de %1" call comspec_atak_native_fnc_tr, _test select 0], _test select 1], COMSPEC_ATAK_Th get "surface"]; };
        // Voisins directs : liens partant de mon nœud (index 0) ; tri et filtre au choix.
        private _nb = [];
        {
            _x params ["_a", "_b", "_lq", ["_lc", 0]];
            if (_a isEqualTo 0) then { _nb pushBack [_lq, _b, _lc]; };
            if (_b isEqualTo 0) then { _nb pushBack [_lq, _a, _lc]; };
        } forEach _links;
        private _sortN = _s getOrDefault ["wrSortN", "q"];
        private _weakOnly = _s getOrDefault ["wrWeak", false];
        private _nbAll = count _nb;
        if (_weakOnly) then { _nb = _nb select { (_x select 0) < 60 }; };
        _nb = [_nb, [], { switch (_sortN) do { case "dist": { player distance2D ((_nodes select (_x select 1)) select 1) }; case "hops": { private _h = _hops param [_x select 1, -1]; [1000, _h] select (_h >= 0) }; default { 100 - (_x select 0) }; } }] call BIS_fnc_sortBy;
        _rows pushBack ["section", format ["Voisins directs (%1)", _nbAll], "joints sans intermédiaire"];
        if (_nbAll > 1) then {
            _rows pushBack ["segment", "", [["QUALITÉ", ["wrSortN", "'q'"] call _setS, _sortN isEqualTo "q"], ["DISTANCE", ["wrSortN", "'dist'"] call _setS, _sortN isEqualTo "dist"], ["SAUTS", ["wrSortN", "'hops'"] call _setS, _sortN isEqualTo "hops"],
                [["FAIBLES", "TOUS"] select _weakOnly, ["wrWeak", str !_weakOnly] call _setS, _weakOnly, "N'afficher que les liens sous 60 %"]]];
        };
        { _rows pushBack ([_x select 1, _x select 0, _x select 2] call _nodeMeter); } forEach _nb;
        if (_nbAll isEqualTo 0) then {
            (["gate"] call comspec_atak_native_fnc_relayNet) params ["_gOpen", "", "_gWhy", "_gFix"];
            _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", [_gWhy, "Personne à portée"] select _gOpen, [_gFix, "Montez sur un point haut ou rapprochez-vous d'un équipier."] select _gOpen];
        };
        private _dz = ["deadZone"] call comspec_atak_native_fnc_relayNet;
        if (_dz isNotEqualTo []) then { _rows pushBack ["info", "Zone sans réseau la plus proche", format ["%1 · %2", [_dz select 0] call _dist, [_dz select 1] call _card]]; };
        // Cartes repliables : une ligne d'état chacune, le détail seulement dépliée.
        private _muted = { format ["<t size='0.9' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", _this] };
        private _lkTxt = createHashMapFromArray [["cable", "câble EUD"], ["wifi", "Wi-Fi"], ["porte", "portée sur vous"], ["branche", "téléphone branché"]] getOrDefault [_det param [3, ""], "non reliée au téléphone"];
        if (["wrFoldRadio", "Radio de données", [format ["%1 · %2 · %3", [_det param [0, ""]] call _esc, _lkTxt, [_det param [2, 0]] call _dist], "téléphone seul"] select (_det isEqualTo [])] call _fold) then {
            if (_det isNotEqualTo []) then {
                _det params ["_dn", "_dc", "_dr", "_dl", "_dm"];
                _rows pushBack ["info", "Radio", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexOk", [_dn] call _esc]];
                _rows pushBack ["info", "Portée · liaison", format ["%1 · <t color='%2'>%3</t>", [_dr] call _dist, COMSPEC_ATAK_Th get (["hexWarn", "hexText"] select (_dl isNotEqualTo "")), _lkTxt]];
                if (_dc isNotEqualTo "") then {
                    _rows pushBack ["info", "Objet reconnu", format ["%1 · %2", [_dc] call _esc, createHashMapFromArray [["acre", "radio ACRE2"], ["tfar", "radio TFAR"], ["mod", "objet d'un autre mod"]] getOrDefault [_dm, "objet COMSPEC"]]];
                };
                if (_real && {_dl isEqualTo ""}) then {
                    _rows pushBack ["text", "Radio vue, mais pas reliée au téléphone : menu ACE Téléphone ATAK &gt; Radio de données &gt; Relier le téléphone à la radio (câble)." call _muted];
                };
                if (!_real) then {
                    (player getVariable ["COMSPEC_ATAK_Mpu5", []]) params [["_up", 0], ["_uh", 0]];
                    _rows pushBack ["info", "Partage avec l'équipe", [format ["%1 Mbit/s partagés%2", _up toFixed 1, ["", format [" (%1 bond(s) MPU5)", _uh]] select (_uh > 0)], "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>rien à partager : aucun relais reçu</t>"] select (_up <= 0)];
                };
            } else {
                _rows pushBack ["text", format ["Aucune radio de données sur vous : le téléphone porte seul à %1. Une MPU5, ou une radio ACRE2 / TFAR reliée, porte plus loin.", [["range", [player]] call comspec_atak_native_fnc_mpu5] call _dist] call _muted];
            };
            // Liaison réaliste (fn_mpuLink) : état de la MPU5, réglages dans l'app Réseau.
            if (_real) then {
                private _ms = ["status"] call comspec_atak_native_fnc_mpuLink;
                private _mv = ["view"] call comspec_atak_native_fnc_mpuLink;
                _rows pushBack ["info", "Autonomie estimée · trafic relayé", format ["%1 min · %2 nœud(s)", _mv get "left", (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["relayed", 0]]];
                _rows pushBack ["info", "État", format ["%1 · <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>réglages : app Réseau</t>",
                    [format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1</t>", [_ms get "why"] call _esc], format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1 saut(s) jusqu'à %2</t>", (_ms get "hops") max 0, [_ms get "gw"] call _esc]] select (_ms get "ok")]];
            };
            // Téléphone branché sur une radio ACRE2 / TFAR (ancien modèle) : elle relaie les données comme une MPU5.
            if (!_real && {missionNamespace getVariable ["comspec_atak_native_radio_data", true]}) then {
                private _pl = ["plugged"] call comspec_atak_native_fnc_mpu5;
                _rows pushBack ["buttons", [[["BRANCHER SUR MA RADIO", "DÉBRANCHER LA RADIO"] select (_pl isNotEqualTo []), {
                    ["plug"] call comspec_atak_native_fnc_mpu5;
                    [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                }]]];
            };
        };
        if (_acre || {_tfar}) then {
            if (["wrFoldVoice", "Voix par le réseau", [_vState] call _esc] call _fold) then {
                _rows pushBack ["info", "État", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexOk"] select (_vCode isEqualTo "on")), [_vState] call _esc]];
                _rows pushBack ["buttons", [["COMMENT L'ACTIVER", { ["open", ["voice"]] call comspec_atak_native_fnc_waveRelaySheet; }, _vCode isNotEqualTo "on"], ["ONGLET VOIX", ["wrTab", "'TG'"] call _setS]]];
            };
        };
        // Chiffrement de ma liaison : cadenas et libellé de la barre d'état, avec le détail (fn_meshKeys "link").
        (["link"] call comspec_atak_native_fnc_meshKeys) params ["_lockCode", "", "_lockLong", "_lockWhy", "_lockImg", "", "_lockHex"];
        if (["wrFoldKeys", "Chiffrement", toLower (_lockLong call comspec_atak_native_fnc_tr)] call _fold) then {
            _rows pushBack ["info", "État", format ["<img image='%1' color='%2'/> <t color='%2'>%3</t>", _lockImg, COMSPEC_ATAK_Th get _lockHex, (_lockLong) call comspec_atak_native_fnc_tr]];
            _rows pushBack ["text", ([(_lockWhy) call comspec_atak_native_fnc_tr] call _esc) call _muted];
            if (_real) then { _rows append (["rows"] call comspec_atak_native_fnc_meshKeys); };
        };
        // Ce qui change le débit, du plus gênant au moins gênant.
        private _factorRows = _st get "rows";
        private _bad = { (_x select 1) in ["warn", "ko"] } count _factorRows;
        if (["wrFoldRate", "Ce qui change votre débit", ["rien de gênant", format ["%1 gêne(s)", _bad]] select (_bad > 0)] call _fold) then {
            _rows append _factorRows;
            if (_factorRows isEqualTo []) then { _rows pushBack ["text", "Rien ne réduit votre débit en ce moment." call _muted]; };
        };
        if (["wrFoldNode", "Mon nœud", (_nodes select 0) select 2] call _fold) then {
            _rows append [
                ["info", "Passerelle", switch (true) do {
                    case (_gw < 0): { "<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>aucune</t>" };
                    case (_selfGw): { format ["vous-même · %1", [_mesh get "gwLabel"] call _esc] };
                    case (_myHops < 0): { format ["%1 · <t color='%2'>coupé d'elle</t>", [_mesh get "gwLabel"] call _esc, COMSPEC_ATAK_Th get "hexAlert"] };
                    default { format ["%1 · %2 saut(s)", [_mesh get "gwLabel"] call _esc, _myHops] };
                }],
                ["info", "Signal", format ["%1 (%2/4)", _q getOrDefault ["label", "?"], _q getOrDefault ["bars", 0]]]
            ];
            if (_gw > 0) then {
                private _gp = (_nodes select _gw) select 1;
                _rows pushBack ["info", "Distance du relais", format ["%1 · %2", [player distance2D _gp] call _dist, [_gp] call _bearing]];
            };
            // Qualité du chemin et cause (fn_meshGraph "view") : ce qui coupe ou limite, et quoi faire.
            if (_real) then {
                private _mm = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
                private _lvl = ((_mm getOrDefault ["level", 3]) max 0) min 3;
                _rows pushBack ["info", "Qualité du chemin", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexOk", "hexWarn", "hexWarn", "hexAlert"] select _lvl),
                    (["bonne", "dégradée", "à la limite", "coupée"] select _lvl) call comspec_atak_native_fnc_tr]];
                private _cz = _mm getOrDefault ["cause", ""];
                if (_cz isNotEqualTo "") then {
                    _rows pushBack ["info", "Cause", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexAlert"] select (_lvl >= 3)), [_cz call comspec_atak_native_fnc_tr] call _esc]];
                    private _fx = _mm getOrDefault ["fix", ""];
                    if (_fx isNotEqualTo "") then { _rows pushBack ["text", ([_fx call comspec_atak_native_fnc_tr] call _esc) call _muted]; };
                };
            };
            if (_selfGw) then { _rows pushBack ["text", "Pas de relais reçu : vous êtes votre propre passerelle (réseau libre, réglage du serveur). Le débit affiché est celui de votre téléphone." call _muted]; };
            private _talk = (["mine"] call comspec_atak_native_fnc_meshGroups) apply { ["name", [_x]] call comspec_atak_native_fnc_meshGroups };
            if (_talk isNotEqualTo []) then { _rows pushBack ["info", "Groupes", _talk joinString ", "]; };
            _rows pushBack ["switch", "Liens du maillage sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], { ["mapLinks"] call comspec_atak_native_fnc_waveRelayAction; }, "Traits colorés selon la qualité du lien"];
        };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
