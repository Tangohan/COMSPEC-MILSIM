# Discussions Steam : un sujet par app ATAK

Textes prêts à coller dans les discussions de la page Workshop COMSPEC ATAK (état 2.9.34). Steam n'a pas d'API
pour créer des sujets : la publication se fait à la main.

## Contenu

- **66 sujets** : `00_INDEX.txt` (index), 63 apps (`01` à `63`), 2 sujets d'aide (`90`, `91`).
- Les apps en chantier (Brèche, OSINT) n'ont pas de sujet : elles sont citées dans l'index comme « en préparation ».
- L'app SSE terrain (mod séparé COMSPEC SSE) est décrite dans `34_sse.txt`.

## Format d'un fichier

- Ligne 1 : titre du sujet, à coller dans le champ titre (120 caractères au plus).
- Le reste : corps du message en BBCode Steam, français puis anglais.
- Si le fichier contient une ligne `===== RÉPONSE =====`, le sujet tient en plusieurs messages (limite Steam
  d'environ 8 000 caractères) : collez la partie 1 comme message du sujet, puis chaque partie suivante comme
  réponse, dans l'ordre. Ne collez pas la ligne `===== RÉPONSE =====`.
- 44 sujets sont en deux messages (l'index, le sujet Réglages serveur et 42 apps), les 22 autres en un seul.

## Ordre de publication conseillé

Steam affiche les sujets du plus récent au plus ancien : publier l'index en dernier le met en tête, puis
l'épingler le garde en haut.

1. Les sujets d'aide : `90_aide-installation-serveur-logs.txt`, `91_aide-reglages-serveur-cba.txt`.
2. Les apps dans l'ordre inverse des numéros, de `63_tinder.txt` à `01_carte.txt` : la Carte se retrouve en
   haut de la liste, Tinder en bas.
3. L'index `00_INDEX.txt` en dernier.
4. Remplacer dans l'index chaque « Voir le sujet dédié » / « See the dedicated thread » par
   `[url=LIEN]sujet dédié[/url]` / `[url=LIEN]dedicated thread[/url]`, puis modifier le message de l'index.
5. **Épingler l'index** (menu du sujet › Épingler) et, si besoin, les deux sujets d'aide.

## Mise à jour

Quand une app change, modifier son fichier ici puis éditer le sujet Steam correspondant. La version citée
dans chaque sujet est 2.9.34.

Ces fichiers alimentent aussi le wiki public du site (`/wiki/atak`, une page par fichier) : une modification
apparaît sur le site sans autre étape. Contrôle : `php tools/wiki/atak_wiki.php`. Voir `docs/wiki-atak.md`.
