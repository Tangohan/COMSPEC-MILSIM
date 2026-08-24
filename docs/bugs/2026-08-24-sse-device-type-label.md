# File « À exploiter » — libellé de type de support manquant

## Contexte

GET `/atak/sse/exploitation-numerique/a-exploiter`.

## Symptôme

Page 500 (incident technique). Journal PHP : `Undefined array key "device_type_label"`.

## Cause

En PHP 8, `$group['device_type_label']` sans `??` lève une exception dès que la clé est absente. Deux cas :

1. La sous-navigation réutilisait `$groups` (blocs de menu Travail / Analyse / Sortie) et écrasait les vrais groupes de paquets.
2. Un paquet sans support rattaché n’avait pas toujours la clé, donc même un vrai groupe pouvait tomber.

## Correctif

- Sous-navigation : `$labNavGroups` (plus `$groups`).
- File : copie dans `$queueGroups` avant l’inclusion de la sous-nav.
- Vues : accès `??` et repli français **Inconnu** (jamais une chaîne vide ni un tiret seul).
- `SseDomexContract::deviceTypeLabel()` / `queueGroupFromPacket()` : la clé existe toujours.

## Fichiers touchés

- `views/atak/sse/digital/_subnav.php`
- `views/atak/sse/digital/queue.php`
- `views/atak/sse/digital/hub.php`
- `views/atak/sse/digital/devices.php`
- `views/atak/sse/digital/device_show.php`
- `views/atak/sse/digital/phone.php`
- `views/atak/sse/digital/reports.php`
- `app/Support/SseDomexContract.php`
- `app/Controllers/Web/SseDigitalLabController.php`
- `app/Repositories/SseDigitalLabRepository.php`

## Vérification

`php -l` sur les PHP modifiés. La file s’ouvre : état vide si aucun paquet, ou les groupes de supports. Plus de 500.

## Statut

corrigé
