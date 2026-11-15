<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog as C;

/**
 * Notice PDF d'un avis de recherche, générée à la volée (rien n'est rangé sur le disque) à partir de l'avis, de l'identité
 * FICTIVE et des réglages de la communauté. Ne lit jamais le compte d'un membre : ni nom, ni portrait, ni pseudo ; le portrait
 * et la signature viennent seulement de l'identité (photo du titulaire, photos du dossier) et les champs masqués par le
 * lore (visibilité « masqué » d'une fiche) restent hors du document.
 */
final class SseWantedNoticePdfService
{
    private const LANGUAGES = [
        'fr' => ['français', 'French'], 'en' => ['anglais', 'English'], 'ar' => ['arabe', 'Arabic'], 'ru' => ['russe', 'Russian'],
        'de' => ['allemand', 'German'], 'es' => ['espagnol', 'Spanish'], 'fa' => ['persan', 'Persian'], 'ps' => ['pachto', 'Pashto'],
        'uk' => ['ukrainien', 'Ukrainian'], 'el' => ['grec', 'Greek'], 'tr' => ['turc', 'Turkish'], 'zh' => ['chinois', 'Chinese'],
        'it' => ['italien', 'Italian'], 'pt' => ['portugais', 'Portuguese'], 'pl' => ['polonais', 'Polish'],
    ];

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseFileRecordRepository $records = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseWantedNoticePdf $template = null,
        private ?SseWantedAttachments $attachments = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->template ??= new SseWantedNoticePdf();
    }

    /** Nom de fichier sûr, fabriqué ici : « avis-A-0123-2026-fr.pdf ». */
    public static function filename(string $ref, string $lang): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ref), '-');

        return 'avis-' . ($safe !== '' ? $safe : 'notice') . '-' . C::lang($lang) . '.pdf';
    }

    /** Langue de la notice : choix explicite, sinon réglage de la communauté, sinon langue du lecteur. */
    public static function resolveLang(string $requested, string $configured, string $reader): string
    {
        if (in_array($requested, ['fr', 'en'], true)) {
            return $requested;
        }
        if (in_array($configured, ['fr', 'en'], true)) {
            return $configured;
        }

        return C::lang($reader);
    }

    /**
     * Données du gabarit pour un avis.
     *
     * @param array<string, mixed> $notice avis (SseWantedNoticeRepository::hydrate)
     * @param string $faces « auto », « 1 » ou « 2 »
     * @return array<string, mixed>|null null si l'identité n'appartient pas à la communauté
     */
    public function data(int $tenantId, array $notice, string $lang, string $faces = 'auto'): ?array
    {
        $identity = $this->identities->find((int) $notice['identity_id'], $tenantId);
        if ($identity === null || (int) $notice['tenant_id'] !== $tenantId) {
            return null;
        }
        $lang = C::lang($lang);
        $config = $this->settings->get($tenantId);
        $profile = $this->safe(fn (): ?array => ($this->records ??= new SseFileRecordRepository())->profile($tenantId, (int) $identity['id']));
        $known = static fn (string $field): bool => ($profile['visibility'][$field]['v'] ?? 'known') !== 'hidden';
        $value = static fn (string $field): mixed => $profile !== null && $known($field) ? ($profile[$field] ?? null) : null;

        $aliases = [];
        if (($identity['alias'] ?? null) !== null && $identity['alias'] !== '') {
            $aliases[] = (string) $identity['alias'];
        }
        $fileAliases = $this->safe(fn (): array => ($this->records ??= new SseFileRecordRepository())->aliasesFor($tenantId, [(int) $identity['id']])[(int) $identity['id']] ?? []);
        foreach (is_array($fileAliases) ? $fileAliases : [] as $a) {
            if (($a['visibility'] ?? 'known') === 'known' && ($a['alias'] ?? '') !== '' && !in_array($a['alias'], $aliases, true)) {
                $aliases[] = (string) $a['alias'];
            }
        }

        $hairKey = (string) ($value('hair') ?? '');
        $eyesKey = (string) ($value('eyes') ?? '');
        $marks = $value('marks');
        $lg = trim((string) ($identity['language'] ?? ''));
        $banner = C::BANNERS[(string) $config['banner']] ?? C::BANNERS['red'];
        // Service demandeur de l'avis : instantané du jour de la pose (autorité, sigle, couleur d'en-tête).
        $meta = $this->safe(fn (): array => (new \App\Repositories\SseWantedMetaRepository())->forNotice($tenantId, (int) $notice['id'])) ?? \App\Repositories\SseWantedMetaRepository::hydrate(null);
        $snap = is_array($meta['service'] ?? null) ? $meta['service'] : [];
        $hex = (string) ($snap['color'] ?? '');
        $rgb = preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m) === 1 ? [(int) hexdec($m[1]), (int) hexdec($m[2]), (int) hexdec($m[3])] : null;
        $service = $snap !== [] ? ['code' => (string) ($snap['code'] ?? ''), 'ac' => (string) ($snap['ac'] ?? ''), 'color' => $rgb, 'name' => (string) ($snap[$lang === 'en' ? 'en' : 'fr'] ?? '')] : null;
        // Bandeaux de classification américains (fictifs) : le niveau de l'avis, sinon celui par défaut de l'échelle.
        $classCode = SseUsClassification::normalize($meta['classification'] ?? null);
        $classification = ['code' => $classCode, 'banner' => SseUsClassification::banner($classCode), 'rgb' => SseUsClassification::rgb($classCode)];
        // Motifs, mesures, signalement et pièces de l'avis (pièces : droits du lecteur, rien pour une identité de membre non autorisée).
        $wanted = SseWantedNoticeData::forPdf($notice, $lang, $this->attachments);
        $attached = $this->attachedImages($wanted['attachments'], $lang);
        $ref = (string) $notice['ref'];
        $expires = $notice['expires_at'] ?? null;
        $extra = [];
        $photo = null;
        // Image d'un membre : TRÈS SECRET (TS). Sans autorisation (ou sans trace au journal d'audit), l'avis part sans photo ni signature.
        $imagesOk = $this->imagesAllowed($tenantId, $identity);
        foreach ($imagesOk ? $this->photoCandidates($tenantId, $identity) : [] as $path) {
            $bytes = self::imageBytes($path, false);
            if ($bytes === null) {
                continue;
            }
            if ($photo === null) {
                $photo = $bytes;
            } elseif (count($extra) < 3) {
                $extra[] = $bytes;
            }
        }
        $signature = $imagesOk && ($identity['signature_path'] ?? null) !== null ? self::imageBytes((string) $identity['signature_path'], true) : null;
        $emblem = null;
        if ($config['emblem'] === 'community') {
            try {
                $emblem = AthenaPdfTheme::meta(['tenant_id' => $tenantId])['logo'];
            } catch (\Throwable) {
                $emblem = null;
            }
        }

        // Notice de couleur (cadre plein, titre du type), service à informer et adresse de signalement (QR code).
        $color = \App\Support\SseAlertLevel::noticeColor((string) $notice['notice_type'], $config);
        if ($color !== null) {
            $banner = $color['title'];
        }
        $alerts = new SseAlertService(null, null, $this->settings);
        $report = $this->safe(fn (): array => $alerts->noticeReport($tenantId, $notice)) ?? [];
        $link = (string) ($notice['status'] ?? 'active') === 'active' ? $this->safe(fn (): ?array => $alerts->noticeLink($tenantId, $notice)) : $this->safe(fn (): ?array => $alerts->alerts()->linkFor($tenantId, \App\Repositories\SseAlertRepository::DOC_NOTICE, (int) $notice['id']));
        if (is_array($link) && ($link['report'] ?? []) !== []) {
            $report = \App\Support\SseAlertReportTo::read($link['report']);
        }
        if (($report['code'] ?? '') !== '') {
            $report['emblem_bytes'] = SseSheetService::serviceEmblem($tenantId, (string) $report['code']);
        }
        $reportUrl = is_array($link) && ($link['revoked_at'] ?? null) === null ? \App\Support\SseAlertReportTo::url((string) $link['token']) : '';
        // Véhicules et personnes associées : libellés courts des fichiers, personnes selon la classification de l'avis, jamais un membre.
        $vehicles = [];
        $associates = [];
        $fileLinks = $this->safe(fn (): array => ($this->records ??= new SseFileRecordRepository())->linksFor($tenantId, [(int) $identity['id']])[(int) $identity['id']] ?? []);
        foreach (is_array($fileLinks) ? $fileLinks : [] as $fl) {
            if (($fl['visibility'] ?? 'known') === 'hidden' || trim((string) ($fl['value'] ?? '')) === '') {
                continue;
            }
            if (($fl['kind'] ?? '') === 'vehicle' && count($vehicles) < 3) {
                $vehicles[] = (string) $fl['value'];
            } elseif (($fl['kind'] ?? '') === 'associate' && count($associates) < 3 && SseRedactionService::visibleAt('identite', $classCode) && !$this->isMemberIdentity($tenantId, (int) ($fl['linked_identity_id'] ?? 0))) {
                $associates[] = (string) $fl['value'];
            }
        }
        $measureCodes = array_map(static fn (array $c): string => (string) $c['code'], $wanted['measures']);

        return [
            'lang' => $lang,
            'notice_color' => $color,
            'report' => $report,
            'report_url' => $reportUrl,
            'vehicles' => $vehicles,
            'associates' => $associates,
            'flight_risk' => (string) $notice['notice_type'] === 'escaped' || array_intersect($measureCodes, ['A-EXF', 'A-CHK', 'A-NAV']) !== [],
            'banner' => [$banner[0], $banner[1]],
            'authority' => [(string) ($snap['auth_fr'] ?? $config['authority_fr']), (string) ($snap['auth_en'] ?? $config['authority_en'])],
            'service' => $service,
            'classification' => $classification,
            'reasons' => $wanted['reasons'],
            'measures' => $wanted['measures'],
            'physical_rows' => $wanted['physical_rows'],
            'physical_line' => $wanted['physical_line'],
            'attached' => $attached,
            'ref' => $ref,
            'issued_at' => (string) $notice['issued_at'],
            'expires_at' => $expires !== null ? (string) $expires : null,
            'last_name' => (string) $identity['last_name'],
            'first_name' => (string) $identity['first_name'],
            'sex' => in_array((string) $identity['sex'], ['M', 'F'], true) ? (string) $identity['sex'] : 'X',
            'birth_date' => $identity['birth_date'] !== null ? (string) $identity['birth_date'] : null,
            'birth_place' => $identity['birth_place'],
            'nationality' => $this->nationality($tenantId, (string) ($identity['nationality'] ?? ''), $lang),
            'height_cm' => is_int($value('height_cm')) ? $value('height_cm') : null,
            'weight_kg' => is_int($value('weight_kg')) ? $value('weight_kg') : null,
            'eyes' => $eyesKey !== '' ? ($lang === 'en' ? self::EYES_EN[$eyesKey] ?? $eyesKey : (S::EYES[$eyesKey] ?? $eyesKey)) : '',
            'hair' => $hairKey !== '' ? ($lang === 'en' ? self::HAIR_EN[$hairKey] ?? $hairKey : (S::HAIR[$hairKey] ?? $hairKey)) : '',
            'marks' => is_string($marks) ? $marks : '',
            'aliases' => array_slice($aliases, 0, 8),
            'languages' => $lg !== '' ? (self::LANGUAGES[strtolower($lg)][$lang === 'en' ? 1 : 0] ?? strtoupper($lg)) : '',
            'type_label' => C::typeLabel((string) $notice['notice_type'], $config, $lang),
            'threat_level' => (string) $notice['threat_level'],
            'threat_label' => C::label(C::THREATS, (string) $notice['threat_level'], $lang),
            'armed' => (string) $notice['armed'],
            'reason' => (string) ($notice['reason'] ?? ''),
            'instructions' => (string) ($notice['instructions'] ?? ''),
            'zone' => (string) ($notice['zone'] ?? ''),
            'last_seen_place' => (string) ($notice['last_seen_place'] ?? ''),
            'last_seen_at' => $notice['last_seen_at'] ?? null,
            'contact_label' => (string) ($notice['contact_label'] ?? ''),
            'contact_detail' => (string) ($notice['contact_detail'] ?? ''),
            'photo' => $photo ?? $attached['main'],
            'extra_photos' => $extra,
            'signature' => $signature,
            'emblem_path' => is_string($emblem) ? $emblem : null,
            'faces' => in_array($faces, ['1', '2'], true) ? $faces : 'auto',
            'generated_at' => gmdate($lang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i') . ' UTC',
        ];
    }

    /**
     * Image principale (pleine) et jusqu'à 3 miniatures légendées des pièces de l'avis, en octets JPEG.
     *
     * @param list<array<string, mixed>> $attachments
     * @return array{main: ?string, thumbs: list<array{bytes: string, caption: string}>}
     */
    private function attachedImages(array $attachments, string $lang): array
    {
        $main = null;
        $thumbs = [];
        foreach ($attachments as $a) {
            $isMain = !empty($a['is_main']);
            if ($isMain && $main === null) {
                $main = self::imageBytes((string) $a['file'], false, true);
                if ($main !== null) {
                    continue;
                }
            }
            if (count($thumbs) < 3) {
                $bytes = self::imageBytes((string) ($a['thumb_file'] ?? $a['file']), false, true);
                if ($bytes !== null) {
                    $thumbs[] = ['bytes' => $bytes, 'caption' => trim((string) ($a['caption'] ?? '')) !== '' ? (string) $a['caption'] : (string) ($a[$lang === 'en' ? 'kind_en' : 'kind_fr'] ?? '')];
                }
            }
        }

        return ['main' => $main, 'thumbs' => $thumbs];
    }

    private const EYES_EN = ['brown' => 'Brown', 'black' => 'Black', 'hazel' => 'Hazel', 'green' => 'Green', 'blue' => 'Blue', 'gray' => 'Grey'];

    private const HAIR_EN = ['black' => 'Black', 'brown' => 'Brown', 'blond' => 'Blond', 'red' => 'Red', 'gray' => 'Grey', 'white' => 'White', 'bald' => 'Bald or shaved'];

    /**
     * PDF de la notice : octets et nom de fichier, ou null (moteur PDF indisponible, avis d'une autre communauté).
     *
     * @param array<string, mixed> $notice
     * @return array{bytes: string, filename: string}|null
     */
    public function build(int $tenantId, array $notice, string $lang, string $faces = 'auto'): ?array
    {
        $data = $this->data($tenantId, $notice, $lang, $faces);
        if ($data === null) {
            return null;
        }
        $bytes = $this->template->render($data);
        if ($bytes === null) {
            return null;
        }

        return ['bytes' => $bytes, 'filename' => self::filename((string) $notice['ref'], (string) $data['lang'])];
    }

    /** Cette identité liée est-elle celle d'un membre (jamais nommée sur une notice) ? Identité introuvable : oui (fail-closed). */
    private function isMemberIdentity(int $tenantId, int $identityId): bool
    {
        if ($identityId < 1) {
            return false;
        }
        $row = $this->safe(fn (): ?array => $this->identities->find($identityId, $tenantId));

        return !is_array($row) || (int) ($row['user_id'] ?? 0) > 0;
    }

    /** @param array<string, mixed> $identity */
    private function imagesAllowed(int $tenantId, array $identity): bool
    {
        $gate = SseMemberImageGate::forSession();
        $access = $gate->forIdentity($tenantId, $identity);
        if (!$access['allowed']) {
            $gate->recordDenied($tenantId, $gate->viewerId(), 'wanted_pdf', (int) ($identity['id'] ?? 0), $access['owner']);

            return false;
        }
        if ($access['confidential'] && $access['reason'] !== SseMemberImageGate::WHY_HOLDER) {
            try {
                $gate->recordConsultation($tenantId, (int) $gate->viewerId(), 'wanted_pdf', (int) ($identity['id'] ?? 0), $access['owner'], $access['reason']);
            } catch (\Throwable $e) {
                error_log('Avis de recherche : consultation d’une image confidentielle non journalisée, images omises : ' . $e->getMessage());

                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $identity @return list<string> */
    private function photoCandidates(int $tenantId, array $identity): array
    {
        $out = [];
        if (($identity['photo_path'] ?? null) !== null) {
            $out[] = (string) $identity['photo_path'];
        }
        $photos = $this->safe(fn (): array => ($this->records ??= new SseFileRecordRepository())->photosFor($tenantId, [(int) $identity['id']])[(int) $identity['id']] ?? []);
        foreach (is_array($photos) ? $photos : [] as $p) {
            if (($p['visibility'] ?? 'known') !== 'hidden' && !in_array((string) $p['path'], $out, true)) {
                $out[] = (string) $p['path'];
            }
        }

        return array_slice($out, 0, 6);
    }

    private function nationality(int $tenantId, string $code, string $lang): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }
        $row = $this->safe(fn (): ?array => ($this->countries ??= new SseIdCountryRepository())->findByCode($tenantId, $code));
        if (is_array($row)) {
            $name = $lang === 'en' && ($row['name_en'] ?? '') !== '' ? (string) $row['name_en'] : (string) ($row['name'] ?? '');
            if ($name !== '') {
                return $name . ' (' . $code . ')';
            }
        }

        return $code;
    }

    /**
     * Octets JPEG d'une image rangée sous public/uploads (jamais un autre chemin), réduits et aplatis sur fond blanc. Sans GD,
     * seul un JPEG est repris tel quel.
     */
    public static function imageBytes(string $path, bool $flatten, bool $absolute = false): ?string
    {
        // $absolute : chemin déjà résolu par SseImageStorage (pièces d'avis), jamais un chemin venu d'une requête.
        $file = $absolute ? (is_file($path) ? $path : null) : \App\Support\SseImageStorage::readable($path);
        if ($file === null || filesize($file) > 6 * 1024 * 1024) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        if (!function_exists('imagecreatefromstring')) {
            return str_starts_with($raw, "\xFF\xD8\xFF") ? $raw : null;
        }
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, 900 / max(1, max($w, $h)));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $canvas = imagecreatetruecolor($nw, $nh);
        if ($canvas === false) {
            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white !== false) {
            imagefill($canvas, 0, 0, $white);
        }
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($canvas, null, $flatten ? 90 : 86);
        $out = (string) ob_get_clean();

        return $out !== '' ? $out : null;
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T|null
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
