/*
    Mode drone, côté téléphone du pilote. Le drone doit être posé à ses pieds pour s'appairer (rien à distance) ;
    ensuite la liaison radio porte comspec_atak_native_drone_range mètres (fn_droneRange), réduite par le relief.
    Liaison perdue : le drone rentre seul à son point de décollage, comme un DJI.
    Drones du mod Mavic (Mavic_drone_base_F) : appairage identique, signal repris de Mavic_fnc_getSignal.
      ["pair", drone] / ["unpair"]   un drone de plus s'ajoute à la flotte (fn_droneFleet, 4 au plus), le drone piloté
                                  d'avant continue seul ; désappairer passe au drone suivant de la flotte ;
      ["fleetAdopt", drone]       ce drone de la flotte devient le drone piloté (fn_droneFleet "switch" / "promote") ;
      ["mode", "hover" | "me" | "player" | "home" | "land" | "hunt"]
      ["player", uid]             joueur à suivre ;
      ["alt", m] / ["speed", km/h]
      ["arm"(, chargeur)]         fixe une charge prise dans l'inventaire (drone à moins de 5 m ; chargeur choisi, sinon la première) ;
      ["disarm"]                  retire la charge et la rend à l'inventaire (drone à moins de 5 m) ;
      ["payloads"]                charges compatibles portées : [[chargeur, libellé, nombre]...] ;
      ["strike", "LASER" | "GRID"]  tir et oublie, à confirmer d'un second appui ;
      ["cam"]                     caméra du drone dans l'app ;
      ["camBind"]                 → texture du rendu (r2t) à poser dans un RscPicture : la caméra est recréée sur une
                                  cible de rendu neuve quand elle a été suspendue (page quittée, téléphone rangé) ou que la
                                  qualité a changé (une caméra coupée puis relancée sur la même cible donnait une image noire) ;
      ["quality", 0 | 1 | 2]      qualité du flux (profil COMSPEC_ATAK_DroneQuality) : taille de la texture de rendu ;
      Nacelle (vue caméra) : ["gimbal", [cap relatif, site]] vise (site -90 à +20°, cap relatif au cap du drone, lissé) ;
      ["gimbalNudge", [dcap, dsite]] ; ["recenter"] remet la nacelle devant ; ["zoomBy", facteur] zoom continu x1 à x8 ;
      ["gimbalGet"] → [cap relatif, site] actuels ; ["markMode"] bascule le mode marquage (clic sur l'image = marquer) ;
      ["imgClick", [x, y]] clic sur l'image : la nacelle vise le point ; en mode marquage, la cible sous le clic est marquée ;
      La caméra est posée hors de la boîte englobante du drone, du côté où elle regarde : le drone n'est jamais dans l'image.
      ["sendRaw", [ordre, arguments]] ordre de vol venu du suivi des cibles (fn_droneSeq), sans arrêter ce suivi ;
      ["feedSrc", "DRONE" | "PHONE" | "HELMET"] source de l'image (missionNamespace COMSPEC_ATAK_DroneFeedSrc) : drone, caméra du
                                  téléphone devant le joueur, caméra casque ; ["feedUnit", uid | netId] porteur de la caméra casque
                                  (COMSPEC_ATAK_DroneFeedUnit, le joueur par défaut) ;
      Clichés de la caméra : fn_dronePhoto ; suivi des cibles marquées dans l'ordre : fn_droneSeq ; neige NO DATA : fn_droneStatic.
      ["takeoff"]                 démarre et décolle (tout ordre de vol donné au sol le fait aussi) ;
      ["quick", "hunt" | "me" | "rth" | "land"]   touches rapides des options CBA (RECHERCHE, ME SUIVRE, RTB, ATTERRIR) ;
      ["cqb"]                     bascule le profil CQB (vol bas 2 à 8 m, 5 à 15 km/h, boucle de vol à nous) ;
      ["manual"]                  pilotage manuel au terminal UAV ; tout autre ordre du téléphone reprend la main.
    Verrou (variable publique COMSPEC_DroneLock du drone, appliqué par XEH_postInitClient sur chaque client) :
      "" = le téléphone pilote, personne ne peut se connecter au drone avec un terminal UAV ;
      uid = seul ce joueur peut s'y connecter (pilotage manuel) ; absent = drone libre.
      ["tick"]                    surveillance (toutes les secondes tant qu'un drone est appairé) ;
      ["link"]                    renvoie [barres 0-4, distance, en liaison, qualité 0-1, flux vidéo (fn_videoLink "path")].
    Tâches sur point (onglet TÂCHES) :
      ["tab", "PILOT" | "TASK" | "TARGETS" | "PHOTO" | "LOG"]   onglet de la page ;
      ["task", "GOTO" | "LOITER" | "OBSERVE" | "ESCORT"] / ["taskRad", m]   tâche et rayon choisis ;
      ["pickMap"] / ["picked", position]   point pointé sur la carte du téléphone (clé dronePick, fn_mapMouseButtonDown) ;
      ["taskSend"]                envoie la tâche sur la grille saisie ; ["escort", uid] escorte un joueur allié ;
      ["taskClear"]               efface le point, et remet le drone en stationnaire s'il exécutait une tâche.
    Détections automatiques (onglet CIBLES, ex-PISTES) : ce que la caméra du drone voit seule, en vue directe et à portée (relevé à chaque tick) :
      ["camAim", clé | ""]        pointe la caméra sur une détection, "LOCK:clé" sur une cible marquée (ou la remet devant) ;
      ["trackMark", clé]          pose un marqueur sur la dernière position vue ; ["tracksClear"] vide la liste ;
      ["strike", "TRK:clé"]       frappe la détection (cible suivie si vue il y a moins de 10 s, sinon dernière position).
    Ordres depuis la carte (outil DRONE de la carte du téléphone, mapMode "DRONE") :
      ["mapSub", "GOTO" | "ROUTE" | "ZONE"]   sous-outil ; ["routeMode", "ONCE" | "LOOP" | "PINGPONG"] ; ["zoneKind", "LOITER" | "OBSERVE" | "HUNT"] ;
      ["mapClick", position]      clic sur la carte (aller ici, point de route, centre puis bord / coins de la zone) ;
      ["routeSend"] / ["mapClear"]   envoie la route tracée / efface le tracé en cours ;
      ["mapBar", [x, y, largeur]] dessine la barre drone de la carte (appelé par fn_pageMap).
    Journal : ["log", texte, couleur] ; tout événement y est daté HH:MM:SS (100 lignes au plus).
    Identité : ["rename", texte] (variable publique COMSPEC_DroneName, 24 caractères, vide = nom du modèle) ;
      ["icon", "quad" | "fixed" | "hexa" | "nano" | "fpv"] (variable publique COMSPEC_DroneIcon, textures data\drone_<id>.paa).
    Veille : ["standbyKind", "LAND" | "HOVER"] choix du pilote ; ["standby"(, genre)] se pose ou tient le stationnaire,
      consommation réduite, capteur et caméra coupés (un second appui reprend) ; ["resume"] renvoie l'ordre d'avant la veille.
    FORCER L'EXÉCUTION : ["force"] renvoie le dernier ordre de vol (COMSPEC_ATAK_DroneOrder) avec remise d'aplomb de l'IA
      (équipage recréé, points de passage effacés, déplacement réactivé, altitude et vitesse, doMove) et demande au serveur
      de recoller l'équipage à la machine du drone. Surveillance automatique (tick) : ordre sans effet ou aucun progrès vers
      le but pendant 10 s → même reprise, une fois par ordre, notée au journal.
    Transfert : ["transfer", uid] vers un allié à moins de 50 m portant un téléphone, à confirmer d'un second appui, puis
      3 s de liaison ; le drone lui appartient (COMSPEC_DroneOwner, et COMSPEC_ATAK_Drone sur son client par l'événement
      visé comspec_atak_native_droneCmd "transferIn") ; noté au journal des deux pilotes. ["transferIn", [drone, de, home, étape]].
    Ordres web (COMSPEC Athena, via l'addon Overwatch connect), à appeler sur le client du pilote :
      ["webCmd", [ordre, [arguments]]] → true si l'ordre est accepté, false sinon (pas de drone, hors liaison, ordre inconnu).
      Positions : [x, y] ou [x, y, z] (z ignoré, recalé sur le sol) ; unité : uid du joueur ou netId.
        "takeoff" []            "hover" []            "land" []             "home" []  (retour au pilote)
        "rth" []  (retour au point de décollage)      "me" []  (suivre le pilote)
        "follow" [uid]          "escort" [uid]        "goto" [position]
        "loiter" [position, rayon = 150]              "observe" [position, rayon = 150]
        "hunt" [rayon = 250 (, position du centre)]   "route" [[positions...] (12 au plus), "ONCE" | "LOOP" | "PINGPONG"]
        "alt" [m, 2 à 500]      "speed" [km/h, 5 à 150]                       "cqb" [true | false]
        "standby" [("LAND" | "HOVER")]                "resume" []           "force" []
        "rename" [texte]        "icon" ["quad" | "fixed" | "hexa" | "nano" | "fpv"]
      Pas de frappe depuis le web : elle reste confirmée au téléphone.
    État : missionNamespace COMSPEC_ATAK_Drone (drone appairé), COMSPEC_ATAK_DroneHome, COMSPEC_ATAK_DroneCam,
    COMSPEC_ATAK_DroneCamTgt (clé de piste visée par la caméra), COMSPEC_ATAK_DroneTracks (clé → [objet, type, position ASL,
    vu à (time), vu à (HH:MM:SS), camp vu, distance, n°, état]), COMSPEC_ATAK_DroneLog ([[HH:MM:SS, texte, couleur]...]).
*/
params [["_act", "tick"], ["_arg", ""]];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
// Liaison : [barres 0-4, distance, en liaison, qualité 0-1, flux vidéo] (la qualité règle les défauts TNT de l'image,
// fn_droneStatic). Relais dans la mission (fn_videoLink) : le drone passe aussi par eux (relais à portée du drone, puis
// réseau jusqu'au relais du téléphone), le meilleur chemin l'emporte et donne la qualité du flux ; sans relais, liaison
// directe seule, comme avant. Flux vidéo : HashMap de fn_videoLink "path" (clé "active" fausse en mode arcade).
// Demandée plusieurs fois par seconde (suivi des cibles marquées, une fois par cible ; incrustations ; neige ; flotte) :
// calcul gardé 0,2 s, et réponse avant de préparer le reste de ce fichier (tables d'ordres refaites à chaque appel sinon).
if (_act isEqualTo "link") exitWith {
    private _lc = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinkC", []];
    if ((count _lc) isEqualTo 3 && {(_lc select 0) isEqualTo _d} && {diag_tickTime < (_lc select 1)}) exitWith { +(_lc select 2) };
    private _l = call {
        if (isNull _d || {!alive _d}) exitWith { [0, 0, false, 0] };
        private _range = call comspec_atak_native_fnc_droneRange;
        if (terrainIntersectASL [eyePos player, getPosASL _d]) then { _range = _range * 0.3; };
        if !([player] call comspec_atak_native_fnc_hasDevice) then { _range = 0; };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) then { _range = 0; };
        private _dist = player distance _d;
        // Drone du mod Mavic (DJI) : on reprend son propre calcul de signal (0-1, perdu sous 0,05).
        if (_range > 0 && {_d isKindOf "Mavic_drone_base_F"} && {!isNil "Mavic_fnc_getSignal"}) exitWith {
            private _sig = [player, _d] call Mavic_fnc_getSignal;
            if !(_sig isEqualType 0) then { _sig = 1; };
            private _okM = _sig >= 0.05;
            private _qM = [0, 1 - (1 - ((_sig max 0) min 1)) ^ 2] select _okM;
            [[0, (ceil (_qM * 4)) max 1 min 4] select _okM, round _dist, _okM, _qM]
        };
        // Qualité 1 - (d/portée)² : 4/4 jusqu'à la moitié de la portée, 3/4 jusqu'à 71 %, 2/4 jusqu'à 87 %, 1/4 au-delà.
        // Avant (1 - d/portée) : 4/4 s'arrêtait au quart de la portée (625 m sur 2500 m, retour de TTRD).
        private _ok = _dist < _range;
        private _q = [0, ((1 - (_dist / (_range max 1)) ^ 2) max 0) min 1] select _ok;
        [[0, ceil (4 * _q) max 1] select _ok, round _dist, _ok, _q]
    };
    private _r = call {
        if (isNull _d || {!alive _d}) exitWith { _l + [createHashMap] };
        // Téléphone absent ou batterie vide : ni direct ni relais.
        if (!([player] call comspec_atak_native_fnc_hasDevice) || {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { _l + [createHashMap] };
        private _v = ["path", ["DRONE", getPosASL _d, _l select 3, 20]] call comspec_atak_native_fnc_videoLink;
        if !(_v getOrDefault ["active", false]) exitWith { _l + [_v] };
        private _okV = _v get "ok";
        private _qV = _v get "q";
        [[0, ceil (4 * _qV) max 1 min 4] select _okV, _l select 1, _okV, _qV, _v]
    };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLinkC", [_d, diag_tickTime + 0.2, _r]];
    +_r
};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Ordres de vol suivis (FORCER L'EXÉCUTION, surveillance) et mode que le drone doit afficher une fois l'ordre reçu.
private _flight = ["takeoff", "hover", "follow", "home", "land", "hunt", "goto", "loiter", "observe", "escort", "route", "rth", "strike", "manual", "standby"];
private _expect = createHashMapFromArray [["takeoff", "HOVER"], ["hover", "HOVER"], ["follow", "FOLLOW"], ["home", "HOME"], ["land", "LAND"], ["hunt", "HUNT"], ["goto", "GOTO"], ["loiter", "LOITER"], ["observe", "OBSERVE"], ["escort", "ESCORT"], ["route", "ROUTE"], ["rth", "RTH"], ["strike", "STRIKE"], ["manual", "MANUAL"], ["standby", "STANDBY"]];
// Unité désignée par uid de joueur, ou par netId (équipier IA).
private _unitOf = {
    params [["_id", ""]];
    if (_id isEqualType objNull) exitWith { _id };
    if !(_id isEqualType "") exitWith { objNull };
    private _i = allPlayers findIf { getPlayerUID _x isEqualTo _id };
    if (_i >= 0) exitWith { allPlayers select _i };
    if (_id isEqualTo "") exitWith { objNull };
    objectFromNetId _id
};
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _send = {
    params ["_cmd", ["_args", []]];
    // Zones interdites au drone (fn_noFly) : un ordre dont le but y tombe est refusé (le retour automatique passe toujours).
    private _goal = switch (_cmd) do {
        case "goto"; case "loiter"; case "observe"; case "strike": { [_args param [0, []]] };
        case "route": { _args param [0, []] };
        default { [] };
    };
    private _bad = _goal findIf {
        private _gp = [_x, getPosASL _x] select (_x isEqualType objNull);
        (_gp isEqualType []) && {(count _gp) >= 2} && {(_gp select 0) isEqualType 0} && {(count (["inside", _gp] call comspec_atak_native_fnc_noFly)) > 0}
    };
    if (_bad >= 0) exitWith {
        ["WARNING", "Drone : le but est dans une zone interdite, ordre refusé"] call _say;
        [format ["Ordre refusé (%1) : but en zone interdite", _cmd], "#e5483a"] call _log;
        false
    };
    // Tout ordre du téléphone (sauf le passage en manuel) verrouille le terminal UAV : une seule main sur le drone.
    if (_cmd isNotEqualTo "manual" && {(_d getVariable ["COMSPEC_DroneLock", ""]) isNotEqualTo ""}) then { _d setVariable ["COMSPEC_DroneLock", "", true]; };
    // Dernier ordre de vol : [ordre, arguments, envoyé à, compteur d'ordres reçus du drone à l'envoi, reçu, reprise auto faite,
    // clé du but, meilleure distance, à]
    if (_cmd in _flight) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", [_cmd, _args, time, _d getVariable ["COMSPEC_DroneAckN", 0], false, false, -1, 1e9, time]]; };
    // Un ordre de vol du pilote reprend la main sur le suivi des cibles marquées.
    if (_cmd in _flight && {!(missionNamespace getVariable ["COMSPEC_ATAK_DroneSeqSending", false])}) then { ["stop", "", true] call comspec_atak_native_fnc_droneSeq; };
    // Un ordre du pilote remplace le rôle tenu seul par le drone (fn_droneFleet : garde, surveillance...).
    if (_cmd in _flight && {!(missionNamespace getVariable ["COMSPEC_ATAK_DroneRoleSending", false])} && {(_d getVariable ["COMSPEC_DroneRole", ""]) isNotEqualTo ""}) then { _d setVariable ["COMSPEC_DroneRole", "", true]; };
    ["comspec_atak_native_droneCmd", [_d, _cmd, _args], _d] call CBA_fnc_targetEvent;
};
private _mapRender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
// Charges acceptées : chargeur de l'inventaire → munition déclenchée à l'impact.
private _payloads = [
    ["RPG7_F", "R_PG7_F", "roquette RPG-7"], ["RPG32_F", "R_PG32V_F", "roquette RPG-42"],
    ["DemoCharge_Remote_Mag", "DemoCharge_Remote_Ammo_Scripted", "charge de démolition"],
    ["HandGrenade", "GrenadeHand", "grenade RGO"], ["MiniGrenade", "mini_Grenade", "grenade RGN"]
];
private _link = { ["link"] call comspec_atak_native_fnc_droneAction };
private _needLink = {
    if ((call _link) select 2) exitWith { true };
    ["WARNING", "Drone hors liaison : ordre non transmis"] call _say;
    false
};
// Journal du drone : chaque événement daté, 100 lignes au plus.
private _log = {
    params ["_t", ["_c", ""]];
    private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
    _lg pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _t, _c];
    while { (count _lg) > 100 } do { _lg deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLog", _lg];
    // Lignes écrites depuis le début (le journal plafonné à 100 lignes ne change plus de taille ; voir "tick").
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLogN", (missionNamespace getVariable ["COMSPEC_ATAK_DroneLogN", 0]) + 1];
};
private _modeName = { params ["_m"]; (createHashMapFromArray [["ROUTE", "route"], ["STANDBY", "veille"], ["HOVER", "stationnaire"], ["FOLLOW", "suivi"], ["HOME", "retour au pilote"], ["LAND", "atterrissage"], ["RTH", "retour automatique au point de décollage"], ["STRIKE", "frappe"], ["HUNT", "recherche"], ["MANUAL", "pilotage manuel"], ["GOTO", "vers le point"], ["LOITER", "orbite"], ["OBSERVE", "observation"], ["ESCORT", "escorte"]]) getOrDefault [_m, toLower _m] };
// Grille saisie (6, 8 ou 10 chiffres) → centre de la case, position ASL ; [] si invalide.
private _gridPos = {
    params ["_txt"];
    private _g = _txt regexReplace ["[^0-9]", ""];
    if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) exitWith { [] };
    (([_g] call BIS_fnc_gridToPos)) params [["_p", []], ["_sz", [0, 0]]];
    if ((count _p) < 2) exitWith { [] };
    private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2];
    [_c select 0, _c select 1, (getTerrainHeightASL _c) + 1]
};
// Le champ de grille de l'onglet TÂCHES est gardé avant chaque nouveau rendu (absent de l'écran : on garde la valeur connue).
private _saveGrid = { private _v = ["droneTaskGrid", "§"] call comspec_atak_native_fnc_formValue; if (_v isNotEqualTo "§") then { _s set ["droneTaskGridVal", _v]; }; };
// Qualité du flux (profil COMSPEC_ATAK_DroneQuality : 0 basse, 1 moyenne, 2 haute) : taille de la texture de rendu.
// Largeur et hauteur en puissances de 2 (576 ne l'est pas : texture jamais créée, image noire) ; le 16/9 vient de r2t.
private _camQ = { ((profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 1]) max 0) min 2 };
// Boîte englobante du drone dans le repère du modèle : [centre, demi-côtés].
private _camBox = {
    (boundingBoxReal _d) params ["_b0", "_b1"];
    [(_b0 vectorAdd _b1) vectorMultiply 0.5, (_b1 vectorDiff _b0) vectorMultiply 0.5]
};
// Exposition du flux. Une caméra rendue dans une texture ne s'adapte pas à l'obscurité comme la vue du joueur :
// en VISION JOUR, la nuit, l'image restait noire (retour de TTRD, 00:49, liaison 4/4 à 10 m). En JOUR, le gain suit
// la lumière autour du drone (correction de couleurs, setPiPEffect type 3) : normal le jour, éclairci en pénombre,
// fortement éclairci la nuit (« MODE NUIT » dans l'incrustation), comme le viseur de l'app Photo. NUIT et THERMIQUE
// gardent leur effet. Renvoie le niveau (0 jour, 1 pénombre, 2 nuit ; 0 hors VISION JOUR).
private _expose = {
    private _name = missionNamespace getVariable ["COMSPEC_ATAK_DroneR2T", "comspec_dronecam"];
    private _v = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
    private _lvl = 0;
    if (_v isEqualTo 0) then {
        private _lux = (getLightingAt ([player, _d] select (!isNull _d))) param [1, 0];
        _lvl = switch (true) do {
            case (sunOrMoon < 0.5 && {_lux < 15}): { 2 };
            case (sunOrMoon < 0.5 || {_lux < 40}): { 1 };
            default { 0 };
        };
    };
    if ([_name, _v, _lvl] isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DroneExp", []])) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneExp", [_name, _v, _lvl]];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamLvl", _lvl];
        _name setPiPEffect ([
            [_v],
            [3, 1, 1.6, 1.05, 0.01, [0, 0, 0, 0], [1, 1, 1, 0.9], [0.299, 0.587, 0.114, 0]],
            [3, 1, 3.2, 1.1, 0.03, [0, 0, 0, 0], [1, 1, 1, 0.55], [0.299, 0.587, 0.114, 0]]
        ] select _lvl);
    };
    _lvl
};
// Objet caméra sur une cible de rendu neuve (anneau de 8 noms par qualité, comspec_dronecam<qualité>_0..7 : mémoire
// vidéo bornée ; un même nom n'est jamais repris avec une autre taille de texture).
// Image noire au retour sur la page : l'ancienne caméra était coupée (Terminate) puis relancée sur la même cible,
// et l'image de la page était créée avant que la caméra rende de nouveau. Désormais la page appelle camBind, qui
// recrée la caméra et lance son rendu avant de poser l'image.
private _camMake = {
    private _n = ((missionNamespace getVariable ["COMSPEC_ATAK_DroneR2TN", 0]) + 1) mod 8;
    missionNamespace setVariable ["COMSPEC_ATAK_DroneR2TN", _n];
    private _name = format ["comspec_dronecam%1_%2", call _camQ, _n];
    private _bc = (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamBox", [[0, 0, 0], [0.3, 0.3, 0.15]]]) select 0;
    private _cam = "camera" camCreate (_d modelToWorld _bc);
    _cam cameraEffect ["Internal", "Back", _name];
    _cam camSetFov (0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]));
    _cam camCommit 0;
    _cam attachTo [_d, _bc];
    _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
    _cam setVariable ["rendering", true];
    _cam setVariable ["r2t", _name];
    _cam setVariable ["q", call _camQ];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", _cam];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneR2T", _name];
    call _expose;
    _cam
};
private _camOpen = {
    if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]) || {isNull _d}) exitWith {};
    // Nacelle posée hors du modèle, du côté où elle regarde (voir la boucle ci-dessous) : sur un Mavic, la caméra
    // posée sous la coque voyait le drone lui-même dès qu'on tournait la nacelle vers l'arrière.
    // Réglage par mission : COMSPEC_ATAK_DroneCamOffsets = HashMap classe → décalage fixe [x, y, z] du modèle.
    private _over = (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamOffsets", createHashMap]) getOrDefault [typeOf _d, []];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamOver", [[], _over] select (_over isEqualType [] && {(count _over) isEqualTo 3})];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamBox", call _camBox];
    // PiP coupée (Options > Vidéo > Qualité PiP) : journal et notification ; distance de vue PiP trop courte relevée.
    ["check", "DRONE"] call comspec_atak_native_fnc_pipCheck;
    // Caméra libérée puis rouverte (camPark) : on garde la vision choisie.
    if !(missionNamespace getVariable ["COMSPEC_ATAK_DroneCamParked", false]) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneVision", [0, 1] select (sunOrMoon < 0.3)];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamT0", time];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamParked", false];
    call _camMake;
    // Nacelle : chaque image, la caméra vise la cible choisie ou le point observé, sinon regarde devant et en bas.
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamPfh", [{ private _perfT = diag_tickTime; call {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (isNull _cam || {isNull _d}) exitWith {};
        // Page Drone à l'écran, ou vue drone sur la carte (à la place de la carte ou incrustée, aussi en miniature).
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _page = _st getOrDefault ["activePage", ""];
        private _show = (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])
            && {_page isEqualTo "DRONE" || {_page isEqualTo "MAP" && {(_st getOrDefault ["droneView", "OFF"]) isNotEqualTo "OFF"}}})
            // Vue directe (téléphone rangé) : la nacelle continue de tourner, la caméra plein écran prend sa pose.
            || {!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneDirectCam", objNull])};
        // Rendu coupé après 1,5 s hors de la page (un aller-retour rapide garde la même cible de rendu), caméra libérée
        // après 20 s (camPark, rouverte par la page au retour). Avant : 3 s et 60 s (retour de TTRD : le jeu rame).
        if (_show) then { _cam setVariable ["hiddenSince", -1]; } else {
            private _h0 = _cam getVariable ["hiddenSince", -1];
            if (_h0 < 0) then { _cam setVariable ["hiddenSince", diag_tickTime]; _h0 = diag_tickTime; };
            if (diag_tickTime - _h0 > 20) exitWith { ["camPark"] call comspec_atak_native_fnc_droneAction; };
            if (diag_tickTime - _h0 < 1.5) then { _show = true; };
        };
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) exitWith {};
        if (!_show) exitWith {
            if (_cam getVariable ["rendering", true]) then {
                _cam setVariable ["rendering", false];
                _cam cameraEffect ["Terminate", "Back", _cam getVariable ["r2t", "comspec_dronecam"]];
            };
        };
        // Photo demandée par le poste web (Overwatch connect, fn_webCmdUavPhoto) : elle relance cette caméra sur l'ancienne
        // cible fixe comspec_dronecam ; à la fin du cliché, caméra et cible neuves.
        private _web = missionNamespace getVariable ["COMSPEC_WebUavPhotoBusy", false];
        if (!_web && {_cam getVariable ["webBusy", false]}) then { _cam setVariable ["rendering", false]; };
        _cam setVariable ["webBusy", _web];
        // De retour à l'écran : la page appelle camBind (caméra et cible neuves). Si elle a été dessinée avant que la
        // coupure soit vue, on recrée la caméra ici et on redessine la page (une fois : camBind remet rendering).
        if !(_cam getVariable ["rendering", true]) exitWith {
            ["camBind"] call comspec_atak_native_fnc_droneAction;
            [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call CBA_fnc_execNextFrame;
        };
        if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_DroneExpAt", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneExpAt", diag_tickTime + 1];
            ["expose"] call comspec_atak_native_fnc_droneAction;
        };
        // Image figée (défaut TNT d'une liaison faible, fn_droneStatic) : la caméra reste où elle est, détachée du drone,
        // puis se rattache (point « off » oublié) à la fin du gel.
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedFreeze", 0])) exitWith {
            if (!isNull (attachedTo _cam)) then {
                private _fp = getPosASLVisual _cam;
                private _fd = vectorDirVisual _cam;
                private _fu = vectorUpVisual _cam;
                detach _cam;
                _cam setPosASL _fp;
                _cam setVectorDirAndUp [_fd, _fu];
                _cam setVariable ["off", nil];
            };
        };
        // Autre source du flux (sélecteur SOURCE de la vue caméra) : caméra du téléphone, tenue devant le joueur, ou caméra
        // casque (la sienne ou celle d'un équipier), au niveau des yeux et tournée comme la tête. Caméra détachée du drone.
        // Dans l'app Drone, toujours la caméra du drone (retour de TTRD) ; le choix de source sert à la vue drone de la carte.
        private _src = ["DRONE", missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"]] select (_page isEqualTo "MAP");
        if (_src isNotEqualTo "DRONE") exitWith {
            private _u = [player, missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", objNull]] select (_src isEqualTo "HELMET");
            if (isNull _u || {!alive _u}) exitWith {};
            if (!isNull (attachedTo _cam)) then { detach _cam; _cam setVariable ["off", nil]; };
            private _dir = vectorNormalized ([eyeDirection _u, getCameraViewDirection _u] select (_u isEqualTo player));
            private _eye = (eyePos _u) vectorAdd ([[0, 0, 0.1] vectorAdd (_dir vectorMultiply 0.12), [0, 0, -0.2] vectorAdd (_dir vectorMultiply 0.45)] select (_src isEqualTo "PHONE"));
            private _side = _dir vectorCrossProduct [0, 0, 1];
            if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
            _cam setPosASL _eye;
            _cam setVectorDirAndUp [_dir, (vectorNormalized _side) vectorCrossProduct _dir];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", _dir];
        };
        private _k = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamBox", [[0, 0, 0], [0.3, 0.3, 0.15]]]) params ["_bc", "_bh"];
        private _ctr = _d modelToWorldVisualWorld _bc;
        private _dir = [];
        if (_k isEqualTo "GIMBAL") then {
            // Nacelle pilotée à la souris (glisser sur l'image, flèches) : cap relatif au drone et site, lissés
            // (environ 0,15 s pour rejoindre la consigne), stabilisés dans le repère du monde.
            (missionNamespace getVariable ["COMSPEC_ATAK_DroneGimbal", [0, -37]]) params ["_tp", "_tt"];
            (missionNamespace getVariable ["COMSPEC_ATAK_DroneGimbalCur", [_tp, _tt]]) params ["_cp", "_ct"];
            private _a = 1 - exp (-14 * (diag_deltaTime min 0.1));
            private _dp = ((_tp - _cp + 540) mod 360) - 180;
            // Écart minuscule : on colle à la consigne (pas de micro-dérive de l'image à l'arrêt).
            _cp = if ((abs _dp) < 0.02) then { _tp } else { _cp + _dp * _a };
            _ct = if ((abs (_tt - _ct)) < 0.02) then { _tt } else { _ct + (_tt - _ct) * _a };
            missionNamespace setVariable ["COMSPEC_ATAK_DroneGimbalCur", [_cp, _ct]];
            private _h = (getDirVisual _d) + _cp;
            _dir = [(sin _h) * (cos _ct), (cos _h) * (cos _ct), sin _ct];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", _dir];
        } else {
            private _p = [];
            // Nadir : nacelle à la verticale, sous le drone.
            if (_k isEqualTo "NADIR") then { _p = _ctr vectorAdd [0, 0, -50]; };
            // Point cliqué sur l'image (vue drone) : la nacelle y reste pointée.
            if (_k isEqualTo "PT") then { _p = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamPt", []]; };
            // Cible marquée : suivie tant qu'elle est accrochée, sinon sa dernière position.
            if ((_k select [0, 5]) isEqualTo "LOCK:") then {
                private _e = (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]) getOrDefault [_k select [5], createHashMap];
                if ((count _e) > 0) then { _p = [_e get "pos", aimPos (_e get "obj")] select ((_e get "state") isEqualTo "TRACK" && {alive (_e get "obj")}); };
            };
            if (_k isNotEqualTo "" && {!(_k in ["NADIR", "PT"])} && {(_k select [0, 5]) isNotEqualTo "LOCK:"}) then {
                private _tr = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_k, []];
                if ((count _tr) > 0) then {
                    _tr params ["_o", "", "_pos", "_seen"];
                    // Détection vue à l'instant : la nacelle la suit ; sinon elle reste sur sa dernière position vue.
                    _p = [_pos, aimPos _o] select (alive _o && {time - _seen < 5});
                };
            };
            if (_p isEqualTo []) then {
                private _t = _d getVariable ["COMSPEC_DroneTask", []];
                if ((_t param [0, ""]) isEqualTo "OBSERVE") then { _p = _t select 1; };
            };
            if (_p isEqualTo []) then {
                // Nacelle au repos : devant, 37° vers le bas, stabilisée (tangage et roulis du drone hors de l'image).
                private _h = getDirVisual _d;
                _dir = [(sin _h) * 0.7986, (cos _h) * 0.7986, -0.6018];
                missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", []];
            } else {
                _dir = vectorNormalized (_p vectorDiff _ctr);
                missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", _dir];
            };
        };
        // Repère de l'image : droite horizontale ; à la verticale, le haut de l'image est l'avant du drone.
        private _side = _dir vectorCrossProduct [0, 0, 1];
        if ((vectorMagnitude _side) < 0.01) then { _side = (vectorDirVisual _d) vectorCrossProduct [0, 0, 1]; };
        if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
        private _up = (vectorNormalized _side) vectorCrossProduct _dir;
        private _mDir = _d vectorWorldToModelVisual _dir;
        private _mUp = _d vectorWorldToModelVisual _up;
        // Point de la nacelle : au-delà du plan d'appui de la boîte englobante dans la direction visée (plus 25 cm) :
        // tout le modèle, bras et hélices compris, reste derrière l'objectif, quelle que soit l'orientation.
        private _over = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamOver", []];
        private _off = if ((count _over) isEqualTo 3) then { _over } else {
            private _md = vectorNormalized _mDir;
            _bc vectorAdd (_md vectorMultiply (((_bh select 0) * abs (_md select 0)) + ((_bh select 1) * abs (_md select 1)) + ((_bh select 2) * abs (_md select 2)) + 0.25))
        };
        // Caméra attachée : position et orientation dans le repère du drone (rattachée seulement si le point bouge).
        if ((_off distance (_cam getVariable ["off", [1e6, 0, 0]])) > 0.01) then {
            _cam attachTo [_d, _off];
            _cam setVariable ["off", _off];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamOff", _off];
        };
        _cam setVectorDirAndUp [_mDir, _mUp];
        // Cliché en cours (fn_dronePhoto) : la caméra plein écran prend la même pose.
        private _shot = missionNamespace getVariable ["COMSPEC_ATAK_DroneShotCam", objNull];
        if (!isNull _shot) then { _shot attachTo [_d, _off]; _shot setVectorDirAndUp [_mDir, _mUp]; };
    }; ["nacelle (image)", _perfT] call comspec_atak_native_fnc_perfAcc; }, 0] call CBA_fnc_addPerFrameHandler];
};
private _camClose = {
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", _cam getVariable ["r2t", "comspec_dronecam"]]; camDestroy _cam; };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", objNull];
    [missionNamespace getVariable ["COMSPEC_ATAK_DroneCamPfh", -1]] call CBA_fnc_removePerFrameHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamPfh", -1];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", []];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamParked", false];
};
// Caméra prête à afficher (rouverte si elle avait été libérée) : recréée si son rendu a été coupé ou si la qualité a
// changé, rendu lancé avant que la page pose l'image. Renvoie la texture r2t du RscPicture ("" sans drone).
private _camBind = {
    if (isNull _d) exitWith { "" };
    call _camOpen;
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    if (isNull _cam) exitWith { "" };
    if (!(_cam getVariable ["rendering", false]) || {(_cam getVariable ["q", -1]) isNotEqualTo (call _camQ)}) then {
        _cam cameraEffect ["Terminate", "Back", _cam getVariable ["r2t", "comspec_dronecam"]];
        camDestroy _cam;
        _cam = call _camMake;
    };
    _cam setVariable ["hiddenSince", -1];
    ([[512, 256], [1024, 512], [2048, 1024]] select (call _camQ)) params ["_tw", "_th"];
    format ["#(argb,%1,%2,1)r2t(%3,1.7778)", _tw, _th, _cam getVariable ["r2t", "comspec_dronecam"]]
};
// Capteur : ce que la caméra voit (vue directe, portée réduite par le brouillard), en 360° ou dans le champ de la nacelle pointée.
private _scan = {
    private _eye = _d modelToWorldVisualWorld [0, 0.3, -0.4];
    private _k = 1 - 0.7 * fog;
    private _rMan = 450 * _k;
    private _rVeh = 700 * _k;
    // Nacelle pointée : capteur dans son champ ; flux pris sur une autre caméra (téléphone, casque) : en 360°.
    private _onMap = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP";
    private _cone = [[], missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []]] select (!_onMap || {(missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"]) isEqualTo "DRONE"});
    private _tracks = missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
    private _side = side group player;
    private _grp = units group player;
    private _seen = (_d nearEntities [["CAManBase", "LandVehicle", "Ship", "Air"], _rVeh]) select {
        _x isNotEqualTo _d && {alive _x} && {!(_x in _grp)} && {_x isNotEqualTo (vehicle player)}
        && {[({alive _x} count (crew _x)) > 0, (_x distance _d) < _rMan] select (_x isKindOf "CAManBase")}
        && {(_cone isEqualTo []) || {(_cone vectorCos ((aimPos _x) vectorDiff _eye)) > 0.77}}
        && {([_d, "VIEW", _x] checkVisibility [_eye, aimPos _x]) > 0.3}
    };
    {
        private _key = hashValue _x;
        private _tr = _tracks getOrDefault [_key, []];
        private _sx = side _x;
        private _tag = switch (true) do {
            case ([_side, _sx] call BIS_fnc_sideIsEnemy): { "ENNEMI" };
            case (_sx isNotEqualTo civilian && {[_side, _sx] call BIS_fnc_sideIsFriendly}): { "AMI" };
            default { "INCONNU" };
        };
        private _num = _tr param [7, -1];
        if (_num < 0) then { _num = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTrackN", 0]) + 1; missionNamespace setVariable ["COMSPEC_ATAK_DroneTrackN", _num]; };
        private _name = getText (configOf _x >> "displayName");
        _tracks set [_key, [_x, _name, getPosASL _x, time, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _tag, round (_x distance _d), _num, ""]];
        if ((count _tr) isEqualTo 0 && {_tag isNotEqualTo "AMI"}) then {
            [format ["Nouvelle détection %1 : %2 (%3) en %4, à %5 m du drone", [_num, 2] call CBA_fnc_formatNumber, _name, toLower _tag, [getPosASL _x, 8] call comspec_atak_native_fnc_gridRef, round (_x distance _d)], ["#f2ab33", "#e5483a"] select (_tag isEqualTo "ENNEMI")] call _log;
            if (_tag isEqualTo "ENNEMI") then { ["WARNING", format ["Drone : détection %1, %2 ennemi", [_num, 2] call CBA_fnc_formatNumber, _name], 4, 40] call comspec_atak_native_fnc_notify; };
        };
    } forEach _seen;
    // Évaluation des dégâts : une piste détruite n'est connue que si la caméra revoit sa position.
    {
        _y params ["_o", "_name", "_pos", "", "", "", "", "_num", "_state"];
        if (_state isEqualTo "" && {!alive _o} && {(_d distance _pos) < _rVeh} && {([_d, "VIEW", _o] checkVisibility [_eye, _pos vectorAdd [0, 0, 1]]) > 0.3}) then {
            _y set [8, "DÉTRUIT"];
            [format ["Détection %1 (%2) : détruite, constaté par la caméra", [_num, 2] call CBA_fnc_formatNumber, _name], "#5cc76b"] call _log;
        };
    } forEach _tracks;
    // 30 pistes au plus : les plus anciennes sortent.
    while { (count _tracks) > 30 } do {
        private _old = (keys _tracks) apply { [(_tracks get _x) select 3, _x] };
        _old sort true;
        _tracks deleteAt ((_old select 0) select 1);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneTracks", _tracks];
    // Remontée au poste (couche « contacts vus par drone » de la carte TOC) : pistes ennemies ou inconnues nouvelles,
    // déplacées de plus de 50 m ou constatées détruites (BDA). Toutes les 5 s au plus, 6 pistes par passage.
    if (diag_tickTime >= (uiNamespace getVariable ["COMSPEC_ATAK_DroneTrkTx", 0]) && {[] call comspec_atak_native_fnc_bridge}) then {
        uiNamespace setVariable ["COMSPEC_ATAK_DroneTrkTx", diag_tickTime + 5];
        private _sent = missionNamespace getVariable ["COMSPEC_ATAK_DroneTrkSent", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneTrkSent", _sent];
        private _n = 0;
        private _me = [player] call comspec_atak_native_fnc_unitCallsign;
        {
            _y params ["_o", "_name", "_pos", "_seen", "", "_tag", "", "_num", "_state"];
            private _bda = _state isEqualTo "DÉTRUIT";
            private _prev = _sent getOrDefault [_x, []];
            if (_n >= 6) exitWith {};
            if (_tag isEqualTo "AMI" || {!_bda && {time - _seen > 10}}) then { continue; };
            // Déjà envoyée : BDA envoyé une fois, piste vivante renvoyée seulement si elle a bougé.
            if ((count _prev) > 0 && {(_prev select 0) || {!_bda && {((_prev select 1) distance2D _pos) < 50}}}) then { continue; };
            _sent set [_x, [_bda, _pos]];
            _n = _n + 1;
            private _type = switch (true) do { case (isNull _o): { "UNKNOWN" }; case (_o isKindOf "CAManBase"): { "INFANTRY" }; case (_o isKindOf "Air"): { "AIR" }; default { "VEHICLE" }; };
            private _pay = createHashMapFromArray [
                ["t", ["obs", "bda"] select _bda], ["track_id", "DRN-" + (_x select [0, 12])], ["source", "drone"],
                ["label", format ["Drone %1 · %2", [_num, 2] call CBA_fnc_formatNumber, _name]], ["track_type", _type],
                ["affiliation", ["UNKNOWN", "HOSTILE"] select (_tag isEqualTo "ENNEMI")], ["x", round (_pos select 0)], ["y", round (_pos select 1)],
                ["call_sign", _me], ["mission_key", missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""]]
            ];
            if (_bda) then { _pay set ["assessment", "DESTROYED"]; };
            "COMSPECExtension" callExtension ["EmitTelemetry", [["2", "1"] select _bda, ["obs", "bda"] select _bda, "drntrk:" + _x, [_pay] call comspec_atak_native_fnc_json]];
        } forEach _tracks;
        if ((count _sent) > 60) then { { if !(_x in _tracks) then { _sent deleteAt _x; }; } forEach (keys _sent); };
    };
};
// Reprise : renvoie le dernier ordre de vol en mode forcé (IA remise d'aplomb là où le drone est local, équipage recollé par le serveur).
private _recover = {
    params [["_auto", false], ["_why", ""]];
    private _o = missionNamespace getVariable ["COMSPEC_ATAK_DroneOrder", []];
    private _cmd = _o param [0, "hover"];
    private _args = _o param [1, []];
    // Unité suivie disparue : stationnaire.
    if (_cmd in ["follow", "escort", "home"] && {private _t = _args param [0, objNull]; !(_t isEqualType objNull) || {!alive _t}}) then { _cmd = "hover"; _args = []; };
    if (_cmd isNotEqualTo "manual" && {(_d getVariable ["COMSPEC_DroneLock", ""]) isNotEqualTo ""}) then { _d setVariable ["COMSPEC_DroneLock", "", true]; };
    ["comspec_atak_native_droneCmd", [_d, "force", [_cmd, _args]], _d] call CBA_fnc_targetEvent;
    ["comspec_atak_native_droneCmd", [_d, "relocal", [_cmd, _args]]] call CBA_fnc_serverEvent;
    missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", [_cmd, _args, time, _d getVariable ["COMSPEC_DroneAckN", 0], false, _auto || {_o param [5, false]}, -1, 1e9, time]];
    [format ["%1 : ordre « %2 » renvoyé, IA de vol remise d'aplomb%3", ["Exécution forcée", "Drone bloqué, reprise automatique"] select _auto,
        [_expect getOrDefault [_cmd, "HOVER"]] call _modeName, ["", format [" (%1)", _why]] select (_why isNotEqualTo "")], "#f2ab33"] call _log;
    if (_auto) then { ["WARNING", "Drone bloqué : ordre renvoyé automatiquement"] call _say; };
};
// Surveillance du dernier ordre (chaque seconde, liaison établie) : reçu par le drone, puis progrès vers son but.
private _watch = {
    private _o = missionNamespace getVariable ["COMSPEC_ATAK_DroneOrder", []];
    if ((count _o) < 9 || {_o select 5}) exitWith {};
    _o params ["_cmd", "", "_t0", "_n0", "_ack", "", "_key", "_best", "_bestT"];
    private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
    // Ordre sans effet attendu sur un drone posé (retour, atterrissage, veille) : rien à surveiller.
    if (((getPosATL _d) select 2) < 1 && {_cmd in ["home", "land", "standby"]}) exitWith { _o set [5, true]; };
    // Reçu : le drone a traité un ordre depuis l'envoi (compteur public COMSPEC_DroneAckN), ou affiche déjà le mode attendu.
    if (!_ack && {(_d getVariable ["COMSPEC_DroneAckN", 0]) isNotEqualTo _n0 || {_m isEqualTo (_expect getOrDefault [_cmd, ""])}}) then { _ack = true; _o set [4, true]; _o set [8, time]; _bestT = time; };
    if (!_ack) exitWith { if ((time - _t0) > 10) then { [true, "ordre resté sans effet 10 s"] call _recover; }; };
    // But du mode en cours : point, étape de route, unité suivie, point de décollage, centre d'orbite, cible ; et rayon d'arrivée.
    private _cqb = _d getVariable ["COMSPEC_DroneCqb", false];
    private _task = _d getVariable ["COMSPEC_DroneTask", []];
    private _f = _d getVariable ["COMSPEC_DroneFollow", objNull];
    private _route = _d getVariable ["COMSPEC_DroneRoute", []];
    private _goal = switch (_m) do {
        case "GOTO": { [_task param [1, []], [25, 5] select _cqb] };
        case "ROUTE": { [(_route param [0, []]) param [_route param [2, 0], []], [30, 3] select _cqb] };
        case "HOME": { [[[], getPosASL _f] select (alive _f), [30, 6] select _cqb] };
        case "FOLLOW";
        case "ESCORT": { [[[], getPosASL _f] select (alive _f), [60, 15] select _cqb] };
        case "RTH": { [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", []], 20] };
        case "LOITER";
        case "OBSERVE": { [_task param [1, []], (_task param [2, 150]) + 80] };
        case "STRIKE": { [_d getVariable ["COMSPEC_DroneTgt", []], 130] };
        default { [[], 0] };
    };
    _goal params ["_gp", "_arr"];
    private _k = [_m, _route param [2, 0]];
    if (_k isNotEqualTo _key) then { _o set [6, _k]; _o set [7, 1e9]; _o set [8, time]; _best = 1e9; _bestT = time; };
    if ((count _gp) < 2) exitWith { _o set [8, time]; };
    private _dist = _d distance2D _gp;
    // Arrivé, en progrès d'au moins 3 m, ou en route derrière une unité qui roule : pas de blocage.
    if (_dist < _arr || {_dist < (_best - 3)} || {_m in ["FOLLOW", "ESCORT", "HOME"] && {(speed _d) > 10}}) exitWith { _o set [7, _dist min _best]; _o set [8, time]; };
    if ((time - _bestT) > 10) then { [true, format ["aucun progrès vers le but depuis 10 s, à %1 m", round _dist]] call _recover; };
};
// Prise en main d'un drone (appairage au pied, ou transfert reçu) : état du téléphone et surveillance chaque seconde.
private _adopt = {
    params ["_new", ["_home", []]];
    _new setVariable ["COMSPEC_DroneOwner", getPlayerUID player, true];
    _new setVariable ["COMSPEC_DroneLock", "", true];
    _new setVariable ["COMSPEC_DroneSide", side group player, true];
    missionNamespace setVariable ["COMSPEC_ATAK_Drone", _new];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneHome", [_home, getPosASL _new] select ((count _home) < 3)];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", ""];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 0];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneEvtN", (_new getVariable ["COMSPEC_DroneEvt", [0]]) select 0];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLinked", true];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", []];
    if ((missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]) < 0) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", [{ private _t = diag_tickTime; ["tick"] call comspec_atak_native_fnc_droneAction; ["drone suivi", _t] call comspec_atak_native_fnc_perfAcc; _t = diag_tickTime; ["tick"] call comspec_atak_native_fnc_droneFleet; ["flotte suivi", _t] call comspec_atak_native_fnc_perfAcc; }, 1] call CBA_fnc_addPerFrameHandler];
    };
};
switch (_act) do {
    case "link": { call _link };
    case "log": { [_arg, _this param [2, ""]] call _log; };
    case "pair": {
        private _new = _arg;
        if (isNull _new || {!alive _new} || {(player distance _new) > 15}) exitWith { ["WARNING", "Posez le drone à vos pieds pour l'appairer"] call _say; };
        private _owner = _new getVariable ["COMSPEC_DroneOwner", ""];
        if (_owner isNotEqualTo "" && {_owner isNotEqualTo getPlayerUID player}) exitWith { ["WARNING", "Ce drone est déjà appairé à un autre téléphone"] call _say; };
        // Flotte : un drone déjà appairé devient le drone piloté ; un nouveau drone s'ajoute, celui d'avant continue seul.
        if (_new in (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []])) exitWith { ["switch", _new] call comspec_atak_native_fnc_droneFleet; };
        if (_new isEqualTo _d) exitWith { call _render; };
        if (!isNull _d && {alive _d}) then {
            if ((count (["list"] call comspec_atak_native_fnc_droneFleet)) >= 4) exitWith { ["WARNING", "Flotte complète : 4 drones au plus par téléphone"] call _say; _new = objNull; };
            ["keep", _d] call comspec_atak_native_fnc_droneFleet;
            call _camClose;
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
            [format ["Flotte : %1 continue seul, nouveau drone appairé", [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd], "#4da3ff"] call _log;
        };
        if (isNull _new) exitWith {};
        _new setVariable ["COMSPEC_DroneAlt", _s getOrDefault ["droneAlt", 40], true];
        _new setVariable ["COMSPEC_DroneSpd", _s getOrDefault ["droneSpd", 40], true];
        [_new] call _adopt;
        private _nm = [_new, [], "NAME"] call comspec_atak_native_fnc_droneOsd;
        ["SUCCESS", format ["Drone appairé : %1", _nm]] call _say;
        [format ["Appairage : %1, point de décollage en %2", _nm, [getPosASL _new, 8] call comspec_atak_native_fnc_gridRef], "#5cc76b"] call _log;
        call _render;
    };
    case "unpair": {
        if (!isNull _d) then {
            if (alive _d) then { ["Désappairage du drone"] call _log; };
            _d setVariable ["COMSPEC_DroneOwner", "", true]; _d setVariable ["COMSPEC_DroneLock", nil, true];
        };
        call _camClose;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", []];
        // Flotte : le drone suivant devient le drone piloté (la surveillance continue).
        ["promote"] call comspec_atak_native_fnc_droneFleet;
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) then {
            [missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", -1];
        };
        call _render;
    };
    // Flotte (fn_droneFleet) : ce drone devient le drone piloté ; celui d'avant, vivant, reste dans la flotte et continue seul.
    case "fleetAdopt": {
        private _new = _arg;
        if (isNull _new || {!alive _new}) exitWith {};
        call _camClose;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        ["stop", "", true] call comspec_atak_native_fnc_droneSeq;
        if (!isNull _d && {alive _d} && {_d isNotEqualTo _new}) then { ["keep", _d] call comspec_atak_native_fnc_droneFleet; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFleet", (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []]) - [_new]];
        _s set ["droneAlt", _new getVariable ["COMSPEC_DroneAlt", 40]];
        _s set ["droneSpd", _new getVariable ["COMSPEC_DroneSpd", 40]];
        [_new, _new getVariable ["COMSPEC_DroneHomePos", []]] call _adopt;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", _new getVariable ["COMSPEC_DroneMode", ""]];
        call _render;
    };
    case "mode": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        switch (_arg) do {
            case "hover": { ["hover"] call _send; };
            case "me": { ["follow", [player]] call _send; };
            case "player": {
                private _t = [_s getOrDefault ["droneFollowUid", ""]] call _unitOf;
                if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Choisissez d'abord le joueur à suivre"] call _say; };
                ["follow", [_t]] call _send;
            };
            case "home": { ["home", [player]] call _send; };
            case "land": { ["land"] call _send; };
            case "hunt": { ["hunt", [_s getOrDefault ["droneHuntRad", 250], player]] call _send; };
        };
        if (_arg isEqualTo "hunt") then { [format ["Ordre : recherche, rayon %1 m", _s getOrDefault ["droneHuntRad", 250]]] call _log; };
        call _render;
    };
    // Touches rapides (options CBA) : RECHERCHE, ME SUIVRE, RTB, ATTERRIR, avec une confirmation à l'écran.
    case "quick": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        if (_arg isEqualTo "rth") then {
            ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
            ["Ordre (touche) : RTB, retour au point de décollage"] call _log;
            call _render;
        } else {
            ["mode", _arg] call comspec_atak_native_fnc_droneAction;
            if (_arg in ["me", "land"]) then { [format ["Ordre (touche) : %1", ["atterrissage sur place", "me suivre"] select (_arg isEqualTo "me")]] call _log; };
        };
        ["INFO", format ["Drone : %1", createHashMapFromArray [["hunt", "RECHERCHE"], ["me", "ME SUIVRE"], ["rth", "RTB"], ["land", "ATTERRIR"]] getOrDefault [_arg, _arg]], 2, 20] call comspec_atak_native_fnc_notify;
    };
    case "takeoff": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        ["takeoff"] call _send;
        [format ["Ordre : démarrage et décollage, %1 m", _s getOrDefault ["droneAlt", 40]]] call _log;
        call _render;
    };
    case "cqb": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _on = !(_d getVariable ["COMSPEC_DroneCqb", false]);
        _s set ["droneAlt", [40, 3] select _on];
        _s set ["droneSpd", [40, 10] select _on];
        ["cqb", [_on, _s get "droneAlt", _s get "droneSpd"]] call _send;
        if (((getPosATL _d) select 2) > 1) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", ["hover", [], time, -1, true, false, -1, 1e9, time]]; };
        [["Profil CQB quitté : 40 m, 40 km/h", "Profil CQB : vol bas à 3 m, 10 km/h, arrêt devant obstacle"] select _on, "#f2ab33"] call _log;
        call _render;
    };
    case "manual": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        _d setVariable ["COMSPEC_DroneLock", getPlayerUID player, true];
        ["manual"] call _send;
        ["INFO", "Pilotage manuel : connectez votre terminal UAV. Un ordre du téléphone reprend la main."] call _say;
        call _render;
    };
    case "player": { _s set ["droneFollowUid", _arg]; call _render; };
    case "alt": { _s set ["droneAlt", _arg]; if (!isNull _d && {call _needLink}) then { ["alt", [_arg]] call _send; }; call _render; };
    case "speed": { _s set ["droneSpd", _arg]; if (!isNull _d && {call _needLink}) then { ["speed", [_arg]] call _send; }; call _render; };
    case "radius": { _s set ["droneHuntRad", _arg]; call _render; };
    case "arm": {
        if (isNull _d) exitWith {};
        if ((player distance _d) > 5) exitWith { ["WARNING", "Le drone doit être à moins de 5 m pour fixer une charge"] call _say; };
        if !(missionNamespace getVariable ["comspec_atak_native_drone_strike", true]) exitWith { ["WARNING", "Drones armés interdits sur ce serveur"] call _say; };
        private _mags = magazines player;
        private _i = _payloads findIf { (_x select 0) isEqualTo _arg && {(_x select 0) in _mags} && {isClass (configFile >> "CfgAmmo" >> (_x select 1))} };
        if (_i < 0) then { _i = _payloads findIf { ((_x select 0) in _mags) && {isClass (configFile >> "CfgAmmo" >> (_x select 1))} }; };
        if (_i < 0) exitWith { ["WARNING", "Aucune charge dans l'inventaire (roquette RPG, charge de démolition ou grenade)"] call _say; };
        (_payloads select _i) params ["_mag", "_ammo", "_label"];
        player removeMagazine _mag;
        _d setVariable ["COMSPEC_DroneArmed", _ammo, true];
        _d setVariable ["COMSPEC_DroneArmedLabel", _label, true];
        _d setVariable ["COMSPEC_DroneArmedMag", _mag, true];
        ["SUCCESS", format ["Drone armé : %1", _label]] call _say;
        [format ["Armement : %1 fixée", _label], "#f2ab33"] call _log;
        call _render;
    };
    case "payloads": {
        private _mags = magazines player;
        (_payloads select { ((_x select 0) in _mags) && {isClass (configFile >> "CfgAmmo" >> (_x select 1))} }) apply {
            private _m = _x select 0;
            [_m, _x select 2, { _x isEqualTo _m } count _mags]
        }
    };
    case "disarm": {
        if (isNull _d || {(_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo ""}) exitWith {};
        if ((player distance _d) > 5) exitWith { ["WARNING", "Le drone doit être à moins de 5 m pour retirer la charge"] call _say; };
        if ((_d getVariable ["COMSPEC_DroneMode", "HOVER"]) isEqualTo "STRIKE") exitWith { ["WARNING", "Frappe en cours : impossible de désarmer"] call _say; };
        private _mag = _d getVariable ["COMSPEC_DroneArmedMag", ""];
        private _label = _d getVariable ["COMSPEC_DroneArmedLabel", "charge"];
        if (_mag isNotEqualTo "") then { if (player canAdd _mag) then { player addMagazine _mag; } else { (createVehicle ["GroundWeaponHolder", getPosATL player, [], 0, "CAN_COLLIDE"]) addMagazineCargoGlobal [_mag, 1]; }; };
        _d setVariable ["COMSPEC_DroneArmed", "", true];
        _d setVariable ["COMSPEC_DroneArmedLabel", nil, true];
        _d setVariable ["COMSPEC_DroneArmedMag", nil, true];
        ["INFO", format ["Charge retirée : %1", _label]] call _say;
        [format ["Désarmement : %1 retirée", _label], "#f2ab33"] call _log;
        call _render;
    };
    case "strike": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        if ((_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo "") exitWith { ["WARNING", "Drone non armé"] call _say; };
        private _trk = [];
        private _tgt = switch (true) do {
            case (_arg isEqualTo "LASER"): {
                private _l = ((units group player) apply { laserTarget _x }) select { !isNull _x };
                if ((count _l) > 0) then { _l select 0 } else { objNull }
            };
            case ((_arg find "TRK:") isEqualTo 0): {
                // Piste capteur : verrouillée si la caméra la voit encore (moins de 10 s), sinon sa dernière position vue.
                _trk = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_arg select [4], []];
                if ((count _trk) isEqualTo 0) then { [] } else {
                    _trk params ["_o", "", "_pos", "_seen"];
                    [_pos, _o] select (alive _o && {time - _seen < 10})
                }
            };
            default { [["droneGrid", ""] call comspec_atak_native_fnc_formValue] call _gridPos };
        };
        if ((_tgt isEqualType objNull && {isNull _tgt}) || {_tgt isEqualTo []}) exitWith {
            ["WARNING", switch (true) do { case (_arg isEqualTo "LASER"): { "Aucun laser allumé dans votre groupe" }; case ((_arg find "TRK:") isEqualTo 0): { "Détection inconnue" }; default { "Grille invalide (6, 8 ou 10 chiffres)" }; }] call _say;
        };
        // Second appui dans les 5 s pour confirmer : pas de frappe partie par erreur.
        private _c = _s getOrDefault ["droneConfirm", ["", 0]];
        if ((_c select 0) isNotEqualTo _arg || {diag_tickTime - (_c select 1) > 5}) exitWith {
            _s set ["droneConfirm", [_arg, diag_tickTime]];
            ["WARNING", "Appuyez encore pour confirmer la frappe"] call _say;
            call _render;
        };
        _s set ["droneConfirm", ["", 0]];
        ["strike", [_tgt, player]] call _send;
        private _what = switch (true) do {
            case ((count _trk) > 0): { format ["la détection %1 (%2) en %3", [_trk select 7, 2] call CBA_fnc_formatNumber, _trk select 1, [_trk select 2, 8] call comspec_atak_native_fnc_gridRef] };
            case (_tgt isEqualType objNull): { format ["le point laser (%1)", [_tgt, 8] call comspec_atak_native_fnc_gridRef] };
            default { format ["la grille %1", [_tgt, 8] call comspec_atak_native_fnc_gridRef] };
        };
        ["WARNING", format ["Frappe lancée sur %1", _what]] call _say;
        [format ["Frappe lancée sur %1 (%2)", _what, _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]], "#e5483a"] call _log;
        call _render;
    };
    case "cam": {
        if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) then { call _camClose; } else { call _camOpen; };
        call _render;
    };
    case "zoom": {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneZoom", _arg];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (!isNull _cam) then { _cam camSetFov (0.7 / _arg); _cam camCommit 0; };
        call _render;
    };
    case "gimbalGet": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""]) isEqualTo "GIMBAL") exitWith { missionNamespace getVariable ["COMSPEC_ATAK_DroneGimbal", [0, -37]] };
        if (isNull _d) exitWith { [0, -37] };
        private _dir = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []];
        // Nacelle au repos (aucune cible) : devant, 37° vers le bas.
        if (_dir isEqualTo []) exitWith { [0, -37] };
        _dir = vectorNormalized _dir;
        private _pan = ((((_dir select 0) atan2 (_dir select 1)) - getDirVisual _d + 540) mod 360) - 180;
        [_pan, asin ((_dir select 2) max -1 min 1)]
    };
    case "gimbal": {
        if (isNull _d) exitWith {};
        _arg params [["_pan", 0], ["_tilt", -37]];
        _pan = ((_pan + 540) mod 360) - 180;
        _tilt = -90 max (_tilt min 20);
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""]) isNotEqualTo "GIMBAL") then {
            // Départ en douceur depuis l'orientation actuelle de la nacelle.
            missionNamespace setVariable ["COMSPEC_ATAK_DroneGimbalCur", ["gimbalGet"] call comspec_atak_native_fnc_droneAction];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", "GIMBAL"];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneGimbal", [_pan, _tilt]];
    };
    case "gimbalNudge": {
        (["gimbalGet"] call comspec_atak_native_fnc_droneAction) params ["_p0", "_t0"];
        _arg params [["_dp", 0], ["_dt", 0]];
        ["gimbal", [_p0 + _dp, _t0 + _dt]] call comspec_atak_native_fnc_droneAction;
    };
    case "recenter": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""]) isEqualTo "GIMBAL") then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneGimbal", [0, -37]];
        } else {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
            call _render;
        };
    };
    case "zoomBy": {
        private _z = (round (10 * (((missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) * _arg) max 1 min 8))) / 10;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneZoom", _z];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (!isNull _cam) then { _cam camSetFov (0.7 / _z); _cam camCommit 0.15; };
        _z
    };
    // Ligne de la vue caméra : angles de la nacelle, zoom, mode marquage.
    case "gimbalHud": {
        (uiNamespace getVariable ["COMSPEC_ATAK_DroneGimTxt", [controlNull, ""]]) params [["_c", controlNull], ["_last", ""]];
        if (isNull _c) exitWith {};
        (["gimbalGet"] call comspec_atak_native_fnc_droneAction) params ["_gp", "_gt"];
        private _mk = uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false];
        private _t = format ["<t size='0.75' align='center' font='EtelkaMonospacePro' shadow='2' shadowColor='#000000' color='%1'>CAMÉRA CAP %2%3° · SITE %4° · x%5%6</t>",
            ["#e8f0ea", "#ff8a7a"] select _mk, ["", "+"] select (_gp > 0.5), round _gp, round _gt, missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1],
            ["", "<br/>MARQUAGE : CLIQUEZ LA CIBLE"] select _mk];
        if (_t isNotEqualTo _last) then { _c ctrlSetStructuredText parseText ((_t) call comspec_atak_native_fnc_tr); uiNamespace setVariable ["COMSPEC_ATAK_DroneGimTxt", [_c, _t]]; };
    };
    case "markMode": {
        uiNamespace setVariable ["COMSPEC_ATAK_DroneMarkMode", !(uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false])];
        call _render;
    };
    case "imgClick": {
        if !(["click", _arg] call comspec_atak_native_fnc_droneFlir) exitWith {};
        // Mode marquage : la cible sous le clic est marquée tout de suite, sur le rayon du clic (fn_droneFlir le garde
        // dans COMSPEC_ATAK_DroneClickRay) : plus besoin d'attendre que la nacelle ait tourné vers le point.
        if (uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false]) then {
            if (isNull _d) exitWith {};
            ["mark", "DRONE", objNull, uiNamespace getVariable ["COMSPEC_ATAK_DroneClickRay", []]] call comspec_atak_native_fnc_lockAction;
            call _render;
        };
    };
    case "vision": {
        // Jour → nuit (intensification) → thermique → jour.
        private _v = ((missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]) + 1) mod 3;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneVision", _v];
        call _expose;
        call _render;
    };
    case "camBind": { call _camBind };
    // Gain du flux selon la lumière (boucle de la nacelle, une fois par seconde).
    case "expose": { call _expose };
    // Source du flux de la vue caméra : drone, caméra du téléphone, caméra casque (la sienne ou celle d'un équipier).
    case "feedSrc": {
        if !(_arg in ["DRONE", "PHONE", "HELMET"]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedSrc", _arg];
        if (_arg isEqualTo "HELMET" && {!alive (missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", objNull])}) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedUnit", player]; };
        call _render;
        call _mapRender;
    };
    case "feedUnit": {
        private _u = [_arg] call _unitOf;
        if (isNull _u) then { _u = player; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedUnit", _u];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedSrc", "HELMET"];
        call _render;
        call _mapRender;
    };
    case "quality": {
        profileNamespace setVariable ["COMSPEC_ATAK_DroneQuality", ((_arg max 0) min 2)];
        saveProfileNamespace;
        ["INFO", format ["Flux du drone : qualité %1", ["basse (512 px, plus fluide)", "moyenne (1024 px)", "haute (2048 px, plus lourde)"] select ((_arg max 0) min 2)]] call _say;
        call _render;
    };
    // Ordre de vol du suivi des cibles marquées (fn_droneSeq) : même chemin que les boutons, sans arrêter ce suivi.
    case "sendRaw": {
        if (isNull _d || {!(call _needLink)}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneSeqSending", true];
        _arg call _send;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneSeqSending", false];
        true
    };
    case "mapSub": { _s set ["droneMapSub", _arg]; _s set ["droneMapPts", []]; call _mapRender; };
    case "routeMode": { _s set ["droneRouteMode", _arg]; call _mapRender; };
    case "zoneKind": { _s set ["droneZoneKind", _arg]; _s set ["droneMapPts", []]; call _mapRender; };
    case "mapClear": { _s set ["droneMapPts", []]; call _mapRender; };
    case "mapClick": {
        private _p = [_arg select 0, _arg select 1, getTerrainHeightASL [_arg select 0, _arg select 1]];
        private _pts = _s getOrDefault ["droneMapPts", []];
        switch (_s getOrDefault ["droneMapSub", "GOTO"]) do {
            case "ROUTE": {
                if ((count _pts) >= 12) exitWith { ["WARNING", "Route : 12 points au plus"] call _say; };
                _pts pushBack _p;
                _s set ["droneMapPts", _pts];
            };
            case "ZONE": {
                private _kind = _s getOrDefault ["droneZoneKind", "LOITER"];
                if ((count _pts) isEqualTo 0) exitWith { _s set ["droneMapPts", [_p]]; };
                private _a = _pts select 0;
                _s set ["droneMapPts", []];
                if (isNull _d || {!(call _needLink)}) exitWith {};
                if (_kind isEqualTo "HUNT") then {
                    // Zone de recherche rectangulaire : deux coins opposés.
                    private _c = [((_a select 0) + (_p select 0)) / 2, ((_a select 1) + (_p select 1)) / 2];
                    _c pushBack (getTerrainHeightASL _c);
                    private _hx = (abs ((_p select 0) - (_a select 0)) / 2) max 25;
                    private _hy = (abs ((_p select 1) - (_a select 1)) / 2) max 25;
                    ["hunt", [((_hx min _hy) * 0.7) max 50, player, _c, [_c, _hx, _hy, 0, true]]] call _send;
                    [format ["Ordre : recherche sur zone %1 × %2 m en %3", round (_hx * 2), round (_hy * 2), [_c, 8] call comspec_atak_native_fnc_gridRef]] call _log;
                } else {
                    private _r = ((_a distance2D _p) max 30) min 800;
                    [["loiter", "observe"] select (_kind isEqualTo "OBSERVE"), [_a, round _r]] call _send;
                    if (_kind isEqualTo "OBSERVE") then { missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""]; };
                    [format ["Ordre : %1 en %2, rayon %3 m (carte)", ["orbite", "observer"] select (_kind isEqualTo "OBSERVE"), [_a, 8] call comspec_atak_native_fnc_gridRef, round _r]] call _log;
                };
            };
            default {
                if (isNull _d || {!(call _needLink)}) exitWith {};
                ["goto", [_p]] call _send;
                [format ["Ordre : aller en %1 (carte)", [_p, 8] call comspec_atak_native_fnc_gridRef]] call _log;
            };
        };
        call _mapRender;
    };
    case "routeSend": {
        private _pts = _s getOrDefault ["droneMapPts", []];
        if ((count _pts) isEqualTo 0) exitWith { ["WARNING", "Route vide : touchez la carte pour poser des points"] call _say; };
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _rm = _s getOrDefault ["droneRouteMode", "ONCE"];
        ["route", [+_pts, _rm]] call _send;
        [format ["Ordre : route de %1 points, %2", count _pts, (createHashMapFromArray [["ONCE", "une fois"], ["LOOP", "en boucle"], ["PINGPONG", "en aller-retour"]]) get _rm]] call _log;
        _s set ["droneMapPts", []];
        call _mapRender;
    };
    case "mapBar": {
        // Barre DRONE posée sur la carte : état, sous-outils, mode de route, validation.
        disableSerialization;
        _arg params ["_x0", "_y0", "_w"];
        if (isNull _d) exitWith {};
        private _fs = ([] call comspec_atak_native_fnc_layoutGet) get "fontSmall";
        private _pad = (([] call comspec_atak_native_fnc_layoutGet) get "pad") * 2;
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
        private _ln = call _link;
        private _sub = _s getOrDefault ["droneMapSub", "GOTO"];
        private _pts = _s getOrDefault ["droneMapPts", []];
        private _rh = _fs * 1.5;
        private _rows = [];
        // [libellé, ordre, actif, aide] : libellés en clair, détail en infobulle.
        _rows pushBack ([["ALLER ICI", "GOTO", "Touchez la carte : le drone y va, puis reste en stationnaire"], ["TRACER UNE ROUTE", "ROUTE", "Touchez la carte point par point (12 au plus), puis ENVOYER LA ROUTE"], ["ZONE", "ZONE", "Touchez le centre puis le bord (ou deux coins) : orbite, observation ou recherche"]] apply { [_x select 0, ["mapSub", _x select 1], _sub isEqualTo (_x select 1), _x select 2] });
        switch (_sub) do {
            case "ROUTE": {
                private _rm = _s getOrDefault ["droneRouteMode", "ONCE"];
                _rows pushBack ([["UNE FOIS", "ONCE", "Parcourt la route une fois puis s'arrête"], ["EN BOUCLE", "LOOP", "Recommence la route sans fin"], ["ALLER-RETOUR", "PINGPONG", "Parcourt la route dans un sens puis dans l'autre"]] apply { [_x select 0, ["routeMode", _x select 1], _rm isEqualTo (_x select 1), _x select 2] });
                _rows pushBack [[format ["ENVOYER LA ROUTE (%1 POINTS)", count _pts], ["routeSend"], true, "Envoie la route tracée au drone"], ["EFFACER LE TRACÉ", ["mapClear"], false, "Efface les points posés"]];
            };
            case "ZONE": {
                private _zk = _s getOrDefault ["droneZoneKind", "LOITER"];
                _rows pushBack ([["TOURNER AUTOUR", "LOITER", "Le drone tourne autour du point"], ["OBSERVER", "OBSERVE", "Le drone tourne autour du point, caméra fixée dessus"], ["CHERCHER DES ENNEMIS", "HUNT", "Le drone fouille la zone et signale le premier ennemi vu"]] apply { [_x select 0, ["zoneKind", _x select 1], _zk isEqualTo (_x select 1), _x select 2] });
            };
        };
        _rows pushBack [["S'ARRÊTER ICI", ["mode", "hover"], false, "Le drone s'arrête et reste en vol stationnaire"], ["REVENIR À MOI", ["mode", "home"], false, "Le drone revient au-dessus de vous"]];
        private _h = _rh * 1.1 + (count _rows) * (_rh + _pad / 3) + _pad / 2;
        private _bg = ["COMSPEC_RscMapPanel", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.92];
        private _st = ["COMSPEC_RscStructuredText", [_x0 + _pad / 2, _y0 + _pad / 4, _w - _pad, _rh * 1.1]] call _mk;
        _st ctrlSetStructuredText parseText ((format ["<t size='0.85'><t font='COMSPEC_BarlowBold' color='#5cc76b'>DRONE</t>  %1 · %2 · H %3 m%4</t>",
            [_d getVariable ["COMSPEC_DroneMode", "HOVER"]] call _modeName,
            ["<t color='#e5483a'>hors liaison</t>", format ["liaison %1/4", _ln select 0]] select (_ln select 2),
            round ((getPosATL _d) select 2), ["", " · CQB"] select (_d getVariable ["COMSPEC_DroneCqb", false])]) call comspec_atak_native_fnc_tr);
        private _y = _y0 + _rh * 1.1 + _pad / 4;
        {
            private _row = _x;
            private _bw = (_w - _pad) / (count _row);
            {
                _x params ["_lbl", "_a", "_on", ["_tip", ""]];
                private _b = ["COMSPEC_RscButton", [_x0 + _pad / 2 + _forEachIndex * _bw, _y, _bw - _pad / 4, _rh], _lbl] call _mk;
                _b ctrlSetFontHeight ((_fs * 0.74) max COMSPEC_ATAK_FontMin);
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                _b ctrlAddEventHandler ["ButtonClick", compile format ["%1 call comspec_atak_native_fnc_droneAction;", str _a]];
            } forEach _row;
            _y = _y + _rh + _pad / 3;
        } forEach _rows;
    };
    // Vue drone sur la carte : "OFF" (carte), "FULL" (la vue remplace la carte), "INSET" (incrustée) ; sans argument, fait le tour.
    case "view": {
        if (isNull _d) exitWith { ["WARNING", "Aucun drone appairé"] call _say; };
        private _v = if (_arg isEqualTo "") then { ["OFF", "FULL", "INSET"] select ((((["OFF", "FULL", "INSET"] find (_s getOrDefault ["droneView", "OFF"])) max 0) + 1) mod 3) } else { _arg };
        _s set ["droneView", _v];
        if (_v isNotEqualTo "OFF") then {
            private _fresh = isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]);
            call _camOpen;
            // Première ouverture en vue nacelle : thermique.
            if (_fresh) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneVision", 2]; call _expose; };
        };
        call _mapRender;
    };
    case "camEnsure": { if (!isNull _d) then { call _camBind; }; };
    // Caméra restée hors écran (fn_droneAction, boucle de la nacelle) : libérée, rouverte au retour sur la page Drone.
    case "camPark": { call _camClose; missionNamespace setVariable ["COMSPEC_ATAK_DroneCamParked", true]; };
    // Image du flux posée par la page (fn_pageDrone) : journal RPT de l'ouverture une fois par cible de rendu
    // (fn_pipCheck, étapes 1 à 5 et relevé à 2 s), sinon simple rebranchement de la texture à l'image suivante.
    case "feedWatch": {
        _arg params [["_pic", controlNull], ["_tex", ""]];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (_tex isEqualTo "" || {isNull _cam}) exitWith {};
        private _name = _cam getVariable ["r2t", ""];
        if (_name isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DroneWatched", ""])) exitWith {
            [{ ["rebind", _this] call comspec_atak_native_fnc_pipCheck; }, [_pic, _tex]] call CBA_fnc_execNextFrame;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneWatched", _name];
        ["watch", ["DRONE", _cam, _name, _tex, _pic]] call comspec_atak_native_fnc_pipCheck;
    };
    // VUE DIRECTE : secours quand l'image du téléphone reste noire (retour de TTRD, 13/10). La vue du jeu passe sur la
    // caméra du drone, sans texture de rendu (comme le terminal UAV) : elle s'affiche même si la PiP ne rend rien.
    // Téléphone rangé ; la nacelle garde sa visée (boucle de la caméra, pose recopiée chaque image). Échap revient à
    // l'app Drone ; ouvrir le téléphone, perdre la liaison ou le drone, ou mourir, coupe aussi la vue directe.
    case "direct": {
        if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneDirectCam", objNull])) exitWith { ["directEnd", true] call comspec_atak_native_fnc_droneAction; };
        if (isNull _d || {!alive _d}) exitWith { ["WARNING", "Aucun drone appairé"] call _say; };
        if !((["link"] call comspec_atak_native_fnc_droneAction) select 2) exitWith { ["WARNING", "Drone hors liaison : pas d'image"] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false]) exitWith { ["INFO", "Cliché en cours"] call _say; };
        call _camBind;
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (isNull _cam) exitWith { ["WARNING", "Caméra du drone indisponible"] call _say; };
        private _v = "camera" camCreate (getPosATL _cam);
        _v attachTo [_d, _cam getVariable ["off", [0, 0.3, -0.3]]];
        _v setVectorDirAndUp [_d vectorWorldToModelVisual (vectorDirVisual _cam), _d vectorWorldToModelVisual (vectorUpVisual _cam)];
        _v cameraEffect ["Internal", "Back"];
        _v camSetFov (0.7 / ((missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) max 1));
        _v camCommit 0;
        private _vision = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
        if (_vision isEqualTo 1) then { camUseNVG true; };
        if (_vision isEqualTo 2) then { true setCamUseTI 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectCam", _v];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotCam", _v];
        [] call comspec_atak_native_fnc_close;
        ("COMSPEC_ATAK_DroneDirect" call BIS_fnc_rscLayer) cutText [format ["<t size='1.1' shadow='2'>VUE DIRECTE · %1</t><br/><t size='0.85' shadow='2' color='#c9d4cf'>Échap : retour au téléphone</t>",
            [_d, ["link"] call comspec_atak_native_fnc_droneAction, "NAME"] call comspec_atak_native_fnc_droneOsd] call comspec_atak_native_fnc_tr, "PLAIN DOWN", 0.5, true, true];
        private _eh = (findDisplay 46) displayAddEventHandler ["KeyDown", {
            if ((_this select 1) isNotEqualTo 0x01) exitWith { false };
            [{ ["directEnd", true] call comspec_atak_native_fnc_droneAction; }] call CBA_fnc_execNextFrame;
            true
        }];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectEh", _eh];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectPfh", [{
            private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
            private _why = switch (true) do {
                case (!alive player): { "joueur mort" };
                case (isNull _d || {!alive _d}): { "drone perdu" };
                case (!((["link"] call comspec_atak_native_fnc_droneAction) select 2)): { "liaison perdue" };
                case (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])): { "caméra du drone fermée" };
                case (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])): { "téléphone ouvert" };
                default { "" };
            };
            if (_why isNotEqualTo "") then {
                ["INFO", "CAMERA", format ["DRONE : vue directe fermée (%1)", _why]] call comspec_atak_native_fnc_log;
                ["directEnd", false] call comspec_atak_native_fnc_droneAction;
            };
        }, 0.25] call CBA_fnc_addPerFrameHandler];
        ["INFO", "CAMERA", format ["DRONE : vue directe ouverte (caméra plein écran sans texture de rendu, PiP active : %1)", isPiPEnabled]] call comspec_atak_native_fnc_log;
    };
    // Fin de la vue directe ; avec true, le téléphone rouvre en main sur l'app Drone.
    case "directEnd": {
        private _v = missionNamespace getVariable ["COMSPEC_ATAK_DroneDirectCam", objNull];
        [missionNamespace getVariable ["COMSPEC_ATAK_DroneDirectPfh", -1]] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectPfh", -1];
        (findDisplay 46) displayRemoveEventHandler ["KeyDown", missionNamespace getVariable ["COMSPEC_ATAK_DroneDirectEh", -1]];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectEh", -1];
        ("COMSPEC_ATAK_DroneDirect" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        if (isNull _v) exitWith {};
        camUseNVG false;
        false setCamUseTI 0;
        _v cameraEffect ["Terminate", "Back"];
        camDestroy _v;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneDirectCam", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotCam", objNull];
        if (_arg isEqualTo true && {alive player} && {isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])}) then {
            [true] call comspec_atak_native_fnc_open;
            [{ ["DRONE"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        };
    };
    // Marquage au viseur de la caméra du drone (appui sur MARQUER ou touche « Marquer la cible »).
    case "lock": {
        if (isNull _d) exitWith {};
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) exitWith { ["WARNING", "Ouvrez la caméra du drone pour viser"] call _say; };
        ["mark", "DRONE"] call comspec_atak_native_fnc_lockAction;
        call _render;
    };
    case "camAim": {
        if (isNull _d) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", _arg];
        if (_arg isNotEqualTo "") then { call _camOpen; };
        call _render;
    };
    case "tab": { call _saveGrid; ["save"] call comspec_atak_native_fnc_dronePhoto; _s set ["droneTab", _arg]; call _render; };
    case "task": { call _saveGrid; _s set ["droneTask", _arg]; call _render; };
    case "taskRad": { call _saveGrid; _s set ["droneTaskRad", _arg]; call _render; };
    case "pickMap": {
        call _saveGrid;
        _s set ["dronePick", "1"];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["INFO", "Touchez le point de la tâche sur la carte", 4, 40] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        // Appelé par la carte (fn_mapMouseButtonDown) avec la position touchée : la grille à 10 chiffres remplit le champ.
        _s set ["droneTaskGridVal", [_arg, 10] call comspec_atak_native_fnc_gridRef];
        _s set ["droneTab", "TASK"];
        ["INFO", format ["Point de la tâche : %1", [_arg, 8] call comspec_atak_native_fnc_gridRef], 3, 30] call comspec_atak_native_fnc_notify;
        [{ ["DRONE"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "taskSend": {
        call _saveGrid;
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _p = [_s getOrDefault ["droneTaskGridVal", ""]] call _gridPos;
        if (_p isEqualTo []) exitWith { ["WARNING", "Point invalide : saisissez une grille (8 ou 10 chiffres) ou choisissez-le sur la carte"] call _say; };
        private _task = _s getOrDefault ["droneTask", "GOTO"];
        private _rad = _s getOrDefault ["droneTaskRad", 150];
        switch (_task) do {
            case "LOITER": { ["loiter", [_p, _rad]] call _send; };
            case "OBSERVE": { ["observe", [_p, _rad]] call _send; call _camOpen; missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""]; };
            default { ["goto", [_p]] call _send; };
        };
        private _g = [_p, 8] call comspec_atak_native_fnc_gridRef;
        [format ["Ordre : %1 en %2%3", (createHashMapFromArray [["GOTO", "aller"], ["LOITER", "orbite"], ["OBSERVE", "observer"]]) getOrDefault [_task, "aller"], _g,
            ["", format [", rayon %1 m", _rad]] select (_task in ["LOITER", "OBSERVE"])]] call _log;
        ["SUCCESS", format ["Tâche envoyée : %1", _g]] call _say;
        call _render;
    };
    case "escort": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _t = [_arg] call _unitOf;
        if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Unité introuvable"] call _say; };
        ["escort", [_t]] call _send;
        [format ["Ordre : escorte de %1%2", name _t, ["", format [" (%1)", getText (configOf vehicle _t >> "displayName")]] select ((vehicle _t) isNotEqualTo _t)]] call _log;
        call _render;
    };
    case "taskClear": {
        _s set ["droneTaskGridVal", ""];
        if (!isNull _d && {(_d getVariable ["COMSPEC_DroneMode", ""]) in ["GOTO", "LOITER", "OBSERVE", "ESCORT"]} && {call _needLink}) then {
            ["hover"] call _send;
            ["Ordre : tâche annulée, stationnaire"] call _log;
        };
        call _render;
    };
    case "trackMark": {
        private _tr = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_arg, []];
        if ((count _tr) isEqualTo 0) exitWith {};
        _tr params ["_o", "_name", "_pos", "", "_clock", "_tag", "", "_num"];
        // Marqueur ordinaire du téléphone (palette réglée le temps de la pose) : camp vu, infanterie ou véhicule.
        private _keys = ["markerAff", "markerType", "markerColor", "markerEditAfter"];
        private _old = _keys apply { _s getOrDefault [_x, "§"] };
        private _aff = ["u", "o", "b"] select (["INCONNU", "ENNEMI", "AMI"] find _tag);
        _s set ["markerAff", _aff];
        _s set ["markerType", format ["%1_%2", _aff, ["motor_inf", "inf"] select (_o isKindOf "CAManBase")]];
        _s set ["markerColor", "AUTO"];
        _s set ["markerEditAfter", false];
        private _m = [_pos] call comspec_atak_native_fnc_markerDrop;
        { if ((_old select _forEachIndex) isEqualTo "§") then { _s deleteAt _x; } else { _s set [_x, _old select _forEachIndex]; }; } forEach _keys;
        if (_m isNotEqualTo "") then {
            _m setMarkerText format ["%1 %2 (drone %3)", ["INC", "ENI", "AMI"] select (["INCONNU", "ENNEMI", "AMI"] find _tag), _name, _clock];
            [format ["Détection %1 posée sur la carte (%2)", [_num, 2] call CBA_fnc_formatNumber, [_pos, 8] call comspec_atak_native_fnc_gridRef]] call _log;
        };
    };
    case "tracksClear": {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        ["Détections effacées"] call _log;
        call _render;
    };
    case "rename": {
        if (isNull _d) exitWith {};
        // Texte libre affiché en texte structuré : on retire ce qui casserait le balisage.
        private _n = [toString ((toArray _arg) select { !(_x in [34, 38, 39, 60, 62, 92]) })] call CBA_fnc_trim;
        _n = _n select [0, 24];
        if (_n isEqualTo "") then { _d setVariable ["COMSPEC_DroneName", nil, true]; } else { _d setVariable ["COMSPEC_DroneName", _n, true]; };
        [format ["Drone renommé : %1", [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd]] call _log;
        ["SUCCESS", format ["Drone renommé : %1", [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd]] call _say;
        call _render;
    };
    case "icon": {
        if (isNull _d || {!(_arg in ["quad", "fixed", "hexa", "nano", "fpv"])}) exitWith {};
        _d setVariable ["COMSPEC_DroneIcon", _arg, true];
        [format ["Icône du drone : %1", (createHashMapFromArray [["quad", "quadricoptère"], ["fixed", "aile fixe"], ["hexa", "hexacoptère"], ["nano", "nano-drone"], ["fpv", "FPV"]]) get _arg]] call _log;
        call _render;
    };
    case "standbyKind": { _s set ["droneStandbyKind", _arg]; call _render; };
    case "standby": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
        if (_m isEqualTo "STANDBY") exitWith { ["resume"] call comspec_atak_native_fnc_droneAction; };
        if (_m isEqualTo "STRIKE") exitWith { ["WARNING", "Frappe en cours : veille impossible"] call _say; };
        private _kind = [_arg, _s getOrDefault ["droneStandbyKind", "LAND"]] select !(_arg in ["LAND", "HOVER"]);
        // Ordre à reprendre en sortie de veille (stationnaire si c'était déjà un arrêt).
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_DroneOrder", []];
        private _prev = [_o param [0, "hover"], _o param [1, []]];
        if ((_prev select 0) in ["land", "standby", "manual", "home", "rth", "strike", "takeoff"]) then { _prev = ["hover", []]; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneResume", _prev];
        ["standby", [_kind]] call _send;
        call _camClose;
        [format ["Veille : %1, capteur et caméra coupés", ["stationnaire", "posé sur place"] select (_kind isEqualTo "LAND")], "#8fb3c9"] call _log;
        call _render;
    };
    case "resume": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        (missionNamespace getVariable ["COMSPEC_ATAK_DroneResume", ["hover", []]]) params [["_c", "hover"], ["_a", []]];
        if (_c in ["follow", "escort"] && {private _t = _a param [0, objNull]; !(_t isEqualType objNull) || {!alive _t}}) then { _c = "hover"; _a = []; };
        [_c, _a] call _send;
        [format ["Fin de veille : reprise (%1)", [_expect getOrDefault [_c, "HOVER"]] call _modeName], "#5cc76b"] call _log;
        call _render;
    };
    case "force": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        [false] call _recover;
        ["INFO", "Exécution forcée : ordre renvoyé au drone"] call _say;
        call _render;
    };
    case "transfer": {
        if (isNull _d) exitWith {};
        private _t = [_arg] call _unitOf;
        if (isNull _t || {!alive _t} || {!isPlayer _t} || {(_t distance player) > 50}) exitWith { ["WARNING", "Le destinataire doit être un joueur allié à moins de 50 m"] call _say; };
        if !([_t] call comspec_atak_native_fnc_hasDevice) exitWith { ["WARNING", format ["%1 n'a pas de téléphone", name _t]] call _say; };
        private _uid = getPlayerUID _t;
        if ((allUnitsUAV findIf { alive _x && {_x isNotEqualTo _d} && {(_x getVariable ["COMSPEC_DroneOwner", ""]) isEqualTo _uid} }) >= 0) exitWith { ["WARNING", format ["%1 pilote déjà un drone", name _t]] call _say; };
        if ((_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STRIKE") exitWith { ["WARNING", "Frappe en cours : transfert impossible"] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DroneXfer", false]) exitWith { ["INFO", "Transfert déjà en cours"] call _say; };
        // Second appui dans les 5 s pour confirmer.
        private _k = format ["XFER:%1", _uid];
        private _c = _s getOrDefault ["droneConfirm", ["", 0]];
        if ((_c select 0) isNotEqualTo _k || {diag_tickTime - (_c select 1) > 5}) exitWith {
            _s set ["droneConfirm", [_k, diag_tickTime]];
            ["WARNING", format ["Appuyez encore pour transférer le drone à %1", name _t]] call _say;
            call _render;
        };
        _s set ["droneConfirm", ["", 0]];
        // Poignée de main : 3 s de liaison entre les deux téléphones, annoncée au destinataire.
        missionNamespace setVariable ["COMSPEC_ATAK_DroneXfer", true];
        ["comspec_atak_native_droneCmd", [_d, "transferIn", [name player, _uid, [], "HELLO"]], _t] call CBA_fnc_targetEvent;
        ["INFO", format ["Liaison avec le téléphone de %1…", name _t]] call _say;
        [{ missionNamespace setVariable ["COMSPEC_ATAK_DroneXfer", false]; ["transferDo", _this] call comspec_atak_native_fnc_droneAction; }, [_d, _t, _uid], 3] call CBA_fnc_waitAndExecute;
        call _render;
    };
    case "transferDo": {
        _arg params ["_dd", "_t", "_uid"];
        if (isNull _d || {_dd isNotEqualTo _d} || {!alive _d}) exitWith { ["WARNING", "Transfert annulé"] call _say; };
        if (!alive _t || {(_t distance player) > 50} || {getPlayerUID _t isNotEqualTo _uid}) exitWith {
            ["WARNING", "Transfert annulé : liaison perdue avec le destinataire"] call _say;
            ["Transfert annulé : destinataire hors de portée", "#f2ab33"] call _log;
        };
        _d setVariable ["COMSPEC_DroneOwner", _uid, true];
        _d setVariable ["COMSPEC_DroneLock", "", true];
        ["comspec_atak_native_droneCmd", [_d, "transferIn", [name player, _uid, missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", []], "DONE"]], _t] call CBA_fnc_targetEvent;
        [format ["Drone transféré à %1", name _t], "#5cc76b"] call _log;
        ["SUCCESS", format ["Drone transféré à %1", name _t]] call _say;
        // On rend la main sans libérer le drone : il appartient déjà au nouveau pilote.
        call _camClose;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneOrder", []];
        [missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", -1];
        call _render;
    };
    case "transferIn": {
        _arg params ["_new", ["_from", ""], ["_home", []], ["_stage", "DONE"]];
        if (isNull _new || {!alive _new}) exitWith {};
        if (_stage isEqualTo "HELLO") exitWith {
            ["INFO", format ["%1 vous transfère son drone : liaison en cours…", _from]] call _say;
            [] call comspec_atak_native_fnc_vibrate;
        };
        if (!isNull _d && {_d isNotEqualTo _new}) then { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        _s set ["droneAlt", _new getVariable ["COMSPEC_DroneAlt", 40]];
        _s set ["droneSpd", _new getVariable ["COMSPEC_DroneSpd", 40]];
        [_new, _home] call _adopt;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", _new getVariable ["COMSPEC_DroneMode", "HOVER"]];
        private _nm = [_new, [], "NAME"] call comspec_atak_native_fnc_droneOsd;
        [format ["Drone reçu de %1 : %2, en %3", _from, _nm, [getPosASL _new, 8] call comspec_atak_native_fnc_gridRef], "#5cc76b"] call _log;
        ["SUCCESS", format ["Drone reçu de %1 : %2", _from, _nm]] call _say;
        [] call comspec_atak_native_fnc_vibrate;
        call _render;
    };
    case "webCmd": {
        // Ordres venus de COMSPEC Athena par l'addon Overwatch connect : mêmes chemins que les boutons du téléphone.
        _arg params [["_c", ""], ["_a", []]];
        if !(_a isEqualType []) then { _a = [_a]; };
        _c = toLower _c;
        private _toAsl = { params ["_p"]; if !(_p isEqualType [] && {(count _p) >= 2}) exitWith { [] }; [_p select 0, _p select 1, (getTerrainHeightASL [_p select 0, _p select 1]) + 1] };
        private _known = ["takeoff", "hover", "land", "home", "rth", "me", "follow", "escort", "goto", "loiter", "observe", "hunt", "route", "alt", "speed", "cqb", "standby", "resume", "force", "rename", "icon"];
        if (isNull _d || {!(_c in _known)}) exitWith { false };
        if (!(_c in ["rename", "icon"]) && {!(call _needLink)}) exitWith { false };
        [format ["Ordre reçu de COMSPEC Athena : %1", _c], "#4da3ff"] call _log;
        private _ok = true;
        switch (_c) do {
            case "takeoff": { ["takeoff"] call comspec_atak_native_fnc_droneAction; };
            case "hover";
            case "land";
            case "home";
            case "me": { ["mode", _c] call comspec_atak_native_fnc_droneAction; };
            case "rth": { ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send; call _render; };
            case "follow": {
                private _t = [_a param [0, ""]] call _unitOf;
                if (isNull _t || {!alive _t}) exitWith { _ok = false; };
                ["follow", [_t]] call _send;
                [format ["Ordre : suivi de %1", name _t]] call _log;
                call _render;
            };
            case "escort": {
                private _t = [_a param [0, ""]] call _unitOf;
                if (isNull _t || {!alive _t}) exitWith { _ok = false; };
                ["escort", _t] call comspec_atak_native_fnc_droneAction;
            };
            case "goto": {
                private _p = [_a param [0, []]] call _toAsl;
                if (_p isEqualTo []) exitWith { _ok = false; };
                ["goto", [_p]] call _send;
                [format ["Ordre : aller en %1", [_p, 8] call comspec_atak_native_fnc_gridRef]] call _log;
                call _render;
            };
            case "loiter";
            case "observe": {
                private _p = [_a param [0, []]] call _toAsl;
                private _r = ((_a param [1, 150, [0]]) max 30) min 800;
                if (_p isEqualTo []) exitWith { _ok = false; };
                [_c, [_p, _r]] call _send;
                if (_c isEqualTo "observe") then { missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""]; };
                [format ["Ordre : %1 en %2, rayon %3 m", ["orbite", "observer"] select (_c isEqualTo "observe"), [_p, 8] call comspec_atak_native_fnc_gridRef, round _r]] call _log;
                call _render;
            };
            case "hunt": {
                private _r = ((_a param [0, 250, [0]]) max 50) min 1000;
                private _p = [_a param [1, []]] call _toAsl;
                if (_p isEqualTo []) then { _s set ["droneHuntRad", _r]; ["mode", "hunt"] call comspec_atak_native_fnc_droneAction; } else {
                    ["hunt", [_r, player, _p]] call _send;
                    [format ["Ordre : recherche en %1, rayon %2 m", [_p, 8] call comspec_atak_native_fnc_gridRef, round _r]] call _log;
                    call _render;
                };
            };
            case "route": {
                private _pts = ((_a param [0, [], [[]]]) apply { [_x] call _toAsl }) select { _x isNotEqualTo [] };
                private _rm = toUpper (_a param [1, "ONCE", [""]]);
                if !(_rm in ["ONCE", "LOOP", "PINGPONG"]) then { _rm = "ONCE"; };
                if ((count _pts) isEqualTo 0) exitWith { _ok = false; };
                _pts = _pts select [0, 12];
                ["route", [_pts, _rm]] call _send;
                [format ["Ordre : route de %1 points, %2", count _pts, (createHashMapFromArray [["ONCE", "une fois"], ["LOOP", "en boucle"], ["PINGPONG", "en aller-retour"]]) get _rm]] call _log;
                call _render;
            };
            case "alt": { ["alt", round (((_a param [0, 40, [0]]) max 2) min 500)] call comspec_atak_native_fnc_droneAction; };
            case "speed": { ["speed", round (((_a param [0, 40, [0]]) max 5) min 150)] call comspec_atak_native_fnc_droneAction; };
            case "cqb": { if ((_a param [0, true, [true]]) isNotEqualTo (_d getVariable ["COMSPEC_DroneCqb", false])) then { ["cqb"] call comspec_atak_native_fnc_droneAction; }; };
            case "standby": {
                if ((_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STANDBY") exitWith {};
                ["standby", toUpper (_a param [0, "", [""]])] call comspec_atak_native_fnc_droneAction;
            };
            case "resume": { ["resume"] call comspec_atak_native_fnc_droneAction; };
            case "force": { ["force"] call comspec_atak_native_fnc_droneAction; };
            case "rename": {
                private _v = _a param [0, ""];
                if !(_v isEqualType "") then { _v = str _v; };
                ["rename", _v] call comspec_atak_native_fnc_droneAction;
            };
            case "icon": { ["icon", toLower (_a param [0, "", [""]])] call comspec_atak_native_fnc_droneAction; };
        };
        _ok
    };
    case "tick": {
        if (isNull _d) exitWith { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        if (!alive _d) exitWith {
            private _strike = (_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STRIKE";
            ["WARNING", ["Drone perdu", "Drone : impact"] select _strike] call _say;
            if (_strike) then {
                [format ["Impact en %1", [_d getVariable ["COMSPEC_DroneTgt", getPosASL _d], 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
            } else {
                [format ["Drone perdu en %1", [missionNamespace getVariable ["COMSPEC_ATAK_DroneLastPos", getPosASL _d], 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
            };
            ["unpair"] call comspec_atak_native_fnc_droneAction;
        };
        private _l = call _link;
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinked", true];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLinked", _l select 2];
        private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
        // Veille posée : le drone reste au sol, quoi qu'il arrive à la liaison ou à la batterie.
        private _parked = _m isEqualTo "STANDBY" && {(_d getVariable ["COMSPEC_DroneStandby", "LAND"]) isEqualTo "LAND"};
        // Liaison perdue : retour automatique au point de décollage (sauf frappe déjà lancée).
        if (_was && {!(_l select 2)}) then {
            ["Liaison perdue", "#e5483a"] call _log;
            if (!(_m in ["STRIKE", "RTH", "LAND"]) && {!_parked}) then {
                ["WARNING", "Drone : liaison perdue, retour au point de décollage"] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
            };
        };
        if (!_was && {_l select 2}) then { ["SUCCESS", "Drone : liaison rétablie"] call _say; ["Liaison rétablie", "#5cc76b"] call _log; };
        // Zone interdite : un drone qui y entre (vent, suivi, pilotage) en ressort aussitôt par le bord le plus proche.
        private _nfz = ["inside", getPosASL _d] call comspec_atak_native_fnc_noFly;
        private _nfWas = missionNamespace getVariable ["COMSPEC_ATAK_DroneNoFlyIn", ""];
        private _nfId = _nfz param [0, ""];
        if (_nfId isNotEqualTo _nfWas) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneNoFlyIn", _nfId];
            if (_nfId isNotEqualTo "" && {_l select 2} && {((getPosATL _d) select 2) > 3} && {!(_m in ["STRIKE", "LAND", "RTH"])} && {!_parked}) then {
                private _out = ["exit", getPosASL _d] call comspec_atak_native_fnc_noFly;
                ["WARNING", "Drone : zone interdite, sortie de la zone"] call _say;
                [format ["Entrée en zone interdite (%1) : sortie vers %2", [_nfz select 1, 8] call comspec_atak_native_fnc_gridRef, [_out, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
                ["goto", [[_out select 0, _out select 1, 0]]] call _send;
            };
        };
        // Le mode affiché est celui que le drone renvoie : le journal note chaque changement (ordre exécuté, arrivée, repli...).
        private _lm = missionNamespace getVariable ["COMSPEC_ATAK_DroneLastMode", ""];
        if (_m isNotEqualTo _lm) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", _m];
            if (_lm isNotEqualTo "" && {_l select 2}) then { [format ["Mode : %1", [_m] call _modeName], ["", "#f2ab33"] select (_m in ["RTH", "STRIKE"])] call _log; };
        };
        // Recherche : ennemi repéré ou engagé, renvoyé par le drone.
        private _ev = _d getVariable ["COMSPEC_DroneEvt", [0]];
        if ((_ev select 0) > (missionNamespace getVariable ["COMSPEC_ATAK_DroneEvtN", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneEvtN", _ev select 0];
            _ev params ["", "_kind", "_pos", "_what"];
            if (_kind isEqualTo "OBSTACLE") exitWith {
                ["WARNING", "Drone : obstacle devant, arrêt en stationnaire"] call _say;
                [format ["CQB : obstacle devant en %1, arrêt en stationnaire", [_pos, 8] call comspec_atak_native_fnc_gridRef], "#f2ab33"] call _log;
            };
            [format ["Recherche : %1 %2 en %3", ["ennemi repéré,", "engagement de"] select (_kind isEqualTo "ENGAGE"), _what, [_pos, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
        };
        // Batterie : alerte à 20 %, retour automatique à 10 % (comme un DJI), une seule fois par seuil.
        private _fu = fuel _d;
        private _warn = missionNamespace getVariable ["COMSPEC_ATAK_DroneFuelWarn", 0];
        if (_fu > 0.25 && {_warn > 0}) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 0]; _warn = 0; };
        if (_fu <= 0.2 && {_warn < 1}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 1];
            ["WARNING", format ["Drone : batterie faible (%1 %%)", round (100 * _fu)]] call _say;
            [] call comspec_atak_native_fnc_vibrate;
            [format ["Batterie faible : %1 %%", round (100 * _fu)], "#f2ab33"] call _log;
        };
        if (_fu <= 0.1 && {_warn < 2} && {((getPosATL _d) select 2) > 2}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 2];
            if (!(_m in ["STRIKE", "RTH", "LAND"]) && {!_parked}) then {
                ["WARNING", "Drone : batterie critique, retour au point de décollage"] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
                [format ["Batterie critique (%1 %%) : retour automatique", round (100 * _fu)], "#e5483a"] call _log;
            };
        };
        // Capteur : seulement en vol et avec le retour vidéo.
        // Réglage serveur « Repérage automatique par drone » (désactivé par défaut) : sinon le pilote repère lui-même.
        if ((_l select 2) && {((getPosATL _d) select 2) > 3} && {_m isNotEqualTo "STANDBY"} && {missionNamespace getVariable ["comspec_atak_native_drone_autospot", false]}) then { call _scan; };
        // Ordre qui n'avance pas : reprise automatique, une fois (pas en pilotage manuel ni en veille).
        if ((_l select 2) && {!(_m in ["MANUAL", "STANDBY"])}) then { call _watch; };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_DroneOsd", controlNull];
        if (!isNull _osd) then { _osd ctrlSetStructuredText parseText ((([_d, _l] call comspec_atak_native_fnc_droneOsd)) call comspec_atak_native_fnc_tr); };
        private _feed = uiNamespace getVariable ["COMSPEC_ATAK_DroneFeedTxt", []];
        if ((count _feed) isEqualTo 5 && {!isNull (_feed select 0)}) then {
            private _t = [_d, _l, uiNamespace getVariable ["COMSPEC_ATAK_DroneFeedKind", "FEED"]] call comspec_atak_native_fnc_droneOsd;
            { _x ctrlSetStructuredText parseText (((_t select _forEachIndex)) call comspec_atak_native_fnc_tr); } forEach _feed;
        };
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
        // Journal de performance (retour de TTRD : le jeu rame avec le drone ou les photos) : toutes les 30 s, les caméras
        // qui rendent dans une texture (chacune redessine la scène à chaque image) et les images par seconde.
        if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_PerfLogAt", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_PerfLogAt", diag_tickTime + 30];
            private _on = { params ["_k"]; private _c = missionNamespace getVariable [_k, objNull]; [0, 1] select (!isNull _c && {_c getVariable ["rendering", true]}) };
            private _dc = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
            ["INFO", "PERF", format ["%1 img/s ; caméras de rendu : drone %2 (qualité %3), aperçu cliché %4, viseur photo %5, live cam %6, carte JPC %7 ; flotte %8 ; page %9 ; téléphone %10",
                round diag_fps, ["COMSPEC_ATAK_DroneCam"] call _on, (profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 1]),
                ["COMSPEC_ATAK_DronePrevCam"] call _on, ["COMSPEC_ATAK_PhotoCam"] call _on, ["COMSPEC_ATAK_LivecamCam"] call _on, ["COMSPEC_ATAK_JpcMapCam"] call _on,
                count (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []]), _s getOrDefault ["activePage", ""],
                ["fermé", "ouvert"] select (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]))]] call comspec_atak_native_fnc_log;
        };
        // Suivi des cibles marquées dans l'ordre choisi : cible suivante, position de l'orbite tenue à jour.
        ["tick"] call comspec_atak_native_fnc_droneSeq;
        // Onglet TÂCHES, drone non armé : redessiné quand le pilote passe à moins de 5 m du drone (étape 1 de l'armement).
        if ((_s getOrDefault ["droneTab", "PILOT"]) isEqualTo "TASK" && {(_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo ""}) then {
            private _near = (player distance _d) <= 5;
            if (_near isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DroneArmNear", _near])) then { call _saveGrid; call _render; };
            missionNamespace setVariable ["COMSPEC_ATAK_DroneArmNear", _near];
        };
        // Onglets CIBLES et JOURNAL : redessinés quand une ligne arrive (pas quand l'image seule est à l'écran). Compteur de
        // lignes écrites, pas taille du journal : plafonné à 100 lignes, il ne se redessinait plus une fois plein.
        private _sig = [missionNamespace getVariable ["COMSPEC_ATAK_DroneLogN", 0], count (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap])];
        if (_sig isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DroneSig", []])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneSig", _sig];
            if ((_s getOrDefault ["droneTab", "PILOT"]) in ["TARGETS", "LOG"] && {uiNamespace getVariable ["COMSPEC_ATAK_DroneMenusShown", false]}) then { call _render; };
        };
    };
};
