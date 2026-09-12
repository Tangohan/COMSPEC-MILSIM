# Messagerie ATAK COMSPEC (canaux) — MVP

**Date :** 2026-09-12  
**Statut :** livré (MVP)

## Contexte

L’écran IceMan « Group Messages » est une liste plate en anglais, sans notion de canal. Le portail Athena dispose déjà des canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés) via le journal radio.

## Approche retenue

Messagerie **COMSPEC** native dans le téléphone ATAK (app « Messagerie »), branchée sur les canaux existants — pas de modification d’IceMan.

## Livré

- App tiroir **Messagerie** (français métier : Envoyer, canaux, Effacer l’affichage local)
- Liste des canaux (système + custom via liaison)
- Fil filtré par canal, envoi via le canal actif
- Raccourci bureau, menu ACE, Ctrl+K → Messagerie
- IceMan Group Messages reste en miroir pour compatibilité

## Reporté

- Création de canal custom depuis le téléphone (déjà possible depuis le journal web)
- Bulles style IceMan (liste compacte pour le MVP)
- Remplacement complet / masquage d’IceMan Group Messages

## Versions

Overwatch **1.5.49** · Athena **1.0.93**

## Vérification

1. Relancer Arma, ouvrir le téléphone → tiroir **Messagerie**
2. Choisir un canal, envoyer un message → visible au journal radio du poste
3. Message poste sur le même canal → apparaît dans le fil en jeu
