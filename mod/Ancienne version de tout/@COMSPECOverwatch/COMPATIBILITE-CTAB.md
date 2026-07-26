# Compatibilité cTab / ATAK Enhanced

## Vue d'ensemble

COMSPEC Overwatch est conçu pour fonctionner **en parallèle** avec cTab, cTab+, ATAK Enhanced et autres mods tactiques sans conflits.

---

## ✅ Compatible avec

| Mod | Statut | Notes |
|-----|--------|-------|
| **cTab** (Riouken) | ✅ Compatible | Mod original, assets réutilisés sous GPL v2 |
| **cTab+** (jetelain/1erGTD) | ✅ Compatible | Version améliorée de cTab |
| **cTab ATAK Enhanced** (Iceman77) | ✅ Compatible | Extension cTab avec features avancées |
| **SIT 1erGTD / cTab IRL** | ✅ Compatible | Liaison web/mobile cTab |
| **cTAB Connect [BETA]** | ✅ Compatible | Successeur SIT 1erGTD |
| **ACE3** | ✅ Requis | Utilisé pour menus Interact |
| **ACRE2 / TFAR** | ✅ Compatible | Détection radio optionnelle |

---

## 🔧 Configuration Recommandée

### Sans cTab (COMSPEC seul)

**Utilisation** : Menus ACE + Raccourcis configurables

1. **Activer raccourcis** : CBA Settings → ATAK Tactique → ☑ Activer raccourcis ATAK
2. **Configurer touches** : Options → Commandes → COMSPEC Overwatch - ATAK
3. **Utilisation** : Menus ACE Self-Interact + raccourcis personnalisés

---

### Avec cTab / ATAK Enhanced

**Utilisation** : Menus ACE uniquement (pas de raccourcis)

1. **Laisser désactivé** : CBA Settings → ATAK Tactique → ☐ Activer raccourcis ATAK
2. **Utilisation** : ACE Self-Interact → 📡 ATAK Tactique
3. **Avantage** : Aucun conflit de touches avec cTab

**Items détectés** :
- `ItemAndroid` (S7 Android cTab)
- `ItemAndroidMisc` (objet cTab en inventaire)
- `ItemcTab` (tablette microDAGR)
- `ItemcTabHCam` (caméra helmet)

---

## 🎯 Différences COMSPEC vs cTab

### COMSPEC Overwatch

**Focus** : Liaison serveur Athena temps réel

- ✅ Carte tactique web partagée
- ✅ Rapports structurés (SPOTREP, CONTACT, SITREP)
- ✅ POI collaboratifs avec intelligence
- ✅ MEDEVAC 9-Line avec golden hour
- ✅ QRF avec coordination
- ✅ Tracking véhicules automatique
- ✅ Messagerie intégrée
- ✅ Ordres C2
- ✅ LMS / HR / Forum intégré

**Limitation** : Nécessite serveur Athena backend

---

### cTab / ATAK Enhanced

**Focus** : Carte tactique in-game autonome

- ✅ Tablette/téléphone 3D en jeu
- ✅ BFT (Blue Force Tracking)
- ✅ Markers map synchronisés
- ✅ UAV feed (ATAK Enhanced)
- ✅ Fonctionne offline (pas de serveur requis)

**Limitation** : Pas de persistance multi-session

---

## 🔗 Utilisation Conjointe

### Scénario recommandé

**cTab/ATAK Enhanced** : Carte tactique in-game  
**COMSPEC Overwatch** : Liaison backend + features avancées

**Workflow** :

1. **En jeu** : Utiliser cTab pour navigation/BFT local
2. **Rapports** : COMSPEC ACE menu → Rapport tactique (sauvegardé serveur)
3. **POI** : COMSPEC ACE menu → POI (partagé avec commandement web)
4. **MEDEVAC/QRF** : COMSPEC ACE menu → Appui (workflow complet backend)
5. **Commandement** : Athena web pour vue d'ensemble + coordination

---

## ⚙️ Paramètres Détection Terminal

**CBA Setting** : `Détection terminal (position)`

| Mode | Description | Quand utiliser |
|------|-------------|----------------|
| **Slot d'objet** | `ItemAndroid` équipé (comme GPS/NVG) | cTab non installé, contrôle strict |
| **Inventaire** | `ItemAndroidMisc` transporté | cTab installé, mode permissif |
| **Les deux** (défaut) | Accepte l'un ou l'autre | Maximum compatibilité |

**Note** : COMSPEC fonctionne **sans** cTab si vous n'utilisez pas la détection terminal.

---

## 🚫 Éviter Conflits

### Raccourcis clavier

**Par défaut** : COMSPEC n'assigne **aucune touche** automatiquement

**Si conflit détecté** :
1. ESC → Options → Commandes → Configurer addons
2. Chercher le mod causant conflit
3. Réassigner ou désactiver touches

**Raccourcis COMSPEC** (si activés) :
- Rapport rapide
- POI rapide
- MEDEVAC rapide
- QRF rapide

**Raccourcis préservés COMSPEC** (non configurables pour éviter conflits) :
- **K** : Menu ATAK COMSPEC (pas de conflit avec cTab)
- **Ctrl+K** : Messagerie COMSPEC

---

## 📋 Checklist Compatibilité

### Installation

- [ ] CBA A3 installé et activé
- [ ] ACE3 installé (recommandé pour menus)
- [ ] COMSPEC Overwatch activé
- [ ] cTab/ATAK Enhanced activé (optionnel)
- [ ] Pas d'erreurs RPT au lancement

### Configuration

- [ ] CBA Settings COMSPEC configuré (URL Athena, Token)
- [ ] Détection terminal : Mode "Les deux" si cTab installé
- [ ] Raccourcis ATAK : **Désactivés** si cTab présent
- [ ] Test en jeu : Menu ACE ATAK fonctionnel

### Test Fonctionnel

- [ ] cTab tablette s'ouvre correctement
- [ ] COMSPEC menu K fonctionne
- [ ] ACE Self-Interact → ATAK Tactique présent
- [ ] Position remonte vers Athena (si configuré)
- [ ] Rapport test créé avec succès

---

## 🆘 Troubleshooting

### "Extension not found"

**Cause** : DLL COMSPEC bloquée

**Solution** :
1. Désactiver BattlEye pour test local
2. Production : Whitelist `COMSPECExtension_x64.dll` dans BattlEye

**Note** : cTab a aussi son extension, aucun conflit entre les deux.

---

### "Menus ACE ATAK absents"

**Cause** : ACE3 non chargé ou init échouée

**Solution** :
```sqf
[] call comspec_overwatch_connect_fnc_initATAKMenu;
```

**Alternative** : Utiliser fonctions directement :
```sqf
["CONTACT", "IMMEDIATE", "Test", "Test"] call comspec_overwatch_connect_fnc_submitTacticalReport;
```

---

### "Raccourci ne répond pas"

**Cause** : Conflit avec cTab ou autre mod

**Solution** :
1. Désactiver raccourcis ATAK : CBA Settings → ☐ Activer raccourcis ATAK
2. Utiliser menus ACE Interact uniquement
3. Ou réassigner touches dans Options → Commandes

---

## 📚 Références

### COMSPEC Overwatch

- **GitHub** : (À venir - dépôt public)
- **Athena** : https://athena.ttrd.fr/public
- **Licence** : GPL v2 (parties dérivées cTab) + Propriétaire COMSPEC

### cTab

- **GitHub** : https://github.com/jetelain/cTab
- **Steam (cTab+)** : https://steamcommunity.com/workshop/filedetails/?id=2262006564
- **Licence** : GNU GPL v2

### ATAK Enhanced (Iceman77)

- **Steam** : (Rechercher "cTab ATAK Enhanced")
- **Licence** : GPL v2 (basé sur cTab)

### SIT / cTAB Connect

- **Steam SIT** : https://steamcommunity.com/sharedfiles/filedetails/?id=2262009445
- **Steam Connect** : https://steamcommunity.com/sharedfiles/filedetails/?id=3438247879
- **Site** : https://ctab.plan-ops.fr/

---

## ✨ Résumé

**COMSPEC Overwatch et cTab/ATAK Enhanced sont complémentaires, pas concurrents.**

- ✅ **Zéro conflit** si raccourcis COMSPEC désactivés (défaut)
- ✅ **Menus ACE** toujours accessibles
- ✅ **Items cTab** détectés automatiquement
- ✅ **Extensions** cohabitent sans problème
- ✅ **Utilisables ensemble** pour expérience optimale

**Recommandation** : Laisser les deux mods activés, utiliser menus ACE pour COMSPEC, profiter de la carte tactique cTab in-game + backend Athena web.

---

**Version** : 1.2.0  
**Date** : 24 juillet 2026  
**Contact** : support@comspec.com
