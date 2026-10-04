/*
    Mémoire vive simulée du téléphone (S7 : 4 Go). Calculée à partir de ce que le téléphone fait vraiment :
    système Android, services COMSPEC (réseau, BFT, nombre d'unités suivies), app au premier plan,
    apps restées en arrière-plan (apps récentes, 6 au plus : Android ferme les plus anciennes),
    appareil photo, live cam, flux drone, musique, guidage GPS. Téléphone rangé : les apps sont compressées.
    La valeur suit la cible en douceur (pas de saut d'une seconde à l'autre).
    Retourne un HashMap : used, total (Mo), pct (0-100), parts [[libellé, Mo]...], apps (nombre d'apps en mémoire).
    Dernière mesure : missionNamespace COMSPEC_ATAK_Ram.
*/
private _total = 4096;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _open = !isNull ([] call comspec_atak_native_fnc_display);
private _page = _s getOrDefault ["activePage", "LAUNCHER"];
private _weight = {
    params ["_p"];
    switch (_p) do {
        case "MAP": { 380 + ((count allMapMarkers) * 0.4 min 160) };
        case "DRONE": { 360 };
        case "LIVECAM": { 300 };
        case "AAR": { 320 };
        case "RELIEF": { 290 };
        case "PHOTOS": { 260 };
        case "EW"; case "DRONEDETECT": { 240 };
        case "BRIEFING": { 230 };
        case "ATHENA": { 210 };
        case "CHAT"; case "FRS"; case "RECO"; case "SSE": { 170 };
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
_bg = _bg select [0, 6];
private _bgMo = 0;
{ _bgMo = _bgMo + ([_x] call _weight) * 0.35; } forEach _bg;
if ((count _bg) > 0) then { _parts pushBack [format ["%1 app(s) en arrière-plan", count _bg], _bgMo * ([0.6, 1] select _open)]; };
if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then { _parts pushBack ["Appareil photo", 450]; };
if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull])) then { _parts pushBack ["Live cam (encodage vidéo)", 520]; };
if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) then { _parts pushBack ["Liaison drone", 180]; };
if (((missionNamespace getVariable ["COMSPEC_ATAK_Music", createHashMap]) getOrDefault ["kind", ""]) isNotEqualTo "" && {!((missionNamespace getVariable ["COMSPEC_ATAK_Music", createHashMap]) getOrDefault ["paused", false])}) then { _parts pushBack ["Lecture audio", 90]; };
if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap])) > 0) then { _parts pushBack ["Guidage GPS", 60]; };
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
