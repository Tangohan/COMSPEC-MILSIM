# Carte : mention « PERDU » sur les marqueurs, texte illisible à mi-zoom

- **Contexte** : retours du propriétaire sur la carte du téléphone.
- **Symptôme** : marqueurs et étiquettes marqués « PERDU » ; textes difficiles à lire en zoom moyen.
- **Cause prouvée par lecture (PERDU)** : deux sources. `fn_lockAction` renommait pour tout le camp le marqueur d'une cible LOCK sortie du champ (« LOCK 098 · nom perdu 15:30 ») ; `fn_mapLabels` ajoutait « PERDU » ou « ANCIEN » devant la ligne 2 des pistes d'unités anciennes. Le propriétaire soupçonnait la première, sans certitude : les deux sont retirées.
- **Supposé (mi-zoom)** : pas de cause unique. Tailles relevées : étiquettes réduites jusqu'à 0,88 en vue large (`_tsL`), lieux-dits à 0,020 en police fine et détourée, noms de repères à 0,020 téléphone en main. Le rendu réel est à juger en jeu.
- **Correctif** : marqueur LOCK perdu seulement estompé, nom gardé ; pistes anciennes seulement estompées (alpha déjà présent) ; état toujours lisible dans l'app Drone. Étiquettes jamais réduites sous 1 ; lieux-dits à 0,024 en gras, villages à 0,028 ; repères à 0,023 en main et 0,025 porté. Coût inchangé.
- **Fichiers touchés** : `functions/drone/fn_lockAction.sqf`, `functions/map/fn_mapLabels.sqf`, `functions/map/fn_mapOnDraw.sqf`, `functions/map/fn_mapPlaceText.sqf`, `functions/map/fn_mapMarkerText.sqf`.
- **Vérification** : en jeu : marquer une cible au drone puis la perdre : nom inchangé et estompé, aucun « PERDU » sur la carte ; un coéquipier déconnecté : symbole estompé sans mention ; zoomer et dézoomer sur une ville : noms lisibles sans chevauchement excessif.
- **Statut** : corrigé à vérifier en jeu.
