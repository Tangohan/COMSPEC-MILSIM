/*
    Indique si le contrôle navigateur embarqué (type 106) est utilisable.
    Best effort : présence de la classe de base moteur + pas de désactivation forcée.
    L’ouverture réelle (createDialog / ctrlCreate) et le repli UI sont gérés dans onLoad.
*/
if (!hasInterface) exitWith { false };

if (missionNamespace getVariable ["comspec_overwatch_force_classic_tablet", false]) exitWith { false };

// Désactivation joueur via CBA (optionnel)
if (!(missionNamespace getVariable ["comspec_overwatch_webbrowser_enabled", true])) exitWith { false };

// Arma 2.14+ : RscWebBrowser / CT_WEBBROWSER
if (isClass (configFile >> "RscWebBrowser")) exitWith { true };
if (isClass (configFile >> "COMSPEC_RscWebBrowser")) exitWith { true };

// Laisser tenter l’ouverture : repli « Ouvrir sur le PC » / carte si échec
true
