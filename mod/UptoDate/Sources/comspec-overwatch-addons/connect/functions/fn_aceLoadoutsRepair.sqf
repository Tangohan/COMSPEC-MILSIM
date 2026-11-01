/*
    Réparation des tenues ACE (profil du joueur, ace_arsenal_saved_loadouts) abîmées par les anciennes versions :
    tenue emballée une fois de trop, format inconnu, doublons à l'infini. ACE plantait sur ces entrées
    (fnc_fillLoadoutsList, « Type Tableau, Chaîne attendu ») et n'affichait plus rien après elles : une tenue
    enregistrée ensuite « disparaissait » à la réouverture de la liste.
    Lancée au démarrage et à chaque ouverture de l'arsenal ACE (arsenalInitOverlay), avant l'écran « Mes équipements ».
    Le travail est fait par arsenalAceStore (« repair ») : entrées lisibles par ACE laissées telles quelles, entrées
    abîmées reconstruites, illisibles mises de côté (COMSPEC_AceLoadoutsBackup), doublons de même contenu retirés,
    même nom mais contenu différent renommés « nom (2) ». N'écrit le profil que si quelque chose change.
*/
(["repair"] call comspec_overwatch_connect_fnc_arsenalAceStore) params [["_before", 0], ["_after", 0], ["_rebuilt", 0], ["_aside", 0], ["_renamed", 0]];
if (_rebuilt > 0 || {_aside > 0} || {_renamed > 0}) then {
    ["INFO", "ACE", format ["Tenues ACE réparées : %1 → %2 (%3 reconstruite(s), %4 en double ou illisible(s) mise(s) de côté, %5 renommée(s))", _before, _after, _rebuilt, _aside, _renamed]] call comspec_overwatch_connect_fnc_log;
};
