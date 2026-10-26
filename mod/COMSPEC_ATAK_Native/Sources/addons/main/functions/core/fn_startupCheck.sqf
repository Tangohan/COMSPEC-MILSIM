/*
    Check de démarrage et de liaison : rapport court de chaque étape du lancement (mod, téléphone, DLL, Athena, mods,
    FPS, erreurs) et de la liaison du joueur à Athena, envoyé à Athena qui le poste dans le salon Discord de debug.
    On y voit qui a un mod ou une DLL cassés, et si la liaison en jeu a bien attribué au joueur son numéro, son IMEI et
    son terminal, ou quelle erreur de connexion l'en a empêché.
      [] call comspec_atak_native_fnc_startupCheck          depuis XEH_postInitClient, à chaque mission
      ["reset"] call comspec_atak_native_fnc_startupCheck   Dépannage liaison > « Renvoyer le check de démarrage »
    Un seul rapport, au premier lancement, puis plus jamais : le profil du joueur garde « déjà envoyé »
    (COMSPEC_ATAK_StartupCheckOnce) d'une mission et d'un lancement du jeu à l'autre. Seul « reset » l'efface et
    refait un envoi. Envoi quand le joueur se lie en jeu (COMSPEC_AthenaReady passe à vrai, identité du téléphone
    reçue d'Athena), ou, faute de liaison 2 min après le contrôle de l'installation, avec l'erreur de connexion. Un
    envoi qui échoue (réseau, Athena injoignable) est retenté à la mission suivante. L'identifiant de session est
    gardé dans le profil jusqu'au prochain « reset » : Athena écarte un second rapport de la même session (scope=once).
    Seulement avec le service Athéna sur « Oui » et le réglage CBA « Envoyer le check de démarrage » coché.
    Commande DLL StartupCheck (COMSPEC Link 2.0.82+), POST /api/atak/startup-check : pseudo, indicatif, versions, carte,
    déclencheur (trigger=link | link_failed) et résultat des étapes. Pas d'UID Steam (empreinte seulement), pas
    d'adresse IP, pas de jeton.
    Résultat : missionNamespace « COMSPEC_ATAK_StartupCheck » = [[étape, statut, détail], ...] (statut ok, warn, fail, off),
    « COMSPEC_ATAK_StartupCheckSent » = réponse d'Athena (app Debug, onglet DLL).
*/
if (!hasInterface) exitWith {};
params [["_mode", "", [""]]];
if (!canSuspend) exitWith { [_mode] spawn comspec_atak_native_fnc_startupCheck; };
// Remise à zéro demandée par le joueur : nouvelle session, nouvel envoi (même si un envoi de cette mission attend).
if (_mode isEqualTo "reset") then {
    profileNamespace setVariable ["COMSPEC_ATAK_StartupCheckOnce", nil];
    profileNamespace setVariable ["COMSPEC_ATAK_StartupCheckId", nil];
    [] call comspec_atak_native_fnc_saveProfile;
    missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckDone", false];
    missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckSent", "en cours (remis à zéro)"];
    ["INFO", "STARTUP", "Check de démarrage remis à zéro par le joueur : nouvel envoi"] call comspec_atak_native_fnc_log;
};
if (missionNamespace getVariable ["COMSPEC_ATAK_StartupCheckDone", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckDone", true];
// Déjà envoyé (premier lancement ou dernier « reset ») : on garde la réponse d'Athena pour l'app Debug, sans envoi.
private _once = profileNamespace getVariable ["COMSPEC_ATAK_StartupCheckOnce", []];
if (_once isEqualType [] && {_once isNotEqualTo []}) exitWith {
    missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckSent", format ["%1 (déjà envoyé le %2 ; Dépannage liaison > Renvoyer le check de démarrage pour un nouvel envoi)", _once param [0, ""], _once param [1, "?"]]];
};

// Joueur en jeu, puis le contrôle local (15 s) et la comparaison avec Athena (30 s) de fn_integrityCheck.
waitUntil { sleep 1; !isNull player && {time > 0} };
sleep 45;
// Service Athéna pas encore choisi : on attend le choix (10 min au plus). « Non » : aucun envoi.
private _t0 = diag_tickTime;
waitUntil { sleep 5; ([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "" || {diag_tickTime - _t0 > 600} };
if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith {
    ["INFO", "STARTUP", "Check de démarrage non envoyé : service Athéna désactivé"] call comspec_atak_native_fnc_log;
};
if !(missionNamespace getVariable ["comspec_atak_native_startup_report", true]) exitWith {
    ["INFO", "STARTUP", "Check de démarrage non envoyé : refusé dans les réglages CBA"] call comspec_atak_native_fnc_log;
};
_t0 = diag_tickTime;
waitUntil { sleep 2; ((missionNamespace getVariable ["COMSPEC_ATAK_LatestRemote", ["", "", -1]]) select 2) >= 0 || {diag_tickTime - _t0 > 60} };

private _ready = { (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) isEqualTo true };
private _str = { params ["_k", ["_d", ""]]; private _v = missionNamespace getVariable [_k, _d]; [str _v, _v] select (_v isEqualType "") };
// Versions et extension lues par _build, reprises par _send.
private _bridge = false;
private _ext = "";
private _code = "";
private _cfg = "";
private _dll = "";
// Étapes du rapport, relues à chaque envoi (une liaison faite plus tard change compte, téléphone et terminal).
private _build = {
    _bridge = [] call comspec_atak_native_fnc_bridge;
    _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select _bridge;
    private _issues = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
    private _issueText = { params ["_list"]; (_list apply { _x select 2 }) joinString " " };
    private _issueStatus = { params ["_list"]; ["warn", "fail"] select ((_list findIf { (_x select 1) isEqualTo "ERROR" }) >= 0) };
    private _patch = { params ["_p"]; getText (configFile >> "CfgPatches" >> _p >> "versionStr") };
    private _has = { params ["_p"]; isClass (configFile >> "CfgPatches" >> _p) };
    private _steps = [];

    // 1. Mod : un seul main.pbo, code et config de la même version.
    _code = missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", ""];
    _cfg = ["comspec_atak_native_main"] call _patch;
    private _bad = _issues select { (_x select 0) isEqualTo "code" || {((_x select 0) find "dup_") isEqualTo 0} };
    _steps pushBack ["mod", ["ok", "fail"] select (_bad isNotEqualTo []), [format ["version %1", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", _code]], [_bad] call _issueText] select (_bad isNotEqualTo [])];

    // 2. Téléphone : état initialisé, téléphone porté ou non (simple information).
    private _stateOk = !isNil { uiNamespace getVariable "COMSPEC_ATAK_State" };
    _steps pushBack ["atak", ["fail", "ok"] select _stateOk, [
        "téléphone non initialisé",
        ["initialisé, pas de téléphone sur le joueur", "initialisé, téléphone porté"] select ([player] call comspec_atak_native_fnc_hasDevice)
    ] select _stateOk];

    // 3. DLL : chargée et de la version attendue par ce main.pbo.
    private _dllRaw = _ext callExtension ["GetExtensionVersion", []];
    if (_dllRaw isEqualType []) then { _dllRaw = _dllRaw param [0, ""]; };
    if !(_dllRaw isEqualType "") then { _dllRaw = str _dllRaw; };
    _dll = if ((_dllRaw select [0, 3]) isEqualTo "OK|") then { private _w = _dllRaw splitString " |"; _w param [(count _w) - 1, ""] } else { "" };
    private _dllIssues = _issues select { (_x select 0) isEqualTo "dll" };
    _steps pushBack ["dll",
        [[_dllIssues] call _issueStatus, "ok"] select (_dllIssues isEqualTo []),
        [[_dllIssues] call _issueText, format ["%1 chargée%2", _dll, ["", " (DLL native)"] select !_bridge]] select (_dllIssues isEqualTo [])
    ];

    // 4. Extension : répond, et sans figer le jeu (appels « Async »).
    private _noAsync = _ext in (missionNamespace getVariable ["COMSPEC_ATAK_ExtNoAsync", []]);
    _steps pushBack ["ext", ["fail", ["ok", "warn"] select _noAsync] select (_dll isNotEqualTo ""), [
        "la DLL ne répond pas",
        ["répond", "répond, mais trop ancienne pour les appels sans blocage"] select _noAsync
    ] select (_dll isNotEqualTo "")];

    // 5. Connexion Athena (versions publiées lues par fn_integrityCheck). Le compte lié est l'étape 12 (liaison en jeu).
    private _remote = missionNamespace getVariable ["COMSPEC_ATAK_LatestRemote", ["", "", -1]];
    _steps pushBack ["athena", ["fail", "ok"] select ((_remote select 2) >= 0), ["portail injoignable", "portail joignable"] select ((_remote select 2) >= 0)];

    // 7. Mise à jour disponible (mod ou DLL plus anciens que la dernière version publiée).
    private _upd = _issues select { ((_x select 0) find "update_") isEqualTo 0 };
    _steps pushBack ["update", ["warn", "ok"] select (_upd isEqualTo []), [[_upd] call _issueText, ["à jour", "non vérifié"] select ((_remote select 2) < 0)] select (_upd isEqualTo [])];

    // 8. Dépendances et 9. mods COMSPEC détectés.
    private _deps = [format ["CBA %1", ["cba_main"] call _patch]];
    if (["ace_main"] call _has) then { _deps pushBack format ["ACE %1", ["ace_main"] call _patch]; };
    if (["acre_main"] call _has) then { _deps pushBack format ["ACRE %1", ["acre_main"] call _patch]; };
    if (["tfar_core"] call _has || {["task_force_radio"] call _has}) then { _deps pushBack "TFAR"; };
    _steps pushBack ["deps", ["fail", "ok"] select (["cba_main"] call _has), _deps joinString " · "];
    private _mods = [];
    if (_bridge) then { _mods pushBack "Overwatch connect"; };
    if (["comspec_sse_main"] call _has) then { _mods pushBack format ["SSE %1", ["comspec_sse_main"] call _patch]; };
    if (["comspec_atak_native_towers"] call _has) then { _mods pushBack "pylônes relais"; };
    _steps pushBack ["mods", "ok", [_mods joinString " · ", "ATAK seul"] select (_mods isEqualTo [])];

    // 10. FPS et ping (multijoueur).
    private _fps = round diag_fps;
    private _ping = if (isMultiplayer) then { ((getUserInfo getPlayerID player) param [9, []]) param [0, -1] } else { -1 };
    _steps pushBack ["perf", ["warn", "ok"] select (_fps >= 20), format ["%1 FPS%2", _fps, ["", format [" · ping %1 ms", round _ping]] select (_ping >= 0)]];

    // 11. Erreurs du journal depuis le chargement (les trois dernières).
    private _errs = (missionNamespace getVariable ["COMSPEC_ATAK_Log", []]) select { (_x select 1) isEqualTo "ERROR" };
    private _last = (_errs select [((count _errs) - 3) max 0, 3]) apply { (_x select 3) select [0, 120] };
    _steps pushBack ["errors", ["warn", "ok"] select (_errs isEqualTo []), ["aucune", format ["%1 : %2", count _errs, _last joinString " / "]] select (_errs isNotEqualTo [])];

    // 12. Liaison en jeu : compte lié à Athena, sinon l'erreur de connexion vue par COMSPEC Overwatch connect.
    private _err = ["comspec_overwatch_auth_error"] call _str;
    if (_err isEqualTo "") then { _err = ["COMSPEC_LinkDetail"] call _str; };
    private _tenant = ["comspec_tenant_name"] call _str;
    _steps pushBack ["link", ["fail", "ok"] select (call _ready), [
        format ["non lié%1", [" : " + _err, " (aucune erreur remontée, connexion pas encore faite)"] select (_err isEqualTo "")],
        format ["lié%1", ["", " · " + _tenant] select (_tenant isNotEqualTo "")]
    ] select (call _ready)];

    // 13. Téléphone attribué : numéro et IMEI gardés dans Athena (sinon numéro provisoire calculé en jeu).
    ([player] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei"];
    private _db = missionNamespace getVariable ["comspec_profile_phone", []];
    if !(_db isEqualType []) then { _db = []; };
    private _given = (_db param [0, ""]) isNotEqualTo "";
    _steps pushBack ["phone", ["warn", "ok"] select _given, format ["n° %1 · IMEI %2 · %3", _num, _imei,
        ["provisoire, pas encore attribué par Athena", format ["attribué par Athena (%1)", _db param [3, "FR"]]] select _given]];

    // 14. Terminal : objet téléphone porté (catalogue des appareils) et modèle choisi.
    private _set = missionNamespace getVariable ["COMSPEC_ATAK_DeviceCatalogSet", createHashMap];
    private _item = ((assignedItems player) + (items player)) param [((assignedItems player) + (items player)) findIf { (toLower _x) in _set }, ""];
    private _model = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"]);
    private _itemName = getText (configFile >> "CfgWeapons" >> _item >> "displayName");
    if (_itemName isEqualTo "") then { _itemName = _item; };
    private _hasDev = [player, true] call comspec_atak_native_fnc_hasDevice;
    _steps pushBack ["terminal", ["warn", "ok"] select _hasDev, [
        format ["aucun terminal porté (modèle %1)", _model],
        format ["%1 · modèle %2", [_itemName, "accès libre (objet non exigé)"] select (_item isEqualTo ""), _model]
    ] select _hasDev];
    _steps
};
// Envoi d'un rapport : _trigger = "link" (liaison faite) ou "link_failed" (pas de liaison, erreur de connexion).
private _send = {
    params ["_trigger"];
    private _steps = call _build;
    missionNamespace setVariable ["COMSPEC_ATAK_StartupCheck", _steps];
    private _v = productVersion select 2;
    private _uid = getPlayerUID player;
    private _key = hashValue ([_uid, profileName] select (_uid isEqualTo ""));
    // Session : tirée au premier envoi, gardée dans le profil jusqu'au prochain « reset ».
    private _launch = profileNamespace getVariable ["COMSPEC_ATAK_StartupCheckId", ""];
    if !(_launch isEqualType "" && {_launch isNotEqualTo ""}) then {
        _launch = hashValue format ["%1|%2|%3", _key, systemTimeUTC, diag_tickTime];
        profileNamespace setVariable ["COMSPEC_ATAK_StartupCheckId", _launch];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    private _args = [
        "https://athena-comspec.eu/public",
        "atak=" + _code, "config=" + _cfg, "link=" + _dll, "link_expected=" + (missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""]),
        format ["arma=%1.%2", floor (_v / 100), _v mod 100],
        "player=" + profileName, "callsign=" + groupId group player,
        "player_key=" + _key, "session=" + _launch, "scope=once",
        "world=" + worldName, "mission=" + briefingName, "mp=" + str isMultiplayer, "trigger=" + _trigger
    ];
    { _args pushBack format ["step_%1=%2|%3", _x select 0, _x select 1, _x select 2]; } forEach _steps;

    private _sum = format ["%1 erreur(s), %2 avertissement(s)", { (_x select 1) isEqualTo "fail" } count _steps, { (_x select 1) isEqualTo "warn" } count _steps];
    // Trois essais (réseau coupé, Athena qui redémarre) ; une DLL sans StartupCheck répond vide.
    private _r = "";
    for "_try" from 1 to 3 do {
        _r = ([_ext, "StartupCheck", _args] call comspec_atak_native_fnc_extAwait) param [0, ""];
        if !(_r isEqualType "") then { _r = str _r; };
        if ((_r select [0, 3]) isEqualTo "OK|" || {_r isEqualTo ""} || {_r isEqualTo "ERR|rate_limited"}) exitWith {};
        sleep (30 * _try);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckSent", [_r, "DLL trop ancienne (commande inconnue)"] select (_r isEqualTo "")];
    // Reçu par Athena (ou DLL sans la commande) : plus jamais d'envoi, sauf « reset » dans Dépannage liaison.
    if ((_r select [0, 3]) isEqualTo "OK|" || {_r isEqualTo ""}) then {
        systemTimeUTC params ["_y", "_mo", "_d", "_h", "_mi"];
        profileNamespace setVariable ["COMSPEC_ATAK_StartupCheckOnce", [
            missionNamespace getVariable ["COMSPEC_ATAK_StartupCheckSent", ""],
            format ["%1-%2-%3 %4:%5 UTC", _y, [_mo, 2] call CBA_fnc_formatNumber, [_d, 2] call CBA_fnc_formatNumber, [_h, 2] call CBA_fnc_formatNumber, [_mi, 2] call CBA_fnc_formatNumber]
        ]];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    ["INFO", "STARTUP", format ["Check de %1 (%2) : %3", ["liaison", "démarrage sans liaison"] select (_trigger isEqualTo "link_failed"), _sum, switch (true) do {
        case (_r isEqualTo "OK|posted"): { "posté dans le salon Discord de debug" };
        case (_r isEqualTo "OK|no_channel"): { "reçu par Athena, aucun salon Discord configuré" };
        case (_r isEqualTo "OK|duplicate"); case (_r isEqualTo "OK|throttled"): { "déjà posté récemment" };
        case (_r isEqualTo ""): { "non envoyé, DLL trop ancienne" };
        default { "échec de l'envoi : " + _r };
    }]] call comspec_atak_native_fnc_log;
    true
};

// Liaison en jeu : 2 min au plus après le contrôle de l'installation pour que la connexion se fasse ; puis l'identité
// du téléphone, qui arrive avec le profil Athena (20 s au plus).
_t0 = diag_tickTime;
waitUntil { sleep 2; (call _ready) || {diag_tickTime - _t0 > 120} };
private _linked = call _ready;
if (_linked) then {
    private _t1 = diag_tickTime;
    waitUntil { sleep 2; ((missionNamespace getVariable ["comspec_profile_phone", []]) param [0, ""]) isNotEqualTo "" || {diag_tickTime - _t1 > 20} };
};
// Un « reset » ou une autre mission a pu envoyer pendant l'attente (rare) : pas de second rapport.
if ((profileNamespace getVariable ["COMSPEC_ATAK_StartupCheckOnce", []]) isNotEqualTo []) exitWith { true };
[["link_failed", "link"] select _linked] call _send;
true
