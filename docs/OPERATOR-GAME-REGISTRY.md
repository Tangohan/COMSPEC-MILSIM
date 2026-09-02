# Registre d'identité opérationnelle Arma

## Audit et décisions

- L'identifiant Steam est déjà normalisé par `SteamId` et la recherche sûre existe sous la forme
  `tenant_id + steam_id`. Le registre réutilise cette frontière et n'effectue aucun repli par pseudo,
  nom Arma ou indicatif. Les indicatifs et noms officiels lus pour comparaison viennent du dossier
  communauté (`user_community_profiles`) lorsqu'ils y sont renseignés.
- Les positions restent sur `/api/atak/position`. Les snapshots riches disposent de deux canaux
  dédiés, `/api/atak/operator/register` et `/api/atak/operator/sync`.
- `personnel_profiles` demeure la référence RH. Le registre écrit exclusivement dans les tables
  `operator_game_*`; une observation vide est traitée comme inconnue et aucune donnée RH n'est corrigée.
- La DLL assure le transport authentifié et le SQF collecte le visage, l'identité, l'équipement,
  `getUnitLoadout`, le contexte mission et les versions techniquement disponibles.
- Le payload Overwatch peut être imbriqué (`identity`, `face`, `equipment`, `environment`) : le
  normaliseur recopie les alias attendus (`player_name`, `sex`, `face_class`, `loadout` à la racine,
  `server_name` / `mission_id`). Un payload plat Codex reste accepté.

## Cycle de synchronisation

1. Le tenant est résolu avant toute recherche de compte.
2. Le SteamID64 exact est recherché dans ce tenant (y compris d'anciens formats encore en base).
3. En l'absence de compte, un événement `STEAM_ACCOUNT_NOT_FOUND` est conservé, la fiche observée
   est tout de même enregistrée (`user_id` vide) et l'API retourne `UNLINKED_ARMA_OPERATOR`.
   Aucun compte n'est créé.
4. La fiche observée est mise à jour, puis un snapshot est créé uniquement à la première observation,
   lors d'un changement significatif ou d'une incohérence.
5. Le moteur compare identité, indicatif, sexe, groupe sanguin, visage et versions.
6. L'empreinte stable d'une anomalie déduplique les occurrences. Si l'anomalie avait été close et
   réapparaît, elle est rouverte ; le compteur n'est incrémenté que tant qu'elle reste ouverte.

## Sécurité et exploitation

Les clés uniques, index et recherches incluent systématiquement le tenant. Les payloads bruts sont
réservés à l'exploitation technique et peuvent contenir un loadout détaillé. Les tables de journal de
notifications et de versions attendues sont prêtes pour le worker e-mail et l'interface santé opérateurs.
Une acceptation future de valeur jeu devra appeler le service métier RH et l'audit global : elle ne doit
jamais exécuter directement un `UPDATE personnel_profiles`.
