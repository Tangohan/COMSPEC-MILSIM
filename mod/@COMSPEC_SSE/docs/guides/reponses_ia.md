# Parler aux IA : questions comprises, réponses du maître du jeu, ce qu'une IA peut évoquer

Version anglaise : [reponses_ia.en.md](reponses_ia.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Les joueurs parlent aux IA en texte libre (interaction ACE › **Parler à…**, ou Ctrl+T). L'IA comprend environ **120 sortes de questions**, en français et en anglais, fautes et tutoiement compris, et répond selon son caractère, sa couverture et ce qu'elle sait. Le maître du jeu peut, question par question, garder cette **réponse auto** ou écrire une **réponse perso**, et fixer **ce que l'IA peut évoquer**.

Règle d'or : **une IA ne parle que de ce qu'elle sait**. Un passant sans savoir ne parle jamais d'armes, de groupes armés, de pick-up, de mines ou de caches : « Non, je n'ai rien vu de tel. », « Je ne sais pas, je viens d'arriver. »

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
| Hors sujet | insultes, blagues, « T'es un robot ? », l'heure, la politique : elle reste en personnage |

Une IA se souvient : à « Tu as un téléphone ? », elle répond la même chose la deuxième fois ; « Répète » redit sa dernière phrase. Avec un fait dans son histoire (ligne « Téléphone : 06 12 34 », « Depuis : ce matin », « Avec : mon frère », « Hier soir : au café de la place », « Transporte : des oranges »), elle le donne.

## Où régler les réponses

| Contexte | Chemin |
|---|---|
| Eden | Attributs d'une unité › **COMSPEC SSE · IA RP › Profil IA RP › Atelier IA… › Réponses et limites…**, puis **Valider** et **Appliquer** dans l'atelier : enregistré dans la mission |
| Zeus, ZEN | Clic droit sur une ou plusieurs IA › **SSE · Roleplay de l'IA › Profil d'IA › Réponses de l'IA…** (ou **Ce qu'elle peut évoquer…** pour le cadre seul) |
| Zeus, IA de la zone | Cochez les IA › **Réponses…** (voir [ia_de_la_zone.md](ia_de_la_zone.md)) |
| Zeus, Atelier IA | module ou menu **Atelier IA** › **Réponses et limites…** |

## La fenêtre « Réponses de l'IA »

### En haut : le cadre

- **Ce qu'elle peut évoquer** : **Auto** (« Rien de sensible » pour un civil sans savoir ni secret, sinon « Ce qu'elle sait »), **Rien de sensible** (réponses neutres, jamais de sous-entendu), **Ce qu'elle sait** (son savoir, ses secrets et vos textes ; les réponses auto ne parlent d'aucun thème de scénario), **Libre** (les réponses auto peuvent évoquer des thèmes, sauf interdits).
- **Thèmes interdits** : Armes (et groupes armés), Explosifs (et mines), Lieux militaires (bases, checkpoints), Recherchés (où est-il, photo, description), Drogue, Politique (et police, armée). Sur une question d'un thème coché : réponse neutre (« Je ne sais rien là-dessus. »), **même avec une réponse perso**.

### À gauche : les questions

Les mêmes que l'onglet GUIDE des joueurs, par thème, avec **Chercher** (« nom », « armés », « mot de passe »). À droite de chacune : **Auto**, **Perso (2)** (nombre de textes) ou **Interdit**. En tête : vos **questions perso**, et **Ajouter une question perso**.

### À droite : la réponse

| Réglage | Rôle | Exemple |
|---|---|---|
| **Réponse** | Réponse auto ou Réponse perso | Perso pour « Qui es-tu ? » |
| **Ordre** | Au hasard (jamais deux fois de suite la même) ou Dans l'ordre (pour chaque joueur, le dernier texte se répète) | un témoin qui en dit plus à chaque fois |
| **Question**, **Déclencheurs** | Question perso seulement : son nom (pour vous) et les mots qui la déclenchent, séparés par « ; » ; « a+b » : les deux mots dans le message | « mot de passe; password » |
| **Situation** | Toujours, S'il est calme, S'il a peur, Si on le menace (ou arme braquée), S'il est accusé de mentir, La 1re fois, Si on lui redemande, S'il accepte, S'il refuse ; la plus précise qui a un texte l'emporte | « S'il a peur » : « Je… je suis Nikos ! Ne tirez pas ! » |
| **Réponses** | Une par ligne, 300 caractères au plus, 8 par situation | voir les variables |
| **Débloque** | Rien, palier vague, précis ou complet de son savoir, pour ce joueur | précis |
| **Lieu** | Comme une ligne « Lieu : » du savoir : carroyage, coordonnées ou marqueur, puis le nom ; posé une fois sur la carte de ce joueur | 045 078 Ferme abandonnée |

**Aperçu** : la réponse avec les variables remplacées par des valeurs d'exemple ; une variable inconnue est signalée. **Revenir en auto** efface la réponse perso (ou supprime la question perso). **Tout son groupe** (Zeus) applique aussi au groupe de chaque IA. **Appliquer** (Zeus : tout de suite ; Eden : dans l'attribut de chaque unité) ou **Valider** (atelier).

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
2. **Réponses et limites…** › « Qui es-tu ? » › **Réponse perso** : « Je suis {prenom}, le boulanger. Tout le monde me connaît ici. » ; Situation **S'il a peur** : « Je… je suis {prenom} ! Je fais le pain, c'est tout ! »
3. « Avez-vous vu des hommes armés ? » › Perso, Situation **S'il accepte** : « Hier soir, trois pick-up vers le nord. » ; Débloque **Palier précis** ; Lieu « 045 078 Ferme abandonnée » ; Situation **S'il refuse** : « Je ne veux pas d'ennuis. »
4. **Ajouter une question perso** : Question « Mot de passe », Déclencheurs « mot de passe; password », Réponse « Le faucon vole bas. »
5. **Valider**, puis **Appliquer** dans l'atelier. En jeu : « Qui es-tu ? » → « Je suis Nikos, le boulanger. Tout le monde me connaît ici. »

Les autres civils du marché, sans savoir, restent en **Auto** : à « Il y a des insurgés ici ? », « Non. Je vais juste au marché, moi. »

## Pièges fréquents

- Une réponse perso **ne remplace que la phrase** : un ordre refusé reste refusé, un menteur reste démasquable, les papiers passent toujours par le contrôle ACE. Pour un ordre, une fouille ou un pot-de-vin, « Toujours » vaut quand il obéit ; s'il refuse, seul « S'il refuse » est dit.
- Une question perso remplace la conversation du message ; un ordre ou une menace du même message est traité quand même (« Stop ! Le mot de passe. »).
- Un thème interdit l'emporte sur vos textes.
- Les réponses restent sur le serveur (jamais envoyées aux joueurs), sont enregistrées dans la mission (Eden) et partent avec l'IA par **Garder pour les prochaines parties**.
- Les caractères `<` `>` `&` s'affichent tels quels ; un retour à la ligne sépare deux réponses.

## Athena

Les profils IA du site ne portent pas ces réponses (pas de champ prévu) : elles vivent dans la mission ou dans l'IA gardée (configuration copiée sur Athena par « Garder »).
