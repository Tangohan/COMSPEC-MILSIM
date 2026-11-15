/*
    Photo du papier présenté lors d'un contrôle d'identité, jointe au contrôle sur Athena (COMSPEC Link 2.0.127).
    Toujours un geste du joueur : bouton « PHOTO DU PAPIER » de la visionneuse du document. La capture d'écran du jeu
    (commande screenshot, visionneuse ouverte) est écrite dans le dossier Screenshots du profil ; la DLL la recadre sur le
    papier (cadre de la visionneuse, en dix-millièmes de l'écran), la compresse en JPEG (1,5 Mo au plus) et l'envoie par le
    canal des photos du mod (UploadIdCheckPhoto → POST /api/sse/identity/checks/image), avec la clé du contrôle.
    Le site ne rattache la photo qu'à un contrôle déjà reçu : prise avant « Transmettre », elle part avec le contrôle
    (fn_idAthenaSubmit). L'état est porté par la fiche (fn_idHistory) : paperShot (fichier), paperCrop, paperClock,
    paperState ("taken" | "sending" | "sent" | "failed"), paperWhy (motif de l'échec).
    Rien n'est affiché par l'interface d'Arma : ligne d'état de la visionneuse et ligne « Photo du papier » de l'app
    Contrôle ID (avec « Renvoyer la photo » après un échec).
    ["has"]            -> la DLL sait envoyer la photo (commande annoncée par GetAtakFeatures)
    ["can"]            -> [possible, motif (vide si possible), texte court du bouton (vide : « PHOTO DU PAPIER »)]
    ["shoot"]          visionneuse ouverte : capture du papier affiché, puis envoi (ou attente du contrôle)
    ["send", fiche]    envoi (ou renvoi) de la capture gardée sur la fiche
    ["label", fiche]   -> [texte de l'état (traduit), couleur] ; ["", ""] sans photo
    ["refresh", fiche] ligne d'état de la visionneuse et app Contrôle ID redessinées
*/
params [["_op", "shoot", [""]], ["_rec", createHashMap, [createHashMap]]];
if (!hasInterface) exitWith { false };
private _tr = { _this call comspec_sse_fnc_tr };

if (_op isEqualTo "has") exitWith {
    if (!isNil "comspec_overwatch_connect_fnc_uplinkOff" && {[] call comspec_overwatch_connect_fnc_uplinkOff}) exitWith { false };
    private _have = missionNamespace getVariable "comspec_sse_idPaperDll";
    if (!isNil "_have") exitWith { _have };
    private _caps = "COMSPECExtension" callExtension ["GetAtakFeatures", []];
    if (_caps isEqualType []) then { _caps = _caps param [0, ""]; };
    if !(_caps isEqualType "") then { _caps = str _caps; };
    _have = (_caps find "UploadIdCheckPhoto") >= 0;
    // Sans réponse (DLL pas encore chargée) : rien n'est gardé, la question sera reposée.
    if (_caps isNotEqualTo "") then { missionNamespace setVariable ["comspec_sse_idPaperDll", _have]; };
    _have
};

if (_op isEqualTo "label") exitWith {
    private _why = switch (_rec getOrDefault ["paperWhy", ""]) do {
        case "no_dll": { "COMSPEC Link 2.0.127 requis" };
        case "screenshot_rejected": { "capture refusée par le jeu (qualité HDR sur Moyen au moins)" };
        case "not_found": { "contrôle pas encore reçu par Athena" };
        case "file_not_found";
        case "file_not_ready";
        case "invalid_type";
        case "invalid_file": { "capture introuvable, reprenez la photo" };
        case "too_large": { "image trop lourde" };
        case "rate_limited";
        case "queue_full": { "trop d'envois, réessayez dans un moment" };
        case "unauthorized": { "accès refusé par Athena" };
        case "refused": { "image refusée par Athena" };
        case "not_connected";
        case "network";
        case "unavailable";
        case "timeout": { "pas de liaison Athena" };
        default { "erreur d'envoi" };
    };
    switch (_rec getOrDefault ["paperState", ""]) do {
        case "taken": { ["Photo du papier prise : elle partira avec le contrôle (Transmettre)." call _tr, "#f2c94c"] };
        case "sending": { ["Photo du papier : envoi en cours." call _tr, "#f2c94c"] };
        case "sent": { ["Photo du papier envoyée à Athena." call _tr, "#cfe8cf"] };
        case "failed": { [format ["Photo du papier non envoyée : %1." call _tr, _why call _tr], "#ff8a80"] };
        default { ["", ""] };
    }
};

if (_op isEqualTo "refresh") exitWith {
    (["label", _rec] call comspec_sse_fnc_idPaperPhoto) params ["_text", "_color"];
    private _st = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
    if (!isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull]) && {(count _st) > 0}
        && {((_st getOrDefault ["doc", createHashMap]) getOrDefault ["id", ""]) isEqualTo (_rec getOrDefault ["docId", ""])}) then {
        ["status", _text, _color] call comspec_sse_fnc_idViewerOnLoad;
    };
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then {
        ["IDCHECK"] call comspec_atak_native_fnc_pageRender;
    };
    true
};

if (_op isEqualTo "send") exitWith {
    if ((_rec getOrDefault ["paperShot", ""]) isEqualTo "") exitWith { false };
    if ((_rec getOrDefault ["paperState", ""]) isEqualTo "sending") exitWith { false };
    if !(["has"] call comspec_sse_fnc_idPaperPhoto) exitWith {
        _rec set ["paperState", "failed"];
        _rec set ["paperWhy", "no_dll"];
        ["refresh", _rec] call comspec_sse_fnc_idPaperPhoto;
        false
    };
    _rec set ["paperState", "sending"];
    _rec set ["paperWhy", ""];
    ["refresh", _rec] call comspec_sse_fnc_idPaperPhoto;
    [_rec] spawn {
        params ["_rec"];
        private _key = _rec getOrDefault ["idem", ""];
        private _crop = (_rec getOrDefault ["paperCrop", [0, 0, 0, 0]]) apply { str _x };
        private _raw = ["UploadIdCheckPhoto", [_key, _rec getOrDefault ["paperShot", ""]] + _crop] call comspec_sse_fnc_extensionCall;
        private _state = "failed";
        private _why = "timeout";
        if ((_raw select [0, 3]) isEqualTo "OK|") then {
            // Envoi en tâche de fond dans la DLL : état relu toutes les 2 s, 90 s au plus.
            private _t0 = diag_tickTime;
            while { (diag_tickTime - _t0) < 90 } do {
                uiSleep 2;
                private _s = (["IdCheckPhotoStatus", [_key]] call comspec_sse_fnc_extensionCall) splitString "|";
                if ((_s param [1, ""]) isEqualTo "sent") exitWith { _state = "sent"; _why = ""; };
                if ((_s param [1, ""]) isEqualTo "failed") exitWith { _why = _s param [2, "error"]; };
            };
        } else {
            _why = (_raw splitString "|") param [1, "not_connected"];
        };
        _rec set ["paperState", _state];
        _rec set ["paperWhy", _why];
        diag_log text format ["[COMSPEC SSE] Identité : photo du papier du contrôle %1 : %2%3.", _rec getOrDefault ["ref", ""], _state, ["", format [" (%1)", _why]] select (_why isNotEqualTo "")];
        [{ ["refresh", _this] call comspec_sse_fnc_idPaperPhoto; }, _rec] call CBA_fnc_execNextFrame;
    };
    true
};

private _st = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
private _viewer = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
private _recOf = {
    // Fiche du contrôle en cours si elle porte sur le document affiché, sinon celle de l'historique qui le porte.
    private _id = (_st getOrDefault ["doc", createHashMap]) getOrDefault ["id", ""];
    private _cur = ["current"] call comspec_sse_fnc_idHistory;
    if ((count _cur) > 0 && {(_cur getOrDefault ["docId", ""]) isEqualTo _id}) exitWith { _cur };
    private _i = (["list"] call comspec_sse_fnc_idHistory) findIf { (_x getOrDefault ["docId", ""]) isEqualTo _id };
    if (_i >= 0) exitWith { ["select", [_i]] call comspec_sse_fnc_idHistory };
    createHashMap
};

if (_op isEqualTo "can") exitWith {
    if (isNull _viewer || {(count _st) isEqualTo 0}) exitWith { [false, "", ""] };
    if (_st getOrDefault ["own", false]) exitWith { [false, "Votre propre document : rien à photographier.", ""] };
    if !(["has"] call comspec_sse_fnc_idPaperPhoto) exitWith { [false, "Photo du papier : COMSPEC Link 2.0.127 et une liaison Athena sont nécessaires.", "PHOTO INDISPONIBLE"] };
    private _cur = call _recOf;
    switch (_cur getOrDefault ["paperState", ""]) do {
        case "sending": { [false, "Photo du papier : envoi en cours.", "ENVOI EN COURS"] };
        case "": { [true, "", ""] };
        default { [true, "", "REFAIRE LA PHOTO"] };
    }
};

// --- « shoot » : capture du papier affiché par la visionneuse ------------------------------------------------------
if (isNull _viewer || {(count _st) isEqualTo 0}) exitWith { false };
private _status = { ["status", _this select 0, _this select 1] call comspec_sse_fnc_idViewerOnLoad; };
(["can"] call comspec_sse_fnc_idPaperPhoto) params ["_ok", "_whyNot"];
if (!_ok) exitWith { [_whyNot, "#f2c94c"] call _status; false };

// Fiche du contrôle : celle en cours, sinon une nouvelle sur ce document (papier tendu et ouvert tout seul).
_rec = call _recOf;
if ((count _rec) isEqualTo 0) then {
    private _subj = _st getOrDefault ["subj", objNull];
    if !(_subj isEqualType objNull) then { _subj = objNull; };
    _rec = ["start", [_st get "doc", _subj, "", createHashMap]] call comspec_sse_fnc_idHistory;
};
// Cadre du papier à l'écran (geo de la visionneuse, unités de l'interface) en dix-millièmes de l'écran.
(_st getOrDefault ["geo", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]]) params ["_cx", "_cy", "_cw", "_ch"];
private _crop = [(_cx - safeZoneX) / safeZoneW, (_cy - safeZoneY) / safeZoneH, _cw / safeZoneW, _ch / safeZoneH] apply { round (((_x max 0) min 1) * 10000) };
private _uid = getPlayerUID player;
private _file = format ["COMSPEC_SSE_Paper_%1_%2.png", _uid select [((count _uid) - 6) max 0], round (diag_tickTime * 10)];
private _ret = screenshot _file;
_rec set ["paperClock", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
// Capture refusée par le jeu (qualité HDR basse, dossier Screenshots plein) : aucune image, rien à envoyer.
if (_ret isEqualType true && {!_ret}) exitWith {
    _rec set ["paperShot", ""];
    _rec set ["paperState", "failed"];
    _rec set ["paperWhy", "screenshot_rejected"];
    ["refresh", _rec] call comspec_sse_fnc_idPaperPhoto;
    false
};
_rec set ["paperShot", _file];
_rec set ["paperCrop", _crop];
_rec set ["paperWhy", ""];
diag_log text format ["[COMSPEC SSE] Identité : photo du papier %1 prise à %2 par %3.", (_rec get "doc") getOrDefault ["id", ""], _rec get "paperClock", name player];
// Contrôle déjà sur Athena (référence CTRL-…) : la photo part tout de suite ; sinon elle attend « Transmettre ».
if (((_rec getOrDefault ["ref", ""]) select [0, 5]) isEqualTo "CTRL-") then {
    ["send", _rec] call comspec_sse_fnc_idPaperPhoto;
} else {
    _rec set ["paperState", "taken"];
    ["refresh", _rec] call comspec_sse_fnc_idPaperPhoto;
};
true
