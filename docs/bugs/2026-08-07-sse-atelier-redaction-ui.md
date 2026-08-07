# Atelier de rédaction SSE trop pauvre visuellement

## Contexte

Page `/atak/sse/documents` (et formulaires / lecture associés) dans le bureau SSE.
Remontée usage : « la page rédaction est trop laide ».

## Symptôme

- Grille de types mal proportionnée (4 tuiles dans une grille à 5 colonnes).
- Liste plate en table sans hiérarchie d’état.
- Formulaire et lecture sans composition « atelier » (pas de bandeau, pas de papier de rédaction).

## Cause

Mise en page minimale réutilisant `sse-ops-grid` générique, sans composants dédiés à l’atelier de rédaction, alors que d’autres écrans (dossiers d’intérêt) avaient déjà un hero + faits + rail d’actions.

## Correctif

- Hero atelier (compteurs brouillon / relecture / validé).
- Tuiles de types (FLH / CR / NA / SYN) en grille 4.
- Liste en cartes avec marque de type et badges d’état.
- Formulaire et lecture sur « papier » de rédaction, actions regroupées.

## Fichiers touchés

- `views/atak/sse/documents.php`
- `views/atak/sse/document_form.php`
- `views/atak/sse/document_show.php`
- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php` (cache-bust CSS)

## Vérification

- [ ] Ouvrir `/atak/sse/documents` : hero + types + liste cartes
- [ ] Nouveau document / lecture : papier + actions latérales
- [ ] Responsive < 640px : types en colonne, cartes empilées

## Statut

corrigé
