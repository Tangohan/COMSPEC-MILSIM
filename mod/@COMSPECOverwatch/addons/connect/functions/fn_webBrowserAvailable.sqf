/*
    Indique si le contrôle navigateur embarqué (type 106) est utilisable.
    Best effort : présence de la classe de base moteur + pas de désactivation forcée.
*/
if (!hasInterface) exitWith { false };

if (missionNamespace getVariable ["comspec_overwatch_force_classic_tablet", false]) exitWith { false };

// Désactivation joueur via CBA (optionnel)
if (!(missionNamespace getVariable ["comspec_overwatch_webbrowser_enabled", true])) exitWith { false };

// Sur builds trop anciennes, la classe peut manquer
private _ok = isClass (configFile >> "RscDisplayEmpty");
// Heuristique : si le moteur expose déjà RscWebBrowser, on tente
if (isClass (configFile >> "RscWebBrowser")) exitWith { true };

// Sinon on laisse tenter l’ouverture (fallback géré dans webBrowserShow / onLoad)
true
