# Matricule d’organisation (`tenant_member_number`)

## Principe

Deux identifiants coexistent :

| Notion | Stockage | Portée | Modifiable |
|--------|----------|--------|------------|
| **Identifiant plateforme** | `users.athena_identifier` | Unique plateforme | Non (système) |
| **Matricule d’organisation** | `users.tenant_member_number` | Unique **par tenant** (si activé) | Oui (`personnel.member_number.manage`) |

Le matricule dossier historique (`personnel_profiles.matricule_internal` / `MatriculeService` + `tenant_matricule_config`) reste intact et n’est **pas** remplacé.

## Isolation multi-tenant

Toute opération est scopée :

- `tenant_id` + `user_id`, ou
- `tenant_id` + `tenant_member_number`

Le `tenant_id` provient de la session authentifiée, jamais du navigateur.

Exemple SQL autorisé :

```sql
UPDATE users
SET tenant_member_number = ?
WHERE id = ? AND tenant_id = ?;
```

Un même numéro (`OPS-001`) peut exister dans le tenant A et le tenant B. Il est refusé en doublon **dans** le même tenant si `unique_required` est actif.

## Configuration (back-office)

**Organisation → Personnel / Membres → Configuration des matricules**

Route : `/back-office/organisation/matricules`

Champs (`tenant_member_number_config`) :

- `enabled` — active la fonctionnalité
- `label` — libellé affiché (défaut : « Matricule d’organisation »)
- `mode` — `free` | `automatic` | `assisted`
- `pattern` — ex. `{PREFIX}-{NUMBER:4}`
- `prefix`
- `next_sequence`
- `unique_required`
- `required`

### Modes

1. **free** — saisie manuelle par l’administrateur
2. **automatic** — génération selon le format à l’intégration / régénération
3. **assisted** — saisie manuelle avec suggestion du prochain numéro

### Variables de format

`{PREFIX}`, `{NUMBER}`, `{NUMBER:3|4|5}`, `{YEAR}`, `{YEAR:2}`, `{MONTH}`, `{TENANT}`, `{UNIT}`, `{GRADE}`

`UNIT` et `GRADE` sont optionnels (architecture prête, sans dépendance obligatoire).

## Permission

`personnel.member_number.manage` — attribuer, modifier, supprimer, régénérer.

La consultation suit les permissions normales du personnel.

## Affichage

Surfaces métier :

1. `tenant_member_number` s’il existe
2. sinon identifiant plateforme (`athena_identifier`)

API / payloads :

```json
{
  "platform_number": "K7M2NPQRS",
  "tenant_member_number": "GEND-0458",
  "display_number": "GEND-0458"
}
```

Helper : `TenantMemberNumberService::identityPayload($userRow)`.

## Recherche / import / export

- Recherche personnel / portail / listes BO : nom, prénom, `athena_identifier`, `tenant_member_number` (toujours filtrée par `tenant_id`).
- Import CSV (BO matricules) : colonnes `email` + `tenant_member_number`.
- Export CSV (BO matricules, `/back-office/organisation/matricules/export`, `?filtre=sans` pour les membres sans matricule) : `nom;indicatif;email;tenant_member_number;matricule_plateforme`, réimportable tel quel (BOM UTF-8, formules neutralisées).

## Page Matricules : contrôle du registre et attribution

- **Contrôle du registre** (`App\Support\MemberNumberAnalysis`, calcul pur) : doublons (insensibles à la casse), membres actifs sans matricule, valeurs hors modèle, numéros sautés dans la séquence, séquence en retard sur le plus grand numéro attribué (bouton « Reprendre à N », route `POST …/matricules/recaler`). La reconnaissance du modèle reproduit exactement `TenantMemberNumberService::format()`.
- **Registre** : recherche et filtres (sans matricule / doublons / hors modèle), correction d’un matricule (`POST …/matricules/membre`, vide = retirer), attribution du prochain matricule à un membre ou à tous ceux qui n’en ont pas (`POST …/matricules/attribuer`, 200 par passage, modes automatique / assisté uniquement).
- **Modèle** : éléments insérables au curseur, modèles courants, aperçu en direct des trois prochains matricules. Un mode généré refuse un modèle sans `{NUMBER}` (tous les matricules seraient identiques).
- **Derniers changements** : 12 dernières lignes de `tenant_member_number_audit` de la communauté.
- Export effectifs : colonnes séparées `platform_number` et `tenant_member_number`.

## Audit

Table `tenant_member_number_audit` + événement admin `member_number_changed` (ancien / nouveau / motif / acteur).

## ATAK / Arma

Le matricule organisation est exposé en **métadonnée** (`member_number`) sur certaines réponses de liaison. Il ne devient pas une clé technique de synchronisation (les indicatifs restent la clé).

## Migration

- `migrations/20260829150000_tenant_member_number.sql`
- Bootstrap idempotent : `bootstrap/tenant_member_number_migration.php` (enregistré dans `run-migrations.php`)

## Tests

`tests/Unit/TenantMemberNumberServiceTest.php` — formats, payload, câblage migration / permission / routes / isolation SQL.
`tests/Unit/MemberNumberAnalysisTest.php` — reconnaissance du modèle, doublons / trous / séquence en retard, refus d’un modèle sans numéro, rendu de la page, export.
