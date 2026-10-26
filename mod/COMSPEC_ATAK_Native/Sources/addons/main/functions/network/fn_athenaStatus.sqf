/*
    État de la connexion au compte Athena, en toutes lettres (app Athena, et toute app qui veut afficher la connexion).
    Une seule lecture des variables d'Overwatch connect (ou de la DLL native sans Overwatch), traduite en un état clair,
    sa cause et l'action à faire, plus les étapes de la liaison, de la DLL jusqu'au canal du poste.
    Retour : HashMap
      key     "battleye" | "off" | "unset" | "disconnected" | "connecting" | "code" | "syncing" | "unlinked" | "error"
              | "connected" | "degraded" | "channel"
      logged  session ouverte (compte connecté)
      title   état en toutes lettres (« Connecté en tant que TA1 »)
      text    explication courte        action  ce que le joueur doit faire ("" : rien)
      color   couleur RGBA de l'état    fix     bouton proposé : "reconnect" | "code" | "channel" | "service" | "choose" | ""
      steps   [[libellé, niveau, valeur, aide]...], niveau "ok" | "now" | "warn" | "ko" | "todo" | "off"
      sig     [état de session, liaison, canal] comparé par fn_statusUpdate
      code    code d'erreur brut de la session ("" sans erreur), en majuscules
    Textes en français, traduits à l'affichage (fn_tr).
*/
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _v = { params ["_k", ["_d", ""]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
private _mode = [] call comspec_atak_native_fnc_athenaMode;

// Session : variables d'Overwatch connect, ou état renvoyé par la DLL native.
private _state = ["comspec_overwatch_auth_state", ""] call _v;
private _sigState = ["comspec_overwatch_auth_state", "—"] call _v;
private _err = ["comspec_overwatch_auth_error"] call _v;
private _link = toLower (["COMSPEC_LinkState", "offline"] call _v);
private _detail = ["COMSPEC_LinkDetail"] call _v;
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _linked = missionNamespace getVariable ["COMSPEC_OperatorLinked", false];
if !(_bridge) then {
    private _cells = (["GetAuthState", []] call comspec_atak_native_fnc_extensionCall) splitString "|";
    _state = _cells param [1, ""];
    _sigState = _cells param [1, "—"];
    _err = _cells param [4, ""];
    _ready = _state isEqualTo "READY";
    _linked = _ready;
    _link = ["offline", "linked"] select _ready;
};
if (_err in ["-", "—"]) then { _err = ""; };
private _logged = _state isEqualTo "READY";

// DLL de liaison : relue toutes les 20 s (BattlEye bloque les extensions non autorisées, cause quasi constante).
private _be = uiNamespace getVariable ["COMSPEC_ATAK_BattlEyeCheck", [-100, false]];
if (diag_tickTime - (_be select 0) > 20) then {
    _be = [diag_tickTime, ([true] call comspec_atak_native_fnc_extVersion) isEqualTo ""];
    uiNamespace setVariable ["COMSPEC_ATAK_BattlEyeCheck", _be];
};
private _dllDown = _be select 1;
private _dllVer = missionNamespace getVariable ["COMSPEC_ExtensionVersion", ""];
if !(_dllVer isEqualType "") then { _dllVer = ""; };

// Code de connexion (fn_athenaLink).
(uiNamespace getVariable ["COMSPEC_ATAK_AthenaLink", ["", "", 0, "", "", ""]]) params ["_ls", "_lc", "_lu", "", "", "_ld"];
if (_ls isEqualTo "pending" && {diag_tickTime >= _lu}) then { _ls = "expired"; };
// Code refusé par Athena (réponse HTTP 4xx) : le serveur répond, c'est le compte, la communauté ou le mod qui bloque.
private _refused = _ls isEqualTo "error" && {(_ld find "http_4") isEqualTo 0};
private _refusal = switch (true) do {
    case (!_refused): { [] };
    case (_ld isEqualTo "http_426"): { ["Votre mod est plus ancien que la version minimale de votre communauté.", "Mettez le mod à jour sur Steam, ou demandez à votre staff de baisser la version minimale (réglages ATAK de la communauté sur Athena)."] };
    case (_ld isEqualTo "http_403"): { ["Athena a refusé ce compte pour le jeu.", "Compte désactivé, pas membre de la communauté, ATAK bloqué ou mod non autorisé : prévenez votre staff avec ce message."] };
    case (_ld isEqualTo "http_401"): { ["Athena n'a pas reconnu le compte qui a autorisé le code.", "Connectez-vous au bon compte sur le site, puis NOUVEAU CODE."] };
    case (_ld isEqualTo "http_429"): { ["Trop de demandes envoyées à Athena.", "Patientez une minute, puis RÉESSAYER."] };
    default { ["Athena a refusé ce code.", "Demandez un nouveau code et autorisez-le à nouveau sur le site."] };
};

// Indicatif et nom affichés.
private _cs = ["comspec_profile_callsign"] call _v;
if (_cs in ["", "—"] && {_bridge} && {!isNil "comspec_overwatch_connect_fnc_getCallsign"}) then {
    private _g = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (!isNil "_g" && {_g isEqualType ""}) then { _cs = _g; };
};
private _name = ["comspec_profile_name"] call _v;
private _who = switch (true) do {
    case (_cs isNotEqualTo "" && {_name isNotEqualTo ""} && {_name isNotEqualTo _cs}): { format ["%1 (%2)", _cs, _name] };
    case (_cs isNotEqualTo ""): { _cs };
    case (_name isNotEqualTo ""): { _name };
    default { name player };
};
private _tenant = ["comspec_tenant_name"] call _v;

// Liaison qui a répondu « compte non lié » (le portail répond mais ce jeu n'est relié à aucun compte).
private _det = toLower _detail;
private _notLinked = _bridge && {!_logged} && {((_det find "not linked") >= 0) || {(_det find "non li") >= 0} || {_cs isNotEqualTo "" && {!_linked} && {_link isEqualTo "linked"}}};
private _syncing = _state in ["INITIALIZING_SESSION", "CONTACTING_ATHENA", "EXTENSION_READY", "AUTHENTICATING", "RESOLVING_ACCOUNT", "RESOLVING_TENANT", "SYNCING_PROFILE", "LOADING_BRANDING", "LOADING_CONFIGURATION", "CONNECTING_C2", "RESTORING_SESSION"];
private _stepName = switch (_state) do {
    case "CONTACTING_ATHENA": { "contact du serveur Athena" };
    case "AUTHENTICATING": { "vérification du compte" };
    case "RESOLVING_ACCOUNT": { "recherche du compte" };
    case "RESOLVING_TENANT": { "recherche de la communauté" };
    case "SYNCING_PROFILE": { "synchronisation du profil" };
    case "LOADING_BRANDING";
    case "LOADING_CONFIGURATION": { "réglages de la communauté" };
    case "CONNECTING_C2": { "ouverture du canal du poste" };
    case "RESTORING_SESSION": { "reprise de la session précédente" };
    default { "ouverture de la session" };
};

// Erreur de la DLL ou du portail : cause lisible et action, jamais le code brut seul.
private _code = toUpper ([_err, _state] select (_err isEqualTo "" && {!(_state in ["", "READY", "INITIALIZING", "DISABLED", "AWAITING_OTP"])} && {!_syncing}));
private _why = switch (true) do {
    case (_code isEqualTo ""): { [] };
    case (_code in ["INVALID_CREDENTIALS"]): { ["E-mail ou mot de passe incorrect.", "Vérifiez vos identifiants, ou recevez un code par e-mail (onglet E-MAIL)."] };
    case (_code in ["NETWORK_ERROR", "TIMEOUT", "HTTP_503", "HTTP_502", "HTTP_504"]): { ["Le serveur Athena ne répond pas.", "Vérifiez la connexion internet du jeu, puis RECONNECTER."] };
    case (_code in ["SESSION_EXPIRED", "SESSION_REVOKED", "TOKEN_REVOKED", "REFRESH_REVOKED", "INVALID_REFRESH", "UNAUTHORIZED", "C2_UNAUTHORIZED", "HTTP_401"]): { ["Votre session a expiré.", "Reconnectez-vous : OBTENIR MON CODE, ou e-mail et mot de passe."] };
    case (_code in ["STEAM_NOT_LINKED", "C2_STEAM_NOT_LINKED"]): { ["Ce compte Steam n'est relié à aucun compte Athena.", "Reliez-le une fois : OBTENIR MON CODE et autorisez-le sur le site."] };
    case (_code in ["C2_STEAM_REQUIRED", "C2_INVALID_STEAM", "STEAM_REQUIRED"]): { ["Identité Steam introuvable.", "Lancez Arma depuis Steam, ou connectez-vous par code ou e-mail."] };
    case (_code in ["NO_TENANT", "C2_TENANT_REQUIRED", "TENANT_CONTEXT_REQUIRED", "TENANT_REQUIRED"]): { ["Votre compte n'est membre d'aucune communauté.", "Demandez à votre staff de vous ajouter à la communauté sur Athena."] };
    case (_code in ["TENANT_DISABLED"]): { ["Votre communauté est désactivée sur Athena.", "Prévenez votre staff."] };
    case (_code in ["ACCOUNT_DISABLED", "C2_ACCOUNT_DISABLED", "HTTP_403"]): { ["Votre compte Athena est désactivé ou suspendu.", "Contactez votre staff ou le support Athena."] };
    case (_code in ["MOD_OUTDATED", "UPDATE_REQUIRED"]): { ["Votre mod est trop ancien pour Athena.", "Mettez le mod à jour sur Steam, puis relancez Arma."] };
    case (_code in ["OTP_EXPIRED"]): { ["Le code reçu par e-mail a expiré.", "Demandez un nouveau code (onglet E-MAIL)."] };
    case (_code in ["C2_DEGRADED"]): { ["Liaison avec le poste instable.", "Elle se rétablit seule en général ; sinon RECONNECTER."] };
    default { [format ["Erreur inattendue (%1).", _err], "Essayez RECONNECTER ; si cela continue, prévenez votre staff avec ce message."] };
};

private _green = [0.36, 0.78, 0.42, 1];
private _amber = [0.95, 0.67, 0.2, 1];
private _red = [0.9, 0.28, 0.23, 1];
private _grey = [0.6, 0.67, 0.64, 1];
private _lat = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
private _latText = ["", format [" · %1 ms", round _lat]] select (_lat isEqualType 0 && {_lat >= 0});

private _out = createHashMap;
private _set = { params ["_k", "_t", "_tx", "_a", "_c", "_f"]; _out set ["key", _k]; _out set ["title", _t]; _out set ["text", _tx]; _out set ["action", _a]; _out set ["color", _c]; _out set ["fix", _f]; };
switch (true) do {
    case (_dllDown && {!_logged}): {
        ["battleye", "Liaison impossible : BattlEye actif", "Le module de liaison COMSPEC Link ne répond pas. BattlEye bloque les modules non autorisés : Athena ne pourra jamais se connecter ainsi.", "Quittez Arma, décochez BattlEye dans le lanceur Arma 3, puis relancez le jeu.", _red, ""] call _set;
    };
    case (_mode isEqualTo "off" && {!_logged}): {
        ["off", "Hors connexion : service Athena désactivé", "Le téléphone fonctionne en local : rien n'est envoyé à Athena ni au poste de commandement.", "Si votre unité utilise Athena, activez le service.", _grey, "service"] call _set;
    };
    case (_mode isEqualTo "" && {!_logged}): {
        ["unset", "Non connecté : service Athena pas encore choisi", "La liaison attend votre réponse : votre unité utilise-t-elle Athena ?", "Répondez avec CHOISIR.", _amber, "choose"] call _set;
    };
    case (_logged && {_link isEqualTo "degraded"}): {
        ["degraded", format ["Connecté en tant que %1, liaison instable", _who], [_detail, "Les échanges passent mal (réseau saturé, brouillage ou relais trop loin)."] select (_detail isEqualTo ""), "Elle se rétablit seule en général ; sinon RECONNECTER.", _amber, "reconnect"] call _set;
    };
    case (_logged && {!_ready}): {
        ["channel", format ["Connecté en tant que %1, canal du poste fermé", _who], "Votre compte est connecté mais vous ne recevez ni ordres ni messages du poste.", "Rouvrez le canal avec OUVRIR LE CANAL.", _amber, "channel"] call _set;
    };
    case (_logged): {
        ["connected", format ["Connecté en tant que %1", _who], format ["%1 · canal du poste ouvert%2", [_tenant, "Athena"] select (_tenant in ["", "—"]), _latText], "", _green, ""] call _set;
    };
    case (_ls isEqualTo "pending"): {
        ["code", format ["Connexion en cours : code %1 à autoriser", _lc], "Autorisez ce code sur le site Athena, connecté à votre compte. Le téléphone se connecte seul ensuite.", "", _amber, ""] call _set;
    };
    case (_ls in ["asking", "approved"] || {_syncing} || {_link isEqualTo "connecting"}): {
        ["syncing", format ["Connexion en cours : %1…", [_stepName, "synchronisation du profil"] select (_ls isEqualTo "approved")], "Patientez quelques secondes.", "", _amber, ""] call _set;
    };
    case (_refused): {
        ["error", "Erreur : Athena a refusé la connexion", format ["%1 (%2)", _refusal select 0, toUpper _ld], _refusal select 1, _red, "code"] call _set;
    };
    case (_notLinked): {
        ["unlinked", format ["Non connecté : %1 (non lié)", [_cs, name player] select (_cs isEqualTo "")], "Le serveur Athena répond, mais ce jeu n'est relié à aucun compte Athena.", "Reliez-le : OBTENIR MON CODE, puis autorisez-le sur le site.", _red, "code"] call _set;
    };
    case (_ls isEqualTo "error"): {
        ["error", "Erreur : Athena n'a pas donné de code", format ["Le serveur Athena n'a pas répondu (%1).", _ld], "Vérifiez la connexion internet du jeu, puis RÉESSAYER.", _red, "code"] call _set;
    };
    case (_why isNotEqualTo []): {
        ["error", format ["Erreur : %1", _why select 0], "", _why select 1, _red, "reconnect"] call _set;
    };
    default {
        ["disconnected", "Non connecté", "Ce jeu n'est pas encore connecté à votre compte Athena.", "Connectez-vous ci-dessous : un code à autoriser sur le site, ou e-mail et mot de passe.", _amber, "code"] call _set;
    };
};
_out set ["logged", _logged];
// Code d'erreur brut de la session (fn_athenaLink « watch » : session perdue en cours de partie).
_out set ["code", _code];
// Signature relue par fn_statusUpdate (même forme) : la page se redessine quand elle change.
_out set ["sig", [_sigState, _link, _ready]];

// Étapes de la liaison, dans l'ordre où elles se font.
private _steps = [];
_steps pushBack (if (_dllDown) then {
    ["Module de liaison", "ko", "ne répond pas", "BattlEye actif, ou COMSPECExtension_x64.dll absente ou bloquée par l'antivirus."]
} else {
    ["Module de liaison", "ok", ["actif", format ["COMSPEC Link %1", _dllVer]] select (_dllVer isNotEqualTo ""), ""]
});
_steps pushBack (switch (_mode) do {
    case "on": { ["Service Athena", "ok", "activé", ""] };
    case "off": { ["Service Athena", "off", "désactivé (ATAK local)", ""] };
    default { ["Service Athena", "warn", "pas encore choisi", ""] };
});
private _netKo = _code in ["NETWORK_ERROR", "TIMEOUT", "HTTP_503", "HTTP_502", "HTTP_504"] || {_ls isEqualTo "error" && {!_refused}};
_steps pushBack (switch (true) do {
    case (_mode isEqualTo "off" || {_dllDown}): { ["Serveur Athena", "off", "pas contacté", ""] };
    case (_netKo): { ["Serveur Athena", "ko", "injoignable", "Connexion internet du jeu coupée, pare-feu, ou site Athena en maintenance."] };
    case (_logged || {_refused} || {_link in ["linked", "degraded"]} || {_ls in ["pending", "approved"]} || {_notLinked} || {_why isNotEqualTo []}): { ["Serveur Athena", "ok", format ["athena-comspec.eu%1", _latText], ""] };
    case (_syncing || {_link isEqualTo "connecting"} || {_ls isEqualTo "asking"}): { ["Serveur Athena", "now", "contact en cours", ""] };
    default { ["Serveur Athena", "todo", "pas encore contacté", ""] };
});
_steps pushBack (switch (true) do {
    case (_logged): { ["Compte Athena", "ok", _who, [_tenant, ""] select (_tenant in ["", "—"])] };
    case (_mode isEqualTo "off" || {_dllDown}): { ["Compte Athena", "off", "non connecté", ""] };
    case (_ls isEqualTo "pending"): { ["Compte Athena", "now", format ["code %1 en attente", _lc], "À autoriser sur le site Athena."] };
    case (_ls isEqualTo "approved" || {_syncing}): { ["Compte Athena", "now", _stepName, ""] };
    case (_refused): { ["Compte Athena", "ko", "refusé par Athena", _refusal select 0] };
    case (_notLinked): { ["Compte Athena", "ko", format ["%1 (non lié)", [_cs, name player] select (_cs isEqualTo "")], "Ce jeu n'est relié à aucun compte."] };
    case (_why isNotEqualTo []): { ["Compte Athena", "ko", _why select 0, ""] };
    default { ["Compte Athena", "todo", "non connecté", ""] };
});
([player] call comspec_atak_native_fnc_phoneIdent) params [["_num", ""], ["_imei", ""]];
private _stored = (count (missionNamespace getVariable ["comspec_profile_phone", []])) > 0;
_steps pushBack ([
    ["Identité du téléphone", "todo", format ["%1 · IMEI %2", _num, _imei], "Provisoire : enregistrée sur Athena à la connexion."],
    ["Identité du téléphone", "ok", format ["%1 · IMEI %2", _num, _imei], ""]
] select (_logged && {_stored}));
_steps pushBack (switch (true) do {
    case (_logged && {_ready} && {_link isEqualTo "degraded"}): { ["Canal du poste", "warn", "instable", ""] };
    case (_logged && {_ready}): { ["Canal du poste", "ok", "ouvert", ""] };
    case (_logged): { ["Canal du poste", "warn", "fermé", "Ni ordres ni messages du poste tant qu'il est fermé."] };
    default { ["Canal du poste", ["todo", "off"] select (_mode isEqualTo "off" || {_dllDown}), "fermé", ""] };
});
_out set ["steps", _steps];
_out
