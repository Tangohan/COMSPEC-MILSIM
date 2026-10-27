# Tenues Athena — fiche incomplète (items, auteur, favoris, portrait)

## Contexte

Panneau **Athena · Tenues** dans l’arsenal ACE (Overwatch Connect). Capture du 27/10/2026 : tenue « SOAR GOLD DIGGER » sélectionnée, badge « Sur le site », message « Équipement non disponible pour cette tenue », pas d’auteur / favori / mise en avant / portrait.

## Symptôme

- Aucune pièce affichée dans la fiche alors que la tenue est bien dans l’arsenal.
- Favoris, auteur (qui a envoyé), tenues mises en avant / obligatoires absents de la liste et de la fiche.
- Pas de portrait opérateur (contrairement au téléphone ATAK).
- Pas d’indication claire que la liste est bien copiée en local dans Documents.
- **Enfiler** laissait le panneau ouvert.

## Cause

1. Déballage ACE incomplet (`[tenue, version]` / HashMap / double enveloppe) → aperçu vide.
2. Liste site (extension) sans colonnes featured / mandatory / badge / avatar / cover.
3. Fiche centrée sur l’état de synchro seul, sans pastilles métier ni portrait.
4. **Enfiler** n’appelait pas la fermeture du panneau.

## Correctif

- Normalisation renforcée + repli sur la version site si l’entrée locale est illisible.
- API / cache liste : favori, mise en avant (épingles tableau de bord), pastille, portrait opérateur, cover.
- UI fiche : pièces même sans image de mod, pastilles, auteur, portrait, état copie Documents.
- **Enfiler** ferme le panneau après succès.

## Fichiers touchés

- `app/Repositories/ArsenalWardrobeRepository.php`
- `app/Controllers/Api/AtakWardrobeApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/Extension_WardrobeCache.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalNormalizeLoadout.sqf`
- `fn_arsenalOverlayRefresh.sqf`, `fn_arsenalOverlayFill.sqf`, `fn_arsenalOverlayShow.sqf`, `fn_arsenalOverlayPreview.sqf`, `fn_arsenalOverlayAction.sqf`
- `app/Support/DevDispatchCatalog.php` (UPDATE #1177)

## Vérification

- Actualiser la liste des tenues, ouvrir une tenue locale synchronisée : pièces + auteur + pastilles visibles.
- Filtrer Favoris / Mises en avant / Obligatoires.
- Portrait présent si le membre a un portrait opérateur sur le site.
- En-tête : « Copie locale OK » après un Actualiser réussi.
- **Enfiler** habille le mannequin et ferme le panneau.

## Statut

corrigé
