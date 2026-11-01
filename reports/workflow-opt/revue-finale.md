# Revue finale : branche opt/workflow-sqf (tours 1 à 4)

Rien n'est commité et aucun PBO n'est construit. Les fichiers modifiés sont tous en UTF-8 CRLF (vérifié avec `git ls-files --eol`).

## Ce que fait le diff

112 fichiers changés : 289 lignes ajoutées, 2284 retirées. Il contient trois sortes de changements :
- moins de trafic réseau en multijoueur ;
- moins de travail à chaque image ;
- suppression de code mort, que rien n'appelle.

## Vérifications

- **Fonctions supprimées** : j'ai cherché chaque nom dans `mod/`, `app/`, `public/`, `views/`, `tools/` et les DLL C#, sans tenir compte de la casse. On ne les trouve plus que dans `mod/Workshop/_extract` (des archives) et dans des commentaires. Aucun appel dynamique du type `format "fnc_%1"` ne vise ces fonctions.
- **ACE** : les actions sur une personne sortent avant la lecture de `objectRoot`, et `_rootPerson` sert toujours à `addClass CAManBase`. Les actions qui consomment un kit lisent sans rien retirer (`kitUse` avec `_dryRun`, signature vérifiée). L'usage est ensuite retiré par `progressAction`, qui est appelé dans le même appel. Aucune fonction ACE n'est remplacée par une autre.
- **Tests PHP** : phpunit n'est pas installé, j'ai donc utilisé une petite copie des assertions. Les tests du worktree qui lisent des fichiers ont tourné, puis les mêmes tests sur un export de HEAD. Aucun échec nouveau ne touche un fichier modifié. Les échecs qui restent viennent de `app/`, `views/` et `public/`, ou des classes `App\` qui manquent ; ils existaient avant la branche.
- **hemtt check (ATAK natif)** : seules les 2 erreurs connues `ctrlWebBrowserAction` restent (fn_screenCast, fn_slideWebShow). Les avertissements sur les fichiers modifiés existaient déjà et ne portent pas sur les lignes changées.
- **connect et SSE** : les 42 fichiers SQF modifiés ont été copiés dans un projet hemtt temporaire. Ils sont tous lus par l'analyseur, sans erreur de syntaxe.

## Points relus en détail

- **Plan du camp en différences (planPatch / planFull)** : les ajouts vont en fin de liste et les remplacements restent à leur place. Le client refait donc la même liste que le serveur. La révision ne fait qu'augmenter (`clear` aussi fait +1), donc la nouvelle demande toutes les 3 s s'arrête toujours. Aucun gestionnaire `addPublicVariableEventHandler` ne lit ces variables : les lecteurs relisent la valeur à chaque passage. `planFull` peut renvoyer le plan d'un autre camp, mais tous les joueurs recevaient déjà tous les plans.
- **COMSPEC_ATAK_PhoneTag `[2, clientOwner]`** : les lecteurs sont le serveur (fn_captureServer, fn_adminServer), le joueur lui-même (fn_adminClient) et l'EH `Put` du joueur local (fn_linkAllyInit). Aucun autre client ne lit cette étiquette.
- **TocFeed par camp** : fn_tocScreen ne dessine un écran que si son camp est celui du joueur (`_sideOk`). fn_tocLaptop lit le camp du joueur.
- **Relais du suivi allié** : l'élection est la même que `_isBridge` (fn_initGpsBeacons) : joueur lié, UID le plus petit. Le serveur ajoute seulement `alive`.
- **Palette ECOTI mémorisée** : aucun lecteur ne modifie le tableau partagé. `ecotiNormalizeColor` renvoie un nouveau tableau, DrawBuilding copie avant de modifier, et DrawBadge copie `badge`.
- **Relecture complète des marqueurs toutes les 5 min** : les EH MarkerCreated, MarkerUpdated et MarkerDeleted couvrent le reste. Un style de trait qui arrive après la création du trait est toujours relu toutes les 2 s.

## Défauts corrigés à la revue

| Fichier | Défaut | Correction |
|---|---|---|
| natif map/fn_strokeDraw.sqf:19 | Un commentaire parlait encore d'un « relevé complet de 30 s ». | Remplacé par « 5 min », sans changer la logique. La fin de ligne CRLF a été remise après l'édition. |

## Points à décider

1. **Sang** : un relevé raté (« aucune crête exploitable ») ne retire plus d'usage du kit. C'est cohérent avec COMSPEC_SSE_KitLatent, mais c'est plus généreux qu'avant.
2. **isSyncableMapMarker** : les marqueurs au losange OTAN partent de nouveau vers Athena (avant, le `exitWith` était perdu). Le comportement est conforme à la doc, mais il ajoute du trafic Athena.
3. **getClientStateNumber** : ce garde, qui ne marchait pas avant, est maintenant actif dans fn_canShowWinMessageBox et fn_isPlayerSpawnStable. Il faut vérifier qu'un hôte non dédié renvoie bien 10 en jeu.
4. Les modèles i18n et de localisation citent encore des fichiers retirés. Il faut relancer `tools/i18n.py`.

## À tester en jeu

**Solo / hébergé**
- Plan du camp : tracer, modifier, supprimer, annuler, fusionner un plan enregistré. Le plan doit rester identique chez l'hôte.
- Carte ATAK et montre Garmin : filtres BFT appliqués, marqueurs ajoutés, déplacés ou supprimés vus tout de suite, traits épais et fléchés.
- ECOTI : couleurs des marqueurs et des pastilles. Le fondu ne doit pas s'accumuler (les pastilles ne deviennent pas transparentes avec le temps). Allumer puis éteindre l'éclairage de zone.
- SSE : tache de sang (examiner, empreintes, ADN : un usage retiré seulement à la fin de la barre, rien sur annulation). Empreintes latentes sur une arme au sol. Capture complète sans SEEK ni kits : refusée.
- ACE : menus SSE sur une personne et sur un objet, dogtags ACE, menus Diagnostic / Réparer du téléphone, y compris avec un téléphone détruit.

**Multijoueur (dédié, 2 clients ou plus, dont un en JIP)**
- Plan du camp : client B voit les traits de A. Un client JIP récupère le plan entier. Après une coupure courte, la resynchronisation se fait en quelques secondes au plus.
- Poste TOC : flux visible pour son camp. Rien n'est reçu par l'autre camp.
- Captures de téléphone et compromission : le serveur voit l'étiquette, une IA ramasse un téléphone posé au sol, l'admin voit la flotte.
- Distances du téléphone (fn_distanceMine) mises à jour quand on bouge.
- Suivi des IA alliées : le relais change à la mort ou à la déconnexion du joueur élu.
- Sang : les gouttes apparaissent chez les autres en 12 s environ. Un joueur JIP voit les anciennes traces (en 60 s au plus).
- Zone roleplay Zeus accrochée à un objet mobile : elle suit l'objet par pas d'environ 10 % du rayon.
- Brouillage Zeus : la liaison coupe pendant 45 s.
- Pylônes relais : les resynchros Zeus et le tableau de bord forcent l'envoi.
