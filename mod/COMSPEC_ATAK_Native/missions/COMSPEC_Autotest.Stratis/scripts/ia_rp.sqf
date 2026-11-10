// SSE › IA RP : interprète et générateur (contrôles purs), terminal « Parler à… », réponse cohérente avec la couverture,
// pas de redite, journal, bulle 3D, /try, ordre simple ; atelier IA (profil par script, fenêtre du Zeus).
// 4.2.1 : « stop » relâché seul, deux demandes, faute de frappe, renseignement par paliers avec marqueur local,
// confrontation, tirs proches et témoins.
if !("sse" call AT_fnc_groupe) exitWith {};
AT_fnc_iarpIa = {
    private _g = createGroup civilian;
    private _u = _g createUnit ["C_man_1", player getPos [2.5, getDir player], [], 0, "CAN_COLLIDE"];
    _u setDir ((getDir player) + 180);
    _u disableAI "PATH";
    _u allowDamage false;
    _u
};
// Message tapé dans le terminal ouvert, comme le joueur (Entrée) ; attend la réplique de l'IA.
AT_fnc_iarpDire = {
    params ["_u", "_txt", ["_reponse", true]];
    missionNamespace setVariable ["comspec_sse_iarpLastAck", nil];
    missionNamespace setVariable ["comspec_sse_iarpLastReply", nil];
    ((findDisplay 94600) displayCtrl 94620) ctrlSetText _txt;
    ["send"] call comspec_sse_fnc_iarpUi;
    [{ !isNil { missionNamespace getVariable "comspec_sse_iarpLastAck" } }, 6] call AT_fnc_wait;
    AT_iarpU = _u;
    if (_reponse) then { [{ ((missionNamespace getVariable ["comspec_sse_iarpLastReply", [objNull]]) select 0) isEqualTo AT_iarpU }, 6] call AT_fnc_wait; };
    uiSleep 1; // anti-flood du serveur (0,8 s par poste)
    [missionNamespace getVariable ["comspec_sse_iarpLastAck", []], missionNamespace getVariable ["comspec_sse_iarpLastReply", []]]
};

["ia_rp_interprete", {
    if (["comspec_sse_fnc_iarpSelfTest"] call AT_fnc_nil) exitWith {};
    ([] call comspec_sse_fnc_iarpSelfTest) params ["_ko", "_info"];
    { _x call AT_fnc_info; } forEach _info;
    { [false, _x] call AT_fnc_check; } forEach _ko;
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["ia_rp_texte_libre", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_idLegend", "comspec_sse_fnc_idIssue"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 2;
    [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    // Personne honnête (atelier IA) : ses réponses doivent coller à sa couverture, sans exception.
    ["apply", [_u, createHashMapFromArray [["honesty", 95], ["talk", 55], ["nerve", 30]]]] call comspec_sse_fnc_iarpProfile;
    private _city = (["get", [_u]] call comspec_sse_fnc_idLegend) getOrDefault ["city", ""];
    [_city isNotEqualTo "", "légende de l'IA sans ville (fn_idLegend)"] call AT_fnc_check;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    ([_u, "D'où venez-vous ?"] call AT_fnc_iarpDire) params ["_ack", "_rep"];
    [(_ack param [1, ""]) isEqualTo "origin", format ["intention reconnue : %1 (origin attendue)", _ack param [1, "aucune"]]] call AT_fnc_check;
    private _txt = _rep param [1, ""];
    (format ["réponse : %1 (ville de la couverture : %2)", _txt, _city]) call AT_fnc_info;
    [(_txt find _city) >= 0, format ["réponse sans la ville de la couverture (%1) : %2", _city, _txt]] call AT_fnc_check;
    ["ia_texte_libre"] call AT_fnc_shot;
    // Même question encore cinq fois : toujours la même ville, presque jamais la même phrase.
    private _vus = [_txt];
    private _dup = 0;
    for "_i" from 1 to 5 do {
        private _r = (([_u, "Vous venez d'où, exactement ?"] call AT_fnc_iarpDire) select 1) param [1, ""];
        if (_r in _vus) then { _dup = _dup + 1; } else { _vus pushBack _r; };
        [(_r find _city) >= 0, format ["réponse %1 incohérente : %2", _i + 1, _r]] call AT_fnc_check;
    };
    [_dup < 3, format ["redite : %1 phrases identiques sur 6", _dup]] call AT_fnc_check;
    // Journal : ma question et la réponse, avec l'intention.
    uiNamespace setVariable ["comspec_sse_iarpLogRows", []];
    ["tab", ["log"]] call comspec_sse_fnc_iarpUi;
    [{ (uiNamespace getVariable ["comspec_sse_iarpLogRows", []]) isNotEqualTo [] }, 5] call AT_fnc_wait;
    private _rows = uiNamespace getVariable ["comspec_sse_iarpLogRows", []];
    [(_rows findIf { (_x select 11) isEqualTo "D'où venez-vous ?" && {(_x select 12) isEqualTo "origin"} && {(_x select 7) isEqualTo (netId _u)} }) >= 0, "question absente du journal"] call AT_fnc_check;
    [(_rows findIf { (_x select 3) isEqualTo "IA>P" && {(_x select 7) isEqualTo (netId _u)} }) >= 0, "réponse de l'IA absente du journal"] call AT_fnc_check;
    ["ia_journal"] call AT_fnc_shot;
    // /try : résultat du serveur ; ordre simple reconnu.
    ["tab", ["talk"]] call comspec_sse_fnc_iarpUi;
    private _try = ([_u, "/try lit la plaque dans le noir", false] call AT_fnc_iarpDire) select 0;
    [(_try param [1, ""]) isEqualTo "try" && {(_try param [2, ""]) in ["success", "fail"]}, format ["/try sans tirage : %1", _try]] call AT_fnc_check;
    private _ord = ([_u, "Mains en l'air !"] call AT_fnc_iarpDire) select 0;
    [(_ord param [1, ""]) isEqualTo "handsup", format ["ordre non reconnu : %1", _ord]] call AT_fnc_check;
    closeDialog 0;
    uiSleep 0.5;
    // Bulle de l'IA au-dessus de sa tête (bulles du SSE, sans l'ATAK).
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    uiSleep 0.5;
    [_u, "Merci, bonne journée."] call AT_fnc_iarpDire;
    closeDialog 0;
    private _b = missionNamespace getVariable ["comspec_sse_iarpBubbles", []];
    [(_b findIf { (_x select 0) isEqualTo _u }) >= 0, "aucune bulle au-dessus de l'IA"] call AT_fnc_check;
    "bulle de l'IA au-dessus de sa tête, nom et texte lisibles" call AT_fnc_visuel;
    ["ia_bulle"] call AT_fnc_shot;
    deleteVehicle _u;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Atelier IA : profil de bibliothèque appliqué par script, texte du profil aller-retour, fenêtre du Zeus et sa zone
// de test, réplique dite par le Zeus.
["ia_rp_atelier", {
    if (["comspec_sse_fnc_iarpWorkshop", "comspec_sse_fnc_iarpProfile"] call AT_fnc_nil) exitWith {};
    private _p = ["preset", "milicien"] call comspec_sse_fnc_iarpProfile;
    [(_p get "role") isEqualTo "insurge" && {(_p get "aggro") isEqualTo 65}, "profil « milicien » de la bibliothèque mal lu"] call AT_fnc_check;
    private _raw = ["serialize", _p] call comspec_sse_fnc_iarpProfile;
    private _back = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
    [(["serialize", _back] call comspec_sse_fnc_iarpProfile) isEqualTo _raw, "profil en texte : l'aller-retour change le profil"] call AT_fnc_check;
    _p set ["name", "Yannis PETRAKIS"];
    private _g = ["generate", [_p, ["name", "role"]]] call comspec_sse_fnc_iarpProfile;
    [(_g get "name") isEqualTo "Yannis PETRAKIS" && {(_g get "role") isEqualTo "insurge"}, "Générer a changé un champ verrouillé"] call AT_fnc_check;
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    ["apply", [_u, _p]] call comspec_sse_fnc_iarpProfile;
    [(_u getVariable ["COMSPEC_ID_Profile", ""]) isEqualTo "insurge" && {(_u getVariable ["comspec_sse_idName", ""]) isEqualTo "Yannis PETRAKIS"}, "profil non appliqué à l'IA"] call AT_fnc_check;
    ["open", ["zeus", [_u]]] call comspec_sse_fnc_iarpWorkshop;
    if !([[{ !isNull (uiNamespace getVariable ["comspec_sse_iarpWsDisplay", displayNull]) }, 5] call AT_fnc_wait, "atelier IA non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    private _d = uiNamespace getVariable ["comspec_sse_iarpWsDisplay", displayNull];
    [[{ (ctrlText ((uiNamespace getVariable ["comspec_sse_iarpWsDisplay", displayNull]) displayCtrl 94720)) isEqualTo "Yannis PETRAKIS" }, 5] call AT_fnc_wait, "profil de l'IA non chargé dans l'atelier"] call AT_fnc_check;
    (_d displayCtrl 94740) ctrlSetText format ["Origine : Kavala%1Métier : Pêcheur", toString [10]];
    (_d displayCtrl 94744) ctrlSetText "D'où venez-vous ?";
    ["test"] call comspec_sse_fnc_iarpWorkshop;
    private _t = missionNamespace getVariable ["comspec_sse_iarpWsLastTest", []];
    [(_t param [0, ""]) isEqualTo "origin" && {((_t param [2, ""]) find "Kavala") >= 0 || {(_t param [1, ""]) isNotEqualTo "origin"}}, format ["zone de test : %1", _t]] call AT_fnc_check;
    (format ["zone de test : %1", _t]) call AT_fnc_info;
    ["ia_atelier_zeus"] call AT_fnc_shot;
    (_d displayCtrl 94747) ctrlSetText "Sortez de mon champ !";
    ["say"] call comspec_sse_fnc_iarpWorkshop;
    uiSleep 1;
    _d closeDisplay 2;
    private _b = missionNamespace getVariable ["comspec_sse_iarpBubbles", []];
    [(_b findIf { (_x select 0) isEqualTo _u }) >= 0, "réplique du Zeus sans bulle"] call AT_fnc_check;
    deleteVehicle _u;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// 4.2.1 : « Stop » relâché seul (durée réglée ramenée à 15 s pour l'essai).
["ia_rp_stop_relache", {
    if (["comspec_sse_fnc_iarpReact", "comspec_sse_fnc_iarpUi"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    _u enableAI "PATH";
    uiSleep 1;
    private _old = missionNamespace getVariable ["comspec_sse_iarp_haltTime", 120];
    missionNamespace setVariable ["comspec_sse_iarp_haltTime", 15];
    ["apply", [_u, createHashMapFromArray [["fear", 95], ["aggro", 0], ["react", [["order", "coopere"]]]]]] call comspec_sse_fnc_iarpProfile;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    [[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check;
    ([_u, "Stop ! Ne bougez plus !"] call AT_fnc_iarpDire) params ["_ack"];
    closeDialog 0;
    [(_ack param [1, ""]) isEqualTo "halt", format ["ordre « stop » non reconnu : %1", _ack]] call AT_fnc_check;
    [[{ !(AT_iarpU checkAIFeature "PATH") }, 3] call AT_fnc_wait, "l'IA ne s'est pas arrêtée (PATH encore actif)"] call AT_fnc_check;
    [[{ AT_iarpU checkAIFeature "PATH" }, 25] call AT_fnc_wait, "IA toujours figée 25 s après un « stop » de 15 s"] call AT_fnc_check;
    missionNamespace setVariable ["comspec_sse_iarp_haltTime", _old];
    deleteVehicle _u;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// 4.2.1 : deux demandes dans un message, faute de frappe, confrontation d'un menteur, savoir par paliers avec un lieu.
["ia_rp_dialogue_421", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpMem", "comspec_sse_fnc_idLegend", "comspec_sse_fnc_idIssue"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 2;
    [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    private _nl = toString [10];
    // Nerfs solides (20) : un peureux nerveux peut aussi nier puis s'enfuir (fn_iarpServer « confrontFlee », une chance sur
    // deux), hors de portée du terminal ; l'essai porte sur l'aveu, pas sur la fuite.
    ["apply", [_u, createHashMapFromArray [["role", "insurge"], ["honesty", 95], ["fear", 95], ["nerve", 20], ["aggro", 0], ["bribe", 100], ["unlock", "bribe"],
        ["secrets", ["Il garde un fusil sous le siège de son pick-up."]],
        ["know", format ["Vague : Des hommes armés se retrouvent la nuit.%1Précis : À la ferme abandonnée, après la prière.%1Lieu : [%2, %3] Ferme abandonnée", _nl, round ((getPosATL player) select 0) + 50, round ((getPosATL player) select 1)]],
        ["react", [["bribe", "coopere"]]]]]] call comspec_sse_fnc_iarpProfile;
    private _city = (["get", [_u]] call comspec_sse_fnc_idLegend) getOrDefault ["city", ""];
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    [[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check;
    // Salut puis question : une seule bulle, la ville de la couverture dedans.
    ([_u, "Bonjour, d'où venez-vous ?"] call AT_fnc_iarpDire) params ["_a1", "_r1"];
    [(_a1 param [1, ""]) isEqualTo "origin" && {((_r1 param [1, ""]) find _city) >= 0}, format ["deux demandes : %1 / %2", _a1, _r1]] call AT_fnc_check;
    (format ["deux demandes : %1", _r1 param [1, ""]]) call AT_fnc_info;
    // Faute de frappe : « emploieur » compris.
    private _a2 = ([_u, "Et votre emploieur ?"] call AT_fnc_iarpDire) select 0;
    [(_a2 param [1, ""]) isEqualTo "employer", format ["faute de frappe non comprise : %1", _a2]] call AT_fnc_check;
    // Pot-de-vin puis renseignement : palier précis et marqueur sur MA carte.
    [_u, "/me tend une liasse de billets"] call AT_fnc_iarpDire;
    ([_u, "Que savez-vous ?"] call AT_fnc_iarpDire) params ["_a3", "_r3"];
    (format ["renseignement : %1 / %2", _a3 param [2, ""], _r3 param [1, ""]]) call AT_fnc_info;
    [[{ (allMapMarkers findIf { (_x find "comspec_sse_iarpIntel_") isEqualTo 0 }) >= 0 }, 5] call AT_fnc_wait, "pas de marqueur local après un renseignement précis avec lieu"] call AT_fnc_check;
    "marqueur « Renseignement : Ferme abandonnée (…) » sur la carte du joueur" call AT_fnc_visuel;
    // Confrontation d'un insurgé peureux : il finit par craquer (4 essais au plus).
    private _conf = false;
    for "_i" from 1 to 4 do {
        if (!_conf) then {
            private _a4 = ([_u, "Vos papiers sont faux, tu mens !"] call AT_fnc_iarpDire) select 0;
            (format ["confrontation %1 : %2", _i, _a4 param [2, ""]]) call AT_fnc_info;
            if (((_a4 param [2, ""]) find "avoue") >= 0) then { _conf = true; };
        };
    };
    [_conf, "confrontation : aucun aveu en 4 essais (insurgé à 95 de peur)"] call AT_fnc_check;
    closeDialog 0;
    { if ((_x find "comspec_sse_iarpIntel_") isEqualTo 0) then { deleteMarkerLocal _x; }; } forEach allMapMarkers;
    deleteVehicle _u;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// 4.2.1 : tirs proches et témoins (évènements appelés directement : aucun tir réel dans l'essai).
["ia_rp_tirs_temoins", {
    if (["comspec_sse_fnc_iarpReact"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    private _w = (group _u) createUnit ["C_man_1", _u getPos [6, 0], [], 0, "CAN_COLLIDE"];
    _w setDir ((_w getDir _u)); _w allowDamage false;
    private _g2 = createGroup west;
    private _firer = _g2 createUnit ["B_Soldier_F", player getPos [8, 90], [], 0, "CAN_COLLIDE"];
    _firer allowDamage false;
    uiSleep 1;
    ["apply", [_u, createHashMapFromArray [["fear", 95]]]] call comspec_sse_fnc_iarpProfile;
    _u setVariable ["comspec_sse_iarpTalkAt", time];
    AT_iarpU = _u;
    ["watch", [_u]] call comspec_sse_fnc_iarpReact;
    [(_u getVariable ["comspec_sse_iarpFnEh", -1]) >= 0, "FiredNear non posé sur l'IA en conversation"] call AT_fnc_check;
    ["fired", [_u, _firer, 10]] call comspec_sse_fnc_iarpReact;
    [(_u getVariable ["comspec_sse_iarpShotAt", -1]) >= 0 && {(["shock", _u] call comspec_sse_fnc_iarpReact) > 0}, "aucune réaction au tir proche"] call AT_fnc_check;
    [[{ (AT_iarpU getVariable ["ace_captives_isSurrendering", false]) || {(animationState AT_iarpU) find "surrender" >= 0} || {(unitPos AT_iarpU) isEqualTo "Down"} }, 5] call AT_fnc_wait, "civil apeuré : ni reddition ni mise à terre"] call AT_fnc_check;
    "bulle « Ne tirez pas ! » ou « Je me rends ! » au-dessus de l'IA" call AT_fnc_visuel;
    private _n = ["witness", [_u, player]] call comspec_sse_fnc_iarpReact;
    (format ["témoins : %1", _n]) call AT_fnc_info;
    [(["shock", _w] call comspec_sse_fnc_iarpReact) > 0, "le civil à 6 m qui voit la scène n'est pas choqué"] call AT_fnc_check;
    { deleteVehicle _x; } forEach [_u, _w, _firer];
    call AT_fnc_done;
}, 40] call AT_fnc_run;

// 4.2.2 : actions du menu Zeus « SSE · Roleplay de l'IA » côté serveur (solo : isServer, le joueur est l'autorité) :
// profil retouché, état de conversation, lot sur deux IA, mémoire, réactions, nom affiché (fn_iarpName).
["ia_rp_zeus_actions", {
    if (["comspec_sse_fnc_iarpServer", "comspec_sse_fnc_iarpProfile", "comspec_sse_fnc_iarpName"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    private _u2 = (group _u) createUnit ["C_man_1", _u getPos [3, 90], [], 0, "CAN_COLLIDE"];
    _u2 allowDamage false;
    { _x enableAI "PATH"; } forEach [_u, _u2];
    uiSleep 1;
    // Nom affiché : jamais le nom d'unité Arma ; le Zeus voit la vérité (nom de l'atelier différent de l'identité : ment).
    _u setVariable ["comspec_sse_idName", nil];
    [(["shown", _u] call comspec_sse_fnc_iarpName) isNotEqualTo (name _u), format ["nom d'unité Arma affiché : %1", name _u]] call AT_fnc_check;
    // Profil retouché (menu « Rôle et caractère ») : rôle et peur changés, le reste gardé.
    missionNamespace setVariable ["comspec_sse_iarpLastNotice", nil];
    ["apply", [_u, createHashMapFromArray [["talk", 77]]]] call comspec_sse_fnc_iarpProfile;
    ["profileTweak", [player, [_u], [["role", "insurge"], ["fear", 90], ["name", "Yannis PETRAKIS"], ["react", [["order", "coopere"]]]]]] call comspec_sse_fnc_iarpServer;
    private _p = ["get", _u] call comspec_sse_fnc_iarpProfile;
    [(_p get "role") isEqualTo "insurge" && {(_p get "fear") isEqualTo 90} && {(_p get "talk") isEqualTo 77} && {((_p get "react") get "order") isEqualTo "coopere"}, format ["profil retouché : %1", ["summary", _p] call comspec_sse_fnc_iarpProfile]] call AT_fnc_check;
    [(_u getVariable ["COMSPEC_ID_Profile", ""]) isEqualTo "insurge", "rôle non transmis aux IA réalistes (COMSPEC_ID_Profile)"] call AT_fnc_check;
    [!isNil { missionNamespace getVariable "comspec_sse_iarpLastNotice" }, "aucun retour au Zeus après la retouche du profil"] call AT_fnc_check;
    (["truth", _u] call comspec_sse_fnc_iarpName) params [["_shown", ""], ["_true", ""], ["_nat", ""]];
    (format ["nom pour le Zeus : %1 / %2 / %3 -> %4", _shown, _true, _nat, ["label", [_shown, _true, _nat]] call comspec_sse_fnc_iarpName]) call AT_fnc_info;
    [_shown isEqualTo "Yannis PETRAKIS" && {_nat in ["true", "lies"]}, format ["vérité du nom : %1", [_shown, _true, _nat]]] call AT_fnc_check;
    // État de conversation : « Stop » fige l'IA, « Relâcher » la libère (ordres de l'IA RP, serveur).
    ["zenState", [player, [_u], "stop"]] call comspec_sse_fnc_iarpServer;
    AT_iarpU = _u;
    [[{ !(AT_iarpU checkAIFeature "PATH") }, 3] call AT_fnc_wait, "« Stop » du Zeus : l'IA n'est pas arrêtée"] call AT_fnc_check;
    ["zenState", [player, [_u], "free"]] call comspec_sse_fnc_iarpServer;
    [[{ AT_iarpU checkAIFeature "PATH" }, 3] call AT_fnc_wait, "« Relâcher » du Zeus : l'IA reste figée"] call AT_fnc_check;
    // Lot : profil de bibliothèque sur deux IA, réactions coupées pour les deux.
    private _raw = ["serialize", ["preset", "milicien"] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
    ["profileSet", [player, [_u, _u2], _raw, "apply"]] call comspec_sse_fnc_iarpServer;
    [((["get", _u] call comspec_sse_fnc_iarpProfile) get "preset") isEqualTo "milicien" && {((["get", _u2] call comspec_sse_fnc_iarpProfile) get "preset") isEqualTo "milicien"}, "lot : profil non appliqué aux deux IA"] call AT_fnc_check;
    ["reactSet", [player, [_u, _u2], false, true]] call comspec_sse_fnc_iarpServer;
    [("shots" in (_u getVariable ["comspec_sse_iarpReactOff", []])) && {"shots" in (_u2 getVariable ["comspec_sse_iarpReactOff", []])} && {!("witness" in (_u2 getVariable ["comspec_sse_iarpReactOff", []]))}, "lot : réactions non réglées sur les deux IA"] call AT_fnc_check;
    if (!(["comspec_sse_fnc_iarpReact"] call AT_fnc_nil)) then {
        // Tirs coupés pour cette IA : suivie (« watch ») mais aucune réaction au tir proche.
        _u setVariable ["comspec_sse_iarpTalkAt", time];
        ["watch", [_u]] call comspec_sse_fnc_iarpReact;
        ["fired", [_u, player, 10]] call comspec_sse_fnc_iarpReact;
        [(_u getVariable ["comspec_sse_iarpShotAt", -1]) < 0, "réaction aux tirs malgré la coupure du Zeus"] call AT_fnc_check;
    };
    // Mémoire : lue (vide) puis effacée.
    missionNamespace setVariable ["comspec_sse_iarpLastMem", nil];
    ["memGet", [player, _u]] call comspec_sse_fnc_iarpServer;
    private _m = missionNamespace getVariable ["comspec_sse_iarpLastMem", []];
    [(_m param [0, objNull]) isEqualTo _u, "mémoire de l'IA non reçue par le Zeus"] call AT_fnc_check;
    if (dialog) then { closeDialog 0; };
    _u setVariable ["comspec_sse_iarpMem", createHashMapFromArray [["said", createHashMap]]];
    ["memClear", [player, [_u], ""]] call comspec_sse_fnc_iarpServer;
    [isNil { _u getVariable "comspec_sse_iarpMem" }, "mémoire de l'IA non effacée"] call AT_fnc_check;
    { deleteVehicle _x; } forEach [_u, _u2];
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// 4.2.2 : entrée ZEN « SSE · Roleplay de l'IA » posée, visible sur une IA, jamais sur un joueur seul (condition évaluée
// avec les arguments de ZEN : [position, objets sélectionnés, groupes, points de passage, marqueurs, survol, argument]).
["ia_rp_zeus_menu", {
    if (["comspec_sse_fnc_iarpZen"] call AT_fnc_nil) exitWith {};
    if (isNil "zen_context_menu_fnc_createAction" || {isNil "zen_context_menu_actions"}) exitWith { "ZEN absent de ce build" call AT_fnc_skip; };
    [[{ !isNil "comspec_sse_iarpZenTargets" }, 10] call AT_fnc_wait, "menu ZEN de l'IA RP non enregistré (comspec_sse_iarpZenTargets)"] call AT_fnc_check;
    private _node = (zen_context_menu_actions select { ((_x select 0) select 0) isEqualTo "comspec_sse_iarp" }) param [0, []];
    if !([_node isNotEqualTo [], "entrée racine « comspec_sse_iarp » absente des actions ZEN"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    private _cond = (_node select 0) select 5;
    private _kids = (_node select 1) apply { (_x select 0) select 0 };
    (format ["sous-menus : %1", _kids]) call AT_fnc_info;
    { [_x in _kids, format ["sous-menu %1 absent", _x]] call AT_fnc_check; } forEach ["comspec_sse_iarp_profile", "comspec_sse_iarp_state", "comspec_sse_iarp_mem", "comspec_sse_iarp_react"];
    private _u = call AT_fnc_iarpIa;
    private _pos = getPosASL _u;
    [[_pos, [_u], [], [], [], _u, []] call _cond, "entrée invisible sur une IA survolée"] call AT_fnc_check;
    [[_pos, [_u, player], [], [], [], objNull, []] call _cond, "entrée invisible sur une sélection IA + joueur"] call AT_fnc_check;
    [!([_pos, [player], [], [], [], player, []] call _cond), "entrée visible sur un joueur seul"] call AT_fnc_check;
    [!([_pos, [], [], [], [], objNull, []] call _cond), "entrée visible sans cible"] call AT_fnc_check;
    [([_pos, [player, _u], [], [], [], player, []] call comspec_sse_iarpZenTargets) isEqualTo [_u], "cibles : le joueur n'est pas écarté"] call AT_fnc_check;
    deleteVehicle _u;
    call AT_fnc_done;
}, 30] call AT_fnc_run;
