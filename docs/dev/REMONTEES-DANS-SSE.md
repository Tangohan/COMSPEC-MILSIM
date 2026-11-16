# Remontées : du back-office au bureau SSE

Toutes les pages des remontées du terrain (comptes rendus et fiches de renseignement) vivent désormais dans le bureau
SSE (`/atak/sse/remontees/*`) : même coque, même menu, thème sombre « bureau », bandeau de classification, droits du
portail. Les anciennes adresses du back-office renvoient vers leur équivalent. Aucune migration : mêmes tables, mêmes
services (`IntelIntakeDesk`, `IntelIntakeService`, `IntelIntakeDiscord`, `IntelPlayerFeedService`), même contrat des API.

## Inventaire avant le passage

| Ancienne adresse (back-office) | Rôle | Droit d'accès |
|---|---|---|
| `GET /back-office/remontees` | liste (onglets d'état, source, type, traitant, pastilles, tri) | `AdminIntelIntakeController::allowed()` : `admin.system`, `admin.organization`, `admin.access`, `atak.sse.case.manage` ou `atak.sse.grant`, sous la règle SSE « dossiers › écrire » |
| `GET /back-office/remontees/joueurs` | tableau par joueur | idem |
| `GET /back-office/remontees/joueurs/{key}` | fil d'un joueur | idem |
| `GET /back-office/remontees/{source}/{id}` | fil d'une remontée (`cr` ou `fiche`) | idem |
| `GET /back-office/remontees/{source}/{id}/suggestions` | listes des fenêtres (JSON) | idem |
| `POST /back-office/remontees/{source}/{id}/{action}` | take, comment, state, assign, type, themes, edit, blur, delete, restore, redact, unredact, steam, convert, link, discord | idem ; caviarder : plus haute habilitation ; Steam : administrateurs |
| `POST /back-office/discord-bot/remontees` | notifications Discord des remontées (page « Bot Discord ») | `admin.organization` |

Liens qui menaient au back-office : menu latéral (`views/partials/ath_sidebar_nav.php`, `config/navigation.php`),
tuile « Remontées terrain » du tableau de bord (`views/partials/dashboard_aside.php`, badge « à traiter »),
recherche du back-office (`BackOfficeSearchService`), raccourcis de « Suivi des CR » (`config/back_office_pages.php`),
comptes rendus post-op et routage des CR (`views/admin/aar_reports/*`, `atak_debrief`, `atak_report_routing`),
inspecteur de données (`views/atak/data_inspector.php`), fil d'un joueur (`IntelPlayerFeedService`), message Discord
(`IntelIntakeDiscord::message()['link']`), onglet « Suivi » d'une fiche (`_intake_suivi.php`, `field_note_show.php`),
cours Athena du back-office. Pas de mail ni de lien dans les tickets vers ces pages.

Déjà au bureau avant le passage : `/atak/sse/remontees` (file allégée), `/atak/sse/remontees/cr/{id}` (compte rendu),
onglet « Suivi » d'une fiche (`SseFieldNoteController::show`, `SseIntakeController::sessionRights`). « Transmissions
terrain » (`/atak/sse/transmissions`) est une autre page (envois reçus d'Arma 3) : elle n'est pas touchée.

## Pages du bureau

| Page | Adresse | Contenu |
|---|---|---|
| File | `/atak/sse/remontees` | bande d'en-tête (à traiter, en retard, traitées cette semaine, ouvertes, à moi), onglets des vues, filtres état/source/type/traitant, pastilles (retard, urgence…), tri, cartes sur téléphone |
| Fil d'une remontée | `/atak/sse/remontees/{source}/{id}` (même identifiant) | cycle et délais, contenu sous bandeau de classification, pièces jointes, fil d'activité, onglets Traitement/Émetteur/Liens/Historique/Zone sensible, actions en fenêtres, « Rattacher à… » (fenêtre du bureau) pour une fiche |
| Par joueur | `/atak/sse/remontees/joueurs`, `/joueurs/{key}` | tableau et fil chronologique |
| Réglages | `/atak/sse/remontees/reglages` | délais cibles, états de la file, notifications Discord (fenêtre, mêmes champs `intake_*`) |

Menu : « Renseignement et enquêtes › Remontées » (badge « à traiter » via la boîte À trier, file `remontees`) et
« Remontées par joueur » ; « Outils et réglages › Réglages des remontées ». Chaque action réussie est notée dans
`sse_audit_log` (`intake.<action>`, référence, jamais le texte). Une pièce floutée ne montre pas son adresse à qui ne
gère pas les remontées.

## Redirections

| Ancienne adresse | Statut | Nouvelle adresse |
|---|---|---|
| `GET /back-office/remontees?…` | 302 | `/atak/sse/remontees?…` (filtres conservés) |
| `GET /back-office/remontees/joueurs?…` | 302 | `/atak/sse/remontees/joueurs?…` |
| `GET /back-office/remontees/joueurs/{key}` | 302 | `/atak/sse/remontees/joueurs/{key}` |
| `GET /back-office/remontees/{source}/{id}` | 302 | `/atak/sse/remontees/{source}/{id}` |
| `GET /back-office/remontees/{source}/{id}/suggestions` | 302 | même chemin au bureau |
| `POST /back-office/remontees/{source}/{id}/{action}` | 307 | même chemin au bureau (corps, jeton et noms de champs conservés) |

302 et non 301 : la réponse dépend de la session. Sans entrée possible au bureau (ni session SSE active, ni entrée
sans code), l'ancienne adresse affiche « Accès au bureau SSE requis » (403) avec le bouton d'entrée, au lieu du sas ou
d'une page vide. La page « Bot Discord » du back-office garde son formulaire et pointe vers les réglages du bureau.

## Droits : ancienne → nouvelle

| Droit | Back-office (avant) | Bureau SSE (maintenant) |
|---|---|---|
| Ouvrir, traiter (prendre en compte, commenter, état, transmettre, doublon, attribuer, type, thèmes), notifier Discord | ancien droit | ancien droit **ou** « terrain › gérer » |
| Convertir | ancien droit | ancien droit **ou** « terrain › gérer » + « dossiers › écrire » |
| Relier / rattacher une fiche | ancien droit | ancien droit **ou** « terrain › gérer » + « terrain › écrire » |
| Modifier les données, flouter, supprimer, restaurer, voir les supprimées | ancien droit | ancien droit (inchangé) |
| Caviarder, lever un caviardage | ancien droit + habilitation « Très secret (TS) » | inchangé |
| Lire l'identifiant Steam | `admin.organization`, `admin.access`, `admin.system` | inchangé (et ancien droit) |
| Notifications Discord (réglage) | `admin.organization` | inchangé |
| Session invitée (code d'accès) | sans objet | aucun droit |

« Ancien droit » = `AdminIntelIntakeController::allowed()`, conservé tel quel. En plus, entrer au bureau SSE est
nécessaire (session SSE active ou entrée sans code) : sinon la page « Accès au bureau SSE requis ».
