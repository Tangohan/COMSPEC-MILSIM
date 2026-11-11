# ATAK : import d'une couverture générée sur Athena

But : donner à un joueur une couverture complète et cohérente, générée sur Athena, qu'il importe dans le jeu sans connexion à Athena. Personnes fictives seulement.

Deux voies, même contenu : la synchronisation depuis le téléphone (réseau, derrière la licence) ou le fichier (aucune requête réseau, la DLL lit un fichier local de `Documents\Arma 3 - COMSPEC`). Le bouton « Demander la mise à jour » (paquet Athena) ne change pas.

## Parcours

1. Athena › Ma situation › Mes papiers › Générer ma couverture pour le jeu : pays, sexe, langue des textes, photo fictive facultative. Limite : 3 générations par 24 h (20 pour un gestionnaire qui génère pour un autre).
2. Téléphone ATAK › Ma couverture › Importer ma couverture › **Synchroniser avec Athena** : fenêtre « Mes couvertures sur Athena », choix, Importer la sélection.
3. Sans connexion : Télécharger (fichier `comspec-couverture-CVR-XXXXXXXX.json`), le déposer dans `Documents\Arma 3 - COMSPEC`, puis Importer un fichier.

## Synchronisation depuis le téléphone (`fn_idCoverAthena`)

- Garde, avant toute requête : service Athena allumé, licence acceptée, liaison Athena autorisée (`COMSPEC_AthenaReady`), compte lié (`athenaStatus` logged), COMSPEC Link 2.0.118 ou plus. Sinon la fenêtre dit pourquoi, sans appel réseau.
- Canal : commande existante `GetIdentityPack` de COMSPEC Link (DLL inchangée), dans une tâche de fond, puis `GetIdentityPackChunk`. Opération `~covers` (liste) ou `~cover:CVR-…,CVR-…` (5 fichiers au plus), `since` = `v=1` (version du protocole côté mod). Réponse au format du paquet, tout dans `settings` : `covers_api` (1), `covers` ou `files` + `errors`.
- Site : `SseCoverSyncService`, `AtakCoverSyncApiController` (aussi `GET /api/atak/covers` et `GET /api/atak/covers/files?refs=`). Compte et communauté lus dans le jeton de jeu, jamais dans la requête ; une référence d'un autre membre répond `not_found`. Limites : 12 listes par minute, 10 téléchargements par 10 minutes, par compte.
- Liste : `ref`, `name`, `summary` (métier · ville), `country`, `country_code`, `status` (valid, expired, invalid), `has_photo`, `generated_at`, `expires_at`, `updated_at`, `size` (octets du fichier), `sha` (`content_sha256`), `by_manager`, `downloaded`, `active` (vrai sur la couverture que le membre a marquée active sur le site, listée en premier ; les révoquées ne sont pas listées).
- Fichiers : le fichier `comspec-cover` v1 tel que généré, relu par `SseCoverExportService::parse`, sauf `cover.photo` mis à null (le jeu ne l'affiche pas). Chaque fichier servi est marqué téléchargé et journalisé.
- Mod : champs revalidés par `fn_idCoverImport` « clean », gardés dans le profil (`comspec_sse_idCoverLib`, 10 au plus) ; la plus récente de la sélection devient l'active (`fn_idCoverImport` « use », même chemin que le fichier : profil, serveur, légende). États d'une ligne : Nouvelle, Déjà importée, Mise à jour disponible (empreinte différente), Active, Expirée.
- Couverture active sur le site (champ `active` de la liste, SSE 1.10.0) : marquée « Active sur Athena », présélectionnée à l'ouverture de la fenêtre (sauf si elle est déjà l'active à jour du poste) ; dans la sélection, c'est elle qui devient l'active, avant la plus récente. Site ancien sans le champ : comportement d'avant, sans marque ni présélection.
- Couverture révoquée sur le site : absente de la liste, donc plus proposée. Sa copie du profil est retirée à la lecture de la liste (site récent seulement), sauf la couverture en cours, jamais effacée d'office : la fenêtre dit qu'elle n'est plus sur Athena. Une couverture hors de la liste ne s'active plus depuis la fenêtre.
- Le tampon du paquet de la DLL est partagé avec `fn_idAthenaPack` « fetch » : verrou `comspec_sse_idPackBusy` et contrôle de l'opération lue dans la réponse.

## Fichier `comspec-cover` v1

JSON UTF-8, 262 144 octets au plus, profondeur 6, clés connues et uniques, sans espace entre `"cover":` et la fin de l'objet.

| Clé | Contenu |
|---|---|
| `format` | `comspec-cover` |
| `version` | `1` |
| `ref` | `CVR-` + 8 hexadécimaux majuscules |
| `generated_at`, `expires_at` | UTC, `AAAA-MM-JJThh:mm:ssZ` (validité 90 jours) |
| `account_ref` | 16 hexadécimaux : empreinte (HMAC) du compte, jamais un identifiant |
| `cover` | voir ci-dessous |
| `content_sha256` | SHA-256 du texte exact de `cover` |
| `sig` | HMAC-SHA256 serveur de `ref|generated_at|account_ref|content_sha256` : seul Athena peut le vérifier |

`cover` : `seed`, `lang` (`fr` ou `en`), `name`, `first_name`, `last_name`, `sex` (`M`, `F`, `X`), `dob` (`AAAA-MM-JJ`), `pob`, `country`, `country_code`, `address`, `city`, `profession`, `employer`, `phone`, `travel`, `family`, `history` (8 lignes au plus), `contacts` (10 triplets `[nom, lien, téléphone]` au plus), `docs` (10 objets `type`, `country`, `number`, `issued`, `expires` au plus ; `type` parmi `id_card`, `passport`, `driving_license`, `military_id`), `photo` (`null` ou `{mime: "image/jpeg", b64}`, 150 000 caractères au plus).

Le mod ne peut vérifier que le format et l'empreinte : un fichier retouché avec une empreinte recalculée passe, et ne sert qu'à remplir la fiche locale du joueur. Rien n'est prouvé à Athena par ce fichier.

## Ce que fait l'import

- DLL (`ReadCoverFile`, `ReadCoverFileChunk`) : lecture stricte, résultat en paires lisibles par `parseSimpleArray`. La photo est vérifiée et jamais retransmise.
- SQF (`fn_idCoverImport`) : nettoyage une seconde fois (textes coupés, caractères de contrôle retirés), profil du joueur (`profileNamespace`), envoi au serveur (`coverImport`, revalidé), renvoi à chaque connexion et respawn.
- `fn_idLegend` : la couverture importée l'emporte champ par champ. Les papiers, les profils d'identité du registre SSE et `fn_radioGuard` ne sont pas touchés, l'infiltration n'est pas ouverte.
- « Oublier l'import » retire le profil et l'entrée du serveur.
