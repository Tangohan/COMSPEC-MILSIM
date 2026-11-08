# Carte : symboles illisibles au dézoom, traits de la carte du jeu parfois absents

- **Contexte** : retour du propriétaire, carte du téléphone.
- **Symptôme** : au dézoom plus aucun marqueur lisible ; traits tracés sur la carte classique du jeu non affichés.
- **Cause supposée (non confirmée)** : symboles du jeu tous dessinés au dézoom (tas illisible) ; un trait tracé sur la carte du jeu est créé vide puis rempli sans nouvel événement de marqueur, donc son entrée (`fn_mapGameMarkers`) restait vide jusqu'à la relecture complète de 5 min.
- **Correctif** : au dézoom (échelle de 0,12 et plus), un symbole par case de 1/16 de la vue avec « +n » pour les autres. Traits créés sans points : revus toutes les 0,5 s pendant 20 s, puis abandonnés jusqu'à la relecture complète.
- **Fichiers touchés** : `fn_mapGameMarkers.sqf`.
- **Vérification** : à tester en jeu, dézoomer sur une mission à nombreux marqueurs ; tracer un trait sur la carte du jeu (canal direct) et ouvrir le téléphone : le trait apparaît en une ou deux secondes.
- **Non traité** : lisibilité du texte à mi-zoom (aucune cause établie ; les noms restent masqués au dézoom, échelle 0,3).
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
