# Architecture

Préfixe PBO : `z\comspec_sse\addons\...`

Fonctions : `comspec_sse_fnc_*`  
Variables : `comspec_sse_*`

```
@COMSPEC_SSE
├── addons
│   ├── main          — CfgPatches racine, catégorie Zeus
│   ├── core          — data model, UID, set/get, settings CBA, server ops
│   ├── generator     — generateData / Person / Phone / Site / Cluster
│   ├── interaction   — ACE menus, journal, progressBar
│   ├── evidence      — CfgWeapons items
│   ├── zeus          — modules + dialogue génération
│   ├── eden          — attributs 3DEN
│   ├── ui            — dialogue résultat terrain
│   ├── network       — submitRecord / queue offline / adapter Overwatch
│   ├── digital       — exploitation téléphone / PC
│   ├── biometrics    — SEEK II / empreintes
│   └── intel         — moteur intel V0.6 (niveaux, triage, pivot, fusion)
├── docs
├── tools
├── keys
├── mod.cpp
└── README.md
```

## Localité

- Source de vérité des données critiques : serveur (`setVariable ..., true` + `requestServerOp`)
- Journal joueur : local
- File transmission : locale + flush périodique

## Lazy generation

Un objet searchable stocke `seed` + `profile` sans remplir digitalDevices complets.  
Au premier `inspect` / `ensureGenerated`, le contenu est généré puis mis en cache (`lazyReady=true`).
