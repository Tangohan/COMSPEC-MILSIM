# Description Steam Workshop — COMSPEC SSE

Page Workshop : https://steamcommunity.com/sharedfiles/filedetails/?id=3790566777

Texte prêt à coller dans la fiche Workshop, dans la charte de la page COMSPEC ATAK Native (3684656708) : bannière,
titres numérotés `01 · …` en `[h1]`, grille des fonctionnalités, vignettes, tableau des prérequis, FAQ avec
`[spoiler]`. Les images sont servies par Athena sous `/assets/workshop/sse/{fr,en}/` (dépôt :
`public/assets/workshop/sse`, en-têtes CORS pour Steam dans `docs/nginx.example.conf`).

- **Version FR** : description principale (langue par défaut de la fiche).
- **Version EN** : à coller dans la traduction anglaise de la fiche (Publisher ou page Steam › « Ajouter une traduction »).
- Ne **pas** joindre ce fichier au pack publié (`workshop-pack.ps1` l'exclut).
- **Dépendance** : sur la page Steam, ajouter à la main **COMSPEC ATAK Native (3684656708)** comme « Required item »
  (le Publisher ne le fait pas). CBA_A3 et ACE3 aussi.
- **Galerie** : images 1920 x 1080 produites par `tools/make_workshop_gallery.py` dans `docs/workshop/gallery/{fr,en}/`
  (voir `PACKAGING.md`, section « Galerie Workshop »).
- **À compléter avant publication** : la section `04 · NOUVEAUTÉS` (volontairement générique) avec le numéro de
  version et les nouveautés réelles de la mise à jour.

Variante de titres : les en-têtes graphiques `section-01-about.png` … `section-09-credits.png` et
`separateur.png` existent aussi (même dossier). Pour les utiliser, remplacer chaque `[h1]NN · …[/h1]` par
`[img]https://athena.ttrd.fr/assets/workshop/sse/fr/section-NN-….png[/img]` (07 = `section-07-missions.png`).

---

## Version BBCode (FR) — coller dans Steam

```
[img]https://athena.ttrd.fr/assets/workshop/sse/fr/banniere.png[/img]

[quote][b]Prérequis :[/b] Arma 3 2.14 ou plus récent, [url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url], [url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url] et [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708][b]COMSPEC ATAK Native[/b][/url] (obligatoire). COMSPEC Overwatch n'est plus nécessaire.[/quote]

[h1]01 · À PROPOS[/h1]
[b]COMSPEC SSE[/b] (Sensitive Site Exploitation) fait de chaque objectif une scène à exploiter. Fouiller les personnes, les corps, les objets et les véhicules, relever empreintes, iris, visage et ADN sur un terminal SEEK II, exploiter téléphones, ordinateurs et clés USB, mettre les pièces sous scellé et remonter le réseau jusqu'au chef.

Le renseignement collecté part vers [b]Athena[/b], le portail web COMSPEC : les fiches arrivent au Bureau SSE, rangées par affaire. Le chef de mission prépare tout dans Eden ou au Zeus, en quelques modules.

Gratuit, non commercial, en [b]français et en anglais[/b].

[h1]02 · FONCTIONNALITÉS[/h1]
[img]https://athena.ttrd.fr/assets/workshop/sse/fr/fonctionnalites.png[/img]
[list]
[*][b]Fouille et collecte[/b] - menu ACE sur les personnes, corps, objets et véhicules : inspecter, fouiller, photographier, lire les documents, collecter dans un sachet de preuve, révéler une cache.
[*][b]Biométrie SEEK II[/b] - empreintes (10 doigts), iris, photo du visage et ADN sur un vrai terminal. Qualité de capture réaliste (nuit, pluie, masque, blessures ACE), recherche 1:N, watchlist tiers 1 à 4, fausses alertes, empreintes latentes sur les armes et les objets.
[*][b]Identités uniques[/b] - chaque personnage a ses propres empreintes, iris et ADN ; un personnage récurrent garde les mêmes d'une mission à l'autre. Laboratoire ADN à résultat différé, avec recherche familiale.
[*][b]Exploitation numérique[/b] - téléphones, ordinateurs, clés USB et cartes SD : contacts, messages, appels, photos, coordonnées, documents, identifiants. Supports scénarisés (DOMEX) dont le contenu remonte à Athena.
[*][b]Câble DL-7[/b] - avec COMSPEC ATAK Native, branchez votre téléphone sur un support, trouvez ou forcez le mot de passe et téléchargez les fichiers préparés par le chef de mission.
[*][b]Chaîne de traçabilité[/b] - chaque pièce a son numéro, chaque scellé son journal (saisie, collecte, photo, ouverture, remise) ; journal infalsifiable, registre des scellés, remise des pièces à un équipier.
[*][b]Organisations et tableau des liens[/b] - réseaux, cellules, clans, milices avec rôles réalistes ; tableau des liens en graphe ou en ORBAT, soupçonné ou confirmé. Les joueurs découvrent le réseau en exploitant.
[*][b]Campagnes[/b] - permanente, récurrente (bootcamp) ou temporaire : personnages, organisations, ce que les joueurs savent et registre des scellés gardés d'une mission à l'autre, export et import en texte.
[*][b]Zeus et Eden[/b] - un site complet en un module, 24 modèles de génération, un scénario prêt à jouer (FALCON), attributs SSE sur chaque unité et objet, tableau de bord SSE dans Eden, exercice posé en un clic au Zeus.
[*][b]Liaison Athena[/b] - fiches personnes, biométrie et exploitation numérique transmises au Bureau SSE, rangées par référence d'affaire ; hors liaison, elles attendent dans une file. Campagnes copiées et partagées entre communautés.
[*][b]Guide intégré[/b] - manuel de terrain en jeu (menu ACE, Eden, Zeus) et guide complet en PDF livré avec le mod.
[/list]

[h1]03 · GALERIE[/h1]
[img]https://athena.ttrd.fr/assets/workshop/sse/fr/vignettes-seek.png[/img]
Plus d'images dans la galerie en haut de la page.

[h1]04 · NOUVEAUTÉS[/h1]
[b]Version 2.x[/b]
[list]
[*][b]COMSPEC ATAK Native[/b] devient obligatoire : SSE et ATAK forment un seul ensemble.
[*]Kits et sites préfabriqués pour Eden et Zeus.
[*]Les actions numériques passent par le terminal ATAK.
[/list]
Historique complet dans l'onglet [b]Notes de mise à jour[/b].

[h1]05 · PRÉREQUIS[/h1]
[table]
[tr][th]Mod[/th][th]Nécessaire ?[/th][/tr]
[tr][td]Arma 3[/td][td]Version 2.14 ou plus récente[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url][/td][td]Obligatoire[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url][/td][td]Obligatoire (menus d'interaction)[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url][/td][td]Obligatoire[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=1779063631]Zeus Enhanced (ZEN)[/url][/td][td]Optionnel (menus Zeus COMSPEC SSE · Mission et · Outils)[/td][/tr]
[tr][td]COMSPEC Overwatch[/td][td]Optionnel, n'est plus nécessaire pour Athena[/td][/tr]
[tr][td]BII Identifi[/td][td]Optionnel (passerelle activée s'il est chargé)[/td][/tr]
[/table]
Le mod doit tourner [b]aussi sur le serveur[/b] (identités, campagnes, registre des scellés, transmissions).

[h1]06 · INSTALLATION[/h1]
[olist]
[*]Abonnez-vous à ce mod : Steam ajoute CBA_A3, ACE3 et COMSPEC ATAK Native.
[*]Dans le lanceur Arma 3, activez CBA_A3, ACE3, COMSPEC ATAK Native puis COMSPEC SSE.
[*]Retirez les anciennes versions du SSE chargées en local, s'il y en a.
[*]Après une mise à jour, relancez Arma complètement, pas seulement la mission.
[/olist]
[b]Sur le terrain :[/b] menu ACE personnel › COMSPEC SSE › [b]Équiper le kit SSE[/b], puis menu ACE sur une personne ou un objet › [b]SSE[/b]. [b]Ctrl+Maj+S[/b] ouvre le terminal SEEK.

[h1]07 · POUR LES MISSION MAKERS[/h1]
Les modules sont dans Eden (Systèmes › Modules › [b]COMSPEC SSE[/b]) et au Zeus, rangés en cinq catégories.
[list]
[*][b]Sites et objets[/b] - créer un site SSE complet, générer un profil, rendre un objet exploitable, poser un objet de renseignement, appliquer un modèle, site d'entraînement.
[*][b]Personnes et liens[/b] - identité d'un sujet (réponse du SEEK), organisation, lier des entités.
[*][b]Mission et scénario[/b] - campagne, directeur de scénario (dataset FALCON, niveaux de révélation), dotation en terminal SEEK, génération depuis un brief, présentation des documents, référence d'affaire Athena.
[*][b]Numérique[/b] - support DOMEX, palier d'accès, point carte, données à extraire au câble DL-7.
[*][b]Outils du Zeus[/b] - panneau et gestionnaire de site, connu des joueurs ou vérité, compte rendu (AAR) avec graphe, enregistrer un modèle, tutoriel « poser un exercice ici ».
[/list]
Dans Eden, double-cliquez sur une unité ou un objet : catégories [b]COMSPEC SSE[/b] (renseignement, documents, téléphone, ordinateur, biométrie, organisation, extraction DL-7). Menu [b]COMSPEC SSE[/b] de la barre d'Eden : tableau de bord de la mission et manuel.
Réglages serveur : [b]Options › Réglages de l'addon › COMSPEC SSE[/b] (matériel exigé, durées, biométrie, organisations et scellés, campagnes, Athena).

[h1]08 · FAQ[/h1]
[b]Faut-il un compte Athena ?[/b]
Non. Tout le SSE fonctionne en jeu sans Athena. La liaison ajoute le Bureau SSE : dossiers, campagnes, exploitation numérique.
[spoiler]Les fiches sont rangées dans l'affaire indiquée par le module Référence de dossier SSE. Sans référence, elles vont dans la seule affaire ouverte, sinon dans une affaire du jour « à trier ».[/spoiler]

[b]Je n'ai aucune action biométrique.[/b]
Le serveur exige le matériel SSE : prenez le kit (menu ACE personnel › COMSPEC SSE › Équiper le kit SSE) ou demandez à décocher [i]Exiger le matériel SSE[/i].

[b]Pourquoi COMSPEC ATAK Native est-il obligatoire ?[/b]
Le câble DL-7, les notifications et la liaison avec Athena passent par lui. Il est gratuit et s'installe tout seul avec ce mod.

[b]Ma campagne ne se recharge pas.[/b]
Gardez le même identifiant de campagne d'une mission à l'autre. Pour changer de serveur, envoyez-la à Athena puis récupérez-la.

[h1]09 · CRÉDITS ET LIENS[/h1]
[list]
[*]Développé par [b]COMSPEC[/b].
[*]Construit sur [b]CBA_A3[/b] et [b]ACE3[/b], avec [b]COMSPEC ATAK Native[/b].
[*]Passerelle optionnelle vers [b]BII Identifi[/b] (S.O.A.R).
[/list]
Gratuit, non commercial, aucun contenu payant.

[url=https://athena.ttrd.fr]Portail Athena[/url]  ·  [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url]
```

---

## Version BBCode (EN) — paste into the English translation

```
[img]https://athena.ttrd.fr/assets/workshop/sse/en/banniere.png[/img]

[quote][b]Requirements:[/b] Arma 3 2.14 or newer, [url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url], [url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url] and [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708][b]COMSPEC ATAK Native[/b][/url] (required). COMSPEC Overwatch is no longer needed.[/quote]

[h1]01 · ABOUT THIS MOD[/h1]
[b]COMSPEC SSE[/b] (Sensitive Site Exploitation) turns every objective into a scene to exploit. Search people, bodies, objects and vehicles, take fingerprints, iris, face and DNA on a SEEK II terminal, exploit phones, computers and USB keys, seal the evidence and work your way up the network to its leader.

The intelligence you collect goes to [b]Athena[/b], the COMSPEC web portal: records reach the SSE office, filed by case. Mission makers set everything up in Eden or Zeus with a few modules.

Free, non-commercial, in [b]English and French[/b].

[h1]02 · FEATURES[/h1]
[img]https://athena.ttrd.fr/assets/workshop/sse/en/fonctionnalites.png[/img]
[list]
[*][b]Search and collect[/b] - ACE menu on people, bodies, objects and vehicles: inspect, search, photograph, read documents, collect into an evidence bag, reveal a cache.
[*][b]SEEK II biometrics[/b] - fingerprints (10 fingers), iris, face photo and DNA on a real terminal. Realistic capture quality (night, rain, mask, ACE wounds), 1:N search, tier 1 to 4 watchlist, false alarms, latent prints on weapons and objects.
[*][b]Unique identities[/b] - every character has their own fingerprints, iris and DNA; a recurring character keeps them from one mission to the next. DNA lab with delayed results and family search.
[*][b]Digital exploitation[/b] - phones, computers, USB keys and SD cards: contacts, messages, calls, photos, coordinates, documents, credentials. Scripted devices (DOMEX) whose content goes up to Athena.
[*][b]DL-7 cable[/b] - with COMSPEC ATAK Native, plug your phone into a device, find or crack the password and download the files the mission maker left there.
[*][b]Chain of custody[/b] - every item has its number, every seal its log (seizure, collection, photo, opening, hand-over); tamper-evident log, seal register, hand items over to a teammate.
[*][b]Organisations and link chart[/b] - networks, cells, clans, militias with realistic roles; link chart as a graph or ORBAT, suspected or confirmed. Players uncover the network by exploiting it.
[*][b]Campaigns[/b] - permanent, recurring (bootcamp) or temporary: characters, organisations, what players know and the seal register kept from one mission to the next, text export and import.
[*][b]Zeus and Eden[/b] - a full site in one module, 24 generation templates, a ready-to-play scenario (FALCON), SSE attributes on every unit and object, SSE dashboard in Eden, a training drill placed in one click in Zeus.
[*][b]Athena link[/b] - person, biometric and digital records sent to the SSE office, filed by case reference; without a link they wait in a queue. Campaigns copied and shared between communities.
[*][b]Built-in guide[/b] - in-game field manual (ACE menu, Eden, Zeus) and a full PDF guide shipped with the mod.
[/list]

[h1]03 · GALLERY[/h1]
[img]https://athena.ttrd.fr/assets/workshop/sse/en/vignettes-seek.png[/img]
More pictures in the gallery at the top of the page.

[h1]04 · WHAT'S NEW[/h1]
[b]Version 2.x[/b]
[list]
[*][b]COMSPEC ATAK Native[/b] is now required: SSE and ATAK work as one.
[*]Ready-made kits and sites for Eden and Zeus.
[*]Digital actions go through the ATAK terminal.
[/list]
Full history in the [b]Change Notes[/b] tab.

[h1]05 · REQUIREMENTS[/h1]
[table]
[tr][th]Mod[/th][th]Needed?[/th][/tr]
[tr][td]Arma 3[/td][td]Version 2.14 or newer[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url][/td][td]Required[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url][/td][td]Required (interaction menus)[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url][/td][td]Required[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=1779063631]Zeus Enhanced (ZEN)[/url][/td][td]Optional (COMSPEC SSE · Mission and · Tools Zeus menus)[/td][/tr]
[tr][td]COMSPEC Overwatch[/td][td]Optional, no longer needed for Athena[/td][/tr]
[tr][td]BII Identifi[/td][td]Optional (bridge enabled when loaded)[/td][/tr]
[/table]
The mod must run [b]on the server too[/b] (identities, campaigns, seal register, transmissions).

[h1]06 · INSTALLATION[/h1]
[olist]
[*]Subscribe to this mod: Steam adds CBA_A3, ACE3 and COMSPEC ATAK Native.
[*]In the Arma 3 Launcher, enable CBA_A3, ACE3, COMSPEC ATAK Native then COMSPEC SSE.
[*]Remove any old local copy of SSE.
[*]After an update, restart Arma completely, not just the mission.
[/olist]
[b]In the field:[/b] ACE self menu › COMSPEC SSE › [b]Equip SSE kit[/b], then ACE menu on a person or object › [b]SSE[/b]. [b]Ctrl+Shift+S[/b] opens the SEEK terminal.

[h1]07 · FOR MISSION MAKERS[/h1]
Modules are in Eden (Systems › Modules › [b]COMSPEC SSE[/b]) and in Zeus, in five categories.
[list]
[*][b]Sites and objects[/b] - create a full SSE site, generate a profile, make an object exploitable, place an intel object, apply a template, training site.
[*][b]People and links[/b] - subject identity (SEEK answer), organisation, link entities.
[*][b]Mission and scenario[/b] - campaign, scenario director (FALCON dataset, reveal levels), SEEK terminal issue, generate from a brief, document style, Athena case reference.
[*][b]Digital[/b] - DOMEX device, access stage, map point, data to extract with the DL-7 cable.
[*][b]Zeus tools[/b] - site panel and manager, known to players or ground truth, after-action report with graph, save a template, "place a drill here" tutorial.
[/list]
In Eden, double-click a unit or object: [b]COMSPEC SSE[/b] categories (intel, documents, phone, computer, biometrics, organisation, DL-7 extraction). [b]COMSPEC SSE[/b] menu in the Eden toolbar: mission dashboard and manual.
Server settings: [b]Options › Addon Options › COMSPEC SSE[/b] (required equipment, durations, biometrics, organisations and seals, campaigns, Athena).

[h1]08 · FAQ[/h1]
[b]Do I need an Athena account?[/b]
No. All of SSE works in game without Athena. The link adds the SSE office: case files, campaigns, digital exploitation.
[spoiler]Records are filed in the case set by the SSE case reference module. Without a reference, they go to the only open case, otherwise to a "to sort" case of the day.[/spoiler]

[b]I have no biometric actions.[/b]
The server requires SSE equipment: take the kit (ACE self menu › COMSPEC SSE › Equip SSE kit) or ask for [i]Require SSE equipment[/i] to be turned off.

[b]Why is COMSPEC ATAK Native required?[/b]
The DL-7 cable, notifications and the Athena link go through it. It is free and installs itself with this mod.

[b]My campaign does not reload.[/b]
Keep the same campaign ID from one mission to the next. To move to another server, send it to Athena then fetch it back.

[h1]09 · CREDITS & LINKS[/h1]
[list]
[*]Developed by [b]COMSPEC[/b].
[*]Built on [b]CBA_A3[/b] and [b]ACE3[/b], with [b]COMSPEC ATAK Native[/b].
[*]Optional bridge to [b]BII Identifi[/b] (S.O.A.R).
[/list]
Free, non-commercial, no paid content ever.

[url=https://athena.ttrd.fr]Athena portal[/url]  ·  [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url]
```
