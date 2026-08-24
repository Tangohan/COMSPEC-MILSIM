# File « À exploiter » — clé device_type_label absente

## Contexte

GET `/atak/sse/exploitation-numerique/a-exploiter` (production Athena).

Corrélations : `97fb10206f35691e`, puis `670bf39f941be1b6`.

## Symptôme

Page « 500 Incident technique » / ALERTE SYSTÈME. Journal PHP : `Undefined array key "device_type_label"`. L’opérateur ne voit pas le nom de la clé — la page 500 reste générique.

## Cause

Deux défauts se combinaient :

1. **Collision de variable (cause principale).** `views/atak/sse/digital/_subnav.php` était inclus par la file et réassignait `$groups` à la navigation (Travail / Analyse / Sortie). La vue `queue.php` itérait ensuite ces blocs de menu comme des groupes de paquets. Ces tableaux n’ont pas de `device_type_label`.
2. **Accès PHP 8 sans coalescence.** La vue lisait `$group['device_type_label'] ?: '—'`. En PHP 8, une clé absente lève une exception *avant* le `?:`. Un paquet sans support rattaché, ou le regroupement incomplet, faisait aussi tomber la page.

## Correctif

- Sous-navigation : `$labNavGroups` au lieu de `$groups`.
- File : copie dans `$queueGroups` *avant* l’inclusion de la sous-nav.
- Vue : coalescence `??` sur type, origine, collecteur, lieu, propriétaire.
- `SseDomexContract::deviceTypeLabel()` / `queueGroupFromPacket()` : la clé existe toujours. Repli humain **Inconnu** (sinon Téléphone, Ordinateur, etc. selon le type connu). Pas de type inventé.
- Hydratation laboratoire (supports, paquets, acquisitions) via le même helper.

## Fichiers touchés

- `views/atak/sse/digital/_subnav.php`
- `views/atak/sse/digital/queue.php`
- `app/Support/SseDomexContract.php`
- `app/Controllers/Web/SseDigitalLabController.php`
- `app/Repositories/SseDigitalLabRepository.php`
- `tests/Unit/SseDomexContractTest.php`
- `tests/Unit/SseDigitalLabTerrainIngestTest.php`

## Vérification

`php -l` sur les PHP modifiés. Smoke include de `SseDomexContract` (groupe vide → `Inconnu`, téléphone → `Téléphone`). Tests PHPUnit ciblés si `vendor` est présent.

La file « À exploiter » doit s’ouvrir : état vide si aucun paquet, ou les vrais groupes de supports — plus de 500, plus de faux panneaux issus du menu.

## Statut

corrigé
