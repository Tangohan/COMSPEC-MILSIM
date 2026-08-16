# Fiche DI — pile de tampons illisible

## Contexte
Fiche dossier d’intérêt (`interest_case_show`), encart latéral « tampons ».

## Symptôme
Enchaînement de 3–4 tampons rotatifs (Pré-SSE, état, validation, priorité) qui se chevauchent et donnent un rendu brouillon.

## Cause
Chaque marque utilisait le style « tampon encre » avec `transform: rotate(±n deg)` en flex wrap dans une colonne étroite.

## Correctif
Un seul sceau (Pré-SSE) ; état / contrôle / priorité en liste de marques alignées, sans rotation.

## Fichiers touchés
- `views/atak/sse/interest_case_show.php`
- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php` (cache-bust)

## Vérification
Recharger une fiche DI : un tampon Pré-SSE + lignes État / Contrôle (/ Priorité si besoin).

## Statut
corrigé
