# Arrêt brutal — taille de tableau négative (7C2D2B58)

## Contexte

Pack du 15/09 (Athena 1.0.127) remis en jeu. Plusieurs arrêts à quelques
secondes d’intervalle, même sans ouvrir le téléphone. Sessions du 17/09
avec téléphone ouvert sur la carte : même dernière ligne, 30 à 50 secondes
après l’ouverture.

## Symptôme

Toujours la même dernière ligne avant l’exception :

`Error: can't resize AutoArray to negative size!`
puis ACCESS_VIOLATION à `7C2D2B58` dans `Arma3_x64.exe` (pas la liaison).

Les sessions qui se ferment normalement n’ont pas cette ligne.

## Cause

Le moteur calcule une longueur négative, échoue le redimensionnement, puis
déréférence quand même.

1. Lecture du journal de session trop large pour le tampon moteur.
2. Derniers événements de la frise : index de départ sous zéro s’il y a
   moins de quatre événements.
3. Parcours de la liste des repères **pendant** qu’un envoi ou une pose
   change cette liste (même fil d’exécution : la boucle appelle encore
   l’envoi, qui peut créer ou supprimer un repère).
4. Lecture des listes de repères du téléphone sans copie locale.
5. Recalcul de liaison en boucle (écarté comme récursion, resté comme
   charge). Les cartouches recréés en boucle : écarté (journal déjà
   « extra overlay retired » avant l’arrêt).

Les identifiants 9800 / 9801 répétés d’une application à l’autre ne sont
pas en cause : chaque page les lit dans son propre groupe.

Copier les listes d’indicatifs du téléphone puis écrire dans la copie
n’affiche plus les indicatifs : ces listes restent mutées en place.

## Correctif

- Lecture du journal limitée à 8 000 octets, copie bornée.
- Frise : copie locale, index de départ jamais négatif, géométrie nulle
  ignorée.
- Listes de repères figées avant parcours ; envoi après la copie.
- Listes utilisateur du téléphone copiées avant fusion.
- Indicatifs : un seul passage à la fois ; les listes d’origine restent
  écrites en place ; seuls les noms de repères et d’opérateurs sont copiés.
- Messagerie : pas de rafraîchissement si l’écran ou le groupe a disparu.

## Fichiers touchés

- Liaison (lecture du journal)
- `fn_createTimeline.sqf` et panneaux carte (`createLayerPanel`,
  `createInspector`, `createOperatorCard`, `createToolRail`, `createTopBar`)
- `XEH_postInitClient.sqf`
- `fn_athena_bridgeCtabMarkers.sqf`
- `fn_athena_relabelBft.sqf`
- `fn_athena_commsOnOpened.sqf`
- `fn_applyMapLayers.sqf`, `fn_selectMapEntity.sqf`, `fn_mapSearch.sqf`

## Vérification

Quitter Arma complètement, relancer. Ouvrir le téléphone sur la carte,
poser un repère, rester une à deux minutes. Si ça s’arrête encore : régler
Overwatch sur désactivé dans CBA, relancer.

## Statut

Corrigé (Athena 1.0.133 / Extension 2.0.44).
