# ATAK : import d'une couverture générée sur Athena

But : donner à un joueur une couverture complète et cohérente, générée sur Athena, qu'il importe dans le jeu sans connexion à Athena. Personnes fictives seulement.

Règle de licence : le mod ne fait aucune requête réseau pour importer. La DLL lit un fichier local de `Documents\Arma 3 - COMSPEC` ; le bouton « Demander la mise à jour » (paquet Athena) ne change pas.

## Parcours

1. Athena › Ma situation › Mes papiers › Générer ma couverture pour le jeu : pays, sexe, langue des textes, photo fictive facultative. Limite : 3 générations par 24 h (20 pour un gestionnaire qui génère pour un autre).
2. Télécharger : fichier `comspec-couverture-CVR-XXXXXXXX.json`.
3. Le déposer dans `Documents\Arma 3 - COMSPEC`.
4. Téléphone ATAK › Ma couverture › Importer ma couverture.

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
