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
use App\Services\Modpack\ModpackUpload;
use App\Services\Modpack\ModpackVersion;

final class PublicModpackController
{
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

    public function uploadBlock(Request $request): Response
    {
        if (!Gate::getInstance()->isPlatformAdmin() || !Csrf::verify($request)) {
            return Response::json(['message' => 'Session expirée ou accès refusé.'], 403);
        }
        $token = $request->inputString('upload_token');
        $offset = filter_var($request->input('offset'), FILTER_VALIDATE_INT);
        $block = $_FILES['block'] ?? [];
        $size = $block['size'] ?? 0;
        if (!ModpackUpload::validToken($token) || $offset === false || $offset < 0) {
            return Response::json(['message' => 'Position ou identifiant d’envoi invalide.'], 400);
        }
        if (($block['error'] ?? null) !== UPLOAD_ERR_OK || !is_int($size) || $size < 1
            || $size > ModpackUpload::MAX_BLOCK_BYTES || !is_uploaded_file($block['tmp_name'])) {
            return Response::json(['message' => 'Bloc manquant ou trop volumineux.'], 400);
        }
        $bytes = @file_get_contents($block['tmp_name'], false, null, 0, ModpackUpload::MAX_BLOCK_BYTES);
        if ($bytes === false || strlen($bytes) !== $size) {
            return Response::json(['message' => 'Bloc incomplet.'], 400);
        }

        try {
            $this->prepareStorage();
            $nextOffset = $this->upload()->append($token, $offset, $bytes);
        } catch (\DomainException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 503);
        }

        return Response::json(['offset' => $nextOffset]);
    }

    public function publish(Request $request): Response
    {
        if (!Gate::getInstance()->isPlatformAdmin() || !Csrf::verify($request)) {
            return $this->error(403, 'Session expirée ou accès refusé.');
        }

        $version = trim($request->inputString('version'));
        $notes = trim($request->inputString('notes'));
        $token = $request->inputString('upload_token');
        $size = filter_var($request->input('archive_size'), FILTER_VALIDATE_INT);
        if (!ModpackVersion::isValid($version)) {
            return $this->notice('La version doit commencer par un numéro, par exemple « ' . ModpackVersion::EXAMPLE . ' ».', true);
        }
        if (mb_strlen($notes, 'UTF-8') > 3000) {
            return $this->notice('Les notes de publication sont limitées à 3 000 caractères.', true);
        }
        if (!ModpackUpload::validToken($token) || $size === false || $size < 1 || $size > ModpackUpload::MAX_ARCHIVE_BYTES) {
            return $this->notice('L’archive doit être envoyée avant la publication.', true);
        }
        $upload = $this->upload();
        try {
            $this->prepareStorage();
            $upload->locked(fn () => $this->publishArchive($upload->path($token), $version, $notes, $size));
        } catch (\DomainException | \RuntimeException $e) {
            if ($e instanceof \PDOException) {
                throw $e;
            }
            return $this->notice($e->getMessage(), true);
        }

        return $this->notice('La version ' . $version . ' est publiée.');
    }

    private function publishArchive(string $tmp, string $version, string $notes, int $size): void
    {
        if (@filesize($tmp) !== $size) {
            throw new \DomainException('L’archive est incomplète. Relancez son envoi.');
        }
        set_time_limit(0);
        $hash = @hash_file('sha256', $tmp);
        if ($hash === false) {
            throw new \RuntimeException('Impossible de calculer l’empreinte de l’archive.');
        }

        $lock = @fopen($this->directory() . '/publish.lock', 'c');
        if (!$lock) {
            throw new \RuntimeException('Impossible de préparer la publication.');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Une publication est déjà en cours. Réessayez dans un instant.');
            }
            $previous = $this->packs->current()['archive_key'] ?? null;
            $key = bin2hex(random_bytes(16));
            if (!@rename($tmp, $this->archivePath($key))) {
                throw new \RuntimeException('Impossible de stocker cette archive.');
            }
            // Conserver le fichier en cas d’erreur SQL ambiguë après validation.
            $this->packs->publish($key, $version, $notes, $size, $hash);
            if (is_string($previous) && preg_match('/\A[a-f0-9]{32}\z/', $previous)) {
                @unlink($this->archivePath($previous));
            }
        } finally {
            fclose($lock);
        }
    }

    private function upload(): ModpackUpload
    {
        return new ModpackUpload($this->directory(), (int) Session::get('user_id'));
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

        if ($expected !== '' && !ModpackVersion::isValid($expected)) {
            return $this->notice('La version de référence doit commencer par un numéro, par exemple « ' . ModpackVersion::EXAMPLE . ' ».', true);
        }
        if ($max === false || $max < 0 || $max > 16
            || $threshold === false || $threshold < 1 || $threshold > 16
            || $speed === false || $speed < 1 || $speed > 64
            || ($max > 0 && $threshold > $max)) {
            return $this->notice('Vérifiez le seuil de bridage, le débit et le nombre de places.', true);
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
            ->header('Content-Disposition', ModpackVersion::downloadDisposition($pack['version']))
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
