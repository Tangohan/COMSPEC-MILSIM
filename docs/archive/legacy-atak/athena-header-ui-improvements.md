# Améliorations UI Header Athena - Guide technique

## 🎯 Vue d'ensemble

Cette mise à jour modernise le header Athena (couche UI Enhanced de l'ATAK) pour offrir une expérience cohérente avec les améliorations de l'interface ATAK, tout en conservant l'identité visuelle distinctive du portail Athena.

## 🎨 Variables CSS ajoutées

```css
:root {
  /* Couleur mint bright pour highlights */
  --athena-header-mint-bright: #6ee7b7;
  
  /* Système d'ombres cohérent avec ATAK */
  --athena-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
  --athena-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
  --athena-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
}
```

## 📦 Composants améliorés

### 1. Bouton CTA principal (`athena-header__cta`)

#### Avant
```css
background: #059669;
border-radius: 2px;
box-shadow: (aucune par défaut);
```

#### Après
```css
background: linear-gradient(135deg, #059669, #047857);
border-radius: 6px;
padding: 0 1.1rem;  /* +10% */
box-shadow: 
  0 2px 8px rgba(5, 150, 105, 0.3),
  inset 0 1px 0 rgba(255, 255, 255, 0.2);
```

#### Fonctionnalités ajoutées

**Pseudo-élément pour brillance**
```css
.athena-header__cta::before {
  content: '';
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: radial-gradient(circle, 
    rgba(255, 255, 255, 0.2), 
    transparent 70%
  );
  opacity: 0 → 1 (on hover);
}
```

**States interactifs**
```css
:hover {
  background: linear-gradient(135deg, #047857, #065f46);
  transform: translateY(-2px);
  box-shadow: 
    0 4px 16px rgba(5, 150, 105, 0.45),
    inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

:active {
  transform: translateY(0);
  box-shadow: 
    0 2px 8px rgba(5, 150, 105, 0.3),
    inset 0 2px 4px rgba(0, 0, 0, 0.2);
}

.is-active {
  box-shadow: 
    inset 0 -2px 0 rgba(255, 255, 255, 0.25),
    0 2px 8px rgba(5, 150, 105, 0.3);
}
```

### 2. Boutons secondaires (menu/icon/profile)

#### Structure améliorée
```css
/* Avant */
border: 1px solid var(--athena-header-line);  /* rgba(255,255,255,0.12) */
background: rgba(255, 255, 255, 0.04);
border-radius: 2px;

/* Après */
border: 1px solid rgba(255, 255, 255, 0.15);  /* +25% opacité */
background: linear-gradient(135deg, 
  rgba(255, 255, 255, 0.06), 
  rgba(255, 255, 255, 0.04)
);
border-radius: 6px;
box-shadow: var(--athena-shadow-sm);
```

#### Padding augmenté
```css
.athena-header__menu-trigger {
  padding: 0 0.85rem → 0 0.9rem;
}

.athena-header__icon-btn {
  padding: 0 0.7rem → 0 0.75rem;
}
```

#### Hover state amélioré
```css
:hover,
:focus-visible,
[aria-expanded="true"] {
  color: #fff;
  border-color: rgba(5, 150, 105, 0.5);
  background: linear-gradient(135deg, 
    rgba(5, 150, 105, 0.2), 
    rgba(5, 150, 105, 0.15)
  );
  box-shadow: 
    0 0 12px rgba(5, 150, 105, 0.2),
    var(--athena-shadow);
  transform: translateY(-1px);
}
```

### 3. Badges de notification (`athena-header__dot`)

#### Amélioration visuelle majeure

**Avant**
```css
background: var(--athena-header-mint);
box-shadow: 0 0 0 2px #050505;
width: 16px; height: 16px;
```

**Après**
```css
background: radial-gradient(circle, 
  var(--athena-header-mint-bright), 
  var(--athena-header-mint)
);
box-shadow: 
  0 0 0 2.5px #050505,
  0 0 12px rgba(52, 211, 153, 0.6),
  inset 0 1px 0 rgba(255, 255, 255, 0.4);
width: 17px; height: 17px;
animation: athena-badge-pulse 2s ease-in-out infinite;
```

#### Animations

**Badge normal**
```css
@keyframes athena-badge-pulse {
  0%, 100% { 
    box-shadow: 
      0 0 0 2.5px #050505,
      0 0 12px rgba(52, 211, 153, 0.6),
      inset 0 1px 0 rgba(255, 255, 255, 0.4);
  }
  50% { 
    box-shadow: 
      0 0 0 2.5px #050505,
      0 0 18px rgba(52, 211, 153, 0.9),
      inset 0 1px 0 rgba(255, 255, 255, 0.5);
  }
}
```

**Badge urgent**
```css
.athena-header__dot--urgent {
  background: radial-gradient(circle, #fb7185, #e11d48);
  animation: athena-badge-pulse-urgent 1.5s ease-in-out infinite;
}

@keyframes athena-badge-pulse-urgent {
  0%, 100% { 
    box-shadow: 
      0 0 0 2.5px #050505,
      0 0 12px rgba(225, 29, 72, 0.7),
      inset 0 1px 0 rgba(255, 255, 255, 0.3);
  }
  50% { 
    box-shadow: 
      0 0 0 2.5px #050505,
      0 0 20px rgba(225, 29, 72, 1),
      inset 0 1px 0 rgba(255, 255, 255, 0.4);
  }
}
```

### 4. Panels dropdown

#### Structure de base (tous les panels)

**Avant**
```css
border: 1px solid rgba(255, 255, 255, 0.1);
background: rgba(8, 8, 11, 0.96);
box-shadow: 0 18px 48px rgba(0, 0, 0, 0.55);
backdrop-filter: blur(22px);
```

**Après**
```css
border: 1px solid rgba(255, 255, 255, 0.12);
background: linear-gradient(135deg, 
  rgba(12, 12, 15, 0.98), 
  rgba(8, 8, 11, 0.96)
);
backdrop-filter: blur(24px);
-webkit-backdrop-filter: blur(24px);
box-shadow: 
  0 0 0 1px rgba(5, 150, 105, 0.08),
  0 20px 56px rgba(0, 0, 0, 0.6),
  inset 0 1px 0 rgba(255, 255, 255, 0.08);
```

#### Panel espaces (`--panel--espaces`)

**Border-radius**
```css
border-radius: 14px → 16px;
padding: 1rem → 1.1rem;
```

**Items espaces**
```css
/* Avant */
padding: 0.7rem 0.75rem;
border: 1px solid rgba(255, 255, 255, 0.07);
background: rgba(255, 255, 255, 0.02);

/* Après */
padding: 0.75rem 0.85rem;
border: 1px solid rgba(255, 255, 255, 0.08);
background: linear-gradient(135deg, 
  rgba(255, 255, 255, 0.04), 
  rgba(255, 255, 255, 0.02)
);
box-shadow: var(--athena-shadow-sm);
position: relative;
overflow: hidden;
```

**Pseudo-élément brillance**
```css
.athena-header__espace-item::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, 
    transparent, 
    rgba(255, 255, 255, 0.1), 
    transparent
  );
  opacity: 0 → 1 (on hover);
}
```

**Hover state**
```css
:hover {
  background: linear-gradient(135deg, 
    rgba(5, 150, 105, 0.12), 
    rgba(5, 150, 105, 0.08)
  );
  border-color: rgba(5, 150, 105, 0.45);
  box-shadow: 
    0 0 12px rgba(5, 150, 105, 0.15),
    var(--athena-shadow);
  transform: translateY(-1px);
}
```

**Abbréviations espaces**
```css
/* Avant */
border: 1px solid rgba(52, 211, 153, 0.45);
background: transparent;
min-width: 2.55rem;
height: 1.7rem;

/* Après */
border: 1px solid rgba(52, 211, 153, 0.5);
background: linear-gradient(135deg, 
  rgba(52, 211, 153, 0.15), 
  rgba(52, 211, 153, 0.08)
);
min-width: 2.6rem;
height: 1.8rem;
border-radius: 4px → 6px;
color: var(--athena-header-mint) → var(--athena-header-mint-bright);
box-shadow: 
  inset 0 1px 0 rgba(255, 255, 255, 0.15),
  0 0 8px rgba(52, 211, 153, 0.2);
```

#### Panel menu rapide (`--panel--quick`)

**Container**
```css
padding: 0.85rem → 0.9rem;
border-radius: 12px → 14px;
```

**Items de menu**
```css
/* Avant */
min-height: 40px;
background: #09090d;
padding: 0 12px;

/* Après */
min-height: 42px;
background: linear-gradient(135deg, 
  rgba(9, 9, 13, 0.95), 
  rgba(6, 6, 10, 0.9)
);
padding: 0 14px;
position: relative;
```

**Indicator gauche**
```css
.athena-header__quick-grid a::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 2px;
  background: var(--athena-header-accent);
  opacity: 0 → 1 (on hover);
}
```

**Hover**
```css
:hover {
  background: linear-gradient(135deg, 
    rgba(5, 150, 105, 0.22), 
    rgba(5, 150, 105, 0.15)
  );
}
```

#### Panel notifications (`--panel--notif`)

**Applique le même pattern** : gradient background, backdrop blur 24px, triple-layer shadow

#### Panel profil (`--panel--profile`)

**Container avec scroll**
```css
max-height: min(calc(100vh - 4.75rem), 560px);
overflow-y: auto;
```

**Menu items**
```css
/* Avant */
min-height: 40px;
padding: 8px 10px;
background: #09090d;

/* Après */
min-height: 42px;
padding: 10px 12px;
background: linear-gradient(135deg, 
  rgba(9, 9, 13, 0.95), 
  rgba(6, 6, 10, 0.9)
);
```

**Indicator gauche avec glow**
```css
::before {
  width: 3px;
  background: linear-gradient(180deg, 
    transparent, 
    var(--athena-header-accent), 
    transparent
  );
  box-shadow: 0 0 8px rgba(5, 150, 105, 0.4);
  opacity: 0 → 1 (on hover);
}
```

**Hover avec transition color**
```css
:hover {
  background: linear-gradient(135deg, 
    rgba(5, 150, 105, 0.18), 
    rgba(5, 150, 105, 0.12)
  );
}

:hover span {
  color: #fff → var(--athena-header-mint-bright);
}
```

**Item danger**
```css
button.danger span {
  color: var(--athena-header-danger);
}

button.danger:hover {
  background: linear-gradient(135deg, 
    rgba(251, 113, 133, 0.15), 
    rgba(251, 113, 133, 0.1)
  );
}

button.danger:hover span {
  color: #fecaca;
}

button.danger::before {
  background: linear-gradient(180deg, 
    transparent, 
    var(--athena-header-danger), 
    transparent
  );
  box-shadow: 0 0 8px rgba(251, 113, 133, 0.4);
}
```

## 🎨 Patterns de design réutilisables

### 1. Triple-layer box-shadow

Utilisé sur tous les panels et éléments flottants :

```css
box-shadow: 
  /* Layer 1 : Outline subtile avec accent color */
  0 0 0 1px rgba(5, 150, 105, 0.08),
  
  /* Layer 2 : Ombre de profondeur */
  0 20px 56px rgba(0, 0, 0, 0.6),
  
  /* Layer 3 : Inset highlight pour brillance */
  inset 0 1px 0 rgba(255, 255, 255, 0.08);
```

### 2. Gradient directionnel cohérent

Tous les gradients utilisent `135deg` pour cohérence :

```css
background: linear-gradient(135deg, light-color, dark-color);
```

### 3. Transform feedback

Tous les éléments interactifs utilisent `translateY` :

```css
/* Boutons primaires */
:hover { transform: translateY(-2px); }

/* Boutons secondaires et items */
:hover { transform: translateY(-1px); }

/* Active state (retour à position normale) */
:active { transform: translateY(0); }
```

### 4. Pseudo-élément indicator

Pattern réutilisable pour indicateurs gauche/haut :

```css
.element {
  position: relative;
  overflow: hidden;
}

.element::before {
  content: '';
  position: absolute;
  /* Position selon besoin (top/left/right) */
  background: gradient ou color;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.element:hover::before {
  opacity: 1;
}
```

### 5. Backdrop-filter avec fallbacks

```css
backdrop-filter: blur(24px);
-webkit-backdrop-filter: blur(24px);
/* Background gradient comme fallback si backdrop-filter non supporté */
background: linear-gradient(...);
```

## 🎬 Animations

### Timing et durées

```css
/* Transitions standards */
transition: all 0.2s ease;

/* Animations pulse */
animation: name 2s ease-in-out infinite;     /* Normal */
animation: name 1.5s ease-in-out infinite;   /* Urgent */
```

### States d'animation

```css
/* Pulse : 2 keyframes (0/100% et 50%) */
@keyframes example-pulse {
  0%, 100% { 
    /* État de base */
  }
  50% { 
    /* État peak (intensité maximale) */
  }
}
```

## 📊 Métriques de changements

### Dimensions

| Élément | Avant | Après | Change |
|---------|-------|-------|--------|
| CTA padding | 1rem | 1.1rem | +10% |
| Menu trigger padding | 0.85rem | 0.9rem | +6% |
| Icon btn padding | 0.7rem | 0.75rem | +7% |
| Badge size | 16px | 17px | +6% |
| Panel espaces padding | 1rem | 1.1rem | +10% |
| Panel quick padding | 0.85rem | 0.9rem | +6% |
| Espace item padding | 0.7/0.75rem | 0.75/0.85rem | +7% |
| Abbr min-width | 2.55rem | 2.6rem | +2% |
| Abbr height | 1.7rem | 1.8rem | +6% |
| Quick item height | 40px | 42px | +5% |
| Profile item height | 40px | 42px | +5% |
| Profile item padding | 8/10px | 10/12px | +20% |

### Border-radius

| Élément | Avant | Après |
|---------|-------|-------|
| CTA | 2px | 6px |
| Boutons secondaires | 2px | 6px |
| Panels espaces | 14px | 16px |
| Panels quick/notif | 12px | 14px |
| Panels profile | 12px | 14px |
| Abbr | 4px | 6px |

### Opacité borders

| Élément | Avant | Après | Change |
|---------|-------|-------|--------|
| Boutons secondaires | 0.12 | 0.15 | +25% |
| Panels | 0.1 | 0.12 | +20% |
| Espace items | 0.07 | 0.08 | +14% |
| Abbr | 0.45 | 0.5 | +11% |

### Backdrop-filter

| Élément | Avant | Après |
|---------|-------|-------|
| Panels (tous) | blur(22px) | blur(24px) |

## 🔍 Points d'attention

### Performance

- **Backdrop-filter** : peut impacter les performances sur anciennes cartes graphiques
- **Animations** : utilisent `transform` et `opacity` (GPU-accelerated)
- **Box-shadows** : triple-layer peut être coûteux, mais acceptable pour éléments non nombreux

### Compatibilité

- **Backdrop-filter** : supporté dans tous les navigateurs modernes, fallback via background gradient
- **Gradients** : supportés partout
- **Animations** : supportées partout, respecte `prefers-reduced-motion`

### Maintenance

- **Variables CSS** : facilite les ajustements globaux
- **Patterns réutilisables** : cohérence et maintenabilité
- **Naming cohérent** : suit la convention BEM existante

## ✅ Checklist d'implémentation

- [x] Variables CSS ajoutées
- [x] Bouton CTA amélioré avec pseudo-élément
- [x] Boutons secondaires avec gradient et shadows
- [x] Badges avec gradient radial et animations
- [x] Panels avec backdrop-filter et triple-layer shadow
- [x] Items espaces avec pseudo-élément et hover states
- [x] Abbréviations avec gradient et glow
- [x] Menu rapide avec indicator et hover
- [x] Menu profil avec indicator glow et transitions
- [x] Item danger avec states spécifiques
- [x] Toutes les transitions en 0.2s ease
- [x] Tous les border-radius modernisés
- [x] Tous les paddings augmentés
- [x] Documentation complète

---

**Résultat** : Header Athena cohérent avec l'ATAK, moderne et professionnel, avec feedback visuel clair sur toutes les interactions et une identité visuelle renforcée.
