# Guide de test manuel - Configuration réalisme ATAK

Ce document guide les tests manuels de la configuration centralisée réalisme ATAK.

## Prérequis

- Accès admin à l'interface ATHENA
- Tenant de test configuré
- Navigateur moderne (Chrome, Firefox, Edge)

---

## 1. Migration et seed

### Test 1.1 : Vérifier que les tables existent

**Commande SQL** :
```sql
SHOW TABLES LIKE 'atak_realism_config';
```

**Résultat attendu** : La table `atak_realism_config` existe.

### Test 1.2 : Exécuter le seed

**Action** : Exécuter la migration seed via le système de migration PHP.

**Commande** :
```bash
php bootstrap/migrations.php
```

**Vérification** :
```sql
SELECT COUNT(*) FROM atak_realism_config WHERE is_active = 1;
```

**Résultat attendu** : Au moins 1 configuration active par tenant.

### Test 1.3 : Vérifier la structure JSON

**Commande SQL** :
```sql
SELECT tenant_id, config_version, 
       JSON_KEYS(config_json) as domains,
       created_at 
FROM atak_realism_config 
WHERE is_active = 1 
LIMIT 1;
```

**Résultat attendu** : Les 11 domaines sont présents :
- `radio_relays`
- `zones_roleplay`
- `network_simulation`
- `certificates`
- `terminal_damage`
- `waypoints_routes`
- `symbology_map`
- `control_measures`
- `coverage_viewshed`
- `experience_ambiance`
- `other_settings`

---

## 2. API GET

### Test 2.1 : Appel API sans authentification

**URL** : `GET /api/atak/realism/config`

**Résultat attendu** : Erreur 401 (authentification requise)

### Test 2.2 : Appel API authentifié

**URL** : `GET /api/atak/realism/config`
**Headers** : Session cookie ou API key

**Résultat attendu** :
```json
{
  "ok": true,
  "config": {
    "radio_relays": { ... },
    "zones_roleplay": { ... },
    ...
  },
  "version": "1.0.0",
  "config_name": "Configuration initiale (migration)",
  "updated_at": "2026-09-22 09:00:00"
}
```

### Test 2.3 : Vérifier les valeurs par défaut

**Vérifications** :
- `radio_relays.relay_range_m` = `2000`
- `radio_relays.weather_effects_enabled` = `true`
- `certificates.certificate_duration_days` = `365`
- `control_measures.enabled` = `true`
- `coverage_viewshed.viewshed_radius_default_m` = `500`

---

## 3. Interface admin

### Test 3.1 : Accès à l'interface

**Actions** :
1. Se connecter en tant qu'admin
2. Aller sur `/back-office/atak/controle-serveur`
3. Cliquer sur "⚙️ Config réalisme centralisée"

**Résultat attendu** : 
- Page charge sans erreur
- 10 onglets visibles : Relais radio, Zones roleplay, Simulation réseau, Certificats, Dommages terminal, Itinéraires, Symbologie, Control Measures, Couverture, Expérience
- Métadonnées config affichées (version, date modification)

### Test 3.2 : Navigation entre onglets

**Actions** :
1. Cliquer sur chaque onglet
2. Vérifier que le contenu change
3. Vérifier que l'onglet actif est surligné en noir

**Résultat attendu** : Navigation fluide, aucune erreur console.

### Test 3.3 : Formulaire pré-rempli

**Vérifications** :
- Les valeurs par défaut sont affichées
- Les checkboxes reflètent l'état booléen
- Les selects ont la bonne option sélectionnée
- Les inputs number affichent les valeurs numériques
- Les inputs color affichent les couleurs hex

---

## 4. Section météo

### Test 4.1 : Calculateur d'impact

**Actions** :
1. Aller sur l'onglet "Relais radio"
2. Scroller jusqu'à "☔ Effet météo sur les communications"
3. Scroller jusqu'au calculateur d'impact

**Vérifications** :
- Portée base : 2000m
- Condition : Temps clair
- Vent : 0 km/h
- **Résultat** : "Portée effective : 2000m" (100%)

### Test 4.2 : Impact pluie

**Actions** :
1. Changer "Condition" à "Pluie"

**Résultat attendu** : 
- "Portée effective : 1700m"
- "Pluie (85%) = 85% de la portée base"

### Test 4.3 : Impact brouillard

**Actions** :
1. Changer "Condition" à "Brouillard"

**Résultat attendu** :
- "Portée effective : 1400m"
- "Brouillard (70%) = 70% de la portée base"

### Test 4.4 : Impact orage

**Actions** :
1. Changer "Condition" à "Orage"

**Résultat attendu** :
- "Portée effective : 1200m"
- "Orage (60%) = 60% de la portée base"

### Test 4.5 : Impact vent

**Actions** :
1. Condition : "Temps clair"
2. Vent : 70 km/h

**Résultat attendu** :
- "Portée effective : 1800m"
- "Temps clair × Vent 70km/h (90%) = 90% de la portée base"
- Calcul : 70 - 50 (seuil) = 20 km/h → 2 tranches de 10 → 2 × 5% = -10%

### Test 4.6 : Impact combiné

**Actions** :
1. Condition : "Pluie"
2. Vent : 70 km/h

**Résultat attendu** :
- "Portée effective : 1530m"
- "Pluie (85%) × Vent 70km/h (90%) = 77% de la portée base"
- Calcul : 2000 × 0.85 × 0.90 = 1530m

### Test 4.7 : Modification des multiplicateurs

**Actions** :
1. Changer "Pluie - Portée (%)" de 0.85 à 0.90
2. Condition : "Pluie", Vent : 0

**Résultat attendu** :
- Calculateur se met à jour automatiquement
- "Portée effective : 1800m"
- "Pluie (90%) = 90% de la portée base"

---

## 5. Onglet Control Measures

### Test 5.1 : Paramètres par défaut

**Vérifications** :
- Control Measures activés : ✓
- Axis naming : ✓
- LD activés : ✓ (couleur vert #00ff00)
- LOA activés : ✓ (couleur rouge #ff0000)
- Phase Lines activés : ✓ (couleur jaune #ffff00)
- Objectifs activés : ✓ (rayon 200m)
- Checkpoints activés : ✓ (rayon 50m, auto-numérotés)
- Visibilité : "Équipe uniquement"

### Test 5.2 : Modifier les couleurs

**Actions** :
1. Cliquer sur le color picker "LD"
2. Choisir une couleur différente (ex: bleu #0000ff)

**Résultat attendu** : La couleur change dans le picker.

### Test 5.3 : Désactiver un type

**Actions** :
1. Décocher "Activer les checkpoints"

**Résultat attendu** : La checkbox se désactive.

---

## 6. Validation

### Test 6.1 : Validation portée relais invalide

**Actions** :
1. Onglet "Relais radio"
2. Changer "Portée relais par défaut" à `100000` (hors bornes)
3. Cliquer sur "💾 Enregistrer"

**Résultat attendu** :
- Alert : "Erreurs de validation : La portée du relais doit être entre 50 et 8000m"
- Config non sauvegardée

### Test 6.2 : Validation certificat invalide

**Actions** :
1. Onglet "Certificats"
2. Changer "Durée certificat (jours)" à `5000` (hors bornes)
3. Cliquer sur "💾 Enregistrer"

**Résultat attendu** :
- Alert : "Erreurs de validation : La durée du certificat doit être entre 1 et 1825 jours"
- Config non sauvegardée

### Test 6.3 : Validation multiplicateur météo invalide

**Actions** :
1. Onglet "Relais radio"
2. Changer "Pluie - Portée (%)" à `1.5` (hors bornes 0-1)
3. Cliquer sur "💾 Enregistrer"

**Résultat attendu** :
- Alert : "Erreurs de validation : Le multiplicateur rain_range_multiplier doit être entre 0 et 1"
- Config non sauvegardée

### Test 6.4 : Validation multiple

**Actions** :
1. Mettre plusieurs valeurs invalides :
   - Portée relais : 100000
   - Durée certificat : 5000
   - Pluie portée : 1.5
2. Cliquer sur "💾 Enregistrer"

**Résultat attendu** :
- Alert avec 3 erreurs listées
- Config non sauvegardée

---

## 7. Sauvegarde

### Test 7.1 : Sauvegarde valide

**Actions** :
1. Onglet "Relais radio"
2. Changer "Portée relais par défaut" à `2500` (valide)
3. Activer "Activer les effets météo sur les comms"
4. Cliquer sur "💾 Enregistrer"

**Résultat attendu** :
- Alert : "✓ Configuration enregistrée avec succès !"
- Page se recharge
- Les modifications sont persistées

### Test 7.2 : Vérifier historique

**Actions** :
1. Cliquer sur "📜 Historique"

**Résultat attendu** :
- Modal s'ouvre
- Au moins 2 entrées :
  - "Configuration initiale (migration)" (archivée)
  - "Configuration modifiée le [date]" (✓ Active)

### Test 7.3 : Vérifier API après sauvegarde

**URL** : `GET /api/atak/realism/config`

**Vérifications** :
- `config.radio_relays.relay_range_m` = `2500` (nouvelle valeur)
- `config.radio_relays.weather_effects_enabled` = `true`
- `updated_at` = timestamp récent

---

## 8. Tooltips d'aide

### Test 8.1 : Affichage tooltips

**Actions** :
1. Onglet "Relais radio" → Section météo
2. Survoler l'icône "?" à côté de "Pluie - Portée (%)"

**Résultat attendu** :
- Infobulle apparaît : "Multiplicateur appliqué à la portée en cas de pluie"
- Infobulle positionnée au-dessus de l'icône
- Flèche pointant vers l'icône

### Test 8.2 : Plusieurs tooltips

**Actions** :
1. Tester tous les "?" de la section météo

**Résultat attendu** :
- Chaque tooltip affiche une explication pertinente
- Pas de chevauchement visuel

---

## 9. Navigation

### Test 9.1 : Lien depuis server_control

**Actions** :
1. Aller sur `/back-office/atak/controle-serveur`
2. Vérifier la présence du bouton "⚙️ Config réalisme centralisée" (vert emerald)
3. Cliquer dessus

**Résultat attendu** : Redirection vers `/admin/atak/realism/config`

### Test 9.2 : Lien depuis roleplay

**Actions** :
1. Aller sur `/back-office/atak/roleplay`
2. Vérifier la présence du bouton "⚙️ Config réalisme centralisée" (violet)
3. Cliquer dessus

**Résultat attendu** : Redirection vers `/admin/atak/realism/config`

### Test 9.3 : Retour depuis config centralisée

**Actions** :
1. Sur `/admin/atak/realism/config`
2. Cliquer sur "← Contrôle de mission"

**Résultat attendu** : Retour sur `/back-office/atak/controle-serveur`

---

## 10. Validation serveur

### Test 10.1 : Appel POST invalide

**URL** : `POST /admin/atak/realism/save`
**Body** :
```json
{
  "config": {
    "radio_relays": {
      "relay_range_m": 100000
    }
  },
  "_csrf_token": "valid-token"
}
```

**Résultat attendu** :
```json
{
  "ok": false,
  "error": "Validation échouée : relay_range_m must be between 50 and 8000"
}
```

### Test 10.2 : Domaine manquant

**URL** : `POST /admin/atak/realism/save`
**Body** :
```json
{
  "config": {
    "radio_relays": {}
    // Manque 10 domaines
  },
  "_csrf_token": "valid-token"
}
```

**Résultat attendu** :
```json
{
  "ok": false,
  "error": "Validation échouée : Missing or invalid domain: zones_roleplay, ..."
}
```

---

## 11. Console navigateur

### Test 11.1 : Pas d'erreurs JavaScript

**Actions** :
1. Ouvrir la console développeur (F12)
2. Naviguer sur `/admin/atak/realism/config`
3. Changer d'onglets
4. Modifier des valeurs
5. Tester le calculateur météo

**Résultat attendu** : Aucune erreur JavaScript dans la console.

### Test 11.2 : Logs de sauvegarde

**Actions** :
1. Console ouverte
2. Modifier une valeur
3. Cliquer sur "💾 Enregistrer"

**Vérification** : Voir la requête POST vers `/admin/atak/realism/save` dans l'onglet Network.

---

## 12. Compatibilité navigateurs

### Test 12.1 : Chrome/Edge

- [ ] Interface charge correctement
- [ ] Onglets fonctionnent
- [ ] Calculateur météo fonctionne
- [ ] Tooltips s'affichent
- [ ] Sauvegarde fonctionne

### Test 12.2 : Firefox

- [ ] Interface charge correctement
- [ ] Onglets fonctionnent
- [ ] Calculateur météo fonctionne
- [ ] Tooltips s'affichent
- [ ] Sauvegarde fonctionne

### Test 12.3 : Safari (si disponible)

- [ ] Interface charge correctement
- [ ] Onglets fonctionnent
- [ ] Calculateur météo fonctionne
- [ ] Tooltips s'affichent
- [ ] Sauvegarde fonctionne

---

## Checklist finale

- [ ] Tous les tests de migration passent
- [ ] API GET retourne les bonnes données
- [ ] Interface admin charge sans erreur
- [ ] 10 onglets accessibles et fonctionnels
- [ ] Calculateur météo précis
- [ ] Validation côté client fonctionne
- [ ] Validation côté serveur fonctionne
- [ ] Sauvegarde persiste les données
- [ ] Historique affiche les versions
- [ ] Tooltips d'aide affichés
- [ ] Navigation entre pages fonctionne
- [ ] Aucune erreur console
- [ ] Compatible Chrome/Firefox/Edge

---

## Rapport de bugs

Si un test échoue, documenter :
1. **Test** : Numéro du test (ex: 4.2)
2. **Action** : Ce qui a été fait
3. **Attendu** : Résultat attendu
4. **Obtenu** : Résultat obtenu
5. **Console** : Erreurs JavaScript éventuelles
6. **Screenshot** : Capture d'écran si pertinent

---

**Date de création** : 22 septembre 2026  
**Version** : 1.0  
**Auteur** : Équipe COMSPEC-MILSIM
