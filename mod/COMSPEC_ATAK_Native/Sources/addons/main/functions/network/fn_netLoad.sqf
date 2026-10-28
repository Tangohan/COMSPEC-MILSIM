/*
    Surcharge du réseau ATAK : charge du canal radio partagé par les téléphones d'un même relais.
    Chaque téléphone publie son trafic (kbit/s émis sur les 30 dernières secondes, variable publique COMSPEC_ATAK_NetLoad
    sur le joueur, au plus toutes les 10 s et seulement si elle change). La charge d'un relais est la somme du trafic
    des joueurs à sa portée, plus le trafic de fond fixé par le serveur (fn_netCond "bgLoad", % de la capacité : autres
    utilisateurs, flux vidéo ISR, synchronisations), rapportée à sa capacité.
    Effets (fn_linkQuality, condition « Surcharge » fn_netCond "congestion") :
      - au-delà de 100 %, la capacité se partage : débit x 1 / charge ;
      - accès au canal (CSMA) : rendement en baisse à mesure qu'il se remplit, jusqu'à -30 % à 100 % ;
      - file d'attente : latence en plus (M/M/1, 20 ms x u / (1 - u)) ;
      - collisions : pertes de paquets jusqu'à 25 %.
    Params : ["track", Ko] (fn_netSend) | ["publish"] (boucle client) |
             ["at", position ASL, portée m, capacité kbit/s] -> [charge 0-n, téléphones, kbit/s des autres]
*/
params [["_mode", "at"], ["_a", 0], ["_b", 0], ["_c", 0]];
switch (_mode) do {
    case "track": {
        private _t = missionNamespace getVariable ["COMSPEC_ATAK_NetTxKb", []];
        _t pushBack [diag_tickTime, _a];
        if ((count _t) > 200) then { _t deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_NetTxKb", _t];
    };
    case "publish": {
        private _t = (missionNamespace getVariable ["COMSPEC_ATAK_NetTxKb", []]) select { diag_tickTime - (_x select 0) < 30 };
        missionNamespace setVariable ["COMSPEC_ATAK_NetTxKb", _t];
        private _kb = 0;
        { _kb = _kb + (_x select 1); } forEach _t;
        private _kbps = round (_kb * 8 / 30);
        private _old = player getVariable ["COMSPEC_ATAK_NetLoad", 0];
        // Passage à 0 (ou depuis 0) toujours publié : sous 4 kbit/s le seuil ne laissait jamais retomber la valeur publique.
        if (abs (_kbps - _old) > ((_old * 0.2) max 4) || {(_kbps isEqualTo 0) isNotEqualTo (_old isEqualTo 0)}) then { player setVariable ["COMSPEC_ATAK_NetLoad", _kbps, true]; };
    };
    case "at": {
        private _cap = _c max 100;
        private _n = 0;
        private _sum = 0;
        {
            if (_x isEqualTo player || {!alive _x}) then { continue };
            if ((getPosASL _x) distance _a > _b) then { continue };
            private _l = _x getVariable ["COMSPEC_ATAK_NetLoad", 0];
            if (_l isEqualType 0 && {_l > 0}) then { _n = _n + 1; _sum = _sum + _l; };
        } forEach allPlayers;
        private _mine = player getVariable ["COMSPEC_ATAK_NetLoad", 0];
        private _bg = ((["bgLoad"] call comspec_atak_native_fnc_netCond) max 0) / 100;
        [(_sum + _mine) / _cap + _bg, _n + 1, _sum]
    };
};
