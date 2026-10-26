/*
    IA et ATAK (demande de TTRD, 26/10 : « corriger le réalisme des IA, pouvoir obliger qu'ils aient un ATAK »).
    1. Obliger les IA à porter un ATAK : réglage serveur « IA équipées d'un ATAK » (aucune, chefs de groupe, toutes),
       module « ATAK · IA équipées » (Eden : unités ou groupes synchronisés ; Zeus : le groupe de l'unité visée), ou
       par script : _unit setVariable ["COMSPEC_ATAK_AiAtak", true, true] (false : jamais d'ATAK pour cette IA).
       Le serveur leur donne un vrai téléphone (objet COMSPEC_ATAK_Phone dans l'inventaire, réglage « Objet donné ») ;
       sans place dans l'inventaire, l'IA compte quand même comme équipée (COMSPEC_ATAK_AiAtak). Elle reçoit aussi un
       indicatif propre (« Alpha 1-1 · 2 ») au lieu du seul nom de groupe.
    2. Réalisme des nœuds IA (réglage « IA réalistes sur le réseau », coché par défaut) :
       - une IA n'apparaît sur le BFT que si elle porte vraiment un ATAK, même quand les joueurs n'en ont pas besoin
         (fn_hasDevice) ;
       - ses points PLI sont envoyés par le serveur, comme le téléphone d'un joueur (fn_pli) : même intervalle (± 15 %,
         la moitié en mouvement), même erreur GPS (dérive lente propre à chaque IA, et non un tirage différent chez
         chaque joueur), même point pour tout le monde (écrans TOC compris), perdu ou dégradé par les brouilleurs (calcul de fn_ewEffects).
    Modes (serveur) : ["init"] | ["tick"] (chaque seconde) | ["equip", unité] | ["set", [unités, vrai | faux]]
*/
params [["_mode", "tick"], ["_a", objNull]];
if (!isServer) exitWith { false };
private _fn = comspec_atak_native_fnc_aiAtak;
switch (_mode) do {
    case "init": {
        COMSPEC_ATAK_AiState = createHashMap;   // netId -> [prochain point, dernière position, décalage GPS, facteur]
        ["comspec_atak_native_aiAtak", { ["set", _this] call comspec_atak_native_fnc_aiAtak; }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_aiAtak; }, 1] call COMSPEC_ATAK_PfhSpread;
        true
    };
    case "set": {
        _a params [["_units", []], ["_on", true]];
        {
            if (!isNull _x && {!isPlayer _x}) then {
                _x setVariable ["COMSPEC_ATAK_AiAtak", _on, true];
                if (_on) then { ["equip", _x] call _fn; } else { _x setVariable ["COMSPEC_ATAK_Pli", nil, true]; };
            };
        } forEach _units;
        true
    };
    case "equip": {
        private _u = _a;
        if (isNull _u || {isPlayer _u} || {!alive _u} || {_u getVariable ["COMSPEC_ATAK_AiEquipped", false]}) exitWith { false };
        _u setVariable ["COMSPEC_ATAK_AiEquipped", true];
        if (isNil { _u getVariable "COMSPEC_ATAK_AiAtak" }) then { _u setVariable ["COMSPEC_ATAK_AiAtak", true, true]; };
        private _item = missionNamespace getVariable ["comspec_atak_native_ai_atak_item", "COMSPEC_ATAK_Phone"];
        if (_item isEqualTo "" || {!isClass (configFile >> "CfgWeapons" >> _item)}) then { _item = "COMSPEC_ATAK_Phone"; };
        // Déjà un terminal : rien à ajouter. Sinon dans l'inventaire, là où l'unité est locale (IA d'un joueur, client sans tête).
        private _set = missionNamespace getVariable ["COMSPEC_ATAK_DeviceCatalogSet", createHashMap];
        if ((((assignedItems _u) + (items _u)) findIf { (toLower _x) in _set }) < 0) then {
            if (local _u) then { _u addItem _item; } else { [_u, _item] remoteExecCall ["addItem", _u]; };
        };
        // Indicatif propre à chaque IA : le BFT et les écrans TOC ne montraient que le nom du groupe, en double.
        if ((_u getVariable ["COMSPEC_AllyCallsign", ""]) isEqualTo "" && {(_u getVariable ["COMSPEC_CallsignPublic", ""]) isEqualTo ""}) then {
            private _i = (units group _u) find _u;
            _u setVariable ["COMSPEC_AllyCallsign", [format ["%1 · %2", groupId group _u, _i + 1], groupId group _u] select (_u isEqualTo leader group _u), true];
        };
        true
    };
    case "tick": {
        private _mode = missionNamespace getVariable ["comspec_atak_native_ai_atak", "off"];
        private _real = missionNamespace getVariable ["comspec_atak_native_ai_realism", true];
        // Équipement : IA voulues par le réglage ou la mission, vérifiées toutes les 10 s (IA créées en cours de partie).
        if (diag_tickTime > (missionNamespace getVariable ["COMSPEC_ATAK_AiSweepAt", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_AiSweepAt", diag_tickTime + 10];
            {
                private _u = _x;
                private _force = _u getVariable "COMSPEC_ATAK_AiAtak";
                private _want = if (isNil "_force") then {
                    switch (_mode) do { case "all": { true }; case "leaders": { _u isEqualTo leader group _u }; default { false }; }
                } else { _force };
                if (_want && {!(_u getVariable ["COMSPEC_ATAK_AiEquipped", false])}) then { ["equip", _u] call _fn; };
            } forEach (allUnits select { !isPlayer _x && {alive _x} && {_x isKindOf "CAManBase"} });
        };
        if (!_real || {!(["pli"] call comspec_atak_native_fnc_netCond)}) exitWith {};
        // Points PLI des IA équipées : un par intervalle et par IA, 250 IA au plus.
        private _rate = (["pliRate"] call comspec_atak_native_fnc_netCond) max 1;
        private _gps = ["gps"] call comspec_atak_native_fnc_netCond;
        private _gk = ((["gpsK"] call comspec_atak_native_fnc_netCond) max 0) / 100;
        private _now = ["now"] call comspec_atak_native_fnc_pli;
        private _jams = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { _x isEqualType [] };
        private _n = 0;
        {
            private _u = _x;
            if (_n >= 250) exitWith {};
            if (!alive _u || {!([_u] call comspec_atak_native_fnc_hasDevice)}) then { continue };
            _n = _n + 1;
            private _k = netId _u;
            private _st = COMSPEC_ATAK_AiState getOrDefault [_k, [diag_tickTime + random _rate, [0, 0, 0], [0, 0, 0], 1]];
            _st params ["_at", "_lastPos", "_off", "_f"];
            private _p = getPosASL _u;
            private _due = diag_tickTime >= _at || {(_p distance2D _lastPos) > 50 && {diag_tickTime >= _at - _rate * _f / 2}};
            if (!_due) then { COMSPEC_ATAK_AiState set [_k, _st]; continue };
            private _f2 = 0.85 + random 0.3;
            // Brouilleurs (même calcul que fn_ewEffects pour un joueur) : point perdu selon la force, sinon GPS dégradé.
            private _side = str side group _u;
            private _jk = 0;
            {
                _x params [["_jp", [0, 0, 0]], ["_rad", 500], "", ["_js", ""], ["_until", 1e9], ["_exempt", false], ["_str", 1]];
                if (_until < _now || {_exempt && {_js isEqualTo _side}}) then { continue };
                if (_jp isEqualType objNull) then { if (isNull _jp) then { continue }; _jp = getPosATL _jp; };
                private _d = _u distance2D _jp;
                if (_d < _rad) then { _jk = _jk max ((1 - _d / (_rad max 1)) * ((_str max 0) min 1)); };
            } forEach _jams;
            if ((random 1) >= _jk * 0.9) then {
                // Erreur GPS : dérive lente propre à l'IA (3 à 8 m à découvert, x 2,5 sous un toit ou sous les arbres).
                private _err = 0;
                if (_gps) then {
                    private _roof = lineIntersects [_p, _p vectorAdd [0, 0, 25], vehicle _u];
                    private _cover = [1, 2.5] select (_roof || {(count (nearestTerrainObjects [_u, ["TREE"], 6, false, true])) > 2});
                    _err = (3 + random 5) * _gk * _cover + ([0, 25 + 175 * _jk] select (_jk > 0));
                    private _a = random 360;
                    _off = (_off vectorMultiply 0.7) vectorAdd [_err * 0.3 * sin _a, _err * 0.3 * cos _a, 0];
                    if ((vectorMagnitude _off) > _err * 1.5) then { _off = (vectorNormalized _off) vectorMultiply (_err * 1.5); };
                } else { _off = [0, 0, 0]; };
                _u setVariable ["COMSPEC_ATAK_Pli", [_p vectorAdd _off, getDir vehicle _u, _now, round ((vectorMagnitude _off) max _err)], true];
            };
            COMSPEC_ATAK_AiState set [_k, [diag_tickTime + _rate * _f2, _p, _off, _f2]];
        } forEach (allUnits select { !isPlayer _x });
        if ((count COMSPEC_ATAK_AiState) > 800) then { { if (isNull (objectFromNetId _x)) then { COMSPEC_ATAK_AiState deleteAt _x; }; } forEach (keys COMSPEC_ATAK_AiState); };
    };
};
true
