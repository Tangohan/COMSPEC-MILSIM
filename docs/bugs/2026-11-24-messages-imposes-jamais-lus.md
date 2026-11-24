# Messages imposés jamais « lus »

- Contexte : fiche joueur, carte « Messages imposés ».
- Symptôme : messages à « Affiché en jeu », un sur quatre « Lu par le joueur ».
- Cause : le bandeau du téléphone (fn_fullAlert) n'avait qu'un OK sans accusé ; seul COMPRIS dans l'app Alertes envoyait AdminControl.Ack « acked ». Les messages sans validation demandée ne sont jamais acquittés.
- Correctif : OK du bandeau valide l'avis (ATAK natif 4.32.2) ; libellés de la fiche clarifiés.
- Fichiers touchés : fn_fullAlert.sqf, fn_adminNotices.sqf, views/admin/atak-fleet/show.php.
- Vérification : test unitaire de câblage ; rien testé en jeu.
- Statut : corrigé à vérifier en jeu
