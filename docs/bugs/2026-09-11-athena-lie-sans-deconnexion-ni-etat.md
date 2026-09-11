# Athena lié : pas de déconnexion ni d’état des remontées

**Date :** 2026-09-11  
**Statut :** corrigé

## Contexte

Une fois « Liaison OK », le panneau Athena n’affichait que la fiche (nom, indicatif…) sans moyen de se déconnecter, sans état du canal / Steam / position, et le bandeau relançait Entrer sans retour visible.

## Symptôme

- Impossible de déconnecter ou de ré-appairer clairement.
- Aucune indication si la position ou le temps de mission remontent.
- Pas de distinction canal ouvert / interrompu.

## Cause

- Formulaire de connexion masqué dès liaison complète, sans boutons d’action de rechange.
- `authFocus` forçait Entrer dès READY / lié.
- Fiche limitée aux champs profil.

## Correctif

- Bandeau : actualise l’état si déjà lié (plus d’Entrer silencieux).
- Boutons **Rouvrir canal** et **Déconnecter**.
- État lisible : Steam, canal poste, dernière confirmation, position remontée, temps de mission.
- Déconnexion : logout jeu + effacement clé communauté / session côté liaison.

## Fichiers touchés

- `atak_athena/ui/athena_page.hpp`
- `atak_athena/functions/fn_athena_*.sqf` (layout, panel, auth, homeAction)
- `connect/functions/auth/fn_logout.sqf`
- `COMSPECExtension/GameAuth.cs`

## Vérification

Athena lié → lire l’état → Déconnecter → formulaire de connexion → reconnecter. Pack Athena **1.0.82**, Overwatch **1.5.25**, liaison **2.0.21**.
