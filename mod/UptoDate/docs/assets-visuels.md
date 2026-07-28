# Assets visuels du mod

Guide graphistes — textures, overlays, icônes. **100 % originaux COMSPEC** pour les nouveautés roleplay (ne pas recopier cTab GPL).

Documentation détaillée DA + placeholders : [`docs/design/atak-assets-roleplay.md`](../../../../docs/design/atak-assets-roleplay.md) (dépôt racine).

---

## Deux emplacements

| Dossier | Usage |
|---|---|
| `Sources/.../connect/img/device/` | Cadre téléphone/tablette, OSD batterie/signal |
| `Sources/.../connect/img/overlays/` | Roleplay : écran cassé, NO SIGNAL, icônes Zeus |
| `public/assets/img/` (portail) | Logo chargement Tacmap, alertes liaison web |

---

## Conversion Arma

1. Exporter **PNG** (sRGB, alpha si besoin)
2. **TexView 2** (Bohemia) → `.paa`
3. Suffixe **`_ca`** si canal alpha (overlays)
4. Rebuild **connect.pbo**

Dimensions et briefs : voir tableau dans `docs/design/atak-assets-roleplay.md`.

---

## État livraison (1.3.0)

| Asset | État |
|---|---|
| Logo aigle Tacmap | Brouillon IA — utilisable, retouche possible |
| Icône liaison perdue (web) | Brouillon IA |
| Overlay écran fissuré | Brouillon IA |
| Overlay NO SIGNAL | Brouillon IA |
| Écran éteint, static, low signal | Placeholder dev |
| Barres signal / batterie (états multiples) | Placeholder dev |
| Icônes Zeus SSE (futur) | Non commencé |

Les placeholders dev sont des **rectangles colorés + texte** : OK pour tester le code, **à remplacer** avant release publique.

---

## Palette recommandée

| Usage | Couleur |
|---|---|
| Fond terminal / HUD | `#0a1628` |
| Accent liaison | `#33d9a5` |
| Alerte | `#ff8a4a` |
| Accent secondaire | `#e8b84a` |

---

## SSE (1.4.x)

| Asset | Usage | Dimensions cibles | État |
|---|---|---|---|
| Cadre laptop rugged | Fond terminal « Renseignement interpersonnel » | Zone écran utile 1024×768 | À produire |
| Cadre mugshot | Overlay capture photo visage (guides yeux / épaules) | 512×640 portrait | À produire |
| Icône enregistrer personne | Hub ACE / barre apps Athena | 128×128 PNG → `.paa` `_ca` | À produire |
| Icône saisie / preuve | Liste saisies site | 64×64 | À produire (1.4.1) |
| Icône site exploité | Dossier site | 128×128 | À produire (1.4.1) |
| Icône liste surveillance | Watchlist / alerte match | 64×64 | À produire (1.4.2) |
| Badge TOC « Personne identifiée » | Pastille liste Athena | 24×24 + variante alerte | À produire |
| Overlay biométrie sim | Barre progression empreinte / iris | 512×64 | Placeholder OK |

Palette SSE (cohérente avec le terminal) : fond `#0a1628`, accent liaison `#33d9a5`, alerte watchlist `#ff8a4a`, accent secondaire `#e8b84a`.

Emplacement sources : `Sources/.../connect/img/sse/` (à créer au premier asset). Conversion PNG → `.paa` via TexView 2.

Voir [terminal-sse-renseignement.md](terminal-sse-renseignement.md) et [contrat-api-sse.md](contrat-api-sse.md).

---

## Voir aussi

- [Réalisme liaison](realisme-liaison-atak.md) — quand les overlays s’affichent
- `connect/img/overlays/README.md` — rappel conversion
