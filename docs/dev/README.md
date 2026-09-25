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

- [SPOTREP #00003](SPOTREP-00003.md) — 1er septembre 2026 — dossier de mission, tablette IceMan, bureau
- [TECHREP #00003](TECHREP-00003.md) — 1er septembre 2026 — pack 1.4.97 et outils (sans jargon d’atelier)
- [SPOTREP #00002](SPOTREP-00002.md) — 24 août 2026 — relief, rôles, terminaux, comptes rendus
- [TECHREP #00002](TECHREP-00002.md) — 24 août 2026 — outils (sans jargon d’atelier)
- [SPOTREP #00001](SPOTREP-00001.md) — 24 août 2026 — vague 2026.08c
- [TECHREP #00001](TECHREP-00001.md) — 24 août 2026 — archive d’atelier (PR, noms internes)

À chaque mise à jour visible : ajouter une entrée au catalogue (règle Cursor `spotrep-a-chaque-update`) et, pour un SPOTREP / TECHREP, un fichier ici.

## Changelogs Steam (copier-coller Workshop)

- [Overwatch 1.6.12 — 22/09/2026](STEAM-CHANGELOG-2026-09-22-overwatch-1.6.12.md)
- [Overwatch 1.6.9 — 20/09/2026](STEAM-CHANGELOG-2026-09-20-overwatch-1.6.9.md)
- [Overwatch 1.6.8 — 20/09/2026](STEAM-CHANGELOG-2026-09-20-overwatch-1.6.8.md)
- [Overwatch 1.6.7 — 20/09/2026](STEAM-CHANGELOG-2026-09-20-overwatch-1.6.7.md)
- [Overwatch 1.6.3 — 20/09/2026](STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md)
- [Overwatch 1.5.78 — 15/09/2026](STEAM-CHANGELOG-2026-09-15-overwatch-1.5.78.md)
- [Overwatch 1.5.77 — 14/09/2026](STEAM-CHANGELOG-2026-09-14-overwatch-1.5.77.md)
- [SSE 0.7.21 — 14/09/2026](STEAM-CHANGELOG-2026-09-14-sse-0.7.21.md)
- [Overwatch 1.5.76 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.76.md)
- [Overwatch 1.5.75 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.75.md)
- [Overwatch 1.5.74 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.74.md)
- [Overwatch 1.5.73 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.73.md)
- [Overwatch 1.5.72 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.72.md)
- [Overwatch 1.5.71 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.71.md)
- [Overwatch 1.5.70 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.70.md)
- [Overwatch 1.5.69 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.69.md)
- [Overwatch 1.5.68 — 13/09/2026](STEAM-CHANGELOG-2026-09-13-overwatch-1.5.68.md)
- [Overwatch 1.5.36 — 11/09/2026](STEAM-CHANGELOG-2026-09-11-overwatch-1.5.36.md)
