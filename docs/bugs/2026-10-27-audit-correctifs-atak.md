# Correctifs de l’audit ATAK du 27 octobre 2026

## Contexte

Lecture du pack sur `release/2.7.0`. Les défauts ci-dessous ont été confirmés dans le code, puis corrigés. Aucun essai dans Arma 3 n’a été exécuté. PHPUnit n’est pas installé dans ce dépôt : `php -l` a validé les fichiers PHP touchés.

## SEC-041 — Identité du compte lisible par toute mission

- **Symptôme :** `GetAuthState` contenait le nom civil, l’e-mail, le numéro, l’IMEI et l’adresse matérielle.
- **Cause :** ces champs étaient recopiés dans la ligne lue par n’importe quel script.
- **Correctif :** ces cellules restent vides, y compris le type de numéro. `GetDeviceIdentity` répond `ERR|denied`. Le numéro du terminal, pas l’e-mail, est poussé une fois au téléphone (`PhoneHandset`) pour que l’appareil et les autres opérateurs affichent le même numéro.
- **Fichiers :** `GameAuth.cs`, `fn_extensionCallback.sqf`, `fn_phoneDeviceSync.sqf`.
- **Vérification :** lecture du format. Pas d’exécution Arma.
- **Statut :** corrigé dans les sources. Un script qui écoute tous les rappels de la liaison voit encore ce numéro de terminal. Il ne voit plus l’e-mail ni le nom civil.

## SEC-042 — Temps de jeu et distances des autres joueurs

- **Symptôme :** une session de joueur pouvait enregistrer le camp entier.
- **Cause :** l’API ne limitait les distances au joueur que si le corps disait `client`.
- **Correctif :** session de joueur = uniquement son Steam, côté site et côté liaison. Le serveur dédié, sans session de joueur, envoie toujours le camp. Sur un serveur hébergé par un joueur, l’envoi groupé est refusé et les téléphones prennent le relais.
- **Fichiers :** `AtakApiController.php`, `Extension.cs`, `fn_playTimeServer.sqf` (comportement déjà prévu en cas de refus).
- **Vérification :** lecture des deux chemins. Pas de partie jouée.
- **Statut :** corrigé dans les sources.

## SEC-044, SEC-045, SEC-046, DLL-001

- Avis d’administration : une session de joueur ne lit et ne valide que ses avis.
- Conditions réseau enregistrées sur le site : réservées à un gestionnaire, ou à la clé du serveur sans session de joueur. L’application en partie ne passe pas par cet enregistrement.
- Lien ouvert dans le navigateur : HTTPS du portail, et seulement les pages de liaison, de carte et de 9-LINE.
- `PathExists` et l’envoi de photo : chemins hors captures, musique et cache refusés, sans ouvrir un partage réseau.
- **Statut :** corrigé dans les sources. Pas d’essai sur un partage réel.

## ARCH-002, ARCH-003, ARCH-001

- Avec la liaison chargée, la carte native recopie les positions sans exiger que la DLL native soit `READY`.
- Sans liaison, l’état de session est découpé sur la tabulation.
- Le raccourci Overwatch ouvre le téléphone du pack quand il est chargé.
- **Statut :** corrigé dans les sources. Pas d’essai en jeu.

## Carte, IA, marqueurs, tablette, coupure

- Les icônes hors de la vue ne sont plus dessinées.
- Le serveur ne parcourt plus toutes les IA chaque seconde quand aucune n’a de téléphone.
- Quand le relais serveur des marqueurs est actif, les gestionnaires du démarrage, le passage toutes les 2 s sur les repères `_…`, l’envoi au clic carte et le rappel des points d’objectif n’envoient plus. Le serveur reprend aussi les repères déjà posés. Le nom de l’auteur est publié pour que le serveur puisse l’afficher. Les repères purement locaux (bâtiment ECOTI) restent envoyés par le poste.
- L’ancienne tablette libère les adresses d’image et borne ses magasins. Un tracé effacé n’est plus rechargé.
- La pause choisie par la liaison reste à 90 secondes. Un délai demandé par le portail est respecté jusqu’à 10 minutes. La pause d’une seule adresse reste plafonnée à 5 minutes.
- **Statut :** corrigé dans les sources. Aucun chiffre de fluidité mesuré.

## Non corrigé, volontairement

- Contrainte unique des fiches d’effectifs : elle exige de fusionner d’abord les doublons déjà en base.
- Rejeu d’un lot coupé après écriture : changement de contrat plus large.
- Un joueur du camp peut encore remplacer le plan partagé : c’est le téléphone officiel qui l’envoie. Il ne peut plus écrire le brouillon personnel d’un autre.
- Pas de mesure Arma, pas de banc de charge.
