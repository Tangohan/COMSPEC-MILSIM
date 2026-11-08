# Carte : images (PAA) des marqueurs et zones (rectangles) absentes depuis l'optimisation 3.0.1

- **Contexte** : retour du propriétaire, carte du téléphone, depuis la vague d'optimisation 3.0.1.
- **Symptôme** : images des marqueurs et zones n'apparaissent plus sur le téléphone alors qu'elles s'affichaient avant.
- **Relevé fait** : les commits `eeac27e01`, `626b51ea7`, `2a6a75309`, `a620b6901`, `1c6587d0e` et `074abd2db` (tout ce que 3.0.1 change dans la carte, les zones et les routes) ont été relus ligne à ligne contre la 3.0.0, avec contrôle de l'équilibre des parenthèses des fichiers modifiés. Sur le papier ils donnent le même résultat qu'avant (caches par classe et par révision, sorties anticipées équivalentes).
- **Cause : non établie.** Aucun commit fautif prouvé sans lancer Arma 3. Le seul code de ce lot qui touche l'image d'un marqueur est le cache de couleur et de halo des repères Athena (`fn_mapOnDraw`, clé « classe|L » ou « classe|D » dans la table partagée `COMSPEC_ATAK_MkColorCache`) ; les marqueurs du jeu (`fn_mapGameMarkers`) et les zones (`fn_zoneDraw`) n'ont pas changé en 3.0.1.
- **Ajouté** : une ligne de relevé toutes les 30 s dans le RPT (`[COMSPEC ATAK NATIVE][MAP] marqueurs du jeu : …`) qui compte les symboles, traits, rectangles et ellipses gardés. Si les rectangles ou symboles gardés sont à zéro alors que la mission en a, c'est la lecture qui est en cause ; sinon c'est le dessin.
- **Fichiers touchés** : `fn_mapGameMarkers.sqf` (relevé seulement).
- **Vérification** : à faire en jeu avec `-showScriptErrors` et le RPT, sur une mission avec marqueurs à image et rectangles, en 3.0.0 puis 3.1.0.
- **Statut** : identifié, cause non établie (diagnostic ajouté, pas de correctif).
