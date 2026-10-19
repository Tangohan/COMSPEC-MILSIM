/*
    Liaison d'un flux vidéo (Live cam d'un porteur, caméra fixe) à travers le réseau et ses relais.
    Le flux part du téléphone de la source (liaison montante : son meilleur relais), traverse le réseau, puis arrive
    sur le mien (liaison descendante : fn_linkQuality). Le plus faible des deux fixe la qualité de l'image.
      ["publish"]       : publie ma liaison montante pour les autres clients (boucle de XEH_postInitClient, 10 s) :
                          player getVariable "COMSPEC_ATAK_NetUp" = [kbit/s, perte %, relais]. Diffusé seulement
                          quand le palier change (débit par 250 kbit/s, perte par 10 %, relais) et tient deux tours (relais ou coupure : aussitôt).
      ["eval", source]  -> HashMap : kbps, loss, tier ("HD" | "SD" | "BAS" | "MINIMAL" | "COUPÉ"), res (côté de la
                          texture en px, 0 si coupé), drop (part du temps où l'image décroche, 0-0.8),
                          up [kbit/s, perte, relais] de la source, down [kbit/s, perte, relais] du téléphone, why.
    Caméra fixe : elle passe en Wi-Fi par l'ATAK du camp le plus proche (portée comspec_atak_native_camera_range) ;
    sa liaison montante est celle de ce téléphone. Mon téléphone à portée de son Wi-Fi : liaison directe, sans réseau
    (débit selon la distance), pour regarder une caméra partagée par NFC ou Bluetooth en coupure réseau.
*/
params [["_mode", "eval"], ["_src", objNull]];
private _mine = {
    private _q = [] call comspec_atak_native_fnc_linkQuality;
    private _k = _q getOrDefault ["kbps", 0];
    private _relay = if (_k > 0 && {_q getOrDefault ["sim", false]}) then { (["best"] call comspec_atak_native_fnc_relayNet) param [1, ""] } else { "" };
    [_k, _q getOrDefault ["loss", 0], _relay]
};
switch (_mode) do {
    case "publish": {
        if (!hasInterface || {!alive player}) exitWith { false };
        private _up = if ([player] call comspec_atak_native_fnc_hasDevice) then { call _mine } else { [0, 100, ""] };
        private _sig = [round ((_up select 0) / 250), round ((_up select 1) / 10), _up select 2];
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_NetUpSig", []];
        if (_sig isEqualTo _old) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_NetUpPend", []]; false };
        // Simple variation du palier (évanouissements, bâtiment) : diffusée seulement si elle tient deux tours de suite,
        // sinon chaque joueur près d'un relais rediffusait sa liaison toutes les 10 s. Changement de relais, coupure ou
        // retour du réseau : tout de suite.
        private _now = (_old param [2, "?"]) isNotEqualTo (_sig select 2) || {((_old param [0, -1]) isEqualTo 0) isNotEqualTo ((_sig select 0) isEqualTo 0)};
        if (!_now && {_sig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_NetUpPend", []])}) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_NetUpPend", _sig];
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_NetUpPend", []];
        uiNamespace setVariable ["COMSPEC_ATAK_NetUpSig", _sig];
        player setVariable ["COMSPEC_ATAK_NetUp", _up, true];
        true
    };
    default {
        private _cr = missionNamespace getVariable ["comspec_atak_native_camera_range", 600];
        if !(_cr isEqualType 0) then { _cr = 600; };
        private _dd = if (_src isKindOf "COMSPEC_ATAK_CameraObj") then { player distance _src } else { 1e9 };
        if (_dd < _cr) exitWith {
            private _f = (_dd / _cr) ^ 2;
            private _kbps = round (6000 * (1 - _f) max 300);
            private _loss = round (25 * _f);
            private _tier = switch (true) do {
                case (_kbps >= 1800 && {_loss < 15}): { ["HD", 512, 0] };
                case (_kbps >= 700): { ["SD", 256, 0.05] };
                default { ["BAS", 128, 0.2] };
            };
            createHashMapFromArray [
                ["kbps", _kbps], ["loss", _loss], ["tier", _tier select 0], ["res", _tier select 1],
                ["drop", ((_tier select 2) + _loss / 150) min 0.8], ["up", [_kbps, _loss, "Wi-Fi"]], ["down", [_kbps, _loss, "Wi-Fi"]], ["why", ""]
            ]
        };
        private _down = call _mine;
        private _host = _src;
        if (_src isKindOf "COMSPEC_ATAK_CameraObj") then {
            private _range = missionNamespace getVariable ["comspec_atak_native_camera_range", 600];
            if !(_range isEqualType 0) then { _range = 600; };
            private _side = _src getVariable ["COMSPEC_ATAK_CamSide", ""];
            private _near = allPlayers select { alive _x && {(_x distance _src) < _range} && {str side group _x isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} };
            _near = [_near, [], { _x distance _src }, "ASCEND"] call BIS_fnc_sortBy;
            _host = _near param [0, objNull];
        };
        private _up = switch (true) do {
            case (isNull _host): { [0, 100, ""] };
            case (_host isEqualTo player): { _down };
            // Pas encore publié (client qui vient d'arriver) : liaison moyenne supposée.
            default { _host getVariable ["COMSPEC_ATAK_NetUp", [1500, 10, ""]] };
        };
        _up params [["_uk", 0], ["_ul", 100], ["_ur", ""]];
        _down params ["_dk", "_dl", "_dr"];
        // Débit utile : le maillon le plus faible, moins l'en-tête du flux ; pertes cumulées.
        private _kbps = round (((_uk min _dk) * 0.85) max 0);
        private _loss = round (100 * (1 - (1 - (_ul min 100) / 100) * (1 - (_dl min 100) / 100)));
        private _why = switch (true) do {
            case (_dk <= 0): { "Pas de réseau sur votre téléphone." };
            case (isNull _host): { "Aucun ATAK du camp à portée de la caméra." };
            case (_uk <= 0): { "La source n'a plus de réseau (hors de portée des relais, bâtiment ou brouillage)." };
            default { "" };
        };
        if (_why isNotEqualTo "") then { _kbps = 0; };
        private _tier = switch (true) do {
            case (_kbps <= 0): { ["COUPÉ", 0, 1] };
            case (_kbps >= 1800 && {_loss < 15}): { ["HD", 512, 0] };
            case (_kbps >= 700): { ["SD", 256, 0.05] };
            case (_kbps >= 250): { ["BAS", 128, 0.2] };
            default { ["MINIMAL", 64, 0.4] };
        };
        createHashMapFromArray [
            ["kbps", _kbps], ["loss", _loss], ["tier", _tier select 0], ["res", _tier select 1],
            ["drop", ((_tier select 2) + _loss / 150) min 0.8], ["up", [_uk, _ul, _ur]], ["down", _down], ["why", _why]
        ]
    };
}
