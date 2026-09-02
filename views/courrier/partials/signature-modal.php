<?php
$baseUrl = rtrim((string) url(''), '/');
$documentId = (int) ($document_id ?? 0);
?>
<div id="signature-modal"
     class="fixed inset-0 z-50"
     x-data="signatureModal(<?= $documentId ?>, <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>)"
     x-show="open"
     x-cloak
     @keydown.escape.window="close()"
     style="display: none;">
    <div class="absolute inset-0 bg-black/50" @click="close()"></div>
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-xl shadow-xl p-6 max-h-[90vh] overflow-y-auto"
         @click.stop>
        <h3 class="text-lg font-bold text-slate-800 mb-4">Signer le document</h3>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Signature</label>
                <div class="flex gap-2 mb-2">
                    <button type="button" @click="switchToPad()"
                            class="px-3 py-1.5 text-sm rounded border"
                            :class="usePad ? 'bg-slate-700 text-white border-slate-700' : 'border-slate-200 text-slate-600'">
                        Dessiner
                    </button>
                    <button type="button" @click="usePad = false"
                            class="px-3 py-1.5 text-sm rounded border"
                            :class="!usePad ? 'bg-slate-700 text-white border-slate-700' : 'border-slate-200 text-slate-600'">
                        Ma signature enregistrée
                    </button>
                </div>

                <div x-show="!usePad" x-cloak>
                    <select x-model="selectedSignatureId" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                        <option value="">— Choisir une signature —</option>
                        <template x-for="s in mySignatures" :key="s.id">
                            <option :value="String(s.id)" x-text="s.name || ('Signature #' + s.id)"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-slate-400" x-show="mySignatures.length === 0">Aucune signature enregistrée. <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/courrier/signature" class="underline">Créer ma signature</a></p>
                </div>

                <div x-show="usePad" x-cloak>
                    <div class="border border-slate-200 rounded overflow-hidden bg-white">
                        <canvas id="signature-pad-canvas"
                                class="block w-full cursor-crosshair bg-white"
                                style="touch-action: none; height: 160px;"
                                width="400"
                                height="160"
                                aria-label="Zone de signature"></canvas>
                        <div class="p-2 flex gap-2 bg-slate-50 border-t border-slate-100">
                            <button type="button" @click="clearPad()" class="px-2 py-1 text-xs border border-slate-200 rounded hover:bg-slate-100">Effacer</button>
                            <span class="text-[11px] text-slate-400 self-center">Dessinez avec la souris ou le doigt</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Original signé</label>
                <select x-model="stamps.stamp_original_signed" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                    <option value="">—</option>
                    <option value="Original signé">Original signé</option>
                    <option value="Copie conforme">Copie conforme</option>
                    <option value="Pour copie">Pour copie</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nom Prénom Signature</label>
                <input type="text" x-model="stamps.stamp_name_signature" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Nom Prénom Signature">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Grade</label>
                <input type="text" x-model="stamps.stamp_grade" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Grade">
            </div>

            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="secureHash" class="rounded border-slate-300">
                    <span class="text-sm text-slate-700">Sécuriser le document (hash d'authenticité)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer" x-show="usePad">
                    <input type="checkbox" x-model="saveSignatureAsUser" class="rounded border-slate-300">
                    <span class="text-sm text-slate-700">Enregistrer ma signature pour une réutilisation</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="close()" class="px-4 py-2 border border-slate-200 rounded text-slate-700 hover:bg-slate-50 text-sm">Annuler</button>
            <button type="button" @click="submitSign()" class="px-4 py-2 bg-slate-700 text-white rounded hover:bg-slate-600 text-sm" :disabled="sending">
                <span x-text="sending ? 'Envoi…' : 'Valider la signature'"></span>
            </button>
        </div>
        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600" x-cloak></p>
    </div>
</div>

<script>
(function () {
    window.signatureModal = function (documentId, baseUrl) {
        return {
            open: false,
            documentId: documentId,
            baseUrl: String(baseUrl || '').replace(/\/$/, ''),
            usePad: true,
            selectedSignatureId: '',
            mySignatures: [],
            stamps: { stamp_original_signed: 'Original signé', stamp_name_signature: '', stamp_grade: '' },
            secureHash: true,
            saveSignatureAsUser: false,
            savedSignatureName: 'Signature principale',
            sending: false,
            error: '',
            canvas: null,
            ctx: null,
            drawing: false,
            hasInk: false,
            _padBound: false,

            init() {
                var self = this;
                if (this.documentId > 0) {
                    fetch(this.baseUrl + '/courrier/my-signatures', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (list) { self.mySignatures = Array.isArray(list) ? list : []; })
                        .catch(function () { self.mySignatures = []; });
                }
                var modal = document.getElementById('signature-modal');
                if (modal) {
                    modal.addEventListener('show', function () {
                        self.openModal();
                    });
                }
            },

            openModal() {
                this.open = true;
                this.error = '';
                this.usePad = true;
                this.selectedSignatureId = '';
                this.hasInk = false;
                var self = this;
                this.$nextTick(function () {
                    requestAnimationFrame(function () {
                        self.initPad(true);
                    });
                });
            },

            close() {
                this.open = false;
                this.drawing = false;
            },

            switchToPad() {
                this.usePad = true;
                var self = this;
                this.$nextTick(function () {
                    requestAnimationFrame(function () {
                        self.initPad(false);
                    });
                });
            },

            initPad(forceClear) {
                var el = document.getElementById('signature-pad-canvas');
                if (!el) {
                    return;
                }
                this.canvas = el;
                var rect = el.getBoundingClientRect();
                var cssW = Math.max(280, Math.floor(rect.width) || 400);
                var cssH = 160;
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

                if (this._padBound) {
                    return;
                }
                this._padBound = true;

                var self = this;
                var pos = function (e) {
                    var r = el.getBoundingClientRect();
                    var t = (e.touches && e.touches[0]) ? e.touches[0] : e;
                    var w = self.padCssW || r.width || 400;
                    var h = self.padCssH || r.height || 160;
                    var scaleX = w / Math.max(1, r.width);
                    var scaleY = h / Math.max(1, r.height);
                    return {
                        x: (t.clientX - r.left) * scaleX,
                        y: (t.clientY - r.top) * scaleY
                    };
                };
                var start = function (e) {
                    if (!self.usePad || !self.open) {
                        return;
                    }
                    e.preventDefault();
                    self.drawing = true;
                    var p = pos(e);
                    self.ctx.beginPath();
                    self.ctx.moveTo(p.x, p.y);
                };
                var move = function (e) {
                    if (!self.drawing || !self.usePad) {
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
                var w = this.padCssW || this.canvas.clientWidth || 400;
                var h = this.padCssH || this.canvas.clientHeight || 160;
                var dpr = window.devicePixelRatio || 1;
                this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, w, h);
                this.ctx.strokeStyle = '#0b1220';
                this.ctx.lineWidth = 2.25;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.hasInk = false;
            },

            getPadDataUrl() {
                if (!this.canvas || !this.hasInk) {
                    return null;
                }
                return this.canvas.toDataURL('image/png');
            },

            submitSign() {
                var self = this;
                this.error = '';
                var imageBase64 = null;
                if (this.usePad) {
                    imageBase64 = this.getPadDataUrl();
                    if (!imageBase64) {
                        this.error = 'Dessinez votre signature dans le cadre avant de valider.';
                        return;
                    }
                } else if (!this.selectedSignatureId) {
                    this.error = 'Choisissez une signature enregistrée.';
                    return;
                }

                this.sending = true;
                var payload = {
                    stamp_original_signed: this.stamps.stamp_original_signed || '',
                    stamp_name_signature: this.stamps.stamp_name_signature || '',
                    stamp_grade: this.stamps.stamp_grade || '',
                    secure_hash: !!this.secureHash,
                    save_signature_as_user: !!this.saveSignatureAsUser,
                    saved_signature_name: this.savedSignatureName || 'Signature principale'
                };
                if (this.usePad) {
                    payload.image_base64 = imageBase64;
                } else {
                    payload.user_signature_id = parseInt(this.selectedSignatureId, 10);
                }

                var csrf = document.querySelector('input[name="_csrf_token"]');
                if (csrf) {
                    payload._csrf_token = csrf.value;
                }

                fetch(this.baseUrl + '/courrier/documents/' + this.documentId + '/sign', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(function (r) {
                        return r.json().then(function (data) {
                            return { ok: r.ok, data: data };
                        });
                    })
                    .then(function (res) {
                        self.sending = false;
                        if (res.data && res.data.success) {
                            self.close();
                            window.location.reload();
                            return;
                        }
                        self.error = (res.data && res.data.message) ? res.data.message : 'Erreur lors de la signature.';
                    })
                    .catch(function () {
                        self.sending = false;
                        self.error = 'Erreur réseau. Vérifiez votre connexion et réessayez.';
                    });
            }
        };
    };
})();
</script>
