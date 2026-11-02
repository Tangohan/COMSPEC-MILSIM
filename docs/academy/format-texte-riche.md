# Academy — format de texte riche des cours (guide auteur)

Pour toute personne (ou agent) qui écrit un cours Academy : cours intégré (`app/Services/Academy/AcademyBuiltInCourses.php`)
ou cours créé dans le Studio. Rendu : `App\Services\Academy\AcademyRichText::render()`, côté serveur, identique dans la
salle de cours, le lecteur historique, l'aperçu partagé et l'aperçu en direct du Studio.

## Champs concernés

Texte riche : cours (`theory`), démonstration (`demonstration`), mise en situation des cas guidé et autonome (`brief`),
piste de réflexion (`hint`), explication d'une question (`feedback`), consignes d'évaluation (`assessment.instructions`),
consigne de débriefing (`debrief.prompt`), présentation de la validation (`validation.brief`), document intégré
(`resources[].body`), et l'option facultative `option_feedback` (trois textes : pourquoi chaque choix convient ou non).

Texte simple (jamais mis en forme) : titres, questions, choix de réponse, critères, textes saisis par les stagiaires.

## Compatibilité

Un texte sans syntaxe s'affiche comme avant : une ligne vide sépare deux paragraphes, un retour à la ligne est conservé.
Seules les lignes qui *commencent* par une syntaxe ci-dessous changent de rendu (`- `, `1. `, `> `, `#`, `|`, `:::`, ```` ``` ````, `---`, `![`).

## Syntaxe

| Syntaxe | Rendu |
|---|---|
| `## Titre` · `### Sous-titre` · `#### Petit titre` | Titres de section (`#` = `##`) |
| `**gras**` · `*italique*` · `` `9-line` `` | Gras, italique, terme technique |
| `- élément` · `1. élément` | Listes (une ligne indentée de deux espaces prolonge l'élément) |
| `> texte` | Citation (plusieurs lignes `>` = une citation) |
| `---` | Séparateur |
| `[texte](https://exemple.org)` | Lien, `http`/`https` uniquement, ouvert dans un nouvel onglet |
| `\| A \| B \|` puis `\|---\|---\|` | Tableau (la ligne `---` fait de la première ligne l'en-tête) |
| ```` ```Titre ```` … ```` ``` ```` | Bloc journal / exemple à recopier (texte brut, police fixe, titre facultatif) |
| `![Légende](uploads/…)` | Image avec légende (agrandie au clic) |

### Blocs Academy

Chaque bloc s'ouvre par `:::type Titre facultatif` et se ferme par une ligne `:::` seule.

```
:::analyse Commentaire
Le pilote confirme et signale **franchement** ce qu'il ne voit pas.
:::
```

- Encadrés : `note` (bleu), `astuce` / `tip` (vert), `attention` / `warning` (ambre), `exemple` / `example` (indigo),
  `analyse` / `analysis` (violet). Sans titre, le libellé du type s'affiche dans la langue de l'interface. Un encadré
  peut contenir listes, citations, tableaux…
- Échange radio : `:::transmission` (ou `:::radio`), une ligne `Indicatif : message` par transmission ; les autres
  lignes s'affichent comme indications de scène. Ne pas mettre de guillemets autour du message.

  ```
  :::transmission Fréquence CAS
  Voodoo 1 : Bastion, Voodoo 1, prêt pour check-in.
  Bastion : Voodoo 1, Bastion, je vous prends en compte.
  :::
  ```
- Étapes numérotées : `:::etapes` (ou `:::steps`), une étape par ligne (`1. ` facultatif).

## Images

Sources acceptées, tout le reste est refusé (un encart « Image non affichée » apparaît à la place) :

1. images envoyées depuis le Studio (bouton Image → « Envoyer une image », JPEG/PNG/WEBP/GIF, 4 Mo maximum, type réel
   vérifié) : chemin `uploads/training-course-media/<communauté>/academy-<16 hex>.<ext>`, rangées dans la médiathèque de
   la communauté ;
2. visuels livrés avec l'Academy : `assets/images/academy/….webp|png|jpg|gif` ;
3. HTTPS d'un hôte de `AcademyRichText::IMAGE_HOSTS` (aujourd'hui `upload.wikimedia.org`).

Pour un cours intégré, déposer le visuel dans `public/assets/images/academy/` et l'appeler par ce chemin.

## Sécurité

Tout le texte est échappé avant la mise en forme : aucune balise ni attribut saisi n'atteint la page (`<script>`,
`onerror=`… s'affichent en clair). Les liens `javascript:`, `data:` ou avec identifiants sont rendus en texte simple ;
`rel="noopener noreferrer"`. Ne jamais contourner le moteur avec du HTML : il n'est pas interprété.

## Cours intégrés : versions

Les inscrits gardent une copie (`snapshot`) du cours. Après une modification d'un cours intégré : augmenter sa version,
puis ajouter une étape de migration qui rafraîchit uniquement les inscrits non commencés (modèle :
`database/steps/3540_academy_jtac_rich_text_refresh.php`), et mettre à jour son test.

## Repères de rédaction

- Un titre `##` par section ; la numérotation « 01 — » reste possible dans le titre.
- Démonstration : contexte dans `:::note`, échanges dans `:::transmission`, explication juste après dans `:::analyse`,
  synthèse finale dans `:::astuce À retenir`.
- FR et EN suivent la même structure ; les mots-clés de bloc français et anglais sont équivalents.
