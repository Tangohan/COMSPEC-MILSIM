# Guide visuel — assets ATAK roleplay (DA + placeholders)

Ce document explique **à quoi sert chaque image**, **où elle est utilisée**, et **comment la produire** pour remplacer les placeholders de développement.

> Les PNG colorés avec du texte (« CRACKED », « EAGLE », etc.) générés automatiquement sont des **brouillons techniques** : ils permettent de tester le câblage in-game / web sans bloquer le dev. Les visuels finaux doivent suivre la direction artistique ci-dessous.

---

## Direction artistique (DA) COMSPEC Overwatch

### Identité

| Élément | Valeur |
|---|---|
| Ton | Militaire moderne, sobre, crédible milsim — pas sci-fi, pas cartoon |
| Palette principale | Fond `#0a1628` · accent liaison `#33d9a5` · alerte `#ff8a4a` · or badge `#e8b84a` |
| Typo (web / overlays texte) | Monospace ou condensed (Roboto Condensed, IBM Plex Mono) |
| Formes | Angles légèrement coupés, badges plats, icônes lisibles à 32 px |
| Références | ATAK / cTab (lisibilité tactique), pas de copie d’assets tiers (GPL cTab interdit pour les nouveaux overlays) |

### Règles communes

1. **Transparence** : icônes et overlays dommages → fond alpha (PNG → PAA `_ca`).
2. **Contraste** : lisible sur fond sombre (hub, tablette, carte Leaflet).
3. **Pas de jargon technique** sur les visuels joueur (« NO SIGNAL » ou « Liaison perdue » OK ; « packet_loss » interdit).
4. **Conversion mod Arma** : PNG → TexView 2 → `.paa` (même nom de base, suffixe `_ca` si canal alpha).

---

## Les deux familles d’assets

```text
mod/.../connect/img/          → affichés in-game (hub, tablette, modules Zeus)
public/assets/img/            → portail web Tacmap / TOC
```

---

## Mod Arma — device (cadres & OSD)

| Fichier placeholder | Rôle | Où c’est lu | Spécification finale |
|---|---|---|---|
| `comspec_phone_bg_ca` | Cadre téléphone autour de l’écran web | `display_device.hpp` | 2048×2048, zone écran vide ≈ (452,713)–(1550,1339) |
| `comspec_tablet_bg_ca` / `athena_tablet` | Cadre tablette | `display_device.hpp` | 2048×1024 (2:1), bezel réaliste |
| `comspec_icon_battery_ca` + `comspec_battery_*` | Pastille batterie (4 états) | OSD barre d’état | 128×64 chaque : pleine / moyenne / faible / critique |
| `comspec_icon_signal_ca` + `comspec_signal_bars_0..4` | Barres signal (0–4) | OSD barre d’état | 64×32 ou sprite horizontal |
| `comspec_icon_phone` / `tablet` / `mail` | Icônes apps hub | `display_hub.hpp` | 256×256 flat tactique |
| `comspec_atak_logo` | Splash / branding mod | Workshop, menu | 512×512 ou 1024×1024, fond transparent |

**Placeholder actuel** : rectangle coloré + label texte → remplacer par bezel + icônes monochromes teal/blanc.

---

## Mod Arma — overlays roleplay (priorité haute)

Ces textures sont référencées dans `display_device_macros.hpp` et affichées par `fn_updateAtakEnhancedRoleplay.sqf` (overlays texte + futures textures).

| # | Fichier | État | Fonction in-game | Brief créatif |
|---|---|---|---|---|
| 11 | `comspec_overlay_screen_cracked_ca` | **Brouillon IA 23/08** | Écran fissuré (dommage niveau 2, liaison maintenue) | Verre radial fissuré, fond sombre 40 % alpha, léger reflet vert HUD |
| 12 | `comspec_overlay_screen_off_ca` | **Brouillon IA 23/08** | ATAK éteint (niveau 1) | Noir 85–90 % alpha, vignette ; pas de texte (le SQF affiche « ATAK ÉTEINT ») |
| 13 | `comspec_overlay_static_noise_ca` | **Brouillon IA 23/08** | Brouillage / glitch (tile) | 512×512 tileable, grain TV, niveaux de gris |
| 14 | `comspec_overlay_no_signal_ca` | **Brouillon IA 23/08** | Déconnexion réseau simulée | Bandeau CRT « LIAISON PERDUE », scanlines, 1024×512 |
| 15 | `comspec_overlay_low_signal_ca` | **Brouillon IA 23/08** | Zone couverture dégradée | Même style que #14 mais plus léger (pas de texte, interference subtle) |

### Comportement lié (pour les graphistes)

| Situation joueur | Overlay + texte hub |
|---|---|
| Coupure réseau aléatoire | #14 + « Liaison ATAK perdue » + compte à rebours |
| Zone sans couverture | #14 ou icône #17 + bannière zone |
| Écran cassé (torse/choc) | #11 + « Écran endommagé — connexion maintenue » |
| ATAK éteint | #12 + « ATAK éteint » |
| Gel appareil (crash ATAK) | #13 + « Terminal bloqué — redémarrage… » |

---

## Mod Arma — icônes modules Zeus

| Fichier | Fonction |
|---|---|
| `comspec_icon_jammer_ca` | Module Eden « Brouilleur ATAK actif » |
| `comspec_icon_no_coverage_ca` | Module « Zone sans couverture » |
| `comspec_icon_device_destroyed_ca` | État appareil détruit (niveau 3) |
| `comspec_icon_reboot_ca` | Action ACE « Rallumer l’ATAK » |
| `comspec_icon_repair_ca` | Action ACE « Réparer l’écran » (toolkit) |

Style : pictogramme blanc/teal sur fond transparent, lisible à 64 px dans l’éditeur Zeus.

---

## Portail web (`public/assets/img/`)

| # | Fichier | État | Fonction | Brief |
|---|---|---|---|---|
| 22 | `atak-eagle-logo.png` | **Brouillon IA livré** | Écran chargement Tacmap (`views/atak.php`) | Emblème aigle géométrique, teal + or |
| 23 | `atak-link-lost-icon.png` | **Brouillon IA livré** | Alerte liaison (`atak-roleplay-effects.js`) | Antenne/chaîne brisée, badge amber |
| 24 | `atak-signal-icons.png` | Placeholder dev | Sprite 5 niveaux qualité liaison | Bande 320×64 : excellent → aucun |
| 25 | `atak-jammer-badge.png` | Placeholder dev | Badge « Brouillage détecté » fiche opérateur | 64×64, picto onde barrée |
| 26 | `atak-device-damaged.png` | Placeholder dev | Statut appareil endommagé TOC | 128×128, tablette fissurée |
| 27 | `atak-favicon.png` + `atak-favicon-180.png` | Placeholder dev | Favicon / PWA | Déclinaison simplifiée du logo #22 |

---

## Placeholders : comment les reconnaître

Les **placeholders PowerShell** (générés en dev) ont :

- un fond **aplati une couleur unie** ;
- un **mot en majuscules** en coin (« CRACKED », « BAT », « JAM »…) ;
- des dimensions exactes mais **aucun soin graphique**.

Les **brouillons IA** (ChatGPT / Cursor GenerateImage) :

- ont une **vraie composition** (logo, fissures, CRT…) ;
- peuvent nécessiter **retouches Photoshop** (alpha, taille exacte, tile #13) ;
- sont déjà copiés aux emplacements cibles pour les fichiers marqués « Brouillon IA livré » ci-dessus.

---

## Workflow de remplacement

```text
1. Photoshop / Figma → export PNG (sRGB, dimensions du tableau)
2. Mod Arma : TexView 2 → .paa dans connect/img/... (pack addon)
3. Web : PNG direct dans public/assets/img/
4. Tester in-game : hub (K), dommages ACE, zone Zeus, déconnexion roleplay
5. Tester web : chargement /atak, alerte liaison simulée admin
```

### Checklist avant release

- [ ] Overlays #11–15 en `.paa` avec alpha propre
- [ ] Logo #22 sans fond blanc (transparence)
- [ ] Barres signal/batterie : 4 + 5 états cohérents
- [ ] Favicon #27 décliné 32 px et 180 px
- [ ] Aucun asset copié depuis cTab / BCE / KAM

---

## Génération IA — limites et suite

**Oui, on peut générer par chat** (comme les 4 visuels déjà produits) pour accélérer la DA. Limites :

- Cohérence pixel-perfect entre 27 fichiers → **retouche manuelle** ou kit Figma recommandé ;
- Textures **tileables** (#13) et **sprites** (#24) demandent souvent une passe Photoshop ;
- Les `.paa` restent à convertir côté moddeur.

**Prochaine batch IA suggérée** (si tu veux) : #12 off, #13 static tile, #15 low signal, sprite #24, badges #25–26.

---

## Fichiers liés

| Fichier | Contenu |
|---|---|
| `connect/img/overlays/README.md` | Rappel conversion TexView |
| `public/assets/img/ATAK-ASSETS-README.md` | Index court assets web |
| `docs/technique/atak-roleplay-simulation.md` | Mécaniques roleplay (réseau, zones) |

---

*Dernière mise à jour : implémentation plan roleplay ATAK — placeholders + 4 brouillons IA.*
