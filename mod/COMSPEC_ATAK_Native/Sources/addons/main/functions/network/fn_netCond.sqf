/*
    Conditions réseau effectives de la simulation radio du téléphone (débit, météo, surcharge, pertes de paquets…).
    Chaque condition a trois sources, la première qui s'exprime l'emporte :
      1. règle de la communauté sur le site (Contrôle serveur > Réseau et simulation, clé net_*, reçue par COMSPEC Link
         dans COMSPEC_TenantExperience ; « Laisser au serveur » = pas de règle) ;
      2. conditions appliquées au serveur depuis le menu Échap (fn_netConfig, COMSPEC_ATAK_NetCond diffusée à tous) ;
      3. réglage CBA (COMSPEC > ATAK · Simulation), toutes les simulations actives par défaut.
    Params : [clé] -> valeur effective (lue dans un cache recalculé au plus toutes les secondes)
             ["defs"] -> [[clé, variable CBA, clé du site, "bool" | "num", défaut, [valeurs proposées], [libellés]]...]
             ["source", clé] -> "site" | "server" | "cba"
             ["refresh"] -> recalcule le cache tout de suite (après un changement)
*/
params [["_k", ""], ["_a", ""]];
private _defs = missionNamespace getVariable "COMSPEC_ATAK_NetDefs";
if (isNil "_defs") then {
    _defs = [
        ["sim", "comspec_atak_native_net_sim", "net_sim", "bool", true, [], []],
        ["realism", "comspec_atak_native_net_realism", "net_realism", "num", 1, [1, 2, 0], ["Réaliste", "Exigeant", "Simplifié (ancien modèle)"]],
        ["weather", "comspec_atak_native_net_weather", "net_weather", "bool", true, [], []],
        ["weatherK", "comspec_atak_native_net_weather_k", "net_weather_k", "num", 100, [0, 50, 100, 150, 200], ["0 %", "50 %", "100 % (réel)", "150 %", "200 %"]],
        ["congestion", "comspec_atak_native_net_congestion", "net_congestion", "bool", true, [], []],
        ["bgLoad", "comspec_atak_native_net_bg_load", "net_bg_load", "num", 10, [0, 10, 25, 50, 75, 90], ["0 %", "10 %", "25 %", "50 %", "75 %", "90 %"]],
        ["loss", "comspec_atak_native_net_loss", "net_loss", "bool", true, [], []],
        ["lossFloor", "comspec_atak_native_net_loss_floor", "net_loss_floor", "num", 0, [0, 1, 3, 5, 10, 20], ["0 %", "1 %", "3 %", "5 %", "10 %", "20 %"]],
        ["latencyAdd", "comspec_atak_native_net_latency_add", "net_latency_add", "num", 0, [0, 50, 150, 300, 600, 1200], ["0 ms", "50 ms", "150 ms", "300 ms", "600 ms (satellite)", "1 200 ms"]],
        ["relayOnly", "comspec_atak_native_relay_only", "net_relay_only", "bool", false, [], []],
        ["gateways", "comspec_atak_native_vehicle_gateways", "net_gateways", "bool", false, [], []],
        ["offlineHold", "comspec_atak_native_offline_hold", "net_offline_hold", "bool", true, [], []],
        ["videoRelays", "comspec_atak_native_video_relays", "net_video_relays", "bool", true, [], []]
    ];
    missionNamespace setVariable ["COMSPEC_ATAK_NetDefs", _defs];
};
if (_k isEqualTo "defs") exitWith { _defs };
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_NetEff", []];
if (_k isNotEqualTo "refresh" && {_k isNotEqualTo "source"} && {(count _cache) isEqualTo 3} && {diag_tickTime - (_cache select 0) < 1}) exitWith {
    (_cache select 1) getOrDefault [_k, nil]
};
// Recalcul : site, puis serveur, puis CBA.
private _site = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
if !(_site isEqualType createHashMap) then { _site = createHashMap; };
private _srv = createHashMapFromArray ((missionNamespace getVariable ["COMSPEC_ATAK_NetCond", [[], "", 0]]) param [0, []]);
private _eff = createHashMap;
private _src = createHashMap;
{
    _x params ["_key", "_cba", "_sk", "_kind", "_def"];
    private _v = missionNamespace getVariable [_cba, _def];
    private _s = "cba";
    private _sv = _srv getOrDefault [_key, nil];
    if (!isNil "_sv" && {_sv isEqualType _def}) then { _v = _sv; _s = "server"; };
    private _r = _site getOrDefault [_sk, "player"];
    if (_r isEqualType "") then {
        _r = toLower trim _r;
        if (_kind isEqualTo "bool") then {
            if (_r in ["on", "off"]) then { _v = _r isEqualTo "on"; _s = "site"; };
        } else {
            if (_r isNotEqualTo "" && {_r isNotEqualTo "player"} && {(_r regexMatch "^-?[0-9]+(\.[0-9]+)?$")}) then { _v = parseNumber _r; _s = "site"; };
        };
    };
    if !(_v isEqualType _def) then { _v = _def; _s = "cba"; };
    _eff set [_key, _v];
    _src set [_key, _s];
} forEach _defs;
missionNamespace setVariable ["COMSPEC_ATAK_NetEff", [diag_tickTime, _eff, _src]];
if (_k isEqualTo "source") exitWith { _src getOrDefault [_a, "cba"] };
if (_k isEqualTo "refresh") exitWith { _eff };
_eff getOrDefault [_k, nil]
