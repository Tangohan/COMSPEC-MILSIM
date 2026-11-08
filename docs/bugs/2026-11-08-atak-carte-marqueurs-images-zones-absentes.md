# Carte : images (PAA) des marqueurs et zones (rectangles) absentes

- **Contexte** : retour du propriétaire, carte du téléphone, depuis la 3.0.1.
- **Symptôme** : images des marqueurs et zones absentes de la carte du téléphone alors qu'elles s'affichaient avant.
- **Cause prouvée par lecture** : `fn_mapColors` range dans la palette un code `lift` qui lit `_vivid`, variable locale de `fn_mapColors`. Ce code est appelé plus tard depuis `fn_mapGameMarkers` et `fn_mapOnDraw`, où `_vivid` n'existe pas (SQF n'a pas de fermeture). Sur fond SOMBRE, NUIT, SATELLITE, CLASSIQUE ou HYBRIDE, la première lecture d'un marqueur s'arrête donc en erreur de script, le cache n'est jamais écrit et l'erreur revient à chaque image. Présent depuis 2.9.40. Sur fond TOPO et CLAIR, `lift` est un autre code : rien ne se passe.
- **Supposé** : ce bug est le retour du propriétaire s'il utilise un de ces fonds. L'erreur arrête aussi la suite de `fn_mapOnDraw` (unités, repères, zones de l'app), ce qui va au-delà du symptôme décrit : à trancher en jeu avec le fond utilisé.
- **Écarté par lecture** : lots 3.0.1 (relus en 3.1.0, équivalents) ; lecture et dessin des rectangles et symboles en TOPO ; jetons de thème `COMSPEC_ATAK_Th` tous définis (`hexMap*` compris) ; `fn_mapOverlayUpdate` et `fn_pageMap` : couleurs seulement ; `CfgMarkers` : le mod n'en définit pas. Non écarté : `ctrlSetBackgroundColor` posé par le thème sur le groupe de contenu 88531, au-dessus de la carte (effet moteur non vérifiable hors jeu ; il masquerait toute la carte, pas seulement les marqueurs).
- **Correctif** : table `vivid` rangée dans la palette, lue par `lift`. Relevé RPT `[MAP] marqueurs du jeu` conservé.
- **Fichiers touchés** : `functions/map/fn_mapColors.sqf`, `functions/map/fn_mapGameMarkers.sqf` (relevé).
- **Vérification** : en jeu avec `-showScriptErrors`, mission à marqueurs image et rectangles : fond TOPO puis SOMBRE, NUIT, CLASSIQUE, HYBRIDE ; symboles, rectangles et unités visibles, aucune erreur `_vivid`. Si absents en TOPO : joindre la ligne `[MAP] marqueurs du jeu` du RPT.
- **Statut** : corrigé à vérifier en jeu.
