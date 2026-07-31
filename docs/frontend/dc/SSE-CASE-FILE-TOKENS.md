# Maquette « SSE Case File » — jetons de conception

Extraits de `SSE-Case-File.dc.html` (composant `.dc`, interprété par `support.js` — ce
n'est pas une page HTML autonome, voir `README.md` de ce dossier).

Ce document sert à aligner `public/assets/css/sse_portal.css` sur la maquette sans avoir à
relire 95 ko de balisage. **Le portail actuel ne suit pas cette charte** : il utilise un
vert sauge (`#8faa83`), Arial Narrow, et des tailles de 0.7–0.85 rem. La maquette est
nettement plus dense et plus « station de travail ».

## Palette

| Rôle | Valeur | Occurrences |
|---|---|---|
| Fond page | `#07090b` | — |
| Surface panneau | `#0a0e10` | 14 |
| Surface alternée | `#0d1114` | 25 |
| Surface élevée | `#14191c` | 61 |
| Bordure | `#1b2227` | 51 |
| Survol de ligne | `#111a1d` | — |
| **Accent principal** | `#12d18e` | 61 |
| Accent clair | `#3ddc9a` | 31 |
| Avertissement | `#e0a233` | 30 |
| Alerte | `#ff6b5e` | 28 |
| Information | `#6bb2f0` | 12 |
| Texte fort | `#e8eef1` | 5 |
| Texte courant | `#d6dee2` / `#a9b6bc` | 9 / 38 |
| Texte secondaire | `#93a1a8` | 25 |
| Libellé discret | `#5b686e` / `#4a565b` | 35 / 41 |
| Bandeau de classification | `#8f1d1d` | — |

Trois couleurs sémantiques distinctes (`#e0a233`, `#ff6b5e`, `#6bb2f0`) là où le portail
n'en a que deux. Le bleu information manque côté portail.

## Typographie

- **Archivo** en `font-stretch: 75%` (condensé) pour le texte, **JetBrains Mono** pour
  toute valeur technique (références, scores, horodatages).
- Échelle très basse : `7.5px`, `8.5px`, `9px`, `9.5px`, `10px`, `10.5px`, `11px`, `13px`.
  La taille dominante est **9.5px**.
- Graisses lourdes presque partout : `700`, `800`, `900`.
- Interlettrage large sur les libellés : `.18em` et `.2em` dominants, jusqu'à `.34em` sur
  le bandeau de classification.

C'est la signature visuelle : **petit, gras, très espacé**. Le portail actuel fait
l'inverse (plus gros, moins gras, peu espacé).

## Animations

| Nom | Effet |
|---|---|
| `sRise` | Apparition, translation verticale 7 px, `.34s` |
| `sBar` | Jauge, `scaleX` depuis la gauche, `.75s` |
| `sPulse` | Point d'état clignotant, `2s` en boucle |
| `sScan` | **Ligne de balayage biométrique**, `4.5s` linéaire en boucle |

`.s-bio` + `.s-bio-scan` reproduisent le balayage d'un lecteur : un dégradé vert de 22 %
de hauteur qui descend en boucle. C'est l'élément qui donne vie au bloc biométrique.

`.s-ph` est un fond hachuré à 135° servant de vignette manquante — utile pour les portraits
absents, là où le portail affiche un tiret.

## Structure de la page

```text
Bandeau de classification (sticky, rouge)
Bandeau d'en-tête (sticky) — sigle SSE, référence de dossier, unité, méta en grille
Onglets
Corps — panneaux à bordure fine, tableaux denses, blocs biométriques
```

Les méta d'en-tête sont une grille de cellules séparées par un filet de 1 px
(`gap: 1px` sur fond `#1b2227`), avec libellé minuscule au-dessus et valeur en mono
en dessous. Ce motif est réutilisable pour l'en-tête de fiche personne et de site.

## Écart avec le portail actuel

| Point | Portail | Maquette |
|---|---|---|
| Accent | `#8faa83` sauge | `#12d18e` vert vif |
| Police | Arial Narrow | Archivo 75 % + JetBrains Mono |
| Densité | ~0.8 rem | ~9.5 px |
| Sémantique | 2 couleurs | 4 couleurs |
| Mouvement | aucun | 4 animations, dont le balayage |
| Vignette absente | tiret | fond hachuré |

L'alignement n'est pas un simple remplacement de variables : la densité change la hauteur
de toutes les lignes. À traiter comme un lot dédié, pas comme une retouche.
