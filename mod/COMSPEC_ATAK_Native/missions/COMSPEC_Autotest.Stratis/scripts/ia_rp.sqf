// SSE › IA RP : interprète et générateur (contrôles purs), terminal « Parler à… », réponse cohérente avec la couverture,
// pas de redite, journal, bulle 3D, /try, ordre simple ; atelier IA (profil par script, fenêtre du Zeus).
// 4.2.1 : « stop » relâché seul, deux demandes, faute de frappe, renseignement par paliers avec marqueur local,
// confrontation, tirs proches et témoins. 4.3.0 : une réponse et une bulle par message au nom SSE, guide, actions, peur.
// À venir : réponses écrites par le maître du jeu (attribut, Zeus, question perso, palier débloqué), cadre d'un civil sans savoir.
// Groupes : « sse » ou « ia_rp » (tout ce fichier), « ia_rp_reponses » (la fenêtre « Réponses de l'IA » seule).
if !("sse" call AT_fnc_groupe || {"ia_rp" call AT_fnc_groupe} || {"ia_rp_reponses" call AT_fnc_groupe}) exitWith {};
AT_iarpSeul = !("sse" call AT_fnc_groupe) && {!("ia_rp" call AT_fnc_groupe)};
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

// Fenêtre « Réponses de l'IA » ouverte : aucun texte coupé, aucun contrôle visible sur un autre (même groupe de contrôles).
AT_fnc_iarpAnsVerif = {
    params ["_etat"];
    private _d = findDisplay 94800;
    if (isNull _d) exitWith { [false, format ["%1 : fenêtre fermée", _etat]] call AT_fnc_check };
    private _fond = ["Back", "Head", "Line", "Split", "SplitV", "PrevBack", "VarBack", "VarList", "Probe"];
    private _vis = (allControls _d) select {
        private _g = ctrlParentControlsGroup _x;
        ctrlShown _x && {!((ctrlClassName _x) in _fond)} && {isNull _g || {ctrlShown _g}} && {((ctrlPosition _x) select 2) > 0}
    };
    { if ((ctrlType _x) in [0, 1, 13, 16]) then { [_x, format ["%1 : %2", _etat, ctrlClassName _x]] call AT_fnc_tient; }; } forEach _vis;
    private _n = count _vis;
    for "_i" from 0 to (_n - 2) do {
        private _a = _vis select _i;
        (ctrlPosition _a) params ["_ax", "_ay", "_aw", "_ah"];
        for "_j" from (_i + 1) to (_n - 1) do {
            private _b = _vis select _j;
            if ((ctrlParentControlsGroup _a) isEqualTo (ctrlParentControlsGroup _b) && {(ctrlType _a) != 15} && {(ctrlType _b) != 15}) then {
                (ctrlPosition _b) params ["_bx", "_by", "_bw", "_bh"];
                private _ix = ((_ax + _aw) min (_bx + _bw)) - (_ax max _bx);
                private _iy = ((_ay + _ah) min (_by + _bh)) - (_ay max _by);
                [_ix < 0.002 || {_iy < 0.002}, format ["%1 : %2 sur %3", _etat, ctrlClassName _a, ctrlClassName _b]] call AT_fnc_check;
            };
        };
    };
};

// Fenêtre « Réponses de l'IA » du maître du jeu (Zeus, une IA) : états à contrôler sur les captures.
["ia_rp_reponses_fenetre", {
    if (["comspec_sse_fnc_iarpAnswersUi", "comspec_sse_fnc_iarpProfile"] call AT_fnc_nil) exitWith {};
    (format ["résolution %1", getResolution]) call AT_fnc_info;
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    ["apply", [_u, ["normalize", createHashMapFromArray [["name", "Nikos KARRAS"], ["role", "civil"], ["know", "Précis : Ils dorment à la ferme du vieux Petros."]]] call comspec_sse_fnc_iarpProfile]] call comspec_sse_fnc_iarpProfile;
    uiNamespace setVariable ["comspec_sse_iarpAnsAdv", false];
    ["open", ["units", [_u]]] call comspec_sse_fnc_iarpAnswersUi;
    if !([[{ !isNull findDisplay 94800 }, 5] call AT_fnc_wait, "fenêtre « Réponses de l'IA » non ouverte"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    [{ (lbSize ((findDisplay 94800) displayCtrl 94821)) > 100 }, 6] call AT_fnc_wait;
    uiSleep 1;
    private _d = findDisplay 94800;
    private _lb = _d displayCtrl 94821;
    ["1 initial"] call AT_fnc_iarpAnsVerif;
    ["reponses_1_initial"] call AT_fnc_shot;
    // Une question choisie (« Avez-vous vu des hommes armés ? »).
    private _row = -1;
    for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isEqualTo "seenArmed") exitWith { _row = _i; }; };
    [_row >= 0, "question « seenArmed » absente de la liste"] call AT_fnc_check;
    _lb lbSetCurSel _row;
    uiSleep 0.5;
    ["2 question"] call AT_fnc_iarpAnsVerif;
    ["reponses_2_question"] call AT_fnc_shot;
    // Ma réponse en cours d'écriture, deux textes et une variable : aperçu.
    (_d displayCtrl 94831) lbSetCurSel 1;
    ["mode"] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.3;
    (_d displayCtrl 94836) ctrlSetText format ["Hier soir, trois pick-up vers le nord, {monsieur}.%1Je ne sais rien, moi. Je suis {prenom}, je vends du pain à {lieu}.%1", toString [10]];
    ["store"] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.5;
    private _prev = ctrlText (_d displayCtrl 94839);
    (format ["aperçu : %1", _prev]) call AT_fnc_info;
    [(_prev find "Nikos") >= 0 && {(_prev find "{") < 0}, format ["aperçu sans variable remplacée : %1", _prev]] call AT_fnc_check;
    ["3 ma reponse"] call AT_fnc_iarpAnsVerif;
    ["reponses_3_ma_reponse"] call AT_fnc_shot;
    // « Insérer une variable » : liste ouverte, puis {nom} ajouté à la fin du texte.
    (_d displayCtrl 94836) ctrlSetTextSelection [count (toArray (ctrlText (_d displayCtrl 94836))), 0];
    ["vars"] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.5;
    [ctrlShown (_d displayCtrl 94881) && {(lbSize (_d displayCtrl 94881)) >= 10}, "liste des variables non ouverte"] call AT_fnc_check;
    ["3b variables"] call AT_fnc_iarpAnsVerif;
    ["reponses_3b_variables"] call AT_fnc_shot;
    (_d displayCtrl 94881) lbSetCurSel 0;
    uiSleep 0.5;
    private _txt = ctrlText (_d displayCtrl 94836);
    [!ctrlShown (_d displayCtrl 94881) && {(_txt find "{nom}") >= 0 || {(_txt find "{name}") >= 0}}, format ["variable non insérée : %1", _txt]] call AT_fnc_check;
    // Options avancées : ce qu'elle peut évoquer (Libre), deux thèmes interdits, situation « S'il a peur ».
    ["advanced", [true]] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.3;
    (_d displayCtrl 94810) lbSetCurSel 2;
    { (_d displayCtrl _x) cbSetChecked true; } forEach [94811, 94812];
    ["frame"] call comspec_sse_fnc_iarpAnswersUi;
    ["help", ["Sit"]] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.5;
    ["4 options avancees"] call AT_fnc_iarpAnsVerif;
    ["reponses_4_avancees"] call AT_fnc_shot;
    // Ma propre question (exemple prérempli : mot de passe).
    ["add"] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.5;
    ["5 question perso"] call AT_fnc_iarpAnsVerif;
    ["reponses_5_question_perso"] call AT_fnc_shot;
    // Options avancées refermées : mode simple sur la question perso.
    ["advanced", [false]] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 0.3;
    ["6 simple question perso"] call AT_fnc_iarpAnsVerif;
    ["reponses_6_simple_perso"] call AT_fnc_shot;
    // Enregistrer : la réponse perso arrive sur l'IA (serveur), format inchangé.
    ["apply"] call comspec_sse_fnc_iarpAnswersUi;
    uiSleep 1.5;
    private _ans = (["get", _u] call comspec_sse_fnc_iarpProfile) getOrDefault ["answers", []];
    (format ["réponses de l'IA : %1", _ans]) call AT_fnc_info;
    [(_ans findIf { (_x select 0) isEqualTo "seenArmed" && {(count (((_x select 4) select 0) select 1)) isEqualTo 3} && {((((_x select 4) select 0) select 1) select 0) find "trois pick-up" >= 0} }) >= 0, "réponse perso non enregistrée sur l'IA"] call AT_fnc_check;
    [(_ans findIf { (_x select 0) isEqualTo "c1" }) >= 0, "question perso non enregistrée"] call AT_fnc_check;
    (findDisplay 94800) closeDisplay 2;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;
if (AT_iarpSeul) exitWith {};

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
    missionNamespace setVariable ["comspec_sse_iarpLogRows", []];
    ["tab", ["log"]] call comspec_sse_fnc_iarpUi;
    [{ (missionNamespace getVariable ["comspec_sse_iarpLogRows", []]) isNotEqualTo [] }, 5] call AT_fnc_wait;
    private _rows = missionNamespace getVariable ["comspec_sse_iarpLogRows", []];
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
    // Confrontation d'un insurgé peureux : il finit par craquer (4 essais au plus). Arme braquée sur lui : sans arme, une
    // fois nié, le faux découvert (fn_idAiFsm « bust », +30) peut porter sa peur à 65 et l'automate le fait fuir
    // (fn_idAiBrain), muet ensuite (« ne répond pas (fuit) ») ; braqué, il ne fuit pas : l'essai porte sur l'aveu.
    if ((primaryWeapon player) isEqualTo "") then { player addWeapon "arifle_MX_F"; player addMagazine "30Rnd_65x39_caseless_mag"; };
    player action ["WeaponInHand", player];
    player setDir (player getDir _u);
    uiSleep 2;
    (format ["confrontation : %1 arme(s) braquée(s) sur l'IA", (["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) select 0]) call AT_fnc_info;
    private _conf = false;
    for "_i" from 1 to 4 do {
        if (!_conf) then {
            private _a4 = ([_u, "Vos papiers sont faux, tu mens !"] call AT_fnc_iarpDire) select 0;
            (format ["confrontation %1 : %2 (état %3, peur %4, distance %5 m)", _i, _a4 param [2, ""], ["state", [_u]] call comspec_sse_fnc_idAiFsm,
                round (_u getVariable ["comspec_sse_idTension", 0]), round (player distance _u)]) call AT_fnc_info;
            if (((_a4 param [2, ""]) find "avoue") >= 0) then { _conf = true; };
        };
    };
    [_conf, "confrontation : aucun aveu en 4 essais (insurgé à 95 de peur)"] call AT_fnc_check;
    closeDialog 0;
    player action ["WeaponOnBack", player];
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

// À venir : une réponse, une bulle, un nom ; « Tu vas bien ? » compris ; journal sans doublon ; guide qui remplit la saisie.
["ia_rp_une_reponse", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_idIssue"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 2;
    [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    AT_iarpU = _u;
    [[{ (AT_iarpU getVariable ["comspec_sse_idName", ""]) isNotEqualTo "" }, 5] call AT_fnc_wait, "IA avec papiers : nom SSE absent (comspec_sse_idName)"] call AT_fnc_check;
    private _name = _u getVariable ["comspec_sse_idName", ""];
    uiSleep 1;
    [(ctrlText ((findDisplay 94600) displayCtrl 94601)) isEqualTo _name, format ["en-tête « %1 » au lieu de « %2 »", ctrlText ((findDisplay 94600) displayCtrl 94601), _name]] call AT_fnc_check;
    private _net = netId _u;
    private _before = count ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [_net, []]);
    ([_u, "Tu vas bien ?"] call AT_fnc_iarpDire) params ["_ack", "_rep"];
    [(_ack param [1, ""]) isEqualTo "howareyou", format ["« Tu vas bien ? » : %1", _ack param [1, "aucune"]]] call AT_fnc_check;
    uiSleep 4;
    private _l = ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [_net, []]) select [_before];
    private _ai = _l select { (_x select 0) isEqualTo "ai" };
    [(count _ai) isEqualTo 1, format ["%1 réponse(s) de l'IA dans le fil pour un message", count _ai]] call AT_fnc_check;
    [((_ai param [0, []]) param [1, ""]) isEqualTo _name, format ["nom du fil « %1 », nom SSE « %2 »", (_ai param [0, []]) param [1, ""], _name]] call AT_fnc_check;
    private _b = (missionNamespace getVariable ["comspec_sse_iarpBubbles", []]) select { (_x select 0) isEqualTo AT_iarpU };
    [(count _b) isEqualTo 1 && {((_b select 0) select 2) isEqualTo (toUpper _name)}, format ["bulles au-dessus de l'IA : %1", _b apply { _x select 2 }]] call AT_fnc_check;
    ["ia_une_reponse"] call AT_fnc_shot;
    "fenêtre « Parler à » : en-tête (initiales, nom, état), bulles Moi à droite / IA à gauche, une seule bulle 3D" call AT_fnc_visuel;
    // Journal : chaque ligne une fois.
    ["tab", ["log"]] call comspec_sse_fnc_iarpUi;
    uiSleep 2;
    private _rows = missionNamespace getVariable ["comspec_sse_iarpLogRows", []];
    private _ids = _rows apply { _x select 0 };
    [(count _ids) isEqualTo (count (_ids arrayIntersect _ids)), "journal : lignes en double"] call AT_fnc_check;
    ["ia_journal_lisible"] call AT_fnc_shot;
    // Guide : thèmes, un exemple cliqué remplit le champ.
    ["tab", ["guide"]] call comspec_sse_fnc_iarpUi;
    uiSleep 0.5;
    [(lbSize ((findDisplay 94600) displayCtrl 94660)) >= 10, "guide : moins de 10 thèmes"] call AT_fnc_check;
    private _pair = ((((["guide"] call comspec_sse_fnc_iarpData) select 1) select 1) select 0) select 1;
    private _s = [(_pair select 0) select 1, (_pair select 0) select 0] select (language isEqualTo "French");
    ["example", [_s]] call comspec_sse_fnc_iarpUi;
    [(ctrlText ((findDisplay 94600) displayCtrl 94620)) isEqualTo _s, "guide : l'exemple n'a pas rempli le champ de saisie"] call AT_fnc_check;
    ["ia_guide"] call AT_fnc_shot;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

// À venir : chaque action du menu « Faire une action » laisse une ligne dans le fil et un effet mesurable sur l'IA.
["ia_rp_actions", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpReact"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    AT_iarpU = _u;
    uiSleep 1;
    ["apply", [_u, createHashMapFromArray [["react", [["order", "coopere"], ["bribe", "refuse"]]]]]] call comspec_sse_fnc_iarpProfile;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    [[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal non ouvert"] call AT_fnc_check;
    AT_iarpPress = { (AT_iarpU getVariable ["comspec_sse_iarpPress", [0, 0]]) select 0 };
    {
        _x params ["_id", "_effect"];
        if (isNull findDisplay 94600) then { ["open", [AT_iarpU]] call comspec_sse_fnc_iarpUi; uiSleep 1; };
        private _n0 = count ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [netId AT_iarpU, []]);
        AT_iarpP0 = call AT_iarpPress;
        missionNamespace setVariable ["comspec_sse_iarpLastAck", nil];
        ["action", [_id]] call comspec_sse_fnc_iarpUi;
        [{ !isNil { missionNamespace getVariable "comspec_sse_iarpLastAck" } }, 6] call AT_fnc_wait;
        uiSleep 2.5;
        private _l = ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [netId AT_iarpU, []]) select [_n0];
        [(_l findIf { (_x select 0) isEqualTo "act" }) >= 0, format ["action « %1 » : pas de ligne dans le fil", _id]] call AT_fnc_check;
        [(_l findIf { (_x select 0) isEqualTo "ai" }) >= 0, format ["action « %1 » : l'IA ne réagit pas", _id]] call AT_fnc_check;
        [call compile _effect, format ["action « %1 » : aucun effet (%2)", _id, _effect]] call AT_fnc_check;
        uiSleep 1;
    } forEach [
        ["handsup", "(call AT_iarpPress) < AT_iarpP0 || {AT_iarpP0 <= -29}"], ["lower", "(call AT_iarpPress) < AT_iarpP0 || {AT_iarpP0 <= -29}"],
        ["aim", "(call AT_iarpPress) > AT_iarpP0"], ["badge", "(call AT_iarpPress) > AT_iarpP0"], ["money", "(call AT_iarpPress) > AT_iarpP0"],
        ["give", "(call AT_iarpPress) < AT_iarpP0 || {AT_iarpP0 <= -29}"],
        ["photo", "((missionNamespace getVariable ['comspec_sse_iarpLastAck', []]) param [2, '']) isNotEqualTo ''"],
        ["search", "(AT_iarpU getVariable ['ace_captives_isSurrendering', false]) || {(call AT_iarpPress) > AT_iarpP0} || {(((missionNamespace getVariable ['comspec_sse_iarpLastAck', []]) param [2, '']) find 'fouille') >= 0}"],
        ["cuff", "(AT_iarpU getVariable ['ace_captives_isHandcuffed', false]) || {(call AT_iarpPress) > AT_iarpP0}"],
        ["take", "((missionNamespace getVariable ['comspec_sse_iarpLastAck', []]) param [1, '']) isEqualTo 'papers'"]
    ];
    ["ia_actions"] call AT_fnc_shot;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

// À venir : un civil sans menace reste calme à côté d'un joueur armé (arme baissée) ; braqué à 3 m, sa peur monte ;
// arme baissée 30 s, elle redescend (automate des IA réalistes, fn_idAiBrain), et le journal dit pourquoi.
["ia_rp_peur", {
    if (["comspec_sse_fnc_idAiFsm", "comspec_sse_fnc_iarpReact"] call AT_fnc_nil) exitWith {};
    if ((primaryWeapon player) isEqualTo "") then { player addWeapon "arifle_MX_F"; player addMagazine "30Rnd_65x39_caseless_mag"; };
    private _u = call AT_fnc_iarpIa;
    AT_iarpU = _u;
    _u setPosATL (player getPos [3, getDir player]);
    _u setDir ((getDir player) + 180);
    uiSleep 1;
    ["engage", [_u, player]] call comspec_sse_fnc_idAiFsm;
    if !(weaponLowered player) then { player action ["WeaponOnBack", player]; };
    uiSleep 60;
    private _t1 = _u getVariable ["comspec_sse_idTension", 0];
    (format ["peur après 60 s, arme baissée : %1", round _t1]) call AT_fnc_info;
    [_t1 < 30, format ["civil sans menace : peur %1 (moins de 30 attendu)", round _t1]] call AT_fnc_check;
    player action ["WeaponInHand", player];
    player setDir (player getDir _u);
    uiSleep 6;
    private _aim = (["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) select 0;
    private _t2 = _u getVariable ["comspec_sse_idTension", 0];
    (format ["arme braquée à 3 m : %1 arme(s), peur %2 ; arme %3, baissée %4, visée %5, état %6", _aim, round _t2, currentWeapon player, weaponLowered player,
        ((player weaponDirection (currentWeapon player)) vectorDotProduct (vectorNormalized ((aimPos _u) vectorDiff (eyePos player)))) toFixed 3,
        ["state", [_u]] call comspec_sse_fnc_idAiFsm]) call AT_fnc_info;
    [_aim > 0 && {_t2 > _t1 + 10}, format ["arme braquée à 3 m : peur %1 (avant %2), %3 arme(s) vue(s)", round _t2, round _t1, _aim]] call AT_fnc_check;
    player action ["WeaponOnBack", player];
    uiSleep 30;
    private _t3 = _u getVariable ["comspec_sse_idTension", 0];
    [_t3 < _t2 - 8, format ["arme baissée 30 s : peur %1 (pic %2)", round _t3, round _t2]] call AT_fnc_check;
    private _rows = (missionNamespace getVariable ["comspec_sse_iarpLog", []]) select { (_x select 3) isEqualTo "PEUR" && {(_x select 7) isEqualTo (netId AT_iarpU)} };
    [_rows isNotEqualTo [], "journal : aucune variation de peur notée avec sa raison"] call AT_fnc_check;
    { ("journal de la peur : " + (_x select 11)) call AT_fnc_info; } forEach (_rows select [0, 4]);
    deleteVehicle _u;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

// ---------------------------------------------------------------- 4.3.x : réponses écrites par le maître du jeu, cadre
// Réponse perso posée par l'attribut Eden (même expression : « applyRaw » sur le serveur), variables remplacées.
["ia_rp_reponse_perso_attribut", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpAnswers"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    private _p = ["normalize", createHashMapFromArray [["name", "Nikos KARRAS"], ["honesty", 95], ["answers", [
        ["name", "", [], "random", [["*", ["Je suis {prenom}, le boulanger. Tout le monde me connaît ici."]]], 0, ""]
    ]]]] call comspec_sse_fnc_iarpProfile;
    ["applyRaw", [_u, ["serialize", _p] call comspec_sse_fnc_iarpProfile]] call comspec_sse_fnc_iarpProfile;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    ([_u, "Qui es-tu ?"] call AT_fnc_iarpDire) params ["_ack", "_rep"];
    private _txt = _rep param [1, ""];
    (format ["réponse perso : %1", _txt]) call AT_fnc_info;
    [(_txt find "Je suis Nikos, le boulanger.") >= 0, format ["réponse perso de l'attribut non dite : %1", _txt]] call AT_fnc_check;
    [(_txt find "{") < 0, format ["variable non remplacée : %1", _txt]] call AT_fnc_check;
    // Une autre question garde la réponse auto (l'intention et les règles ne changent pas).
    ([_u, "Vous êtes d'où ?"] call AT_fnc_iarpDire) params ["_ack2", "_rep2"];
    [(_ack2 param [1, ""]) isEqualTo "origin" && {((_rep2 param [1, ""]) find "boulanger") < 0}, format ["question sans réponse perso : %1 / %2", _ack2 param [1, ""], _rep2 param [1, ""]]] call AT_fnc_check;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Réponse perso posée par le Zeus (menu ZEN « Réponses de l'IA… » : profileTweak) : palier précis débloqué, lieu sur la carte.
["ia_rp_reponse_perso_zeus", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpAnswersUi"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    ["apply", [_u, ["normalize", createHashMapFromArray [["role", "civil"], ["unlock", "never"], ["know", "Précis : Ils dorment à la ferme du vieux Petros."]]] call comspec_sse_fnc_iarpProfile]] call comspec_sse_fnc_iarpProfile;
    // La fenêtre du Zeus s'ouvre sur cette IA et lit son profil sur le serveur.
    ["open", ["units", [_u]]] call comspec_sse_fnc_iarpAnswersUi;
    [[{ !isNull findDisplay 94800 }, 5] call AT_fnc_wait, "fenêtre « Réponses de l'IA » non ouverte"] call AT_fnc_check;
    uiSleep 1;
    [(lbSize ((findDisplay 94800) displayCtrl 94821)) > 100, format ["fenêtre : %1 lignes de questions (plus de 100 attendues)", lbSize ((findDisplay 94800) displayCtrl 94821)]] call AT_fnc_check;
    ["ia_reponses_zeus"] call AT_fnc_shot;
    "fenêtre « Réponses de l'IA » : cadre en haut, questions par thème à gauche (Auto / Perso), réponse, aperçu et aide à droite" call AT_fnc_visuel;
    (findDisplay 94800) closeDisplay 2;
    private _ans = [["seenArmed", "", [], "random", [["*", ["Hier soir, trois pick-up vers le nord."]]], 2, format ["[%1, %2] Ferme du vieux Petros", round (((getPosATL _u) select 0) + 300), round ((getPosATL _u) select 1)]]];
    ["send", ["profileTweak", [player, [_u], [["answers", _ans]]]]] call comspec_sse_fnc_iarpClient;
    uiSleep 1.5;
    private _nMk = count (allMapMarkers select { (_x find "comspec_sse_iarpIntel_") isEqualTo 0 });
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    ([_u, "Avez-vous vu des hommes armés ?"] call AT_fnc_iarpDire) params ["_ack", "_rep"];
    [(_ack param [1, ""]) isEqualTo "seenArmed", format ["intention %1 (seenArmed attendue)", _ack param [1, ""]]] call AT_fnc_check;
    [((_rep param [1, ""]) find "trois pick-up") >= 0, format ["réponse perso du Zeus non dite : %1", _rep param [1, ""]]] call AT_fnc_check;
    uiSleep 1;
    [(count (allMapMarkers select { (_x find "comspec_sse_iarpIntel_") isEqualTo 0 })) > _nMk, "lieu de la réponse perso absent de la carte"] call AT_fnc_check;
    // Palier précis débloqué par la réponse (savoir « never » d'habitude fermé) : « Que savez-vous ? » le donne.
    ([_u, "Que savez-vous ?"] call AT_fnc_iarpDire) params ["", "_rep2"];
    [((_rep2 param [1, ""]) find "ferme du vieux Petros") >= 0, format ["palier débloqué non donné : %1", _rep2 param [1, ""]]] call AT_fnc_check;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

// Question perso du maître du jeu (déclencheurs) : « mot de passe » -> « Le faucon vole bas. », ordre du même message gardé.
["ia_rp_question_perso", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpAnswers"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    ["apply", [_u, ["normalize", createHashMapFromArray [["answers", [["c1", "Mot de passe", ["mot de passe", "password"], "random", [["*", ["Le faucon vole bas."]]], 0, ""]]]]] call comspec_sse_fnc_iarpProfile]] call comspec_sse_fnc_iarpProfile;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    ([_u, "Quel est le mot de passe ?"] call AT_fnc_iarpDire) params ["", "_rep"];
    [((_rep param [1, ""]) find "Le faucon vole bas.") >= 0, format ["question perso : %1", _rep param [1, ""]]] call AT_fnc_check;
    ([_u, "What's the password?"] call AT_fnc_iarpDire) params ["", "_rep2"];
    [((_rep2 param [1, ""]) find "Le faucon vole bas.") >= 0, format ["question perso (anglais) : %1", _rep2 param [1, ""]]] call AT_fnc_check;
    ([_u, "Stop ! Le mot de passe."] call AT_fnc_iarpDire) params ["_ack3", ""];
    [((_ack3 param [2, ""]) find "obéit") >= 0 || {((_ack3 param [2, ""]) find "refuse") >= 0}, format ["ordre du même message perdu : %1", _ack3 param [2, ""]]] call AT_fnc_check;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Cadre : un civil sans savoir ne parle jamais d'armes, d'insurgés, de mines ni de caches (vraies répliques du serveur).
["ia_rp_cadre_civil", {
    if (["comspec_sse_fnc_iarpUi", "comspec_sse_fnc_iarpAnswers"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_iarpIa;
    uiSleep 1;
    ["apply", [_u, ["normalize", createHashMapFromArray [["role", "civil"], ["talk", 80], ["fear", 90], ["bribe", 90]]] call comspec_sse_fnc_iarpProfile]] call comspec_sse_fnc_iarpProfile;
    ["open", [_u]] call comspec_sse_fnc_iarpUi;
    if !([[{ !isNull findDisplay 94600 }, 5] call AT_fnc_wait, "terminal « Parler à… » non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    private _watch = ["arme", "armes", "armee", "pick", "pickup", "mine", "mines", "minee", "explosif", "explosifs", "bombe", "insurge", "insurges", "rebelle", "rebelles",
        "cache", "caches", "technique", "milice", "combattants", "embuscade", "attentat"];
    {
        private _r = (([_u, _x] call AT_fnc_iarpDire) select 1) param [1, ""];
        private _toks = (_r call comspec_sse_fnc_iarpNorm) splitString " ";
        private _hit = _watch select { _x in _toks };
        [_hit isEqualTo [], format ["« %1 » -> « %2 » (%3)", _x, _r, _hit joinString ", "]] call AT_fnc_check;
    } forEach ["Avez-vous vu des hommes armés ?", "Il y a des mines sur la route ?", "Des insurgés dans le coin ?", "Une cache d'armes au village ?",
        "Vous avez vu un pick-up ?", "Qui commande ici ?", "Il s'est passé quoi hier ?", "C'est dangereux par ici ?"];
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// À venir : émotions des IA (peur des armes). Contrôle au niveau du code, sans attendre l'automate : arme braquée à 3 m,
// peur (fn_iarpReact « level ») qui monte avec la peur des armes, nulle sans elle (réglage global, puis réglage de l'IA
// qui l'emporte) alors que l'IA voit toujours l'arme ; une autre cause (menace dite, « press ») fait toujours monter la peur.
["ia_rp_peur_armes", {
    if (["comspec_sse_fnc_iarpReact", "comspec_sse_fnc_iarpProfile"] call AT_fnc_nil) exitWith {};
    if ((primaryWeapon player) isEqualTo "") then { player addWeapon "arifle_MX_F"; player addMagazine "30Rnd_65x39_caseless_mag"; };
    private _u = call AT_fnc_iarpIa;
    _u setPosATL (player getPos [3, getDir player]);
    _u setDir ((getDir player) + 180);
    private _gVars = ["comspec_sse_iarp_emoOn"] + ((["emoIds"] call comspec_sse_fnc_iarpProfile) apply { format ["comspec_sse_iarp_emo_%1", _x] });
    private _gOld = _gVars apply { missionNamespace getVariable [_x, true] };
    { missionNamespace setVariable [_x, true]; } forEach _gVars;
    player action ["WeaponInHand", player];
    player setDir (player getDir _u);
    uiSleep 3;
    private _lvl = { _u setVariable ["comspec_sse_iarpPress", nil]; _u setVariable ["comspec_sse_idShock", nil]; ["level", _u] call comspec_sse_fnc_iarpReact };
    private _aim = (["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) select 0;
    private _l1 = call _lvl;
    [_aim > 0 && {_l1 > 0}, format ["peur des armes active : braquée à 3 m, peur %1 (%2 arme(s) vue(s))", _l1, _aim]] call AT_fnc_check;
    missionNamespace setVariable ["comspec_sse_iarp_emo_armes", false];
    private _l2 = call _lvl;
    private _aim2 = (["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) select 0;
    [_l2 isEqualTo 0 && {_aim2 > 0}, format ["peur des armes décochée (global) : peur %1, arme toujours vue %2", _l2, _aim2]] call AT_fnc_check;
    _u setVariable ["comspec_sse_iarpPress", nil];
    ["press", [_u, 20, "menace (autotest)", player]] call comspec_sse_fnc_iarpReact;
    private _l3 = ["level", _u] call comspec_sse_fnc_iarpReact;
    [_l3 > 0, format ["peur des armes décochée : une menace dite fait toujours monter la peur (%1)", _l3]] call AT_fnc_check;
    missionNamespace setVariable ["comspec_sse_iarp_emo_armes", true];
    // Réglage de l'IA (menu Zeus « Émotions… ») : sans « armes », alors que le global la garde.
    ["profileTweak", [player, [_u], [["emotionsOn", true], ["emotions", ["peur", "nervosite", "colere", "mefiance", "soulagement"]]]]] call comspec_sse_fnc_iarpServer;
    uiSleep 1;
    private _l4 = call _lvl;
    [_l4 isEqualTo 0, format ["peur des armes décochée pour cette IA (global coché) : peur %1", _l4]] call AT_fnc_check;
    // Et l'inverse : l'IA garde la peur des armes alors que le global la décoche.
    missionNamespace setVariable ["comspec_sse_iarp_emo_armes", false];
    ["profileTweak", [player, [_u], [["emotions", ["peur", "armes"]]]]] call comspec_sse_fnc_iarpServer;
    uiSleep 1;
    private _l5 = call _lvl;
    [_l5 > 0, format ["réglage de l'IA prioritaire sur le global : peur %1", _l5]] call AT_fnc_check;
    // Automate des IA réalistes (identité SSE) : tension sans peur des armes plus basse qu'avec.
    if (!isNil "comspec_sse_fnc_idAiBrain") then {
        ["profileTweak", [player, [_u], [["emotions", ["peur", "nervosite", "colere", "mefiance", "soulagement"]]]]] call comspec_sse_fnc_iarpServer;
        uiSleep 1;
        _u setVariable ["comspec_sse_idTension", 0];
        for "_i" from 1 to 4 do { ["decide", [_u, "interpelle", true]] call comspec_sse_fnc_idAiBrain; };
        private _tNo = _u getVariable ["comspec_sse_idTension", 0];
        ["profileTweak", [player, [_u], [["emotions", "auto"]]]] call comspec_sse_fnc_iarpServer;
        missionNamespace setVariable ["comspec_sse_iarp_emo_armes", true];
        uiSleep 1;
        _u setVariable ["comspec_sse_idTension", 0];
        for "_i" from 1 to 4 do { ["decide", [_u, "interpelle", true]] call comspec_sse_fnc_idAiBrain; };
        private _tYes = _u getVariable ["comspec_sse_idTension", 0];
        [_tYes > _tNo + 5, format ["automate : tension braquée %1 avec la peur des armes, %2 sans", round _tYes, round _tNo]] call AT_fnc_check;
    };
    // IA neutre (« Tout décocher ») : peur nulle même braquée et menacée.
    ["profileTweak", [player, [_u], [["emotionsOn", false]]]] call comspec_sse_fnc_iarpServer;
    uiSleep 1;
    ["press", [_u, 20, "menace (autotest)", player]] call comspec_sse_fnc_iarpReact;
    private _l6 = ["level", _u] call comspec_sse_fnc_iarpReact;
    [_l6 isEqualTo 0, format ["IA neutre : peur %1 braquée et menacée", _l6]] call AT_fnc_check;
    { missionNamespace setVariable [_x, _gOld select _forEachIndex]; } forEach _gVars;
    player action ["WeaponOnBack", player];
    deleteVehicle _u;
    call AT_fnc_done;
}, 60] call AT_fnc_run;
