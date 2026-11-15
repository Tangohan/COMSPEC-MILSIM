# Bureau SSE : liaisons, provenance et Cerbères

Un seul widget « Liaisons » dans chaque page d'élément du bureau SSE montre le chemin de liaison complet de l'élément (d'où il
vient, ce qui en découle, pourquoi chaque lien existe), ses liens directs, et permet d'ajouter un lien à la main ou de citer
l'élément dans un compte rendu JNET. Tout ce que le système fait tout seul porte un nom : les **Cerbères**.

## Ce qui existait (audit)

| Élément | Où le lien est porté | Sens | Explication du lien |
|---|---|---|---|
| Identité du registre → personne | `sse_identities.person_id` | un seul sens | aucune |
| Papier → identité, titulaire | `sse_id_documents.identity_id`, `holder_person_id` | un seul sens | aucune |
| Contrôle → papier, identité, personne | `sse_id_checks.document_id`, `identity_id`, `person_id` | un seul sens | aucune : le moyen (papier, clé biométrique, fiche désignée, identifiant de jeu) n'était pas gardé |
| Contrôle → fiche de renseignement | `sse_id_checks.field_note_id` | un seul sens | aucune |
| Fiche SEEK II → identité, personne, papier, contrôle | `sse_seek_records.*_id` | un seul sens | aucune |
| Avis de recherche → identité | `sse_wanted_notices.identity_id` | un seul sens | motif de l'avis seulement |
| Document émis → personne, identité | `sse_doc_issues.person_id`, `identity_id` | un seul sens | aucune |
| Photo, élément du fichier (adresse, véhicule, téléphone), mention → identité | `sse_file_photos`, `sse_file_links`, `sse_file_mentions` | un seul sens | aucune |
| Personne → dossier | `sse_case_persons` | deux sens | note libre (« Classement automatique — … ») |
| Fiche de renseignement, document, transmission → dossier | `sse_field_notes.case_id`, `sse_documents.case_id`, `sse_intel_events.case_id` | un seul sens | aucune |
| Relations d'analyste, décisions de croisement | `sse_relations`, `sse_cross_decisions` | deux sens | note et raison |
| Compte rendu JNET → contenu | `jnet_links` (huit types de contenus) | deux sens | aucune (pas d'élément SSE) |

**Trous** : liens jamais enregistrés (la transmission « Identité relevée » liée à une personne par son contenu JSON seulement),
liens à sens unique sans lien retour, et surtout traitements automatiques qui ne laissaient aucune trace du pourquoi.

### Le mécanisme « dossier créé et rempli tout seul »

Il n'existe aucune validation automatique d'un dossier : la validation d'un document de rédaction (`sse_documents.status =
valide`) est toujours humaine. Ce que l'on voit sur une transmission « Identité relevée » avec « Ouvrir le dossier validé », c'est
un dossier ouvert ou rempli par le système :

1. le terminal SEEK envoie un code de dossier : `SseApiController::fileOnCase` ouvre le dossier si le code est inconnu
   (`cerbere.create.case_from_code`) et y classe la fiche (`cerbere.link.case_terminal`) ;
2. sans code : `SseAutomationService` classe la fiche dans le seul dossier ouvert (`cerbere.link.case_single_open`) ou dans le
   dossier du jour `AUTO-AAMMJJ` ouvert par le système (`cerbere.create.case_daily`, `cerbere.link.case_daily`) ;
3. au contrôle : `SseIdSubjectMatchService` rattache le contrôle à l'identité, à la personne ou au papier par le papier présenté,
   la clé biométrique, l'identifiant de jeu ou la fiche désignée, et rattache après coup les contrôles précédents de la même clé
   (`cerbere.link.check_retro`).

Avant cette version, seule une note libre ou une ligne du journal de la carte gardait un indice de ce « pourquoi ».

## Cerbères

| Traitement | Ce qu'il fait | Règles (codes stables) |
|---|---|---|
| Cerbère-Liaison | rattache un contrôle ou classe une fiche | `cerbere.link.check_document`, `cerbere.link.check_biokey`, `cerbere.link.check_netid`, `cerbere.link.check_designated`, `cerbere.link.check_retro`, `cerbere.link.person_identity`, `cerbere.link.case_single_open`, `cerbere.link.case_daily`, `cerbere.link.case_terminal`, `cerbere.backfill.column` |
| Cerbère-Validation | prépare les propositions à valider (jamais validées sans décision humaine) | `cerbere.validate.engine_suggestion` |
| Cerbère-Corrélation | doublons probables, correspondances fortes, co-présence | `cerbere.correlate.duplicate_biometric`, `cerbere.correlate.hard_match`, `cerbere.correlate.co_presence`, `cerbere.correlate.engine_suggestion` |
| Cerbère-Détection | identité sous avis de recherche actif qui réapparaît | `cerbere.detect.wanted` |
| Cerbère-Création | ouvre le dossier du jour ou le dossier d'un code inconnu | `cerbere.create.case_daily`, `cerbere.create.case_from_code` |

Le nom est un **affichage** : aucune clé technique, colonne, route, droit ni valeur stockée ne change (`rule_key` du moteur, auteur
« Automatisme » des relations, type `SSE_AUTO` du journal). La table de correspondance est `App\Support\SseCerbere` (`RULES`,
`LEGACY_LABELS`), testée par `SseCerbereTest`. La page `atak/sse/cerberes` (menu Analyse, et lien du widget) liste, en lecture
seule, les traitements, leur état et leurs derniers événements.

## Provenance enregistrée : `sse_entity_links`

Une ligne = un lien : `source_type`, `source_id`, `target_type`, `target_id`, `relation`, `reason` (motif en clair), `rule_code`
(règle Cerbère, `manual.link` pour un lien manuel), `created_by` (`user` ou `system`), `created_by_user_id`, `created_at`,
`tenant_id`. Unicité sur le couple d'éléments et la communauté (dans la définition de la table, compatible sqlite), index sur les
deux extrémités. Écrite par `SseProvenance` (jamais bloquante) aux endroits listés ci-dessus et par le widget.

**Migration** : étape 4690 `sse_entity_links` (`database/steps/4690_sse_entity_links.php`, SQL dans
`migrations/2026_11_16_110_sse_entity_links.sql`, retour arrière `migrations/rollback/sse_entity_links_down.sql`). Aucune donnée
ajoutée. Sans elle, le widget est en **mode dégradé** : liens déduits des colonnes existantes (badge « Déduit », motif non
enregistré), message clair, ajout de lien désactivé.

### Rétro-remplissage

```
php tools/sse-backfill-links.php                       # simulation (par défaut) : compte, n'écrit rien
php tools/sse-backfill-links.php --apply               # écrit, par lots de 500, chaque lot dans une transaction
php tools/sse-backfill-links.php --apply --tenant=7 --batch=200 --max-batches=20
php tools/sse-backfill-links.php --apply --resume=7:3:1520   # reprend au curseur affiché
```

Reconstruit les liens déductibles des colonnes (règle `cerbere.backfill.column`, date de l'élément d'origine, motif de la colonne
quand elle en a un). Idempotent (un couple déjà lié, dans un sens ou l'autre, n'est jamais doublé) ; ne modifie ni ne supprime aucune
autre donnée.

## Widget et service

* `SseLinksWidget::render('person', $id)` : une ligne dans la page. Vue partielle `views/atak/sse/_links_widget.php`, script
  `public/assets/js/sse-links.js`, styles `public/assets/css/sse-links.css` (variables `--sl-*`, jetons `--ui-*` du portail).
* `SseLinkGraph` (lecture seule) : parcours en largeur, profondeur 6, 200 nœuds, une fois par élément (cycles sans effet),
  une requête par source et par niveau puis une par type (aucun N+1). Un adaptateur par type (`SseLinkAdapters`, extensible).
* Lien manuel : motif obligatoire (5 caractères), droit d'écrire de la section de l'élément, lecture des deux bouts, jeton CSRF,
  audit. Retrait d'un lien manuel seulement (jamais Cerbère ni déduit), audité.

### Confidentialité

Chaque nœud passe `SseLinkVisibility` avec les droits du lecteur : **coupé** (identité de couverture, d'emprunt ou falsifiée sans
l'habilitation Confidentiel : aucune trace), **restreint** (section non lisible, identité d'un membre sans titulaire, hiérarchie,
habilitation ou dérogation, classification au-dessus de l'habilitation, hors de la portée du code d'accès, dossier d'intérêt à
diffusion restreinte) : « Élément restreint », sans nom, référence, type, adresse, motif ni date, et le chemin ne le traverse pas.
La communauté est celle de la session, jamais celle de la requête.

## JNET

Un élément SSE se cite dans un échange (compte rendu), une page ou un lien : type JNET `sse`, identifiant `type-numéro`
(`person-12`), dans `jnet_links` et dans le texte `[[sse:person-12|IDN-00012]]` (libellé neutre = la référence). Rien d'autre n'est
copié. À la lecture, le titre et le chemin de liaison résumé sont résolus avec les droits SSE **du lecteur** (« Élément restreint »
sinon) ; le widget de l'élément SSE montre « cité dans : compte rendu X » avec les droits JNET du lecteur.

## Limites

Les pages sans fiche propre (avis, fiche SEEK II, photos, véhicules, téléphones) sont des maillons du chemin de la personne ou de
l'identité. Les liens de contenu JSON (transmission → personne) restent implicites. Les libellés visibles des détections d'avis de
recherche, du générateur et des pages en refonte ne sont pas encore renommés (second passage).
