# 🔧 Correctifs Overwatch Beta - Vue aérienne et marqueurs

## Problèmes identifiés

D'après la capture d'écran :

### 1. ❌ Vue aérienne ne fonctionne pas
**Cause** : Le script `atak-aerial.js` n'est pas chargé dans les démos

**Solution** : Intégrer le système de vue aérienne dans Overwatch Beta

### 2. ❌ Manque options 3D, pente, niveau, courbe, ombre
**Cause** : Ces options ne sont pas implémentées dans l'interface

**Solution** : Ajouter ces contrôles dans le panneau réglages

### 3. ❌ Manque les marqueurs comme sur /atak/
**Cause** : Les marqueurs Arma ne sont pas chargés ni affichés

**Solution** : Intégrer le système de marqueurs avec les icônes

### 4. ❌ Erreurs JavaScript dans la console
**Erreurs visibles** :
- `Uncaught SyntaxError: missing ) after argument list [at_areal_xhux]`
- `MouseEvent.movePressure est obsolète`
- `Erreur dans les liens source`
- Erreurs HTTP 404

---

## Actions correctives

### Phase 1 : Corriger les erreurs JS

1. **Charger atak-aerial.js dans les démos**
2. **Corriger les références de variables manquantes**
3. **Mettre à jour l'API PointerEvent**

### Phase 2 : Vue aérienne

1. **Activer ATAKAerial.attach() au chargement de la carte**
2. **Lier les boutons radio de vue**
3. **Vérifier les tuiles aériennes**

### Phase 3 : Options carte

Ajouter dans les réglages :

```html
<p class="ow-kicker">OPTIONS CARTE</p>
<fieldset class="ow-looks">
  <legend>Affichage</legend>
  
  <label>
    <input type="checkbox" data-ow-option="3d">
    <div>
      <strong>Mode 3D</strong>
      <small>Affichage en relief</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" data-ow-option="slope">
    <div>
      <strong>Pente</strong>
      <small>Visualisation du dénivelé</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" data-ow-option="contour">
    <div>
      <strong>Courbes de niveau</strong>
      <small>Lignes d'altitude</small>
    </div>
  </label>
  
  <label>
    <input type="checkbox" data-ow-option="shadow">
    <div>
      <strong>Ombres</strong>
      <small>Ombres portées du relief</small>
    </div>
  </label>
</fieldset>
```

### Phase 4 : Marqueurs Arma

1. **Charger arma-marker-catalog.js**
2. **Charger arma-marker-library-index.js**
3. **Afficher les marqueurs sur la carte**

---

## Fichiers à modifier

1. `public/assets/js/atak-overwatch-beta.js`
   - Ajouter ATAKAerial.attach() après création de la carte
   - Gérer les options 3D, pente, courbe, ombre
   
2. `public/assets/css/atak-overwatch-beta.css`
   - Styles pour les nouvelles options

3. `demo/demo-overwatch-beta-v3.html`
   - Charger atak-aerial.js
   - Charger scripts de marqueurs
   - Ajouter les options dans les réglages

---

## Priorités

1. 🔴 **URGENT** : Corriger les erreurs JS qui cassent l'interface
2. 🟠 **IMPORTANT** : Activer la vue aérienne
3. 🟡 **MOYEN** : Ajouter les options carte (3D, pente, etc.)
4. 🟢 **BONUS** : Intégrer les marqueurs Arma

---

**Status** : En cours de correction
