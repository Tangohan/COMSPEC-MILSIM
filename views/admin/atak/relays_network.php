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
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 tracking-tight">Mâts visibles</h2>
                        <p class="text-xs text-slate-500 mt-0.5"><?= count($relays) ?> antenne<?= count($relays) > 1 ? 's' : '' ?> remontée<?= count($relays) > 1 ? 's' : '' ?> depuis le théâtre</p>
                    </div>
                    <a href="#tutoriel-relais" class="text-xs font-semibold text-emerald-800 hover:underline">Comment ça marche ?</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3 font-semibold">État</th>
                                <th class="px-5 py-3 font-semibold">Nom</th>
                                <th class="px-5 py-3 font-semibold">Portée</th>
                                <th class="px-5 py-3 font-semibold">Places</th>
                                <th class="px-5 py-3 font-semibold">Débit</th>
                                <th class="px-5 py-3 font-semibold">Fiabilité</th>
                                <th class="px-5 py-3 font-semibold">Dernière activité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($relays as $relay): ?>
                                <?php
                                $alive = !empty($relay['alive']);
                                $reliability = (int) ($relay['reliability_pct'] ?? 0);
                                $meta = $statusMeta($alive, $reliability);
                                $slots = (int) ($relay['slots'] ?? 0);
                                $used = (int) ($relay['slots_used'] ?? 0);
                                $name = trim((string) ($relay['display_name'] ?? ''));
                                if ($name === '') {
                                    $name = trim((string) ($relay['relay_uid'] ?? 'Relais'));
                                }
                                $identity = trim((string) ($relay['identity'] ?? ''));
                                ?>
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold <?= $h($meta['class']) ?>">
                                            <?= $h($meta['label']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-slate-900"><?= $h($name) ?></div>
                                        <?php if ($identity !== ''): ?>
                                            <div class="text-xs text-slate-500 mt-0.5"><?= $h($identity) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-800"><?= number_format((float) ($relay['range_m'] ?? 0), 0, ',', ' ') ?> m</td>
                                    <td class="px-5 py-3.5 text-slate-800"><?= $used ?> / <?= $slots ?></td>
                                    <td class="px-5 py-3.5 text-slate-800"><?= number_format((float) ($relay['throughput_mbps'] ?? 0), 1, ',', ' ') ?> Mbit/s</td>
                                    <td class="px-5 py-3.5 text-slate-800"><?= $reliability ?> %</td>
                                    <td class="px-5 py-3.5 text-slate-600"><?= $h($formatSeen(isset($relay['last_seen_at']) ? (string) $relay['last_seen_at'] : null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
