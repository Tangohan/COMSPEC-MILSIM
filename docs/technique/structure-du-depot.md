# Structure du dépôt

Arborescence de **COMSPEC-MILSIM** (portail Athena + packs Arma). Les listes de fichiers sont indicatives : le dépôt évolue.

Trois blocs à retenir :

1. **Portail PHP** — `public/` (entrée web), `app/`, `views/`, `routes/`
2. **Packs jeu** — `mod/UptoDate/` (Overwatch) et `mod/@COMSPEC_SSE/`
3. **Exploitation** — `docs/`, `migrations/`, `bootstrap/`, `scripts/`, `.github/`

Le **document root** de production pointe uniquement vers `public/`.

---

## Vue d’ensemble

```
COMSPEC-MILSIM/
├── public/                 # Seul dossier exposé sur Internet
├── app/                    # PHP applicatif (contrôleurs, services, dépôts)
├── views/                  # Templates PHP
├── routes/web.php          # Toutes les routes HTTP
├── bootstrap/              # Démarrage + scripts de migration ciblés
├── config/                 # Navigation, e-mail, presets métier
├── lang/                   # Textes FR / EN (site, auth, erreurs…)
├── storage/                # Logs, version portail, fichiers générés
├── migrations/             # SQL versionné
├── tests/                  # PHPUnit (Unit, Contract, Courrier)
├── scripts/                # Cron, install, smoke tests
├── deploy/                 # Sparse-checkout VPS
├── .github/workflows/      # CI + déploiement VPS
├── docs/                   # Documentation atelier
├── mod/                    # Packs Arma (Overwatch + SSE)
├── server/                 # Service carte ATAK (optionnel)
├── composer.json
├── run-migrations.php
├── install.php
└── .env.example
```

Hors socle (ne pas traiter comme du code métier) : `vendor/`, `tcpdf/`, `phpqrcode/`, archives `mod/Ancienne version de tout/`, `mod/_local_pre_pr150_backup/`.

---

## Portail Athena (`app/`)

```
app/
├── Config/                 # Fusionné au bootstrap (app, database, auth…)
├── Core/                   # Application, Router, Request, Response, Session, Database, Container
├── Middleware/             # Auth, RBAC, SSE, ATAK, CSRF, rate-limit, en-têtes
├── Controllers/
│   ├── Web/                # Pages membres (tableau de bord, ATAK, SSE, formations, effectifs…)
│   ├── Api/                # JSON : ATAK, SSE, forum, webhooks, santé
│   ├── Admin/
│   │   ├── Organization/   # Back-office communauté (intégrations, effectifs, alertes…)
│   │   └── System/         # Administration du site (cron, stockage, maintenance…)
│   ├── Auth/
│   └── Courrier/           # Courrier officiel (éditeur, PDF, signatures)
├── Services/               # Logique métier par domaine
│   ├── Tactical/           # Carte ATAK, motion, rapports, icônes
│   ├── Sse/                # Dossiers, fiches, transmissions, biométrie
│   ├── Training/           # Catalogue LMS, studio, inscriptions
│   ├── Personnel/          # Dossier, ancienneté, grades
│   ├── Recruitment/        # Candidatures, acceptation, identité
│   ├── Cron/               # Planificateur + jobs (escalade, digestifs…)
│   ├── Integrations/       # Relais Discord, notifications pack
│   ├── Courrier/
│   ├── Community/
│   ├── Audit/
│   └── …                   # Billing, Forum, Moderation, Platform, Replay…
├── Repositories/           # Accès SQL par agrégat
└── Support/                # Catalogues, helpers, journaux publics (DevDispatch, Changelog)
```

Flux HTTP : `public/index.php` → `bootstrap/` → `Application` → `routes/web.php` → contrôleur → service / dépôt → `views/`.

---

## Vues et assets

```
views/
├── layout/                 # Coques (marketing, hub, effectifs…)
├── partials/               # En-tête, sidebars, bandeau formations, UI
├── admin/                  # Back-office (system, organization, training, ATAK…)
├── atak/                   # Poste de commandement + bureau SSE
├── training/               # Catalogue, leçons, studio apprenant
├── personnel/              # Fiches, annuaire
├── forum/  courrier/  enlistment/  community/
├── site/                   # Pages publiques (à propos, nouveautés)
├── emails/                 # Courriels transactionnels
└── auth/  legal/  documentation/

public/
├── index.php               # Front controller
├── assets/
│   ├── css/                # Portail, ATAK, LMS, back-office
│   ├── js/                 # Carte ATAK, formations, forum…
│   ├── markers/            # Symboles carte
│   ├── img/  sounds/  video/
│   └── vendor/             # Leaflet, milsymbol…
└── uploads/                # Médias runtime (hors Git en prod)
```

---

## Packs Arma (`mod/`)

```
mod/
├── README.md               # Quel dossier builder
├── UptoDate/               # Overwatch — dossier de travail
│   ├── build_mod.bat       # Native AOT + AddonBuilder → PBO
│   ├── workshop-pack.ps1   # Pack Workshop propre
│   ├── COMSPECExtension/   # Extension C# (DLL Native AOT)
│   ├── Sources/comspec-overwatch-addons/
│   │   ├── connect/        # Liaison jeu ↔ Athena (SQF)
│   │   ├── atak_athena/    # Téléphone / tablette Athena
│   │   ├── main/
│   │   ├── sse_ace/
│   │   └── mavik_compat/
│   └── @COMSPECOverwatch/  # Sortie : PBO + DLL
└── @COMSPEC_SSE/           # SSE — sources + PBO
    ├── build_mod.bat / build_pbo.bat
    ├── addons/
    │   ├── main  core  network  intel  ui
    │   ├── biometrics  digital  generator
    │   ├── interaction  zeus  eden  evidence
    │   └── compat_bii  compat_ace
    └── docs/               # Installation, publication Workshop
```

Build Overwatch : `mod/UptoDate/build_mod.bat`.  
Build SSE : `mod/@COMSPEC_SSE/build_mod.bat`.

---

## Documentation, tests, exploitation

```
docs/
├── technique/              # Architecture, dépôt, déploiement, sécurité
├── utilisateur/            # Guides métier (sans jargon d’atelier)
├── sse/                    # Dossiers, fiches, transmissions
├── bugs/                   # Notes de correctifs (YYYY-MM-DD-slug)
├── dev/                    # SPOTREP / TECHREP
└── frontend/

tests/
├── Unit/
├── Contract/Api/
└── Courrier/

scripts/
├── cron-run.php
├── install-system-cron.sh / .ps1
└── post-deploy-smoke-tests.php

.github/workflows/
├── ci.yml
└── deploy-vps.yml          # Push sur main → git pull VPS
```

Traductions portail : `lang/fr/`, `lang/en/` (`site.php`, `auth.php`, `errors.php`…).

---

## Rôles des dossiers clés

| Chemin | Usage |
|--------|--------|
| `public/` | Seul répertoire à exposer. |
| `app/Core` | Noyau HTTP et base de données. |
| `app/Controllers/Web` | Pages HTML membres. |
| `app/Controllers/Api` | JSON (carte, SSE, forum, paiements). |
| `app/Controllers/Admin` | Back-office communauté et administration du site. |
| `app/Services` | Métier ; pas de SQL brut hors dépôts. |
| `app/Repositories` | Requêtes paramétrées. |
| `views/` | HTML ; libellés humains, pas de jargon interne. |
| `mod/UptoDate/` | Seul chemin Overwatch à compiler. |
| `mod/@COMSPEC_SSE/` | Sources et PBO SSE. |
| `bootstrap/` + `migrations/` | Schéma et conversions sûres. |
| `storage/` | Runtime (logs, `app_version.json`) ; uploads hors Git en prod. |
| `docs/bugs/` | Une note par bug identifié. |

---

## Voir aussi

- [Architecture applicative](architecture.md)
- [Configuration et déploiement](configuration-et-deploiement.md)
- [Modules fonctionnels](modules-fonctionnels.md)
- [Mod Overwatch](overwatch-mod/architecture.md)
- [README packs](../../mod/README.md)
