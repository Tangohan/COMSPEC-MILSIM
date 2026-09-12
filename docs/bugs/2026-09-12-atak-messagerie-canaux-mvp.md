# Messagerie ATAK COMSPEC (canaux) — MVP + suite

**Date :** 2026-09-12  
**Statut :** livré

## Contexte

L’écran IceMan « Group Messages » est une liste plate en anglais, sans notion de canal. Le portail Athena dispose déjà des canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés) via le journal radio.

## Approche retenue

Messagerie **COMSPEC** native dans le téléphone ATAK (app « Messagerie »), branchée sur les canaux existants — pas de modification du code IceMan.

## Livré

- App tiroir **Messagerie** (français métier : Envoyer, canaux, Effacer l’affichage local)
- Liste des canaux (système + custom via liaison)
- Fil filtré par canal, envoi via le canal actif
- Raccourci bureau, menu ACE, Ctrl+K → Messagerie
- **Création de canal personnalisé** depuis le téléphone (champ + Créer)
- **Fil plus lisible** : De / Vous / Du poste, horodatage, contrastes, info-bulle du texte complet
- **Group Messages IceMan masqué** : retiré du tiroir ; toute ouverture résiduelle bascule vers Messagerie COMSPEC

## Versions

Overwatch **1.5.55** · Athena **1.0.100**

## Vérification

1. Relancer Arma, ouvrir le téléphone → tiroir **Messagerie** (pas « Groups » / Group Messages)
2. Choisir un canal, envoyer un message → visible au journal radio du poste
3. Créer un canal (nom + Créer) → apparaît dans la liste et au poste
4. Message poste sur le même canal → fil lisible (De / Vous / Du poste)
