# Sessions de jeu : code de session, notifications en jeu, app Session

Page : `/back-office/roleplay/sessions/{id}` (fiche d'une session). Contrat de l'app du téléphone :
[`docs/technical/ICD-SESSION-JEU.md`](../technical/ICD-SESSION-JEU.md).

## État des lieux (avant ce lot)

**Page et service.** `RoleplayGameSessionAdminController` (liste, fiche, création, modification, état, participants) ;
droits : `AuthMiddleware` + `OrganizationAdminMiddleware` et `guardBackOfficeRight` (gestionnaires de la communauté).
Une session (`roleplay_game_sessions`) est `discovered | open | closed | cancelled` ; la fiche la classe « à venir / en cours /
passée » (`RoleplaySessionBoard::phase` : démarrée = `started_at` posé). Actions : démarrer, clôturer, annuler, rouvrir
(`RoleplayGameSessionService::startSession` / `closeSession`). Participants (`roleplay_game_session_members`, un par compte) :
temps brut, temps retenu, statut `absent | declared | detected | confirmed`, présence retenue ou écartée à la main, ajout manuel.
Temps de présence par plages (`roleplay_game_session_segments`, motifs `join, reconnect, heartbeat_timeout, mission_end,
leave, check_in, check_out, staff`) ; `qualifyMember` applique les seuils (minutes ou % de la session, tolérance de retard).
Règles de la communauté : `RoleplayGameSessionSettings` (catégories d'heures, seuils, délai de signe de vie, réception depuis
le jeu, seuils des sessions joueur `player_sessions`).

**API jeu existante.** `POST /api/atak/game-session/{discover,join,heartbeat,check-in,check-out,leave}` (clé ATAK + Steam,
`RoleplayGameSessionService`) : rattache le temps de jeu à la session démarrée en cours ; aucune route de lecture pour le
joueur, aucun code. `POST /api/atak/server-presence` (toutes les 10 min) remplit `tenant_game_server_presence` et prolonge
`player_sessions`. Aucune notification Discord ou site propre aux sessions.

**Canal vers les téléphones.** `atak_web_commands` (`AtakWebCommandRepository`), relu par le sondage des charges ; kind
`phone` / cmd `notify` du poste TOC web arrive dans la boîte du téléphone et l'app Alertes (`fn_tocPhone`), sans alerte à
l'écran. Réutilisé tel quel.

**Côté mod (lecture seule).** Pas d'app Session, pas d'appel aux routes `game-session/*` dans
`mod/COMSPEC_ATAK_Native/Sources/addons/main` ; « pointage » n'y désigne que le pointage de carte / satellite et « check-in »
la fiche JTAC. `fn_serverPresence` envoie la présence serveur ; `fn_tocPhone` traite `notify` (texte codé, niveaux i/a/u).

## Ce que ce lot ajoute

- **Code de session** (`SessionJoinCode`, `RoleplaySessionCodeService`) : format, cycle de vie, régénération, révocation,
  expiration (tâche `roleplay_session_app`, toutes les 5 min). Bande « Code de session » avec Copier, Afficher en grand
  (QR code par `QrPngGenerator`), Régénérer, Révoquer (fenêtres de confirmation) ; code en grand dans la bande d'en-tête et
  dans la liste des sessions.
- **API de l'app Session** : `GET /api/atak/session/current`, `POST /api/atak/session/{join,checkin,leave}`.
- **Notifications en jeu** (`RoleplaySessionNotifier`) : annonce, rappels T-15 / T-5, début, reprise, horaire modifié,
  appel à pointer, fin, annulation, test ; réglages et consignes par session.
- **Joueurs ATAK connectés** (`RoleplaySessionLiveRoster`) : liste en direct (liaison du téléphone + présence serveur),
  pointage par sélection, de tous les connectés ou par ligne, retrait ; « Pourquoi je ne le vois pas ? ».
- **Enregistrement** (`RoleplaySessionAttendance`) : « Démarrer l'enregistrement » / « Terminer l'enregistrement » avec
  récapitulatif ; le temps des joueurs pointés court dès le démarrage (ou dès leur pointage pendant l'enregistrement).
- **Migration** `4920_roleplay_session_app` : `roleplay_game_session_codes`, `roleplay_game_session_app`,
  `roleplay_game_session_journal` (unicités dans les CREATE TABLE, `utf8mb4_unicode_ci`, retour arrière
  `migrations/rollback/roleplay_session_app_down.sql`). Sans elles : page utilisable sans code ni notification, API
  `feature_unavailable`.

## Points d'attention

- Dates : les tables des sessions et les nouvelles tables sont écrites à l'heure PHP (`date()`) ; `atak_units.updated_at` et
  `tenant_game_server_presence.seen_at` sont écrits en UTC par MySQL : `RoleplaySessionLiveRoster` les relit en UTC.
- Une session « prévue » démarrée par l'ancien bouton « Démarrer » reste possible (même service) ; le bouton de la bande est
  désormais « Démarrer l'enregistrement », qui ouvre en plus le temps des joueurs déjà pointés.
- Diffusion `*` : une ligne par événement, durée de vie courte, pour ne jamais occuper les 8 places de la file des
  notifications du poste TOC plus de quelques minutes.
