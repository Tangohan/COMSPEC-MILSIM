/*
    Boucle périodique des postInit (client et serveur, une cinquantaine) avec un décalage de départ tiré au hasard
    (5 s au plus) au lieu de toutes à la même image : avant, les boucles de 1, 2, 5 et 10 s tombaient ensemble à chaque
    multiple de 10 s (à-coup régulier) et toutes partaient dans les premières secondes de l'arrivée en jeu.
    Mêmes paramètres que CBA_fnc_addPerFrameHandler ; ne renvoie pas d'identifiant (aucun appelant ne le garde).
    Fonction CfgFunctions, donc finale : le moteur refuse de la réécrire. Elle remplace la variable globale
    COMSPEC_ATAK_PfhSpread (non finale) des versions précédentes.
    Params : [code, cadence s (0 = chaque image), arguments]
*/
params [["_code", {}, [{}]], ["_delay", 0, [0]], ["_args", [], [[]]]];
if (_delay <= 0) exitWith { [_code, _delay, _args] call CBA_fnc_addPerFrameHandler; nil };
[{ _this call CBA_fnc_addPerFrameHandler; }, [_code, _delay, _args], random (_delay min 5)] call CBA_fnc_waitAndExecute;
nil
