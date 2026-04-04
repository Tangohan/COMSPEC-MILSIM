<?php
$baseUrl = url('');
$documentId = (int)($document_id ?? 0);
?>
<div id="signature-modal" class="fixed inset-0 z-50" x-data="signatureModal(<?= $documentId ?>, '<?= $baseUrl ?>')" x-show="open" x-cloak      @keydown.escape.window="open = false">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-xl shadow-xl p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Signer le document</h3>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Signature</label>
                <div class="flex gap-2 mb-2">
                    <button type="button" @click="usePad = true; selectedSignatureId = null"
                            class="px-3 py-1.5 text-sm rounded border"
                            :class="usePad ? 'bg-slate-700 text-white border-slate-700' : 'border-slate-200 text-slate-600'">
                        Dessiner
                    </button>
                    <button type="button" @click="usePad = false; clearPad()"
                            class="px-3 py-1.5 text-sm rounded border"
                            :class="!usePad ? 'bg-slate-700 text-white border-slate-700' : 'border-slate-200 text-slate-600'">
                        Ma signature enregistrée
                    </button>
                </div>
                <template x-if="!usePad">
                    <select x-model="selectedSignatureId" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                        <option value="">— Choisir une signature —</option>
                        <template x-for="s in mySignatures" :key="s.id">
                            <option :value="s.id" x-text="s.name || ('Signature #' + s.id)"></option>
                        </template>
                    </select>
                </template>
                <template x-if="usePad">
                    <div class="border border-slate-200 rounded overflow-hidden bg-slate-50">
                        <canvas id="signature-pad-canvas" width="400" height="160" class="block w-full touch-none cursor-crosshair"
                                style="touch-action: none;"></canvas>
                        <div class="p-2 flex gap-2">
                            <button type="button" @click="clearPad()" class="px-2 py-1 text-xs border border-slate-200 rounded hover:bg-slate-100">Effacer</button>
                        </div>
                    </div>
                </template>
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
            <button type="button" @click="open = false" class="px-4 py-2 border border-slate-200 rounded text-slate-700 hover:bg-slate-50 text-sm">Annuler</button>
            <button type="button" @click="submitSign()" class="px-4 py-2 bg-slate-700 text-white rounded hover:bg-slate-600 text-sm" :disabled="sending">
                <span x-text="sending ? 'Envoi…' : 'Valider la signature'"></span>
            </button>
        </div>
        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
    </div>
</div>

<script>
(function() {
    window.signatureModal = function(documentId, baseUrl) {
        return {
            open: false,
            documentId: documentId,
            baseUrl: baseUrl,
            usePad: true,
            selectedSignatureId: null,
            mySignatures: [],
            stamps: { stamp_original_signed: '', stamp_name_signature: '', stamp_grade: '' },
            secureHash: true,
            saveSignatureAsUser: false,
            savedSignatureName: 'Signature principale',
            sending: false,
            error: '',
            canvas: null,
            ctx: null,
            drawing: false,

            init() {
                if (this.documentId > 0) {
                    fetch(this.baseUrl + '/courrier/my-signatures', { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(list => { this.mySignatures = Array.isArray(list) ? list : []; })
                        .catch(() => {});
                }
                document.getElementById('signature-modal').addEventListener('show', () => {
                    this.open = true;
                    this.error = '';
                    this.$nextTick(() => this.initPad());
                });
            },

            initPad() {
                var el = document.getElementById('signature-pad-canvas');
                if (!el) return;
                this.canvas = el;
                this.ctx = el.getContext('2d');
                if (!this.ctx) return;
                this.ctx.strokeStyle = '#000';
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                var self = this;
                function pos(e) {
                    var r = el.getBoundingClientRect();
                    var t = (e.touches && e.touches[0]) ? e.touches[0] : e;
                    return { x: t.clientX - r.left, y: t.clientY - r.top };
                }
                function start(e) { e.preventDefault(); self.drawing = true; var p = pos(e); self.ctx.beginPath(); self.ctx.moveTo(p.x, p.y); }
                function move(e) { e.preventDefault(); if (!self.drawing) return; var p = pos(e); self.ctx.lineTo(p.x, p.y); self.ctx.stroke(); }
                function end(e) { e.preventDefault(); self.drawing = false; }
                el.addEventListener('mousedown', start);
                el.addEventListener('mousemove', move);
                el.addEventListener('mouseup', end);
                el.addEventListener('mouseleave', end);
                el.addEventListener('touchstart', start, { passive: false });
                el.addEventListener('touchmove', move, { passive: false });
                el.addEventListener('touchend', end, { passive: false });
            },

            clearPad() {
                if (this.canvas && this.ctx) {
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                }
            },

            getPadDataUrl() {
                if (!this.canvas) return null;
                return this.canvas.toDataURL('image/png');
            },

            submitSign() {
                this.error = '';
                var imageBase64 = null;
                if (this.usePad) {
                    imageBase64 = this.getPadDataUrl();
                    if (!imageBase64) { this.error = 'Dessinez une signature ou choisissez une signature enregistrée.'; return; }
                } else {
                    if (!this.selectedSignatureId) { this.error = 'Choisissez une signature enregistrée.'; return; }
                }
                this.sending = true;
                var payload = {
                    stamp_original_signed: this.stamps.stamp_original_signed || '',
                    stamp_name_signature: this.stamps.stamp_name_signature || '',
                    stamp_grade: this.stamps.stamp_grade || '',
                    secure_hash: this.secureHash,
                    save_signature_as_user: this.saveSignatureAsUser,
                    saved_signature_name: this.savedSignatureName
                };
                if (this.usePad) payload.image_base64 = imageBase64;
                else payload.user_signature_id = this.selectedSignatureId;

                var csrf = document.querySelector('input[name="_csrf_token"]');
                if (csrf) payload._csrf_token = csrf.value;
                var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };

                fetch(this.baseUrl + '/courrier/documents/' + this.documentId + '/sign', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers,
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(data => {
                    this.sending = false;
                    if (data.success) {
                        this.open = false;
                        window.location.reload();
                    } else {
                        this.error = data.message || 'Erreur lors de la signature.';
                    }
                })
                .catch(() => {
                    this.sending = false;
                    this.error = 'Erreur réseau.';
                });
            }
        };
    };
})();
</script>
