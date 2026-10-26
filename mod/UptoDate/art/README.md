# Sources d'images et de sons sorties des addons empaquetés

AddonBuilder est lancé avec `-packonly` sur le dossier de l'addon
(`mod/UptoDate/build_mod.bat`) : **tout** ce qui se trouve dans
`Sources/comspec-overwatch-addons/<addon>/` part dans le PBO, y compris les PNG
et JPG de travail dont seul le `.paa` est chargé en jeu.

Ce dossier recueille ces fichiers : ils restent dans le dépôt, consultables et
réutilisables, mais ne sont plus téléchargés par les joueurs.

## `connect/`

Fichiers retirés du PBO `connect` le 26/10/2026 (audit DEAD-007, DEAD-009,
DEAD-021). Aucun n'était chargé en jeu : ni chemin littéral, ni chemin construit
(`format`, concaténation), ni `model=`, ni `CfgSounds`. L'arborescence reproduit
celle de l'addon, donc remettre un fichier en service se fait par un `git mv`
vers `Sources/comspec-overwatch-addons/connect/<même chemin>` suivi d'un rebuild.

- `data/device/` — copies cTab NSWDG (itemAndroid, DK10, MicroDAGR : `.p3d`,
  `.rvmat`, textures). Aucune classe de config n'a de `model=` vers ces modèles.
  L'attribution GPL v2 reste dans l'addon (`data/device/SOURCES.txt`).
- `img/device/` — maquettes PNG/JPG dont le `.paa` est (ou n'est plus) chargé, et
  `.paa` d'écrans cTab abandonnés (FBCB2, TAD, MicroDAGR, fenêtres, icônes mail).
  Les PNG sont les masters à repasser dans `tools/png2paa.py` ou TexView 2.
- `img/overlays/` — overlays roleplay jamais branchés : les 5 icônes Zeus / ACE
  (brouillons), `comspec_overlay_no_signal_ca` (son README dit « ne plus
  utiliser ») et `comspec_overlay_screen_cracked_ca.png`. Les 3 overlays
  réellement affichés (`static_noise`, `screen_off`, `atak-fx/broken-screen`)
  restent dans l'addon.
- `sounds/` — `roger_prio.ogg` et `roger_simple.ogg`, remplacés par des carillons
  le 24/08/2026 (`docs/bugs/2026-08-24-atak-pack-sons.md`) et absents de
  `CfgSounds`.
- `texHeaders.bin` — index de textures produit par Binarize. Exact mais incomplet : ses
  37 entrées décrivent bien les fichiers, mais 31 visent des textures sorties de l'addon
  (ci-dessus), 6 des textures encore affichées, et 24 textures affichées n'y ont jamais
  figuré. Plusieurs `main.pbo` natifs ont déjà été livrés sans index (1.95.0, 1.96.0 et
  2.0.1, probablement aussi 1.97.0) sans qu'aucune régression de texture soit notée au
  CHANGELOG ; rien dans le dépôt ne documente en revanche ce que fait le moteur sans ce
  fichier. **À remettre dans l'addon (`git mv`) ou à régénérer avec Binarize** si un essai en
  jeu montre une texture de l'addon connect manquante ou mal affichée : tablette, téléphone,
  overlays, icônes, écran « seek », HUD ECOTI ou logo du menu principal.
