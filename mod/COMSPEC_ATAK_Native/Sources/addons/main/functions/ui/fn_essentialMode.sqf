/*
    Mode Essentiel (4.18.0) : un seul bouton réduit le téléphone aux apps de base, le même bouton remet tout.
    Rien n'est désinstallé : les apps masquées gardent leurs données, leurs messages, leurs ordres et leurs fonctions de fond,
    elles ne sont simplement plus proposées (accueil, apps regroupées, dock et rail de la carte, recherche, App Store en tête,
    apps épinglées sur la carte). Le filtre est posé une seule fois, à la source (fn_appVisible) ; un raccourci, une
    notification ou un outil de la carte qui ouvre une app masquée l'ouvre toujours (fn_navigate n'est pas touché).
    Apps du mode : voir la table ci-dessous. Messages, SMS et Radio n'y sont pas : les ordres arrivent par Tâches, les alertes
    et les messages de l'encadrement par Alertes, et la voix passe par la radio du jeu (ACRE2, TFAR) ; un chef de groupe qui
    veut garder Messages le met en « Apps obligatoires » (une app obligatoire reste affichée, le mode ne la masque pas).
    Réglages est toujours là (c'est la sortie du mode) ; Admin et Poste TOC gardent leur propre règle (droits, PC de TOC).
    Réglages CBA : comspec_atak_native_essential_mode (par joueur, ATAK · Affichage, coupé par défaut, gardé dans le profil par
    fn_setSetting) et comspec_atak_native_essential_force (serveur : imposé à tous ; le bouton est alors grisé avec une phrase).
    Params :
      ["on"]            -> le mode est actif (choix du joueur ou imposé)
      ["locked"]        -> imposé par le serveur (le joueur ne peut pas le couper)
      ["ids"]           -> apps du mode
      ["hides", id]     -> cette app est-elle masquée par le mode
      ["toggle"]        -> bascule le choix du joueur (rien si imposé) et redessine
      ["refresh"]       -> redessine le dock et la page ouverte, une fois par image (rappel du réglage CBA ou bascule)
      ["bar", [marge, y, largeur, taille du texte, petit format]] -> pose le bouton (et la phrase s'il est imposé) sur la page
                           ouverte ; retourne la hauteur prise, marge comprise
      ["rows"]          -> ligne « switch » de fn_formRender (Réglages et panneau de la carte), phrase d'aide comprise
    Aucune notification, aucun toast : tout se passe dans le téléphone.
*/
params [["_act", "on"], ["_a", []]];
// Table unique des apps du mode (identifiants de COMSPEC_ATAK_Apps, config.cpp).
private _essential = [
    "Map",       // carte
    "Gps",       // position, grille, altitude
    "Waypoints", // itinéraire guidé
    "Tasks",     // ordres et tâches reçus
    "Briefing",  // consignes et informations de la mission
    "Alerts",    // alertes tactiques et messages de l'encadrement
    "Drone",     // pilotage et caméra du drone
    "Livecam",   // vidéo en direct (drones du camp compris)
    "Settings"   // la sortie du mode : jamais masquée
];
// Apps qui suivent leur propre règle (droits de l'encadrement, PC de TOC) : le mode n'y touche pas.
private _own = ["Admin", "Toc"];
private _var = "comspec_atak_native_essential_mode";
private _forceVar = "comspec_atak_native_essential_force";
private _locked = { (missionNamespace getVariable [_forceVar, false]) isEqualTo true };
private _on = { call _locked || {(missionNamespace getVariable [_var, false]) isEqualTo true} };
private _tr = { _this call comspec_atak_native_fnc_tr };

switch (_act) do {
    case "on": { call _on };
    case "locked": { call _locked };
    case "ids": { +_essential };
    case "hides": {
        private _id = [_a, ""] select (_a isEqualType "");
        if !(call _on) exitWith { false };
        if (_id in _essential || {_id in _own}) exitWith { false };
        // Une app rendue obligatoire par l'encadrement reste affichée.
        (["state", _id] call comspec_atak_native_fnc_appInstall) isNotEqualTo "required"
    };
    case "toggle": {
        if (call _locked) exitWith { false };
        // fn_setSetting : réglage posé par CBA et gardé dans le profil, écriture du profil regroupée.
        [_var, (missionNamespace getVariable [_var, false]) isNotEqualTo true] call comspec_atak_native_fnc_setSetting;
        ["refresh"] call comspec_atak_native_fnc_essentialMode;
        true
    };
    case "refresh": {
        if (!hasInterface || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith { false };
        // Une seule fois par image : le rappel du réglage CBA et la bascule préviennent dans la même image.
        if ((uiNamespace getVariable ["COMSPEC_ATAK_EssentialAt", -1]) isEqualTo diag_frameNo) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_EssentialAt", diag_frameNo];
        // Même chemin que les apps imposées ou interdites : dock redessiné, puis la page ouverte.
        ["applied"] call comspec_atak_native_fnc_appInstall;
        true
    };
    case "bar": {
        _a params ["_pad", "_y", "_gw", "_font", ["_mini", false]];
        private _thm = [] call comspec_atak_native_fnc_theme;
        private _isLocked = call _locked;
        private _isOn = call _on;
        private _bh = _font * ([1.7, 1.3] select _mini);
        private _label = ["ESSENTIEL", "ESSENTIEL · ACTIF"] select _isOn;
        if (_isLocked) then { _label = "ESSENTIEL · IMPOSÉ"; };
        // Actif : vert plein comme les onglets actifs des apps ; inactif : fond neutre.
        private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _isOn, [_pad, _y, _gw, _bh], _label] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_font max COMSPEC_ATAK_FontMin);
        if (_isOn) then {
            _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95];
            _b ctrlSetTextColor [0.03, 0.05, 0.04, 1];
        } else {
            _b ctrlSetBackgroundColor (_thm get "surface2");
            _b ctrlSetTextColor (_thm get "text");
        };
        private _used = _bh + _pad * 0.5;
        if (_isLocked) then {
            // Imposé : bouton grisé, la phrase dit pourquoi (dans le téléphone, aucune fenêtre d'Arma).
            _b ctrlEnable false;
            private _n = ["COMSPEC_RscStructuredText", [_pad, _y + _bh, _gw, _font * 2.3]] call comspec_atak_native_fnc_pageCtrl;
            _n ctrlSetFontHeight ((_font * 0.85) max COMSPEC_ATAK_FontMin);
            _n ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _thm get "hexTextMuted", ("Imposé par l'encadrement pour toute la mission : tous les téléphones montrent les mêmes apps.") call _tr]; // i18n:brut (déjà traduit)
            _n ctrlEnable false;
            _used = _used + _font * 2.3;
        } else {
            _b ctrlSetTooltip ((["Réduit le téléphone aux apps de base : carte, GPS, ordres, drone", "Mode Essentiel actif : touchez pour afficher toutes les apps"] select _isOn) call _tr);
            _b ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_atak_native_fnc_essentialMode; }];
        };
        _used
    };
    case "rows": {
        private _isLocked = call _locked;
        [["switch", "Mode Essentiel", call _on, { ["toggle"] call comspec_atak_native_fnc_essentialMode; },
            ["Réduit le téléphone aux apps de base : carte, GPS, itinéraire, ordres, briefing, alertes, drone et vidéo en direct. Rien n'est désinstallé ni perdu.",
                "Imposé par l'encadrement pour toute la mission : tous les téléphones montrent les mêmes apps."] select _isLocked, _isLocked, "essentialMode"]]
    };
    default { false };
};
