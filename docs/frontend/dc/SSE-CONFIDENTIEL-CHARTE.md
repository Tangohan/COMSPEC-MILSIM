# Charte visuelle — Bureau SSE

Apparence unique du portail **Renseignement interpersonnel (SSE)**, inspirée du
**LMS Effectifs** et du **tableau de bord Athena** : fond dense type bureau,
typographie Inter, accents slate et émeraude, panneaux arrondis.

## Positionnement

| Apparence | Usage |
|---|---|
| **Bureau SSE** (`bureau`) | Unique. Espace de travail dense, rail latéral lisible, CTA émeraude. |

Les anciennes apparences (Console Athena, SSE Confidentiel, Registre classifié)
sont retirées ; un cookie historique est basculé automatiquement vers `bureau`.

## Palette

| Rôle | Jeton | Valeur |
|---|---|---|
| Fond page | `--bg` | `#07090b` |
| Surface panneau | `--panel` / `--surface` | `#141a21` / `#0f1419` |
| Surface élevée | `--surface-3` | `#1a222c` |
| Bordure | `--line` / `--border` | `rgba(242, 244, 243, 0.1)` |
| Accent neutre | `--accent` | `#94a3b8` |
| Accent action | `--green` | `#059669` |
| Accent clair | `--green-bright` | `#34d399` |
| Succès / prêt | `--ok` / `--success` | `#059669` |
| Avertissement | `--amber` | `#f59e0b` |
| Alerte / classification | `--classified` / `--critical` | `#b91c1c` / `#dc2626` |
| Texte fort | `--text-strong` | `#f2f4f3` |
| Texte courant | `--text` | `rgba(242, 244, 243, 0.72)` |
| Texte discret | `--muted` | `rgba(242, 244, 243, 0.45)` |

Typographie : **Inter** (même famille que le LMS Effectifs et le tableau de bord).

## Composants obligatoires

1. **Bandeau classification** — rouge plein, libellé de diffusion restreinte.
2. **Rail latéral** — session, arborescence dossiers/sous-dossiers, création, historique.
3. **Registre** — cartes ou table dense, badges classification, contenu (pers. / notes / pièces).
4. **Carte tactique (Tacmap)** — capture d’écran versée comme preuve du dossier.

## Règles UI (humains)

- Pas de jargon technique (API, JSON, slug) dans les libellés.
- Statuts et classifications en français métier.
- Toute consultation reste journalisée ; le marquage diffusion le rappelle.

## Fichiers

- CSS portail : `public/assets/css/sse_portal.css` (`body.sse-theme-bureau`)
- CSS workspace : `public/assets/css/sse_workspace.css`
- Sas : `views/atak/sse/gate.php`
- Engagement : `views/atak/sse/confidentiality.php`
- Coque : `views/atak/sse/_layout.php`
- Rail : `views/atak/sse/partials/sse_rail.php`
- Tokens Case File (base) : `docs/frontend/dc/SSE-CASE-FILE-TOKENS.md`
