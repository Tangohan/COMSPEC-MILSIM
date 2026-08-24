# Journal développeur — style Bohemia

Deux formats, comme le [Dev Hub Arma 3](https://dev.arma3.com/) :

| Type | Public | Contenu |
| --- | --- | --- |
| **SPOTREP** | Opérateurs, TOC, Zeus | Ce qui change en session et au poste de commandement. Pas de jargon d’atelier. |
| **TECHREP** | Responsables de pack, intégrateurs | Même esprit, orienté outils et pack. Les textes **publiés sur le site** restent en langage métier. |
| **UPDATE** | Tout le monde | Un bulletin par mise à jour (PR Git, ou commit livré s’il n’y a pas encore de PR). |

Les bulletins publics vivent dans `app/Support/DevDispatchCatalog.php` et s’affichent sur `/nouveautes` (section Journal) et `/nouveautes/{spotrep\|techrep\|update}/{numéro}`.

Numérotation à cinq chiffres, comme Bohemia (`SPOTREP #00002`, `UPDATE #00198`).

## Vague actuelle

- [SPOTREP #00002](SPOTREP-00002.md) — 24 août 2026 — relief, rôles, terminaux, comptes rendus
- [TECHREP #00002](TECHREP-00002.md) — 24 août 2026 — outils (sans jargon d’atelier)
- [SPOTREP #00001](SPOTREP-00001.md) — 24 août 2026 — vague 2026.08c
- [TECHREP #00001](TECHREP-00001.md) — 24 août 2026 — archive d’atelier (PR, noms internes)

À chaque mise à jour visible : ajouter une entrée au catalogue (règle Cursor `spotrep-a-chaque-update`) et, pour un SPOTREP / TECHREP, un fichier ici.
