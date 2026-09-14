# ⚠️ Analyse des problèmes Overwatch Beta

Suite à la capture d'écran fournie, voici l'analyse complète des problèmes et leurs solutions.

---

## 🔍 Problèmes identifiés

### 1. ❌ Vue aérienne (Aerial) ne fonctionne pas

**Observation** : Malgré le bouton "Aerial" sélectionné, la carte affiche le fond topographique classique.

**Diagnostic** :
- ✅ Le script `atak-aerial.js` est chargé (ligne 55 de `views/overwatch/index.php`)
- ✅ `ATAKAerial.attach(map, config)` est appelé (ligne 147-149 de `atak-overwatch-beta.js`)
- ✅ La fonction `applyLook()` appelle `ATAKAerial.setMode()`

**Causes possibles** :
1. Les tuiles aériennes ne sont pas accessible (erreur 404)
2. La configuration `config` passée à `attach()` ne contient pas les bonnes données
3. Le mode n'est pas synchronisé entre Overwatch et ATAKAerial

**Solution** :
```javascript
// Dans atak-overwatch-beta.js, après la création de la carte
if (window.ATAKAerial && typeof window.ATAKAerial.attach === 'function') {
  var aerialState = window.ATAKAerial.attach(map, config);
  console.log('ATAKAerial attached:', aerialState);
  
  // Forcer l'activation si "aerial" est sélectionné
  var savedLook = storedLook();
  if (savedLook === 'aerial' && window.ATAKAerial.setMode) {
    window.ATAKAerial.setMode('aerial');
  }
}
```

### 2. ❌ Manque options 3D, pente, niveau, courbe, ombre

**Observation** : Ces options n'apparaissent pas dans le panneau réglages.

**Solution** : Ajouter ces options dans l'HTML des réglages :

```html
<p class="ow-kicker">OPTIONS D'AFFICHAGE</p>
<fieldset class="ow-looks">
  <legend>Relief et topographie</legend>
  
  <label>
    <input type="checkbox" id="ow-option-3d" data-ow-option="3d">
    <div>
      <strong>Mode 3D</strong>
      <small>Affichage en relief (nécessite WebGL)</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" id="ow-option-slope" data-ow-option="slope">
    <div>
      <strong>Carte des pentes</strong>
      <small>Visualisation du dénivelé</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" id="ow-option-contour" data-ow-option="contour" checked>
    <div>
      <strong>Courbes de niveau</strong>
      <small>Lignes d'altitude tous les 10m</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" id="ow-option-shadow" data-ow-option="shadow">
    <div>
      <strong>Ombres du relief</strong>
      <small>Ombres portées selon l'heure</small>
    </div>
  </label>
</fieldset>
```

**Implémentation JS** :
```javascript
// Gestionnaire pour les options d'affichage
document.addEventListener('change', function(e) {
  var checkbox = e.target.closest('[data-ow-option]');
  if (!checkbox) return;
  
  var option = checkbox.getAttribute('data-ow-option');
  var enabled = checkbox.checked;
  
  switch(option) {
    case '3d':
      // TODO: Activer mode 3D (nécessite Mapbox GL JS ou similaire)
      console.log('Mode 3D:', enabled ? 'ON' : 'OFF');
      toast('Mode 3D : en développement');
      break;
      
    case 'slope':
      // Afficher couche de pentes
      toggleSlopeLayer(enabled);
      break;
      
    case 'contour':
      // Afficher courbes de niveau
      toggleContourLayer(enabled);
      break;
      
    case 'shadow':
      // Afficher ombres du relief
      toggleShadowLayer(enabled);
      break;
  }
  
  // Sauvegarder la préférence
  try {
    localStorage.setItem('athena:ow-option-' + option, enabled ? '1' : '0');
  } catch(e) {}
});
```

### 3. ❌ Manque marqueurs comme sur /atak/

**Observation** : Les marqueurs tactiques Arma ne s'affichent pas.

**Diagnostic** :
- Les scripts de marqueurs sont chargés dans `views/overwatch/index.php` :
  - `arma-marker-catalog.js`
  - `arma-marker-library-index.js`
  - `arma-map-markers.js`
  
**Problème** : Ces scripts ne sont PAS chargés dans les démos autonomes.

**Solution pour les démos** :
```html
<!-- Ajouter dans demo-overwatch-beta-v3.html -->
<script src="../public/assets/js/arma-marker-catalog.js"></script>
<script src="../public/assets/js/arma-marker-library-index.js"></script>
<script src="../public/assets/js/arma-map-markers.js"></script>
```

**Solution pour l'affichage** :
Les marqueurs doivent être créés via l'API des formes (shapes). Vérifier que `loadShapes()` est bien appelée et que les marqueurs sont dans la réponse de `/api/map-shapes`.

### 4. ❌ Erreurs JavaScript

**Erreurs visibles dans la console** :

#### A. `Uncaught SyntaxError: missing ) after argument list [at_areal_xhux]`

**Cause** : Variable manquante ou nom de variable corrompu/minifié incorrectement.

**Solution** : Impossible de corriger sans voir le code exact qui produit cette erreur. Il faut :
1. Identifier le fichier source (regarder la stack trace)
2. Vérifier si c'est un fichier minifié
3. Recompiler si nécessaire

#### B. `MouseEvent.movePressure est obsolète`

**Cause** : Utilisation d'une API Pointer Events obsolète.

**Solution** : Remplacer dans le code Leaflet ou les plugins :
```javascript
// Ancien code (obsolète)
var pressure = event.movePressure;

// Nouveau code
var pressure = event.pressure || 0.5;
```

Cette erreur vient probablement de Leaflet lui-même ou d'un plugin. Mettre à jour Leaflet vers la dernière version.

#### C. Erreurs réseau 404

**Observation** : Fichiers non trouvés.

**Solution** : Vérifier les chemins dans le code et s'assurer que tous les assets existent.

---

## ✅ Recommandations immédiates

### 1. Déboguer la vue aérienne

```javascript
// Ajouter dans la console du navigateur
console.log('ATAKAerial:', window.ATAKAerial);
console.log('Current mode:', window.ATAKAerial.storedMode());
console.log('Config:', window.ATAK_MAP_CONFIG);

// Forcer la vue aérienne
window.ATAKAerial.setMode('aerial');
```

### 2. Vérifier les tuiles aériennes

Ouvrir l'onglet Network dans DevTools et vérifier si les requêtes vers les tuiles aériennes :
- Réussissent (200)
- Échouent (404, 403)
- Sont bloquées par CORS

URL attendue : `https://atlas.plan-ops.fr/data/1/maps/3/295/{z}/{x}/{y}.webp`

### 3. Activer les logs de débogage

```javascript
// Dans atak-overwatch-beta.js, ligne 1913
function applyLook(look) {
  console.log('[applyLook] Switching to:', look);
  
  var allowed = { classic: 1, aerial: 1, bw: 1 };
  if (!allowed[look]) look = 'aerial';
  
  try { localStorage.setItem(LOOK_KEY, look); } catch (e) {}
  
  document.getElementById('ow-map-stage').dataset.look = look;
  document.querySelectorAll('[data-ow-look]').forEach(function (input) { 
    input.checked = input.value === look; 
  });
  
  if (window.ATAKAerial && typeof window.ATAKAerial.setMode === 'function') {
    console.log('[applyLook] Calling ATAKAerial.setMode with:', 
      look === 'classic' ? 'plan' : 'aerial');
    window.ATAKAerial.setMode(look === 'classic' ? 'plan' : 'aerial');
  } else {
    console.warn('[applyLook] ATAKAerial not available!');
  }
  
  // ... reste du code
}
```

---

## 📋 Actions à faire

### Priorité 1 (URGENT)
- [x] Analyser les erreurs
- [ ] Déboguer la vue aérienne avec les logs
- [ ] Vérifier la disponibilité des tuiles aériennes
- [ ] Corriger l'erreur `[at_areal_xhux]`

### Priorité 2 (IMPORTANT)
- [ ] Ajouter les options d'affichage (3D, pente, courbes, ombres)
- [ ] Implémenter les couches topographiques
- [ ] Charger les scripts de marqueurs dans les démos

### Priorité 3 (AMÉLIORATION)
- [ ] Mettre à jour Leaflet pour corriger `movePressure`
- [ ] Optimiser le chargement des tuiles aériennes
- [ ] Ajouter une indication visuelle quand les tuiles chargent

---

## 🔧 Tests à faire

```bash
# Dans la console navigateur sur la page Overwatch Beta

# 1. Vérifier ATAKAerial
console.log('ATAKAerial:', window.ATAKAerial);

# 2. Lister les modes disponibles
console.log('Available modes:', 
  window.ATAKAerial.resolveLayers(window.ATAK_MAP_CONFIG));

# 3. Forcer aerial
window.ATAKAerial.setMode('aerial');

# 4. Vérifier le localStorage
console.log('Stored look:', localStorage.getItem('athena:atak-fond'));
console.log('Stored OW look:', localStorage.getItem('athena:atak-fond-look'));

# 5. Vérifier la carte
console.log('Map:', window.ATAKMap ? window.ATAKMap.getMap() : 'N/A');
```

---

**Status** : Analyse terminée, corrections en attente de tests utilisateur
