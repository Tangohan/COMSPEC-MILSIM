<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\Cron\CronRunner;

/**
 * Déclenchement HTTP des tâches planifiées (clé secrète CRON_SECRET).
 */
final class CronController
{
    public function __construct(
        private CronRunner $runner,
    ) {}

    public function run(Request $request, array $params = []): Response
    {
        $expected = trim((string) env('CRON_SECRET', ''));
        if ($expected === '') {
            return Response::json([
                'ok' => false,
                'error' => 'Tâches planifiées non configurées.',
            ], 503);
        }

        $provided = trim((string) $request->query('key', ''));
        if ($provided === '') {
            $provided = trim((string) ($request->input('key', '') ?? ''));
        }
        if ($provided === '') {
            $hdr = $_SERVER['HTTP_X_CRON_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $hdr = is_string($hdr) ? trim($hdr) : '';
            if (str_starts_with(strtolower($hdr), 'bearer ')) {
                $provided = trim(substr($hdr, 7));
            } elseif ($hdr !== '') {
                $provided = $hdr;
            }
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return Response::json([
                'ok' => false,
                'error' => 'Accès refusé.',
            ], 401);
        }

        $only = trim((string) $request->query('job', ''));
        if ($only === '') {
            $only = trim((string) ($request->input('job', '') ?? ''));
        }

        $result = $this->runner->runAll('http', $only !== '' ? $only : null);

        return Response::json([
            'ok' => $result['ok'],
            'results' => array_map(static function (array $r): array {
                return [
                    'job' => $r['job'] ?? '',
                    'label' => $r['label'] ?? '',
                    'ok' => !empty($r['ok']),
                    'summary' => $r['summary'] ?? '',
                ];
            }, $result['results']),
        ], $result['ok'] ? 200 : 500);
    }
}
