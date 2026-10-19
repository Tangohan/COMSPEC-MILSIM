/*
    Positions BFT par points (PLI, condition « pli » de fn_netCond), comme un vrai ATAK : mon téléphone envoie sa
    position à intervalle régulier (condition « pliRate », 10 s en Milsim), telle que son GPS la voit (brouillage,
    dégâts, ciel masqué : fn_ewEffects / fn_gpsFix), et les alliés la reçoivent dans la variable publique
    COMSPEC_ATAK_Pli = [position ASL affichée, cap, heure (serverTime en multijoueur), précision en mètres].
      - en mouvement (plus de 50 m depuis le dernier point), un point part dès la moitié de l'intervalle ;
      - pas de point sans balise (téléphone éteint, cassé, sans signal), en réception seule ni sans position GPS ;
      - un point peut se perdre selon les pertes de la liaison (fn_linkQuality) : le suivant part à l'intervalle normal.
    Le récepteur (fn_localDataRefresh) affiche le dernier point reçu et son âge (en direct, ancien, perdu).
    Params : ["tick"] (client, chaque seconde) | ["now"] -> heure de référence des points
*/
params [["_mode", "tick"]];
private _now = [time, serverTime] select isMultiplayer;
if (_mode isEqualTo "now") exitWith { _now };
if (!hasInterface || {isNull player}) exitWith {};
if !(["pli"] call comspec_atak_native_fnc_netCond) exitWith {
    if !(isNil { player getVariable "COMSPEC_ATAK_Pli" }) then { player setVariable ["COMSPEC_ATAK_Pli", nil, true]; };
};
if !(player getVariable ["COMSPEC_ATAK_Beacon", true]) exitWith {};
private _rate = (["pliRate"] call comspec_atak_native_fnc_netCond) max 1;
private _st = missionNamespace getVariable ["COMSPEC_ATAK_PliState", [-1e9, [0, 0, 0], 1]];
_st params ["_at", "_lastPos", "_k"];
private _age = diag_tickTime - _at;
private _moved = (getPosASL player) distance2D _lastPos;
if (_age < _rate * _k && {_age < (_rate / 2) || {_moved < 50}}) exitWith {};
// Prochain point à l'intervalle, à ±15 % près (les téléphones ne partent pas tous à la même seconde).
missionNamespace setVariable ["COMSPEC_ATAK_PliState", [diag_tickTime, getPosASL player, 0.85 + random 0.3]];
private _lq = [] call comspec_atak_native_fnc_linkQuality;
if !(_lq getOrDefault ["tx", true]) exitWith {};
if ((random 100) < ((_lq getOrDefault ["loss", 0]) min 90)) exitWith {};
([] call comspec_atak_native_fnc_ewEffects) params ["", "", "", "", "_err", "_off", "_gst"];
if (_gst isEqualTo "NOFIX" && {_err isEqualTo 0}) exitWith {};
player setVariable ["COMSPEC_ATAK_Pli", [(getPosASL player) vectorAdd _off, getDir vehicle player, _now, _err], true];
