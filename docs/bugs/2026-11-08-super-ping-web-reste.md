# Super ping web : la ligne restait jusqu'à une suppression manuelle

- **Contexte** : audit des oublis du 08/11, « Idem supprime superping web à l'issu ».
- **Symptôme** : le pulse s'arrêtait après 7 s, mais le point et la ligne restaient sur la carte et dans `atak_pings`.
- **Cause** : aucun terme côté serveur ; la ligne ne partait que par `DELETE /api/pings/{id}`.
- **Correctif** : `GET /api/pings` retire un super ping 15 s après sa création (horloge de la base). `atak-pings.js` relit la liste tant qu'un super ping est affiché.
- **Marqueurs et tracés supprimés en jeu** : `fn_markerDelete` et `fn_markerWebSweep` envoient `deleted` à `POST /api/atak/marker`, et les trois cartes web retirent ce qui n'est plus dans la liste. Chaîne relue, pas de correctif. Limite : un repère ou une forme créés sur le web (`comspec_webmk_`, `COMSPEC_shape_`) ne se suppriment pas depuis le téléphone (`fn_markerDeletable` renvoie 0) ; cela relève du mod.
- **Fichiers touchés** : `app/Support/AtakSuperPing.php`, `app/Repositories/AtakDataRepository.php`, `public/assets/js/atak-pings.js`, `docs/technical/ICD-ATHENA-ATAK.md`.
- **Vérification** : `tests/Unit/AtakSuperPingExpiryTest.php`. Requête SQL et affichage non exécutés localement (pas de base).
- **Statut** : corrigé à vérifier en jeu.
