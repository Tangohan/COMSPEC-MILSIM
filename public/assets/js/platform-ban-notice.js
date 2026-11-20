(() => {
    for (const fields of document.querySelectorAll('[data-ban-notice]')) {
        const form = fields.closest('form');
        const templates = fields.querySelector('[data-ban-notice-template]');
        const rule = fields.querySelector('[name="rule_reference"]');
        const message = fields.querySelector('[name="public_reason"]');
        const duration = fields.querySelector('[data-ban-preview-duration]');
        const previewRule = fields.querySelector('[data-ban-preview-rule]');
        const previewBody = fields.querySelector('[data-ban-preview-body]');
        const status = fields.querySelector('[data-ban-copy-status]');

        function render() {
            const selection = form.querySelector('[name="duree"]:checked');
            const date = form.querySelector('[name="fin"]')?.value;
            const label = selection?.closest('label')?.textContent.trim() ?? '';
            duration.textContent = selection?.value === 'date' && date
                ? 'Fin de la restriction le ' + date.split('-').reverse().join('/') + ' (heure de Paris)'
                : label;
            previewRule.textContent = rule.value.trim();
            previewRule.hidden = previewRule.textContent === '';
            previewBody.textContent = message.value.trim() || previewBody.dataset.defaultMessage;
            status.textContent = '';
        }

        fields.querySelector('[data-ban-use-template]').addEventListener('click', () => {
            const selected = templates.selectedOptions[0];
            if (!selected?.value) {
                templates.focus();
                return;
            }
            rule.value = selected.dataset.rule;
            message.value = selected.dataset.message;
            render();
            message.focus();
        });

        fields.querySelector('[data-ban-copy]').addEventListener('click', async () => {
            const text = ['Décision de modération · Athena', duration.textContent, previewRule.textContent,
                previewBody.textContent, 'Pour demander un réexamen, utilisez le lien « Consulter ou contester un bannissement » sur la page de connexion.']
                .filter(Boolean).join('\n\n');
            try {
                await navigator.clipboard.writeText(text);
                status.textContent = 'Avis copié.';
            } catch {
                status.textContent = 'La copie automatique est indisponible. Sélectionnez le texte de l’aperçu pour le copier.';
            }
        });

        form.addEventListener('input', render);
        form.addEventListener('reset', () => requestAnimationFrame(render));
        render();
    }
})();
