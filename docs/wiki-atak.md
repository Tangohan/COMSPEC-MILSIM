# Wiki ATAK public (`/wiki/atak`)

Documentation joueur du mod COMSPEC ATAK sur le site, en français et en anglais selon la langue du visiteur.
Le contenu n'est pas en base : il est lu dans les fichiers du dépôt, ceux qui servent déjà aux sujets Steam.

## Adresses

| Adresse | Contenu | Source |
| --- | --- | --- |
| `/wiki/atak` | Accueil : présentation, prérequis, installation, catégories, dernière version | `docs/steam/STEAM_DESCRIPTION_LONG_{fr,en}.md` (sans la section « Les apps »), `00_INDEX.txt`, `CHANGELOG-ATAK.md` |
| `/wiki/atak/apps` | Sommaire des apps par catégorie, nom de classe, résumé | `docs/steam/discussions/00_INDEX.txt` |
| `/wiki/atak/<slug>` | Une page par app ou sujet d'aide | `docs/steam/discussions/NN_<slug>.txt` (ex. `01_carte.txt` → `/wiki/atak/carte`) |
| `/wiki/atak/nouveautes` | Le « Résumé joueur » de chaque version, FR / EN | `CHANGELOG-ATAK.md` |
| `/wiki/atak/recherche?q=…` | Recherche plein texte (sans accents ni casse, tous les mots) | toutes les pages |
| `/wiki` | Redirige vers `/wiki/atak` | |

Le slug vient du nom du fichier : renommer un fichier change l'adresse de la page (à éviter, les liens externes
cassent). Les sujets d'aide `90_aide-installation-serveur-logs` et `91_aide-reglages-serveur-cba` sont cités par
toutes les pages (`AtakWiki::HELP_INSTALL`, `AtakWiki::HELP_CBA`).

## Mettre à jour

Rien à générer : modifier le fichier suffit, la page change au prochain affichage.

1. Modifier le sujet dans `docs/steam/discussions/` (format décrit dans le `README.md` du dossier : ligne 1 = titre
   Steam, `[h2]FRANÇAIS[/h2]` puis `[h2]ENGLISH[/h2]`, `===== RÉPONSE =====` entre deux messages).
2. Nouvelle app : créer `NN_<slug>.txt` et ajouter sa ligne dans `00_INDEX.txt` (même nom français que le `[h1]` du
   sujet, sinon le sujet est rangé dans « Autres »).
3. Contrôler : `php tools/wiki/atak_wiki.php` (une page par fichier, titres FR / EN, liens internes, balises mal
   fermées ; code de sortie 1 en cas de problème). `php tools/wiki/atak_wiki.php warm` reconstruit aussi le cache.
4. Les tests `tests/Unit/AtakWikiTest.php` vérifient la même chose en CI.

Le catalogue (titres, résumés, texte de recherche, nouveautés) est mis en cache dans
`storage/cache/atak-wiki-<empreinte>.ser`, reconstruit dès qu'un fichier source change (date et taille).

## Conversion BBCode

`App\Support\Wiki\BbcodeRenderer` : tout le texte est échappé, seules les balises connues produisent du HTML
(`h1` `h2` `h3`, `b` `i` `u` `s`, `list` `olist` `[*]`, `url` en http(s) ou chemin du site uniquement, `hr`, `quote`,
`table` `tr` `th` `td`, `code`, `noparse`, `spoiler`). Une balise inconnue reste du texte (`[URGENT]`), les balises
ouvertes sont refermées. `[/list]` ferme aussi un `[olist]`, comme sur Steam.

Liens entre apps : un nom d'app en gras (`[b]GPS[/b]`, ou `[b]GPS, Itinéraire et Relief[/b]`), écrit exactement comme
son titre dans la langue de la page, devient un lien vers sa page.

## Accès

Menu et barre de l'accueil public, pied de page public, page `/atak-natif`, page du mod `/atak/mod`, guide du portail
(`/documentation`) et pied de page du portail. Le mod n'a pas de lien « Aide » vers le site.

## Code

- `app/Support/Wiki/AtakWiki.php` : lecture des fichiers, index, nouveautés, présentation, cache, liens entre apps.
- `app/Support/Wiki/BbcodeRenderer.php`, `app/Support/Wiki/Search.php`.
- `app/Controllers/Web/AtakWikiController.php`, routes dans `routes/web.php`, vues `views/site/wiki_atak/`,
  style `public/assets/css/wiki-atak.css` (jour / nuit, même choix mémorisé que `/atak-natif`).
- Plan du site : `SeoController::sitemap` ajoute toutes les pages du wiki.
- Interface traduite par `lang/en/site_auto.php` ; le contenu des pages porte `translate="no"` (il est déjà dans la
  langue du visiteur).
