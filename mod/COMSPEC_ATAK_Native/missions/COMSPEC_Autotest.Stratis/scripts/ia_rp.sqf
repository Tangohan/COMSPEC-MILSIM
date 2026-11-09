// SSE › IA RP : interprète et générateur (contrôles purs), terminal « Parler à… », réponse cohérente avec la couverture,
// pas de redite, journal, bulle 3D, /try, ordre simple ; atelier IA (profil par script, fenêtre du Zeus).
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
