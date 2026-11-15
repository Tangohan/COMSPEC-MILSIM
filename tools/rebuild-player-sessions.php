<?php

declare(strict_types=1);

/**
 * Rattrapage des sessions de jeu des joueurs (table player_sessions) à partir des segments de présence des sessions de
 * mission déjà enregistrés. Essai à blanc par défaut : rien n'est écrit sans --apply. Rejouable : une session déjà
 * présente (même Steam, même début) n'est pas dupliquée. Les sessions courtes sont conservées (historique) ; le seuil
 * de la communauté ne sert qu'à annoncer combien comptent.
 *
 * À lancer sur le serveur, depuis la racine de l'application, avec le PHP du site :
 *
 *     php8.4 tools/rebuild-player-sessions.php                       essai à blanc, toutes les communautés
 *     php8.4 tools/rebuild-player-sessions.php --tenant=12           une seule communauté
 *     php8.4 tools/rebuild-player-sessions.php --tenant=12 --apply   écrit, par lots (--batch=200)
 *     php8.4 tools/rebuild-player-sessions.php --since=2026-08-01    à partir d'une date
 *
 * Code de retour : 0 succès, 1 échec (table absente), 2 usage incorrect.
 */

use App\Repositories\PlayerSessionRepository;
use App\Services\Personnel\PlayerSessionRebuildService;
use App\Services\Personnel\RoleplayGameSessionSettings;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

$options = [];
foreach (array_slice($argv ?? [], 1) as $arg) {
    $arg = (string) $arg;
    if (!str_starts_with($arg, '--')) {
        fwrite(STDERR, "Argument inconnu : {$arg}\n");
        exit(2);
    }
    $eq = strpos($arg, '=');
    $key = $eq === false ? substr($arg, 2) : substr($arg, 2, $eq - 2);
    if (!in_array($key, ['apply', 'tenant', 'batch', 'since'], true)) {
        fwrite(STDERR, "Option inconnue : --{$key}\n");
        exit(2);
    }
    $options[$key] = $eq === false ? true : substr($arg, $eq + 1);
}
$apply = !empty($options['apply']);
$since = null;
if (isset($options['since'])) {
    $since = strtotime((string) $options['since']);
    if ($since === false) {
        fwrite(STDERR, "Date --since illisible.\n");
        exit(2);
    }
}

$repo = new PlayerSessionRepository();
$service = new PlayerSessionRebuildService($repo);
$tenants = isset($options['tenant']) ? [(int) $options['tenant']] : $repo->tenantsWithHistory();
echo $apply ? "MODE ÉCRITURE (--apply)\n" : "ESSAI À BLANC : rien n'est écrit (ajoutez --apply pour écrire)\n";
$failed = false;
foreach ($tenants as $tenantId) {
    if ($tenantId < 1) {
        continue;
    }
    $rules = RoleplayGameSessionSettings::playerSessionRules($tenantId);
    $r = $service->run($tenantId, $apply, $rules['gap_minutes'] * 60, $rules['min_minutes'] * 60, (int) ($options['batch'] ?? 200), $since, static function (string $m): void {
        echo $m;
    });
    if (!$r['ready']) {
        $failed = true;
        continue;
    }
    printf(
        "Communauté %d : %d joueur(s), %d session(s) reconstituée(s) dont %d de %d min ou plus ; %s %d, %s %d, inchangées %d\n",
        $tenantId, $r['players'], $r['sessions'], $r['qualifying'], $rules['min_minutes'],
        $apply ? 'ajoutées' : 'à ajouter', $r['inserted'], $apply ? 'mises à jour' : 'à mettre à jour', $r['updated'], $r['unchanged']
    );
}
exit($failed ? 1 : 0);
