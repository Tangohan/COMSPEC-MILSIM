<?php
declare(strict_types=1);
/**
 * Mes formations — coque immersive « registre » (rail latéral, panneaux, cartes tactiles).
 * Page autonome (doctype propre) : ne dépend pas de layout.main, à l’image de training.certificate.
 */
$base = url('');
$enrollments = $enrollments ?? [];
$trainingStats = $trainingStats ?? ['total' => 0, 'in_progress' => 0, 'assigned' => 0, 'completed' => 0, 'expiring_soon' => 0];
$trainingFilter = $trainingFilter ?? 'all';
$viewerName = \App\Core\Session::get('display_name') ?? \App\Core\Session::get('email') ?? '';

$statusLabel = static function (string $s): string {
    return match ($s) {
        'assigned' => 'Non démarré',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'revoked' => 'Révoqué',
        'withdrawn' => 'Inscription annulée',
        'pending_approval' => 'En attente de validation',
        'failed' => 'Non validé',
        'expired' => 'Expiré',
        default => $s,
    };
};
$statusBadgeClass = static function (string $s): string {
    return match ($s) {
        'in_progress' => 'mf-badge--status-inprogress',
        'assigned' => 'mf-badge--status-assigned',
        'completed' => 'mf-badge--status-completed',
        'pending_approval' => 'mf-badge--status-pending',
        default => 'mf-badge--status-muted',
    };
};
$levelLabel = static function (?string $l): string {
    return match ($l ?? '') {
        'initiation' => 'Initiation',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
        'expert' => 'Expert',
        default => '—',
    };
};
$coverUrl = static function (array $e): string {
    $p = trim((string) ($e['thumbnail_path'] ?? ''));
    if ($p === '') {
        $p = trim((string) ($e['banner_path'] ?? ''));
    }

    return training_media_url($p !== '' ? $p : null);
};
$isExpiringSoon = static function (array $e): bool {
    if (empty($e['expires_at']) || in_array($e['status'] ?? '', ['completed', 'revoked', 'withdrawn', 'expired'], true)) {
        return false;
    }
    $t = strtotime((string) $e['expires_at']);

    return $t !== false && $t <= strtotime('+30 days');
};
$matchesFilter = static function (array $e, string $filter) use ($isExpiringSoon): bool {
    $st = (string) ($e['status'] ?? '');

    return match ($filter) {
        'active' => in_array($st, ['assigned', 'in_progress'], true),
        'done' => $st === 'completed',
        'expiring' => $isExpiringSoon($e),
        'pending' => $st === 'pending_approval',
        default => true,
    };
};
$ctaLabel = static function (string $st): string {
    return match (true) {
        $st === 'completed' => 'Consulter',
        in_array($st, ['revoked', 'expired', 'withdrawn'], true) => 'Voir la fiche',
        $st === 'pending_approval' => 'Fiche formation',
        $st === 'assigned' => 'Commencer',
        default => 'Reprendre',
    };
};
$courseUrlFor = static function (array $e) use ($base): string {
    $slug = (string) ($e['course_slug'] ?? '');

    return $slug !== '' ? $base . '/formations/' . rawurlencode($slug) : $base . '/formations';
};
$fmtDate = static function (?string $raw): string {
    if (!$raw) {
        return '—';
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y', $t) : '—';
};

$categories = [];
foreach ($enrollments as $e) {
    $c = trim((string) ($e['category'] ?? ''));
    if ($c !== '') {
        $categories[$c] = true;
    }
}
$categories = array_keys($categories);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

/** Formation « à la une » du rail : la plus urgente parmi les parcours non clos. */
$featured = null;
$openOnes = array_values(array_filter($enrollments, static function (array $e): bool {
    return !in_array($e['status'] ?? '', ['completed', 'revoked', 'withdrawn', 'expired'], true);
}));
if (!empty($openOnes)) {
    usort($openOnes, static function (array $a, array $b) use ($isExpiringSoon): int {
        $ea = $isExpiringSoon($a) ? 0 : 1;
        $eb = $isExpiringSoon($b) ? 0 : 1;
        if ($ea !== $eb) {
            return $ea <=> $eb;
        }
        $rank = static fn (array $x): int => match ($x['status'] ?? '') {
            'in_progress' => 0,
            'assigned' => 1,
            'pending_approval' => 2,
            default => 3,
        };
        $ra = $rank($a);
        $rb = $rank($b);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? ''));
    });
    $featured = $openOnes[0];
}

$totalCount = (int) $trainingStats['total'];
$doneCount = (int) $trainingStats['completed'];
$overallPct = $totalCount > 0 ? (int) round(($doneCount / $totalCount) * 100) : 0;

$filterMeta = [
    'all' => ['Vue complète', 'Tous vos parcours, sans distinction.'],
    'active' => ['Actifs', 'Non démarrés et en cours.'],
    'done' => ['Terminées', 'Parcours validés avec attestation le cas échéant.'],
    'expiring' => ['Échéance proche', 'Recyclage à prévoir sous 30 jours.'],
    'pending' => ['En attente', 'Validation par l’encadrement en cours.'],
    'pinned' => ['Épinglées', 'Parcours que vous avez repérés pour vous-même.'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mes formations — Athena</title>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root{
            --mf-void:#050505;--mf-ink:#f4f4f0;--mf-muted:rgba(244,244,240,.56);
            --mf-line:rgba(244,244,240,.10);--mf-line2:rgba(244,244,240,.18);
            --mf-primary:#059669;--mf-accent:#34d399;--mf-accent2:#6ee7b7;--mf-urgent:#f59e0b;
            --mf-rail-w:72px;--mf-page-pad:24px;
        }
        body.mf-body{background:var(--mf-void);color:var(--mf-ink);font-family:'Inter',system-ui,sans-serif;min-height:100vh;margin:0;overflow-x:hidden}
        body.mf-body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(circle at 12% 16%,rgba(5,150,105,.20),transparent 28%),radial-gradient(circle at 86% 20%,rgba(52,211,153,.12),transparent 30%),linear-gradient(180deg,#050505,#07130f 52%,#050505)}
        .mf-gridlines{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.10;background-image:linear-gradient(rgba(244,244,240,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(244,244,240,.06) 1px,transparent 1px);background-size:72px 72px;mask-image:radial-gradient(circle at 50% 35%,black,transparent 78%)}
        .font-display{font-family:'Inter',system-ui,sans-serif;font-style:italic;font-weight:900}

        /* Rail latéral (hover-expand) */
        .mf-rail{position:fixed;left:0;top:0;bottom:0;width:var(--mf-rail-w);border-right:1px solid var(--mf-line);z-index:120;background:rgba(5,5,5,.9);backdrop-filter:blur(20px);overflow:hidden;transition:width .32s cubic-bezier(.2,1,.2,1),background .25s ease,box-shadow .25s ease}
        .mf-rail:hover,.mf-rail:focus-within{width:min(360px,92vw);background:rgba(6,14,11,.98);box-shadow:40px 0 140px rgba(0,0,0,.62)}
        .mf-rail-compact{position:absolute;inset:0 auto 0 0;width:var(--mf-rail-w);display:flex;align-items:center;justify-content:center;border-right:1px solid var(--mf-line)}
        .mf-rail-compact span{writing-mode:vertical-rl;transform:rotate(180deg);letter-spacing:.35em;font-size:10px;color:var(--mf-muted);font-weight:900;text-transform:uppercase;white-space:nowrap}
        .mf-rail-panel{position:absolute;inset:0 0 0 var(--mf-rail-w);padding:22px 20px 28px;opacity:0;transform:translateX(18px);transition:opacity .28s ease,transform .28s ease;overflow-y:auto;overflow-x:hidden}
        .mf-rail:hover .mf-rail-panel,.mf-rail:focus-within .mf-rail-panel{opacity:1;transform:none}
        .mf-nav-btn,.mf-filter-btn,.mf-link{display:grid;grid-template-columns:42px 1fr auto;align-items:center;gap:12px;width:100%;padding:13px;border:1px solid rgba(244,244,240,.075);background:rgba(244,244,240,.025);text-decoration:none;color:var(--mf-ink);cursor:pointer;transition:.2s;text-align:left;font:inherit}
        .mf-nav-btn:hover,.mf-filter-btn:hover,.mf-link:hover{transform:translateX(4px);border-color:rgba(52,211,153,.38);background:rgba(5,150,105,.08)}
        .mf-nav-btn.active,.mf-filter-btn.active{border-color:rgba(52,211,153,.45);background:rgba(5,150,105,.14);box-shadow:inset 0 0 0 1px rgba(52,211,153,.18)}
        .mf-nav-btn b,.mf-filter-btn b,.mf-link b{color:var(--mf-accent);font-size:10px;font-weight:900;letter-spacing:.18em}
        .mf-nav-btn span,.mf-filter-btn span,.mf-link span{display:block;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;line-height:1.2}
        .mf-nav-btn em,.mf-filter-btn em,.mf-link em{display:block;margin-top:3px;color:rgba(244,244,240,.42);font-size:10px;font-style:normal;line-height:1.35;font-weight:500;text-transform:none;letter-spacing:0}
        .mf-section-label{margin:18px 0 10px;font-size:9px;font-weight:900;letter-spacing:.28em;text-transform:uppercase;color:rgba(244,244,240,.35)}
        .mf-section-label:first-child{margin-top:4px}
        .mf-tools{display:grid;gap:10px;margin-top:4px}
        .mf-search-wrap{position:relative}
        .mf-search-wrap svg{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:rgba(244,244,240,.4);pointer-events:none}
        .mf-search{width:100%;border:1px solid rgba(244,244,240,.1);background:rgba(0,0,0,.35);padding:.65rem .75rem .65rem 2.1rem;font-size:12px;color:var(--mf-ink);outline:none;border-radius:0}
        .mf-search::placeholder{color:rgba(244,244,240,.35)}
        .mf-select{width:100%;appearance:none;border:1px solid rgba(244,244,240,.1);background:#0a0a0a;padding:.65rem .75rem;font-size:12px;color:#e5e7eb;outline:none;cursor:pointer;border-radius:0;text-overflow:ellipsis;white-space:nowrap;overflow:hidden;padding-right:1.75rem;background-image:linear-gradient(45deg,transparent 50%,rgba(244,244,240,.35) 50%),linear-gradient(135deg,rgba(244,244,240,.35) 50%,transparent 50%);background-position:calc(100% - 14px) calc(50% - 2px),calc(100% - 9px) calc(50% - 2px);background-size:5px 5px,5px 5px;background-repeat:no-repeat}
        .mf-select option{background:#0a0a0a;color:#e5e7eb}
        .mf-layout-row{display:flex;gap:.35rem}
        .mf-layout-row button{flex:1;border:1px solid rgba(244,244,240,.1);background:transparent;padding:.55rem;font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:rgba(244,244,240,.45);cursor:pointer;transition:.18s ease}
        .mf-layout-row button.active{background:rgba(244,244,240,.08);color:#fff;border-color:rgba(52,211,153,.35)}
        .mf-filter-btn--urgent{border-color:rgba(245,158,11,.32);background:linear-gradient(135deg,rgba(245,158,11,.1),rgba(52,211,153,.05))}
        .mf-filter-btn--urgent b{color:#f59e0b;font-size:11px}
        .mf-filter-btn--urgent em{color:rgba(253,230,138,.55)}
        .mf-filter-btn--urgent.active{border-color:rgba(245,158,11,.55);background:rgba(245,158,11,.14)}
        .mf-filter-badge{margin-left:auto;font-style:normal;font-size:10px;font-weight:900;color:#5eead4;border:1px solid rgba(52,211,153,.4);background:rgba(52,211,153,.12);padding:.15rem .4rem;border-radius:999px}

        /* Coque de contenu */
        .mf-shell{position:relative;z-index:10;margin-left:var(--mf-rail-w);min-height:100vh}
        .mf-topnav{position:sticky;top:0;z-index:90;display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:64px;padding:0 var(--mf-page-pad);border-bottom:1px solid var(--mf-line);background:rgba(5,5,5,.82);backdrop-filter:blur(16px)}
        .mf-topnav-cta{display:inline-flex;align-items:center;gap:.45rem;border:1px solid rgba(52,211,153,.4);background:rgba(5,150,105,.14);color:#a7f3d0;padding:.625rem 1rem;font-size:10px;font-weight:900;letter-spacing:.15em;text-transform:uppercase;text-decoration:none;transition:.2s ease;border-radius:2px}
        .mf-topnav-cta:hover{background:rgba(5,150,105,.9);border-color:rgba(52,211,153,.65);color:#fff}
        .mf-main{padding:1.75rem var(--mf-page-pad) 3.5rem;position:relative;z-index:1}
        .mf-mobilebar{display:none;gap:.5rem;flex-wrap:wrap;padding:.75rem var(--mf-page-pad);border-bottom:1px solid var(--mf-line);background:rgba(5,5,5,.92)}
        .mf-mobilebar select,.mf-mobilebar input{flex:1;min-width:120px;border:1px solid rgba(244,244,240,.1);background:#0a0a0a;color:#fff;padding:.55rem .65rem;font-size:11px;min-height:2.75rem}
        @media(max-width:900px){
            .mf-rail{display:none}
            .mf-shell{margin-left:0}
            .mf-mobilebar{display:flex;position:sticky;top:64px;z-index:85}
        }

        /* Badges (dans le corps de carte, hors image) */
        .mf-badge{display:inline-flex;align-items:center;gap:.35rem;border:1px solid rgba(244,244,240,.14);background:rgba(244,244,240,.06);padding:.38rem .62rem;font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;border-radius:999px;white-space:nowrap;color:#f4f4f0}
        .mf-badge--mandatory{border-color:rgba(244,63,94,.4);background:rgba(244,63,94,.16);color:#fecdd3}
        .mf-badge--certifying{border-color:rgba(52,211,153,.4);background:rgba(5,150,105,.2);color:#a7f3d0}
        .mf-badge--status-assigned{border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.16);color:#fde68a}
        .mf-badge--status-inprogress{border-color:rgba(56,189,248,.4);background:rgba(14,165,233,.16);color:#bae6fd}
        .mf-badge--status-completed{border-color:rgba(52,211,153,.45);background:rgba(5,150,105,.22);color:#a7f3d0}
        .mf-badge--status-pending{border-color:rgba(167,139,250,.4);background:rgba(139,92,246,.18);color:#ddd6fe}
        .mf-badge--status-muted{border-color:rgba(244,244,240,.18);background:rgba(244,244,240,.06);color:rgba(244,244,240,.6)}
        .mf-badge--progress{border-color:rgba(52,211,153,.4);background:rgba(0,0,0,.5);color:#6ee7b7}
        .mf-badge--urgent{border-color:rgba(245,158,11,.45);background:rgba(245,158,11,.18);color:#fde68a}

        /* Hero « à la une » */
        .mf-hero{position:relative;overflow:hidden;border:1px solid var(--mf-line);margin-bottom:1.75rem;min-height:320px;display:flex;flex-direction:column;justify-content:flex-end}
        .mf-hero-bg{position:absolute;inset:0;background:#0a0a0a center/cover no-repeat;transform:scale(1.02);transition:transform 6s ease}
        .mf-hero:hover .mf-hero-bg{transform:scale(1.07)}
        .mf-hero-veil{position:absolute;inset:0;background:linear-gradient(115deg,rgba(5,5,5,.95) 0%,rgba(5,5,5,.8) 42%,rgba(5,5,5,.4) 100%)}
        .mf-hero-content{position:relative;z-index:2;padding:2rem clamp(1.25rem,4vw,2.5rem) 2.25rem}
        .mf-hero-kicker{font-size:9px;font-weight:900;letter-spacing:.32em;text-transform:uppercase;color:var(--mf-accent)}
        .mf-hero-title{margin-top:.85rem;max-width:min(760px,94%);font-size:clamp(1.6rem,4vw,2.75rem);font-weight:900;font-style:italic;line-height:1.08;letter-spacing:-.02em;color:#fff;text-transform:uppercase}
        .mf-hero-title a{color:inherit;text-decoration:none}
        .mf-hero-title a:hover{color:var(--mf-accent2)}
        .mf-hero-desc{margin-top:.85rem;max-width:min(620px,94%);font-size:13px;line-height:1.65;color:rgba(244,244,240,.6)}
        .mf-hero-meta{display:flex;flex-wrap:wrap;gap:1.35rem;margin-top:1.35rem}
        .mf-hero-meta div{min-width:92px}
        .mf-hero-meta span{display:block;font-size:8px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:rgba(244,244,240,.4)}
        .mf-hero-meta strong{display:block;margin-top:.25rem;font-family:ui-monospace,monospace;font-size:14px;font-weight:900;color:#fff}
        .mf-hero-foot{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-top:1.5rem}
        .mf-hero-cta{display:inline-flex;align-items:center;gap:.65rem;padding:.85rem 1.4rem;border-radius:999px;border:1px solid rgba(244,244,240,.35);font-size:9px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#fff;text-decoration:none;transition:border-color .2s,background .2s}
        .mf-hero-cta:hover{border-color:#fff;background:rgba(244,244,240,.08)}

        /* Barre de filtre actif */
        .mf-browsecontext{position:relative;overflow:hidden;margin:0 0 1.5rem;border:1px solid var(--mf-line);background:linear-gradient(135deg,rgba(6,20,15,.92),rgba(5,5,5,.97))}
        .mf-browsecontext.is-hidden{display:none!important}
        .mf-browsecontext-inner{position:relative;z-index:1;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;padding:1.1rem clamp(1rem,3vw,1.75rem)}
        .mf-browsecontext-kicker{font-size:9px;font-weight:900;letter-spacing:.28em;text-transform:uppercase;color:var(--mf-accent)}
        .mf-browsecontext-title{margin:.3rem 0 0;font-size:1.1rem;font-weight:900;font-style:italic;text-transform:uppercase;color:#fff}
        .mf-browsecontext-meta{margin-top:.3rem;font-size:10px;font-weight:700;letter-spacing:.08em;color:rgba(244,244,240,.45)}
        .mf-browsecontext-clear{border:1px solid rgba(244,244,240,.16);background:rgba(244,244,240,.04);padding:.6rem .9rem;font-size:9px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#e4e4e4;cursor:pointer;transition:.2s ease;white-space:nowrap}
        .mf-browsecontext-clear:hover{border-color:rgba(52,211,153,.45);background:rgba(5,150,105,.12);color:#fff}

        /* Grille + cartes */
        .mf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.15rem}
        .mf-grid.is-list{grid-template-columns:1fr}
        .mf-card{position:relative;display:flex;flex-direction:column;border:1px solid rgba(244,244,240,.08);background:#0a0a0a;box-shadow:20px 20px 60px rgba(0,0,0,.7);transition:transform .4s ease,border-color .3s ease;animation:mfCardIn .5s ease both}
        .mf-card:hover{transform:translateY(-4px);border-color:rgba(52,211,153,.32)}
        .mf-grid.is-list .mf-card{flex-direction:row}
        .mf-grid.is-list .mf-card-hero{width:min(280px,34%);min-height:auto;flex-shrink:0}
        .mf-card.is-hidden{display:none!important}
        @keyframes mfCardIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

        .mf-card-hero{position:relative;min-height:170px;overflow:hidden;background:#0d0d0d center/cover no-repeat}
        .mf-card-hero img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease}
        .mf-card:hover .mf-card-hero img{transform:scale(1.06)}
        .mf-card-hero-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(5,5,5,.92) 0%,rgba(5,5,5,.15) 60%,transparent 100%)}
        .mf-card-hero-shine{position:absolute;inset:0;background:linear-gradient(120deg,transparent,rgba(244,244,240,.12),transparent);transform:translateX(-120%);transition:transform .9s ease;pointer-events:none}
        .mf-card:hover .mf-card-hero-shine{transform:translateX(120%)}

        .mf-card-body{padding:1.2rem;display:flex;flex-direction:column;gap:.7rem;flex:1}
        .mf-card-badges{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
        .mf-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
        .mf-card-tags{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(244,244,240,.4)}
        .mf-pin-btn{flex-shrink:0;width:2.15rem;height:2.15rem;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(244,244,240,.1);background:rgba(244,244,240,.03);color:rgba(244,244,240,.32);cursor:pointer;transition:.2s;font-size:14px}
        .mf-pin-btn:hover{color:#fbbf24;border-color:rgba(251,191,36,.35)}
        .mf-pin-btn.is-pinned{border-color:rgba(251,191,36,.4);background:rgba(251,191,36,.12);color:#fbbf24}
        .mf-card-title{margin:0;font-size:1.18rem;font-weight:900;font-style:italic;letter-spacing:-.01em;text-transform:uppercase;color:#fff;line-height:1.2}
        .mf-card-title a{color:inherit;text-decoration:none}
        .mf-card-title a:hover{color:var(--mf-accent2)}
        .mf-card-desc{margin:0;font-size:12px;line-height:1.6;color:rgba(244,244,240,.5)}

        .mf-progress-label{display:flex;align-items:center;justify-content:space-between;font-size:10px;font-weight:800;letter-spacing:.06em;color:rgba(244,244,240,.45)}
        .mf-progress-track{margin-top:.35rem;height:6px;border-radius:999px;background:rgba(244,244,240,.08);overflow:hidden}
        .mf-progress-fill{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--mf-primary),var(--mf-accent));transition:width .5s ease}
        .mf-progress-fill.is-muted{background:linear-gradient(90deg,#525252,#737373)}

        .mf-meta-panel{border:1px solid rgba(244,244,240,.08);background:rgba(0,0,0,.25);padding:.6rem .7rem;display:grid;gap:.15rem}
        .mf-meta-row{display:flex;gap:.5rem;padding:.28rem 0;border-top:1px solid rgba(244,244,240,.05);font-size:10.5px;line-height:1.4}
        .mf-meta-row:first-child{border-top:0;padding-top:0}
        .mf-meta-row dt{flex:0 0 42%;color:rgba(244,244,240,.35);font-weight:800;text-transform:uppercase;letter-spacing:.06em;font-size:8px;margin:0}
        .mf-meta-row dd{margin:0;flex:1;color:#e4e4e4}
        .mf-meta-row dd.is-urgent{color:#fcd34d;font-weight:800}

        .mf-card-foot{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:rgba(244,244,240,.28)}
        .mf-copy-btn{border:0;background:transparent;color:inherit;cursor:pointer;transition:.2s;font:inherit;letter-spacing:inherit;text-transform:inherit}
        .mf-copy-btn:hover{color:#fff}

        .mf-card-actions{margin-top:auto;display:flex;flex-direction:column;gap:.5rem;padding-top:.35rem}
        .mf-cta{display:flex;align-items:center;justify-content:space-between;width:100%;padding:.85rem 1rem;font-weight:900;text-transform:uppercase;font-size:10px;letter-spacing:.2em;transition:.2s;text-decoration:none;border:1px solid transparent;cursor:pointer}
        .mf-cta--primary{background:#fff;color:#000}
        .mf-cta--primary:hover{background:var(--mf-accent);color:#022c1e}
        .mf-cta--muted{background:rgba(244,244,240,.06);color:rgba(244,244,240,.4);cursor:not-allowed}
        .mf-cta-secondary{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.75rem .9rem;font-size:9px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;border:1px solid rgba(52,211,153,.35);background:rgba(5,150,105,.14);color:#a7f3d0;text-decoration:none;transition:.2s}
        .mf-cta-secondary:hover{background:rgba(5,150,105,.26);color:#fff}
        .mf-cta-row{display:flex;gap:.5rem}

        /* Menu d’actions secondaires repliable */
        .mf-action-menu{border:1px solid rgba(244,244,240,.08);background:rgba(244,244,240,.02)}
        .mf-action-menu[open]{border-color:rgba(244,244,240,.14);background:rgba(244,244,240,.035)}
        .mf-action-menu__summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.6rem .75rem;font-size:9px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:rgba(244,244,240,.62);user-select:none}
        .mf-action-menu__summary::-webkit-details-marker{display:none}
        .mf-action-menu__summary::after{content:'+';font-size:12px;color:rgba(244,244,240,.35);transition:transform .2s ease,color .2s ease}
        .mf-action-menu[open] .mf-action-menu__summary::after{content:'−';color:rgba(244,244,240,.72)}
        .mf-action-menu__panel{padding:0 .65rem .65rem;border-top:1px solid rgba(244,244,240,.06);display:flex;flex-direction:column;gap:.4rem}
        .mf-action-menu__btn{width:100%;border:1px solid rgba(244,63,94,.28);background:rgba(244,63,94,.08);padding:.6rem .7rem;text-align:left;font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#fecdd3;transition:.2s ease;cursor:pointer}
        .mf-action-menu__btn:hover{border-color:rgba(244,63,94,.5);background:rgba(244,63,94,.18);color:#fff}

        .mf-empty{border:1px solid var(--mf-line);background:rgba(244,244,240,.02);padding:3.25rem 1.5rem;text-align:center}
        .mf-tip{margin-top:2.5rem;border:1px dashed rgba(244,244,240,.14);background:rgba(244,244,240,.02);padding:1.1rem 1.35rem}
    </style>
</head>
<body class="mf-body antialiased">
<div class="mf-gridlines" aria-hidden="true"></div>

<aside class="mf-rail" aria-label="Navigation de mes formations">
    <div class="mf-rail-compact"><span>Mes formations</span></div>
    <div class="mf-rail-panel">
        <?php if ($viewerName !== ''): ?>
        <p class="text-[10px] uppercase tracking-[.2em] text-white/35 font-bold mb-1">Bonjour, <?= htmlspecialchars($viewerName, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="flex items-end justify-between gap-4 mb-5">
            <p class="text-[10px] uppercase tracking-[.28em] text-white/40 font-black">Navigation</p>
            <strong class="font-display text-5xl leading-none italic"><?= $totalCount ?></strong>
        </div>
        <div class="h-2 bg-white/10 mb-6"><i class="block h-full bg-gradient-to-r from-[var(--mf-primary)] via-[var(--mf-accent)] to-[var(--mf-accent2)]" style="width:<?= $overallPct ?>%"></i></div>

        <p class="mf-section-label">Filtres</p>
        <nav class="grid gap-2 mb-2" aria-label="Filtres de mes formations" id="mfFilterNav">
            <button type="button" class="mf-filter-btn<?= $trainingFilter === 'all' ? ' active' : '' ?>" data-status="all"><b>01</b><span>Tous<em>Vue complète</em></span><i></i></button>
            <button type="button" class="mf-filter-btn<?= $trainingFilter === 'active' ? ' active' : '' ?>" data-status="active"><b>02</b><span>Actifs<em>À commencer ou en cours</em></span><i></i></button>
            <button type="button" class="mf-filter-btn<?= $trainingFilter === 'done' ? ' active' : '' ?>" data-status="done"><b>03</b><span>Terminées<em>Attestations obtenues</em></span><i></i></button>
            <button type="button" class="mf-filter-btn mf-filter-btn--urgent<?= $trainingFilter === 'expiring' ? ' active' : '' ?>" data-status="expiring"><b>⏱</b><span>Échéance proche<em>Recyclage sous 30 j.</em></span><i class="mf-filter-badge" aria-hidden="true"><?= (int) $trainingStats['expiring_soon'] ?></i></button>
            <button type="button" class="mf-filter-btn<?= $trainingFilter === 'pending' ? ' active' : '' ?>" data-status="pending"><b>·</b><span>En attente<em>Validation encadrement</em></span><i></i></button>
            <button type="button" class="mf-filter-btn" data-status="pinned"><b>♥</b><span>Épinglées<em><span id="mfPinnedCount">0</span> repérées</em></span><i></i></button>
        </nav>

        <p class="mf-section-label">Trier par</p>
        <nav class="grid gap-2 mb-2" aria-label="Tri de mes formations" id="mfSortNav">
            <button type="button" class="mf-nav-btn active" data-sort="priority"><b>01</b><span>Priorité<em>Échéance puis progression</em></span><i></i></button>
            <button type="button" class="mf-nav-btn" data-sort="recent"><b>02</b><span>Récentes<em>Dernières assignations</em></span><i></i></button>
            <button type="button" class="mf-nav-btn" data-sort="alpha"><b>03</b><span>Alphabétique<em>A → Z</em></span><i></i></button>
            <button type="button" class="mf-nav-btn" data-sort="progress"><b>04</b><span>Progression<em>Plus avancées d’abord</em></span><i></i></button>
        </nav>

        <p class="mf-section-label">Rechercher</p>
        <div class="mf-tools">
            <div class="mf-search-wrap">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="mfSearchInput" type="text" class="mf-search" placeholder="Chercher un parcours…" autocomplete="off">
            </div>
            <?php if (!empty($categories)): ?>
            <select id="mfCategorySelect" class="mf-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>

        <p class="mf-section-label">Affichage</p>
        <div class="mf-layout-row" role="group" aria-label="Affichage">
            <button type="button" class="active" data-layout="grid">Grille</button>
            <button type="button" data-layout="list">Liste</button>
        </div>

        <p class="mf-section-label">Raccourcis</p>
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations" class="mf-link mb-2">
            <b>→</b><span>Catalogue<em>Découvrir d’autres parcours</em></span><i></i>
        </a>
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations/competences" class="mf-link mb-2">
            <b>◆</b><span>Compétences<em>Cartographie des acquis</em></span><i></i>
        </a>
        <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="mf-link">
            <b>◎</b><span>Espace RH<em>Dossier administratif</em></span><i></i>
        </a>
    </div>
</aside>

<div class="mf-shell">
    <header class="mf-topnav" role="navigation">
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/dashboard" class="group flex items-center gap-3 no-underline text-white">
            <div class="h-10 w-10 flex items-center justify-center bg-[var(--mf-accent)] text-[#022c1e] font-black text-xl italic rounded-sm transition-transform group-hover:-rotate-2">A</div>
            <div class="hidden sm:flex flex-col">
                <span class="font-display text-sm font-bold uppercase tracking-[.18em]">Athena</span>
                <span class="text-[9px] font-bold text-neutral-500 uppercase tracking-[0.2em] mt-0.5">Mes formations</span>
            </div>
        </a>
        <div class="hidden md:flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-neutral-500">
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/dashboard" class="px-3 py-2 hover:text-white transition-colors no-underline text-inherit">Tableau de bord</a>
            <span class="text-neutral-800">/</span>
            <span class="px-3 py-2 text-white italic underline underline-offset-4 decoration-[var(--mf-accent)]">Mes formations</span>
            <span class="text-neutral-800">/</span>
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations" class="px-3 py-2 hover:text-emerald-200 transition-colors no-underline text-inherit">Catalogue</a>
            <span class="text-neutral-800">/</span>
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations/competences" class="px-3 py-2 hover:text-white transition-colors no-underline text-inherit">Compétences</a>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations" class="mf-topnav-cta">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" class="h-3.5 w-3.5"><path stroke-width="2.5" d="M4 6h16M4 12h16M4 18h7"/></svg>
                Catalogue
            </a>
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/dashboard" class="bg-white hover:bg-emerald-500 text-black hover:text-white px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.15em] transition-all duration-200 rounded-sm no-underline">Tableau de bord</a>
        </div>
    </header>

    <div class="mf-mobilebar" aria-label="Filtres mobiles">
        <select id="mfMobileFilterSelect" aria-label="Filtre">
            <option value="all">Tous</option>
            <option value="active">Actifs</option>
            <option value="done">Terminées</option>
            <option value="expiring">Échéance proche</option>
            <option value="pending">En attente</option>
            <option value="pinned">Épinglées</option>
        </select>
        <input type="search" id="mfMobileSearchInput" placeholder="Chercher…" aria-label="Recherche">
    </div>

    <main class="mf-main">
        <?php if ($featured): ?>
        <?php
            $fSt = (string) ($featured['status'] ?? '');
            $fPct = max(0, min(100, (int) ($featured['progress_percent'] ?? 0)));
        ?>
        <section class="mf-hero" aria-label="Parcours prioritaire">
            <div class="mf-hero-bg" style="background-image:url('<?= htmlspecialchars($coverUrl($featured), ENT_QUOTES, 'UTF-8') ?>')"></div>
            <div class="mf-hero-veil"></div>
            <div class="mf-hero-content">
                <p class="mf-hero-kicker"># À la une · <?= $isExpiringSoon($featured) ? 'Échéance à surveiller' : 'Priorité du moment' ?></p>
                <h1 class="mf-hero-title">
                    <a href="<?= htmlspecialchars($courseUrlFor($featured), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($featured['course_title'] ?? 'Formation'), ENT_QUOTES, 'UTF-8') ?></a>
                </h1>
                <?php if (!empty($featured['short_description'])): ?>
                <p class="mf-hero-desc"><?= htmlspecialchars((string) $featured['short_description'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="mf-hero-meta">
                    <div><span>Statut</span><strong><?= htmlspecialchars($statusLabel($fSt), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span>Progression</span><strong><?= $fPct ?> %</strong></div>
                    <div><span>Durée estimée</span><strong><?= (int) ($featured['estimated_minutes'] ?? 0) ?> min</strong></div>
                    <?php if (!empty($featured['expires_at'])): ?>
                    <div><span>Échéance</span><strong><?= $fmtDate((string) $featured['expires_at']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div class="mf-hero-foot">
                    <a class="mf-hero-cta" href="<?= htmlspecialchars($courseUrlFor($featured), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctaLabel($fSt), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="mf-browsecontext is-hidden" id="mfBrowseContext" aria-live="polite" aria-label="Filtre actif">
            <div class="mf-browsecontext-inner">
                <div class="min-w-0 flex-1">
                    <p class="mf-browsecontext-kicker">Mes formations</p>
                    <h2 class="mf-browsecontext-title font-display" id="mfBrowseContextTitle"></h2>
                    <p class="mf-browsecontext-meta" id="mfBrowseContextMeta"></p>
                </div>
                <button type="button" class="mf-browsecontext-clear" id="mfBrowseContextClear">Réinitialiser les filtres</button>
            </div>
        </section>

        <?php if (empty($enrollments)): ?>
        <div class="mf-empty">
            <span class="inline-flex rounded-full bg-white/5 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white/50">Aucun parcours</span>
            <h2 class="mt-4 text-2xl font-black italic uppercase text-white">Explorez le catalogue</h2>
            <p class="mt-3 max-w-md mx-auto text-sm leading-relaxed text-white/45">
                Vous n’avez pas encore de formation assignée. Les parcours attribués par votre organisation apparaîtront ici avec leur progression et leurs échéances.
            </p>
            <div class="mt-7">
                <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations" class="mf-cta mf-cta--primary inline-flex w-auto"><span>Ouvrir le catalogue</span><span aria-hidden="true">→</span></a>
            </div>
        </div>
        <?php else: ?>
        <section id="mfGrid" class="mf-grid">
            <?php foreach ($enrollments as $e):
                $st = (string) ($e['status'] ?? '');
                $pct = max(0, min(100, (int) ($e['progress_percent'] ?? 0)));
                $isDone = $st === 'completed';
                $isMuted = in_array($st, ['revoked', 'withdrawn', 'expired'], true);
                $certId = (int) ($e['certificate_id'] ?? 0);
                $expiringSoon = $isExpiringSoon($e);
                $courseUrl = $courseUrlFor($e);
                $title = (string) ($e['course_title'] ?? 'Formation');
                $category = trim((string) ($e['category'] ?? ''));
                $showWithdrawBtn = function_exists('training_enrollment_can_withdraw_by_member')
                    && training_enrollment_can_withdraw_by_member($e)
                    && $certId < 1;
                $initiallyVisible = $matchesFilter($e, $trainingFilter);
            ?>
            <article
                class="mf-card<?= $initiallyVisible ? '' : ' is-hidden' ?>"
                data-id="<?= (int) $e['id'] ?>"
                data-status="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"
                data-active="<?= in_array($st, ['assigned', 'in_progress'], true) ? '1' : '0' ?>"
                data-done="<?= $isDone ? '1' : '0' ?>"
                data-pending="<?= $st === 'pending_approval' ? '1' : '0' ?>"
                data-expiring="<?= $expiringSoon ? '1' : '0' ?>"
                data-title="<?= htmlspecialchars(mb_strtolower($title), ENT_QUOTES, 'UTF-8') ?>"
                data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"
                data-assigned-at="<?= htmlspecialchars((string) ($e['assigned_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-progress="<?= $pct ?>"
            >
                <div class="mf-card-hero">
                    <img src="<?= htmlspecialchars($coverUrl($e), ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                    <div class="mf-card-hero-overlay"></div>
                    <div class="mf-card-hero-shine"></div>
                </div>

                <div class="mf-card-body">
                    <div class="mf-card-badges">
                        <span class="mf-badge <?= $statusBadgeClass($st) ?>"><?= htmlspecialchars($statusLabel($st), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($e['is_mandatory'])): ?><span class="mf-badge mf-badge--mandatory">Obligatoire</span><?php endif; ?>
                        <?php if (!empty($e['is_certifying'])): ?><span class="mf-badge mf-badge--certifying">Certifiant</span><?php endif; ?>
                        <?php if ($expiringSoon): ?><span class="mf-badge mf-badge--urgent">Échéance proche</span><?php endif; ?>
                    </div>

                    <div class="mf-card-top">
                        <div class="mf-card-tags">
                            <?php if ($category !== ''): ?><span><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <span><?= htmlspecialchars($levelLabel($e['level'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button type="button" class="mf-pin-btn js-mf-pin" data-pin-id="<?= (int) $e['id'] ?>" aria-pressed="false" aria-label="Épingler ce parcours" title="Épingler ce parcours">
                            <span aria-hidden="true">&#9733;</span>
                        </button>
                    </div>

                    <h3 class="mf-card-title"><a href="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></h3>

                    <?php if (!empty($e['short_description'])): ?>
                    <p class="mf-card-desc line-clamp-2"><?= htmlspecialchars((string) $e['short_description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <div>
                        <div class="mf-progress-label"><span>Progression</span><span><?= $pct ?> %</span></div>
                        <div class="mf-progress-track"><span class="mf-progress-fill<?= $isMuted ? ' is-muted' : '' ?>" style="width:<?= $pct ?>%"></span></div>
                    </div>

                    <dl class="mf-meta-panel">
                        <div class="mf-meta-row"><dt>Durée</dt><dd><?= (int) ($e['estimated_minutes'] ?? 0) ?> min estimées</dd></div>
                        <div class="mf-meta-row"><dt>Assigné le</dt><dd><?= $fmtDate($e['assigned_at'] ?? null) ?></dd></div>
                        <?php if (!empty($e['expires_at'])): ?>
                        <div class="mf-meta-row"><dt>Échéance</dt><dd class="<?= ($expiringSoon && !$isDone) ? 'is-urgent' : '' ?>"><?= $fmtDate((string) $e['expires_at']) ?></dd></div>
                        <?php endif; ?>
                    </dl>

                    <div class="mf-card-foot">
                        <span>#<?= (int) $e['id'] ?></span>
                        <button type="button" class="mf-copy-btn js-mf-copy" data-copy-url="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>">Copier le lien</button>
                    </div>

                    <div class="mf-card-actions">
                        <a href="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>" class="mf-cta mf-cta--primary"><span><?= htmlspecialchars($ctaLabel($st), ENT_QUOTES, 'UTF-8') ?></span><span aria-hidden="true">→</span></a>
                        <?php if ($isDone && $certId > 0 && !empty($e['is_certifying'])): ?>
                        <div class="mf-cta-row">
                            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations/certificate/<?= $certId ?>" class="mf-cta-secondary">Voir l’attestation</a>
                        </div>
                        <?php endif; ?>
                        <?php if ($showWithdrawBtn): ?>
                        <details class="mf-action-menu">
                            <summary class="mf-action-menu__summary">Options</summary>
                            <div class="mf-action-menu__panel">
                                <form method="post" action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/formations/inscription/annuler" data-ui-confirm="1" data-ui-confirm-title="Annuler l’inscription" data-ui-confirm-body="Annuler votre inscription à cette formation ? Vous pourrez vous réinscrire depuis le catalogue si les conditions le permettent.">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="enrollment_id" value="<?= (int) $e['id'] ?>">
                                    <button type="submit" class="mf-action-menu__btn">Annuler l’inscription</button>
                                </form>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
        <p id="mfNoMatch" class="mf-empty" style="display:none">
            <span class="inline-flex rounded-full bg-white/5 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white/50">Aucun résultat</span>
            <span class="mt-4 block text-lg font-black italic uppercase text-white">Aucun parcours ne correspond à ces filtres</span>
            <span class="mt-2 block text-sm text-white/45">Essayez un autre statut, une autre catégorie ou réinitialisez la recherche.</span>
        </p>
        <?php endif; ?>

        <aside class="mf-tip">
            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-white/40">Astuce</p>
            <p class="mt-2 text-sm text-white/60">
                Les formations <strong class="text-white">certifiantes</strong> délivrent une attestation une fois le parcours validé. Surveillez les <strong class="text-white">échéances</strong> pour les modules obligatoires.
            </p>
        </aside>
    </main>
</div>

<?php require base_path('views/partials/ui/confirm_dialog.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/ui_confirm_modal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<script>
(function () {
    'use strict';
    var grid = document.getElementById('mfGrid');
    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.mf-card')) : [];
    var noMatchEl = document.getElementById('mfNoMatch');
    var browseCtx = document.getElementById('mfBrowseContext');
    var browseTitle = document.getElementById('mfBrowseContextTitle');
    var browseMeta = document.getElementById('mfBrowseContextMeta');
    var browseClear = document.getElementById('mfBrowseContextClear');
    var mobileSelect = document.getElementById('mfMobileFilterSelect');
    var pinnedCountEl = document.getElementById('mfPinnedCount');

    var filterMeta = <?= json_encode($filterMeta, JSON_UNESCAPED_UNICODE) ?>;
    var currentStatus = <?= json_encode($trainingFilter) ?>;
    var currentSort = 'priority';
    var currentCategory = '';
    var currentSearch = '';

    var PIN_KEY = 'mf_pinned_enrollments';
    function getPins() {
        try { return JSON.parse(localStorage.getItem(PIN_KEY) || '[]'); } catch (e) { return []; }
    }
    function setPins(list) {
        try { localStorage.setItem(PIN_KEY, JSON.stringify(list)); } catch (e) { /* stockage indisponible */ }
    }
    function isPinned(id) { return getPins().indexOf(String(id)) !== -1; }
    function togglePin(id) {
        var pins = getPins();
        var idx = pins.indexOf(String(id));
        if (idx === -1) { pins.push(String(id)); } else { pins.splice(idx, 1); }
        setPins(pins);
        return idx === -1;
    }
    function refreshPinButtons() {
        var pins = getPins();
        cards.forEach(function (card) {
            var btn = card.querySelector('.js-mf-pin');
            if (!btn) { return; }
            var pinned = pins.indexOf(String(card.getAttribute('data-id'))) !== -1;
            btn.classList.toggle('is-pinned', pinned);
            btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        });
        if (pinnedCountEl) { pinnedCountEl.textContent = String(pins.length); }
    }

    function cardMatchesStatus(card, status) {
        if (status === 'all') { return true; }
        if (status === 'pinned') { return isPinned(card.getAttribute('data-id')); }
        return card.getAttribute('data-' + status) === '1';
    }

    function applyFilters() {
        var visibleCount = 0;
        cards.forEach(function (card) {
            var okStatus = cardMatchesStatus(card, currentStatus);
            var okCategory = !currentCategory || card.getAttribute('data-category') === currentCategory;
            var okSearch = !currentSearch || card.getAttribute('data-title').indexOf(currentSearch) !== -1;
            var visible = okStatus && okCategory && okSearch;
            card.classList.toggle('is-hidden', !visible);
            if (visible) { visibleCount += 1; }
        });
        if (noMatchEl) { noMatchEl.style.display = (visibleCount === 0 && cards.length > 0) ? '' : 'none'; }

        if (browseCtx) {
            var isDefault = currentStatus === 'all' && !currentCategory && !currentSearch;
            browseCtx.classList.toggle('is-hidden', isDefault);
            if (!isDefault) {
                var meta = filterMeta[currentStatus] || filterMeta.all;
                if (browseTitle) { browseTitle.textContent = meta[0]; }
                if (browseMeta) {
                    var extra = [];
                    if (currentCategory) { extra.push('Catégorie : ' + currentCategory); }
                    if (currentSearch) { extra.push('Recherche : "' + currentSearch + '"'); }
                    browseMeta.textContent = meta[1] + (extra.length ? ' — ' + extra.join(' · ') : '') + ' (' + visibleCount + ' résultat' + (visibleCount > 1 ? 's' : '') + ')';
                }
            }
        }
    }

    function applySort() {
        if (!grid) { return; }
        var sorted = cards.slice().sort(function (a, b) {
            if (currentSort === 'alpha') {
                return a.getAttribute('data-title').localeCompare(b.getAttribute('data-title'));
            }
            if (currentSort === 'progress') {
                return (parseInt(b.getAttribute('data-progress'), 10) || 0) - (parseInt(a.getAttribute('data-progress'), 10) || 0);
            }
            if (currentSort === 'recent') {
                var da = new Date((a.getAttribute('data-assigned-at') || '').replace(' ', 'T')).getTime() || 0;
                var db = new Date((b.getAttribute('data-assigned-at') || '').replace(' ', 'T')).getTime() || 0;
                return db - da;
            }
            var expA = a.getAttribute('data-expiring') === '1' ? 0 : 1;
            var expB = b.getAttribute('data-expiring') === '1' ? 0 : 1;
            if (expA !== expB) { return expA - expB; }
            var actA = a.getAttribute('data-active') === '1' ? 0 : 1;
            var actB = b.getAttribute('data-active') === '1' ? 0 : 1;
            return actA - actB;
        });
        sorted.forEach(function (card) { grid.appendChild(card); });
    }

    function setActive(nodeList, target) {
        nodeList.forEach(function (btn) { btn.classList.toggle('active', btn === target); });
    }

    var filterButtons = Array.prototype.slice.call(document.querySelectorAll('#mfFilterNav .mf-filter-btn'));
    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentStatus = btn.getAttribute('data-status');
            setActive(filterButtons, btn);
            if (mobileSelect) { mobileSelect.value = currentStatus; }
            try {
                var qs = new URLSearchParams(window.location.search);
                if (currentStatus === 'all') { qs.delete('filter'); } else { qs.set('filter', currentStatus); }
                var newUrl = window.location.pathname + (qs.toString() ? '?' + qs.toString() : '');
                window.history.replaceState(null, '', newUrl);
            } catch (e) { /* history API indisponible */ }
            applyFilters();
        });
    });

    var sortButtons = Array.prototype.slice.call(document.querySelectorAll('#mfSortNav .mf-nav-btn'));
    sortButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentSort = btn.getAttribute('data-sort');
            setActive(sortButtons, btn);
            applySort();
        });
    });

    var searchInput = document.getElementById('mfSearchInput');
    var mobileSearchInput = document.getElementById('mfMobileSearchInput');
    function onSearchInput(value) {
        currentSearch = (value || '').trim().toLowerCase();
        if (searchInput && searchInput.value !== value) { searchInput.value = value; }
        if (mobileSearchInput && mobileSearchInput.value !== value) { mobileSearchInput.value = value; }
        applyFilters();
    }
    if (searchInput) { searchInput.addEventListener('input', function () { onSearchInput(searchInput.value); }); }
    if (mobileSearchInput) { mobileSearchInput.addEventListener('input', function () { onSearchInput(mobileSearchInput.value); }); }

    var categorySelect = document.getElementById('mfCategorySelect');
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            currentCategory = categorySelect.value;
            applyFilters();
        });
    }

    if (mobileSelect) {
        mobileSelect.value = currentStatus;
        mobileSelect.addEventListener('change', function () {
            currentStatus = mobileSelect.value;
            var match = filterButtons.filter(function (b) { return b.getAttribute('data-status') === currentStatus; })[0];
            if (match) { setActive(filterButtons, match); }
            applyFilters();
        });
    }

    if (browseClear) {
        browseClear.addEventListener('click', function () {
            currentStatus = 'all';
            currentCategory = '';
            currentSearch = '';
            if (categorySelect) { categorySelect.value = ''; }
            onSearchInput('');
            var allBtn = filterButtons.filter(function (b) { return b.getAttribute('data-status') === 'all'; })[0];
            if (allBtn) { setActive(filterButtons, allBtn); }
            if (mobileSelect) { mobileSelect.value = 'all'; }
            try { window.history.replaceState(null, '', window.location.pathname); } catch (e) { /* noop */ }
            applyFilters();
        });
    }

    var layoutButtons = Array.prototype.slice.call(document.querySelectorAll('.mf-layout-row button'));
    var LAYOUT_KEY = 'mf_layout_pref';
    function setLayout(mode) {
        if (!grid) { return; }
        grid.classList.toggle('is-list', mode === 'list');
        layoutButtons.forEach(function (btn) { btn.classList.toggle('active', btn.getAttribute('data-layout') === mode); });
        try { localStorage.setItem(LAYOUT_KEY, mode); } catch (e) { /* noop */ }
    }
    layoutButtons.forEach(function (btn) {
        btn.addEventListener('click', function () { setLayout(btn.getAttribute('data-layout')); });
    });
    try {
        var savedLayout = localStorage.getItem(LAYOUT_KEY);
        if (savedLayout === 'list') { setLayout('list'); }
    } catch (e) { /* noop */ }

    cards.forEach(function (card) {
        var pinBtn = card.querySelector('.js-mf-pin');
        if (pinBtn) {
            pinBtn.addEventListener('click', function () {
                var pinned = togglePin(card.getAttribute('data-id'));
                pinBtn.classList.toggle('is-pinned', pinned);
                pinBtn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
                if (pinnedCountEl) { pinnedCountEl.textContent = String(getPins().length); }
                if (currentStatus === 'pinned') { applyFilters(); }
            });
        }
        var copyBtn = card.querySelector('.js-mf-copy');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = copyBtn.getAttribute('data-copy-url') || '';
                var original = copyBtn.textContent;
                var restore = function () { copyBtn.textContent = original; };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        copyBtn.textContent = 'Lien copié';
                        setTimeout(restore, 1600);
                    }).catch(function () { /* copie impossible : lien déjà visible dans la carte */ });
                }
            });
        }
    });

    refreshPinButtons();
    applyFilters();
})();
</script>
</body>
</html>
