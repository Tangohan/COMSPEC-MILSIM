<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $relays */
/** @var array<string, mixed> $stats */
/** @var bool $linkViaRelays */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$relays = is_array($relays ?? null) ? $relays : [];
$stats = is_array($stats ?? null) ? $stats : [];
$linkViaRelays = !empty($linkViaRelays);

$formatSeen = static function (?string $timestamp): string {
    if ($timestamp === null || trim($timestamp) === '') {
        return 'Jamais vu';
    }
    try {
        $dt = new DateTimeImmutable($timestamp);
    } catch (Throwable) {
        return 'Date inconnue';
    }
    $diff = (new DateTimeImmutable('now'))->getTimestamp() - $dt->getTimestamp();
    if ($diff < 120) {
        return 'À l’instant';
    }
    if ($diff < 3600) {
        return 'Il y a ' . max(1, (int) round($diff / 60)) . ' min';
    }
    if ($diff < 86400) {
        return 'Il y a ' . max(1, (int) round($diff / 3600)) . ' h';
    }

    return $dt->format('d/m H:i');
};

$statusMeta = static function (bool $alive, int $reliability): array {
    if (!$alive) {
        return ['label' => 'Hors service', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'];
    }
    if ($reliability >= 90) {
        return ['label' => 'En service', 'class' => 'bg-emerald-50 text-emerald-900 border-emerald-200'];
    }
    if ($reliability >= 70) {
        return ['label' => 'Correct', 'class' => 'bg-sky-50 text-sky-900 border-sky-200'];
    }
    if ($reliability >= 50) {
        return ['label' => 'Dégradé', 'class' => 'bg-amber-50 text-amber-950 border-amber-200'];
    }

    return ['label' => 'Critique', 'class' => 'bg-rose-50 text-rose-900 border-rose-200'];
};
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1120px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <header class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-emerald-50/40 shadow-sm">
            <div class="relative px-5 sm:px-8 py-7">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">ATAK · Relais</p>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Réseau de relais</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                    Les mâts Relais posés en mission apparaissent ici : état, portée, places et fiabilité.
                    Le poste Overwatch et l’application Relais AT du téléphone lisent les mêmes informations.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?= $h(url('back-office/atak/controle-serveur')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Contrôle de mission</a>
                    <a href="<?= $h(url('back-office/atak/roleplay')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Simulation réseau</a>
                    <a href="#tutoriel-relais" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Tutoriel Relais</a>
                    <a href="<?= $h(url('atak')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Carte du poste</a>
                </div>
            </div>
        </header>

        <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3" aria-label="Situation du réseau">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">En service</p>
                <p class="mt-2 text-2xl font-black text-emerald-800"><?= (int) ($stats['alive'] ?? 0) ?></p>
                <p class="mt-1 text-xs text-slate-500">Mâts intacts</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Hors service</p>
                <p class="mt-2 text-2xl font-black text-slate-800"><?= (int) ($stats['dead'] ?? 0) ?></p>
                <p class="mt-1 text-xs text-slate-500">Détruits ou absents</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Places</p>
                <p class="mt-2 text-2xl font-black text-slate-900"><?= (int) ($stats['used_slots'] ?? 0) ?> / <?= (int) ($stats['total_slots'] ?? 0) ?></p>
                <p class="mt-1 text-xs text-slate-500">Téléphones qui s’appuient sur un mât</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Règle de liaison</p>
                <p class="mt-2 text-lg font-black text-slate-900"><?= $linkViaRelays ? 'Relais obligatoire' : 'Liaison libre' ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= $linkViaRelays
                    ? 'Hors portée d’un mât intact : plus de données ATAK'
                    : 'Le téléphone peut transmettre sans mât' ?></p>
            </div>
        </section>

        <?php if ($relays === []): ?>
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm px-6 py-10 text-center">
                <p class="text-lg font-bold text-slate-900">Aucun mât Relais sur ce théâtre</p>
                <p class="mt-2 text-sm text-slate-600 max-w-xl mx-auto leading-relaxed">
                    Dès qu’un mât est posé en mission (éditeur ou Zeus), il apparaît ici, sur la carte du poste et dans Relais AT sur le téléphone.
                </p>
                <a href="#tutoriel-relais" class="mt-6 inline-flex items-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Ouvrir le tutoriel
                </a>
            </section>
        <?php else: ?>
            <?php
            // Emprise de la carte de couverture : tous les mâts et leur portée, avec 5 % de marge.
            $minX = $minY = INF;
            $maxX = $maxY = -INF;
            foreach ($relays as $relay) {
                $rx = (float) ($relay['pos_x'] ?? 0);
                $ry = (float) ($relay['pos_y'] ?? 0);
                $rr = max(50.0, (float) ($relay['range_m'] ?? 0));
                $minX = min($minX, $rx - $rr);
                $maxX = max($maxX, $rx + $rr);
                $minY = min($minY, $ry - $rr);
                $maxY = max($maxY, $ry + $rr);
            }
            $span = max($maxX - $minX, $maxY - $minY, 1.0) * 1.1;
            $cx = ($minX + $maxX) / 2;
            $cy = ($minY + $maxY) / 2;
            $vb = 1000;
            $toX = static fn (float $x): float => round(($x - ($cx - $span / 2)) / $span * $vb, 1);
            $toY = static fn (float $y): float => round($vb - ($y - ($cy - $span / 2)) / $span * $vb, 1);
            $scaleM = $span >= 8000 ? 2000 : ($span >= 3000 ? 1000 : 500);
            ?>
            <section class="atk-panel atk-admin atk-dev">
                <div class="atk-panel__head">
                    <div>
                        <h2>Couverture radio</h2>
                        <p><?= count($relays) ?> mât<?= count($relays) > 1 ? 's' : '' ?> remonté<?= count($relays) > 1 ? 's' : '' ?> depuis le théâtre. Cercles à l’échelle de la portée déclarée ; carroyage Arma de 100 m.</p>
                    </div>
                    <a href="#tutoriel-relais" class="atk-btn">Comment ça marche ?</a>
                </div>
                <div class="atk-panel__body">
                    <div class="atk-coverage">
                        <svg viewBox="0 0 <?= $vb ?> <?= $vb ?>" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Carte de couverture des mâts Relais">
                            <g>
                                <?php foreach ($relays as $relay):
                                    $alive = !empty($relay['alive']);
                                    $px = $toX((float) ($relay['pos_x'] ?? 0));
                                    $py = $toY((float) ($relay['pos_y'] ?? 0));
                                    $pr = round(max(50.0, (float) ($relay['range_m'] ?? 0)) / $span * $vb, 1);
                                    $col = $alive ? ((int) ($relay['reliability_pct'] ?? 0) >= 70 ? '#4fd3a2' : '#f0c27a') : '#f08585';
                                    $nm = trim((string) ($relay['display_name'] ?? '')) ?: trim((string) ($relay['relay_uid'] ?? 'Relais'));
                                    ?>
                                    <circle cx="<?= $px ?>" cy="<?= $py ?>" r="<?= $pr ?>" fill="<?= $col ?>" fill-opacity=".1" stroke="<?= $col ?>" stroke-opacity=".7" stroke-width="2" <?= $alive ? '' : 'stroke-dasharray="8 6"' ?>/>
                                    <circle cx="<?= $px ?>" cy="<?= $py ?>" r="7" fill="<?= $col ?>"/>
                                    <text x="<?= $px + 12 ?>" y="<?= $py - 10 ?>" fill="#e8f3ee" font-size="22" font-family="system-ui, sans-serif" font-weight="700"><?= $h($nm) ?></text>
                                    <text x="<?= $px + 12 ?>" y="<?= $py + 16 ?>" fill="#9aa6a1" font-size="18" font-family="ui-monospace, monospace"><?= $h(\App\Support\AtakDevicePresenter::gridRef($relay['pos_x'] ?? null, $relay['pos_y'] ?? null)) ?></text>
                                <?php endforeach; ?>
                            </g>
                            <?php $barPx = round($scaleM / $span * $vb, 1); ?>
                            <g transform="translate(24 <?= $vb - 28 ?>)">
                                <rect x="0" y="0" width="<?= $barPx ?>" height="6" fill="#e8f3ee"/>
                                <text x="0" y="-8" fill="#e8f3ee" font-size="18" font-family="system-ui, sans-serif"><?= $scaleM >= 1000 ? ($scaleM / 1000) . ' km' : $scaleM . ' m' ?></text>
                            </g>
                        </svg>
                    </div>
                    <div class="atk-coverage__legend">
                        <span><i style="background:#4fd3a2"></i>En service</span>
                        <span><i style="background:#f0c27a"></i>Dégradé (fiabilité &lt; 70 %)</span>
                        <span><i style="background:#f08585"></i>Hors service</span>
                    </div>
                </div>
            </section>

            <section class="atk-relays atk-admin atk-dev" aria-label="Fiches des mâts">
                <?php foreach ($relays as $relay):
                    $alive = !empty($relay['alive']);
                    $reliability = max(0, min(100, (int) ($relay['reliability_pct'] ?? 0)));
                    $meta = $statusMeta($alive, $reliability);
                    $slots = (int) ($relay['slots'] ?? 0);
                    $used = (int) ($relay['slots_used'] ?? 0);
                    $load = $slots > 0 ? (int) min(100, round($used * 100 / $slots)) : 0;
                    $name = trim((string) ($relay['display_name'] ?? '')) ?: trim((string) ($relay['relay_uid'] ?? 'Relais'));
                    $identity = trim((string) ($relay['identity'] ?? ''));
                    $power = (int) ($relay['power_w'] ?? 0);
                    $dbm = \App\Support\AtakDevicePresenter::wattsToDbm($power);
                    $tone = !$alive ? 'bad' : ($reliability >= 70 ? 'ok' : 'warn');
                    $cert = trim((string) ($relay['certificate'] ?? ''));
                    ?>
                    <article class="atk-relay<?= $alive ? '' : ' is-dead' ?>">
                        <header class="atk-relay__head">
                            <div>
                                <strong><?= $h($name) ?></strong>
                                <small><?= $h($identity !== '' ? $identity : (string) ($relay['relay_uid'] ?? '')) ?></small>
                            </div>
                            <span class="atk-chip atk-chip--<?= $h($tone) ?>"><?= $h($meta['label']) ?></span>
                        </header>
                        <div class="atk-relay__meters">
                            <div class="atk-meter" style="max-width:none">
                                <span>Places</span>
                                <div class="atk-meter__bar<?= $load >= 90 ? ' is-low' : ($load >= 70 ? ' is-mid' : '') ?>"><i style="width: <?= $load ?>%"></i></div>
                                <strong><?= $used ?> / <?= $slots ?></strong>
                            </div>
                            <div class="atk-meter" style="max-width:none">
                                <span>Fiabilité</span>
                                <div class="atk-meter__bar<?= $reliability < 50 ? ' is-low' : ($reliability < 70 ? ' is-mid' : '') ?>"><i style="width: <?= $alive ? $reliability : 0 ?>%"></i></div>
                                <strong><?= $alive ? $reliability : 0 ?> %</strong>
                            </div>
                        </div>
                        <div class="atk-sheet">
                            <dl>
                                <div><dt>Carroyage</dt><dd class="is-mono"><?= $h(\App\Support\AtakDevicePresenter::gridRef($relay['pos_x'] ?? null, $relay['pos_y'] ?? null)) ?></dd></div>
                                <div><dt>Altitude de l’antenne</dt><dd><?= number_format((float) ($relay['pos_z'] ?? 0), 0, ',', ' ') ?> m</dd></div>
                                <div><dt>Portée</dt><dd><?= number_format((float) ($relay['range_m'] ?? 0), 0, ',', ' ') ?> m</dd></div>
                                <div><dt>Puissance d’émission</dt><dd><?= $power > 0 ? $power . ' W' . ($dbm !== null ? ' · ' . number_format($dbm, 1, ',', ' ') . ' dBm' : '') : '—' ?></dd></div>
                                <div><dt>Débit</dt><dd><?= $alive ? number_format((float) ($relay['throughput_mbps'] ?? 0), 1, ',', ' ') . ' Mbit/s' : '0 Mbit/s' ?></dd></div>
                                <div><dt>Dernière activité</dt><dd><?= $h($formatSeen(isset($relay['last_seen_at']) ? (string) $relay['last_seen_at'] : null)) ?></dd></div>
                                <div><dt>Adresse IP</dt><dd class="is-mono"><?= $h(trim((string) ($relay['ip_addr'] ?? '')) ?: '—') ?></dd></div>
                                <div><dt>Passerelle</dt><dd class="is-mono"><?= $h(trim((string) ($relay['gateway'] ?? '')) ?: '—') ?></dd></div>
                                <?php if ($cert !== ''): ?><div class="is-wide"><dt>Certificat du mât</dt><dd class="is-mono is-wrap"><?= $h($cert) ?></dd></div><?php endif; ?>
                            </dl>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section id="tutoriel-relais" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden scroll-mt-8">
            <div class="px-5 sm:px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Tutoriel</p>
                <h2 class="mt-1 text-xl font-black text-slate-900 tracking-tight">Mettre en place les mâts Relais</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                    Six étapes pour le commandement, Zeus et les opérateurs. Aucun fichier externe : tout se lit ici.
                </p>
            </div>

            <div class="px-5 sm:px-6 pt-4 flex flex-wrap gap-2" role="tablist" aria-label="Étapes du tutoriel Relais">
                <?php
                $steps = [
                    '1' => 'À quoi ça sert',
                    '2' => 'Poser un mât',
                    '3' => 'Sur le téléphone',
                    '4' => 'Au poste',
                    '5' => 'Règle obligatoire',
                    '6' => 'Détruire un mât',
                ];
                foreach ($steps as $id => $label):
                ?>
                    <button type="button"
                        class="relay-tuto-tab inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors <?= $id === '1' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
                        data-relay-tuto="<?= $h($id) ?>"
                        role="tab"
                        aria-selected="<?= $id === '1' ? 'true' : 'false' ?>">
                        <?= $h($id . '. ' . $label) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="px-5 sm:px-6 py-6 space-y-0">
                <article class="relay-tuto-panel" data-relay-panel="1" role="tabpanel">
                    <h3 class="text-base font-bold text-slate-900">Un mât Relais, c’est une antenne de liaison</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                        Sur le théâtre, un mât Relais représente une antenne autour de laquelle les téléphones ATAK peuvent s’appuyer.
                        Chaque mât a une portée, un nombre de places, un débit et une fiabilité. S’il est détruit, il ne sert plus.
                    </p>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Les opérateurs voient le mât le plus proche dans l’application <strong>Relais AT</strong> du téléphone.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Le poste voit les mâts sur la carte et dans cette page.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Si la règle « Relais obligatoire » est active, un téléphone hors portée d’un mât intact ne transmet plus.</span></li>
                    </ul>
                </article>

                <article class="relay-tuto-panel hidden" data-relay-panel="2" role="tabpanel" hidden>
                    <h3 class="text-base font-bold text-slate-900">Poser un mât depuis l’éditeur ou Zeus</h3>
                    <ol class="mt-3 space-y-3 text-sm text-slate-700 leading-relaxed list-decimal pl-5">
                        <li>Dans l’éditeur Eden : ouvrez les modules <strong>COMSPEC</strong>, choisissez <strong>Relais ATAK (mât)</strong>, placez-le sur une colline, un toit ou une zone d’atterrissage.</li>
                        <li>Renseignez au minimum le <strong>nom</strong>, la <strong>portée</strong> et l’<strong>identité</strong> (ex. RLY-NORD-01). Les places, le débit, la fiabilité et la puissance peuvent rester aux valeurs proposées.</li>
                        <li>Laissez l’adresse réseau et la passerelle vides si vous voulez qu’elles soient générées à la pose.</li>
                        <li>En mission, Zeus peut aussi poser ou détruire un mât déjà présent.</li>
                    </ol>
                    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Astuce : un mât trop bas ou coincé entre des murs couvre mal. Préférez un point haut, visible, à une distance utile de la zone d’action.
                    </p>
                </article>

                <article class="relay-tuto-panel hidden" data-relay-panel="3" role="tabpanel" hidden>
                    <h3 class="text-base font-bold text-slate-900">Ce que voit l’opérateur sur le téléphone</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                        Dans le menu d’applications du téléphone, ouvrez <strong>Relais AT</strong>.
                    </p>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Le mât le plus proche s’affiche avec son nom, sa grille, son débit et sa fiabilité.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Les barres de signal se remplissent près du mât, et baissent quand on s’éloigne.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Si le mât est détruit, Relais AT indique clairement qu’il est hors service.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Un avis prévient quand on quitte la portée d’un mât encore intact.</span></li>
                    </ul>
                </article>

                <article class="relay-tuto-panel hidden" data-relay-panel="4" role="tabpanel" hidden>
                    <h3 class="text-base font-bold text-slate-900">Ce que voit le poste de commandement</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Sur Overwatch Beta : calque <strong>Relais ATAK</strong> et espace <strong>Réseau</strong> pour la liste.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Sur cette page : état, portée, places occupées, débit et dernière activité.</span></li>
                        <li class="flex gap-2"><span class="text-emerald-700 font-bold">•</span><span>Un mât détruit passe hors service ici et sur la carte (pastille et emprise rouges).</span></li>
                    </ul>
                    <p class="mt-4 text-sm text-slate-600 leading-relaxed">
                        Si la liste reste vide alors qu’un mât a été posé, vérifiez que la mission a bien démarré avec Overwatch et que le mât a été placé via le module Relais ATAK.
                    </p>
                </article>

                <article class="relay-tuto-panel hidden" data-relay-panel="5" role="tabpanel" hidden>
                    <h3 class="text-base font-bold text-slate-900">Rendre le relais obligatoire</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                        Par défaut, le téléphone peut transmettre sans mât. Vous pouvez imposer le passage par antenne pour toute la communauté.
                    </p>
                    <ol class="mt-3 space-y-3 text-sm text-slate-700 leading-relaxed list-decimal pl-5">
                        <li>Ouvrez <a class="font-semibold text-emerald-800 underline" href="<?= $h(url('back-office/atak/controle-serveur')) ?>">Contrôle de mission</a>.</li>
                        <li>Dans la section Relais de liaison, activez l’exigence d’un relais intact.</li>
                        <li>Enregistrez. Dès lors, un téléphone hors portée d’un mât en service ne remonte plus de données ATAK.</li>
                    </ol>
                    <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Règle actuelle pour votre communauté :
                        <strong><?= $linkViaRelays ? 'Relais obligatoire' : 'Liaison libre (sans mât exigé)' ?></strong>.
                    </p>
                </article>

                <article class="relay-tuto-panel hidden" data-relay-panel="6" role="tabpanel" hidden>
                    <h3 class="text-base font-bold text-slate-900">Détruire un mât et en voir l’effet</h3>
                    <ol class="mt-3 space-y-3 text-sm text-slate-700 leading-relaxed list-decimal pl-5">
                        <li>En jeu, détruisez le mât (tir, charge, ou action Zeus).</li>
                        <li>Sur le téléphone, Relais AT passe le mât en hors service : débit et puissance tombent à zéro.</li>
                        <li>Au poste, la pastille devient rouge et cette page affiche « Hors service ».</li>
                        <li>Si la règle obligatoire est active, les téléphones qui ne dépendaient que de ce mât perdent la liaison jusqu’à rejoindre un autre mât intact.</li>
                    </ol>
                    <p class="mt-4 text-sm text-slate-600 leading-relaxed">
                        Pour rejouer le scénario : posez un nouveau mât, ou réparez / remplacez l’objet selon votre mise en scène.
                    </p>
                </article>
            </div>

            <div class="px-5 sm:px-6 pb-6 flex flex-wrap gap-2">
                <button type="button" id="relay-tuto-prev" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" disabled>Étape précédente</button>
                <button type="button" id="relay-tuto-next" class="inline-flex items-center rounded-lg border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Étape suivante</button>
            </div>
        </section>
    </div>
</div>
<script>
(function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-relay-tuto]'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('[data-relay-panel]'));
  var prev = document.getElementById('relay-tuto-prev');
  var next = document.getElementById('relay-tuto-next');
  var current = '1';

  function show(id) {
    current = String(id);
    tabs.forEach(function (btn) {
      var on = btn.getAttribute('data-relay-tuto') === current;
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      btn.className = 'relay-tuto-tab inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors ' +
        (on ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50');
    });
    panels.forEach(function (panel) {
      var on = panel.getAttribute('data-relay-panel') === current;
      panel.classList.toggle('hidden', !on);
      if (on) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', 'hidden');
    });
    if (prev) prev.disabled = current === '1';
    if (next) next.disabled = current === '6';
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () { show(btn.getAttribute('data-relay-tuto')); });
  });
  if (prev) prev.addEventListener('click', function () {
    var n = Math.max(1, Number(current) - 1);
    show(String(n));
  });
  if (next) next.addEventListener('click', function () {
    var n = Math.min(6, Number(current) + 1);
    show(String(n));
  });
  show('1');
})();
</script>
