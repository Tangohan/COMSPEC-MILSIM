# Maquettes `.dc` — back-office ATHENA

## Contenu

| Fichier | Rôle |
|---|---|
| `BackOffice.dc.html` | Maquette du back-office communauté ATHENA : 1 coquille générique + 32 jeux de données (`PAGES`). |
| `support.js` | Runtime qui interprète le format `.dc` (React sous le capot). **Documentaire uniquement.** |

## Ce que ce format n'est pas

`BackOffice.dc.html` **n'est pas une page HTML**. C'est un template déclaratif : il contient `{{ expression }}`, `<sc-for>`, `<sc-if>`, `onClick="{{ handler }}"`, `style-hover="…"`, `<helmet>`, `hint-placeholder-*`, et une `class Component extends DCLogic { renderVals() }` qui calcule toutes les valeurs affichées.

Le déposer dans `views/` produit une page blanche. Il se **transpile** vers PHP.

## Comment l'ouvrir pour la regarder

Servir le dossier en HTTP (les deux fichiers doivent être côte à côte, `support.js` est chargé en relatif) :

```bash
php -S 127.0.0.1:8999 -t docs/frontend/dc
# puis ouvrir http://127.0.0.1:8999/BackOffice.dc.html
```

Le rail latéral est cliquable : c'est ainsi qu'on parcourt les 32 pages.

## Comment l'intégrer

Un seul document fait autorité : **`docs/prompts/cursor-backoffice-dc-integration-fr.md`**.
Il contient la carte des lignes, la table de transpilation `.dc` → PHP, la correspondance des 32 pages vers les routes `/back-office/*` réelles, la procédure par lots et la definition of done.

Contraintes de fidélité résumées, auto-appliquées par Cursor : `.cursor/rules/backoffice-dc-fidelite.mdc`.

## Écarts

Toute donnée exigée par la maquette et absente du backend se consigne dans `ECARTS-MAQUETTE.md` (créé au premier écart) et s'affiche `—` / `N/D`. Jamais de valeur inventée.
