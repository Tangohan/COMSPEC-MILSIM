<?php
$c = $courrier ?? [];
$signatures = $c['signatures'] ?? [];
$baseUrl = url('');
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10"
     x-data="courrierSignaturePad()">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= $h((string) \App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-rose-600"><?= $h((string) \App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <header class="mb-8 pb-6 border-b border-slate-200/80">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 mb-1">Bureau Courrier</p>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Ma signature</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-xl">Dessinez votre signature une fois. Elle sera proposée lorsque vous signerez un courrier.</p>
    </header>

    <div class="grid lg:grid-cols-2 gap-6 lg:gap-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-[0.15em] text-slate-900 mb-4">Nouvelle signature</h2>
            <form method="post" action="<?= $h($baseUrl) ?>/courrier/signature" @submit="prepareSubmit($event)">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="image_base64" x-ref="imageField" value="">
                <div class="border border-slate-200 rounded overflow-hidden bg-white">
                    <canvas id="courrier-signature-pad"
                            class="block w-full cursor-crosshair bg-white"
                            style="touch-action: none; height: 180px;"
                            width="480"
                            height="180"
                            aria-label="Zone de dessin de la signature"></canvas>
                    <div class="p-2 flex gap-2 bg-slate-50 border-t border-slate-100">
                        <button type="button" @click="clearPad()" class="px-2 py-1 text-xs border border-slate-200 rounded hover:bg-slate-100">Effacer</button>
                        <span class="text-[11px] text-slate-400 self-center">Dessinez avec la souris ou le doigt</span>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="signature-name" class="block text-xs font-medium text-slate-600 mb-1">Nom de cette signature</label>
                    <input id="signature-name" type="text" name="name" maxlength="80" value="Signature principale" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Par exemple : Signature principale">
                </div>
                <label class="mt-3 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300" <?= $signatures === [] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700">En faire ma signature principale</span>
                </label>
                <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-600" x-cloak></p>
                <button type="submit" class="mt-5 w-full px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">
                    Enregistrer la signature
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-[0.15em] text-slate-900 mb-4">Signatures enregistrées</h2>
            <?php if ($signatures === []): ?>
            <p class="text-sm text-slate-500 py-8 text-center">Aucune signature enregistrée pour le moment. Dessinez-la à gauche.</p>
            <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($signatures as $sig): ?>
                <li class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800"><?= $h((string) ($sig['name'] ?? 'Signature')) ?></p>
                            <?php if (!empty($sig['is_default'])): ?>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700 mt-0.5">Principale</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 mb-3">
                        <img src="<?= $h((string) ($sig['url'] ?? '')) ?>" alt="Aperçu de la signature" class="max-h-16 mx-auto object-contain">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if (empty($sig['is_default'])): ?>
                        <form method="post" action="<?= $h($baseUrl) ?>/courrier/signature/<?= (int) $sig['id'] ?>/default">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded border border-slate-200 text-slate-700 hover:bg-slate-50">En faire la principale</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $h($baseUrl) ?>/courrier/signature/<?= (int) $sig['id'] ?>/delete" onsubmit="return confirm('Retirer cette signature ? Elle ne sera plus proposée lorsque vous signerez un courrier.');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50">Retirer</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

    <p class="mt-8"><a href="<?= $h($baseUrl) ?>/courrier" class="text-sm text-slate-500 hover:text-slate-900">← Bureau Courrier</a></p>
</div>
<script>
(function () {
    window.courrierSignaturePad = function () {
        return {
            error: '',
            canvas: null,
            ctx: null,
            drawing: false,
            hasInk: false,
            padCssW: 480,
            padCssH: 180,
            init() {
                var self = this;
                this.$nextTick(function () {
                    requestAnimationFrame(function () {
                        self.initPad(true);
                    });
                });
            },
            initPad(forceClear) {
                var el = document.getElementById('courrier-signature-pad');
                if (!el) {
                    return;
                }
                this.canvas = el;
                var rect = el.getBoundingClientRect();
                var cssW = Math.max(280, Math.floor(rect.width) || 480);
                var cssH = 180;
                var dpr = window.devicePixelRatio || 1;
                this.padCssW = cssW;
                this.padCssH = cssH;
                el.width = Math.floor(cssW * dpr);
                el.height = Math.floor(cssH * dpr);
                el.style.width = cssW + 'px';
                el.style.height = cssH + 'px';
                this.ctx = el.getContext('2d');
                if (!this.ctx) {
                    return;
                }
                this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                this.ctx.lineWidth = 2.25;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#0b1220';
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, cssW, cssH);
                if (forceClear) {
                    this.hasInk = false;
                }
                if (this._bound) {
                    return;
                }
                this._bound = true;
                var self = this;
                var pos = function (e) {
                    var r = el.getBoundingClientRect();
                    var t = (e.touches && e.touches[0]) ? e.touches[0] : e;
                    var w = self.padCssW || r.width || 480;
                    var h = self.padCssH || r.height || 180;
                    return {
                        x: (t.clientX - r.left) * (w / Math.max(1, r.width)),
                        y: (t.clientY - r.top) * (h / Math.max(1, r.height))
                    };
                };
                var start = function (e) {
                    e.preventDefault();
                    self.drawing = true;
                    var p = pos(e);
                    self.ctx.beginPath();
                    self.ctx.moveTo(p.x, p.y);
                };
                var move = function (e) {
                    if (!self.drawing) {
                        return;
                    }
                    e.preventDefault();
                    var p = pos(e);
                    self.ctx.lineTo(p.x, p.y);
                    self.ctx.stroke();
                    self.hasInk = true;
                };
                var end = function (e) {
                    if (e) {
                        e.preventDefault();
                    }
                    self.drawing = false;
                };
                el.addEventListener('mousedown', start);
                el.addEventListener('mousemove', move);
                el.addEventListener('mouseup', end);
                el.addEventListener('mouseleave', end);
                el.addEventListener('touchstart', start, { passive: false });
                el.addEventListener('touchmove', move, { passive: false });
                el.addEventListener('touchend', end, { passive: false });
                el.addEventListener('touchcancel', end, { passive: false });
            },
            clearPad() {
                if (!this.canvas || !this.ctx) {
                    this.initPad(true);
                    return;
                }
                var w = this.padCssW || 480;
                var h = this.padCssH || 180;
                var dpr = window.devicePixelRatio || 1;
                this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, w, h);
                this.ctx.strokeStyle = '#0b1220';
                this.ctx.lineWidth = 2.25;
                this.hasInk = false;
            },
            prepareSubmit(e) {
                this.error = '';
                if (!this.hasInk || !this.canvas) {
                    e.preventDefault();
                    this.error = 'Dessinez votre signature dans le cadre avant d’enregistrer.';
                    return;
                }
                if (this.$refs.imageField) {
                    this.$refs.imageField.value = this.canvas.toDataURL('image/png');
                }
            }
        };
    };
})();
</script>
