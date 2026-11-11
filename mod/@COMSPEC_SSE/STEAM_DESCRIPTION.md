# Description Steam Workshop — COMSPEC SSE

Page Workshop : https://steamcommunity.com/sharedfiles/filedetails/?id=3790566777

Texte prêt à coller dans la fiche Workshop, dans la charte de la page COMSPEC ATAK Native (3684656708) : bannière,
titres numérotés `01 · …` en `[h1]`, grille des fonctionnalités, vignettes, tableau des prérequis, FAQ avec
`[spoiler]`. Les images sont servies par Athena sous `/assets/workshop/sse/{fr,en}/` (dépôt :
`public/assets/workshop/sse`, en-têtes CORS pour Steam dans `docs/nginx.example.conf`).

- **Version FR** : description principale (langue par défaut de la fiche).
- **Version EN** : à coller dans la traduction anglaise de la fiche (Publisher ou page Steam › « Ajouter une traduction »).
- **Longueur** : Steam refuse une description de plus de 8 000 caractères (compter en octets UTF-8 : un caractère
  accentué en vaut deux). Chaque bloc ci-dessous est collé seul et doit rester sous cette limite (état SSE 1.8 :
  environ 7 450 octets pour le FR, 6 850 pour l'EN).
- Ne **pas** joindre ce fichier au pack publié (`workshop-pack.ps1` l'exclut).
- **Dépendance** : sur la page Steam, ajouter à la main **COMSPEC ATAK Native (3684656708)** comme « Required item »
  (le Publisher ne le fait pas). CBA_A3 et ACE3 aussi.
- **Galerie** : images 1920 x 1080 produites par `tools/make_workshop_gallery.py` dans `docs/workshop/gallery/{fr,en}/`
  (voir `PACKAGING.md`, section « Galerie Workshop »).
- **Section `04 · NOUVEAUTÉS`** : à tenir à jour à chaque version (4 à 6 puces, sans numéro de version ; l'historique
  complet est dans l'onglet Notes de mise à jour de Steam).

Variante de titres : les en-têtes graphiques `section-01-about.png` … `section-09-credits.png` et
`separateur.png` existent aussi (même dossier). Pour les utiliser, remplacer chaque `[h1]NN · …[/h1]` par
`[img]https://athena-comspec.eu/assets/workshop/sse/fr/section-NN-….png[/img]` (07 = `section-07-missions.png`).

---

## Version BBCode (FR) — coller dans Steam

```
[img]https://athena-comspec.eu/assets/workshop/sse/fr/banniere.png[/img]

[quote][b]Prérequis :[/b] Arma 3 2.14 ou plus récent, [url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url], [url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url] et [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708][b]COMSPEC ATAK Native[/b][/url] (obligatoire). COMSPEC Overwatch n'est plus nécessaire.[/quote]

[h1]01 · À PROPOS[/h1]
[b]COMSPEC SSE[/b] (Sensitive Site Exploitation) fait de chaque objectif une scène à exploiter. Contrôlez les papiers, parlez aux civils, fouillez personnes, corps, objets et véhicules, relevez empreintes, iris, visage et ADN sur un terminal SEEK II, exploitez téléphones et ordinateurs, mettez les pièces sous scellé et remontez le réseau jusqu'au chef.

Le renseignement part vers [b]Athena[/b], le portail web COMSPEC, rangé par affaire. Le chef de mission prépare tout dans Eden ou au Zeus.

Gratuit, non commercial, en [b]français et en anglais[/b].

[h1]02 · FONCTIONNALITÉS[/h1]
[img]https://athena-comspec.eu/assets/workshop/sse/fr/fonctionnalites.png[/img]
[list]
[*][b]Fouille et collecte[/b] - inspecter, fouiller, photographier, lire les documents trouvés, collecter dans un sachet de preuve, révéler une cache.
[*][b]Papiers d'identité[/b] - carte d'identité, passeport, permis, carte militaire, proches des vrais, avec photo du titulaire. Vrais, faux ou couverture. D'autres types (carte de presse, laissez-passer…) par extensions.
[*][b]Contrôle d'identité[/b] - demander les papiers au menu ACE, les examiner, lire la bande MRZ et la puce, interroger le fichier. Checkpoints IA et couvre-feu.
[*][b]Fichiers[/b] - app du téléphone pour vérifier une personne, un document ou une plaque, et retrouver les relevés SEEK.
[*][b]Parler aux IA[/b] - en texte libre (Ctrl+T). Elles répondent selon leur caractère et ce qu'elles savent, peuvent mentir, avouer ou fuir quand on les confronte, et ont peur d'une arme braquée. Ordres simples compris.
[*][b]Couvertures et infiltrés[/b] - jouez sous une fausse identité ; tenue, arme ou mauvaise réponse peuvent la faire tomber. HVT et avis BOLO.
[*][b]Biométrie SEEK II[/b] - empreintes, iris, visage et ADN, qualité de capture réaliste, verdict clair (concordant, discordant, inconnu, signalé), empreintes latentes.
[*][b]Identités uniques[/b] - chaque personnage a ses empreintes, son iris et son ADN, gardés d'une mission à l'autre ; laboratoire ADN à résultat différé.
[*][b]Exploitation numérique[/b] - téléphones, ordinateurs, clés USB : contacts, messages, photos, fichiers. Avec l'ATAK, branchez le câble DL-7 et forcez le mot de passe.
[*][b]Chaîne de traçabilité[/b] - chaque pièce a son numéro, chaque scellé son journal ; registre des scellés, remise à un équipier.
[*][b]Réseaux et campagnes[/b] - organisations, cellules, tableau des liens ; personnages et scellés gardés d'une mission à l'autre.
[*][b]Guides[/b] - manuel en jeu avec recherche, bouton « ? » dans chaque fenêtre, guides complets livrés avec le mod.
[/list]

[h1]03 · GALERIE[/h1]
[img]https://athena-comspec.eu/assets/workshop/sse/fr/vignettes-seek.png[/img]
Plus d'images dans la galerie en haut de la page.

[h1]04 · NOUVEAUTÉS[/h1]
[list]
[*][b]IA plus bavardes[/b] : elles comprennent trois fois plus de questions (présence, renseignement, village, ordres comme « Lâchez votre arme »). L'onglet GUIDE de « Parler à » les montre par thème.
[*][b]Une IA ne parle que de ce qu'elle sait[/b] : un passant qui n'a rien vu le dit, sans inventer.
[*][b]Réponses écrites par le maître du jeu[/b], dans Eden ou en Zeus : selon que l'IA est calme, a peur ou est menacée, avec son prénom ou sa ville. Une version plus simple de cette fenêtre arrive.
[*][b]Papiers réalistes[/b] avec vraie photo, quinze styles de feuilles pour les documents trouvés, app Fichiers.
[*][b]Zeus[/b] : clic droit sur des IA pour changer caractère, papiers ou attitude ; le Zeus voit leur vrai nom et si leurs papiers sont faux.
[/list]
Historique complet dans l'onglet [b]Notes de mise à jour[/b].

[h1]05 · PRÉREQUIS[/h1]
[table]
[tr][th]Mod[/th][th]Nécessaire ?[/th][/tr]
[tr][td]Arma 3[/td][td]2.14 ou plus récent[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url][/td][td]Obligatoire[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url][/td][td]Obligatoire[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url][/td][td]Obligatoire[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=1779063631]Zeus Enhanced (ZEN)[/url][/td][td]Optionnel (modules Zeus)[/td][/tr]
[tr][td]BII Identifi[/td][td]Optionnel (passerelle)[/td][/tr]
[/table]
Le mod doit tourner [b]aussi sur le serveur[/b].

[h1]06 · INSTALLATION[/h1]
[olist]
[*]Abonnez-vous à ce mod : Steam ajoute CBA_A3, ACE3 et COMSPEC ATAK Native.
[*]Activez-les dans le lanceur, puis COMSPEC SSE.
[*]Après une mise à jour, relancez Arma complètement.
[/olist]
[b]Sur le terrain :[/b] menu ACE personnel › COMSPEC SSE › [b]Équiper le kit SSE[/b], puis menu ACE sur une personne ou un objet. [b]Ctrl+Maj+S[/b] ouvre l'outil SSE, [b]Ctrl+T[/b] parle à l'IA en face.

[h1]07 · POUR LES MISSION MAKERS[/h1]
Modules dans Eden (Systèmes › Modules) et au Zeus, en trois catégories :
[list]
[*][b]Renseignement[/b] - site complet en un module, 24 modèles, scénario prêt à jouer (FALCON), organisations, Éditeur SSE (renseignement, identité, papier, dossier, kit).
[*][b]Identité[/b] - donner des papiers, checkpoint IA, couverture, HVT, BOLO, voir les vrais papiers.
[*][b]Mission[/b] - campagne, dossier Athena, présentation des documents.
[/list]
[b]Atelier IA[/b] (Eden et Zeus) : caractère, histoire, secrets, ce qu'elle sait, réponses et thèmes interdits, gardés pour les parties suivantes. Double-clic sur une unité ou un objet : attributs [b]COMSPEC SSE[/b]. Réglages : [b]Options › Réglages de l'addon › COMSPEC SSE[/b].

[h1]08 · FAQ[/h1]
[b]Faut-il un compte Athena ?[/b]
Non. Tout fonctionne en jeu sans Athena. La liaison ajoute le Bureau SSE : dossiers, campagnes, fichiers, vos propres papiers.

[b]Je n'ai aucune action biométrique.[/b]
Prenez le kit (menu ACE personnel › COMSPEC SSE › Équiper le kit SSE) ou demandez à décocher [i]Exiger le matériel SSE[/i].

[b]Pourquoi COMSPEC ATAK Native est-il obligatoire ?[/b]
Les apps Contrôle ID et Fichiers, le câble DL-7 et la liaison Athena passent par lui. Il s'installe tout seul avec ce mod.

[h1]09 · CRÉDITS ET LIENS[/h1]
Développé par [b]COMSPEC[/b], sur [b]CBA_A3[/b] et [b]ACE3[/b], avec [b]COMSPEC ATAK Native[/b]. Passerelle optionnelle vers [b]BII Identifi[/b] (S.O.A.R). Gratuit, aucun contenu payant.

[b]Licence :[/b] © COMSPEC, tous droits réservés. Usage personnel via cette page officielle du Workshop. Interdit sans autorisation écrite : copier, ré-héberger, redistribuer, ré-empaqueter, modifier, décompiler ou extraire le code, les images ou la DLL, tout usage commercial. Texte complet : LICENSE.txt dans le dossier du mod.

[url=https://athena-comspec.eu]Portail Athena[/url]  ·  [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url]
```

---

## Version BBCode (EN) — paste into the English translation

```
[img]https://athena-comspec.eu/assets/workshop/sse/en/banniere.png[/img]

[quote][b]Requirements:[/b] Arma 3 2.14 or newer, [url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url], [url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url] and [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708][b]COMSPEC ATAK Native[/b][/url] (required). COMSPEC Overwatch is no longer needed.[/quote]

[h1]01 · ABOUT THIS MOD[/h1]
[b]COMSPEC SSE[/b] (Sensitive Site Exploitation) turns every objective into a scene to exploit. Check papers, talk to civilians, search people, bodies, objects and vehicles, take fingerprints, iris, face and DNA on a SEEK II terminal, exploit phones and computers, seal the evidence and work your way up the network to its leader.

The intelligence goes to [b]Athena[/b], the COMSPEC web portal, filed by case. Mission makers set everything up in Eden or Zeus.

Free, non-commercial, in [b]English and French[/b].

[h1]02 · FEATURES[/h1]
[img]https://athena-comspec.eu/assets/workshop/sse/en/fonctionnalites.png[/img]
[list]
[*][b]Search and collect[/b] - inspect, search, photograph, read documents you find, collect into an evidence bag, reveal a cache.
[*][b]Identity papers[/b] - ID card, passport, driving licence, military ID, close to real ones, with the holder's photo. Genuine, forged or cover. More types (press card, pass…) through extensions.
[*][b]ID checks[/b] - ask for papers from the ACE menu, examine them, read the MRZ strip and chip, query the records. AI checkpoints and curfew.
[*][b]Records[/b] - phone app to check a person, a document or a plate, and find SEEK results.
[*][b]Talk to AIs[/b] - in free text (Ctrl+T). They answer from their character and what they know, may lie, confess or run when confronted, and fear a weapon aimed at them. Simple orders understood.
[*][b]Covers and undercover play[/b] - play under a false identity; clothes, a weapon or a wrong answer can blow it. HVTs and BOLO notices.
[*][b]SEEK II biometrics[/b] - fingerprints, iris, face and DNA, realistic capture quality, clear verdict (match, mismatch, unknown, flagged), latent prints.
[*][b]Unique identities[/b] - every character has their own fingerprints, iris and DNA, kept from one mission to the next; DNA lab with delayed results.
[*][b]Digital exploitation[/b] - phones, computers, USB keys: contacts, messages, photos, files. With the ATAK, plug in the DL-7 cable and crack the password.
[*][b]Chain of custody[/b] - every item has its number, every seal its log; seal register, hand-over to a teammate.
[*][b]Networks and campaigns[/b] - organisations, cells, link chart; characters and seals kept from one mission to the next.
[*][b]Guides[/b] - searchable in-game manual, a « ? » button in every window, full guides shipped with the mod.
[/list]

[h1]03 · GALLERY[/h1]
[img]https://athena-comspec.eu/assets/workshop/sse/en/vignettes-seek.png[/img]
More pictures in the gallery at the top of the page.

[h1]04 · WHAT'S NEW[/h1]
[list]
[*][b]Chattier AIs[/b]: they understand three times more questions (presence, intel, the village, orders such as "Drop your weapon"). The GUIDE tab of "Talk to" lists them by theme.
[*][b]An AI only talks about what it knows[/b]: a passer-by who saw nothing says so, without making things up.
[*][b]Answers written by the game master[/b], in Eden or Zeus: depending on whether the AI is calm, scared or threatened, with its first name or town. A simpler version of this window is on the way.
[*][b]Realistic papers[/b] with a real photo, fifteen paper styles for found documents, Records app.
[*][b]Zeus[/b]: right-click AIs to change their character, papers or attitude; Zeus sees their real name and whether their papers are forged.
[/list]
Full history in the [b]Change Notes[/b] tab.

[h1]05 · REQUIREMENTS[/h1]
[table]
[tr][th]Mod[/th][th]Needed?[/th][/tr]
[tr][td]Arma 3[/td][td]2.14 or newer[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=450814997]CBA_A3[/url][/td][td]Required[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=463939057]ACE3[/url][/td][td]Required[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url][/td][td]Required[/td][/tr]
[tr][td][url=https://steamcommunity.com/sharedfiles/filedetails/?id=1779063631]Zeus Enhanced (ZEN)[/url][/td][td]Optional (Zeus modules)[/td][/tr]
[tr][td]BII Identifi[/td][td]Optional (bridge)[/td][/tr]
[/table]
The mod must run [b]on the server too[/b].

[h1]06 · INSTALLATION[/h1]
[olist]
[*]Subscribe to this mod: Steam adds CBA_A3, ACE3 and COMSPEC ATAK Native.
[*]Enable them in the Launcher, then COMSPEC SSE.
[*]After an update, restart Arma completely.
[/olist]
[b]In the field:[/b] ACE self menu › COMSPEC SSE › [b]Equip SSE kit[/b], then ACE menu on a person or object. [b]Ctrl+Shift+S[/b] opens the SSE tool, [b]Ctrl+T[/b] talks to the AI in front of you.

[h1]07 · FOR MISSION MAKERS[/h1]
Modules in Eden (Systems › Modules) and in Zeus, in three categories:
[list]
[*][b]Intelligence[/b] - a full site in one module, 24 templates, a ready-to-play scenario (FALCON), organisations, SSE editor (intel, identity, paper, case file, kit).
[*][b]Identity[/b] - give papers, AI checkpoint, cover, HVT, BOLO, see the real papers.
[*][b]Mission[/b] - campaign, Athena case, document style.
[/list]
[b]AI workshop[/b] (Eden and Zeus): character, story, secrets, what it knows, answers and forbidden themes, kept for later games. Double-click a unit or object: [b]COMSPEC SSE[/b] attributes. Settings: [b]Options › Addon Options › COMSPEC SSE[/b].

[h1]08 · FAQ[/h1]
[b]Do I need an Athena account?[/b]
No. Everything works in game without Athena. The link adds the SSE office: case files, campaigns, records, your own papers.

[b]I have no biometric actions.[/b]
Take the kit (ACE self menu › COMSPEC SSE › Equip SSE kit) or ask for [i]Require SSE equipment[/i] to be turned off.

[b]Why is COMSPEC ATAK Native required?[/b]
The ID Check and Records apps, the DL-7 cable and the Athena link go through it. It installs itself with this mod.

[h1]09 · CREDITS & LINKS[/h1]
Developed by [b]COMSPEC[/b], on [b]CBA_A3[/b] and [b]ACE3[/b], with [b]COMSPEC ATAK Native[/b]. Optional bridge to [b]BII Identifi[/b] (S.O.A.R). Free, no paid content ever.

[b]Licence:[/b] © COMSPEC, all rights reserved. Personal use through this official Workshop page. Not allowed without written permission: copying, re-uploading, redistributing, repacking, modifying, decompiling or extracting the code, images or DLL, any commercial use. Full text: LICENSE.txt in the mod folder.

[url=https://athena-comspec.eu]Athena portal[/url]  ·  [url=https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708]COMSPEC ATAK Native[/url]
```
