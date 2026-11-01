/*
    Exécute _code avec _args. Ancienne mesure de performance retirée (aucun écran ne lisait les résultats) ;
    l'enveloppe reste pour les appels de fn_startSyncLoops. _name est ignoré.
    Params : [_code, _args, _name]
*/
params [["_code", {}, [{}]], ["_args", [], [[]]]];
_args call _code
