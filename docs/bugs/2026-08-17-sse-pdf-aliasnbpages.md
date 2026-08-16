# PDF dossier SSE — AliasNbPages() introuvable

## Contexte

Export PDF d’un dossier SSE : `GET /atak/sse/dossiers/{id}/pdf`
(corrélation prod `4b8706af08a69cae`).

## Symptôme

Exception : `Call to undefined method TCPDF@anonymous::AliasNbPages()`  
→ page d’erreur / e-mail d’alerte technique.

## Cause

`SseCasePdfService` appelait `$pdf->AliasNbPages()`, méthode FPDF héritée
**absente** de TCPDF actuel. Le pied de page utilisait déjà correctement
`getAliasNumPage()` / `getAliasNbPages()`.

## Correctif

Suppression de l’appel `AliasNbPages()` — les alias TCPDF restent actifs
via le `Footer()` personnalisé.

## Fichiers touchés

- `app/Services/Sse/SseCasePdfService.php`

## Vérification

Ouvrir `/atak/sse/dossiers/2/pdf` (ou autre dossier) : PDF téléchargé,
pagination « Page x / y » en bas.

## Statut

Corrigé
