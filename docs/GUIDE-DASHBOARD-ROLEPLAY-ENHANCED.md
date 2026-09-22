# Dashboard Mode Roleplay Amélioré - Guide utilisateur

## 🎯 Vue d'ensemble

Le nouveau dashboard Mode Roleplay centralise **toute la configuration, les tests serveur et la visualisation des zones** dans une interface unique et moderne.

---

## ✨ Nouvelles fonctionnalités

### 1. **Tests serveur en temps réel** 🔬

Cliquez sur le bouton **"🔬 Tests serveur"** (header ou colonne droite) pour exécuter 8 tests automatiques :

| Test | Description |
|------|-------------|
| ✅ Configuration chargée | Vérifie que la config roleplay est présente en base |
| ✅ Simulation réseau | Valide latence min/max et cohérence des paramètres |
| ✅ Déconnexions temporaires | Vérifie durée min/max et intervalles |
| ✅ Liaison ATAK via relais | Indique si transmission directe ou via relais |
| ✅ Capteurs médicaux | Contrôle que le total des défauts ≤ 100% |
| ✅ Zones géographiques | Vérifie qu'au moins 1 zone est définie si activé |
| ✅ Données chiffrées | Indique si certificats requis |
| ⚠️ Warning double pénalité | Alerte si risque de double pénalité réseau |

**Affichage** : Résultats en temps réel avec ✅/❌ et messages explicites.

---

### 2. **Carte interactive des zones** 🗺️

**Localisation** : Colonne droite, section "Carte des zones"

**Fonctionnalités** :
- 🎨 **Visualisation Canvas** : Toutes les zones affichées avec couleurs par effet
- 📍 **Pointeurs centrés** : Chaque zone a un point central + cercle de rayon
- 🔄 **Mise à jour auto** : La carte se redessine quand vous modifiez les zones
- 📏 **Auto-scale** : Zoom automatique pour afficher toutes les zones
- 📊 **Légende** : Couleurs par type d'effet (dégradé, interférence, brouillage, etc.)

**Légende des couleurs** :
| Effet | Couleur |
|-------|---------|
| Couverture dégradée | 🟡 Jaune |
| Interférences | 🟠 Orange |
| Forte perte de signal | 🔴 Rouge |
| Brouillage actif | 🟣 Violet |
| Sans couverture | ⚫ Noir |

---

### 3. **UI regroupée et simplifiée** 📦

**Layout 2 colonnes** :
- **Gauche (8/12)** : Configuration (formulaire complet)
- **Droite (4/12)** : Tests + Carte

**Sections compactes** :
- ⚙️ **Simulation réseau** : Mode, latence, pertes, déconnexions (tout en 1 section)
- ⚡ **Liaison relais + Capteurs + Intel** : 3 cartes côte à côte (gain de place)
- 🗺️ **Zones géographiques** : Liste scrollable + bouton ajout direct

**Header avec stats** :
- Nombre de modules actifs / total
- Nombre de zones géographiques
- Navigation rapide (Contrôle mission, Carte tactique, Config réalisme)

---

## 📋 Navigation

### Accès au dashboard

**URL** : `/back-office/atak/roleplay`

**Depuis le back-office** :
1. Poste de situation ATAK
2. Onglet **"Mode Roleplay"**

**Navigation rapide (header)** :
- 🎮 Contrôle mission
- 📊 Poste de situation
- 🗺️ Carte tactique ATAK
- ⚙️ Config réalisme centralisée
- 🔬 Tests serveur

---

## 🧪 Utilisation des tests serveur

### Lancer les tests

1. Cliquez sur **"🔬 Lancer les tests"** (bouton dans section Tests serveur, colonne droite)
2. Les tests s'exécutent en ~1 seconde via AJAX
3. Résultats affichés en temps réel avec ✅/❌

### Interpréter les résultats

**✅ Vert** : Test réussi, configuration OK
**❌ Rouge** : Problème détecté, message explicatif affiché

**Exemple de messages** :
- ✅ *"Latence: 100-500 ms, Perte: 5.0%"*
- ❌ *"Latence max < latence min (incohérent)"*
- ⚠️ *"Coupures portail actives - vérifier CBA client"*

### Relancer les tests

Cliquez simplement à nouveau sur **"🔬 Lancer les tests"** après avoir modifié la configuration.

---

## 🗺️ Utilisation de la carte des zones

### Ajouter une zone

1. Section **"Zones géographiques"**
2. Cliquez sur **"➕ Ajouter une zone"**
3. Remplissez : Est (m), Nord (m), Rayon (m), Effet
4. La carte se met à jour automatiquement

### Modifier une zone

1. Changez les valeurs dans les inputs (Est, Nord, Rayon, Effet)
2. La carte se redessine en temps réel

### Retirer une zone

Cliquez sur **"🗑️ Retirer"** à droite de la zone → disparaît de la liste et de la carte

### Coordonnées de la carte

- **Est** : Axe horizontal (X) en mètres (repère Arma 3)
- **Nord** : Axe vertical (Y) en mètres (repère Arma 3)
- **Rayon** : Portée de la zone en mètres

**Astuce** : Récupérez les coordonnées depuis la carte Arma 3 ou la carte tactique ATAK.

---

## 💾 Sauvegarde

1. Modifiez la configuration (réseau, relais, zones, etc.)
2. Cliquez sur **"💾 Enregistrer"** (bouton bleu, bas du formulaire)
3. Confirmation visuelle → La config est sauvegardée en base

**Attention** : Les tests serveur et la carte sont purement visuels. Seul le bouton **"Enregistrer"** modifie la base de données.

---

## 🔄 Réinitialisation

**Bouton** : **"🔄 Réinitialiser"** (bas du formulaire)

**Action** : Remet tous les paramètres à leurs valeurs par défaut (avec confirmation).

---

## 📱 Responsive

Le dashboard s'adapte à toutes les tailles d'écran :
- **Desktop (XL)** : 2 colonnes (Config + Tests/Carte)
- **Tablette (MD-LG)** : 1 colonne, sections empilées
- **Mobile** : Colonnes grilles adaptées (2 → 1 colonne)

---

## 🎨 Design

- **Palette** : Tailwind CSS moderne (bleu, violet, amber, emerald)
- **Cards** : Bordures arrondies, ombres douces, dégradés subtils
- **Icons** : SVG inline (Heroicons)
- **Typographie** : Inter (system font fallback)

---

## 🔗 Liens utiles

- **Config réalisme centralisée** : `/admin/atak/realism/config` (11 domaines, 105 paramètres)
- **Contrôle mission** : `/back-office/atak/controle-serveur`
- **Carte tactique** : `/atak`
- **Poste de situation** : `/back-office/atak`

---

## 🐛 Dépannage

### Tests serveur ne se lancent pas

**Cause** : Endpoint API non accessible ou session expirée

**Solution** :
1. Vérifier que vous êtes connecté (session active)
2. Recharger la page
3. Vérifier les logs PHP (`storage/logs/`)

### Carte ne s'affiche pas

**Cause** : Canvas non supporté (navigateur très ancien)

**Solution** : Utiliser un navigateur moderne (Chrome, Firefox, Edge, Safari)

### Zones ne se dessinent pas

**Cause** : Coordonnées hors limites ou valeurs manquantes

**Solution** :
1. Vérifier que **Est**, **Nord** et **Rayon** sont renseignés
2. Vérifier que les valeurs sont des nombres valides
3. Ouvrir la console navigateur (F12) pour voir les erreurs JS

---

## 📝 Notes techniques

### API Endpoint

**URL** : `POST /api/atak/roleplay/server-tests`

**Auth** : Authentification requise (session + CSRF token)

**Response** :
```json
{
  "ok": true,
  "tests": [
    {
      "name": "Configuration roleplay chargée",
      "passed": true,
      "message": "Configuration présente en base"
    },
    ...
  ],
  "summary": {
    "total": 8,
    "passed": 7
  }
}
```

### Vue Blade

**Fichier** : `views/admin/atak/roleplay_enhanced.php`

**Fallback** : L'ancienne vue est toujours disponible avec `?legacy=1`

**Variables Blade** :
- `$config` : Configuration roleplay actuelle
- `$zoneRows` : Liste des zones géographiques
- `$zoneEffectOptions` : Options d'effet (degraded, interference, etc.)
- `$serverTests` : Null par défaut (chargé via AJAX)

### Canvas Map

**Fichier** : Inline `<script>` dans la vue

**Fonction** : `drawZonesMap()`

**Triggers** :
- Ajout/suppression de zone
- Modification d'inputs (Est, Nord, Rayon, Effet)
- Resize fenêtre

**Rendu** :
- Fond gris clair + grille 10x10
- Auto-scale pour afficher toutes les zones
- Axes centraux X/Y
- Cercles colorés par effet
- Points centraux + bordures

---

## ✅ Checklist de test

Avant de valider la nouvelle interface, testez :

- [ ] Accès au dashboard : `/back-office/atak/roleplay`
- [ ] Header s'affiche correctement (stats, navigation)
- [ ] Section **Simulation réseau** : toggle, inputs, déconnexions
- [ ] Section **Liaison relais + Capteurs + Intel** : 3 cartes côte à côte
- [ ] Section **Zones géographiques** : ajouter, modifier, retirer
- [ ] Bouton **"🔬 Tests serveur"** : lance les tests via AJAX
- [ ] **Carte des zones** : s'affiche et se met à jour en temps réel
- [ ] Bouton **"💾 Enregistrer"** : sauvegarde la config
- [ ] Bouton **"🔄 Réinitialiser"** : demande confirmation et réinitialise
- [ ] Responsive : tester sur mobile/tablette/desktop
- [ ] Ancienne vue accessible avec `?legacy=1`

---

**Profitez de la nouvelle interface centralisée ! 🚀**
