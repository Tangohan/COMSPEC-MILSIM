    <dialog id="pu-confirm" class="pu-dialog" aria-labelledby="pu-confirm-title">
        <form method="dialog" class="pu-dialog__form">
            <p class="pu-dialog__tag" id="pu-confirm-tag">Confirmer</p>
            <h3 id="pu-confirm-title" class="pu-dialog__title"></h3>
            <ul class="pu-dialog__facts" id="pu-confirm-body"></ul>
            <label class="pu-dialog__field" id="pu-confirm-type-wrap" hidden>
                <span>Pour confirmer, tapez <strong class="pu-mono" id="pu-confirm-type-expected"></strong></span>
                <input type="text" id="pu-confirm-type" autocomplete="off" spellcheck="false">
            </label>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                <button type="button" class="pa-btn pa-btn--solid" id="pu-confirm-ok">Confirmer</button>
            </div>
        </form>
    </dialog>
