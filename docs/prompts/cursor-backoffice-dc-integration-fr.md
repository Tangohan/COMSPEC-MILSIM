# Brief Cursor — intégrer la maquette `BackOffice.dc.html` dans le back-office ATHENA

**Statut** : brief normatif. À lire en entier avant d'écrire une ligne de code.
**Fichiers de référence (dans le dépôt)** :
- `docs/frontend/dc/BackOffice.dc.html` — la maquette (1 707 lignes)
- `docs/frontend/dc/support.js` — le runtime qui l'interprète (à titre documentaire, **ne jamais l'embarquer dans l'application**)

---

## 0. Pourquoi les tentatives précédentes ont échoué

Trois malentendus, à corriger avant tout.

### 0.1 `BackOffice.dc.html` n'est pas une page HTML

C'est un **composant `.dc`** : un template déclaratif interprété au runtime par `support.js` (qui monte du React sous le capot). Le fichier contient des syntaxes qui n'existent ni en HTML natif ni en PHP :

| Syntaxe présente | Occurrences | Sens |
|---|---|---|
| `{{ expression }}` | ~600 | interpolation d'une valeur calculée par `renderVals()` |
| `<sc-for list="{{ … }}" as="x">` | 25 | boucle |
| `<sc-if value="{{ … }}">` | 11 | condition |
| `onClick="{{ handler }}"` | 7 | handler React, **pas** un attribut HTML |
| `style-hover="…"` | 7 | pseudo-attribut inventé par le runtime, injecté en CSS |
| `<helmet>` | 1 | `<head>` du document |
| `hint-placeholder-count` / `hint-placeholder-val` | 36 | hints de streaming, **purement cosmétiques au chargement** |
| `<x-dc>` | 1 | racine du composant |

Coller ce fichier dans `views/` produit une **page blanche**. Le « copier-coller littéral » demandé ne porte donc pas sur le fichier : il porte sur **la géométrie visuelle**. Voir §5.

### 0.2 La maquette n'est pas 32 pages, c'est 1 coquille + 32 jeux de données

Le template (lignes 44–499) est **générique**. Les 32 « pages » sont des entrées du dictionnaire `PAGES` (lignes 792–1707), chacune de la forme :

```js
PAGES.atakCerts = {
  group:'ATAK', kicker:'ATAK · SÉCURITÉ', title:'Certificats & data packages',
  sub:'…', action:'Générer un certificat', quick:['Expirants','Révoqués','Paquets'],
  kpis:[ ['CERTIFICATS ACTIFS','97','+4','#0b8a5c','88%','signés par CA interne'], … ],
  tableTitle:'Certificats émis', filters:['État','Autorité','Échéance'], minW:'1700px',
  cols:['SÉRIE|m','CALLSIGN|m','MEMBRE','TYPE',…,'ÉTAT|b'],
  rows:[ ['0x4A11','VIPER-03',…], … ]
};
```

**Conséquence directe sur la méthode** : porter la coquille **une seule fois** (un layout + 7 partials + 1 CSS + 1 helper), puis chaque page réelle ne fournit plus qu'un tableau `$page`. Dupliquer du markup page par page est la mauvaise route — c'est ce qui a fait exploser les tentatives précédentes.

### 0.3 Les données de la maquette sont fictives, et le backend existe déjà

`VIPER-03`, `SES-88412`, `0x4A11`, `128 effectifs` : tout est inventé. En face, le dépôt expose déjà **plus de 200 routes `/back-office/*`** avec contrôleurs, repositories et vues.

**Aucun nouveau modèle métier n'est à créer.** Le chantier est un **re-skin branché sur l'existant**, pas une fonctionnalité. Si une page de la maquette semble exiger une donnée qui n'existe pas, appliquer §10 — ne pas improviser une table SQL.

---

## 1. Anatomie de la maquette — carte des lignes

À utiliser comme table des matières pendant le portage. Les numéros renvoient à `docs/frontend/dc/BackOffice.dc.html`.

| Lignes | Bloc | Destination PHP |
|---|---|---|
| 9–43 | `<helmet>` : polices Archivo + JetBrains Mono, reset, keyframes `athRise/athGrow/athBar/athPulse/athSweep`, classes `.ath-card/.ath-row/.ath-side/.ath-btn/.ath-sweep` | `public/assets/css/back-office-athena.css` |
| 46–108 | `<aside>` : rail noir, logo, replieur, groupes de nav avec badges et enfants | restyle de `views/partials/back_office_sidebar.php` |
| 112–138 | barre supérieure : fil d'Ariane, recherche `⌘K`, pastilles d'alerte, bouton d'action | `views/back_office/athena/partials/topbar.php` |
| 140–152 | en-tête de page : kicker, `<h1>`, sous-titre, boutons rapides | `…/partials/page_header.php` |
| 154–168 | `sc-if hasAlerts` : bandeau d'alertes | `…/partials/alerts.php` |
| 169–186 | `sc-if hasKpis` : rangée de tuiles KPI | `…/partials/kpi_row.php` |
| 187–232 | `sc-if isDash` : histogramme 14 jours + mini-RSVP | `…/partials/dash_chart.php` |
| 233–282 | `sc-if isRsvp` | `…/partials/rsvp_panel.php` |
| 283–326 | `sc-if isAtakOp` | `…/partials/atak_operator_panel.php` |
| 327–370 | `sc-if isSetup` : progression + checklist + champs | `…/partials/setup_panel.php` |
| 371–416 | `sc-if isProfils` : sélecteur de profils | `…/partials/permission_profiles.php` |
| 417–443 | `sc-if isSettings` : groupes de réglages à interrupteurs | `…/partials/settings_groups.php` |
| 444–498 | `sc-if hasTable` : titre, compteur, filtre, filtres, export CSV, `<table>` | `…/partials/data_table.php` |
| 508–572 | `nav()` : la structure du menu | **ne pas porter** — la nav réelle existe déjà, voir §3.2 |
| 574–591 | `cell()` + `toneOf()` (lignes 785–791) : typage de cellule `m` / `r` / `b` et tonalités | `app/Support/BackOffice/AthenaTable.php` |
| 792–1707 | `PAGES` : 32 jeux de données fictifs | **référence de forme uniquement**, jamais copiée en dur |

---

## 2. Arborescence cible

À produire exactement, pas d'improvisation de chemins :

```
public/assets/css/back-office-athena.css          # helmet + tous les :hover de style-hover
app/Support/BackOffice/AthenaTable.php            # port de cell() / toneOf() / parsing 'LABEL|m'
views/back_office/athena/partials/
    topbar.php
    page_header.php
    alerts.php
    kpi_row.php
    data_table.php
    dash_chart.php
    rsvp_panel.php
    atak_operator_panel.php
    setup_panel.php
    permission_profiles.php
    settings_groups.php
views/partials/back_office_sidebar.php            # MODIFIÉ, pas remplacé
```

### 2.1 Contrat des partials

Chaque partial lit **une seule variable**, un tableau PHP de même forme que l'entrée `PAGES` correspondante. Exemple normatif pour `data_table.php` :

```php
<?php
/**
 * @var array{
 *   title:string, count:string, minWidth:string,
 *   filters:list<array{label:string,href:string}>,
 *   exportHref:?string,
 *   cols:list<array{label:string,kind:''|'m'|'r'|'b',align:'left'|'right'}>,
 *   rows:list<list<string>>
 * } $table
 */
```

Les valeurs sont **déjà formatées pour l'affichage** par le contrôleur ou un presenter (`'1 h 47'`, `'18,4 Go'`, `'27/07 09:41'`). Un partial ne calcule rien, ne requête rien, ne connaît aucun repository.

---

## 3. Table de transpilation — normative

### 3.1 Constructions `.dc` → PHP

| Maquette | PHP à écrire |
|---|---|
| `{{ pTitle }}` dans du texte | `<?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?>` |
| `{{ a.bg }}` dans un `style="…"` | `<?= htmlspecialchars($alert['bg'], ENT_QUOTES, 'UTF-8') ?>` — la couleur reste **inline**, ne pas la déplacer en classe |
| `<sc-for list="{{ kpis }}" as="k">…</sc-for>` | `<?php foreach ($kpis as $k): ?>…<?php endforeach; ?>` |
| `<sc-if value="{{ hasKpis }}">…</sc-if>` | `<?php if ($kpis !== []): ?>…<?php endif; ?>` |
| `$index` dans une boucle | la clé du `foreach` |
| `hint-placeholder-count` / `hint-placeholder-val` | **supprimer** — hints de streaming, aucun équivalent |
| `onClick="{{ go }}"` sur une nav | vrai `<a href="<?= url('back-office/…') ?>">` |
| `onClick="{{ toggle }}"` sur un interrupteur | `<form method="post">` vers la route POST existante, avec le jeton CSRF selon la convention du projet : `<?= \App\Core\Csrf::field() ?>`, ou un `<input type="hidden" name="_csrf_token" value="…">` alimenté par une variable passée par le contrôleur (motif majoritaire dans `views/admin/organization/`) |
| `onClick="{{ toggleSide }}"` (replier le rail) | le rail actuel a déjà son mécanisme (`public/assets/js/dashboard-rail.js`, `back-office-rail.css`) — **le conserver**, ne pas ajouter un second système |
| `style-hover="border-color:#12d18e; color:#12d18e;"` | une classe dans `back-office-athena.css` : `.ath-btn--rail:hover{border-color:#12d18e;color:#12d18e}` |
| `class="ath-rise"`, `ath-card`, `ath-row`, `ath-btn` | **conserver le nom de classe à l'identique** et définir la règle dans `back-office-athena.css` |
| `<helmet>` | la feuille CSS déclarée via `'backOfficePageCss' => ['back-office-athena.css']` dans le `Response::view(...)` |
| `class Component extends DCLogic` | rien. La logique va dans le contrôleur, le presenter, ou `AthenaTable.php` |

### 3.2 La nav : ne pas la porter depuis la maquette

`nav()` (lignes 508–572) est une nav **fictive**. La vraie vit dans `views/partials/back_office_sidebar.php`, avec sa structure `['label','href','hint','active','badge','badgeTone']` **et son filtrage RBAC** (`$boHrefAllowed`, gating par type de tenant).

Règle : **garder la structure de données et le gating existants ; ne remplacer que le rendu** (géométrie, couleurs, tailles) par celui des lignes 46–108. Une entrée de menu qui disparaît du rail pour un rôle donné doit continuer à disparaître exactement comme aujourd'hui.

### 3.3 Le typage des colonnes

`cols:['SESSION|m','DURÉE|r','RÉSULTAT|b','MEMBRE']` — le suffixe après `|` est le type de cellule. Port exact depuis `cell()` (lignes 574–581) :

| Suffixe | Rendu |
|---|---|
| *(aucun)* | `Archivo`, `font-weight:600`, `color:#20282c`, aligné à gauche |
| `m` | `JetBrains Mono`, `font-weight:500`, `color:#3c474c` |
| `r` | aligné à **droite**, `JetBrains Mono`, `font-weight:700` |
| `b` | badge : couleurs issues de `toneOf(valeur)`, `padding:1px 8px`, `font-weight:800` |

`toneOf()` (lignes 785–791) classe la valeur textuelle en `ok` / `warn` / `bad` / `info` par correspondance sur les listes `W_OK` / `W_WARN` / `W_BAD`. **Porter ces listes de mots telles quelles** dans `AthenaTable::tone()`, y compris les accents et la casse d'origine, puis les étendre aux libellés réellement produits par le backend (`Actif`, `Suspendu`, `Révoqué`, `En attente`…). Une valeur inconnue tombe sur `info` — jamais d'exception, jamais de cellule vide.

---

## 4. Correspondance maquette → backend réel

C'est le cœur du branchement. **Une ligne = une page de la maquette = une route qui existe déjà.**

Les cellules marquées **`à confirmer`** doivent être résolues par lecture du code (`grep -rn "…" routes/web.php app/Controllers`), **pas** par création d'une nouvelle route.

| Clé `PAGES` | Titre maquette | Route existante | Contrôleur (vérifié dans `routes/web.php`) |
|---|---|---|---|
| `dash` | Tableau de bord | `GET /back-office` | `OrganizationDashboardController::index` → vue `admin.organization.dashboard` |
| `wall` | Mur opérationnel | `GET /back-office/tableau-operationnel` | `Web\OperationalBoardController::index` |
| `agenda` | Agenda | `GET /back-office/events` | `CommunityEventsAdminController::index` → `events.php` |
| `members` | Annuaire complet | `GET /back-office/users` | `Web\UserAdminController::index` |
| `recrues` | Candidatures | `GET /back-office/recruitments` | `Admin\AdminRecruitmentsController` |
| `sanctions` | Sanctions & absences | **à confirmer** — côté tenant, candidats : `/back-office/moderation`, `/back-office/content-moderation`. `/admin/system/member-sanctions` est **plateforme**, donc hors périmètre | — |
| `roleplay` | Suivi roleplay | `GET /back-office/roleplay-followup` | `RoleplayFollowupAdminController::index` → `roleplay_followup.php` |
| `orbat` | Structure & effectifs | `GET /back-office/organisation/structure` + `GET /back-office/ressources/effectifs` | `OrganizationDashboardController::structureRecruitmentHub` |
| `doctrine` | Doctrine des fonctions | `GET /back-office/doctrine` | `DoctrineAdminController::index` → `doctrine.php` |
| `attributions` | Attributions métier | `GET /back-office/personnel-job-roles/assignments` | `PersonnelJobRoleAdminController::assignments` |
| `formations` | Formations | **`GET /formation/courses`** (et `/formation`, `/formation/enrollments`, `/formation/publications`) | `TrainingCompetencyController` & co. ⚠️ **Piège : `/back-office/ressources/training*` n'est plus qu'une redirection *legacy*** (`training_lms_redirect_legacy_bo_training_to_formation`). Ne pas re-skinner une redirection. |
| `setup` | Configuration initiale | `GET /back-office/configuration-initiale` | `TenantInitialSetupController::index` → `initial_setup.php` |
| `community` | Identité & options | `GET /back-office/community` | `OrganizationSettingsController::index` |
| `publicPage` | Page d'accueil publique | `GET /back-office/community/presentation` | `OrganizationCommunityController::presentation` |
| `medias` | Médias | `GET /back-office/media` | `CommunityMediaAdminController::index` → `media_index.php` |
| `annonces` | Annonces & alertes | `GET /back-office/alerts` | `TenantAlertsController::index` |
| `onboarding` | Onboarding membres | `GET /back-office/onboarding-members` | `OrganizationCommunityController::onboardingMembers` |
| `usage` | Indicateurs d'usage | `GET /back-office/analytics` | `OrganizationAnalyticsController::index` → `analytics.php` |
| `ops` | Opérations | `GET /back-office/operations-admin` (alias de `/back-office/centre-operations`) | `OrganizationDashboardController::operationsCenter` → `operations_center.php` |
| `rsvp` | RSVP d'une opération | `GET /back-office/events/{id}` | `CommunityEventsAdminController` → `event_show.php` |
| `rsvpHist` | Historique RSVP | `GET /back-office/events/insights` | `CommunityEventsAdminController` → `events_insights.php` |
| `aar` | Comptes rendus | **à confirmer** — chercher côté `tableau-operationnel` (REX / after-action) et `cooperation/missions/{id}/rex` avant de conclure | — |
| `logs` | Journal d'audit | `GET /back-office/audit` | `OrganizationAuditController::index` → `audit.php` |
| `roles` | Matrice des rôles | `GET /back-office/access-management` | `AccessManagementController::index` |
| `rolesTable` | Table des rôles | `GET /back-office/roles` | `RoleAdminController::index` |
| `profils` | Profils de permissions | `GET /back-office/roles/presets` | `RoleAdminController` |
| `integrations` | Intégrations | `GET /back-office/integrations` | `OrganizationIntegrationsController::index` → `integrations.php` |
| `settings` | Paramètres | `GET /back-office/organisation/parametres` | `OrganizationSettingsController::index` / `::update` |
| `atakDevices` | Parc de terminaux | `GET /back-office/atak/operateurs` | `Admin\AdminAtakOperatorsController` |
| `atakSessions` | Sessions & connexions | **à confirmer** — point de départ : `AtakSessionWorkspaceRepository` | — |
| `atakCerts` | Certificats & data packages | **à confirmer** — point de départ : `/back-office/ressources/atak-config`, `AdminAtakConfigController` | — |
| `atakOp` | Fiche opérateur | fiche depuis `GET /back-office/atak/operateurs` | `AdminAtakOperatorsController` |

### 4.1 Règle de branchement

Pour chaque page :

1. Lire le contrôleur **et** la vue actuels de bout en bout.
2. Inventorier les variables déjà passées à la vue (`Response::view('layout.main', [...])`).
3. Construire `$page` **à partir de ces variables**. Si un KPI de la maquette correspond à une donnée déjà calculée, le brancher. Sinon → §10.
4. Ne pas toucher aux requêtes SQL, aux repositories, ni aux services. Le re-skin est **read-only sur la couche données**.
5. Conserver `'isBackOfficeShell' => true` et ajouter `'back-office-athena.css'` à `backOfficePageCss`.

---

## 5. Fidélité : ce qui est intouchable

Le « copier-coller littéral » demandé porte sur ceci, **caractère par caractère** :

- **chaque valeur hexadécimale** : `#f6f8f9`, `#0c1116`, `#12d18e`, `#0b8a5c`, `#e3e8ea`, `#8a938e`, `#c98a12`, `#c72e2e`, `#1e4f80`… Aucune substitution par une couleur Tailwind « équivalente ».
- **chaque dimension en px**, y compris les décimales : `font-size:11.5px`, `font-size:8.5px`, `height:54px`, `width:248px`, `width:62px`, `border:1.5px`, `letter-spacing:0.22em`.
- **les deux polices** : `'Archivo'` (400→900) et `'JetBrains Mono'` (400/500/700). C'est une exception documentée à la règle *ui-theme-portail-unifie* : le back-office ATHENA a sa propre charte, assumée, distincte du portail public.
- **les `style="…"` inline** : ils restent inline. Ne pas les extraire en classes utilitaires, ne pas les convertir en Tailwind. Seuls les `:hover` (ex-`style-hover`) et les `@keyframes` partent dans le CSS.
- **les libellés en français** de la coquille : `Rechercher…`, `Filtrer…`, `Exporter CSV`, `Plier le menu`, `⌘K`, et les intitulés de colonnes en capitales.
- **les SVG inline** : copiés tels quels, `stroke-width` compris.
- **l'ordre et la densité** : `padding:22px`, `gap:16px`, hauteurs de lignes de tableau, `min-width` par page (`1780px`, `1700px`, `1620px`…).

Test décisif : une capture de la page réelle et une capture de la maquette, superposées, doivent coïncider au pixel sur la coquille. Si un écart est visible, c'est un bug, pas un choix.

---

## 6. Interdits

1. **Ne pas** embarquer `support.js`, React, ou quoi que ce soit du runtime `.dc` dans l'application. Le rendu final est du PHP + HTML + CSS, plus un soupçon de JS vanille uniquement là où il en existe déjà.
2. **Ne pas** réécrire la maquette en Tailwind, ni en composants « propres », ni en variables CSS. La géométrie inline est le livrable.
3. **Ne pas** « moderniser », « harmoniser avec le portail », arrondir les angles, adoucir les couleurs, ajouter des ombres. La maquette est carrée (aucun `border-radius` sauf les puces circulaires) : ça reste carré.
4. **Ne pas** laisser une seule valeur de `PAGES` en dur dans une vue. Zéro `VIPER-03`, zéro `SES-88412`, zéro `128`.
5. **Ne pas** créer de route, de table, de migration, de repository. Périmètre : vues, partials, CSS, un helper de présentation, et les tableaux `$page` dans les contrôleurs.
6. **Ne pas** créer un second shell de back-office en parallèle de `layout.main` / `isBackOfficeShell`.
7. **Ne pas** contourner le RBAC : tout `Gate`, `$boHrefAllowed`, middleware ou gating par type de tenant reste en place. Un tableau plus joli ne doit jamais exposer une ligne de plus qu'avant.
8. **Ne pas** faire 32 pages d'un coup. Voir §8.
9. **Ne pas** inventer une donnée absente. Voir §10.

---

## 7. Lot 0 — la fondation (à faire en premier, seul)

Aucune page métier dans ce lot.

1. `public/assets/css/back-office-athena.css` : polices, reset ciblé, les 5 `@keyframes`, les classes `.ath-*`, et tous les `:hover` issus des 7 `style-hover`.
2. `app/Support/BackOffice/AthenaTable.php` :
   - `parseColumns(array $cols): array` — découpe `'DURÉE|r'`
   - `cell(string $value, string $kind): array` — port de `cell()`
   - `tone(string $value): array` — port de `toneOf()` + `W_OK` / `W_WARN` / `W_BAD` / `TONE`
3. Les 11 partials de `views/back_office/athena/partials/`, avec les `@var` documentés du §2.1, alimentés par des tableaux **statiques placés dans la vue de démonstration uniquement** — le temps de valider la fidélité visuelle.
4. Restyle de `views/partials/back_office_sidebar.php` sur la géométrie des lignes 46–108, structure de données et gating inchangés.
5. Contrôle visuel : `/back-office` doit déjà afficher le nouveau rail et la nouvelle barre supérieure, sans régression de navigation.

**Le lot 0 est un commit à lui seul.** Il est validé quand la coquille est fidèle et que rien n'est cassé.

---

## 8. Lots suivants — une page par commit

Ordre imposé (du plus simple au plus dense, pour que les erreurs de méthode se voient tôt) :

1. `logs` → `/back-office/audit` (tableau générique pur, sans bloc spécial : le meilleur test de `data_table.php`)
2. `members` → `/back-office/users`
3. `recrues` → `/back-office/recruitments`
4. `agenda` → `/back-office/events`
5. `usage` → `/back-office/analytics`
6. `dash` → `/back-office` (KPI + alertes + histogramme)
7. `setup`, `community`, `publicPage`, `medias`, `integrations`, `settings`
8. `roles`, `rolesTable`, `profils`, `attributions`, `doctrine`, `orbat`
9. `ops`, `rsvp`, `rsvpHist`, `wall`, `aar`, `formations`, `onboarding`, `annonces`, `roleplay`, `sanctions`
10. `atakDevices`, `atakSessions`, `atakCerts`, `atakOp` (en dernier : ce sont les `à confirmer` du §4)

Message de commit : `feat(back-office): page <clé> sur la charte ATHENA, branchée sur <route>`.

**Ne pas enchaîner sur la page suivante avant que la précédente coche tout le §9.**

---

## 9. Definition of done — par page

- [ ] La page réelle est visuellement superposable à la maquette (coquille identique au pixel).
- [ ] Chaque valeur affichée vient d'un repository / service **déjà existant**.
- [ ] Zéro chaîne recopiée de `PAGES`. Vérification : `grep -rn "VIPER-03\|SES-884\|0x4A1\|FER ROUGE\|KILO-11" views/ app/` ne renvoie **rien**.
- [ ] Zéro syntaxe `.dc` résiduelle. Vérification :
  ```bash
  grep -rn "sc-for\|sc-if\|style-hover\|{{\|hint-placeholder" \
    views/back_office views/partials/back_office_sidebar.php views/admin/organization \
    --exclude=compose.php
  ```
  ne renvoie **rien**. (`views/admin/organization/communications/compose.php` contient légitimement des `{{ }}` : ce sont des variables de gabarit d'e-mail, sans rapport avec le format `.dc` — d'où l'exclusion.)
- [ ] Toute donnée manquante figure dans le tableau de §10, affichée `—` ou `N/D` — jamais inventée.
- [ ] Le RBAC est inchangé : mêmes lignes visibles, mêmes actions disponibles, pour chaque rôle testé.
- [ ] Aucune modification de `routes/web.php`, `migrations/`, `app/Repositories/`.
- [ ] `vendor/bin/phpunit` et `vendor/bin/phpstan analyse` passent au même niveau qu'avant le commit.
- [ ] Un état vide (0 ligne) et un état d'erreur (repository qui lève) s'affichent proprement dans la nouvelle charte.

---

## 10. Quand la donnée n'existe pas

Interdiction absolue de combler par une valeur plausible. Procédure :

1. Afficher `—` (cellule) ou `N/D` (KPI). Le projet le fait déjà : voir `$operationsKpiSnapshot` dans `OrganizationDashboardController` (`'value' => 'N/D', 'trend' => 'Instrumentation requise'`). S'aligner sur ce précédent.
2. Consigner la ligne dans `docs/frontend/dc/ECARTS-MAQUETTE.md`, à créer au premier écart, avec ce format :

   | Page | Élément maquette | Donnée requise | Source candidate | Statut |
   |---|---|---|---|---|
   | `atakSessions` | KPI « LATENCE MOY. » | latence moyenne par session TAK | non instrumenté | à décider |

3. Continuer la page. Un écart documenté ne bloque pas le lot ; une donnée inventée le fait échouer.

---

## Annexe A — message d'amorçage à coller dans Cursor

> Lis `docs/prompts/cursor-backoffice-dc-integration-fr.md` en entier avant de coder, puis `docs/frontend/dc/BackOffice.dc.html`.
>
> Trois choses à comprendre avant de commencer :
> 1. `BackOffice.dc.html` **n'est pas** du HTML collable : c'est un template `.dc` avec `{{ }}`, `<sc-for>`, `<sc-if>`, `onClick`, `style-hover`, interprété par un runtime React. Le copier-coller porte sur la **géométrie** (styles inline, hex, px, polices, libellés), pas sur le fichier.
> 2. Ce n'est pas 32 pages mais **1 coquille générique + 32 jeux de données**. Tu portes la coquille une fois (§7), puis chaque page ne fournit qu'un tableau `$page`.
> 3. Les données de la maquette sont **fictives**. Le backend existe : les routes `/back-office/*` sont déjà là (table de correspondance en §4). Tu ne crées **aucune** route, table, migration ni repository.
>
> Commence par le **lot 0** du §7 et rien d'autre : CSS, `AthenaTable.php`, les 11 partials, le restyle du rail. Tu t'arrêtes, tu me montres `/back-office`, et tu attends ma validation avant la première page métier.
>
> À chaque commit, tu coches explicitement la liste du §9, greps inclus, et tu me colles le résultat.

## Annexe B — gabarit de tâche, une page

> Page : `<clé PAGES>` — route `<route>`.
>
> 1. Lis `docs/frontend/dc/BackOffice.dc.html` : l'entrée `PAGES.<clé>` (pour la **forme**) et les blocs de la coquille qu'elle active.
> 2. Lis le contrôleur et la vue actuels de bout en bout. Liste-moi les variables déjà disponibles dans la vue.
> 3. Fais-moi le mapping champ par champ : chaque KPI, colonne, filtre, badge → sa source réelle. Ce qui n'a pas de source va dans `ECARTS-MAQUETTE.md` avec `—` / `N/D`.
> 4. **Attends ma validation du mapping** avant d'écrire la vue.
> 5. Écris la vue en réutilisant les partials du lot 0. Aucun markup dupliqué, aucune valeur en dur.
> 6. Coche le §9 et colle-moi la sortie des deux greps.
