# SSE — compression auto des images au-delà de 5 Mo

## Contexte

Création d’objet SSE et dépôt de preuves : limite utile de stockage ~5 Mo.

## Symptôme

Une image un peu trop lourde était simplement refusée (`5 Mo maximum`), sans tentative de réduction.

## Cause

`storeObjectImageUpload()` rejetait tout fichier `> 5_000_000` octets. Aucun pipeline de compression.

## Correctif

- Service `ImageCompressionService` : accepte jusqu’à 25 Mo à l’envoi, redimensionne (bord max 2048 px) et recompresse en JPEG jusqu’à ≤ 5 Mo.
- Branché sur création d’objet et preuves de dossier.
- Côté formulaire objet : compression navigateur avant envoi si > 5 Mo (repli serveur sinon).
- Libellés UX mis à jour (pas de jargon technique).

## Fichiers touchés

- `app/Services/Media/ImageCompressionService.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/object_create.php`

## Vérification

1. Joindre une image < 5 Mo → enregistrée telle quelle.
2. Joindre une image 6–15 Mo → message de compression, fichier stocké ≤ ~5 Mo.
3. Image > 25 Mo → refus explicite.

## Statut

corrigé
