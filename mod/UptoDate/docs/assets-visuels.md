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

## SSE (prévu 1.4.x)

Assets à prévoir :

- Cadre laptop rugged (1024×768 zone écran)
- Icônes : enregistrer personne, saisie, site exploité, liste surveillance
- Badge TOC « Personne identifiée »

Voir [terminal-sse-renseignement.md](terminal-sse-renseignement.md).

---

## Voir aussi

- [Réalisme liaison](realisme-liaison-atak.md) — quand les overlays s’affichent
- `connect/img/overlays/README.md` — rappel conversion
