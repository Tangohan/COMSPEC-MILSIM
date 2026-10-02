<?php
/**
 * Préparation commune des gabarits de brevet (moderne, classique).
 * Produit $b : valeurs déjà échappées, prêtes à afficher. Compatible Dompdf
 * (pas de flex/grid : positions absolues et tableaux uniquement).
 *
 * @var array $award
 * @var string $holder_name
 * @var string $certificate_number
 * @var string|null $badge_path
 */

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ucfirst = static fn (string $v): string => $v === '' ? '' : mb_strtoupper(mb_substr($v, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($v, 1, null, 'UTF-8');

$tenant = trim((string) ($tenant_name ?? ''));
$tenant = $tenant !== '' ? $tenant : 'ATHENA';
$qualification = trim((string) ($qualification_name ?? ''));
$level = trim((string) ($level_name ?? ''));
$category = trim((string) ($category_name ?? ''));
$issuer = trim((string) ($issuer_name ?? ''));
$grade = trim((string) ($holder_grade ?? ''));
$callsign = trim((string) ($holder_callsign ?? ''));
$expires = trim((string) ($expires_at ?? ''));
$permanent = !empty($is_permanent) || $expires === '' || $expires === '—';

$holderLine = implode(' · ', array_filter([$grade, $callsign !== '' ? '« ' . $callsign . ' »' : ''], static fn (string $v): bool => $v !== ''));

// Statut : couleur selon l’état temporel de la qualification.
$status = trim((string) ($temporal_label ?? '')) ?: 'Valide';
$statusKey = mb_strtolower($status, 'UTF-8');
if (str_contains($statusKey, 'prochaine') || str_contains($statusKey, 'grâce')) {
    $statusColors = ['#fdf3e2', '#8a5a06'];
} elseif (str_contains($statusKey, 'expir') || str_contains($statusKey, 'retir') || str_contains($statusKey, 'suspend')) {
    $statusColors = ['#fdecec', '#b42727'];
} else {
    $statusColors = ['#e6f6ef', '#0b6e4a'];
}
// Pastille courte : « Expiration prochaine — expire dans 5 jours » → « Expiration prochaine ».
$statusShort = trim((string) preg_split('/\s+[—(]/u', $status)[0]);

// Insigne : type MIME réel (les JPG/WebP étaient annoncés en PNG).
$badgeSrc = '';
if (!empty($badge_path) && is_file((string) $badge_path)) {
    $bdgMime = '';
    if (str_ends_with(strtolower((string) $badge_path), '.svg')) {
        $bdgMime = 'image/svg+xml';
    } else {
        $bdgInfo = @getimagesize((string) $badge_path);
        $bdgMime = is_array($bdgInfo) && !empty($bdgInfo['mime']) ? (string) $bdgInfo['mime'] : 'image/png';
    }
    $bdgRaw = @file_get_contents((string) $badge_path);
    // Dompdf ne lit ni les PNG entrelacés ni le WebP : on ré-encode en PNG standard (≤ 320 px).
    if ($bdgRaw !== false && $bdgRaw !== '' && $bdgMime !== 'image/svg+xml' && function_exists('imagecreatefromstring')) {
        $bdgImg = @imagecreatefromstring($bdgRaw);
        if ($bdgImg !== false) {
            $bdgW = imagesx($bdgImg);
            $bdgH = imagesy($bdgImg);
            $bdgScale = min(1, 320 / max(1, $bdgW, $bdgH));
            $bdgNw = max(1, (int) round($bdgW * $bdgScale));
            $bdgNh = max(1, (int) round($bdgH * $bdgScale));
            // Toile carrée transparente : l’insigne garde ses proportions dans le médaillon rond.
            $bdgSide = max($bdgNw, $bdgNh);
            $bdgOut = imagecreatetruecolor($bdgSide, $bdgSide);
            imagealphablending($bdgOut, false);
            imagesavealpha($bdgOut, true);
            imagefill($bdgOut, 0, 0, imagecolorallocatealpha($bdgOut, 0, 0, 0, 127));
            imagecopyresampled($bdgOut, $bdgImg, (int) (($bdgSide - $bdgNw) / 2), (int) (($bdgSide - $bdgNh) / 2), 0, 0, $bdgNw, $bdgNh, $bdgW, $bdgH);
            imageinterlace($bdgOut, false);
            ob_start();
            imagepng($bdgOut);
            $bdgPng = (string) ob_get_clean();
            imagedestroy($bdgImg);
            imagedestroy($bdgOut);
            if ($bdgPng !== '') {
                $bdgRaw = $bdgPng;
                $bdgMime = 'image/png';
            }
        }
    }
    if ($bdgRaw !== false && $bdgRaw !== '') {
        $badgeSrc = 'data:' . $bdgMime . ';base64,' . base64_encode($bdgRaw);
    }
}
$badgeCode = trim((string) ($award['level_short_name'] ?? '')) ?: trim((string) ($award['definition_short_name'] ?? '')) ?: trim((string) ($award['definition_code'] ?? ''));
$badgeCode = mb_strtoupper(mb_substr($badgeCode !== '' ? $badgeCode : 'QUAL', 0, 8, 'UTF-8'), 'UTF-8');

$b = [
    'tenant' => $e($tenant),
    'tenant_upper' => $e(mb_strtoupper($tenant, 'UTF-8')),
    'qualification' => $e($qualification !== '' ? $qualification : 'Qualification'),
    'level' => $e($ucfirst($level)),
    'category' => $e(mb_strtoupper($category !== '' ? $category : 'Qualification', 'UTF-8')),
    'holder' => $e($holder_name ?? ''),
    'holder_line' => $e($holderLine),
    'issuer' => $e($issuer !== '' ? $issuer : $tenant),
    'obtained' => $e(($obtained_at ?? '') !== '' ? $obtained_at : '—'),
    'validity' => $e($permanent ? 'Permanente' : 'Jusqu’au ' . $expires),
    'number' => $e($certificate_number ?? ''),
    'status' => $e($statusShort !== '' ? $statusShort : $status),
    'status_full' => $e($status),
    'status_bg' => $statusColors[0],
    'status_fg' => $statusColors[1],
    'badge_src' => $badgeSrc,
    'badge_code' => $e($badgeCode),
    'generated' => $e($generated_at ?? ''),
];
unset($bdgSide, $bdgImg, $bdgOut, $bdgPng, $bdgRaw, $bdgMime, $bdgInfo, $bdgScale, $bdgNw, $bdgNh, $bdgW, $bdgH);
