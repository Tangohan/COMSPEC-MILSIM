# Poste ATAK — dossiers SSE et localisation téléphone

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Page back-office `/back-office/atak/` (Poste de situation).

## Symptôme

Pas de vue d’ensemble des dossiers SSE déjà pourvus d’une identité, ni moyen de placer un téléphone sous localisation depuis le back-office (il fallait passer par Zeus).

## Cause

Aucune page hub à cette adresse. La géolocalisation téléphone n’existait qu’en jeu (Zeus / Eden).

## Correctif

- Tableau de bord : dossiers ouverts avec au moins une identité, lien vers le dossier et la fiche.
- Bouton **Localiser le téléphone** (identités et contacts déjà visibles).
- Demande transmise au théâtre comme un signal (même canal que « faire vibrer »), appliquée en jeu dès qu’un client Overwatch la reçoit.

## Fichiers touchés

- `app/Controllers/Admin/AdminAtakHubController.php`
- `views/admin/atak_hub/index.php`
- `app/Repositories/SsePersonRepository.php`
- `app/Repositories/AtakOrderRepository.php`
- `routes/web.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyPhoneGeolocOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`

## Vérification

Ouvrir le poste ATAK : un dossier avec identité affiche **Localiser le téléphone**. En mission avec Overwatch à jour, le contact apparaît sur la carte comme un téléphone.

## Statut

corrigé
