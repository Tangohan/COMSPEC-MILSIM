<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Repositories\PublicModpackRepository;

final class PublicModpackController
{
    private const VERSION = '/\A[0-9][a-zA-Z0-9._+-]{0,47}\z/';

    public function __construct(private PublicModpackRepository $packs = new PublicModpackRepository())
    {
    }

    public function index(Request $request): Response
    {
        $admin = false;
        if (Session::get('user_id')) {
            (new AuthMiddleware())($request, static function () use (&$admin): Response {
                $admin = Gate::getInstance()->isPlatformAdmin();
                return new Response();
            });
        }

        try {
            $pack = $this->packs->current();
            $this->packs->countView();
            $activity = $this->packs->activity();
            $ready = $pack !== [];
        } catch (\PDOException $e) {
            error_log('Public modpack: ' . $e->getMessage());
            $pack = $activity = [];
            $ready = false;
        }

        return Response::view('layout.marketing', [
            ...$this->publicLayout('site.public_modpack'),
            'pack' => $pack,
            'activity' => $activity,
            'admin' => $admin,
            'ready' => $ready,
            'notice' => $admin ? Session::getFlash('public_modpack_notice') : null,
        ])->header('Cache-Control', 'private, no-store')->setStatusCode($ready ? 200 : 503);
    }

    public function publish(Request $request): Response
    {
        if (!Gate::getInstance()->isPlatformAdmin()) {
            return $this->error(403, 'Accès réservé aux administrateurs de la plateforme.');
        }
        if (!Csrf::verify($request)) {
            return $this->error(403, 'Session expirée ou formulaire trop volumineux.');
        }

        $version = trim($request->inputString('version'));
        $notes = trim($request->inputString('notes'));
        $upload = $_FILES['archive'] ?? [];
        if (!preg_match(self::VERSION, $version) || mb_strlen($notes) > 3000) {
            return $this->notice('Vérifiez la version et les notes de publication.', true);
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->notice('Archive manquante ou envoi incomplet. Vérifiez la limite de taille du serveur.', true);
        }

        $tmp = $upload['tmp_name'];
        $signature = is_uploaded_file($tmp) ? file_get_contents($tmp, false, null, 0, 8) : false;
        if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'rar'
            || !is_string($signature)
            || (!str_starts_with($signature, "Rar!\x1a\x07\x00") && $signature !== "Rar!\x1a\x07\x01\x00")) {
            return $this->notice('Sélectionnez une archive RAR valide.', true);
        }

        $this->prepareStorage();
        $lock = fopen($this->directory() . '/publish.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->notice('Une publication est déjà en cours. Réessayez dans un instant.', true);
        }
        try {
            $previous = $this->packs->current()['archive_key'] ?? null;
            $key = bin2hex(random_bytes(16));
            $path = $this->archivePath($key);
            $size = filesize($tmp);
            $hash = hash_file('sha256', $tmp);
            if (!$size || !$hash || !move_uploaded_file($tmp, $path)) {
                return $this->notice('Impossible de stocker cette archive.', true);
            }
            // Conserver le nouveau fichier en cas d'erreur SQL ambiguë après validation.
            $this->packs->publish($key, $version, $notes, $size, $hash);
            if (is_string($previous) && preg_match('/\A[a-f0-9]{32}\z/', $previous)) {
                @unlink($this->archivePath($previous));
            }
        } finally {
            fclose($lock);
        }

        return $this->notice('La version ' . $version . ' est publiée.');
    }

    public function configure(Request $request): Response
    {
        if (!Gate::getInstance()->isPlatformAdmin() || !Csrf::verify($request)) {
            return $this->error(403, 'Accès refusé.');
        }
        $expected = trim($request->inputString('expected_version'));
        $max = filter_var($request->input('max_downloads'), FILTER_VALIDATE_INT);
        $threshold = filter_var($request->input('throttle_at'), FILTER_VALIDATE_INT);
        $speed = filter_var($request->input('throttle_mib'), FILTER_VALIDATE_INT);

        if (($expected !== '' && !preg_match(self::VERSION, $expected))
            || $max === false || $max < 0 || $max > 16
            || $threshold === false || $threshold < 1 || $threshold > 16
            || $speed === false || $speed < 1 || $speed > 64
            || ($max > 0 && $threshold > $max)) {
            return $this->notice('Vérifiez la version cible, le seuil de bridage et le nombre de places.', true);
        }

        $this->packs->configure($expected !== '' ? $expected : null, $max, $threshold, $speed, $request->input('published') === '1');
        return $this->notice('Les réglages de diffusion sont enregistrés.');
    }

    public function download(Request $request): Response
    {
        $pack = $this->packs->current();
        if (empty($pack['published']) || !preg_match('/\A[a-f0-9]{32}\z/', $pack['archive_key'] ?? '')) {
            return $this->error(404, 'Aucune archive disponible.');
        }
        $versionKey = $request->queryString('archive');
        if ($versionKey !== '' && $versionKey !== $pack['archive_key']) {
            return $this->error(410, 'Cette archive a été remplacée. Ouvrez la page du pack pour obtenir la version actuelle.');
        }
        $max = (int) $pack['max_downloads'];
        if ($max === 0) {
            return $this->error(503, 'Les téléchargements sont en pause. Réessayez dans un instant.')
                ->header('Retry-After', '60');
        }
        $file = @fopen($this->archivePath($pack['archive_key']), 'rb');
        if (!$file) {
            return $this->error(503, 'Archive momentanément indisponible. Actualisez la page.');
        }

        $size = (int) $pack['bytes'];
        $start = 0;
        $end = $size - 1;
        $range = $request->header('Range');
        $partial = $range !== null && ($request->header('If-Range') === null
            || $request->header('If-Range') === '"' . $pack['sha256'] . '"');
        if ($partial) {
            if (!preg_match('/\Abytes=(\d*)-(\d*)\z/', $range, $parts)
                || ($parts[1] === '' && ($parts[2] === '' || (int) $parts[2] === 0))) {
                fclose($file);
                return $this->error(416, 'Plage de téléchargement invalide.')->header('Content-Range', 'bytes */' . $size);
            }
            if ($parts[1] === '') {
                $start = max(0, $size - (int) $parts[2]);
            } else {
                $start = (int) $parts[1];
                $end = $parts[2] === '' ? $end : min($end, (int) $parts[2]);
            }
            if ($start > $end || fseek($file, $start) !== 0) {
                fclose($file);
                return $this->error(416, 'Plage de téléchargement invalide.')->header('Content-Range', 'bytes */' . $size);
            }
        }

        [$slot, $active] = $this->reserveDownload($max);
        if ($slot === null) {
            fclose($file);
            return $this->error(429, 'Toutes les places sont prises. Réessayez dans quelques secondes.')
                ->header('Retry-After', '20');
        }
        if ($start === 0) {
            $this->packs->countDownload();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $speed = $active >= (int) $pack['throttle_at'] ? (int) $pack['throttle_mib'] * 1048576 : 0;
        $length = $end - $start + 1;
        $response = (new Response())->setStatusCode($partial ? 206 : 200)
            ->header('Content-Type', 'application/vnd.rar')
            ->header('Content-Disposition', 'attachment; filename="COMSPEC-Modpack-' . $pack['version'] . '.rar"')
            ->header('Content-Length', (string) $length)
            ->header('Accept-Ranges', 'bytes')
            ->header('ETag', '"' . $pack['sha256'] . '"')
            ->header('Cache-Control', 'no-store, no-transform')
            ->header('X-Accel-Buffering', 'no');
        if ($partial) {
            $response->header('Content-Range', "bytes $start-$end/$size");
        }
        return $response->setBodyStream(static function () use ($file, $slot, $length, $speed): void {
            set_time_limit(0);
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0 && @ob_end_flush()) {
            }
            $sent = 0;
            $started = microtime(true);
            try {
                while ($sent < $length && !connection_aborted()) {
                    $chunk = fread($file, min(262144, $length - $sent));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $sent += strlen($chunk);
                    if ($speed > 0) {
                        $wait = $sent / $speed - (microtime(true) - $started);
                        if ($wait > 0) {
                            usleep((int) ($wait * 1000000));
                        }
                    }
                    echo $chunk;
                    flush();
                }
            } finally {
                fclose($file);
                fclose($slot);
            }
        });
    }

    /** @return array{0: resource|null, 1: int} */
    private function reserveDownload(int $max): array
    {
        $admission = @fopen($this->directory() . '/admission.lock', 'c');
        if (!$admission) {
            return [null, 0];
        }
        $slot = null;
        $active = 0;
        try {
            if (!flock($admission, LOCK_EX)) {
                return [null, 0];
            }
            // Même les transferts démarrés avant une baisse du plafond occupent une place.
            for ($index = 0; $index < 16; $index++) {
                $candidate = @fopen($this->directory() . '/download-' . $index . '.lock', 'c');
                if (!$candidate) {
                    continue;
                }
                if (!flock($candidate, LOCK_EX | LOCK_NB)) {
                    $active++;
                    fclose($candidate);
                } elseif ($slot === null) {
                    $slot = $candidate;
                } else {
                    fclose($candidate);
                }
            }
            if ($slot !== null && $active >= $max) {
                fclose($slot);
                $slot = null;
            }
        } finally {
            fclose($admission);
        }
        return [$slot, $active + 1];
    }

    private function directory(): string
    {
        return base_path('storage/private/public-modpack');
    }

    private function archivePath(string $key): string
    {
        return $this->directory() . '/' . $key . '.rar';
    }

    private function prepareStorage(): void
    {
        if (!is_dir($this->directory()) && !@mkdir($this->directory(), 0750, true) && !is_dir($this->directory())) {
            throw new \RuntimeException('Le stockage privé des archives est indisponible.');
        }
    }

    private function notice(string $text, bool $error = false): Response
    {
        Session::flash('public_modpack_notice', ['text' => $text, 'error' => $error]);
        return Response::redirect(url('modpacks') . '#administration', 303);
    }

    private function error(int $status, string $message): Response
    {
        return Response::view('layout.marketing', [
            ...$this->publicLayout('site.public_modpack_error'),
            'message' => $message,
        ])->setStatusCode($status)->header('Cache-Control', 'no-store');
    }

    private function publicLayout(string $content): array
    {
        return [
            'content' => $content,
            'title' => 'Modpack Arma 3 · COMSPEC',
            'meta_description' => 'Téléchargez le modpack COMSPEC pour Arma 3 au format RAR et consultez sa version.',
            'og_image' => asset_url('assets/images/home/hero-2-1920.webp'),
            'marketingActive' => 'modpacks',
            'marketingStyles' => [asset_url('assets/css/public-modpack.css')],
        ];
    }
}
