# Structure du dépôt

Arborescence indicative ; certains fichiers peuvent évoluer selon les branches.

```
COMSPEC-MILSIM/
├── app/
│   ├── Config/           # Fichiers de configuration fusionnés au bootstrap
│   ├── Controllers/      # Web, Api, Admin, Auth, Courrier…
│   ├── Core/             # Application, Router, Request, Response, Session, Container…
│   ├── Middleware/
│   ├── Repositories/     # Accès données par agrégat
│   ├── Services/         # Logique métier
│   └── Support/          # Helpers, navigation, vues partagées côté code
├── bootstrap/            # env, autoload, app, scripts de migration ciblés
├── config/               # Config navigation, email, presets métier…
├── docs/                 # Documentation (ce dossier)
├── migrations/           # Schémas et scripts SQL versionnés
├── public/               # Point d’entrée web et assets publics
│   ├── index.php
│   └── assets/
├── routes/
│   └── web.php           # Définition des routes
├── storage/              # Logs, fichiers générés, brouillons courrier…
├── views/                # Templates PHP par zone fonctionnelle
├── .env.example          # Modèle de variables d’environnement
├── run-migrations.php    # Exécution des migrations (selon usage projet)
└── tailwind.config.js    # Build CSS utilitaire
```

## Rôles des dossiers clés

| Chemin | Usage |
|--------|--------|
| `app/Core` | Noyau HTTP minimal : requête, réponse, routeur, conteneur d’injection simple. |
| `app/Controllers/Web` | Pages HTML membres (tableau de bord, forum web, documents…). |
| `app/Controllers/Api` | Endpoints JSON et uploads API. |
| `app/Controllers/Admin` | Back-office système et par organisation. |
| `app/Controllers/Courrier` | Module courrier officiel (éditeur, workflow, PDF…). |
| `views/` | Rendu ; souvent associé à un layout (`views/layout/`). |
| `migrations/` | Évolution du schéma relationnel et données de référence. |
| `public/` | Seul répertoire à exposer en production vers Internet. |

## Dépendances externes notables

- **Composer** : bibliothèques PHP (voir `composer.json` à la racine si présent).
- **Node** : build des styles (Tailwind) via `package.json` / `tailwind.config.js`.

## Voir aussi

- [Configuration et déploiement](configuration-et-deploiement.md)
