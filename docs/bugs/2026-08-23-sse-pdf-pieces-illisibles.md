# PDF dossier SSE — pièces rédactionnelles illisibles

## Contexte

Pages « Flash opérationnel » et « Compte rendu initial » de l’export PDF dossier.

## Symptôme

- Flash : toutes les lignes collées (`0001SITE(S) : 00SUJETS…`), mots coupés, collé au bord.
- Compte rendu : plusieurs lignes superposées, libellés collés aux valeurs, aucun air intérieur.

## Cause

1. TCPDF ignore presque `<pre>` et le `padding` CSS : les retours à la ligne disparaissaient, le texte se compactait.
2. `SetCellPadding(0)` (correctif des marges) annulait l’interligne HTML : les lignes s’empilaient.

## Correctif

- Retirer `SetCellPadding(0)`, rétablir un interligne HTML (`setCellHeightRatio(1.35)`).
- Remplacer le `<pre>` par un tableau : une ligne métier = une rangée, titres en gras, paires « libellé : valeur » en deux colonnes, `cellpadding` réel (TCPDF le respecte).

## Fichiers touchés

- `app/Services/Sse/SseCasePdfService.php`

## Vérification

Réexporter le PDF : flash en liste lisible, compte rendu avec sections espacées, plus de superposition.

## Statut

Corrigé
