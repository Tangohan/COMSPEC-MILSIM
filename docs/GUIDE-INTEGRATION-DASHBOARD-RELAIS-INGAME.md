# Guide d'intégration — Tableau de bord relais en jeu

**Projet** : ATHENA C2 (COMSPEC-MILSIM)  
**Module** : Gestion des relais radio ATAK en jeu  
**Date** : 22 septembre 2026  
**Statut** : ✅ Intégré dans le mod

---

## Vue d'ensemble

Le système de gestion des relais radio ATAK est maintenant **accessible directement en jeu** pour les joueurs et Zeus, sans avoir besoin de passer par le back-office web.

### Fonctionnalités intégrées

1. **Modules Zeus**
   - Scanner réseau relais (détection automatique + upload vers serveur)
   - Tableau de bord relais (interface full-screen)
   - Resync relais (synchronisation manuelle d'un relais spécifique)

2. **Actions ACE/Scroll pour joueurs**
   - Accessible depuis des objets marqués (laptop, tablette)
   - Ouvre le tableau de bord full-screen
   - Pas besoin d'être Zeus

3. **Interface joueur**
   - Liste complète des relais détectés
   - Statistiques globales (actifs, hors ligne, erreurs)
   - Color-coding par statut (OK, Saturé, Endommagé, Hors ligne)
   - Actions : resync tous, téléporter (Zeus), double-clic pour détails

---

## Fichiers modifiés/créés

### Configuration principale
- **`config.cpp`** : Ajout de la faction `COMSPEC_ATAK` et des 3 modules Zeus
- **`XEH_postInit.sqf`** : Appel automatique de `fn_addRelayDashboardActions` au boot

### Fonctions SQF
- **`fn_zeusModuleScanRelays.sqf`** : Scanne et remonte les relais dans un rayon
- **`fn_openRelayDashboard.sqf`** : Interface full-screen du tableau de bord
- **`fn_addRelayDashboardActions.sqf`** : Ajoute actions ACE/scroll aux objets marqués
- **`fn_zeusResyncRelay.sqf`** : Resync rapide d'un relais spécifique

### Documentation
- **`GUIDE-GESTION-RELAIS-ZEUS-INGAME.md`** : Guide complet pour Zeus et joueurs
- **`GUIDE-INTEGRATION-DASHBOARD-RELAIS-INGAME.md`** : Ce document (intégration technique)

---

## Utilisation en mission

### Pour Zeus

1. **Scanner réseau relais**
   ```
   Zeus → Modules → COMSPEC ATAK → Scanner réseau relais
   ```
   - Paramètres : rayon de scan (défaut 5000m), upload auto
   - Affiche rapport style relevé terrain avec feedback visuel

2. **Tableau de bord relais**
   ```
   Zeus → Modules → COMSPEC ATAK → Tableau de bord relais
   ```
   - Ouvre interface full-screen
   - Actions disponibles : resync tous, téléporter au relais

3. **Resync relais**
   ```
   Zeus → Modules → COMSPEC ATAK → Resync relais
   ```
   - Placer sur le relais à resynchroniser
   - Feedback visuel (particules jaune → vert)

### Pour joueurs

1. **Trouver un objet marqué**
   - Laptop (classnames contenant `laptop`, `notebook`)
   - Tablette (`tablet`)
   - Objets avec variable `COMSPEC_RelayDashboard = true`

2. **Ouvrir le tableau de bord**
   ```
   ACE Self Interact → ATAK → Tableau de bord relais
   ```
   ou
   ```
   Scroll menu → ATAK → Tableau de bord relais
   ```

3. **Interface**
   - Vue d'ensemble du réseau (actifs, hors ligne, erreurs)
   - Liste des relais avec statut color-codé
   - Double-clic sur un relais pour détails

### Marquage d'objets personnalisés

Pour ajouter l'action à un objet spécifique en mission :

```sqf
// Dans l'init du laptop/tablette
this setVariable ["COMSPEC_RelayDashboard", true, true];
```

---

## Intégration automatique

Le système s'initialise **automatiquement** au démarrage du mod :

1. **`XEH_postInit.sqf`** (ligne 538-541) :
   ```sqf
   [{
       [] call comspec_overwatch_connect_fnc_addRelayDashboardActions;
   }, [], 10] call CBA_fnc_waitAndExecute;
   ```

2. **Modules Zeus** déclarés dans `config.cpp` (lignes 858-924) :
   - `COMSPEC_ModuleScanRelays`
   - `COMSPEC_ModuleRelayDashboard`
   - `COMSPEC_ModuleResyncRelay`

3. **Fonctions exportées** dans `config.cpp` (lignes 589-593) :
   ```cpp
   class zeusModuleScanRelays {};
   class openRelayDashboard {};
   class addRelayDashboardActions {};
   class zeusResyncRelay {};
   ```

---

## Tests recommandés

### Test 1 : Module Zeus Scanner
1. Ouvrir Zeus
2. Placer plusieurs relais ATAK sur la carte
3. Poser le module "Scanner réseau relais"
4. Vérifier le rapport affiché en hints Zeus
5. Vérifier dans le back-office web que les relais sont remontés

### Test 2 : Tableau de bord Zeus
1. Ouvrir Zeus
2. Poser le module "Tableau de bord relais"
3. Vérifier que l'interface full-screen s'ouvre
4. Tester la téléportation à un relais (Zeus uniquement)
5. Tester le resync de tous les relais

### Test 3 : Actions joueur
1. En tant que joueur (non-Zeus)
2. Trouver un laptop/tablette
3. ACE Self Interact → ATAK → Tableau de bord relais
4. Vérifier que l'interface s'ouvre
5. Vérifier que la téléportation n'est PAS disponible

### Test 4 : Resync individuel
1. Créer un relais
2. Le dégrader (tirer dessus)
3. Zeus → Resync relais sur l'objet
4. Vérifier les particules visuelles (jaune → vert)
5. Vérifier dans le back-office que le statut est à jour

---

## Dépannage

### Les actions ne s'ajoutent pas
- **Cause** : Fonction `fn_addRelayDashboardActions` non appelée
- **Solution** : Vérifier `XEH_postInit.sqf` ligne 538

### Les modules Zeus n'apparaissent pas
- **Cause** : `config.cpp` mal recompilé
- **Solution** : Recompiler le mod, vérifier les lignes 787-924

### L'interface ne s'ouvre pas
- **Cause** : Nom de fonction incorrect
- **Solution** : Vérifier que `comspec_overwatch_connect_fnc_openRelayDashboard` existe

### Les relais ne remontent pas vers le serveur
- **Cause** : API ATHENA non accessible
- **Solution** : Vérifier `fn_syncAtakRelay.sqf`, tester manuellement avec `callExtension`

---

## Prochaines étapes

### Court terme
- ✅ Intégration des modules Zeus
- ✅ Actions ACE pour joueurs
- ✅ Tableau de bord full-screen
- ✅ Synchronisation automatique des relais

### Moyen terme
- 🔲 Gestion des overrides par instance depuis le web
- 🔲 Push notifications pour changements de config en jeu
- 🔲 Historique des événements par relais

### Long terme
- 🔲 Topologie mesh réseau (visualisation des liaisons)
- 🔲 Simulation de propagation radio en temps réel
- 🔲 Intégration avec la météo (déjà prévu dans Phase 3)

---

## Support

Pour toute question ou problème :
1. Consulter `GUIDE-GESTION-RELAIS-ZEUS-INGAME.md` (guide utilisateur)
2. Vérifier les logs RPT : `[COMSPEC Overwatch][RelayDashboard]`
3. Tester manuellement : `call comspec_overwatch_connect_fnc_openRelayDashboard`

---

**Auteur** : Cursor Cloud Agent  
**Projet** : ATHENA C2 — Configuration réalisme centralisée  
**Phase** : 3 (Refactor + Extensions fonctionnelles)  
**Version** : 1.0
