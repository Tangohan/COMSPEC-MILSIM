# Route suivie visible dans F-PANO ECOTI

- **Contexte** : demande du propriétaire : « connecter notre ATAK aux mods ECOTI et pouvoir, via la carte, voir l'itinéraire ».
- **Symptôme** : une route tracée sur la carte du téléphone n'apparaît pas dans la vue F-PANO ECOTI, sauf si l'option « Carte d'Arma et Athena » est cochée.
- **Mods trouvés** (lus dans `F:\SteamLibrary\steamapps\workshop\content\107410`) :
  - **FPANO ECOTI** : Workshop 3759527903, `FPANO_ECOTI.pbo`, aussi dans « # S.O.A.R - CLIENT » (3026465844). Classe `FPANO_ECOTI_PATCH`. Aucune fonction publique et aucun itinéraire. Il relit `allMapMarkers` chaque seconde sous JVN et affiche tout marqueur doté d'un texte.
  - **ECOTI by TFN** : `ECOTI_TFN.pbo` dans le pack S.O.A.R (`whale_ecoti_llll`). Fusion thermique et contours, sans marqueurs ni routes : aucune intégration possible, aucune écrite.
- **Supposé** : le propriétaire pensait à F-PANO ECOTI. À corriger s'il s'agit d'un autre mod.
- **Cause** : F-PANO ne lit que des marqueurs, et les étapes d'une route n'en sont pas par défaut.
- **Correctif** : `fn_routeEcoti` pose les 8 prochaines étapes de la route suivie en marqueurs locaux d'opacité 0, préfixe `comspec_tabletmk_` (exclu de l'envoi vers Athena), de type `FPANO_marker_POI`. Seule la route suivie est concernée. Sous F-PANO, `fn_route3d` ne dessine que le trait, pour ne pas doubler les étiquettes.
- **Repli** : mod absent ou réglage `comspec_atak_native_route_fpano` décoché : aucun marqueur, `fn_route3d` inchangé. Aucune entrée `requiredAddons`.
- **Fichiers touchés** : `functions/route/fn_routeEcoti.sqf`, `functions/route/fn_route3d.sqf`, `config.cpp`, `XEH_preInit.sqf`, `i18n/en.txt`, `i18n/zh_cn.txt`.
- **Vérification** : `hemtt check` ; à tester en jeu (voir PR #1395). L'affichage repose sur le code actuel de F-PANO : une mise à jour de ce mod peut le changer.
- **Statut** : ajouté, à vérifier en jeu.
