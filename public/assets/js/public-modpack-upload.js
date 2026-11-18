(() => {
    const form = document.querySelector('[data-modpack-upload]');
    if (!form) return;

    const archive = form.querySelector('[data-archive]');
    const button = form.querySelector('button[type="submit"]');
    const progress = form.querySelector('progress');
    const status = form.querySelector('[data-upload-status]');
    const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    async function sendBlock(file, token, offset, blockSize) {
        const end = Math.min(file.size, offset + blockSize);
        const data = new FormData();
        data.append('_csrf_token', form.elements._csrf_token.value);
        data.append('upload_token', token);
        data.append('offset', String(offset));
        data.append('block', file.slice(offset, end), 'block');

        for (let attempt = 0; attempt < 3; attempt++) {
            let response;
            try {
                response = await fetch(form.dataset.blockUrl, {
                    method: 'POST',
                    body: data,
                    redirect: 'manual',
                    signal: AbortSignal.timeout(60_000),
                });
            } catch {
                if (attempt === 2) throw new Error('Connexion interrompue. Réessayez l’envoi.');
                await wait(500 * (attempt + 1));
                continue;
            }
            if (response.status === 413 && blockSize > 32 * 1024) {
                return {offset, blockSize: Math.floor(blockSize / 2)};
            }
            if (response.type === 'opaqueredirect' || response.status === 403) {
                throw new Error('Session expirée ou accès refusé. Rechargez la page.');
            }
            if (response.status >= 500 && attempt < 2) {
                await wait(500 * (attempt + 1));
                continue;
            }
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.offset !== end) {
                const detail = response.status === 413 ? 'La limite d’envoi du serveur est trop basse.' : 'Le bloc n’a pas été reçu.';
                throw new Error(result.message || detail);
            }
            return {offset: end, blockSize};
        }
    }

    button.disabled = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;

        const file = archive.files[0];
        if (!file || !/\.rar$/i.test(file.name) || file.size < 7 || file.size > Number(form.dataset.maxSize)) {
            status.textContent = 'Sélectionnez une archive .RAR de 20 Gio maximum.';
            return;
        }

        button.disabled = true;
        archive.disabled = true;
        progress.hidden = false;
        progress.value = 0;
        status.textContent = 'Envoi de l’archive…';

        try {
            const token = Array.from(crypto.getRandomValues(new Uint8Array(16)),
                value => value.toString(16).padStart(2, '0')).join('');
            let offset = 0;
            let blockSize = Number(form.dataset.blockSize);
            while (offset < file.size) {
                ({offset, blockSize} = await sendBlock(file, token, offset, blockSize));
                progress.value = Math.floor(offset / file.size * 100);
            }

            form.elements.upload_token.value = token;
            form.elements.archive_size.value = String(file.size);
            status.textContent = 'Archive reçue. Vérification et publication en cours…';
            form.submit();
        } catch (error) {
            status.textContent = error.message || 'L’envoi a échoué.';
            archive.disabled = false;
            button.disabled = false;
        }
    });
})();
