# Coopérations inter-unités

Module `/back-office/cooperation/missions*` : une communauté « support » propose une coopération à une ou plusieurs
communautés partenaires, la négocie, la lance, la conduit puis la clôture avec un retour d’expérience.

Ce document décrit le parcours, le modèle d’état unifié, les transitions autorisées, les événements journalisés et
les notifications. Le guide utilisateur correspondant est dans `views/documentation/site/_guide_part_b.php`
(section « Coopérations inter-unités »).

## Fichiers clés

| Rôle | Fichier |
| --- | --- |
| Contrôleur des écrans et actions | `app/Controllers/Web/InterteamMissionWebController.php` |
| Recherche JSON (unités, membres) | `app/Controllers/Web/CooperationSearchApiController.php` |
| Règles de transition (pur, testé) | `app/Services/Cooperation/CooperationTransitionRules.php` |
| Progression en 5 étapes (pur, testé) | `app/Services/Cooperation/CooperationProgress.php` |
| Droits du module | `app/Support/CooperationAccess.php` |
| Libellés métier | `app/Support/CooperationDictionary.php` |
| Données | `app/Repositories/InterteamMissionRepository.php` |
| Annonces (courriel, portail, forum) | `app/Services/Cooperation/CooperationAnnouncementDispatcher.php`, `CooperationAnnouncementEvents.php`, `CooperationEmailLayout.php` |
| Envois automatiques | `app/Services/Cooperation/CooperationReminderService.php`, `app/Services/Cron/Jobs/CooperationRemindersCronJob.php` |
| Vues | `views/back_office/cooperation/missions/*.php` |
| Scripts (chargés sur le module uniquement) | `public/assets/js/cooperation/*.js` |
| Styles | `public/assets/css/cooperation.css` |

## Parcours

1. **Cadrage** — création via l’assistant (`/missions/create`) : titre, typologie, priorité, date limite de réponse,
   besoins ; partenaires facultatifs, invités à l’enregistrement si « envoyer les invitations » est coché.
2. **Invitations & négociation** — invitation multiple (`partner_tenant_ids[]`), réponse des unités (acceptation,
   refus avec motif facultatif, contre-proposition), relance manuelle, retrait d’une unité, annulation de la proposition.
3. **Préparation** — lancement (`activate`) dès qu’au moins un partenaire a accepté, sans invitation en attente ni
   contre-proposition à traiter ; autorisation de partage de chaque responsable (familles de données + code OTP).
4. **Exécution** — conduite par étape (`operational_stage`), points de situation, réunions, suspension / reprise.
5. **Clôture & REX** — clôture (motif, bilan, conservation) puis retours d’expérience.

## Modèle d’état unifié

Trois champs historiques coexistent ; `CooperationProgress::compute()` les combine en une seule lecture.

| Champ | Valeurs |
| --- | --- |
| `interteam_missions.status` | `draft`, `pending`, `active`, `archived` |
| `interteam_missions.cooperation_phase` | `proposed`, `preparing`, `active`, `suspended`, `closed`, `cancelled`… |
| `interteam_missions.operational_stage` | `opord_draft` → `command_validation` → `execution` → `closed_aar` → `corrective_actions` |
| `interteam_mission_participants.status` | `invited`, `active`, `declined`, `left` (rôle `lead`, `co_lead`, `partner`) |

Correspondance avec les cinq étapes :

| Étape | Condition |
| --- | --- |
| 1 Cadrage | `status = draft` |
| 2 Invitations & négociation | `status = pending` |
| 3 Préparation | `status = active`, étape de conduite avant `execution` |
| 4 Exécution | `status = active`, étape `execution` (phase `active`) |
| 5 Clôture & REX | `status = archived` (sauf annulation) |

États transverses : **suspendue** (`cooperation_phase = suspended`, espace commun en lecture seule) et **annulée**
(`cooperation_phase = cancelled`, `status = archived`, pas de REX). `compute()` renvoie aussi le badge d’état,
`next_action` (libellé, acteur, lien) et, pour chaque étape, la raison d’un éventuel blocage.

## Transitions

Toutes les actions sont des `POST` protégés par jeton CSRF ; les contrôles d’accès (`tenantCanPilotMission`,
`CooperationAccess::canManage` / `canRespond`, Gate) précèdent les règles de `CooperationTransitionRules`.

| Action | Route | Règle |
| --- | --- | --- |
| Inviter (une ou plusieurs unités) | `POST missions/{id}/invite` | `invitation()`, `canInviteTenant()` — renfort pendant l’exécution sur confirmation |
| Accepter / refuser | `POST missions/{id}/accept`, `/decline` | `canRespondToInvitation()` ; `decline_reason` facultatif |
| Relancer | `POST missions/{id}/remind` | `canRemind()` — une relance par unité et par 24 h |
| Retirer une unité | `POST missions/{id}/remove-partner` | `canRemovePartner()` — accès au brief révoqués |
| Annuler la proposition | `POST missions/{id}/cancel` | `canCancelProposal()` — motif obligatoire |
| Lancer | `POST missions/{id}/activate` | `launchReadiness()` |
| Suspendre / reprendre | `POST missions/{id}/suspend`, `/resume` | `canSuspend()`, `canResume()` |
| Changer d’étape de conduite | `POST missions/{id}/operational-stage` | `canConduct()` ; phase synchronisée par `phaseForStage()` |
| Clôturer | `POST missions/{id}/close` | `canClose()` |

Les actions courtes répondent en JSON quand la requête porte `X-Requested-With` (`{ok, variant, message, warning,
redirect, field}`), sinon par redirection avec message flash : sans JavaScript, tout reste utilisable.

## Événements journalisés

`interteam_mission_events.event_type` (libellés dans `CooperationDictionary::eventTypeLabel()`), notamment :
`partner_invited`, `partner_accepted`, `partner_declined` (motif), `counter_proposal_submitted`, `invitation_reminder`
(`manual` vrai ou faux), `partner_removed`, `proposal_cancelled`, `proposal_deadline_elapsed`, `mission_activated`,
`preparation_started`, `operational_stage_updated`, `sitrep_logged`, `mission_suspended`, `mission_resumed`,
`consent_expiring`, `mission_closed`, `rex_submitted`.

## Notifications

`CooperationAnnouncementDispatcher::dispatch($eventKey, $missionId, $actorUserId, $actorTenantId, $extra)` applique,
pour chaque canal (courriel, portail, forum), le gabarit de l’unité support, sinon celui de la plateforme
(`tenant_id = 0`), sinon un texte intégré. **Un gabarit présent mais désactivé coupe le canal** : le texte intégré
ne le réactive pas.

| Événement (`coop_…`) | Destinataires | Ce qui est attendu / bouton |
| --- | --- | --- |
| `invitation_sent`, `invitation_reminder` | unité invitée | répondre — synthèse |
| `partner_accepted`, `partner_declined` | unité support | lancer ou inviter — participants |
| `counter_proposal_submitted` | unité support | traiter — négociation |
| `mission_activated` | unités engagées | autorisation de partage |
| `consent_expiring` | la personne concernée | renouveler — autorisation de partage |
| `partner_removed` | unité retirée | information — liste des coopérations |
| `proposal_cancelled` | unités invitées ou engagées | information — liste des coopérations |
| `mission_suspended`, `mission_resumed` | unités engagées | information — espace commun |
| `operational_stage_updated` | unités engagées | information — conduite |
| `sitrep_added` (facultatif) | unités engagées, sauf l’auteur | information — conduite |
| `mission_closed` | unités participantes | rédiger le REX |

Destinataires : responsables habilités (`cooperation.missions.manage|respond`, `interteam.missions.manage|respond`,
`admin.organization`, `admin.access`) des unités ciblées **et** membres désignés nommément sur la coopération
(`interteam_mission_members`) appartenant à ces unités. La préférence de notification `cooperation.announcement`
est respectée pour les courriels.

Chaque courriel commence par un encadré imposé (`CooperationEmailLayout`) : coopération, unité émettrice, ce qui est
attendu, échéance ; puis le texte du gabarit ; puis un bouton qui mène à l’écran utile. Variables disponibles dans
les gabarits : `{titre_cooperation}`, `{unite_support}`, `{unite_destinataire}`, `{date_limite}`, `{echeance_texte}`,
`{motif}`, `{attendu}`, `{role_attribue}`, `{etape_conduite}`, `{membre_designe}`, `{fin_autorisation}`,
`{resume_sitrep}`, `{lien_synthese}`, `{lien_proposition}`, `{lien_negociation}`, `{lien_espace_commun}`,
`{lien_autorisation}`.

Gabarits de plateforme semés par `bootstrap/cooperation_catalog_and_announcements_migration.php`, puis `…_v2_…` et
`…_v3_migration.php` (idempotents, lancés par `run-migrations.php`). Le semis v3 active les courriels « invitation »,
« refus » et « lancement » uniquement s’ils n’ont jamais été modifiés (`updated_at IS NULL`). Le courriel
`coop_sitrep_added` est livré désactivé.

## Envois automatiques

Tâche `cooperation_reminders` (`CooperationRemindersCronJob`, toutes les heures via `CronRunner`) :

1. signale les dates limites de réponse dépassées (`proposal_deadline_notified_at`, événement
   `proposal_deadline_elapsed`) ;
2. relance les unités invitées sans réponse quand la date limite tombe dans les 48 h (`autoReminderDue()`), en
   respectant la limite d’une relance par unité et par 24 h partagée avec la relance manuelle (`canRemind()`) ;
3. prévient les responsables dont l’autorisation de partage expire dans les 12 h, une fois par personne et par 24 h
   (`consentExpiryNoticeDue()`).

Elle tourne avec les autres tâches planifiées. Pour la lancer seule :

```
# crontab
0 * * * * php /chemin/vers/athena/send-cooperation-reminders.php >> /var/log/athena-coop.log 2>&1

# ou entrée HTTP planifiée
/cron/run?key=<CRON_SECRET>&job=cooperation_reminders
```

## Recherche

`GET /back-office/cooperation/api/tenants/search?q=&mission_id=` et `/api/members/search?q=&mission_id=` : réponses
JSON minimales (jamais d’adresse e-mail), `Cache-Control: no-store`, 403 hors périmètre. Les unités déjà engagées sont
renvoyées avec `selectable: false` et leur état. Les membres proposés sont ceux de l’unité connectée uniquement.

## Tests

`tests/Unit/CooperationTransitionRulesTest.php`, `CooperationProgressTest.php`, `CooperationConsentTest.php`,
`CooperationNotificationsTest.php` et les tests d’assemblage `Cooperation*AssetTest.php`.
