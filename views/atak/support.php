<?php
/** @var list<int> $presets */
/** @var int $minEur */
/** @var int $maxEur */
/** @var bool $isLoggedIn */
/** @var bool $alreadyDonor */
/** @var string $csrfToken */
/** @var string|null $flashError */
/** @var string|null $flashInfo */
/** @var bool $stripeReady */
/** @var bool $schemaReady */
$cancelled = isset($_GET['annule']);
?>
<div class="min-h-[calc(80vh-2rem)] bg-gradient-to-b from-slate-100 via-slate-50 to-emerald-50/40">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-14">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_24px_80px_-32px_rgba(15,23,42,0.28)]">
            <div class="relative border-b border-slate-800/10 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-10 sm:px-10">
                <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-emerald-500/20 blur-3xl" aria-hidden="true"></div>
                <p class="relative text-[11px] font-black uppercase tracking-[0.35em] text-emerald-400/95">Financement ATAK</p>
                <h1 class="relative mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Soutenir le module ATAK</h1>
                <p class="relative mt-4 max-w-xl text-sm leading-relaxed text-slate-300">
                    Contribuez au développement de la carte tactique et des outils de liaison. Chaque participation aide à maintenir et enrichir ATAK pour toute la communauté.
                </p>
            </div>

            <div class="space-y-8 px-6 py-8 sm:px-10 sm:py-10">
                <?php if (!empty($flashError)): ?>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($flashInfo)): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    <?= htmlspecialchars((string) $flashInfo, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
                <?php if ($cancelled): ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
                    Paiement annulé. Vous pouvez choisir un autre montant quand vous le souhaitez.
                </div>
                <?php endif; ?>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">Badge donateur</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">Donateur ATAK</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">
                            Après un paiement validé, le badge est ajouté à votre profil dans vos communautés. Il apparaît sur votre fiche et dans l’annuaire.
                        </p>
                        <?php if (!empty($alreadyDonor)): ?>
                        <p class="mt-3 text-xs font-semibold text-emerald-700">Vous avez déjà le badge — merci pour votre soutien.</p>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">À quoi ça sert</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">Carte, liaison, évolutions</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">
                            Hébergement, outils de terrain et nouvelles fonctions ATAK. Ce n’est pas un abonnement communauté : c’est un soutien volontaire au module.
                        </p>
                    </div>
                </div>

                <?php if (empty($schemaReady) || empty($stripeReady)): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Le paiement n’est pas disponible pour le moment. Réessayez plus tard ou contactez l’équipe Athena.
                </div>
                <?php elseif (empty($isLoggedIn)): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center">
                    <p class="text-sm text-slate-700">Connectez-vous pour choisir un montant et recevoir le badge donateur.</p>
                    <a href="<?= htmlspecialchars(url('soutenir-atak/connexion'), ENT_QUOTES, 'UTF-8') ?>"
                       class="mt-5 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-800">
                        Se connecter
                    </a>
                </div>
                <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(url('soutenir-atak/checkout'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-6" id="atak-support-form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <fieldset>
                        <legend class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Choisir un montant</legend>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <?php foreach ($presets as $i => $eur): ?>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="amount_preset" value="<?= (int) $eur ?>" class="peer sr-only" <?= $i === 1 ? 'checked' : '' ?> data-atak-preset>
                                <span class="flex items-center justify-center rounded-2xl border-2 border-slate-200 bg-white px-4 py-4 text-lg font-black text-slate-900 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-900 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">
                                    <?= (int) $eur ?>&nbsp;€
                                </span>
                            </label>
                            <?php endforeach; ?>
                            <label class="relative cursor-pointer sm:col-span-3">
                                <input type="radio" name="amount_preset" value="custom" class="peer sr-only" data-atak-preset data-atak-custom-radio>
                                <span class="flex flex-col gap-3 rounded-2xl border-2 border-slate-200 bg-white px-4 py-4 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="text-sm font-bold text-slate-900">Autre montant</span>
                                    <span class="flex items-center gap-2">
                                        <input type="number"
                                               name="amount_custom"
                                               id="atak-amount-custom"
                                               min="<?= (int) $minEur ?>"
                                               max="<?= (int) $maxEur ?>"
                                               step="1"
                                               inputmode="decimal"
                                               placeholder="<?= (int) $minEur ?> – <?= (int) $maxEur ?>"
                                               class="w-36 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                                               aria-label="Montant libre en euros">
                                        <span class="text-sm font-bold text-slate-600">€</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Montant libre entre <?= (int) $minEur ?> € et <?= (int) $maxEur ?> €. Paiement sécurisé.</p>
                    </fieldset>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-600">
                            Continuer vers le paiement
                        </button>
                        <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="text-center text-xs font-semibold text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline">
                            Retour à ATAK
                        </a>
                    </div>
                </form>
                <script>
                (function () {
                  var customInput = document.getElementById('atak-amount-custom');
                  var customRadio = document.querySelector('[data-atak-custom-radio]');
                  if (customInput && customRadio) {
                    customInput.addEventListener('focus', function () {
                      customRadio.checked = true;
                    });
                    customInput.addEventListener('input', function () {
                      customRadio.checked = true;
                    });
                  }
                })();
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
