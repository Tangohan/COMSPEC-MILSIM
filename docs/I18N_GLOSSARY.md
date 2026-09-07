# Athena UI terminology (FR → EN)

This glossary is normative for interface copy. Stored values, API identifiers, callsigns and user-authored content are never translated.

| French source | English UI term | Usage note |
|---|---|---|
| Athena, COMSPEC, ATAK, C2, SSE, BDA, BII, BFT, SITAC, SEEK II | unchanged | Product names and operational acronyms. |
| indicatif | callsign | Never alter the callsign value itself. |
| unité | unit | Organisational or tactical unit. |
| communauté | community | Tenant-facing copy; keep technical `tenant` identifiers unchanged. |
| compte rendu | report | Use “after-action report” only for AAR. |
| ordre | order | Operational order; preserve order type codes. |
| situation tactique | tactical situation | “Tactical picture” is acceptable for map/COP context. |
| renseignement | intelligence / intel | “Intelligence” in formal headings; “Intel” in compact operational UI. |
| prise en compte | acknowledged | For an operator acknowledging an alert/order. |
| effectifs | personnel | “Strength” only for a numeric operational strength. |
| formation | training | “Course” for an individual LMS course. |
| habilitation | authorisation | “Clearance” only when it denotes a security clearance. |
| pièce jointe | attachment | Files attached to messages, reports or forms. |
| brouillon | draft | Visible status; stored status remains `draft`. |
| en attente | pending | Visible status; do not translate enum values. |
| enregistrer | save | Never “register” for a normal form action. |
| annuler | cancel | Action button. |
| retour | back | Prefer a destination-specific label when context permits. |
| supprimer | delete | “Remove” only when the underlying object is not deleted. |

## Style

Use concise sentence-case UI English, active verbs, and British spelling for organisation-facing prose. Keep protocol names, role slugs, database enum values, filenames and API field names unchanged. Translate labels in the presentation layer rather than changing persisted military or user data.
