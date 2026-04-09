<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Corps HTML du premier message d’un sujet forum lié à une offre (textes échappés).
 */
final class RecruitmentOpeningForumAnnouncementHtml
{
    /**
     * @param array<string, mixed> $opening Ligne offre + unit_name, etc. (publiée)
     * @param array<string, mixed> $tenant Ligne tenant (slug, name)
     * @param array{href_fiche: string, href_candidater: string} $links
     */
    public static function build(array $opening, array $tenant, array $links, ?string $jobRoleName): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $title = trim((string) ($opening['title'] ?? ''));
        $ref = trim((string) ($opening['reference_public'] ?? ''));
        $unit = trim((string) ($opening['unit_name'] ?? ''));
        $community = trim((string) ($tenant['name'] ?? ''));

        $cat = RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($opening['personnel_category'] ?? 'other'));
        $arm = RecruitmentOpeningPresentation::armDomainLabel(isset($opening['arm_domain']) ? (string) $opening['arm_domain'] : null);
        $clear = RecruitmentOpeningPresentation::clearanceLabel((string) ($opening['clearance_level'] ?? 'none'));
        $contract = trim((string) ($opening['employment_contract_label'] ?? ''));
        $context = trim((string) ($opening['employment_context_label'] ?? ''));

        $jobLine = '';
        if ($jobRoleName !== null && trim($jobRoleName) !== '') {
            $jobLine = '<p class="rofa-meta"><strong>Emploi métier (référentiel)</strong> — ' . $e(trim($jobRoleName)) . '</p>';
        }

        $summary = trim((string) ($opening['summary'] ?? ''));
        $missionLead = trim((string) ($opening['mission_lead'] ?? ''));
        $description = trim((string) ($opening['description'] ?? ''));
        $technical = trim((string) ($opening['technical_notice'] ?? ''));

        $req = self::decodeJsonList($opening['requirements_json'] ?? null);
        $profile = self::decodeProfileItems($opening['candidate_profile_items'] ?? null);
        $blocks = self::decodeBlocks($opening['responsibility_blocks'] ?? null);

        $parts = [];
        $parts[] = '<div class="rofa-wrap" style="font-family:system-ui,sans-serif;line-height:1.5;color:#0f172a">';
        $parts[] = '<div style="border-left:4px solid #0f766e;padding:12px 16px;background:#f0fdfa;margin-bottom:16px">';
        $parts[] = '<p style="margin:0;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#115e59;font-weight:700">Avis de poste</p>';
        if ($ref !== '') {
            $parts[] = '<p style="margin:8px 0 0;font-family:ui-monospace,monospace;font-size:13px;color:#134e4a">' . $e($ref) . '</p>';
        }
        $parts[] = '<h2 style="margin:12px 0 0;font-size:1.25rem;font-weight:800">' . $e($title) . '</h2>';
        if ($unit !== '') {
            $parts[] = '<p style="margin:6px 0 0;font-size:14px;color:#475569"><strong>Unité</strong> — ' . $e($unit) . '</p>';
        }
        if ($community !== '') {
            $parts[] = '<p style="margin:4px 0 0;font-size:13px;color:#64748b">' . $e($community) . '</p>';
        }
        $parts[] = '</div>';

        $parts[] = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px">';
        $parts[] = self::metaCell('Catégorie', $e($cat));
        $parts[] = self::metaCell('Arme / domaine', $e($arm));
        $parts[] = self::metaCell('Engagement', $e($contract !== '' ? $contract : '—'));
        $parts[] = self::metaCell('Habilitation', $e($clear));
        $parts[] = '</div>';
        if ($context !== '') {
            $parts[] = '<p style="margin:0 0 12px;font-size:14px"><strong>Contexte d’emploi</strong> — ' . $e($context) . '</p>';
        }
        $parts[] = $jobLine;

        $parts[] = '<p style="margin:16px 0 12px">';
        $parts[] = '<a href="' . $e($links['href_fiche']) . '" style="display:inline-block;margin-right:12px;padding:10px 16px;background:#0f172a;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px">Voir la fiche complète</a>';
        $parts[] = '<a href="' . $e($links['href_candidater']) . '" style="display:inline-block;padding:10px 16px;background:#047857;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px">Candidater</a>';
        $parts[] = '</p>';

        if ($summary !== '') {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Accroche</h3>';
            $parts[] = '<p style="margin:0 0 12px;white-space:pre-wrap">' . nl2br($e($summary)) . '</p>';
        }
        if ($missionLead !== '') {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Mission</h3>';
            $parts[] = '<p style="margin:0 0 12px;white-space:pre-wrap">' . nl2br($e($missionLead)) . '</p>';
        }
        if ($description !== '') {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Description</h3>';
            $parts[] = '<div style="margin:0 0 12px;white-space:pre-wrap">' . nl2br($e($description)) . '</div>';
        }

        if ($req !== []) {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Exigences</h3><ul style="margin:0 0 12px;padding-left:1.25rem">';
            foreach ($req as $line) {
                $parts[] = '<li style="margin:4px 0">' . $e((string) $line) . '</li>';
            }
            $parts[] = '</ul>';
        }

        if ($profile !== []) {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Profil candidat</h3>';
            $parts[] = '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:12px">';
            foreach ($profile as $row) {
                $rub = $e((string) ($row['rubrique'] ?? ''));
                $det = nl2br($e((string) ($row['detail'] ?? '')));
                $parts[] = '<tr><td style="vertical-align:top;padding:6px 8px;border:1px solid #e2e8f0;font-weight:600;width:32%">' . $rub . '</td>';
                $parts[] = '<td style="vertical-align:top;padding:6px 8px;border:1px solid #e2e8f0">' . $det . '</td></tr>';
            }
            $parts[] = '</table>';
        }

        if ($blocks !== []) {
            $parts[] = '<h3 style="margin:20px 0 8px;font-size:15px;font-weight:800;color:#334155">Axes du poste</h3>';
            $n = 0;
            foreach ($blocks as $b) {
                $n++;
                $th = $e(trim((string) ($b['theme'] ?? '')));
                $ti = $e(trim((string) ($b['titre'] ?? '')));
                $co = nl2br($e(trim((string) ($b['corps'] ?? ''))));
                $parts[] = '<div style="margin:0 0 12px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc">';
                $parts[] = '<p style="margin:0 0 4px;font-size:11px;font-weight:800;color:#64748b">' . $e(sprintf('%02d', $n)) . ($th !== '' ? ' · ' . $th : '') . '</p>';
                if ($ti !== '') {
                    $parts[] = '<p style="margin:0 0 6px;font-weight:700">' . $ti . '</p>';
                }
                $parts[] = '<div style="font-size:14px;color:#334155">' . $co . '</div></div>';
            }
        }

        if ($technical !== '') {
            $parts[] = '<div style="margin-top:16px;padding:12px 14px;background:#1e293b;color:#f1f5f9;border-radius:10px;font-size:13px">';
            $parts[] = '<p style="margin:0 0 6px;font-size:10px;letter-spacing:.15em;text-transform:uppercase;font-weight:800;color:#94a3b8">Avis technique</p>';
            $parts[] = '<div style="white-space:pre-wrap">' . nl2br($e($technical)) . '</div></div>';
        }

        $parts[] = '<p style="margin-top:20px;font-size:12px;color:#94a3b8">Message généré automatiquement lors de la publication de l’offre.</p>';
        $parts[] = '</div>';

        return implode('', $parts);
    }

    private static function metaCell(string $label, string $valueHtml): string
    {
        return '<div style="padding:10px 12px;background:#f1f5f9;border-radius:8px;border:1px solid #e2e8f0">'
            . '<p style="margin:0 0 4px;font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:700">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0;font-size:13px;font-weight:600">' . $valueHtml . '</p></div>';
    }

    /** @return list<string> */
    private static function decodeJsonList(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            $raw = is_array($d) ? $d : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /** @return list<array{rubrique: string, detail: string}> */
    private static function decodeProfileItems(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            $raw = is_array($d) ? $d : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rub = trim((string) ($row['rubrique'] ?? ''));
            $det = trim((string) ($row['detail'] ?? ''));
            if ($rub === '' && $det === '') {
                continue;
            }
            $out[] = ['rubrique' => $rub !== '' ? $rub : 'Point', 'detail' => $det];
        }

        return $out;
    }

    /** @return list<array{theme: string, titre: string, corps: string}> */
    private static function decodeBlocks(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            $raw = is_array($d) ? $d : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'theme' => (string) ($row['theme'] ?? ''),
                'titre' => (string) ($row['titre'] ?? ''),
                'corps' => (string) ($row['corps'] ?? ''),
            ];
        }

        return $out;
    }
}
