/*
    Réparation des tenues ACE (profil du joueur, ace_arsenal_saved_loadouts) abîmées par les anciennes versions :
    tenue emballée une fois de trop, format inconnu, doublons à l'infini. ACE plantait sur ces entrées
    (fnc_fillLoadoutsList, « Type Tableau, Chaîne attendu ») et n'affichait plus rien après elles : une tenue
    enregistrée ensuite « disparaissait » à la réouverture de la liste.
    Lancée au démarrage et à chaque ouverture de l'arsenal ACE (arsenalInitOverlay), avant l'écran « Mes équipements ».
    Le travail est fait par arsenalAceStore (« repair ») : entrées lisibles par ACE laissées telles quelles, entrées
    abîmées reconstruites, illisibles mises de côté (COMSPEC_AceLoadoutsBackup), doublons de même contenu retirés,
    même nom mais contenu différent renommés « nom (2) ». N'écrit le profil que si quelque chose change.
    Paresseuse : la liste n'est repassée que si sa forme a changé depuis la dernière vérification de la session de jeu
    (nombre de tenues, noms, forme de chaque entrée : emballage en trop, infos en paires, doublons de nom). Avant, toute
    la liste était relue à chaque ouverture de l'arsenal, dans l'image de l'ouverture. Une tenue enregistrée, renommée
    ou supprimée entre deux ouvertures change la forme : la vérification repasse.
    Retour : true si la liste a été vérifiée, false si elle était inchangée.
*/
private _fnShape = {
    private _l = profileNamespace getVariable ["ace_arsenal_saved_loadouts", nil];
    if (isNil "_l") exitWith { [] };
    if !(_l isEqualType []) exitWith { [typeName _l] };
    _l apply {
        if (isNil "_x") then { "" } else {
            if (_x isEqualType [] && {(count _x) >= 2}) then {
                private _d = _x select 1;
                [_x select 0, count _x, if (_d isEqualType []) then { _d apply { if (isNil "_x") then { "" } else { typeName _x } } } else { typeName _d }]
            } else { typeName _x }
        }
    }
};
private _last = uiNamespace getVariable "COMSPEC_AceLoadoutsShape";
if (!isNil "_last" && {_last isEqualTo (call _fnShape)}) exitWith { false };
(["repair"] call comspec_overwatch_connect_fnc_arsenalAceStore) params [["_before", 0], ["_after", 0], ["_rebuilt", 0], ["_aside", 0], ["_renamed", 0]];
if (_rebuilt > 0 || {_aside > 0} || {_renamed > 0}) then {
    ["INFO", "ACE", format ["Tenues ACE réparées : %1 → %2 (%3 reconstruite(s), %4 en double ou illisible(s) mise(s) de côté, %5 renommée(s))", _before, _after, _rebuilt, _aside, _renamed]] call comspec_overwatch_connect_fnc_log;
};
// Forme relue après la réparation (elle a pu retirer ou renommer des entrées).
uiNamespace setVariable ["COMSPEC_AceLoadoutsShape", call _fnShape];
true
