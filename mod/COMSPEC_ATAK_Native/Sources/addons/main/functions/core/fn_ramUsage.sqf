/*
    Mémoire vive simulée du téléphone (S7 : 4 Go ; réglage serveur comspec_atak_native_ram_total). Calculée à partir de ce que le téléphone fait vraiment :
    système Android, services COMSPEC (réseau, BFT, nombre d'unités suivies), app au premier plan,
    apps restées en arrière-plan (apps récentes, 6 au plus par défaut, réglage comspec_atak_native_ram_bg ; Android ferme les plus
    anciennes, et aussi quand la mémoire est pleine),
    appareil photo, live cam, flux drone, musique, guidage GPS. Téléphone rangé : les apps sont compressées.
    C'est une estimation du téléphone simulé, pas la mémoire du PC (le jeu ne la donne pas aux scripts). Le flux vidéo du
    drone ne compte que pendant qu'il est vraiment rendu (texture 512 ou 1024 px), plus après la libération de la caméra
    (page quittée depuis 5 s) ; avant le 19/10, 540 Mo restaient comptés tant qu'un drone était appairé.
    La valeur suit la cible en douceur (pas de saut d'une seconde à l'autre).
    Retourne un HashMap : used, total (Mo), pct (0-100), parts [[libellé, Mo]...], apps (nombre d'apps en mémoire).
    Dernière mesure : missionNamespace COMSPEC_ATAK_Ram.
*/
private _total = (missionNamespace getVariable ["comspec_atak_native_ram_total", 4096]) max 1024;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _open = !isNull ([] call comspec_atak_native_fnc_display);
private _page = _s getOrDefault ["activePage", "LAUNCHER"];
private _weight = {
    params ["_p"];
    switch (_p) do {
        case "MAP": { 380 + ((count allMapMarkers) * 0.4 min 160) };
        case "DRONE": { 200 };
        case "LIVECAM": { 300 };
        case "AAR": { 320 };
        case "RELIEF": { 290 };
        case "PHOTOS": { 260 };
        case "GALLERY": { 180 };
        case "EW"; case "DRONEDETECT": { 240 };
        case "BRIEFING": { 230 };
        case "ATHENA": { 210 };
        case "CHAT"; case "FRS"; case "RECO"; case "SALUTE"; case "SSE": { 170 };
        case "MUSIC": { 140 };
        case "LAUNCHER"; case "RECENTS"; case "NOTIFS": { 60 };
        default { 120 };
    }
};
private _parts = [["Android (système)", 1180]];
private _units = count ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
_parts pushBack ["Services COMSPEC (réseau, BFT)", 210 + ((_units * 3) min 140)];
private _k = [0.5, 1] select _open;
_parts pushBack [format ["App au premier plan (%1)", _page], ([_page] call _weight) * _k];
// Apps en arrière-plan : les plus récentes d'abord, sans doublon.
private _bg = [];
{ if !(_x in ["LAUNCHER", "RECENTS", "NOTIFS", _page] || {_x in _bg}) then { _bg pushBack _x; }; } forEach (+(_s getOrDefault ["history", []]) call { reverse _this; _this });
_bg = _bg select [0, round ((missionNamespace getVariable ["comspec_atak_native_ram_bg", 6]) max 0)];
if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then { _parts pushBack ["Appareil photo", 450]; };
if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull])) then { _parts pushBack ["Live cam (encodage vidéo)", 520]; };
if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) then {
    private _fleet = { !isNull _x } count (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []]);
    _parts pushBack ["Liaison drone", 40 + 20 * _fleet];
    // Flux vidéo : seulement pendant le rendu dans la texture (coupé hors de la page, en vue directe, caméra libérée).
    private _dc = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    if (!isNull _dc && {([_dc, "rendering", false] call comspec_atak_native_fnc_camVar)}) then {
        _parts pushBack ["Flux vidéo du drone", [90, 200] select ((([_dc, "q", 0] call comspec_atak_native_fnc_camVar) max 0) min 1)];
    };
    if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DronePrevCam", objNull])) then { _parts pushBack ["Aperçu du cliché drone", 60]; };
};
if (((missionNamespace getVariable ["COMSPEC_ATAK_Music", createHashMap]) getOrDefault ["kind", ""]) isNotEqualTo "" && {!((missionNamespace getVariable ["COMSPEC_ATAK_Music", createHashMap]) getOrDefault ["paused", false])}) then { _parts pushBack ["Lecture audio", 90]; };
if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap])) > 0) then { _parts pushBack ["Guidage GPS", 60]; };
// Mémoire pleine : Android ferme les apps d'arrière-plan les plus anciennes jusqu'à repasser sous 90 %.
private _fg = 0;
{ _fg = _fg + (_x select 1); } forEach _parts;
private _bgMo = 0;
private _kept = [];
private _full = false;
{
    private _mo = ([_x] call _weight) * 0.35 * ([0.6, 1] select _open);
    if (_fg + _bgMo + _mo > _total * 0.9) then { _full = true; };
    if (!_full) then { _kept pushBack _x; _bgMo = _bgMo + _mo; };
} forEach _bg;
_bg = _kept;
if ((count _bg) > 0) then { _parts pushBack [format ["%1 app(s) en arrière-plan", count _bg], _bgMo]; };
private _target = 0;
{ _target = _target + (_x select 1); } forEach _parts;
// Petites variations (ramasse-miettes, caches) : ± 25 Mo.
_target = (_target + 25 * sin (diag_tickTime * 23)) min (_total * 0.96);
private _prev = missionNamespace getVariable ["COMSPEC_ATAK_Ram", createHashMap];
private _used = if ((count _prev) > 0) then { (_prev get "used") * 0.55 + _target * 0.45 } else { _target };
private _out = createHashMapFromArray [
    ["used", round _used], ["total", _total], ["pct", round (_used / _total * 100)],
    ["parts", _parts apply { [_x select 0, round (_x select 1)] }], ["apps", 1 + count _bg]
];
missionNamespace setVariable ["COMSPEC_ATAK_Ram", _out];
_out
