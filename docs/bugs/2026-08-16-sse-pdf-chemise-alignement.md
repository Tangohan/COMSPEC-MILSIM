# PDF dossier SSE — page de garde ≠ chemise écran

## Contexte
La chemise dossier à l’écran (`case_cover.php`) a un rendu papier (bandeaux classification, registre, canal protégé, tampon, grille, empreintes). Le PDF d’export gardait une couverture sombre minimaliste.

## Symptôme
Export PDF « dossier complet » : page 1 sans le même rendu que la chemise consultée dans Athena.

## Cause
`SseCasePdfService::coverHtml` générait un HTML TCPDF distinct, sans `SseDocumentMarkings`.

## Correctif
Page de garde PDF alignée sur la chemise : marques officielles, palette par classification, registre, canal, sceau, faits, objet, consignes, QR / empreintes, tampons ; bandeaux des pages suivantes harmonisés.

## Fichiers touchés
- `app/Services/Sse/SseCasePdfService.php`

## Vérification
Exporter le PDF d’un dossier Confidentiel : page 1 avec bandeau CONFIDENTIEL, registre, canal protégé, tampon UA, grille de faits.

## Statut
corrigé
