/*
    Contrôle Zeus des ATAK, côté Zeus. Params : [action, arguments]
      "catalog"  : actions proposées [[id, groupe, libellé, aide, module seulement], ...] (menu ZEN et module « ATAK · Contrôle Zeus »)
      "players" [objets]  : joueurs d'une sélection (fantassins et équipages des véhicules)
      "apply" [id, joueurs, intensité 1-100 (-1 = valeur par défaut), durée s (0 = jusqu'à annulation), message]
                 : envoie l'action au serveur (fn_zeusServer) ; les actions à message ouvrent un formulaire s'il manque
      "feedback" [texte] : message dans l'interface Zeus (ZEN, sinon message de curateur vanilla)
      "register" : menu clic droit ZEN « ATAK · Contrôle Zeus » sur un ou plusieurs joueurs (XEH_postInitClient)
    Sans ZEN : modules Zeus « ATAK · Contrôle Zeus » et « ATAK · Brouilleur de zone » (fn_moduleZeusControl,
    fn_moduleZeusJammer), qui marchent aussi sur un rayon ou sur tous les joueurs.
*/
params [["_act", ""], ["_args", []]];
switch (_act) do {
    case "catalog": {
        [
            ["off_on", "Accès", "Désactiver l'ATAK", "Le téléphone s'éteint et ne se rallume plus jusqu'à la réactivation."],
            ["off_off", "Accès", "Réactiver l'ATAK", "Annule la désactivation."],
            ["lock_on", "Accès", "Verrouiller avec un message…", "Écran rouge verrouillé avec votre message ; le téléphone reste inutilisable. Durée réglable."],
            ["lock_off", "Accès", "Déverrouiller", "Retire le verrou à distance."],
            ["mute_on", "Son", "Couper les haut-parleurs", "Plus de musique, de haut-parleur, de sons Bluetooth, de sons de vibration ni de clics."],
            ["mute_off", "Son", "Rétablir les haut-parleurs", "Annule la coupure des haut-parleurs."],
            ["warn", "Punir", "Avertissement…", "Bandeau rouge en plein écran avec votre texte, bip d'alarme."],
            ["msg", "Punir", "Message du commandement…", "Bandeau bleu en plein écran et notification avec votre texte."],
            ["glitch", "Punir", "Écran qui bugue (30 s)", "Parasites et bandes de couleur sur l'écran du téléphone."],
            ["noise", "Punir", "Alarme stridente (20 s)", "Le téléphone sonne fort : les joueurs à 60 m l'entendent."],
            ["buzz", "Punir", "Vibrations à répétition", "Le téléphone vibre une dizaine de fois."],
            ["jam30", "Brouiller", "Brouillage léger (30 %)", "Débit réduit, données qui traînent."],
            ["jam70", "Brouiller", "Brouillage fort (70 %)", "Presque plus de réseau, GPS qui dérive."],
            ["jam100", "Brouiller", "Brouillage total (100 %)", "Aucun réseau, GPS très imprécis."],
            ["jam", "Brouiller", "Brouiller à l'intensité choisie", "Brouillage ciblé de 1 à 100 %.", true],
            ["jam0", "Brouiller", "Arrêter le brouillage", "Retire le brouillage ciblé (les brouilleurs de zone restent)."],
            ["crack", "Dégâts et effets", "Fêler l'écran", "Ajoute des fêlures (intensité = gravité)."],
            ["emp", "Dégâts et effets", "Impulsion électromagnétique (60 s)", "Extinction, pixels morts, GPS et antenne touchés, batterie -30 %."],
            ["heat1", "Dégâts et effets", "Surchauffe : chaud", "Voile orange, batterie qui chauffe."],
            ["heat2", "Dégâts et effets", "Surchauffe : brûlant", "L'écran saute, la batterie fond."],
            ["heat3", "Dégâts et effets", "Surchauffe : arrêt de sécurité", "Le téléphone s'éteint jusqu'au refroidissement."],
            ["heat0", "Dégâts et effets", "Refroidir", "Fin de la surchauffe."],
            ["drain", "Dégâts et effets", "Décharge rapide de la batterie", "La batterie perd 5 % par minute en plus (intensité / 10 %)."],
            ["drain0", "Dégâts et effets", "Arrêter la décharge", "Fin de la décharge forcée."],
            ["bat0", "Dégâts et effets", "Vider la batterie", "Batterie à 0 % : le téléphone s'éteint."],
            ["bat100", "Dégâts et effets", "Recharger la batterie", "Batterie à 100 %."],
            ["batset", "Dégâts et effets", "Batterie au niveau choisi", "Batterie réglée sur l'intensité (0-100 %).", true],
            ["reboot", "Dégâts et effets", "Redémarrer le téléphone", "Arrêt puis écran de démarrage."],
            ["blood", "Dégâts et effets", "Couvrir l'écran de sang", "Éclaboussures, traînées et coulures sur l'écran et la coque (intensité = quantité)."],
            ["clean", "Dégâts et effets", "Nettoyer l'écran", "Retire d'un coup la poussière, les traces et le sang de l'écran et de la coque."],
            ["destroy", "Dégâts et effets", "Détruire l'appareil", "Écran éclaté, inutilisable jusqu'à réparation ou remplacement."],
            ["repair", "Dégâts et effets", "Réparer l'appareil", "Efface les dégâts (écran, composants)."],
            ["reset", "Tout", "Tout rétablir", "Retire tous les effets Zeus et répare le téléphone."]
        ]
    };
    case "players": {
        private _out = [];
        {
            if (_x isEqualType objNull && {!isNull _x}) then {
                if (_x isKindOf "CAManBase") then { if (isPlayer _x) then { _out pushBackUnique _x; }; } else { { if (isPlayer _x) then { _out pushBackUnique _x; }; } forEach (crew _x); };
            };
        } forEach _args;
        _out
    };
    case "feedback": {
        private _t = (_args param [0, ""]) call comspec_atak_native_fnc_tr;
        if (!isNil "zen_common_fnc_showMessage") exitWith { [_t] call zen_common_fnc_showMessage; };
        [getAssignedCuratorLogic player, _t] call BIS_fnc_showCuratorFeedbackMessage;
    };
    case "apply": {
        _args params [["_id", ""], ["_units", []], ["_int", -1], ["_dur", 0], ["_msg", ""]];
        if (_units isEqualTo []) exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        // Actions à message : formulaire si le texte manque.
        if (_id in ["lock_on", "warn", "msg"] && {_msg isEqualTo ""}) exitWith {
            private _title = (createHashMapFromArray [["lock_on", "Verrouiller l'ATAK"], ["warn", "Avertissement"], ["msg", "Message du commandement"]]) get _id;
            // Le menu clic droit ZEN tient encore l'affichage : le formulaire s'ouvre juste après.
            [{
                params ["_title", "_id", "_units", "_int"];
                [_title, [["EDIT", "Message", ""], ["SLIDER", "Durée (min, 0 = jusqu'à annulation)", [0, 120, 0]]], {
                    params ["_vals", "_a"];
                    _a params ["_id", "_units", "_int"];
                    private _m = (_vals select 0) trim [" ", 0];
                    if (_m isEqualTo "") then { _m = ["Comportement signalé au commandement.", "Contactez votre chef de groupe."] select (_id isEqualTo "lock_on"); };
                    ["apply", [_id, _units, _int, (_vals select 1) * 60, (_m) call comspec_atak_native_fnc_tr]] call comspec_atak_native_fnc_zeusMenu;
                }, [_id, _units, _int], format ["%1 joueur(s) : %2", count _units, (_units apply { name _x }) joinString ", "]] call comspec_atak_native_fnc_zeusForm;
            }, [_title, _id, _units, _int], 0.15] call CBA_fnc_waitAndExecute;
        };
        _msg = _msg trim [" ", 0];
        private _i = [_int, 50] select (_int < 0);
        private _send = { params ["_a", "_x"]; ["comspec_atak_native_zeus", [player, _a, _x]] call CBA_fnc_serverEvent; };
        private _set = { params ["_k", "_v"]; ["set", [_units, _k, _v, _dur]] call _send; };
        private _once = { params ["_k", ["_v", []]]; ["once", [_units, _k, _v]] call _send; };
        switch (_id) do {
            case "off_on": { ["off", true] call _set; };
            case "off_off": { ["off", false] call _set; };
            case "lock_on": { ["lock", _msg] call _set; };
            case "lock_off": { ["lock", ""] call _set; };
            case "mute_on": { ["mute", true] call _set; };
            case "mute_off": { ["mute", false] call _set; };
            case "warn": { ["warn", [_msg]] call _once; };
            case "msg": { ["msg", [_msg]] call _once; };
            case "glitch": { ["glitch", [[30, _dur] select (_dur > 0)]] call _once; };
            case "noise": { ["noise", [[20, _dur] select (_dur > 0)]] call _once; };
            case "buzz": { ["buzz", [10]] call _once; };
            case "jam30": { ["jam", 30] call _set; };
            case "jam70": { ["jam", 70] call _set; };
            case "jam": { ["jam", (_i max 1) min 100] call _set; };
            case "jam100": { ["jam", 100] call _set; };
            case "jam0": { ["jam", 0] call _set; };
            case "crack": { ["crack", [[40, _int] select (_int > 0)]] call _once; };
            case "emp": { ["emp", [[60, _dur] select (_dur > 0)]] call _once; };
            case "heat1": { ["heat", 1] call _set; };
            case "heat2": { ["heat", 2] call _set; };
            case "heat3": { ["heat", 3] call _set; };
            case "heat0": { ["heat", 0] call _set; };
            case "drain": { ["drain", [5, (_int / 10) max 1] select (_int > 0)] call _set; };
            case "drain0": { ["drain", 0] call _set; };
            case "bat0": { ["battery", [0]] call _once; };
            case "bat100": { ["battery", [100]] call _once; };
            case "batset": { ["battery", [(_i max 0) min 100]] call _once; };
            case "reboot": { ["reboot"] call _once; };
            case "blood": { ["blood", [[50, _int] select (_int > 0)]] call _once; };
            case "clean": { ["clean"] call _once; };
            case "destroy": { ["destroy"] call _once; };
            case "repair": { ["repair"] call _once; };
            case "reset": { ["reset", [_units]] call _send; };
        };
        private _label = ((["catalog"] call comspec_atak_native_fnc_zeusMenu) select { (_x select 0) isEqualTo _id }) param [0, ["", "", _id]];
        ["feedback", [format ["ATAK » %1 : %2", (_units apply { name _x }) joinString ", ", ((_label select 2) call comspec_atak_native_fnc_tr)]]] call comspec_atak_native_fnc_zeusMenu;
    };
    case "register": {
        if (!hasInterface || {isNil "zen_context_menu_fnc_addAction"} || {missionNamespace getVariable ["COMSPEC_ATAK_ZeusMenuOn", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusMenuOn", true];
        // Joueurs visés : sélection Zeus (fantassins, équipages), sinon l'unité sous la souris.
        private _targets = {
            params ["", ["_objs", []], "", "", "", ["_hover", objNull]];
            private _u = ["players", _objs] call comspec_atak_native_fnc_zeusMenu;
            if (_u isEqualTo [] && {_hover isEqualType objNull}) then { _u = ["players", [_hover]] call comspec_atak_native_fnc_zeusMenu; };
            _u
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusTargets", _targets];
        private _icon = "\z\comspec_atak_native\addons\main\data\app_ew.paa";
        private _root = ["comspec_atak_zeus", "ATAK · Contrôle Zeus", _icon, {}, { (_this call (missionNamespace getVariable "COMSPEC_ATAK_ZeusTargets")) isNotEqualTo [] }] call comspec_atak_native_fnc_zenAction;
        [_root, [], 7] call zen_context_menu_fnc_addAction;
        private _groups = [];
        {
            _x params ["_id", "_grp", "_label", "", ["_modOnly", false]];
            if (_modOnly) then { continue; };
            if !(_grp in _groups) then {
                _groups pushBack _grp;
                private _gid = format ["comspec_atak_zeus_g%1", (count _groups) - 1];
                private _g = [_gid, _grp, "", {}, { true }] call comspec_atak_native_fnc_zenAction;
                [_g, ["comspec_atak_zeus"], 10 - (count _groups)] call zen_context_menu_fnc_addAction;
            };
            private _gid = format ["comspec_atak_zeus_g%1", _groups find _grp];
            private _a = [format ["comspec_atak_zeus_%1", _id], _label, "", {
                private _u = _this call (missionNamespace getVariable "COMSPEC_ATAK_ZeusTargets");
                ["apply", [_this param [6, ""], _u]] call comspec_atak_native_fnc_zeusMenu;
            }, { true }, _id] call comspec_atak_native_fnc_zenAction;
            [_a, ["comspec_atak_zeus", _gid], 100 - _forEachIndex] call zen_context_menu_fnc_addAction;
        } forEach (["catalog"] call comspec_atak_native_fnc_zeusMenu);
    };
};
