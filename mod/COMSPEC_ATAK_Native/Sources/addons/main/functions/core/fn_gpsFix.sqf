/*
    GPS naturel du téléphone (conditions « gps », « gpsK », « gpsFix » de fn_netCond) : précision selon le ciel visible
    et attente des satellites. S'ajoute à l'erreur du brouillage et des dégâts (fn_ewEffects, qui l'appelle).
      - ciel : toit au-dessus de la tête et murs autour (bâtiment), sous-sol, arbres proches (forêt), bâtiments hauts et
        relief qui masquent l'horizon (rue étroite, vallée encaissée), carrosserie d'un véhicule ;
      - satellites visibles : 12 à découvert ; sous 4, plus de fix : la dernière position connue sert 30 s (moins de 25 m
        parcourus), puis position par le réseau (30 à 150 m selon le signal) ; sans réseau, aucune position fiable
        (incertitude de 500 m) ;
      - attente des satellites : téléphone allumé, rallumé ou batterie changée, le GPS cherche de 3 s (éteint moins de
        2 min) à environ 40 s (premier démarrage de la mission) ; en attendant, position approximative par le réseau
        (environ 150 m, sans réseau aucune) ;
      - l'erreur dérive lentement (multitrajets) au lieu de sauter d'une mesure à l'autre ;
      - pertes de fix (condition « gpsLoss », 0-200 %) : part des satellites masqués par le toit, les arbres, les immeubles
        ou le relief qui compte vraiment (0 % : le fix tient partout, seule la précision baisse) ;
      - fiabilité (condition « gpsRel » : 0 parfaite, 1 bonne, 2 moyenne, 3 médiocre) : décrochages du fix de 5 à 25 s et
        sauts de position (multitrajets, 15 à 60 m, qui reviennent en 10 à 30 s), de jamais à environ une fois par minute.
    Recalculé au plus toutes les 2 s (6 s téléphone fermé). État dans missionNamespace : repart à chaque mission.
    Renvoie [erreur en mètres, état "FIX" | "ACQ" | "NOFIX" | "NET" | "OFF", satellites, [raisons (textes à traduire)],
             décalage de la position affichée [dx, dy, 0]].
*/
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_GpsFix", []];
private _open = !isNull ([] call comspec_atak_native_fnc_display);
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < ([6, 2] select _open)}) exitWith { _cache select 1 };
private _res = call {
    if !(["gps"] call comspec_atak_native_fnc_netCond) exitWith {
        missionNamespace setVariable ["COMSPEC_ATAK_GpsLast", nil];
        [0, "FIX", 12, [], [0, 0, 0]]
    };
    private _pos = getPosASL player;
    // Téléphone éteint, vide ou cassé : le GPS repartira de zéro (heure d'extinction gardée pour le temps d'attente).
    private _on = ([player] call comspec_atak_native_fnc_aircrewTerminal) || {([player] call comspec_atak_native_fnc_hasDevice)
        && {!((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"])}};
    if (!_on) exitWith {
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_GpsOffAt" }) then { missionNamespace setVariable ["COMSPEC_ATAK_GpsOffAt", diag_tickTime]; };
        missionNamespace setVariable ["COMSPEC_ATAK_GpsAcqUntil", nil];
        [0, "OFF", 0, ["Téléphone éteint"], [0, 0, 0]]
    };
    // Attente des satellites : démarrée au premier appel téléphone allumé.
    private _until = missionNamespace getVariable "COMSPEC_ATAK_GpsAcqUntil";
    if (isNil "_until") then {
        private _offAt = missionNamespace getVariable "COMSPEC_ATAK_GpsOffAt";
        private _wait = switch (true) do {
            case !(["gpsFix"] call comspec_atak_native_fnc_netCond): { 0 };
            case (isNil "_offAt"): { 28 + random 14 };
            case (diag_tickTime - _offAt < 120): { 2 + random 3 };
            default { 12 + random 13 };
        };
        // Fiabilité médiocre : satellites plus longs à trouver.
        _until = diag_tickTime + _wait * ([1, 1, 1.2, 1.5] select (((["gpsRel"] call comspec_atak_native_fnc_netCond) max 0) min 3));
        missionNamespace setVariable ["COMSPEC_ATAK_GpsAcqUntil", _until];
        missionNamespace setVariable ["COMSPEC_ATAK_GpsOffAt", nil];
    };
    private _k = ((["gpsK"] call comspec_atak_native_fnc_netCond) max 0) / 100;
    private _eye = eyePos player;
    private _veh = vehicle player;
    private _sats = 12;
    private _why = [];
    private _multi = 0;
    // Bâtiment : toit au-dessus, murs autour (mêmes rayons que la liaison radio, fn_linkQuality), sous-sol.
    if (_veh isEqualTo player) then {
        private _roofs = count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 30], player, objNull, true, 4, "GEOM", "NONE"]);
        if (_roofs > 0) then {
            private _walls = 0;
            for "_a" from 0 to 315 step 45 do {
                if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd [8 * sin _a, 8 * cos _a, 0], player, objNull, true, 1, "GEOM", "NONE"])) > 0) then { _walls = _walls + 1; };
            };
            switch (true) do {
                case ((_eye select 2) < (getTerrainHeightASL _eye) - 0.5): { _sats = 0; _why pushBack "En sous-sol"; };
                case (_walls >= 7 && {_roofs >= 2}): { _sats = 2; _why pushBack "Au cœur d'un bâtiment"; };
                case (_walls >= 5 || {_roofs >= 2}): { _sats = 4; _multi = _multi + 35; _why pushBack "Dans un bâtiment"; };
                default { _sats = 6; _multi = _multi + 12; _why pushBack "Sous un toit, près d'une ouverture"; };
            };
        };
    } else {
        // Véhicule : la carrosserie coupe un peu ; aéronef, ciel dégagé.
        if !(_veh isKindOf "Air") then { _sats = _sats - 2; _multi = _multi + 3; _why pushBack "Dans un véhicule"; };
    };
    if (_sats > 2 && {!(_veh isKindOf "Air")}) then {
        // Horizon masqué à 15° : bâtiments hauts (60 m) et relief (400 m), 8 directions.
        private _bld = 0;
        private _ter = 0;
        for "_a" from 0 to 315 step 45 do {
            private _d = [sin _a, cos _a, 0.27];
            if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd (_d vectorMultiply 60), _veh, player, true, 1, "GEOM", "NONE"])) > 0) then { _bld = _bld + 1; } else {
                if (terrainIntersectASL [_eye, _eye vectorAdd (_d vectorMultiply 400)]) then { _ter = _ter + 1; };
            };
        };
        if (_bld >= 3) then { _sats = _sats - (_bld min 5); _multi = _multi + 4 * _bld; _why pushBack "Rue étroite, immeubles autour"; };
        if (_ter >= 3) then { _sats = _sats - ((_ter - 1) min 4); _multi = _multi + 2 * _ter; _why pushBack "Relief qui masque le ciel"; };
        // Forêt : arbres dans les 12 m (le feuillage atténue et réfléchit le signal).
        if (_veh isEqualTo player) then {
            private _trees = count (nearestTerrainObjects [player, ["TREE"], 12, false, true]);
            if (_trees >= 6) then { _sats = _sats - 3; _multi = _multi + 14; _why pushBack "Forêt dense"; } else {
                if (_trees >= 3) then { _sats = _sats - 1; _multi = _multi + 5; _why pushBack "Sous les arbres"; };
            };
        };
    };
    // Pertes de fix réglables : les satellites masqués ne comptent qu'en partie (ou en plus).
    private _lossK = ((["gpsLoss"] call comspec_atak_native_fnc_netCond) max 0) / 100;
    _sats = (12 - round ((12 - _sats) * _lossK)) max 0 min 12;
    // Fiabilité : décrochages et sauts tirés selon le temps écoulé depuis le calcul précédent.
    private _rel = ((["gpsRel"] call comspec_atak_native_fnc_netCond) max 0) min 3;
    private _relAt = missionNamespace getVariable ["COMSPEC_ATAK_GpsRelAt", diag_tickTime];
    private _dt = ((diag_tickTime - _relAt) max 0) min 30;
    missionNamespace setVariable ["COMSPEC_ATAK_GpsRelAt", diag_tickTime];
    // Événements par minute : bonne 1 toutes les 20 min, moyenne toutes les 6 min, médiocre toutes les 2 min.
    private _rate = [0, 0.05, 0.17, 0.5] select _rel;
    private _outUntil = missionNamespace getVariable ["COMSPEC_ATAK_GpsOutUntil", -1];
    if (_rate > 0 && {diag_tickTime > _outUntil} && {random 1 < 1 - exp (-_rate * _dt / 60)}) then {
        _outUntil = diag_tickTime + 5 + random 20;
        missionNamespace setVariable ["COMSPEC_ATAK_GpsOutUntil", _outUntil];
    };
    private _jump = missionNamespace getVariable ["COMSPEC_ATAK_GpsJump", []];
    if (_rate > 0 && {_jump isEqualTo []} && {random 1 < 1 - exp (-_rate * 0.5 * _dt / 60)}) then {
        private _a = random 360;
        private _m = 15 + random 45;
        _jump = [[_m * sin _a, _m * cos _a, 0], diag_tickTime, 10 + random 20];
        missionNamespace setVariable ["COMSPEC_ATAK_GpsJump", _jump];
    };
    private _out = diag_tickTime < _outUntil;
    if (_out) then { _sats = _sats min 3; _why pushBack "Décrochage du signal GPS"; };
    private _last = missionNamespace getVariable ["COMSPEC_ATAK_GpsLast", []];
    // Dérive lente de l'erreur (marche aléatoire bornée).
    private _walk = missionNamespace getVariable ["COMSPEC_ATAK_GpsWalk", [0, 0, 0]];
    private _acq = diag_tickTime < _until;
    if (_acq || {_sats < 4}) exitWith {
        // Pas de fix : dernière position connue (cercle qui grandit) ; pas de fix récent : position par le réseau.
        private _why2 = [["Pas assez de satellites"] + _why, ["Recherche des satellites"]] select _acq;
        private _r = [];
        // Dernière position connue : seulement juste après la perte du fix (30 s) et tant que je n'ai pas bougé de plus
        // de 25 m. Avant, elle restait sans limite : entré dans un bâtiment après un fix dehors, le téléphone me plaçait
        // encore là-bas après 100 m de marche (retour de TTRD, 19/10, carte : « position de tout à l'heure »).
        // Recherche des satellites (rallumage) : 120 s, le joueur n'a en général pas bougé.
        private _lastOk = _last isNotEqualTo [] && {
            private _age = diag_tickTime - (_last select 2);
            [_age < 30 && {((_last select 0) distance2D _pos) < 25}, _age < 120] select _acq
        };
        if (_lastOk) then {
            _last params ["_lp", "_le", "_lt"];
            _r = [round ((_le + (diag_tickTime - _lt) * 1.5) min 500), ["NOFIX", "ACQ"] select _acq, _sats, _why2, _lp vectorDiff _pos];
        } else {
            missionNamespace setVariable ["COMSPEC_ATAK_GpsLast", nil];
            // Position par le réseau (antennes et Wi-Fi, comme un smartphone à l'intérieur) : autour de ma vraie position,
            // 150 m d'incertitude avec une barre de signal, 30 m avec quatre ; le décalage garde sa direction (pas de saut).
            private _bars = ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0];
            if (_bars > 0) then {
                private _rad = 150 - 40 * (((_bars min 4) max 1) - 1);
                private _ang = missionNamespace getVariable ["COMSPEC_ATAK_GpsNetAng", -1];
                if (_ang < 0) then { _ang = random 360; missionNamespace setVariable ["COMSPEC_ATAK_GpsNetAng", _ang]; };
                private _d = 0.6 * _rad;
                _r = [_rad, ["NET", "ACQ"] select _acq, _sats, _why2 + ["Position approximative par le réseau"], [_d * sin _ang, _d * cos _ang, 0]];
            } else {
                // Ni satellites ni réseau : aucune position fiable, incertitude maximale (500 m) affichée.
                _r = [500, "NOFIX", _sats, _why2 + ["Aucune position"], [0, 0, 0]];
            };
        };
        _r
    };
    missionNamespace setVariable ["COMSPEC_ATAK_GpsNetAng", nil];
    // Erreur horizontale typique d'un téléphone : 3 à 5 m à découvert, puis selon le nombre de satellites et les reflets.
    private _base = switch (true) do { case (_sats >= 9): { 3 + random 2 }; case (_sats >= 7): { 6 + random 3 }; case (_sats >= 5): { 12 + random 6 }; default { 25 + random 10 }; };
    private _err = (_base + _multi * (0.7 + random 0.6)) * _k;
    _walk = _walk vectorAdd [(random 2 - 1) * 0.25 * _err, (random 2 - 1) * 0.25 * _err, 0];
    private _m = vectorMagnitude _walk;
    if (_m > 0.8 * _err && {_m > 0}) then { _walk = _walk vectorMultiply (0.8 * _err / _m); };
    missionNamespace setVariable ["COMSPEC_ATAK_GpsWalk", _walk];
    // Saut de position en cours : décalage qui revient peu à peu vers zéro, compté dans l'erreur annoncée.
    private _off = _walk;
    if (_jump isNotEqualTo []) then {
        _jump params ["_jv", "_jt", "_jd"];
        private _f = 1 - (diag_tickTime - _jt) / _jd;
        if (_f <= 0) then { missionNamespace setVariable ["COMSPEC_ATAK_GpsJump", []]; } else {
            _off = _off vectorAdd (_jv vectorMultiply _f);
            _err = _err + (vectorMagnitude _jv) * _f;
            _why pushBack "Saut de position (reflets du signal)";
        };
    };
    missionNamespace setVariable ["COMSPEC_ATAK_GpsLast", [_pos vectorAdd _off, _err, diag_tickTime]];
    [round _err, "FIX", _sats, _why, _off]
};
missionNamespace setVariable ["COMSPEC_ATAK_GpsFix", [diag_tickTime, _res]];
_res
