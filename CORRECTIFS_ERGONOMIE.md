# 🔧 Correctifs Ergonomie Overwatch Beta

## Problèmes identifiés et solutions

### 1. ❌ Bouton de repli des paramètres ne fonctionne pas

**Cause** : Event listener manquant pour le bouton `#ow-toggle-settings`

**Solution** : ✅ CORRIGÉ dans `atak-overwatch-beta.js`

```javascript
// Ajouté après ligne 2785
var toggleSettingsBtn = document.getElementById('ow-toggle-settings');
if (toggleSettingsBtn) {
  toggleSettingsBtn.addEventListener('click', toggleSettingsAside);
}

restoreSettingsCollapsed();
```

---

### 2. ❌ Boutons dupliqués (AO LIVE COMMS MISSION)

**Observation** : Les boutons dans `.ow-map-tools` dupliquent ceux du header `.ow-nav`

**Localisation** : 
- `demo/demo-overwatch-beta.html` lignes 159-164
- Similaire dans les autres démos

**Solution** : SUPPRIMER la section `.ow-map-tools` complètement

```html
<!-- À SUPPRIMER -->
<div class="ow-map-tools">
  <button type="button" class="is-active" data-view="overwatch">CARTE</button>
  <button type="button" data-view="comms">COMMS</button>
  <button type="button" data-view="layers">LAYERS</button>
  <button type="button" data-view="mission">MISSION</button>
  <button type="button" data-view="intel">INTEL</button>
  <button type="button" data-view="tools">TOOLS</button>
</div>
```

**Alternative** : Garder uniquement les boutons utiles (LAYERS, outils de carte) :
```html
<div class="ow-map-tools">
  <button type="button" data-view="layers">CALQUES</button>
  <button type="button" data-ow-fullscreen>PLEIN ÉCRAN</button>
</div>
```

---

### 3. ❌ Calques vides et non fonctionnels

**Observation** : Section "CALQUES" dans réglages est vide ou ne fonctionne pas

**Cause probable** : 
1. Les checkbox des calques n'ont pas d'event listeners
2. La logique de basculement des calques n'est pas implémentée

**Solution** : Ajouter les event listeners dans `atak-overwatch-beta.js`

```javascript
// Gestion des calques (layers)
document.querySelectorAll('[data-ow-layer]').forEach(function(checkbox) {
  checkbox.addEventListener('change', function() {
    var layer = checkbox.getAttribute('data-ow-layer');
    var visible = checkbox.checked;
    
    switch(layer) {
      case 'units':
        hiddenLayers.units = !visible;
        break;
      case 'labels':
        // Gérer visibilité des étiquettes
        document.documentElement.style.setProperty(
          '--ow-label-opacity', 
          visible ? '1' : '0'
        );
        break;
      case 'shapes':
        hiddenLayers.shapes = !visible;
        Object.keys(shapeLayers).forEach(function(id) {
          var layer = shapeLayers[id];
          if (visible) {
            map.addLayer(layer);
          } else {
            map.removeLayer(layer);
          }
        });
        break;
      case 'aerial-view':
        // Activer vue aérienne
        applyLook(visible ? 'aerial' : 'classic');
        break;
    }
    
    // Rafraîchir la carte
    renderMap();
    
    // Sauvegarder la préférence
    try {
      localStorage.setItem('athena:ow-layer-' + layer, visible ? '1' : '0');
    } catch(e) {}
  });
});
```

---

### 4. ❌ Barre d'outils latérale trop longue

**Observation** : `.ow-rail` (barre d'outils à gauche de la carte) prend trop de place

**Solutions proposées** :

#### Option A : Réduire la taille des boutons
```css
.ow-rail button {
  width: 36px;  /* au lieu de 44px */
  height: 36px;
  padding: 6px;
}

.ow-rail button svg {
  width: 18px;  /* au lieu de 20px */
  height: 18px;
}
```

#### Option B : Grouper les boutons par catégorie
```css
.ow-rail {
  display: flex;
  flex-direction: column;
  gap: 4px;  /* moins d'espace entre boutons */
}

/* Ajouter un séparateur visuel */
.ow-rail-separator {
  height: 1px;
  background: var(--athena-line);
  margin: 6px 0;
}
```

#### Option C : Rendre la barre collapsible
```html
<div class="ow-rail" data-collapsed="false">
  <button type="button" class="ow-rail-toggle">«</button>
  <!-- Reste des boutons -->
</div>
```

```css
.ow-rail[data-collapsed="true"] {
  width: 12px;
}

.ow-rail[data-collapsed="true"] button:not(.ow-rail-toggle) {
  display: none;
}
```

---

## Autres améliorations ergonomiques suggérées

### 5. Simplifier la statusbar

Trop d'éléments dans la statusbar. Garder seulement l'essentiel :

```html
<div class="ow-statusbar">
  <!-- Statut connexion -->
  <span id="ow-status-text">
    <b class="ow-green">• LIVE</b> 
    <span class="ow-status-detail">Altis · 12 contacts</span>
  </span>
  
  <div class="ow-statusbar-right">
    <!-- Météo si disponible -->
    <button type="button" class="ow-wx" id="ow-weather-chip" hidden>
      🌤️ 18°C
    </button>
    
    <!-- Boutons d'action seulement -->
    <button type="button" class="ow-mini" onclick="reloadData()">
      <svg>...</svg> SYNC
    </button>
  </div>
</div>
```

### 6. Améliorer la hiérarchie visuelle

```css
/* Réduire la taille du topbar */
.ow-topbar {
  height: 52px;  /* au lieu de 58px */
  padding: 0 16px;
}

/* Réduire la statusbar */
.ow-statusbar {
  height: 30px;  /* au lieu de 34px */
  font-size: 9px;  /* au lieu de 8px */
}

/* Optimiser l'espace des réglages */
.ow-settings {
  width: 280px;  /* au lieu de 330px */
}

.ow-workspace {
  grid-template-columns: 280px minmax(0,1fr) 330px;
}
```

---

## Checklist des corrections

- [x] Ajouter event listener pour bouton repli réglages
- [x] Identifier boutons dupliqués
- [ ] Supprimer `.ow-map-tools` ou ne garder que boutons utiles
- [ ] Ajouter event listeners pour calques
- [ ] Restaurer état des calques depuis localStorage
- [ ] Réduire taille barre d'outils latérale
- [ ] Simplifier statusbar
- [ ] Tester tous les changements

---

## Fichiers à modifier

1. **`public/assets/js/atak-overwatch-beta.js`**
   - ✅ Event listener bouton repli (FAIT)
   - ⏳ Event listeners calques
   - ⏳ Logique bascule calques

2. **`demo/demo-overwatch-beta.html`**
   - ⏳ Supprimer `.ow-map-tools`
   - ⏳ Simplifier statusbar

3. **`public/assets/css/atak-overwatch-beta.css`**
   - ⏳ Réduire taille rail
   - ⏳ Optimiser espacement

4. **Appliquer aux autres démos**
   - `demo/demo-overwatch-beta-v2.html`
   - `demo/demo-overwatch-beta-v3.html`

---

**Status** : Correction 1/4 terminée, 3 corrections en cours
