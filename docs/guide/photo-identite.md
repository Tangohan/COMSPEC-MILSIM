# Photo d'identité façon photomaton (COMSPEC IDENTITY)

Créer la photo d'un **personnage du jeu** pour ses papiers fictifs (carte d'identité, passeport, permis), puis
l'importer dans Athena (RENS › Identités & documents : fiche de l'identité, spécimen d'un papier ou atelier).

> **Uniquement des personnages du jeu.** N'importez jamais la photo d'une vraie personne, ni la vôtre. La photo sert
> au jeu de rôle : elle s'affiche toujours **sous** le filigrane SIMULATION, dans l'aperçu comme dans l'image exportée.

## 1. Capture dans Arma 3

1. Ouvrez la caméra libre (Splendid Camera, menu Échap) ou le mode photo de votre mod.
2. Placez le personnage **face caméra** : tête droite, regard droit dans l'objectif, expression neutre, bouche fermée.
3. Retirez casque, lunettes de soleil et équipement qui masquent le visage ; front et oreilles dégagés.
4. Devant un **mur uni et clair**, avec une lumière douce et frontale, sans ombres marquées sur le visage.
5. Cadrage serré : la **tête et le haut des épaules**, au centre.
6. Capture : F12 dans Steam (dossier des captures Steam).

## 2. Photo façon photomaton avec un générateur d'image

Donnez la capture à un générateur d'image (ChatGPT ou autre) avec ce prompt. Le même texte se copie depuis la page
(aide « Photo d'identité façon photomaton », bouton « Copier le prompt »).

### Prompt (français)

```text
À partir de la capture d'écran jointe d'un personnage du jeu vidéo Arma 3, crée une photo d'identité de jeu stylisée, façon photomaton (cabine photo). C'est un personnage fictif de jeu vidéo, pas une vraie personne : il ne doit ressembler à aucune personne réelle identifiable. Garde ses traits généraux, sa coupe de cheveux, sa couleur de peau et sa pilosité. Cadrage serré tête et haut des épaules, centré, format portrait 35 × 45 mm. Tête droite, regard droit dans l'objectif, bouche fermée, expression neutre, oreilles et front dégagés. Éclairage frontal uniforme et plat, comme le flash diffus d'une cabine photomaton, sans ombres marquées. Fond uni gris clair ou blanc cassé. Pas de lunettes de soleil ni de couvre-chef (sauf couvre-chef religieux), pas de casque, pas d'équipement tactique. Rendu photo réaliste mais propre, image bien nette ; légère texture de tirage photomaton facultative. Aucun texte, logo, tampon ni filigrane dans l'image.
```

### Prompt (English)

```text
Using the attached screenshot of a character from the video game Arma 3, create a stylised in-game ID photo in the style of a photo booth picture. This is a fictional video game character, not a real person: it must not look like any real, identifiable person. Keep the general features, haircut, skin tone and facial hair. Tight framing of the head and top of the shoulders, centred, 35 × 45 mm portrait format. Head straight, looking straight into the lens, mouth closed, neutral expression, ears and forehead uncovered. Even, flat frontal lighting like the diffused flash of a photo booth, no harsh shadows. Plain light grey or off-white background. No sunglasses and no headwear (except religious headwear), no helmet, no tactical gear. Realistic but clean photo rendering, sharp image; a slight photo booth print texture is optional. No text, logo, stamp or watermark in the image.
```

### Variantes (à ajouter à la fin du prompt)

| Variante | Français | English |
|---|---|---|
| Fatigué | Ajoute des traits tirés : cernes marqués, teint un peu terne, regard las, l'expression reste neutre. | Add a tired look: visible dark circles, slightly dull complexion, weary eyes, the expression stays neutral. |
| Barbe | Ajoute une barbe courte de quelques jours, bien nette, sans cacher la bouche. | Add a short, neat few-days beard that does not hide the mouth. |
| Cicatrice | Ajoute une fine cicatrice ancienne et discrète sur la joue gauche. | Add a thin, faded old scar on the left cheek. |
| Plus âgé | Vieillis le personnage d'environ 15 ans : rides légères, tempes grisonnantes. | Age the character by about 15 years: light wrinkles, greying temples. |
| Plus jeune | Rajeunis le personnage d'environ 10 ans, sans changer ses traits. | Make the character about 10 years younger without changing the features. |

## 3. Import dans Athena

- **Photo du titulaire** : PNG, JPG ou WebP, 4 Mo maximum. Recadrez-la dans la page (format 35 × 45 ou carré, zoom,
  glisser), ou reprenez une photo déjà prise de la personne SSE reliée à l'identité.
- **Signature** : image (fond transparent conseillé), 1 Mo maximum ; sinon la signature écrite est imprimée.
- **Drapeau ou emblème** du pays fictif : depuis la fiche du pays, 1 Mo maximum.
- **Image de fond** d'un papier (texture, décor d'un univers de fiction) : dans l'atelier, 3 Mo maximum, visible avec
  le modèle d'impression « Personnalisé ».

Le site vérifie le vrai type du fichier, réencode l'image (recadrage au centre au format 35 × 45 si besoin,
réduction) et **retire toutes les métadonnées** (EXIF, position GPS, XMP). Envoi réservé aux responsables RENS
(gestion SSE et habilitation Encadrement), jamais en invité ; chaque envoi est journalisé.

## En jeu

La photo n'est pas encore envoyée au serveur de jeu : les papiers en jeu affichent la silhouette. L'envoi des vraies
photos vers le jeu est prévu dans un lot suivant (« vraies photos Athena »).
