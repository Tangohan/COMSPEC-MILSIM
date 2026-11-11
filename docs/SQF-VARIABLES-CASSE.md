# SQF : variables locales et casse

En SQF, `_L` et `_l` (ou `_T` et `_t`) sont **la même variable**. Un helper `private _L = { localize … }` écrasé plus loin par `private _l = <liste>`, `params ["_l"]` ou `_l = …` fait échouer le `call _L` suivant : le script s'arrête et la fenêtre reste à moitié construite.

- **Lancer** : `python -I tools/sqf_case_shadow_check.py` (toutes les sources des mods), `--all` pour voir aussi RISK et OK, `--extras` pour d'autres échecs silencieux (clés `localize` absentes, variable créée dans un `if` puis lue dehors, `call` sur une valeur, helper appelé dans un `spawn` ou un gestionnaire d'événement où il n'existe pas). Code retour 1 s'il reste un BREAKS.
- **Ce qu'il détecte** : deux orthographes d'un même nom dans un fichier, avec analyse des portées (`private`, `params`, `for`, blocs imbriqués, helpers appelés depuis une portée qui masque le nom). **BREAKS** : le helper (ou la valeur) est remplacé puis réutilisé avec son ancien type, erreur certaine. **RISK** : la valeur change en silence (même type). **OK** : simple variante d'orthographe sans conflit.
- **Corriger** : renommer la variable de valeur (`_l` → `_lst`, `_t` → `_txt`), jamais le helper sauf s'il est moins utilisé, sans toucher au reste du fichier. Un seul nom par variable, casse comprise.
- **CI** : `tests/Unit/SqfCaseInsensitiveShadowAssetTest.php` refuse tout helper `{ … }` / `compile` dont le nom existe sous une autre casse comme valeur dans le même fichier.
- **Exceptions** : `tools/sqf_case_shadow_allowlist.txt` (`chemin.sqf:_nom  # raison`), seulement pour un verdict OK vérifié avec `--all`. Mieux vaut renommer.
