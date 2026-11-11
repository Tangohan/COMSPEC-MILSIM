/*
    Mes couvertures sur Athena : fenêtre de l'app « Ma couverture » (téléphone ATAK, section Importer ma couverture,
    bouton SYNCHRONISER AVEC ATHENA). Liste les couvertures générées sur Athena pour le compte relié à ce poste, en importe
    une ou plusieurs, active celle à jouer. Aucune alerte hors du téléphone : tout s'affiche dans la fenêtre.
    Réseau : canal GetIdentityPack de COMSPEC Link (2.0.118 et plus, aucune nouvelle commande) avec l'opération « ~covers »
    (liste) ou « ~cover:CVR-…,CVR-… » (fichiers, 5 au plus par demande) ; la réponse est dans « settings » du paquet
    (site : SseCoverSyncService, AtakCoverSyncApiController). Appel non bloquant (fn_extensionCall dans une tâche, comme le
    paquet d'identité), puis morceaux GetIdentityPackChunk. Le tampon du paquet est partagé avec fn_idAthenaPack « fetch » :
    verrou comspec_sse_idPackBusy, et l'opération lue dans la réponse doit être celle demandée (sinon un second essai).
    Garde : rien ne part vers Athena si le service Athena est coupé, si la licence n'est pas acceptée, si la liaison Athena
    de ce poste n'est pas autorisée (COMSPEC_AthenaReady), si le compte n'est pas lié ou sans COMSPEC Link.
    Fichiers reçus : mêmes champs que l'import d'un fichier, revalidés par fn_idCoverImport « clean ». Une seule couverture
    est active à la fois (celle de la légende, fn_idLegend) : la plus récente de la sélection devient active, les autres
    restent sur ce poste (profil : comspec_sse_idCoverLib, ref -> [couverture, empreinte, date], 10 au plus), prêtes à
    activer depuis la fenêtre.
    Actions : ["open"] · ["close"] · ["retry"] · ["toggle", ref] · ["all"] · ["import"] · ["activate", ref]
              ["isOpen"] -> Boolean · ["rows"] -> lignes fn_formRender de la fenêtre
    Tâches de fond : ["pull", opération] -> [code ("" : réussi), settings] · ["download", couvertures] -> [importées,
              déjà à jour, échecs (ref -> code)] · ["listed", …] · ["fetched", …] (résultats)
    État (uiNamespace comspec_sse_idCoverSyncUi) : open, state (idle | loading | ready | empty | importing | off |
    licence | notready | unlinked | nodll | oldsite | error), err, covers (HashMaps reçues), sel (ref -> true), msg, kind.
*/
params [["_op", ""], ["_arg", ""]];
if (!hasInterface) exitWith { false };
private _ui = uiNamespace getVariable ["comspec_sse_idCoverSyncUi", createHashMap];
uiNamespace setVariable ["comspec_sse_idCoverSyncUi", _ui];
if (_op isEqualTo "isOpen") exitWith { _ui getOrDefault ["open", false] };
private _tr = { _this call comspec_sse_fnc_tr };
private _redraw = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
};
private _libGet = {
    private _l = profileNamespace getVariable ["comspec_sse_idCoverLib", createHashMap];
    if !(_l isEqualType createHashMap) then { _l = createHashMap; };
    _l
};
private _activeRef = {
    private _c = profileNamespace getVariable ["comspec_sse_idCoverImport", createHashMap];
    if (_c isEqualType createHashMap) then { _c getOrDefault ["ref", ""] } else { "" }
};
private _isRef = { params ["_r"]; _r isEqualType "" && {(count _r) isEqualTo 12} && {(_r select [0, 4]) isEqualTo "CVR-"} };
// Paires [["clé", valeur]…] (objet JSON converti par la DLL) -> HashMap.
private _h = {
    params ["_a"];
    if !(_a isEqualType []) exitWith { createHashMap };
    createHashMapFromArray (_a select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} })
};
// Garde avant toute requête : code d'état ("" : la requête peut partir). Rien ici ne touche au réseau.
private _gate = {
    if (!isNil "comspec_overwatch_connect_fnc_uplinkOff" && {[] call comspec_overwatch_connect_fnc_uplinkOff}) exitWith { "off" };
    if !(isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]}) exitWith { "licence" };
    if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { "notready" };
    private _status = if (isNil "comspec_atak_native_fnc_athenaStatus") then { createHashMap } else { [] call comspec_atak_native_fnc_athenaStatus };
    if !(_status getOrDefault ["logged", false]) exitWith { "unlinked" };
    if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { "nodll" };
    ""
};

// Demande au site par le paquet d'identité (tâche de fond) -> [code d'erreur ("" : réussi), settings (HashMap)].
if (_op isEqualTo "pull") exitWith {
    if (!canSuspend) exitWith { ["no_answer", createHashMap] };
    private _t = diag_tickTime;
    waitUntil { sleep 0.1; ((!(missionNamespace getVariable ["comspec_sse_idPackBusy", false])) && {!(missionNamespace getVariable ["comspec_sse_idPackFetching", false])}) || {(diag_tickTime - _t) > 30} };
    private _res = ["no_answer", createHashMap];
    private _again = true;
    private _try = 0;
    while { _again && {_try < 2} } do {
        _again = false;
        _try = _try + 1;
        missionNamespace setVariable ["comspec_sse_idPackBusy", true];
        private _head = ["GetIdentityPack", [str (missionNamespace getVariable ["COMSPEC_MapId", 1]), _arg, "v=1"]] call comspec_sse_fnc_extensionCall;
        private _headOk = (_head select [0, 3]) isEqualTo "OK|";
        private _n = if (_headOk) then { parseNumber ((_head splitString "|") param [1, "0"]) } else { 0 };
        private _buf = "";
        private _ok = _headOk && {_n >= 1} && {_n <= 60};
        if (_ok) then {
            for "_i" from 0 to (_n - 1) do {
                private _c = ["GetIdentityPackChunk", [str _i]] call comspec_sse_fnc_extensionCall;
                if ((_c select [0, 3]) isNotEqualTo "OK|") exitWith { _ok = false; };
                _buf = _buf + (_c select [3]);
            };
        };
        missionNamespace setVariable ["comspec_sse_idPackBusy", false];
        switch (true) do {
            case (_head isEqualTo ""): { _res = ["no_answer", createHashMap]; };
            case (!_headOk): { _res = [((_head splitString "|") param [1, "error"]) select [0, 24], createHashMap]; };
            case (!_ok): { _res = ["chunk", createHashMap]; };
            default {
                private _top = [parseSimpleArray _buf] call _h;
                if ((_top getOrDefault ["op", ""]) isNotEqualTo _arg) then {
                    // Tampon repris entre-temps par le paquet d'identité : un second essai.
                    _res = ["busy", createHashMap];
                    _again = true;
                } else {
                    private _set = [_top getOrDefault ["settings", []]] call _h;
                    _res = [["oldsite", ""] select ((_set getOrDefault ["covers_api", 0]) isEqualTo 1), _set];
                };
            };
        };
    };
    _res
};

// Fichiers manquants ou changés, par lots de 5 (tâche de fond) -> [importées, déjà à jour, échecs (ref -> code)].
if (_op isEqualTo "download") exitWith {
    private _lib = call _libGet;
    private _need = _arg select {
        private _have = _lib getOrDefault [_x get "ref", []];
        _have isEqualTo [] || {(_have select 1) isNotEqualTo (_x getOrDefault ["sha", ""])}
    };
    private _upToDate = (_arg - _need) apply { _x get "ref" };
    private _imported = [];
    private _failed = createHashMap;
    private _sha = createHashMapFromArray (_arg apply { [_x get "ref", _x getOrDefault ["sha", ""]] });
    // Couverture expirée : refusée par le site (« expired » dans errors), jamais servie.
    for "_i" from 0 to ((count _need) - 1) step 5 do {
        private _batch = (_need select [_i, 5]) apply { _x get "ref" };
        (["pull", "~cover:" + (_batch joinString ",")] call comspec_sse_fnc_idCoverAthena) params ["_err", "_set"];
        if (_err isNotEqualTo "") then {
            { _failed set [_x, _err]; } forEach _batch;
        } else {
            {
                private _e = [_x] call _h;
                _failed set [_e getOrDefault ["ref", ""], _e getOrDefault ["error", "invalid"]];
            } forEach (_set getOrDefault ["errors", []]);
            {
                private _f = [_x] call _h;
                private _c = [_f getOrDefault ["cover", []]] call _h;
                private _ref = _f getOrDefault ["ref", ""];
                private _m = +_c;
                { _m set [_x, _f getOrDefault [_x, ""]]; } forEach ["ref", "generated_at", "expires_at"];
                // Papiers : objets {type, country, number, issued, expires} -> [type, pays, numéro, délivré, expire].
                _m set ["docs", (_c getOrDefault ["docs", []]) apply {
                    private _d = [_x] call _h;
                    ["type", "country", "number", "issued", "expires"] apply { _d getOrDefault [_x, ""] }
                }];
                private _clean = ["clean", [_m]] call comspec_sse_fnc_idCoverImport;
                switch (true) do {
                    case !(_ref in _batch): {};
                    case ((count _clean) isEqualTo 0): { _failed set [_ref, "invalid"]; };
                    default {
                        _lib set [_ref, [_clean, _sha getOrDefault [_ref, ""], _clean getOrDefault ["generated_at", ""]]];
                        _imported pushBack _ref;
                    };
                };
            } forEach (_set getOrDefault ["files", []]);
            // Référence demandée restée sans réponse : comptée en échec.
            { if !(_x in _imported || {_x in _failed}) then { _failed set [_x, "invalid"]; }; } forEach _batch;
        };
    };
    // 10 couvertures gardées au plus sur ce poste : les plus anciennes partent, jamais l'active.
    if ((count _lib) > 10) then {
        private _act = call _activeRef;
        private _old = ((keys _lib) select { _x isNotEqualTo _act }) apply { [(_lib get _x) select 2, _x] };
        _old sort true;
        { _lib deleteAt (_x select 1); } forEach (_old select [0, (count _lib) - 10]);
    };
    profileNamespace setVariable ["comspec_sse_idCoverLib", _lib];
    saveProfileNamespace;
    [_imported, _upToDate, _failed]
};

// Lignes de la fenêtre (fn_idCoverPage les dessine à la place de la page tant qu'elle est ouverte).
if (_op isEqualTo "rows") exitWith {
    private _th = [] call comspec_atak_native_fnc_theme;
    private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
    private _act = { params ["_a", ["_x2", ""]]; compile format ["['%1', %2] call comspec_sse_fnc_idCoverAthena;", _a, str _x2] };
    private _icon = "\z\comspec_sse\addons\identity\data\app_id.paa";
    private _state = _ui getOrDefault ["state", "idle"];
    private _covers = _ui getOrDefault ["covers", []];
    private _sel = _ui getOrDefault ["sel", createHashMap];
    private _busy = _state in ["loading", "importing"];
    private _rows = [["header", "Mes couvertures sur Athena" call _tr, "Couvertures générées pour votre compte Athena" call _tr, [["FERMER" call _tr, ["close"] call _act]], ["close"] call _act]];
    private _msg = _ui getOrDefault ["msg", ""];
    if (_msg isNotEqualTo "") then {
        private _col = switch (_ui getOrDefault ["kind", ""]) do { case "ok": { _th get "hexOk" }; case "warn": { _th get "hexWarn" }; default { _th get "hexText" }; };
        _rows pushBack ["text", format ["<t color='%1'>%2</t>", _col, [_msg] call _esc], _th get "surface"];
    };
    private _retry = ["RÉESSAYER" call _tr, ["retry"] call _act];
    private _empty = switch (_state) do {
        case "off": { ["Service Athena coupé", "Le service Athena est sur Non sur ce poste : rien n'est demandé à Athena. Rallumez-le dans l'app Athena, ou importez un fichier.", []] };
        // Note de service ouverte d'ici (fn_licenceOpen de l'ATAK natif) : licence acceptée, la liste est relue toute seule.
        case "licence": { ["Licence pas encore acceptée", "Acceptez la licence (note de service du menu principal) : rien n'est envoyé à Athena avant.",
            [[], ["OUVRIR LA NOTE DE SERVICE" call _tr, compile "[{ params ['_ok']; if (_ok && {['isOpen'] call comspec_sse_fnc_idCoverAthena}) then { ['retry'] call comspec_sse_fnc_idCoverAthena; }; }, 'IDCOVER'] call comspec_atak_native_fnc_licenceOpen;"]] select (!isNil "comspec_atak_native_fnc_licenceOpen")] };
        case "notready": { ["Liaison Athena pas encore autorisée", "Ce poste n'est pas encore autorisé par Athena (connexion en cours). Attendez quelques secondes, puis réessayez.", _retry] };
        case "unlinked": { ["Compte Athena non lié", "Liez votre compte Athena à ce téléphone pour retrouver vos couvertures : app Athena du téléphone.", ["LIER MON COMPTE" call _tr, compile "['close'] call comspec_sse_fnc_idCoverAthena; ['ATHENA'] call comspec_sse_fnc_openApp;"]] };
        case "nodll": { ["COMSPEC Link absent ou trop ancien", "La synchronisation passe par COMSPEC Link 2.0.118 ou plus. Sans lui : téléchargez le fichier sur Athena, puis touchez Importer un fichier.", []] };
        case "oldsite": { ["Athena pas encore à jour", "Le site de votre communauté ne propose pas encore la synchronisation. Importez un fichier en attendant.", _retry] };
        case "error": { ["Athena n'a pas répondu", "Vérifiez la connexion de ce poste (app Athena), puis réessayez. Rien n'a changé sur votre couverture.", _retry] };
        case "empty": { ["Aucune couverture sur Athena", "Créez-en une sur Athena › Ma couverture (bouton Générer), puis touchez Réessayer.", _retry] };
        default { [] };
    };
    if (_empty isNotEqualTo []) exitWith {
        _empty params ["_t", "_s", "_a"];
        _s = _s call _tr;
        if (_state isEqualTo "error" && {(_ui getOrDefault ["err", ""]) isNotEqualTo ""}) then { _s = format ["%1 (%2)", _s, _ui get "err"]; };
        _rows pushBack ["empty", _icon, [_t call _tr] call _esc, [_s] call _esc, _a];
        _rows
    };
    if (_state in ["loading", "idle"]) exitWith {
        _rows pushBack ["skeleton", 3, "Recherche de vos couvertures sur Athena…" call _tr];
        _rows
    };
    // Une seule couverture active : le dire avant d'importer.
    _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", _th get "hexTextDim", [("Choisissez une ou plusieurs couvertures. La plus récente de la sélection devient votre couverture active et remplace celle d'avant ; les autres restent sur ce poste, prêtes à activer ici." call _tr)] call _esc]];
    private _valid = _covers select { (_x getOrDefault ["status", ""]) isEqualTo "valid" };
    private _allOn = _valid isNotEqualTo [] && {(_valid findIf { !((_x get "ref") in _sel) }) < 0};
    private _n = count (_valid select { (_x get "ref") in _sel });
    _rows pushBack ["buttons", [
        [(["TOUT SÉLECTIONNER", "TOUT DÉSÉLECTIONNER"] select _allOn) call _tr, ["all"] call _act, false, !_busy && {_valid isNotEqualTo []}],
        [format [("IMPORTER LA SÉLECTION (%1)" call _tr), _n], ["import"] call _act, true, !_busy && {_n > 0}, "Télécharge les couvertures choisies depuis Athena et active la plus récente." call _tr]
    ]];
    private _lib = call _libGet;
    private _active = call _activeRef;
    {
        private _c = _x;
        private _ref = _c get "ref";
        private _have = _lib getOrDefault [_ref, []];
        private _status = _c getOrDefault ["status", ""];
        (switch (true) do {
            case (_status isEqualTo "expired"): { ["Expirée", _th get "hexTextMuted", _th get "off"] };
            case (_status isNotEqualTo "valid"): { ["Illisible sur Athena", _th get "hexAlert", _th get "alert"] };
            case (_ref isEqualTo _active): { ["Active", _th get "hexOk", _th get "ok"] };
            case (_have isNotEqualTo [] && {(_have select 1) isNotEqualTo (_c getOrDefault ["sha", ""])}): { ["Mise à jour disponible", _th get "hexWarn", _th get "warn"] };
            case (_have isNotEqualTo []): { ["Déjà importée", _th get "hexOk", _th get "ok"] };
            default { ["Nouvelle", _th get "hexText", _th get "textDim"] };
        }) params ["_word", "_hex", "_rgba"];
        private _line = [
            [_c getOrDefault ["country", ""]] call _esc,
            format [("%1 Kio" call _tr), ((round ((_c getOrDefault ["size", 0]) / 1024)) max 1)],
            format [("créée le %1" call _tr), (_c getOrDefault ["updated_at", ""]) select [0, 10]]
        ];
        if (_c getOrDefault ["has_photo", false]) then { _line pushBack ("photo sur Athena" call _tr); };
        if (_c getOrDefault ["by_manager", false]) then { _line pushBack ("faite par un responsable" call _tr); };
        private _text = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t><br/><t size='0.85' color='%3'>%4</t><br/><t size='0.85' font='COMSPEC_BarlowBold' color='%5'>%6</t><t size='0.8' color='%3'> · %7</t>",
            _th get "hexText", [_c getOrDefault ["name", "?"]] call _esc, _th get "hexTextDim", [_c getOrDefault ["summary", ""]] call _esc,
            _hex, [_word call _tr] call _esc, (_line select { _x isNotEqualTo "" }) joinString " · "];
        private _on = _ref in _sel;
        private _btns = [[(["CHOISIR", "CHOISIE"] select _on) call _tr, ["toggle", _ref] call _act, _on, _status isEqualTo "valid" && {!_busy}, "Ajoute ou retire cette couverture de la sélection." call _tr]];
        if (_have isNotEqualTo [] && {_ref isNotEqualTo _active}) then {
            _btns pushBack ["ACTIVER" call _tr, ["activate", _ref] call _act, false, !_busy, "Joue cette couverture, déjà sur ce poste, à la place de l'active." call _tr];
        };
        _rows pushBack ["person", _icon, _text, _btns, _rgba, "", "", _rgba];
    } forEach _covers;
    _rows pushBack ["buttons", [["RÉESSAYER" call _tr, ["retry"] call _act, false, !_busy, "Relit la liste sur Athena." call _tr], ["FERMER" call _tr, ["close"] call _act]]];
    _rows
};

switch (_op) do {
    case "open": {
        _ui set ["open", true];
        _ui set ["sel", createHashMap];
        _ui set ["msg", ""];
        ["retry"] call comspec_sse_fnc_idCoverAthena;
    };
    case "close": {
        _ui set ["open", false];
        call _redraw;
    };
    case "toggle": {
        if !([_arg] call _isRef) exitWith {};
        private _sel = _ui getOrDefault ["sel", createHashMap];
        _ui set ["sel", _sel];
        if (_arg in _sel) then { _sel deleteAt _arg; } else { _sel set [_arg, true]; };
        call _redraw;
    };
    case "all": {
        private _ok = (_ui getOrDefault ["covers", []]) select { (_x getOrDefault ["status", ""]) isEqualTo "valid" };
        private _sel = _ui getOrDefault ["sel", createHashMap];
        private _allOn = _ok isNotEqualTo [] && {(_ok findIf { !((_x get "ref") in _sel) }) < 0};
        private _new = createHashMap;
        if (!_allOn) then { { _new set [_x get "ref", true]; } forEach _ok; };
        _ui set ["sel", _new];
        call _redraw;
    };
    case "activate": {
        private _entry = (call _libGet) getOrDefault [_arg, []];
        if (_entry isEqualTo []) exitWith {};
        ["use", [_entry select 0]] call comspec_sse_fnc_idCoverImport;
        _ui set ["kind", "ok"];
        _ui set ["msg", format [("« %1 » est maintenant votre couverture active." call _tr), (_entry select 0) getOrDefault ["name", ""]]];
        call _redraw;
    };
    case "retry": {
        // Une demande à la fois ; une tâche restée sans réponse plus de 2 minutes ne bloque plus la fenêtre.
        if ((_ui getOrDefault ["state", ""]) in ["loading", "importing"] && {(diag_tickTime - (_ui getOrDefault ["at", -1e9])) < 120}) exitWith {};
        private _g = call _gate;
        if (_g isNotEqualTo "") exitWith { _ui set ["state", _g]; _ui set ["msg", ""]; call _redraw; };
        _ui set ["state", "loading"];
        _ui set ["at", diag_tickTime];
        _ui set ["kind", ""];
        _ui set ["msg", ""];
        call _redraw;
        [] spawn { ["listed", ["pull", "~covers"] call comspec_sse_fnc_idCoverAthena] call comspec_sse_fnc_idCoverAthena; };
    };
    case "listed": {
        _arg params [["_err", "no_answer"], ["_set", createHashMap]];
        if (_err isNotEqualTo "") exitWith {
            _ui set ["state", switch (true) do {
                case (_err in ["unauthorized", "forbidden", "no_tenant", "unlinked"]): { "unlinked" };
                case (_err isEqualTo "uplink_off"): { "off" };
                case (_err isEqualTo "oldsite"): { "oldsite" };
                default { "error" };
            }];
            _ui set ["err", _err];
            _ui set ["msg", ""];
            call _redraw;
        };
        private _covers = ((_set getOrDefault ["covers", []]) apply { [_x] call _h }) select { [_x getOrDefault ["ref", ""]] call _isRef };
        _covers = _covers select [0, 20];
        _ui set ["covers", _covers];
        _ui set ["state", ["empty", "ready"] select (_covers isNotEqualTo [])];
        _ui set ["kind", ""];
        _ui set ["msg", [format [("%1 couverture(s) trouvée(s) sur Athena." call _tr), count _covers], ""] select (_covers isEqualTo [])];
        // Sélection gardée pour les couvertures toujours là.
        private _sel = _ui getOrDefault ["sel", createHashMap];
        private _refs = _covers apply { _x get "ref" };
        { if !(_x in _refs) then { _sel deleteAt _x; }; } forEach (keys _sel);
        call _redraw;
    };
    case "import": {
        if ((_ui getOrDefault ["state", ""]) isNotEqualTo "ready") exitWith {};
        private _sel = _ui getOrDefault ["sel", createHashMap];
        // Ordre de la liste (la plus récente d'abord) : la première choisie devient active.
        private _chosen = (_ui getOrDefault ["covers", []]) select { ((_x get "ref") in _sel) && {(_x getOrDefault ["status", ""]) isEqualTo "valid"} };
        if (_chosen isEqualTo []) exitWith {};
        private _g = call _gate;
        if (_g isNotEqualTo "") exitWith { _ui set ["state", _g]; call _redraw; };
        _ui set ["state", "importing"];
        _ui set ["at", diag_tickTime];
        _ui set ["kind", ""];
        _ui set ["msg", "Importation…" call _tr];
        call _redraw;
        [_chosen] spawn {
            params ["_chosen"];
            ["fetched", [_chosen, ["download", _chosen] call comspec_sse_fnc_idCoverAthena]] call comspec_sse_fnc_idCoverAthena;
        };
    };
    case "fetched": {
        _arg params ["_chosen", "_res"];
        _res params [["_imported", []], ["_upToDate", []], ["_failed", createHashMap]];
        private _lib = call _libGet;
        // La plus récente de la sélection présente sur ce poste devient active.
        private _first = ((_chosen apply { _x get "ref" }) select { _x in _lib }) param [0, ""];
        if (_first isNotEqualTo "") then { ["use", [(_lib get _first) select 0]] call comspec_sse_fnc_idCoverImport; };
        private _parts = [];
        if (_imported isNotEqualTo []) then { _parts pushBack format [("%1 importée(s)" call _tr), count _imported]; };
        if (_upToDate isNotEqualTo []) then { _parts pushBack format [("%1 déjà à jour" call _tr), count _upToDate]; };
        if ((count _failed) > 0) then { _parts pushBack format [("%1 en échec" call _tr), count _failed]; };
        private _msg = (_parts joinString ", ") + ".";
        if (_first isNotEqualTo "") then {
            _msg = format ["%1 %2", _msg, format [("« %1 » est maintenant votre couverture active." call _tr), ((_lib get _first) select 0) getOrDefault ["name", ""]]];
        };
        private _why = values _failed;
        if (_why isNotEqualTo []) then {
            _msg = format ["%1 %2", _msg, (switch (true) do {
                case ("rate_limited" in _why): { "Trop de demandes : réessayez dans quelques minutes." };
                case ("expired" in _why): { "Une couverture expirée ne s'importe plus : générez-en une nouvelle sur Athena." };
                case ("not_found" in _why): { "Une couverture n'est plus sur Athena : touchez Réessayer pour relire la liste." };
                default { "Athena n'a pas répondu pour une partie de la sélection : touchez Importer la sélection pour réessayer." };
            }) call _tr];
        };
        _ui set ["kind", ["warn", "ok"] select ((count _failed) isEqualTo 0)];
        _ui set ["msg", _msg];
        _ui set ["state", "ready"];
        _ui set ["sel", createHashMap];
        call _redraw;
    };
};
true
