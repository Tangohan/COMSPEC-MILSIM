/*
    Check de démarrage : rapport court de chaque étape du lancement (mod, téléphone, DLL, Athena, mods, FPS, erreurs),
    envoyé à Athena qui le poste dans le salon Discord de debug. Les joueurs voient ainsi qui a un mod ou une DLL cassés.
      [] call comspec_atak_native_fnc_startupCheck       depuis XEH_postInitClient, une fois par mission
    Part après le contrôle de l'installation (fn_integrityCheck), seulement avec le service Athéna sur « Oui » et le
    réglage CBA « Envoyer le check de démarrage » coché. Commande DLL StartupCheck (COMSPEC Link 2.0.81+),
    POST /api/atak/startup-check : pseudo, indicatif, versions, carte et résultat des étapes. Pas d'UID Steam (empreinte
    seulement), pas d'adresse IP, pas de jeton.
    Résultat : missionNamespace « COMSPEC_ATAK_StartupCheck » = [[étape, statut, détail], ...] (statut ok, warn, fail, off),
    « COMSPEC_ATAK_StartupCheckSent » = réponse d'Athena (app Debug, onglet DLL).
*/
if (!hasInterface) exitWith {};
if (!canSuspend) exitWith { [] spawn comspec_atak_native_fnc_startupCheck; };
if (missionNamespace getVariable ["COMSPEC_ATAK_StartupCheckDone", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_StartupCheckDone", true];

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

private _bridge = [] call comspec_atak_native_fnc_bridge;
private _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select _bridge;
private _issues = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
private _issueText = { params ["_list"]; (_list apply { _x select 2 }) joinString " " };
private _issueStatus = { params ["_list"]; ["warn", "fail"] select ((_list findIf { (_x select 1) isEqualTo "ERROR" }) >= 0) };
private _patch = { params ["_p"]; getText (configFile >> "CfgPatches" >> _p >> "versionStr") };
private _has = { params ["_p"]; isClass (configFile >> "CfgPatches" >> _p) };
private _steps = [];

// 1. Mod : un seul main.pbo, code et config de la même version.
private _code = missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", ""];
private _cfg = ["comspec_atak_native_main"] call _patch;
private _bad = _issues select { (_x select 0) isEqualTo "code" || {((_x select 0) find "dup_") isEqualTo 0} };
_steps pushBack ["mod", ["ok", "fail"] select (_bad isNotEqualTo []), [format ["version %1", _code], [_bad] call _issueText] select (_bad isNotEqualTo [])];

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
private _dll = if ((_dllRaw select [0, 3]) isEqualTo "OK|") then { private _w = _dllRaw splitString " |"; _w param [(count _w) - 1, ""] } else { "" };
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

// 5. Connexion Athena (versions publiées lues par fn_integrityCheck) et 6. compte lié.
private _remote = missionNamespace getVariable ["COMSPEC_ATAK_LatestRemote", ["", "", -1]];
_steps pushBack ["athena", ["fail", "ok"] select ((_remote select 2) >= 0), ["portail injoignable", "portail joignable"] select ((_remote select 2) >= 0)];
private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
_steps pushBack ["account", ["warn", "ok"] select (_linked isEqualTo true), ["pas de compte Athena lié", "compte lié"] select (_linked isEqualTo true)];

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

missionNamespace setVariable ["COMSPEC_ATAK_StartupCheck", _steps];
private _v = productVersion select 2;
private _uid = getPlayerUID player;
private _key = hashValue ([_uid, profileName] select (_uid isEqualTo ""));
private _args = [
    "https://athena.ttrd.fr/public",
    "atak=" + _code, "config=" + _cfg, "link=" + _dll, "link_expected=" + (missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""]),
    format ["arma=%1.%2", floor (_v / 100), _v mod 100],
    "player=" + profileName, "callsign=" + groupId group player,
    "player_key=" + _key, "session=" + hashValue format ["%1|%2|%3", _key, missionName, systemTimeUTC],
    "world=" + worldName, "mission=" + briefingName, "mp=" + str isMultiplayer
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
["INFO", "STARTUP", format ["Check de démarrage (%1) : %2", _sum, switch (true) do {
    case (_r isEqualTo "OK|posted"): { "posté dans le salon Discord de debug" };
    case (_r isEqualTo "OK|no_channel"): { "reçu par Athena, aucun salon Discord configuré" };
    case (_r isEqualTo "OK|duplicate"); case (_r isEqualTo "OK|throttled"): { "déjà posté récemment" };
    case (_r isEqualTo ""): { "non envoyé, DLL trop ancienne" };
    default { "échec de l'envoi : " + _r };
}]] call comspec_atak_native_fnc_log;
true
