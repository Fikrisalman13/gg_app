(() => {
    const messagesEl = document.getElementById('messages');
    const questionEl = document.getElementById('question');
    const sendBtn = document.getElementById('send');
    const statusEl = document.getElementById('status');
    const modelEl = document.getElementById('model-select');
    const docEl = document.getElementById('doc-select');

    if (!messagesEl) return;

    const setStatus = (text) => {
        statusEl.textContent = text || '';
    };

    const scrollToBottom = () => {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const normalizeRole = (role) => {
        const r = String(role || '').toLowerCase();
        if (['assistant', 'ai', 'bot', 'system', 'answer', 'response'].includes(r)) return 'assistant';
        if (['user', 'u', 'human', 'request', 'question'].includes(r)) return 'user';
        return 'assistant';
    };

    const extractImages = (text) => {
        const images = [];
        const cleaned = text.replace(/\[\[image:([^\]|]+)\|([^\]]*)\]\]/g, (_, url, caption) => {
            images.push({ url: url.trim(), caption: (caption || '').trim() });
            return '';
        }).replace(/\[LAMPIRAN_GAMBAR\]/g, '');
        return { text: cleaned.trim(), images };
    };

    const renderImages = (wrap, images) => {
        if (!images || images.length === 0) return;
        const existing = wrap.querySelector('.ai-images');
        if (existing) existing.remove();
        const gallery = document.createElement('div');
        gallery.className = 'ai-images';
        images.forEach(img => {
            if (!img.url) return;
            const figure = document.createElement('figure');
            const imageEl = document.createElement('img');
            imageEl.src = img.url;
            imageEl.alt = img.caption || 'Lampiran gambar';
            imageEl.loading = 'lazy';
            figure.appendChild(imageEl);
            if (img.caption) {
                const cap = document.createElement('figcaption');
                cap.textContent = img.caption;
                figure.appendChild(cap);
            }
            gallery.appendChild(figure);
        });
        wrap.appendChild(gallery);
    };

    const addMessage = (role, text) => {
        const wrap = document.createElement('div');
        wrap.className = `msg ${role}`;
        const pre = document.createElement('pre');
        const parsed = extractImages(text || '');
        pre.textContent = parsed.text;
        wrap.appendChild(pre);
        renderImages(wrap, parsed.images);
        messagesEl.appendChild(wrap);
        scrollToBottom();
        return { wrap, pre };
    };

    const loadHistory = async () => {
        try {
            const res = await fetch('history.php?limit=50', { cache: 'no-store' });
            const json = await res.json();
            if (json.ok && Array.isArray(json.data)) {
                messagesEl.innerHTML = '';
                json.data.forEach(row => {
                    addMessage(normalizeRole(row.role), row.message || '');
                });
            }
        } catch (e) {
            // ignore
        }
    };

    const loadDocuments = async () => {
        if (!docEl) return;
        try {
            const res = await fetch('documents.php', { cache: 'no-store' });
            const json = await res.json();
            if (json.ok && Array.isArray(json.data)) {
                const current = docEl.value;
                docEl.innerHTML = '<option value="">Semua Dokumen</option>';
                json.data.forEach(row => {
                    if (!row || !row.document_id) return;
                    const opt = document.createElement('option');
                    opt.value = row.document_id;
                    opt.textContent = row.title ? `${row.title}` : `Dokumen ${row.document_id}`;
                    docEl.appendChild(opt);
                });
                if (current) docEl.value = current;
            }
        } catch (e) {
            // ignore
        }
    };

    const streamAsk = async (question) => {
        setStatus('Menjawab...');
        sendBtn.disabled = true;
        questionEl.disabled = true;

        addMessage('user', question);
        const assistantMsg = addMessage('assistant', 'Menjawab...');
        const assistantPre = assistantMsg.pre;
        const assistantWrap = assistantMsg.wrap;
        assistantWrap.classList.add('pending');
        let fullText = '';

        const payload = {
            question,
            model: modelEl.value
        };
        if (docEl && docEl.value) {
            payload.document_id = docEl.value;
        }

        try {
            const res = await fetch('ask.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!res.ok || !res.body) {
                assistantPre.textContent = 'Terjadi kesalahan saat menghubungi AI.';
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let done = false;
            while (!done) {
                const result = await reader.read();
                done = result.done;
                if (result.value) {
                    if (assistantWrap.classList.contains('pending')) {
                        assistantPre.textContent = '';
                        assistantWrap.classList.remove('pending');
                    }
                    const chunk = decoder.decode(result.value, { stream: true });
                    fullText += chunk;
                    assistantPre.textContent = fullText;
                    scrollToBottom();
                }
            }
            const parsed = extractImages(fullText);
            assistantPre.textContent = parsed.text;
            renderImages(assistantWrap, parsed.images);
        } catch (e) {
            assistantPre.textContent = 'Gagal memproses permintaan.';
        } finally {
            setStatus('');
            sendBtn.disabled = false;
            questionEl.disabled = false;
            questionEl.value = '';
            questionEl.focus();
        }
    };

    sendBtn.addEventListener('click', () => {
        const q = (questionEl.value || '').trim();
        if (!q) return;
        streamAsk(q);
    });

    questionEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendBtn.click();
        }
    });

    loadHistory();
    loadDocuments();
})();
