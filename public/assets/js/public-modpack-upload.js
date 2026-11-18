(() => {
    const form = document.querySelector('[data-modpack-upload]');
    if (!form) return;

    const messages = JSON.parse(form.dataset.uploadMessages);
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
                if (attempt === 2) throw new Error(messages.network);
                await wait(500 * (attempt + 1));
                continue;
            }
            if (response.status === 413 && blockSize > 32 * 1024) {
                return {offset, blockSize: Math.floor(blockSize / 2)};
            }
            if (response.type === 'opaqueredirect' || response.status === 403) {
                throw new Error(messages.session);
            }
            if (response.status >= 500 && attempt < 2) {
                await wait(500 * (attempt + 1));
                continue;
            }
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.offset !== end) {
                const detail = response.status === 413 ? messages.server_limit : messages.chunk_failed;
                throw new Error(result.message || detail);
            }
            return {offset: end, blockSize};
        }
    }

    button.disabled = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;

        const version = form.elements.version;
        const notes = form.elements.notes;
        version.value = version.value.trim();
        if (!form.reportValidity()) return;

        const file = archive.files[0];
        if (!file || !/\.rar$/i.test(file.name) || file.size < 7 || file.size > Number(form.dataset.maxSize)) {
            status.textContent = messages.invalid_file;
            return;
        }

        button.disabled = true;
        archive.disabled = true;
        version.readOnly = true;
        notes.readOnly = true;
        progress.hidden = false;
        progress.value = 0;
        status.textContent = messages.sending;

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
            status.textContent = messages.finalizing;
            form.submit();
        } catch (error) {
            status.textContent = error.message || messages.failed;
            archive.disabled = false;
            version.readOnly = false;
            notes.readOnly = false;
            button.disabled = false;
        }
    });
})();
