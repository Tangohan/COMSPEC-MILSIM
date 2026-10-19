# Galerie Steam COMSPEC SSE : captures en jeu à faire

La galerie (`docs/workshop/gallery/{fr,en}/sse-NN-*.jpg`) est produite par `tools/make_workshop_gallery.py`.
Quatre images sont déjà faites à partir des visuels de la charte (01 présentation, 03 SEEK, 08 fonctionnalités,
11 matériel). Les sept autres attendent une capture en jeu.

## Comment faire

1. Capture en **1920 x 1080** (ou plus grand, même rapport 16:9), interface Arma masquée sauf l'écran montré,
   sans HUD de debug, sans nom de joueur réel ni pseudo visible si possible.
2. Le sujet dans les **deux tiers droits** de l'image : le tiers gauche est recouvert par le panneau de texte.
3. Enregistrer sous le nom indiqué dans `docs/workshop/source/` (`.jpg` ou `.png`). Pour une capture propre à une
   langue (écran SEEK en anglais, par exemple), la mettre dans `docs/workshop/source/en/` ou `.../fr/`.
4. Relancer `python tools/make_workshop_gallery.py` depuis `mod/@COMSPEC_SSE`. Ajuster si besoin `focus` (recadrage)
   ou `bright` de la diapositive dans `SLIDES`.

## Liste

| N° | Fichier source | Ce qu'il faut montrer | Conseils |
|---|---|---|---|
| 02 | `fouille.jpg` | Un opérateur fouille un prisonnier menotté ou un corps, menu ACE **SSE** ouvert (Inspecter, Fouiller, Lire documents). | Intérieur d'une maison, documents et téléphone au sol. Jour. |
| 04 | `releve-biometrique.jpg` | Relevé biométrique en cours : opérateur à genoux devant un sujet, SEEK II en main, ou l'écran SEEK sur une page **MATCH**. | Mieux : vue troisième personne avec l'écran lisible. Option : sujet avec sac de tête à côté. |
| 05 | `exploitation-dl7.jpg` | Ordinateur portable relié au téléphone ATAK par le **câble DL-7**, écran de mot de passe ou téléchargement en cours. | Doit montrer l'ATAK Native (dépendance). Version FR et EN de l'écran si possible. |
| 06 | `scelles.jpg` | **Registre des scellés** ouvert dans le terminal SSE (numéros P-…, scellés, journal), ou un opérateur qui met une pièce sous scellé. | Laisser lisibles quelques lignes de journal. |
| 07 | `tableau-liens.jpg` | **Tableau des liens** en graphe (ou ORBAT) avec plusieurs personnes, une organisation, des pièces ; quelques nœuds confirmés et soupçonnés. | Partie bien avancée du dataset FALCON (niveau 2) pour un graphe fourni. |
| 09 | `zeus-eden.jpg` | Eden avec les modules **COMSPEC SSE** posés autour d'une maison, ou le **Tableau de bord SSE** (onglet Organigramme). | Peut être remplacée par la vue Zeus du tableau des liens. À refaire quand les kits et sites préfabriqués 2.x seront livrés. |
| 10 | `athena-bureau-sse.jpg` | Capture navigateur du **Bureau SSE d'Athena** : une affaire avec des fiches reçues (personne, biométrie, numérique). | Données d'exercice uniquement, aucun nom réel. Fenêtre 1920 x 1080. |

## Facultatif

- Une capture de **nuit** (lampe, sac de tête sur un prisonnier) pour varier les ambiances.
- Une capture du **manuel de terrain** en jeu (menu ACE › Guide SSE).
- Quand les actions numériques passeront par le terminal ATAK (mise à jour 2.x), refaire la 05 avec l'écran ATAK.
