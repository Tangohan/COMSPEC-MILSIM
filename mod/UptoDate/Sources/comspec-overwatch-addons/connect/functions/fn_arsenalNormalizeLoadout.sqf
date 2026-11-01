/*
    Tableau getUnitLoadout (10 éléments) d'une tenue sous n'importe quel format : texte, tenue seule, ACE étendue
    [tenue, infos], emballée une ou plusieurs fois, entrée [nom, données], HashMap. [] si illisible.
    Le déballage lui-même est fait par arsenalAceStore (« extended »), le seul endroit qui connaît le format ACE.
*/
params [["_data", []]];
(["extended", _data] call comspec_overwatch_connect_fnc_arsenalAceStore) param [0, []]
