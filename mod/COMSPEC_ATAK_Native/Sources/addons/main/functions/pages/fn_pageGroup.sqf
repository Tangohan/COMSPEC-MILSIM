/*
    Groupe : onglet MON GROUPE (fiche du groupe, bilan, membres, réglages du chef repliables, quitter),
    onglet ÉQUIPES (équipes de feu du groupe : créer, rejoindre, quitter, couleurs, icônes, descriptions, rôles ; fn_ftRows)
    et onglet GROUPES (autres groupes de mon camp avec joueurs, rejoindre).
    Membres : triés chef, moi, puis hors de combat, blessés, aptes ; l'état en liseré à gauche et en toutes lettres.
    Sur chaque ligne : SMS, CARTE (centre et sélectionne) et FICHE (fiche unique dépliée sous la ligne, fn_unitCard :
    toutes les actions, dont le passage du commandement en deux temps).
    Actions de groupe (fn_groupAction) : RALLIEMENT SUR MOI (chef), SMS AU GROUPE, CADRER LE GROUPE.
    Sections repliables : état dans COMSPEC_ATAK_State, clés « grpFold_<nom> » (fn_groupAction "fold").
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["grpTab", "MINE"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _types = [] call comspec_atak_native_fnc_groupTypes;
private _typeOf = { params ["_g"]; private _k = _g getVariable ["COMSPEC_GroupType", "INF"]; (_types select { (_x select 0) isEqualTo _k }) param [0, _types select 0] };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
// Distance et direction depuis moi (« 340 m NE ») : où chercher le joueur sans ouvrir la carte.
private _card8 = [["N", "NE", "E", "SE", "S", "SW", "W", "NW"], ["N", "NE", "E", "SE", "S", "SO", "O", "NO"]] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr");
private _where = { params ["_u"]; format ["%1 %2", [_u distance2D player] call _dist, _card8 select ((round ((player getDir _u) / 45)) mod 8)] };
// Initiale d'un nom (pastille sans photo, comme les SMS) : première lettre, « # » sans lettre ni chiffre.
private _ini = { params ["_n"]; toUpper ((((_n splitString " -_.:[]()|") select { _x isNotEqualTo "" }) param [0, "#"]) select [0, 1]) };
// Ouvre la conversation SMS / centre la carte sur un joueur (boutons des membres).
private _smsCode = { params ["_u"]; compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatPeer', %1]; _s set ['chatView', 'CONV']; _s set ['chatPick', false]; ['CHAT'] call comspec_atak_native_fnc_navigate;", str (name _u)] };
private _mapCode = { params ["_u"]; compile format ["['do', ['center', %1]] call comspec_atak_native_fnc_unitCard;", str ("o:" + netId _u)] };
private _isOpen = { params ["_k", ["_def", false]]; _s getOrDefault [format ["grpFold_%1", _k], _def] };
private _fold = { params ["_k", "_t", "_sub", ["_def", false]]; ["fold", _t, _sub, [_k, _def] call _isOpen, compile format ["['fold', ['%1', %2]] call comspec_atak_native_fnc_groupAction;", _k, _def]] };
private _grp = group player;
private _isLead = (leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1};
private _others = allGroups select { side _x isEqualTo side _grp && {_x isNotEqualTo _grp} && {(count units _x) > 0} && {((units _x) findIf { isPlayer _x }) >= 0} };
private _nTeams = count (_grp getVariable ["COMSPEC_FireTeams", []]);
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['grpTab', '%1']; [{ ['GROUP'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k], _tab isEqualTo _k] };
// Onglets soulignés (composant « tabs », 3.1.0) : compteurs en pastille à côté du libellé.
private _rows = [["tabs", [(["MON GROUPE", "MINE"] call _tabBtn) + [count units _grp], (["ÉQUIPES", "FT"] call _tabBtn) + [_nTeams], (["GROUPES", "ALL"] call _tabBtn) + [count _others]]]];
// État d'un membre : [code, texte coloré]. Code : 0 apte, 1 blessé, 2 hors de combat (inconscient ou mort), -1 inconnu.
private _state = {
    params ["_u"];
    (["state", _u] call comspec_atak_native_fnc_unitCard) params ["_c", "_lbl", "_hex", "_rgba"];
    [_c min 2, format ["<t color='%1'>%2</t>", _hex, _lbl], _rgba]
};

if (_tab isEqualTo "FT") then { _rows append ([] call comspec_atak_native_fnc_ftRows); };

if (_tab isEqualTo "MINE") then {
    ([_grp] call _typeOf) params ["_tk", "_tname", "_ticon"];
    private _locked = _grp getVariable ["COMSPEC_GroupLocked", false];
    private _units = units _grp;
    // Fiche : symbole OTAN, nom en grand, type et effectif, chef et accès.
    _rows pushBack ["hero", _ticon, format ["<t size='1.35' font='COMSPEC_BarlowBold'>%1</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>%2</t><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · </t>%3 membre(s)<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · </t>%4 équipe(s)<br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Chef : </t><t size='0.85'>%5</t><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · </t>%6",
        [groupId _grp] call _esc, _tname, count _units, _nTeams,
        [[name leader _grp] call _esc, format ["%1 (moi)", [name leader _grp] call _esc]] select ((leader _grp) isEqualTo player),
        ["<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>● Ouvert</t>", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● Fermé</t>"] select _locked]];

    // Bilan : la réglette d'effectif (fn_squadBar), le même dessin que sur la carte et dans l'ORBAT, et la distance du plus loin.
    // Un clic sur une case déplie la fiche de ce membre.
    private _far = 0;
    { _far = _far max (_x distance2D player); } forEach _units;
    _rows pushBack ["squad", ["cells", _grp] call comspec_atak_native_fnc_squadBar, format ["<t font='COMSPEC_BarlowBold'>%1</t>", [groupId _grp] call _esc],
        ["", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Le plus loin : %1</t>", [_far] call _dist]] select ((count _units) > 1),
        "['card', %1] call comspec_atak_native_fnc_groupAction;"];

    // Actions de groupe : un geste au lieu de huit. Ralliement réservé au chef ; il part en un seul envoi.
    private _many = (count _units) > 1;
    _rows pushBack ["big", [
        ["RALLIEMENT SUR MOI", { ["rally"] call comspec_atak_native_fnc_groupAction; }, "amber", _isLead && {_many}, ["Réservé au chef du groupe", "Fait vibrer les téléphones du groupe (« RALLIEMENT ») et pose un ping sur vous"] select _isLead],
        ["SMS AU GROUPE", { ["smsGroup"] call comspec_atak_native_fnc_groupAction; }, "", _many, "Ouvre le canal du groupe, sinon un SMS à tous ses joueurs"],
        ["CADRER LE GROUPE", { ["frameGroup"] call comspec_atak_native_fnc_groupAction; }, "", true, "Ouvre la carte à l'échelle qui contient tous les membres vivants"]
    ]];
    // Sans canal de groupe : SMS préparé ici, envoyé à chaque joueur du groupe.
    if (_s getOrDefault ["grpSms", false]) then {
        _rows pushBack ["field", "grpSmsText", "SMS à tous les joueurs du groupe", "", "ENVOYER", { ["smsGroupSend"] call comspec_atak_native_fnc_groupAction; }];
    };

    // Membres : chef, moi, puis hors de combat, blessés, aptes ; à gravité égale, du plus proche au plus loin.
    _rows pushBack ["section", format ["Membres (%1)", count _units], ["", "FICHE : toutes les actions, dont le passage du commandement"] select (_isLead && {_many})];
    private _sorted = _units apply {
        private _k = ([_x] call _state) select 0;
        [switch (true) do { case (_x isEqualTo leader _grp): { 0 }; case (_x isEqualTo player): { 1 }; default { 2 }; }, [2, [1, 0] select (_k isEqualTo 2)] select (_k >= 1), _x distance2D player, _x]
    };
    _sorted sort true;
    private _cardOf = _s getOrDefault ["grpCard", ""];
    {
        private _u = _x select 3;
        private _me = _u isEqualTo player;
        // Photo Athena seulement si Arma sait la lire (fn_avatarPath passe par fn_texUsable), sinon l'initiale dans
        // une pastille aux couleurs de l'équipe de feu (le cadre restait noir).
        private _avatar = [_u] call comspec_atak_native_fnc_avatarPath;
        private _role = getText (configOf _u >> "displayName");
        private _ft = [_u] call comspec_atak_native_fnc_ftInfo;
        if ((_ft get "roleLabel") isNotEqualTo "") then { _role = _ft get "roleLabel"; };
        private _tint = [[0.36, 0.78, 0.42, 1], _ft get "rgba"] select ((_ft get "id") isNotEqualTo "");
        private _btns = [];
        if (!_me && {isPlayer _u}) then { _btns pushBack ["SMS", [_u] call _smsCode, false, alive _u, "Écrire un SMS à ce joueur"]; };
        if (!_me) then { _btns pushBack ["CARTE", [_u] call _mapCode, false, true, "Voir ce joueur sur la carte"]; };
        if (!_me) then { _btns pushBack ["FICHE", compile format ["['card', '%1'] call comspec_atak_native_fnc_groupAction;", netId _u], (netId _u) isEqualTo _cardOf, true, "Toutes les actions sur ce joueur"]; };
        private _st = [_u] call _state;
        _rows pushBack ["person", _avatar, format ["<t font='COMSPEC_BarlowBold'>%1</t>%2%3%4<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%5</t><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · </t><t size='0.8'>%6</t><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · %7</t>",
            [name _u] call _esc,
            ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>CHEF</t>"] select (_u isEqualTo leader _grp),
            ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>MOI</t>"] select _me,
            ["", format ["  <t size='0.8' color='%1'>%2</t>", _ft get "hex", [_ft get "name"] call _esc]] select ((_ft get "id") isNotEqualTo ""),
            [_role] call _esc, _st select 1,
            [[_u] call _where, "ici"] select _me], _btns, _tint, [name _u] call _ini, "", _st select 2];
        // Fiche dépliée sous la ligne : la fiche longue et la barre d'actions, les mêmes que sur la carte et dans le BFT.
        if (!_me && {(netId _u) isEqualTo _cardOf}) then {
            _rows pushBack ["text", [true, ["resolve", _u] call comspec_atak_native_fnc_unitCard] call comspec_atak_native_fnc_inspectorUpdate, COMSPEC_ATAK_Th get "surface"];
            _rows append (["rows", [_u, "group"]] call comspec_atak_native_fnc_unitCard);
        };
    } forEach _sorted;

    // Réglages du chef, repliés par défaut pour laisser la liste des membres visible.
    if (_isLead) then {
        private _r = ["perso", "Personnaliser le groupe", format ["%1 · %2", _tname, ["ouvert", "fermé"] select _locked]] call _fold;
        _rows pushBack _r;
        if (_r select 3) then {
            _rows append [
                ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Visible par tout votre camp : carte, BFT et app Groupe.</t>"],
                ["field", "grpName", "Nom du groupe", groupId _grp, "RENOMMER", { ["rename"] call comspec_atak_native_fnc_groupAction; }],
                ["combo", "grpType", "Type", _types apply { [_x select 1, _x select 0, _x select 2, [0.28, 0.70, 1, 1]] }, _tk, ["APPLIQUER", { params ["_k"]; ["type", _k] call comspec_atak_native_fnc_groupAction; }]],
                ["switch", "Groupe fermé", _locked, { ["lock"] call comspec_atak_native_fnc_groupAction; }, "Personne ne peut le rejoindre depuis son téléphone"]
            ];
        };
    } else {
        _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Seul le chef peut renommer le groupe, changer son type ou le fermer.</t>"];
    };

    // Mon indicatif (fn_callsignAction), comme dans C2.
    _rows append (["rows"] call comspec_atak_native_fnc_callsignAction);
    _rows pushBack ["gap"];
    private _alone = (count _units) isEqualTo 1;
    _rows pushBack ["buttons", [["QUITTER LE GROUPE", { ["leave"] call comspec_atak_native_fnc_groupAction; }, false, !_alone, ["Quitter crée un groupe à votre nom", "Vous êtes déjà seul dans votre groupe"] select _alone]]];
    _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Quitter crée un groupe à votre nom, que d'autres peuvent rejoindre.</t>"];
};

if (_tab isEqualTo "ALL") then {
    _rows pushBack ["section", format ["Groupes de mon camp (%1)", count _others], "Avec au moins un joueur, du plus proche au plus loin"];
    private _sorted = _others apply { [(leader _x) distance2D player, _x] };
    _sorted sort true;
    {
        _x params ["_d", "_g"];
        ([_g] call _typeOf) params ["", "_tname", "_ticon"];
        private _locked = _g getVariable ["COMSPEC_GroupLocked", false];
        _rows pushBack ["person", _ticon, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>%2</t>%6<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%3 membre(s) · chef </t><t size='0.8'>%4</t><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'> · %5</t>",
            [groupId _g] call _esc, _tname, count units _g, [name leader _g] call _esc, [leader _g] call _where,
            ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● Fermé</t>"] select _locked],
            [[["REJOINDRE", "FERMÉ"] select _locked, compile format ["['join', '%1'] call comspec_atak_native_fnc_groupAction;", netId _g], !_locked, !_locked, ["Rejoindre ce groupe", "Demandez au chef de l'ouvrir"] select _locked]], [0.28, 0.70, 1, 1]];
    } forEach _sorted;
    if ((count _others) isEqualTo 0) then {
        _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_group.paa", "Aucun autre groupe", "Aucun groupe avec des joueurs dans votre camp pour l'instant."];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
