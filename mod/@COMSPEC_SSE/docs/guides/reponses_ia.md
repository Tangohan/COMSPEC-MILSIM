# Parler aux IA : questions comprises, réponses du maître du jeu, ce qu'une IA peut évoquer

Version anglaise : [reponses_ia.en.md](reponses_ia.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Les joueurs parlent aux IA en texte libre (interaction ACE › **Parler à…**, ou Ctrl+T). L'IA comprend environ **120 sortes de questions**, en français et en anglais, fautes et tutoiement compris, et répond selon son caractère, sa couverture et ce qu'elle sait. Le maître du jeu peut, question par question, garder cette **réponse automatique** ou écrire **sa réponse** (« Ma réponse »), et fixer **ce que l'IA peut évoquer**.

Règle d'or : **une IA ne parle que de ce qu'elle sait**. Un passant sans savoir ne parle jamais d'armes, de groupes armés, de pick-up, de mines ou de caches : « Non, je n'ai rien vu de tel. », « Je ne sais pas, je viens d'arriver. »

## Pour faire parler une IA : écrivez une question, écrivez sa réponse

1. Eden : attributs de l'unité › **COMSPEC SSE · IA RP** › **Question** : ce que le joueur peut lui demander, avec ses mots. Zeus : clic droit › **SSE · Roleplay de l'IA › Profil d'IA › Question et réponse…** (les mêmes deux cases sont aussi en tête de l'**Atelier IA**).
2. **Réponse de l'IA** : ce qu'elle répond (une ligne par réponse possible ; **+ Variable** ou `{prenom}`, `{ville}`, `{joueur}`… pour les mots remplacés en jeu).
3. C'est tout : les formulations proches marchent aussi. Exemple : Question « Où est le chef du village ? », Réponse « Le chef est à la mosquée, il revient ce soir. » ; en jeu, « Le chef du village, il est où ? » donne cette réponse.

Ces deux cases sont la première de « vos propres questions » : d'autres questions, une réponse selon la situation ou un renseignement révélé se règlent dans **Toutes les questions et réponses…** (ci-dessous). **Options avancées** (dans l'atelier) garde **Ce qu'elle sait** (un secret, une info utile ; anciennement « Savoir ») et **Quand elle le dit** (en confiance, pot-de-vin, au mot-clé, jamais ; anciennement « Déblocage ») : les missions déjà faites gardent leurs valeurs. « Si ordre » s'appelle maintenant **Face à un ordre** (ce qu'elle fait à « Les mains en l'air ! », « Suivez-moi »).

## Ce que les joueurs peuvent demander

L'onglet **GUIDE** de la fenêtre « Parler à » liste tout, par thème, avec des exemples cliquables :

| Thème | Exemples |
|---|---|
| Saluer, répéter | Bonjour · Tu peux répéter ? · Vous comprenez ? |
| Identité et papiers | Qui es-tu ? · Quel âge avez-vous ? · Ton numéro ? · Tu parles anglais ? · Vos papiers |
| Couverture | D'où venez-vous ? · Pour qui travaillez-vous ? · Où êtes-vous né ? |
| Présence | Depuis quand êtes-vous ici ? · Vous êtes seul ? · Où étiez-vous hier soir ? · Qu'est-ce que vous transportez ? |
| Fouille, ordres | Je vais vous fouiller · Ouvrez votre sac · Lâchez votre arme ! · Reculez · Asseyez-vous · Taisez-vous |
| Renseignement | Avez-vous vu des hommes armés ? · Des véhicules ? · Des étrangers ? · Des mines sur la route ? · Qui commande ici ? · Combien étaient-ils ? · Où sont-ils partis ? |
| Situation locale | C'est calme ici ? · Il y a de l'eau potable ? · Un médecin ? · De quoi avez-vous besoin ? · Où est le marché ? · On est où ? |
| Relations | Tu connais cet homme ? · Vos voisins ? · Le chef du village ? · Que pensez-vous de l'armée ? |
| Personnel | Vous avez peur ? · Vous êtes blessé ? · Tu as faim ? · Tu veux de l'eau ? |
| Règles du jeu de rôle | Vous voulez un avocat ? · Un interprète ? · Vous allez coopérer ? · Patientez · Je vous promets |
| Hors sujet | insultes, grossièretés, blagues, « T'es un robot ? », l'heure, la politique : elle reste en personnage (choquée, vexée, méfiante), ne fait jamais ce qu'on ne lui a pas demandé, et coupe la conversation un moment si on insiste |

Tutoiement, vouvoiement et questions courtes sont compris de la même façon (« Ton prénom ? », « Tes papiers. », « Où tu vas ? »). « Et alors ? », « Pourquoi ? » : elle développe sa dernière réponse, sans rien inventer. Quand elle ne comprend pas, elle le dit autrement à chaque fois ; après deux échecs de suite, une astuce s'affiche sous la ligne de saisie.

Une IA se souvient : à « Tu as un téléphone ? », elle répond la même chose la deuxième fois ; « Répète » redit sa dernière phrase. Avec un fait dans son histoire (ligne « Téléphone : 06 12 34 », « Depuis : ce matin », « Avec : mon frère », « Hier soir : au café de la place », « Transporte : des oranges »), elle le donne.

## Où régler les réponses

| Contexte | Chemin |
|---|---|
| Eden, une question | Attributs d'une unité › **COMSPEC SSE · IA RP › Question** et **Réponse de l'IA** : enregistré dans la mission |
| Eden, toutes | Attributs d'une unité › **COMSPEC SSE · IA RP › Profil IA RP › Atelier IA… › Toutes les questions et réponses…**, puis **Valider** et **Appliquer** dans l'atelier : enregistré dans la mission |
| Zeus, ZEN | Clic droit sur une ou plusieurs IA › **SSE · Roleplay de l'IA › Profil d'IA › Question et réponse…** (une question) ou **Toutes les questions et réponses…** (ou **Ce qu'elle peut évoquer…** pour le cadre seul) |
| Zeus, IA de la zone | Cochez les IA › **Réponses…** (voir [ia_de_la_zone.md](ia_de_la_zone.md)) |
| Zeus, Atelier IA | module ou menu **Atelier IA** › **Question**, **Réponse de l'IA**, ou **Toutes les questions et réponses…** |

## La fenêtre « Réponses de l'IA »

Elle s'ouvre en **mode simple** : seulement ce qu'il faut pour écrire une réponse. Tout le reste est dans **Options avancées**, en bas à droite, replié par défaut.

### Mode simple

1. **En haut**, une seule ligne : **Ce que cette IA peut évoquer** : **Rien de sensible** (jamais d'armes, de mines ni d'indice), **Ce qu'elle sait** (seulement son savoir, ses secrets et vos réponses) ou **Libre** (tout, sauf les thèmes interdits). Sans choix de votre part, la fenêtre montre le niveau choisi d'après son profil (« Rien de sensible » pour un civil sans savoir ni secret, sinon « Ce qu'elle sait ») et le profil le garde automatique.
2. **À gauche**, les questions, par thème, les mêmes que l'onglet GUIDE des joueurs, avec **Chercher** (« nom », « armés », « mot de passe »). À droite d'une question : **Ma réponse** si vous en avez écrit une, **Interdit** si son thème est interdit.
3. **À droite**, la question choisie (et d'autres façons de la poser), puis **Réponse automatique** ou **Ma réponse**.
4. Avec **Ma réponse** : écrivez **ce qu'elle répond, une réponse par ligne** (plusieurs lignes : elle en dit une). **Insérer une variable** ouvre la liste des mots remplacés en jeu (son prénom, sa ville, le nom du joueur…) avec leur valeur d'exemple ; un clic l'insère là où est le curseur.
5. **L'IA dira** montre la réponse avec les variables remplacées ; une variable inconnue, une question perso sans mot déclencheur ou un thème interdit y sont signalés.
6. **Enregistrer** (Zeus : tout de suite ; Eden : dans l'attribut de chaque unité ; atelier : repris par son bouton Appliquer). **Tout son groupe** (Zeus, IA de la zone) enregistre aussi sur chaque IA de son groupe. La ligne d'état à côté dit si c'est enregistré.

### Options avancées

Le bouton indique combien de réglages avancés sont utilisés (« Options avancées (2 réglages utilisés) »). Ouvert, il montre une phrase d'aide pour le réglage survolé.

| Réglage | Rôle | Exemple |
|---|---|---|
| **Thèmes interdits** | Armes (et groupes armés), Explosifs (et mines), Lieux militaires, Recherchés, Drogue, Politique : sur ces thèmes, elle dit ne rien savoir, **même avec votre réponse** | Explosifs |
| **Situation** | Toujours, S'il est calme, S'il a peur, Si on le menace (ou arme braquée), S'il est accusé de mentir, La 1re fois, Si on lui redemande, S'il accepte, S'il refuse ; la plus précise qui a une réponse l'emporte | « S'il a peur » : « Je… je suis {prenom} ! Ne tirez pas ! » |
| **Ordre** | Au hasard (jamais deux fois de suite la même) ou Dans l'ordre (pour chaque joueur, la dernière se répète) | un témoin qui en dit plus à chaque fois |
| **Puis révèle** | Rien de plus, Un indice vague, Un renseignement précis ou Tout ce qu'elle sait, pour ce joueur | Un renseignement précis |
| **Repère** | Comme une ligne « Lieu : » du savoir : carroyage, coordonnées ou marqueur, puis le nom ; posé une fois sur la carte de ce joueur | 045 078 Ferme abandonnée |
| **Ajouter ma propre question** | Une question que le jeu ne connaît pas : **Nom de la question** (pour vous), **Mots qui la déclenchent** séparés par « ; » (« a+b » : les deux mots dans le message), puis la réponse ; **Supprimer** l'enlève | « mot de passe; password » → « Le faucon vole bas. » |

Refermer les options avancées ne les efface pas : elles restent enregistrées, et le bouton les compte.

### Variables

| Français | English | Remplacée par |
|---|---|---|
| {nom} | {name} | le nom complet (papiers, ou nom de l'atelier) |
| {prenom} | {first} | le prénom |
| {ville} | {city} | sa ville (couverture) |
| {metier} · {employeur} | {job} · {employer} | son métier, son employeur |
| {age} · {famille} · {adresse} · {motif} · {contact} | {age} · {family} · {address} · {travel} · {contact} | les faits de sa couverture |
| {telephone} · {nationalite} | {phone} · {nation} | lignes « Téléphone : », « Nationalité : » de son histoire |
| {joueur} | {player} | le nom du joueur qui parle |
| {heure} | {time} | l'heure du jeu (18:05) |
| {lieu} | {here} | le village ou la ville la plus proche sur la carte |
| {monsieur} | {sir} | « monsieur » / « sir » |

Fait absent : « … » (l'aperçu le signale).

## Pas à pas : le boulanger et le guetteur

1. Eden, attributs du boulanger › **Atelier IA…** : Nom « Nikos KARRAS », histoire « Métier : boulanger ».
2. **Toutes les questions et réponses…** › « Qui es-tu ? » › **Ma réponse** : « Je suis {prenom}, le boulanger. Tout le monde me connaît ici. » (le prénom par **Insérer une variable**).
3. **Options avancées** › Situation **S'il a peur** : « Je… je suis {prenom} ! Je fais le pain, c'est tout ! »
4. « Avez-vous vu des hommes armés ? » › **Ma réponse**, Situation **S'il accepte** : « Hier soir, trois pick-up vers le nord. » ; Puis révèle **Un renseignement précis** ; Repère « 045 078 Ferme abandonnée » ; Situation **S'il refuse** : « Je ne veux pas d'ennuis. »
5. **Ajouter ma propre question** : Nom « Mot de passe », Mots qui la déclenchent « mot de passe; password », réponse « Le faucon vole bas. »
6. **Enregistrer**, puis **Appliquer** dans l'atelier. En jeu : « Qui es-tu ? » → « Je suis Nikos, le boulanger. Tout le monde me connaît ici. »

Les autres civils du marché, sans savoir, restent en **Réponse automatique** : à « Il y a des insurgés ici ? », « Non. Je vais juste au marché, moi. »

## Émotions des IA (peur des armes comprise)

Six émotions, chacune à cocher : **peur** (tremble, se jette au sol, se rend ou fuit), **peur des armes** (arme levée braquée sur elle, soldats arme levée, tirs proches, « /me braque son arme »), **nervosité** (hésite, devient nerveuse, refuse ses papiers), **colère** (ton agressif, nie et s'énerve, riposte), **méfiance** (ton méfiant) et **soulagement** (se détend quand on la rassure). Réglage global : Options des addons › COMSPEC SSE › IA RP (enregistrable dans la mission). Par IA : attributs Eden **Émotions de l'IA** (préréglage, une case par émotion, **Tout son groupe**) ; Zeus : menu SSE · Roleplay de l'IA › Profil d'IA › **Émotions…** (Réglage de la mission, Personnalisé, Tout cocher, Impassible, Très émotif, Tout décocher). Le réglage de l'IA l'emporte sur le réglage global. Une émotion décochée ne monte plus, ne change plus le ton et ne déclenche plus rien ; tout décoché, l'IA reste neutre mais parle normalement. Exemple : décochez **Peur des armes** pour un policier ou un vétéran : il voit votre fusil (« Il fixe le canon de votre arme. ») mais reste calme ; une menace dite, un faux découvert ou une violence vue le touchent toujours.

## Pièges fréquents

- Votre réponse **ne remplace que la phrase** : un ordre refusé reste refusé, un menteur reste démasquable, les papiers passent toujours par le contrôle ACE. Pour un ordre, une fouille ou un pot-de-vin, « Toujours » vaut quand il obéit ; s'il refuse, seul « S'il refuse » est dit.
- Votre propre question remplace la conversation du message ; un ordre ou une menace du même message est traité quand même (« Stop ! Le mot de passe. »).
- Un thème interdit l'emporte sur vos textes.
- Les réponses restent sur le serveur (jamais envoyées aux joueurs), sont enregistrées dans la mission (Eden) et partent avec l'IA par **Garder pour les prochaines parties**.
- Les caractères `<` `>` `&` s'affichent tels quels ; un retour à la ligne sépare deux réponses.

## Athena

Les profils IA du site ne portent pas ces réponses (pas de champ prévu) : elles vivent dans la mission ou dans l'IA gardée (configuration copiée sur Athena par « Garder »).
