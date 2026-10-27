/*
    Relais du réseau ATAK : liste, carte de couverture (débit, perte) et triangulation d'un appareil par les relais qui l'entendent.
      "list"     : relais (COMSPEC_AtakRelays : module « ATAK · Relais » ou Overwatch, du camp ou sans camp) puis
                   passerelles de véhicule et PC mobiles du camp (si le réglage serveur les autorise), puis radios MPU5
                   des équipiers qui reçoivent un relais (fn_mpu5). Caché 2 s ("force" : non).
                   [[objet, position antenne ASL, nom, type ("relay" | "gw" | "cp" | "mpu5"), portée m, Mbit/s reçus du réseau,
                     en service, état ("" | "détruit" | "éteint" | "batterie vide" | "brouillé" | "sans liaison"),
                     téléphones à portée, places (0 = illimité), batterie % (-1 = secteur), raccordé, relais amont du répéteur,
                     fréquence MHz, fréquence automatique, perte %, structure % (100 - dégâts), santé ("nominal" | "dégradé" |
                     "critique" | "hors service"), bonds jusqu'au relais raccordé (0 = raccordé, -1 = sans liaison),
                     relais sur le même canal à portée, latence ms]...]
                   Condition d'activation (COMSPEC_AtakRelayCond, fn_relayCond : mât déployé, à l'arrêt, code SQF) : relais
                   et passerelles de véhicule hors service tant qu'elle n'est pas remplie, état = sa raison.
                   Variables d'un relais : COMSPEC_AtakRelayRange, Throughput, Name, Height (m, défaut 4), Slots, Side,
                   On (défaut vrai), Freq (canal en MHz, 0 = automatique : premier canal libre du plan 4 940-4 975 MHz
                   qu'aucun relais voisin n'occupe), Batt et Autonomy (minutes), Root (raccordé par satellite ou fibre, défaut vrai ; sinon
                   répéteur qui doit voir un relais raccordé). Débit : dégâts, brouilleurs, relais voisins sur le même canal
                   (interférences), chaîne des répéteurs, saturation. La perte du relais cumule les mêmes causes.
                   Réseau exclusivement par relais (fn_relayNet "only") : sans relais reçu, aucun réseau.
      "best"     : meilleur relais reçu par le téléphone (fn_linkQuality, page Réseau). Relais utilisé perdu : toast
                   « Relais perdu : X (raison), liaison dégradée » ou « bascule sur Y ».
                 Relais préféré (fiche du relais sur la carte, uiNamespace COMSPEC_ATAK_RelayPin) : gardé tant qu'il est reçu.
      "power"    : [relais, "on" | "off" | "battery"] ; "info" : [relais] fiche en notification ; "sweep" : batteries (serveur).
      "entry"    : [relais] -> son entrée de "list" ou [].
      "only"     : vrai si le réseau passe exclusivement par les relais (réglage serveur comspec_atak_native_relay_only,
                   case « Réseau exclusivement par relais » d'un module « ATAK · Relais », ou mode réaliste d'Athena).
      "required" : vrai si les relais sont obligatoires (mode « only » ou case « Relais obligatoires »).
      "pin"      : [relais ou objNull] préfère ce relais (objNull : retour au choix automatique).
      "link"     : [position antenne ASL, portée, Mbit/s, position ASL visée, mode, objets ignorés, gain dB]
                   -> [qualité 0-1, Mbit/s, perte %, marge dB, [[cause, dB]...], MCS] : bilan radio de fn_radioLink
                   (relief et zone de Fresnel, feuillage et bâtiments en mode "full"), débit par modulation adaptative.
                   Réglage serveur comspec_atak_native_net_realism à 0 : ancien modèle (portée x 0,35 derrière le relief).
      "coverage" : calcule en tâche de fond une grille 41 x 41 autour du centre donné (ou de moi), rayon rcRadius
                   (2, 5 ou 10 km) : meilleur débit reçu à 1,7 m du sol, brouilleurs compris.
                   Résultat : missionNamespace COMSPEC_ATAK_Coverage = [centre, case, [[pos, Mbit/s, perte %, relais]...],
                   rayon, % couvert, heure, nb relais, débit moyen].
      "at"       : [position] -> [Mbit/s, perte %, nom du relais] d'après la dernière couverture, ou [] hors grille.
      "clear"    : efface la couverture.
      "locate"   : [objet visé] triangule l'appareil : chaque relais ou téléphone du maillage qui l'entend donne une distance
                   estimée (puissance reçue, bruitée selon la qualité du lien) ; position par moindres carrés.
                   Résultat ajouté à missionNamespace COMSPEC_ATAK_Trilat (10 dernières, effacées au bout de 10 min) :
                   [nom, position estimée ou [], rayon d'erreur m, [[position source, distance estimée]...], heure, netId].
      "trilatClear" : efface les triangulations (et celle en cours).
      "locateStart" : [netId visé] lance la triangulation : les relais écoutent 6 à 10 s (bandeau), puis "locate".
                   Annulable par "trilatClear" (menu clic droit de la carte : « Effacer les localisations »).
*/
params [["_act", "list"], ["_arg", []]];
private _now = [time, serverTime] select isMultiplayer;
switch (_act) do {
    case "list": {
        // Recalcul au plus toutes les 2 s (appelé à chaque image par le calque de la carte).
        private _cache = missionNamespace getVariable ["COMSPEC_ATAK_RelayCache", []];
        if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 2} && {_arg isNotEqualTo "force"}) exitWith { _cache select 1 };
        private _out = [];
        private _defR = 2000;
        // Réglage réalisme seulement s'il vient d'une fonction de la config du jeu (CfgFunctions, finale) : realism_config
        // n'est pas construit, et sans cette garde ce nom ne désignerait pas une fonction finale.
        if (isClass (configFile >> "CfgFunctions" >> "ATHENA") && {!isNil "ATHENA_fnc_getRealismParam"}) then { _defR = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam; };
        private _side = str side group player;
        private _num = { params ["_v", "_d"]; [_d, _v] select (_v isEqualType 0) };
        // Brouilleurs actifs (mêmes règles que fn_linkQuality) : facteur de débit à une position.
        private _jam = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= _now) && {!((_x param [5, false]) && {(_x param [3, ""]) isEqualTo _side})} };
        private _jamAt = {
            params ["_p"];
            private _m = 1;
            {
                _x params [["_jp", [0, 0, 0]], ["_jr", 500]];
                if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                private _d = _p distance2D _jp;
                // Force du brouilleur (7e élément, 1 par défaut), comme fn_linkQuality.
                private _js = (([_x param [6, 1], 1] select !((_x param [6, 1]) isEqualType 0)) max 0) min 1;
                if (_d < _jr) then { _m = _m * (1 - _js * (1 - (_d / _jr) ^ 2)); };
            } forEach _jam;
            _m
        };
        private _jf = [];
        {
            if (isNull _x || {!(_x getVariable ["COMSPEC_AtakRelay", false])}) then { continue };
            // Relais réservé à un camp (module « ATAK · Relais ») : invisible pour les autres.
            private _rs = _x getVariable ["COMSPEC_AtakRelaySide", ""];
            if (_rs isEqualType "" && {_rs isNotEqualTo ""} && {_rs isNotEqualTo _side}) then { continue };
            // Dégâts : débit puis portée baissent petit à petit (réglage comspec_atak_native_relay_damage, perte à 100 % de dégâts).
            private _dmgK = (missionNamespace getVariable ["comspec_atak_native_relay_damage", 70]) / 100;
            private _r = ([_x getVariable ["COMSPEC_AtakRelayRange", _defR], _defR] call _num) * (1 - 0.5 * _dmgK * damage _x);
            private _mb = [_x getVariable ["COMSPEC_AtakRelayThroughput", 12], 12] call _num;
            private _n = _x getVariable ["COMSPEC_AtakRelayName", "Relais ATAK"];
            if (!(_n isEqualType "") || {_n isEqualTo ""}) then { _n = "Relais ATAK"; };
            private _h = [_x getVariable ["COMSPEC_AtakRelayHeight", 4], 4] call _num;
            private _slots = [_x getVariable ["COMSPEC_AtakRelaySlots", 0], 0] call _num;
            private _batt = [_x getVariable ["COMSPEC_AtakRelayBatt", -1], -1] call _num;
            private _root = _x getVariable ["COMSPEC_AtakRelayRoot", true];
            private _freq = round ([_x getVariable ["COMSPEC_AtakRelayFreq", 0], 0] call _num);
            private _ant = (getPosASL _x) vectorAdd [0, 0, _h];
            // Condition d'activation de la mission (fn_relayCond : mât déployé, à l'arrêt, code...).
            private _cond = [_x] call comspec_atak_native_fnc_relayCond;
            private _st = switch (true) do {
                case (!alive _x || {damage _x >= 0.95}): { "détruit" };
                case !(_x getVariable ["COMSPEC_AtakRelayOn", true]): { "éteint" };
                case (_batt isEqualTo 0): { "batterie vide" };
                case !(_cond select 0): { _cond select 1 };
                default { "" };
            };
            private _j = [_ant] call _jamAt;
            private _m = _mb * (1 - _dmgK * damage _x) * _j;
            if (_st isEqualTo "" && {_m < 0.05}) then { _st = "brouillé"; };
            _jf pushBack _j;
            _out pushBack [_x, _ant, _n, "relay", _r, _m, _st isEqualTo "", _st, 0, _slots, _batt, _root isEqualTo true, objNull,
                _freq max 0, _freq <= 0, 0, round (100 * (1 - damage _x)), "", 0, 0, 0];
        } forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
        // Fréquences automatiques : premier canal du plan (bande C, 8 canaux de 5 MHz) qu'aucun relais voisin (portées qui
        // se chevauchent) n'occupe déjà ; les canaux fixés par la mission sont pris en premier.
        private _plan = [4940, 4945, 4950, 4955, 4960, 4965, 4970, 4975];
        {
            if !(_x select 14) then { continue };
            private _me = _x;
            private _used = (_out select { _x isNotEqualTo _me && {(_x select 13) > 0} && {((_x select 1) distance2D (_me select 1)) < ((_x select 4) + (_me select 4))} }) apply { _x select 13 };
            _x set [13, (_plan select { !(_x in _used) }) param [0, _plan select (_forEachIndex mod 8)]];
        } forEach _out;
        // Interférences : chaque relais en service sur le même canal dont la portée couvre l'antenne divise le débit
        // et ajoute de la perte.
        {
            if !(_x select 6) then { continue };
            private _me = _x;
            private _co = { _x isNotEqualTo _me && {_x select 6} && {(_x select 13) isEqualTo (_me select 13)} && {((_x select 1) distance (_me select 1)) < ((_x select 4) max (_me select 4))} } count _out;
            _x set [19, _co];
            if (_co > 0) then { _x set [5, (_x select 5) / (1 + 0.35 * _co)]; };
        } forEach _out;
        // Répéteurs : sans liaison propre (satellite, fibre), un relais doit voir un relais raccordé, de proche en proche.
        // Débit du répéteur = le plus faible de la chaîne, réduit par la qualité de chaque bond.
        private _real = (["realism"] call comspec_atak_native_fnc_netCond);
        private _feed = _out apply { [-1, _x select 5] select ((_x select 6) && {_x select 11}) };
        private _hop = _feed apply { [-1, 0] select (_x >= 0) };
        private _queue = [];
        { if (_x >= 0) then { _queue pushBack _forEachIndex; }; } forEach _feed;
        while { _queue isNotEqualTo [] } do {
            private _i = _queue deleteAt 0;
            private _a = _out select _i;
            {
                if ((_feed select _forEachIndex) >= 0 || {!(_x select 6)}) then { continue };
                private _reach = (_a select 4) max (_x select 4);
                private _d = (_a select 1) distance (_x select 1);
                if (_d > 2 * _reach) then { continue };
                // Bond entre relais : bilan radio (relief, zone de Fresnel entre les deux antennes), qualité = marge / 20 dB.
                // Mode simplifié : portée x 0,35 quand le relief coupe la ligne (ancien modèle).
                private _q = -1;
                if (_real isEqualTo 0) then {
                    if (terrainIntersectASL [_a select 1, _x select 1]) then { _reach = _reach * 0.35; };
                    if (_d <= _reach) then { _q = 1 - (_d / _reach) ^ 2; };
                } else {
                    private _mg = ([_a select 1, _x select 1, _reach] call comspec_atak_native_fnc_radioLink) select 0;
                    if (_mg >= 0) then { _q = (_mg / 20) min 1; };
                };
                if (_q < 0) then { continue };
                _feed set [_forEachIndex, (_x select 5) min ((_feed select _i) * (0.45 + 0.55 * _q))];
                _hop set [_forEachIndex, (_hop select _i) + 1];
                _x set [12, _a select 0];
                _queue pushBack _forEachIndex;
            } forEach _out;
        };
        {
            _x set [18, _hop select _forEachIndex];
            if !(_x select 6) then { continue };
            private _f = _feed select _forEachIndex;
            if (_f < 0) then { _x set [6, false]; _x set [7, "sans liaison"]; _x set [5, 0]; } else { _x set [5, _f]; };
        } forEach _out;
        {
            _x params ["_o", "_m", "_r", "_mb", "_n"];
            // Passerelle de véhicule soumise à une condition (fn_relayCond, ex. mât déployé) : listée hors service sinon.
            private _cond = [_o] call comspec_atak_native_fnc_relayCond;
            _out pushBack [_o, (getPosASL _o) vectorAdd [0, 0, [3, 9] select (_m isEqualTo "cp")], _n, ["gw", "cp"] select (_m isEqualTo "cp"), _r, [0, _mb] select (_cond select 0), _cond select 0, _cond select 1, 0, 0, -1, true, objNull,
                4985, false, 0, round (100 * (1 - damage _o)), "", 0, 0, 0];
            _jf pushBack 1;
        } forEach (["list"] call comspec_atak_native_fnc_vehicleGateway);
        // Radios maillées MPU5 des équipiers, ou radio ACRE2/TFAR où leur téléphone est branché (fn_mpu5) : relais portés qui redistribuent le débit montant que leur
        // téléphone reçoit d'un relais ou d'une autre MPU5 (au plus 3 bonds, publiés dans COMSPEC_ATAK_Mpu5).
        private _mR = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        {
            (_x getVariable ["COMSPEC_ATAK_Mpu5", []]) params [["_up", 0], ["_hp", 0], ["_rg", _mR], ["_rn", "MPU5"]];
            if (!(_up isEqualType 0) || {_up <= 0} || {_hp >= 3}) then { continue };
            private _ant = (eyePos _x) vectorAdd [0, 0, 0.4];
            private _j = [_ant] call _jamAt;
            private _m = _up * _j;
            private _st = ["", "brouillé"] select (_m < 0.05);
            _out pushBack [_x, _ant, format ["%1 %2", ["Radio", "MPU5"] select (_rn isEqualTo "MPU5"), [_x, true] call comspec_atak_native_fnc_unitCallsign], "mpu5", _rg, _m, _st isEqualTo "", _st, 0, 0, -1, false, objNull,
                4990, false, 0, round (100 * (1 - damage _x)), "", _hp, 0, 0];
            _jf pushBack _j;
        } forEach (allPlayers select { alive _x && {_x isNotEqualTo player} && {str side group _x isEqualTo _side} });
        // Saturation : au-delà de ses places, le débit du relais se partage entre les téléphones à portée.
        // Aucun relais en service : pas de comptage des téléphones (hasDevice sur chaque joueur, toutes les 2 s).
        private _users = if ((_out findIf { _x select 6 }) < 0) then { [] } else {
            allPlayers select { alive _x && {str side group _x isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} }
        };
        {
            private _sat = 0;
            if (_x select 6) then {
                private _ap = _x select 1;
                private _r = _x select 4;
                private _u = { (_x distance2D _ap) < _r } count _users;
                _x set [8, _u];
                private _sl = _x select 9;
                if (_sl > 0 && {_u > _sl}) then { _x set [5, (_x select 5) * _sl / _u]; _sat = (_u - _sl) / _sl; };
            };
            // Perte du relais : dégâts, brouillage, canal partagé, bonds des répéteurs, saturation (causes indépendantes).
            private _dmg = 100 - (_x select 16);
            private _keep = (1 - 0.4 * _dmg / 100) * (1 - 0.8 * (1 - (_jf select _forEachIndex))) * (1 - ((0.07 * (_x select 19)) min 0.6))
                * (1 - ((0.03 * ((_x select 18) max 0)) min 0.3)) * (1 - ((0.2 * _sat) min 0.4));
            private _loss = [100, round (100 * (1 - _keep))] select (_x select 6);
            _x set [15, _loss];
            _x set [20, [0, round (4 + 9 * ((_x select 18) max 0) + 6 * (_x select 19) + 25 * (1 - (_jf select _forEachIndex)) + 15 * (_sat min 2))] select (_x select 6)];
            // Santé : hors service, critique (structure < 50 %, batterie < 10 %, perte > 40 %), dégradé, nominal.
            private _bt = _x select 10;
            _x set [17, switch (true) do {
                case !(_x select 6): { "hors service" };
                case (_dmg >= 50 || {_bt >= 0 && {_bt < 10}} || {_loss > 40}): { "critique" };
                case (_dmg >= 10 || {_bt >= 0 && {_bt < 25}} || {_loss > 12} || {_sat > 0}): { "dégradé" };
                default { "nominal" };
            }];
        } forEach _out;
        missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", [diag_tickTime, _out]];
        _out
    };
    case "best": {
        // Meilleur relais ou passerelle reçu par le téléphone : [objet, nom, distance, Mbit/s, perte % (lien radio et
        // relais), type, portée effective, téléphones à portée, places, batterie %, fréquence MHz, santé, latence ms du
        // relais, marge dB de la voie montante, émission possible, [[cause, dB]...], MCS] ou []. Hystérésis : celui déjà accroché est gardé 20 % au-delà de sa portée. Relais préféré (fiche du
        // relais sur la carte) : choisi tant qu'il est reçu.
        // Résultat gardé 1,5 s téléphone ouvert, 6 s fermé : qualité de liaison, Messages, Réseau et voie de données le
        // demandent dans la même seconde, et chaque calcul lance des dizaines de lancers de rayons (relief, feuillage, bâtiments).
        private _bc = uiNamespace getVariable ["COMSPEC_ATAK_RelayBest", []];
        if ((count _bc) isEqualTo 2 && {diag_tickTime - (_bc select 0) < ([6, 1.5] select !isNull ([] call comspec_atak_native_fnc_display))}) exitWith { _bc select 1 };
        private _last = uiNamespace getVariable ["COMSPEC_ATAK_RelayLast", objNull];
        private _pin = uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull];
        private _eye = eyePos player;
        private _best = [];
        private _pinned = false;
        private _real = (["realism"] call comspec_atak_native_fnc_netCond);
        // Bilan radio (fn_radioLink) : la portée du relais est celle de la voie montante (le téléphone émet bien moins
        // fort que le relais) ; la voie descendante a 4 dB de plus, d'où une frange « réception seule » au-delà de la
        // portée, où l'on reçoit encore sans pouvoir émettre. Terminal de bord ou passerelle (antenne de toit) : +3 dB.
        // Évanouissements lents (multitrajets) de ±2 dB propres à chaque relais, plus profonds en mouvement rapide.
        // MPU5 portée (fn_mpu5) : le téléphone passe par la radio maillée, plus puissante (réglage comspec_atak_native_mpu5_gain).
        private _gain = ([0, 3] select (([player] call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo "")) + (["gain"] call comspec_atak_native_fnc_mpu5);
        private _spd = (abs speed vehicle player) / 3.6;
        private _dl = [4, 0] select (_real isEqualTo 0);
        private _cands = [];
        {
            _x params ["_o", "_ant", "", "", "_r", "_mb", "_ok"];
            if (!_ok) then { continue };
            private _rr = [_r, _r * 1.2] select (_o isEqualTo _last);
            if ((_ant distance _eye) > 2 * _rr) then { continue };
            private _fade = 0;
            if (_real > 0) then {
                private _seed = ((_ant select 0) + (_ant select 1)) mod 360;
                _fade = 2 * (sin (time * 21 + _seed)) * (cos (time * 7.3 + _seed * 3));
                if (_spd > 8) then { _fade = _fade - random ((_spd / 10) min 3); };
            };
            private _l = ["link", [_ant, _rr, _mb, _eye, "fast", [], _gain + _fade + _dl]] call comspec_atak_native_fnc_relayNet;
            if ((_l select 3) < 0) then { continue };
            _cands pushBack [-(_l select 3), _forEachIndex, _x, _rr, _fade];
        } forEach (["list"] call comspec_atak_native_fnc_relayNet);
        // Les trois meilleurs au relief seul (et le relais préféré) sont recalculés avec feuillage et bâtiments.
        _cands sort true;
        private _top = [];
        { if (_forEachIndex < 3 || {!isNull _pin && {((_x select 2) select 0) isEqualTo _pin}}) then { _top pushBack _x; }; } forEach _cands;
        private _bestTx = false;
        {
            _x params ["", "", "_e", "_rr", "_fade"];
            _e params ["_o", "_ant", "_n", "_k", "_r", "_mb", "_ok", "", "_u", "_sl", "_bt", "", "", "_fq", "", "_rl", "", "_hl", "", "", "_lat"];
            private _l = ["link", [_ant, _rr, _mb, _eye, "full", [player, _o], _gain + _fade]] call comspec_atak_native_fnc_relayNet;
            private _tx = (_l select 3) >= 0;
            // Réception seule : débit de la voie descendante, aucune émission possible.
            if (!_tx) then {
                _l = ["link", [_ant, _rr, _mb, _eye, "full", [player, _o], _gain + _fade + _dl]] call comspec_atak_native_fnc_relayNet;
                if ((_l select 3) < 0) then { continue };
            };
            private _isPin = !isNull _pin && {_o isEqualTo _pin};
            if (_pinned && {!_isPin}) then { continue };
            if (_isPin || {_best isEqualTo []} || {_tx && {!_bestTx}} || {(_tx isEqualTo _bestTx) && {(_l select 1) > (_best select 3)}}) then {
                private _dist = _ant distance _eye;
                private _mg = [(_l select 3) - _dl, _l select 3] select _tx;
                private _eff = if (_real isEqualTo 0) then { [_rr, _rr * 0.35] select (terrainIntersectASL [_ant, _eye]) } else { round (_dist * 10 ^ ((_mg max -20) / 20)) };
                private _loss = round (100 - (100 - (_l select 2)) * (100 - _rl) / 100);
                _best = [_o, _n, _dist, _l select 1, _loss, _k, _eff, _u, _sl, _bt, _fq, _hl, _lat, _mg, _tx, _l select 4, _l select 5];
                _pinned = _isPin;
                _bestTx = _tx;
            };
        } forEach _top;
        // Relais perdu (détruit, coupé, batterie vide, brouillé, sans liaison, ou hors de portée) : toast sur le téléphone,
        // ou notification s'il est fermé, avec le relais de repli s'il y en a un.
        private _now0 = _best param [0, objNull];
        private _lastN = uiNamespace getVariable ["COMSPEC_ATAK_RelayLastName", ""];
        if (_lastN isNotEqualTo "" && {_now0 isNotEqualTo _last}) then {
            private _old = ((["list"] call comspec_atak_native_fnc_relayNet) select { (_x select 0) isEqualTo _last }) param [0, []];
            private _why = [_old param [7, ""], "hors service"] select (_old isEqualTo []);
            // Simple passage à un relais meilleur, l'ancien encore reçu : pas de toast.
            private _still = _why isEqualTo "" && {((["link", [_old select 1, (_old select 4) * 1.2, _old select 5, _eye]] call comspec_atak_native_fnc_relayNet) select 1) > 0};
            if (!_still) then {
                private _msg = if (_why isNotEqualTo "") then { format ["Relais perdu : %1 (%2)", _lastN, _why] } else { format ["Hors de portée : %1", _lastN] };
                _msg = _msg + ([[", liaison dégradée", ", plus aucun réseau"] select (["only"] call comspec_atak_native_fnc_relayNet), format [", bascule sur %1", _best select 1]] select (_best isNotEqualTo []));
                if !([_msg, 4] call comspec_atak_native_fnc_toast) then { ["WARNING", _msg, 4, 30] call comspec_atak_native_fnc_notify; };
            };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_RelayLast", _now0];
        uiNamespace setVariable ["COMSPEC_ATAK_RelayLastName", _best param [1, ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_RelayBest", [diag_tickTime, _best]];
        _best
    };
    case "power": {
        // [relais, "on" | "off" | "battery"] depuis l'action ACE : allumer, couper, changer la batterie.
        _arg params [["_o", objNull], ["_what", "on"]];
        if (isNull _o || {!alive _o}) exitWith { false };
        switch (_what) do {
            case "off": { _o setVariable ["COMSPEC_AtakRelayOn", false, true]; ["INFO", format ["%1 coupé", _o getVariable ["COMSPEC_AtakRelayName", "Relais"]], 3, 20] call comspec_atak_native_fnc_notify; };
            case "battery": { _o setVariable ["COMSPEC_AtakRelayBatt", 100, true]; ["SUCCESS", "Batterie du relais changée", 3, 20] call comspec_atak_native_fnc_notify; };
            default { _o setVariable ["COMSPEC_AtakRelayOn", true, true]; ["SUCCESS", format ["%1 allumé", _o getVariable ["COMSPEC_AtakRelayName", "Relais"]], 3, 20] call comspec_atak_native_fnc_notify; };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil];
        playSound "ClickSoft";
        true
    };
    case "entry": {
        private _o = _arg;
        ((["list"] call comspec_atak_native_fnc_relayNet) select { (_x select 0) isEqualTo _o }) param [0, []]
    };
    case "only": {
        (["relayOnly"] call comspec_atak_native_fnc_netCond)
            || {missionNamespace getVariable ["COMSPEC_ATAK_RelayOnly", false]}
            || {missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]}
    };
    case "required": {
        (["only"] call comspec_atak_native_fnc_relayNet) || {missionNamespace getVariable ["COMSPEC_ATAK_RelaysRequired", false]}
    };
    case "pin": {
        private _o = _arg;
        if (!(_o isEqualType objNull)) then { _o = objNull; };
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull];
        if (!isNull _o && {_o isEqualTo _was}) then { _o = objNull; };
        uiNamespace setVariable ["COMSPEC_ATAK_RelayPin", _o];
        uiNamespace setVariable ["COMSPEC_ATAK_RelayBest", []];
        uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
        private _msg = if (isNull _o) then { "Relais : choix automatique du meilleur relais" } else { format ["Relais préféré : %1 (gardé tant qu'il est reçu)", _o getVariable ["COMSPEC_AtakRelayName", "Relais"]] };
        if !([_msg, 3] call comspec_atak_native_fnc_toast) then { ["INFO", _msg, 3, 20] call comspec_atak_native_fnc_notify; };
        !isNull _o
    };
    case "info": {
        // [relais] : fiche du relais en notification (action ACE « État du relais »).
        private _o = _arg;
        private _e = ((["list", "force"] call comspec_atak_native_fnc_relayNet) select { (_x select 0) isEqualTo _o }) param [0, []];
        if (_e isEqualTo []) exitWith { ["INFO", "Relais d'un autre réseau : aucune information", 3, 20] call comspec_atak_native_fnc_notify; };
        _e params ["", "", "_n", "", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "_root", "", "_fq", "_auto", "_loss", "_hp", "_hl"];
        ["INFO", format ["%1 : %2 · %9 MHz · perte %10 %% · structure %11 %% · portée %3 m · %4 Mbit/s · %5 téléphone(s)%6 · %7 · %8",
            _n, [_hl, _st] select !_ok, round _r, _mb toFixed 1, _u, ["", format [" / %1 places", _sl]] select (_sl > 0),
            ["sur secteur", format ["batterie %1 %%", round _bt]] select (_bt >= 0), ["répéteur", "raccordé"] select _root,
            _fq, _loss, _hp], 8, 30] call comspec_atak_native_fnc_notify;
    };
    case "sweep": {
        // Serveur, toutes les 30 s : les relais allumés sur batterie se vident (COMSPEC_AtakRelayAutonomy minutes pour 100 %).
        if (!isServer) exitWith {};
        {
            if (isNull _x || {!alive _x} || {!(_x getVariable ["COMSPEC_AtakRelayOn", true])}) then { continue };
            private _aut = _x getVariable ["COMSPEC_AtakRelayAutonomy", 0];
            private _bt = _x getVariable ["COMSPEC_AtakRelayBatt", -1];
            if (!(_aut isEqualType 0) || {_aut <= 0} || {!(_bt isEqualType 0)} || {_bt <= 0}) then { continue };
            // Valeur précise gardée sur le serveur (locale) ; la valeur publique, arrondie au %, n'est diffusée que si elle change.
            // Écart > 0,5 avec la valeur publique : batterie changée ou réglée ailleurs (Zeus, module), on repart de celle-ci.
            private _pr = _x getVariable ["COMSPEC_AtakRelayBattPrec", _bt];
            if (!(_pr isEqualType 0) || {abs (_pr - _bt) > 0.5}) then { _pr = _bt; };
            private _nb = (_pr - 50 / _aut) max 0;
            _x setVariable ["COMSPEC_AtakRelayBattPrec", _nb];
            private _pub = [round _nb, 0] select (_nb < 0.5);
            if (_pub isNotEqualTo _bt) then { _x setVariable ["COMSPEC_AtakRelayBatt", _pub, true]; };
        } forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
    };
    case "link": {
        // [antenne ASL, portée, Mbit/s, cible ASL, mode ("fast" relief seul | "full" + feuillage et bâtiments),
        //  objets ignorés, gain dB en plus] -> [qualité 0-1, Mbit/s, perte %, marge dB, [[cause, dB]...], MCS 0-7 (-1 : aucun)].
        _arg params ["_ant", "_range", "_mb", "_p", ["_mode", "fast"], ["_ign", []], ["_extra", 0]];
        if ((["realism"] call comspec_atak_native_fnc_netCond) isEqualTo 0) exitWith {
            // Modèle simplifié (ancien) : portée x 0,35 derrière le relief, qualité 1 - (d / portée)².
            private _d = _ant distance _p;
            private _eff = _range;
            if (_d <= _range && {terrainIntersectASL [_ant, _p]}) then { _eff = _eff * 0.35; };
            if (_d > _eff) exitWith { [0, 0, 100, -1, [], -1] };
            private _q = (1 - (_d / _eff) ^ 2) * (1 - 0.15 * rain);
            [_q, _mb * (0.45 + 0.55 * _q), round ((1 - _q) * 35), 20 * log (_eff / (_d max 1)), [], -1]
        };
        ([_ant, _p, _range, _mode, _ign] call comspec_atak_native_fnc_radioLink) params ["_mg", "_ls"];
        _mg = _mg + _extra;
        if (_mg < 0) exitWith { [0, 0, 100, _mg, _ls, -1] };
        // Modulation adaptative : comme une radio maillée réelle, 8 débits (MCS 0 à 7) selon la marge ; perte de paquets
        // forte au seuil, négligeable au-delà de 15 dB.
        private _mcs = ({ _mg >= _x } count [0, 2, 4, 6, 9, 12, 16, 20]) - 1;
        [(_mg / 20) min 1, _mb * ([0.06, 0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1] select _mcs), round (40 * exp (-_mg / 5)), _mg, _ls, _mcs]
    };
    case "coverage": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_CoverageBusy", false]) exitWith { ["INFO", "Calcul de couverture déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        private _relays = (["list"] call comspec_atak_native_fnc_relayNet) select { _x select 6 };
        if (_relays isEqualTo []) exitWith { ["WARNING", "Aucun relais intact ni passerelle de véhicule dans votre camp", 4, 20] call comspec_atak_native_fnc_notify; };
        private _radius = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["rcRadius", 5000];
        private _c = if (_arg isEqualType [] && {(count _arg) >= 2}) then { _arg } else { getPosASL player };
        missionNamespace setVariable ["COMSPEC_ATAK_CoverageBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_CoverageProgress", 0];
        [_relays, _radius, [_c select 0, _c select 1]] spawn {
            params ["_relays", "_radius", "_center"];
            private _n = 41;
            private _half = floor (_n / 2);
            private _cell = (2 * _radius) / (_n - 1);
            private _jam = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= ([time, serverTime] select isMultiplayer)) && {!((_x param [5, false]) && {(_x param [3, ""]) isEqualTo str side group player})} };
            private _cells = [];
            private _cov = 0;
            private _sum = 0;
            for "_i" from 0 to (_n - 1) do {
                for "_j" from 0 to (_n - 1) do {
                    private _dx = (_i - _half) * _cell;
                    private _dy = (_j - _half) * _cell;
                    if (sqrt (_dx * _dx + _dy * _dy) > _radius + _cell / 2) then { continue };
                    private _p2 = [(_center select 0) + _dx, (_center select 1) + _dy];
                    private _p = [_p2 select 0, _p2 select 1, ((getTerrainHeightASL _p2) max 0) + 1.7];
                    private _best = [0, 0, 100];
                    private _src = "";
                    {
                        _x params ["", "_ant", "_name", "", "_r", "_mb"];
                        if ((_ant distance2D _p) > _r) then { continue };
                        private _l = ["link", [_ant, _r, _mb, _p]] call comspec_atak_native_fnc_relayNet;
                        if ((_l select 1) > (_best select 1)) then { _best = _l; _src = _name; };
                    } forEach _relays;
                    private _mbps = _best select 1;
                    private _loss = _best select 2;
                    {
                        _x params [["_jp", [0, 0, 0]], ["_jr", 500]];
                        if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                        private _d = _p distance2D _jp;
                        // Force du brouilleur (7e élément, 1 par défaut), comme _jamAt et fn_linkQuality.
                        private _js = (([_x param [6, 1], 1] select !((_x param [6, 1]) isEqualType 0)) max 0) min 1;
                        if (_d < _jr) then { private _m = 1 - _js * (1 - (_d / _jr) ^ 2); _mbps = _mbps * _m; _loss = _loss max (round ((1 - _m) * 80)); };
                    } forEach _jam;
                    if (_mbps > 0.05) then { _cov = _cov + 1; _sum = _sum + _mbps; } else { _mbps = 0; _loss = 100; };
                    _cells pushBack [_p, _mbps, _loss, _src];
                };
                missionNamespace setVariable ["COMSPEC_ATAK_CoverageProgress", (_i + 1) / _n];
            };
            private _pct = round (100 * _cov / ((count _cells) max 1));
            missionNamespace setVariable ["COMSPEC_ATAK_Coverage", [_center, _cell, _cells, _radius, _pct, dayTime, count _relays, [0, _sum / (_cov max 1)] select (_cov > 0)]];
            missionNamespace setVariable ["COMSPEC_ATAK_CoverageBusy", false];
            profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
            ["SUCCESS", format ["Couverture calculée : %1 %% du terrain reçoit le réseau sur %2 km", _pct, round (_radius / 1000)], 4, 30] call comspec_atak_native_fnc_notify;
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
        };
    };
    case "at": {
        private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
        if ((count _cv) < 8) exitWith { [] };
        _cv params ["_center", "_cell", "_cells", "_radius"];
        if ((_arg distance2D _center) > _radius + _cell / 2) exitWith { [] };
        // Coins hors cercle sautés au calcul : on cherche la case la plus proche.
        private _best = [];
        private _bd = 1e9;
        {
            private _d = (_x select 0) distance2D _arg;
            if (_d < _bd) then { _bd = _d; _best = _x; };
            if (_d < _cell / 2) exitWith {};
        } forEach _cells;
        if (_best isEqualTo [] || {_bd > _cell}) exitWith { [] };
        [_best select 1, _best select 2, _best select 3]
    };
    case "clear": { missionNamespace setVariable ["COMSPEC_ATAK_Coverage", nil]; };
    case "trilatClear": { missionNamespace setVariable ["COMSPEC_ATAK_Trilat", []]; missionNamespace setVariable ["COMSPEC_ATAK_TrilatPending", []]; };
    case "locateStart": {
        // Retour de jeu : la localisation tombait instantanément. Les relais écoutent d'abord l'émission de la cible.
        private _id = _arg;
        if (_id isEqualType objNull) then { _id = netId _id; };
        if (isNull (objectFromNetId _id)) exitWith { ["WARNING", "Appareil introuvable", 3, 20] call comspec_atak_native_fnc_notify; };
        private _dur = 6 + random 4;
        private _token = diag_tickTime;
        missionNamespace setVariable ["COMSPEC_ATAK_TrilatPending", [_id, _token, diag_tickTime + _dur]];
        ["INFO", format ["Triangulation en cours : écoute des relais pendant %1 s…", round _dur], 4, 20] call comspec_atak_native_fnc_notify;
        [{
            params ["_id", "_token"];
            // Annulée (« Effacer les localisations ») ou remplacée par une autre : rien.
            if (((missionNamespace getVariable ["COMSPEC_ATAK_TrilatPending", []]) param [1, -1]) isNotEqualTo _token) exitWith {};
            missionNamespace setVariable ["COMSPEC_ATAK_TrilatPending", []];
            ["locate", _id] call comspec_atak_native_fnc_relayNet;
        }, [_id, _token], _dur] call CBA_fnc_waitAndExecute;
        true
    };
    case "locate": {
        private _t = _arg;
        if (_t isEqualType "") then { _t = objectFromNetId _t; };
        if (isNull _t) exitWith { ["WARNING", "Appareil introuvable", 3, 20] call comspec_atak_native_fnc_notify; };
        private _name = if (_t isKindOf "CAManBase") then { [_t, true] call comspec_atak_native_fnc_unitCallsign } else { getText (configOf _t >> "displayName") };
        private _tp = if (_t isKindOf "CAManBase") then { (eyePos _t) vectorAdd [0, 0, 0.5] } else { (getPosASL _t) vectorAdd [0, 0, 0.3] };
        // Sources : relais intacts et passerelles, puis téléphones du maillage de mon camp (sauf la cible).
        private _src = ((["list"] call comspec_atak_native_fnc_relayNet) select { _x select 6 }) apply { [_x select 1, _x select 4, _x select 5] };
        private _range = missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000];
        private _side = side group player;
        {
            _src pushBack [(eyePos _x) vectorAdd [0, 0, 0.5], _range, 4];
        } forEach ((allPlayers select { alive _x && {_x isNotEqualTo _t} && {side group _x isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} }) select [0, 63]);
        private _heard = [];
        {
            _x params ["_ant", "_r", "_mb"];
            private _l = ["link", [_ant, _r, _mb, _tp]] call comspec_atak_native_fnc_relayNet;
            private _q = _l select 0;
            if (_q <= 0) then { continue };
            // Distance lue sur la puissance reçue : erreur de 5 % (lien franc) à 25 % (lien faible).
            private _d = _ant distance2D _tp;
            private _err = 0.05 + 0.2 * (1 - _q);
            _heard pushBack [_ant, (_d * (1 + (random [-1, 0, 1]) * _err)) max 5, _err];
        } forEach _src;
        private _cnt = count _heard;
        if (_cnt isEqualTo 0) exitWith { ["WARNING", format ["%1 : aucun relais ni téléphone ne l'entend", _name], 4, 20] call comspec_atak_native_fnc_notify; };
        // Moindres carrés : on part du barycentre des sources (décalé pour 1 ou 2 sources) puis descente de gradient.
        private _est = [0, 0];
        { _est = _est vectorAdd [(_x select 0) select 0, (_x select 0) select 1]; } forEach _heard;
        _est = _est vectorMultiply (1 / _cnt);
        if (_cnt < 3) then { _est = _est vectorAdd [random [-50, 0, 50], random [-50, 0, 50]]; };
        for "_k" from 1 to 80 do {
            private _g = [0, 0];
            {
                _x params ["_a", "_dm"];
                private _v = _est vectorDiff [_a select 0, _a select 1];
                private _len = (vectorMagnitude _v) max 0.1;
                _g = _g vectorAdd (_v vectorMultiply ((_len - _dm) / _len));
            } forEach _heard;
            _est = _est vectorDiff (_g vectorMultiply (0.5 / _cnt));
        };
        private _res = 0;
        { _res = _res + (((_est distance2D [(_x select 0) select 0, (_x select 0) select 1]) - (_x select 1)) ^ 2); } forEach _heard;
        private _meanD = 0;
        { _meanD = _meanD + (_x select 1) * (_x select 2); } forEach _heard;
        private _rad = ((sqrt (_res / _cnt)) + (_meanD / _cnt) / sqrt _cnt) max 15;
        // Une source : seulement un cercle ; deux : deux points possibles, rayon élargi.
        private _pos = [];
        if (_cnt >= 2) then { _pos = [_est select 0, _est select 1, 0]; };
        if (_cnt isEqualTo 2) then { _rad = _rad * 2.5; };
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []]) select { (_x select 5) isNotEqualTo (netId _t) && {_now - (_x select 4) < 600} };
        _list pushBack [_name, _pos, round _rad, _heard apply { [_x select 0, _x select 1] }, _now, netId _t];
        missionNamespace setVariable ["COMSPEC_ATAK_Trilat", _list select [((count _list) - 10) max 0, 10]];
        ["SUCCESS", switch (true) do {
            case (_cnt isEqualTo 1): { format ["%1 : une seule source l'entend, cercle de %2 m", _name, round ((_heard select 0) select 1)] };
            case (_cnt isEqualTo 2): { format ["%1 : deux sources, position ambiguë à %2 m près", _name, round _rad] };
            default { format ["%1 localisé par %2 sources à %3 m près (%4)", _name, _cnt, round _rad, [_pos, 8] call comspec_atak_native_fnc_gridRef] };
        }, 5, 30] call comspec_atak_native_fnc_notify;
        _pos
    };
};
