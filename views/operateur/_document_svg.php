<?php

declare(strict_types=1);

/**
 * Aperçu d'un papier d'identité (recto et verso), dessiné en SVG à partir des données du papier et du pays émetteur.
 * Définit $opxDocSvg(array $doc, string $side, ?string $photo): string. Tout texte est échappé ; les couleurs du pays
 * ne sont acceptées qu'en hexadécimal. Mention SPÉCIMEN · SIMULATION sur les deux faces.
 * Mise en page selon le type (App\Support\SseDocumentTypes, miroir du mod) : sans photo si le type n'en porte pas, bande
 * de lecture au bas du recto (passeport) ou au verso, aucune bande pour un type sans MRZ. Un type à une seule face n'a
 * pas de verso (les vues ne le demandent pas).
 *
 * $doc : OperatorIdentityService::documents() ; $photo : adresse ou donnée intégrée de la photo, null = silhouette.
 */
$opxDocSvg = static function (array $doc, string $side = 'recto', ?string $photo = null): string {
    $x = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $hex = static fn (mixed $v, string $default): string => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : $default;
    $byType = ['id_card' => ['#1e3a5f', '#c9d8ea'], 'passport' => ['#5b1f2a', '#ead0d4'], 'driving_license' => ['#7a3d6a', '#f0d9ea'], 'military_id' => ['#3b4a2f', '#dbe3cf'],
        'residence_permit' => ['#1f4e5a', '#cfe3e6'], 'vehicle_registration' => ['#4a4a55', '#dcdce4']];
    [$defA, $defB] = $byType[(string) ($doc['doc_type'] ?? '')] ?? $byType['id_card'];
    $main = $hex($doc['color_primary'] ?? null, $defA);
    $soft = $hex($doc['color_secondary'] ?? null, $defB);
    $dash = static fn (mixed $v): string => $v !== null && trim((string) $v) !== '' ? (string) $v : '—';
    $date = static fn (mixed $v): string => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v, $m) === 1 ? $m[3] . '.' . $m[2] . '.' . $m[1] : '—';
    $uid = 'opxd' . (int) ($doc['id'] ?? 0) . $side[0];
    $title = $x(($doc['doc_type_label'] ?? 'Papier') . ' · ' . ($doc['country_name'] ?? '') . ' · ' . ($side === 'verso' ? 'verso' : 'recto'));
    $type = \App\Support\SseDocumentTypes::get((string) ($doc['doc_type'] ?? '')) ?? [];
    $hasPhoto = (bool) ($type['photo'] ?? true);
    $hasMrz = (string) ($type['mrz'] ?? 'td1') !== '';
    $mrzRecto = $hasMrz && ($type['mrz_side'] ?? 'verso') === 'recto';
    $mrz = static function (int $y, int $step, int $size, int $max) use ($doc, $x): string {
        $out = '';
        foreach (array_slice(array_values((array) ($doc['mrz'] ?? [])), 0, $max) as $line) {
            $out .= '<text x="36" y="' . $y . '" font-family="\'OCR B\', \'JetBrains Mono\', \'Courier New\', monospace" font-size="' . $size . '" fill="#101827" textLength="784" lengthAdjust="spacing">' . $x((string) $line) . '</text>';
            $y += $step;
        }

        return $out;
    };
    $field = static function (int $px, int $py, string $label, string $value, int $size = 22) use ($x): string {
        return '<text x="' . $px . '" y="' . $py . '" font-size="15" fill="#4b5563" letter-spacing="1">' . $x(mb_strtoupper($label)) . '</text>'
            . '<text x="' . $px . '" y="' . ($py + $size + 4) . '" font-size="' . $size . '" font-weight="700" fill="#101827">' . $x(mb_strimwidth($value, 0, 30, '…')) . '</text>';
    };
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 856 540" role="img" aria-label="' . $title . '" class="opx-doc-svg" font-family="Inter, Arial, Helvetica">'
        . '<defs><clipPath id="' . $uid . 'c"><rect width="856" height="540" rx="28"/></clipPath>'
        . '<pattern id="' . $uid . 'g" width="28" height="28" patternUnits="userSpaceOnUse"><path d="M0 14h28M14 0v28" stroke="' . $x($main) . '" stroke-opacity=".07" stroke-width="1"/></pattern></defs>'
        . '<g clip-path="url(#' . $uid . 'c)">'
        . '<rect width="856" height="540" fill="#f7f8fa"/><rect width="856" height="540" fill="' . $x($soft) . '" fill-opacity=".45"/><rect width="856" height="540" fill="url(#' . $uid . 'g)"/>';
    if ($side !== 'verso') {
        $svg .= '<rect width="856" height="92" fill="' . $x($main) . '"/>'
            . '<text x="36" y="42" font-size="24" font-weight="800" fill="#ffffff" letter-spacing="2">' . $x(mb_strtoupper(mb_strimwidth((string) ($doc['country_name'] ?? ''), 0, 34, '…'))) . '</text>'
            . '<text x="36" y="72" font-size="17" fill="#ffffff" fill-opacity=".85" letter-spacing="1.5">' . $x(mb_strtoupper((string) ($doc['doc_type_label'] ?? ''))) . '</text>'
            . '<text x="820" y="56" font-size="30" font-weight="800" fill="#ffffff" fill-opacity=".9" text-anchor="end" letter-spacing="3">' . $x((string) ($doc['country_code'] ?? '')) . '</text>';
        // Bande au recto (passeport) : la photo et la ligne du bas remontent pour lui laisser le bas de la page.
        $ph = $mrzRecto ? 250 : 275;
        $rowY = $mrzRecto ? 398 : 432;
        // Sans photo : les champs partent du bord gauche et occupent toute la largeur.
        [$c1, $c2, $c3] = $hasPhoto ? [282, 500, 620] : [36, 330, 580];
        if ($hasPhoto) {
            $svg .= '<rect x="36" y="124" width="214" height="' . $ph . '" rx="10" fill="#e5e9ef" stroke="' . $x($main) . '" stroke-opacity=".35"/>';
            if ($photo !== null && $photo !== '') {
                $svg .= '<clipPath id="' . $uid . 'p"><rect x="36" y="124" width="214" height="' . $ph . '" rx="10"/></clipPath>'
                    . '<image x="36" y="124" width="214" height="' . $ph . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#' . $uid . 'p)" href="' . $x($photo) . '" xlink:href="' . $x($photo) . '"/>';
            } else {
                $svg .= '<circle cx="143" cy="' . ($ph - 50) . '" r="46" fill="#b6bfcc"/><path d="M63 ' . (124 + $ph) . 'c0-52 36-88 80-88s80 36 80 88z" fill="#b6bfcc"/>';
            }
        }
        $svg .= $field($c1, 146, 'Nom', mb_strtoupper((string) ($doc['last_name'] ?? '')), 28)
            . $field($c1, 218, 'Prénom', (string) ($doc['first_name'] ?? ''), 24)
            . $field($c1, 286, 'Né(e) le', $date($doc['birth_date'] ?? null))
            . $field($c2, 286, 'Sexe', $dash($doc['sex'] ?? null))
            . $field($c3, 286, 'Nationalité', $dash($doc['nationality'] ?? null))
            . $field($c1, 350, 'Lieu de naissance', $dash($doc['birth_place'] ?? null))
            . $field(36, $rowY, 'N° du document', $dash($doc['doc_number'] ?? null), 26)
            . $field(390, $rowY, 'Délivré le', $date($doc['issued_on'] ?? null))
            . $field(600, $rowY, 'Expire le', $date($doc['expires_on'] ?? null));
        if ($mrzRecto) {
            $svg .= '<rect x="0" y="444" width="856" height="96" fill="#ffffff" fill-opacity=".86"/>' . $mrz(482, 38, 22, 2);
        }
    } else {
        $eyes = \App\Support\SseFileSubjectCatalog::EYES[(string) ($doc['eyes'] ?? '')] ?? '—';
        $hair = \App\Support\SseFileSubjectCatalog::HAIR[(string) ($doc['hair'] ?? '')] ?? '—';
        $svg .= '<rect y="0" width="856" height="14" fill="' . $x($main) . '"/>'
            . $field(36, 62, 'Autorité', $dash($doc['authority'] ?? null))
            . $field(36, 136, 'Taille', ($doc['height_cm'] ?? null) !== null ? (int) $doc['height_cm'] . ' cm' : '—')
            . $field(250, 136, 'Yeux', $eyes)
            . $field(464, 136, 'Cheveux', $hair)
            . $field(36, 210, 'Référence Athena', (string) ($doc['ref'] ?? ''))
            . $field(464, 210, 'Titulaire', (string) ($doc['holder_name'] ?? ''));
        if ($hasMrz) {
            $svg .= '<rect x="0" y="352" width="856" height="188" fill="#ffffff" fill-opacity=".86"/>' . $mrz(404, 44, 25, 3);
        }
    }
    $svg .= '<text x="428" y="300" font-size="46" font-weight="800" fill="#b91c1c" fill-opacity=".2" text-anchor="middle" letter-spacing="4" transform="rotate(-18 428 270)">SPÉCIMEN · SIMULATION</text>'
        . '</g><rect x="1" y="1" width="854" height="538" rx="27" fill="none" stroke="#0f172a" stroke-opacity=".18" stroke-width="2"/></svg>';

    return $svg;
};
