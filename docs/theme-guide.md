# Guide du thème — Plateforme MILSIM / Training Command Interface

## 1) Objectif
Ce thème doit évoquer un **logiciel interne de commandement** et de formation militaire.

Le rendu attendu transmet :
- la rigueur,
- la discipline,
- la hiérarchie,
- un environnement d’unité opérationnelle.

Le rendu doit éviter toute apparence de site gaming, e-learning scolaire ou corporate générique.

## 2) Philosophie UX
Le design suit 4 piliers :

1. **Clarté** : l’utilisateur sait immédiatement où il se trouve et quelle action effectuer.
2. **Hiérarchie** : l’information suit `Command > Module > Section > Instruction`.
3. **Discipline visuelle** : interface sobre, stable, structurée, sans surcharge.
4. **Immersion** : sensation de console tactique / centre de commandement.

## 3) Structure de layout
Layout standard recommandé :

- **Top Bar** : statut système, notifications, opérateur, horloge.
- **Sidebar** : logo unité, nom de module, navigation sections, progression.
- **Main Panel** : contenu des modules et interactions pédagogiques.

## 4) Palette de couleurs
Palette de référence :

- Background : `#050810`
- Surface : `#0b1220`
- Bordures : `rgba(255,255,255,0.08)`
- Accent principal (validation/actions) : `#10b981`
- Accent secondaire (renseignement) : `#3b82f6`
- Warning : `#f59e0b`
- Danger : `#ef4444`

## 5) Typographie
- Police principale : **Inter**.
- Titres : uppercase, `font-weight` 800–900, tracking large.
- Labels : petite taille, uppercase, tracking large.
- Données techniques (token, logs, ID) : police monospace.

## 6) Composants UI
- `ui-card` : surface sombre, bordure discrète, rayon large, padding medium.
- `ui-badge` : états `ACTIVE`, `COMPLETED`, `LOCKED`, `STANDBY`.
- `ui-status` : indicateurs ponctuels (`ONLINE`, `READY`, `ACTIVE`).
- `ui-button--primary` : action principale (accent vert).
- `ui-button--secondary` : action secondaire (fond sombre).
- `ui-button--command` : action système.

## 7) Effets visuels
Effets autorisés et subtils :
- grain overlay,
- grille tactique,
- scanlines légères.

Durées d’animation recommandées : **150–300ms**.

## 8) Structure training
Pipeline de module :
1. INTRO
2. LESSON
3. READING
4. QUIZ (score requis 80%)
5. CERTIFICATION

## 9) Page Gate Module
La page Gate doit inclure :
- operator name,
- unit,
- checklist de confirmation,
- session token,
- bouton `OPEN TRAINING PACKAGE` bloqué tant que les prérequis ne sont pas complets.

## 10) Page Module
Doit afficher :
- sidebar de module,
- contenu central,
- progression,
- navigation section précédente/suivante.

## 11) Page Certificat
Le certificat final affiche :
- nom opérateur,
- nom module,
- date de validation,
- signature ou label Training Command.

## 12) Icônes
Utiliser des SVG orientés mission : helmet, drone, radar, shield, target, satellite, map.

## 13) Responsive
- Desktop prioritaire.
- Tablette : adaptation grille.
- Mobile : navigation compacte.

## 14) Nommage de classes
Convention recommandée :
- `ui-card`
- `ui-badge`
- `ui-panel`
- `ui-sidebar`
- `ui-commandbar`
- `ui-module`

## 15) Structure de fichiers recommandée
```txt
/theme
  /layout
  /components
  /modules
  /training
  /certificates
  /assets
```

## 16) Objectif final
L’interface doit être perçue comme :
- un logiciel interne militaire,
- un centre de formation des forces spéciales,
- une console de commandement.

Jamais comme un site web standard.
