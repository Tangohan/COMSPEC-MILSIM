# Bureau SSE : fenêtre « Rattacher » universelle

Une seule fenêtre pour rattacher une pièce (ou une sélection de pièces) à autre chose qu'un dossier : dossier d'intérêt, piste,
investigation, personne, identité du registre, papier, document, site, organisation, véhicule, matériel, campagne, avis de
recherche, fiche de renseignement, transmission. Elle remplace les petites listes déroulantes « Aucun dossier ▾ ».

Un rattachement **est** un lien du widget « Liaisons » (`sse_entity_links`), enrichi : nature en clair, nom donné à la pièce dans
la cible, motif obligatoire, étiquettes, note, alignement de confidentialité. Il n'y a qu'un système de liens.

## Utiliser le composant dans une page

```php
<?php \App\Services\Sse\Attach\SseAttachWindow::section('case', $id); ?>      // section « Éléments rattachés » + bouton « Rattacher à… »
<?= \App\Services\Sse\Attach\SseAttachWindow::button('note', $id) ?>          // un bouton seul
<?php \App\Services\Sse\Attach\SseAttachWindow::render(); ?>                  // la fenêtre, une fois, hors de tout formulaire
```

```html
<button type="button" data-ra-open data-ra-pieces="note:12">Rattacher à…</button>
<button type="button" data-ra-open data-ra-type="person" data-ra-collect=".pe-check input:checked">Rattacher à…</button>   <!-- lot -->
<button type="button" data-ra-open data-ra-pieces="note:12" data-ra-move="34">Déplacer</button>                              <!-- déplacer le rattachement 34 -->
```

`section()` rend aussi la fenêtre ; elle n'est jamais rendue deux fois. Le script la déplace au niveau du document : la ligne peut
être posée dans un onglet ou une colonne. Fichiers : `views/atak/sse/_attach_window.php`, `_attached_section.php`,
`public/assets/js/sse-rattacher.js`, `public/assets/css/sse-rattacher.css` (préfixe `ra-`, variables du portail). Le script ne
contient aucun texte : libellés et gabarits sont dans la vue, donc traduits avec la page.

## Périmètres et matrice

`App\Support\SseAttachCatalog` : `SCOPES` (les cibles, dans l'ordre des onglets), `MATRIX` (type de pièce → cibles qui ont du sens,
de la plus probable à la moins probable), `NATURES` (classé dans, concerne, preuve de, mentionne, issu de, à vérifier avec, lié à,
plus un libellé libre), `REASON_TEMPLATES`, `CLASSIFIED` (types qui portent une classification, donc alignables), `CREATABLE`
(dossier, piste). Ajouter un périmètre : une entrée dans `SCOPES` et `MATRIX`, un type dans `SseLinkCatalog::TYPES`, un adaptateur
dans `SseLinkAdapters` (description et marques de confidentialité), une ligne dans `SseAttachDirectory::TABLES`.

Types ajoutés au vocabulaire des liaisons : `lead` (piste), `mesh` (investigation), `organization`, `vehicle`, `equipment`
(nœuds d'investigation, registre « Objets »). Relations ajoutées : `concerns`, `evidence_of`, `mentions`, `verify_with`.

## Pistes

Entité légère (`sse_leads`) : référence `PST-AAAA-NNNN`, titre, description, état (ouverte, suivie, close), classification,
responsable. Pages `atak/sse/pistes` et `atak/sse/pistes/{id}` (menu « Renseignement et enquêtes »). Une piste se crée aussi depuis
la fenêtre, sans la quitter.

## Confidentialité

Toutes les règles sont appliquées côté serveur (`SseAttachService`, `SseAttachDirectory`) ; le script n'affiche que ce qu'il reçoit.

* Chaque cible passe par l'adaptateur de son type et `SseLinkVisibility`, comme le widget « Liaisons » : section lisible,
  habilitation, identité d'un membre (`SseMemberImageGate`), identités de couverture, portée du code d'accès, diffusion d'un dossier
  d'intérêt. Une fiche personne classifiée au-dessus de l'habilitation n'est pas proposée.
* Une cible ou une pièce que le lecteur ne peut pas lire n'est ni listée, ni comptée, ni décrite : la réponse est exactement celle
  d'un élément inexistant (`Élément introuvable`, code 404). La communauté est celle de la session, jamais un paramètre.
* **Alignement** (case « Aligner la confidentialité de la pièce sur celle de la cible ») : il ne peut que relever le niveau de la
  pièce vers celui de la cible, jamais au-dessus de l'habilitation de l'opérateur. Avec plusieurs cibles, la pièce prend le plus haut.
* Une cible moins classifiée que la pièce ne l'abaisse **jamais** : la fenêtre avertit avant validation (« la cible est moins
  protégée que la pièce »), la pièce garde son niveau et reste « Élément restreint » pour les lecteurs de la cible sans
  l'habilitation. Abaisser reste une action explicite de la page de la pièce (droit de gérer, motif, journal).
* Réglage de la communauté « Toujours aligner à la hausse au rattachement » (`sse_portal_settings`, clé `attach_always_align_up`,
  désactivé par défaut, droit de gérer les dossiers ; bouton sur la page Pistes).
* Cible reclassifiée après coup : **Cerbère-Classification** propose, dans la section « Éléments rattachés » de la cible, de relever
  les pièces qui avaient demandé l'alignement. Rien n'est appliqué sans validation humaine.
* Journal `sse_audit_log` : `attach.add`, `attach.update`, `attach.remove`, `attach.move`, `attach.undo`, `attach.align` et
  `attach.align_undo` (ancien et nouveau niveau), `attach.create_target`, `attach.setting`.
* Écritures : jeton CSRF (aperçu compris), débit limité (`FileRateLimiter`), 50 pièces et 12 cibles au plus par validation.

## Compatibilité

Un rattachement écrit aussi la colonne ou la table historique (`SseAttachLegacy`) pour que les listes, compteurs, PDF, exports et
l'API du jeu restent justes : `sse_field_notes.case_id` et `interest_case_id`, `sse_documents.case_id`,
`sse_intel_events.case_id` et `interest_case_id`, `sse_meshes.case_id`, `sse_file_photos.identity_id`,
`sse_identities.person_id`, `sse_case_persons`, `sse_interest_case_links`. Une colonne à valeur unique garde le premier dossier ;
un second rattachement est un lien de plus ; « Déplacer » change la colonne ; « Détacher » ne la vide que si elle désignait cette
cible. Un lien posé par Cerbère n'est jamais supprimé : seul l'enrichissement est retiré. Les anciennes routes
(`fiches/{id}/rattachement`, `fichiers/photos/{id}/rattacher`, lot « link » des personnes) restent en place.

Les liens déjà déductibles des colonnes se rétro-remplissent avec l'outil existant, en simulation par défaut :
`php tools/sse-backfill-links.php` (voir `docs/sse-liaisons.md`). Aucun autre outil de reprise n'est nécessaire : un lien existant
est enrichi au premier rattachement.

## Migration

Étape 4840 `sse_attachments` (`database/steps/4840_sse_attachments.php`, SQL `migrations/2026_11_19_100_sse_attachments.sql`, retour
arrière `migrations/rollback/sse_attachments_down.sql`) : trois tables (`sse_link_attachments`, `sse_attach_tags`, `sse_leads`),
unicités dans les `CREATE TABLE`, aucune colonne ajoutée, aucune donnée modifiée. Sans elle, la fenêtre fonctionne en lecture et
pour le rattachement historique à un dossier (fiche, document, transmission, personne) ; le reste est désactivé avec un message.

## Routes

`GET atak/sse/rattacher/contexte | cibles | cible` (JSON), `POST atak/sse/rattacher` (rattacher), `…/apercu`, `…/annuler`,
`…/creer`, `…/aligner`, `…/reglage`, `…/{id}/modifier | detacher | deplacer`, `GET|POST atak/sse/pistes`, `GET|POST atak/sse/pistes/{id}`.
