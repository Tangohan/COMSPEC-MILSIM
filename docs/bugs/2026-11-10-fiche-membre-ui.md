# Fiche membre (back-office) : affichage illisible, photo impossible à joindre

## Contexte
Ressources › Effectifs › fiche d'un membre, surtout la rubrique « Identité & papiers » (PR #1527).

## Symptôme
- « Ajouter une photo » (Transmission en jeu, « Photo absente ») : texte gris sans lien ni formulaire.
- Absences : chaque ligne affichait « Du — au — », même avec des dates enregistrées.
- Historique : sources mêlées sans tri ; mobilité : statut brut (« pending »).
- Petites capitales grises au-dessus des valeurs, textes d'aide minuscules limités à la moitié de la carte.
- « Révoquer » seul sur une ligne, « Pays » décalé, « Générer » sans style, champ fichier brut, radios minuscules.
- Téléphone : bloc « Différence avec le jeu » coupé à droite, raccourcis du bandeau qui se chevauchent.
- « Envoyer la version Athena vers le jeu », présent sur chaque identité non envoyée, remplaçait le kit par défaut sans le dire. Cas observé : identité principale « Bloqué · Pas utilisée en jeu », identité d'emprunt « Prévue pour le jeu ». Seuls ce bouton (cliqué sur l'identité d'emprunt) ou « Composer un kit » produisent ce kit ; le code fait ce qu'il annonce, c'est le libellé qui trompe.

## Cause
- Aucune route d'envoi de photo depuis la fiche membre (seulement dans l'atelier SSE) ; le correctif proposé n'avait pas d'adresse.
- La vue lisait `starts_at` / `ends_at`, alors que la table a `starts_on` / `ends_on`.
- Feuilles de style : `max-width: 72ch`, libellés en capitales, `flex-basis: 100%` sur « Révoquer », classe `btn` absente du back-office, colonnes de grille sans `minmax(0, 1fr)`.
- Libellé du bouton d'envoi trompeur et action sans confirmation.

## Correctif
- `POST …/papiers/identites/{identityId}/photo` (fiche membre et bureau SSE) : même traitement que l'atelier (SseIdImageService), journal d'audit. Zone de dépôt avec aperçu sur chaque identité ; « Photo absente » ouvre le choix du fichier.
- Absences lues sur `starts_on` / `ends_on` (« depuis le … » si pas de fin, « annulée »). Historique trié, statut de mobilité traduit.
- Paires « libellé : valeur », chiffres clés avec le libellé sous la valeur, textes à taille de lecture sur toute la largeur.
- Transmission en jeu restructurée par identité ; bouton renommé « Utiliser cette identité en jeu », avec confirmation ; identité envoyée affichée ; bouton en double retiré.
- Couverture : étapes numérotées, chemins en `code`, grille de champs, sélecteur de fichier, vrai bouton « Générer ».

## Fichiers touchés
`app/Controllers/Admin/MemberIdentityPapersController.php`, `app/Services/Sse/MemberIdentitySyncService.php`, `routes/web.php`, `views/admin/effectifs_workspace/member.php`, `views/admin/effectifs_workspace/partials/member_papers*.php`, `views/partials/sse_cover_export.php`, `public/assets/css/back-office-{effectifs-fiche,member-papers,effectifs-invitations,effectifs-maquette}.css`, `public/assets/js/member-papers.js`, `lang/en/site_auto.php`.

## Vérification
Rendu local avec données factices (Edge sans tête), thèmes clair et sombre, 1440, 900 et 390 px : aucun débordement horizontal. phpunit (filtre Effectifs, Member, Identity, Sse, Untranslated, BackOffice) : seuls les échecs connus (PDO sqlite absent, fins de ligne, chat vanilla).

## Statut
corrigé ; envoi réel d'une photo à vérifier en ligne.
