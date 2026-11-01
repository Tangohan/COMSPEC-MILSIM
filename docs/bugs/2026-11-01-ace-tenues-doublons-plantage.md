# Tenues ACE en double à l'infini et plantage de « Mes équipements » ACE

## Contexte

Panneau « Athena · Tenues » ajouté par le mod dans l'arsenal ACE (connect, `fn_arsenal*`), menu ACE
« COMSPEC Athéna > Tenues » (envoyer / charger / tout basculer).

## Symptôme

- « Mes équipements » ACE : les mêmes tenues répétées à l'infini après « tout télécharger ».
- Erreur ACE `fnc_fillLoadoutsList.sqf` ligne 101 : « Type Tableau, Chaîne attendu / 1 éléments fournis, 6 attendus ».
- Tenue enregistrée qui disparaît après fermeture / réouverture ; « Enfiler » via Athena presque jamais fonctionnel ;
  import du texte ACE d'Athena vu « non transmis ».

## Cause

1. **Doublons** : verrou d'import posé après l'appel réseau (`fn_arsenalPullAll`) → imports parallèles ; fusion
   interruptible qui ajoutait sans fusionner ; copies « nom · Athena » renvoyées puis réimportées (« · Athena · Athena »).
2. **Plantage ACE ligne 101** : chaque fonction écrivait la tenue à sa façon (tenue seule, `[[tenue, infos], infos]`…).
3. **Tenue qui disparaît** : une entrée abîmée arrête le remplissage de la liste ACE (10 entrées par image) ; les
   tenues ajoutées après n'apparaissent plus. Plusieurs fonctions remplaçaient aussi la liste par une copie filtrée.
4. **« Enfiler » / import** : texte du site avec guillemets doublés illisible par `parseSimpleArray` ; découpage
   faux des tenues > 8 Ko ; `setUnitLoadout` sans rafraîchir ACE.
5. **« Non transmise »** : comparaison du texte brut (format, casse, ordre) au lieu du contenu ; renommage
   « - » → « · » côté site.

## Correctif

- Menu ACE « COMSPEC Athéna > Tenues » (envoyer / charger / tout basculer) retiré.
- Panneau « Athena · Tenues » conservé et corrigé : une seule fonction écrit dans la liste ACE (`fn_arsenalAceStore`,
  écriture en place sans interruption, doublon même contenu retiré, même nom contenu différent → « nom (2) », entrées
  illisibles sauvegardées dans `COMSPEC_AceLoadoutsBackup`) ; import idempotent et verrouillé ; « Enfiler » refait le
  chargement d'ACE (verifyLoadout, CBA_fnc_setLoadout, refresh) ; état de synchro sur une empreinte du contenu (« cv1 »).
- Site : nom exact des tenues conservé, export ACE toujours `[tenue (10), infos]`.
- Réparation unique au démarrage (`fn_aceLoadoutsRepair`) : désemballage des entrées abîmées, format ancien
  converti, entrées illisibles et doublons (même nom) retirés. N'écrit le profil que si quelque chose change.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/` : `config.cpp`, `XEH_postInitClient.sqf`,
  `functions/fn_initACE.sqf`, `functions/fn_aceLoadoutsRepair.sqf` (nouveau), `functions/fn_arsenal*`

## Vérification

En jeu : profil avec les doublons → lancer une mission, ouvrir ACE Arsenal > Mes équipements : plus d'erreur,
une seule entrée par nom ; enregistrer une tenue, fermer / rouvrir : elle est toujours là.

## Statut

corrigé à vérifier en jeu (2.9.21)
