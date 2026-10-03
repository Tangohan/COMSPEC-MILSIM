/*
    Live cam partagé vers le web (Overwatch beta, espace « Live cam »).
    Appelé toutes les 2 s par un CBA_fnc_addPerFrameHandler (XEH_postInitClient) : la fonction ne fait
    que des tests légers et, quand c'est l'heure, prend une capture de la vue à la 1re personne du joueur,
    téléphone masqué le temps du cliché (comme fn_photoTake), puis l'envoie par la chaîne recon
    d'Overwatch (captureReconImage) en caméra casque, flux « chest:<UID> ».

    Pourquoi pas la vraie caméra poitrine ? Arma ne sait pas écrire un render-to-texture sur le disque :
    `screenshot` ne capture que l'écran. Il faudrait basculer tout l'écran du joueur sur une caméra
    (camCreate + cameraEffect ["Internal", "Back"]) pendant 2 ou 3 images, à chaque envoi :
    l'image du joueur saute toutes les N secondes (visée, conduite), les effets NV / thermique ne suivent
    pas, et une erreur entre cameraEffect et Terminate laisse le joueur bloqué sur la caméra.
    On garde donc la vue à la 1re personne, qui est aussi ce que voit le porteur.

    Réglages (profil du joueur) :
      COMSPEC_ATAK_LivecamShare      bool, défaut false (imposable par la communauté : clé « native_livecam_share »)
      COMSPEC_ATAK_LivecamShareEvery secondes entre deux images, défaut 15, borné 5 à 120
    Params : [forcer une image maintenant (bool)]
    Renvoie true si une capture a été lancée.
*/
params [["_force", false]];
if (!hasInterface) exitWith { false };

private _on = (["COMSPEC_ATAK_LivecamShare", false, "native_livecam_share"] call comspec_atak_native_fnc_pref) select 0;
if !(_on isEqualType true) then { _on = false; };
// Visible des autres clients (liste Live cam du téléphone, si on veut l'afficher plus tard).
if ((player getVariable ["COMSPEC_ATAK_LivecamSharing", false]) isNotEqualTo _on) then {
    player setVariable ["COMSPEC_ATAK_LivecamSharing", _on, true];
};
if (!_on) exitWith { false };

// Chaîne d'envoi : Overwatch connect chargé et session Athena ouverte.
if !([] call comspec_atak_native_fnc_bridge) exitWith { false };
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { false };
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith { false };

// Cadence
private _every = profileNamespace getVariable ["COMSPEC_ATAK_LivecamShareEvery", 15];
if !(_every isEqualType 0) then { _every = 15; };
_every = (_every max 5) min 120;
private _last = missionNamespace getVariable ["COMSPEC_ATAK_LivecamShareAt", -1e9];
if (!_force && {(diag_tickTime - _last) < _every}) exitWith { false };

// Rien d'utile à montrer, ou on gênerait le joueur : on attend le prochain passage.
if (!alive player) exitWith { false };
if (player getVariable ["ACE_isUnconscious", false]) exitWith { false };
if (([] call comspec_atak_native_fnc_canUse) isNotEqualTo "") exitWith { false };
// Débit simulé : pas de flux sans réseau, ni pendant un redémarrage du téléphone.
if ((([] call comspec_atak_native_fnc_linkQuality) get "bars") isEqualTo 0) exitWith { false };
if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") isEqualTo "OFF") exitWith { false };
if !(cameraView in ["INTERNAL", "GUNNER"]) exitWith { false };
if (!isNull curatorCamera) exitWith { false };
// Drone télécommandé ou caméra d'un autre (BCE, cTab) à l'écran : ce n'est pas la vue du porteur,
// et captureReconImage renommerait le flux au nom de l'hôte de cette caméra.
if (cameraOn isNotEqualTo (vehicle player)) exitWith { false };
private _overlay = false;
if (!isNil "comspec_overwatch_connect_fnc_getActiveCaptureCam") then {
    private _cap = [] call comspec_overwatch_connect_fnc_getActiveCaptureCam;
    _overlay = (_cap isEqualType []) && {!isNull (_cap param [0, objNull])};
};
if (_overlay) exitWith { false };
if (visibleMap) exitWith { false };
if (!isNull (findDisplay 49)) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _phone = [] call comspec_atak_native_fnc_display;
// Téléphone en main : c'est un dialogue, on ne le fait pas clignoter pendant qu'il sert.
if (!isNull _phone && {_s getOrDefault ["interactive", false]}) exitWith { false };
if (dialog) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]) exitWith { false };
// Horodaté plutôt que booléen : si le cliché plante, le partage repart au bout de 10 s.
private _busyAt = missionNamespace getVariable ["COMSPEC_ATAK_LivecamShareBusy", -1e9];
if (_busyAt isEqualType 0 && {(diag_tickTime - _busyAt) < 10}) exitWith { false };
private _suppress = missionNamespace getVariable ["COMSPEC_SuppressFeedSnapshotUntil", 0];
if (_suppress isEqualType 0 && {diag_tickTime < _suppress}) exitWith { false };
private _failUntil = missionNamespace getVariable ["COMSPEC_FeedSnapFailUntil", 0];
if (_failUntil isEqualType 0 && {diag_tickTime < _failUntil}) exitWith { false };
// Une photo du joueur vient de partir : captureReconImage réutiliserait son PNG (< 2,8 s).
private _shotAt = missionNamespace getVariable ["COMSPEC_LastArmaShotAt", -1e9];
if (_shotAt isEqualType 0 && {(diag_tickTime - _shotAt) < 3}) exitWith { false };

missionNamespace setVariable ["COMSPEC_ATAK_LivecamShareAt", diag_tickTime];
missionNamespace setVariable ["COMSPEC_ATAK_LivecamShareBusy", diag_tickTime];

private _uid = getPlayerUID player;
if (_uid isEqualTo "") then { _uid = netId player; };
private _feedId = format ["chest:%1", _uid];
private _caption = format ["Live cam · %1 · %2", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef];

[_caption, _feedId] spawn {
    params ["_caption", "_feedId"];
    disableSerialization;
    private _d = [] call comspec_atak_native_fnc_display;
    private _hidden = [];
    if (!isNull _d) then { { if (ctrlShown _x) then { _x ctrlShow false; _hidden pushBack _x; }; } forEach (allControls _d); };
    // Laisse une image sans téléphone avant le cliché (screenshot prend l'écran de la fin d'image).
    if ((count _hidden) > 0) then { uiSleep 0.15; };
    // alignDevicePov = false : pas de bascule de caméra ; jpegRetryOnce = false : pas de bandeau à chaque image.
    private _ok = false;
    if (cameraView in ["INTERNAL", "GUNNER"] && {!visibleMap}) then {
        _ok = ["", _caption, "HELMET", _feedId, false, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
    };
    if ((count _hidden) > 0) then { uiSleep 0.1; };
    { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;

    // Le dossier Screenshots d'Arma a un plafond : on retire l'avant-dernière image envoyée
    // (déjà recopiée dans « Arma 3 - COMSPEC\Captures » et partie depuis au moins un intervalle).
    if (_ok) then {
        private _png = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
        private _old = missionNamespace getVariable ["COMSPEC_ATAK_LivecamSharePngs", []];
        if !(_old isEqualType []) then { _old = []; };
        if (_png isEqualType "" && {_png isNotEqualTo ""} && {!(_png in _old)}) then { _old pushBack _png; };
        while { (count _old) > 2 } do {
            private _drop = _old deleteAt 0;
            "COMSPECExtension" callExtension ["DeleteLocalFile", [_drop]];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_LivecamSharePngs", _old];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamShareBusy", -1e9];
};
true
