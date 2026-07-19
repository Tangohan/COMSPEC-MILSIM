<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $alerts_active */
/** @var list<array<string, mixed>> $alerts_history */
/** @var string|null $alerts_manage_url */

$alertsActive = is_array($alerts_active ?? null) ? $alerts_active : [];
$alertsHistory = is_array($alerts_history ?? null) ? $alerts_history : [];
$alertsManageUrl = isset($alerts_manage_url) && is_string($alerts_manage_url) && $alerts_manage_url !== ''
    ? $alerts_manage_url
    : null;

$kindLabelFr = static function (string $kind): string {
    return match (strtolower(trim($kind))) {
        'urgent' => 'Urgent',
        'novelty' => 'Nouveau',
        'discount' => 'Promotion',
        'notice' => 'Annonce',
        'forum_pin' => 'Message épinglé',
        default => 'Information',
    };
};

$formatEndedAt = static function (?string $raw): ?string {
    if ($raw === null || $raw === '') {
        return null;
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y H:i', $t) : null;
};

$renderCard = static function (array $item, bool $archived) use ($kindLabelFr, $formatEndedAt): void {
    $kind = strtolower(trim((string) ($item['kind'] ?? 'info')));
    if ($kind === 'forum_pin') {
        $kind = 'notice';
    }
    if (!in_array($kind, ['info', 'urgent', 'novelty', 'discount', 'notice'], true)) {
        $kind = 'info';
    }
    $category = trim((string) ($item['category'] ?? ''));
    if ($category === '') {
        $category = $kindLabelFr((string) ($item['kind'] ?? $kind));
    }
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        return;
    }
    $body = trim((string) ($item['body'] ?? ''));
    $ctaLabel = isset($item['cta_label']) && is_string($item['cta_label']) && $item['cta_label'] !== ''
        ? (string) $item['cta_label']
        : null;
    $ctaUrl = isset($item['cta_url']) && is_string($item['cta_url']) && $item['cta_url'] !== ''
        ? (string) $item['cta_url']
        : null;
    $isPlatform = strtolower(trim((string) ($item['scope'] ?? ''))) === 'platform';
    $endedLabel = $archived ? $formatEndedAt(isset($item['ended_at']) ? (string) $item['ended_at'] : null) : null;
    $tag = ($ctaUrl !== null) ? 'a' : 'article';
    $hrefAttr = $ctaUrl !== null ? ' href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '"' : '';
    $extraClass = $archived ? ' alerts-page__card--archived' : '';
    ?>
    <<?= $tag ?> class="alerts-page__card alerts-page__card--<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?><?= $isPlatform ? ' alerts-page__card--verified' : '' ?><?= $extraClass ?>"<?= $hrefAttr ?>>
        <div class="alerts-page__card-meta">
            <span class="alerts-page__card-kind"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($isPlatform): ?>
                <span class="alerts-page__card-verified">Annonce officielle du site</span>
            <?php endif; ?>
            <?php if ($endedLabel !== null): ?>
                <span class="alerts-page__card-ended">Diffusion terminée le <?= htmlspecialchars($endedLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <h3 class="alerts-page__card-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
        <?php if ($body !== ''): ?>
            <p class="alerts-page__card-body"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($ctaLabel !== null || $ctaUrl !== null): ?>
            <span class="alerts-page__card-cta"><?= htmlspecialchars($ctaLabel !== null ? $ctaLabel : 'Ouvrir', ENT_QUOTES, 'UTF-8') ?> →</span>
        <?php endif; ?>
    </<?= $tag ?>>
    <?php
};
?>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<style>
.alerts-page {
  --ap-bg: #0a0a0a;
  --ap-panel: #111;
  --ap-line: rgba(255,255,255,.08);
  --ap-muted: #a3a3a3;
  --ap-accent: #34d399;
  max-width: 56rem;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
  color: #f5f5f5;
}
@media (min-width: 768px) {
  .alerts-page { padding: 2rem 1.5rem 3.5rem; }
}
.alerts-page__hero {
  background: linear-gradient(160deg, #0a0a0a 0%, #111827 55%, #0a0a0a 100%);
  border: 1px solid var(--ap-line);
  border-radius: 1rem;
  padding: 1.5rem 1.35rem 1.65rem;
  margin-bottom: 2rem;
}
.alerts-page__kicker {
  margin: 0;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: #e5e5e5;
}
.alerts-page__title {
  margin: .35rem 0 0;
  font-family: Rajdhani, ui-sans-serif, system-ui, sans-serif;
  font-size: clamp(1.75rem, 4vw, 2.25rem);
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--ap-accent);
  line-height: 1.1;
}
.alerts-page__lead {
  margin: .85rem 0 0;
  max-width: 38rem;
  font-size: .95rem;
  line-height: 1.55;
  color: var(--ap-muted);
}
.alerts-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: .75rem 1rem;
  margin-top: 1.25rem;
}
.alerts-page__link {
  display: inline-flex;
  align-items: center;
  font-size: .75rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ap-accent);
  text-decoration: underline;
  text-underline-offset: 3px;
}
.alerts-page__link:hover { color: #6ee7b7; }
.alerts-page__link--muted { color: #d4d4d4; }
.alerts-page__section { margin-top: 2.25rem; }
.alerts-page__section-title {
  margin: 0;
  font-family: Rajdhani, ui-sans-serif, system-ui, sans-serif;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #fff;
}
.alerts-page__section-lead {
  margin: .4rem 0 0;
  font-size: .875rem;
  color: var(--ap-muted);
}
.alerts-page__stack {
  display: grid;
  gap: .85rem;
  margin-top: 1.15rem;
}
.alerts-page__empty {
  margin: 1.15rem 0 0;
  padding: 1.15rem 1rem;
  border: 1px dashed var(--ap-line);
  border-radius: .85rem;
  color: var(--ap-muted);
  font-size: .9rem;
  background: var(--ap-bg);
}
.alerts-page__card {
  display: block;
  background: var(--ap-panel);
  border: 1px solid var(--ap-line);
  border-radius: .85rem;
  padding: 1.1rem 1.15rem 1.2rem;
  color: inherit;
  text-decoration: none;
  transition: border-color .18s ease, background .18s ease;
}
a.alerts-page__card:hover {
  border-color: rgba(52, 211, 153, .35);
  background: #141414;
}
.alerts-page__card--urgent { border-left: 3px solid #f87171; }
.alerts-page__card--novelty { border-left: 3px solid #60a5fa; }
.alerts-page__card--discount { border-left: 3px solid #fbbf24; }
.alerts-page__card--notice { border-left: 3px solid #a78bfa; }
.alerts-page__card--info { border-left: 3px solid var(--ap-accent); }
.alerts-page__card--archived { opacity: .82; }
.alerts-page__card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem .75rem;
  align-items: center;
  margin-bottom: .55rem;
}
.alerts-page__card-kind {
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #d4d4d4;
}
.alerts-page__card-verified {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ap-accent);
}
.alerts-page__card-ended {
  font-size: .75rem;
  color: var(--ap-muted);
}
.alerts-page__card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: .02em;
  color: #fff;
  line-height: 1.3;
}
.alerts-page__card-body {
  margin: .55rem 0 0;
  font-size: .9rem;
  line-height: 1.55;
  color: #d4d4d4;
  white-space: pre-wrap;
}
.alerts-page__card-cta {
  display: inline-block;
  margin-top: .85rem;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ap-accent);
}
</style>

<div class="alerts-page">
    <header class="alerts-page__hero">
        <p class="alerts-page__kicker">Transmission</p>
        <h1 class="alerts-page__title">Alertes &amp; annonces</h1>
        <p class="alerts-page__lead">
            Retrouvez ici les messages officiels en cours pour votre communauté,
            ainsi que les annonces dont la diffusion est terminée.
        </p>
        <div class="alerts-page__actions">
            <a class="alerts-page__link alerts-page__link--muted" href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">← Retour au tableau de bord</a>
            <?php if ($alertsManageUrl !== null): ?>
                <a class="alerts-page__link" href="<?= htmlspecialchars($alertsManageUrl, ENT_QUOTES, 'UTF-8') ?>">Gérer les annonces →</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="alerts-page__section" aria-labelledby="alerts-active-heading">
        <h2 id="alerts-active-heading" class="alerts-page__section-title">Messages en cours</h2>
        <p class="alerts-page__section-lead">
            <?= count($alertsActive) === 0
                ? 'Aucune transmission active pour le moment.'
                : (count($alertsActive) === 1
                    ? '1 message actif.'
                    : count($alertsActive) . ' messages actifs.') ?>
        </p>
        <?php if ($alertsActive === []): ?>
            <p class="alerts-page__empty">Aucun message à afficher pour l’instant. Les nouvelles annonces apparaîtront ici dès leur publication.</p>
        <?php else: ?>
            <div class="alerts-page__stack">
                <?php foreach ($alertsActive as $item): ?>
                    <?php $renderCard($item, false); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="alerts-page__section" aria-labelledby="alerts-history-heading">
        <h2 id="alerts-history-heading" class="alerts-page__section-title">Historique</h2>
        <p class="alerts-page__section-lead">Annonces dont la période de diffusion est terminée.</p>
        <?php if ($alertsHistory === []): ?>
            <p class="alerts-page__empty">Pas encore d’historique à afficher.</p>
        <?php else: ?>
            <div class="alerts-page__stack">
                <?php foreach ($alertsHistory as $item): ?>
                    <?php $renderCard($item, true); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
