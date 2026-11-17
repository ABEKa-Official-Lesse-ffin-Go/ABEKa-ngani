(() => {
    'use strict';

    const root = document.getElementById('abekaChat');
    if (!root) return;

    const launch = document.getElementById('abekaChatLaunch');
    const panel = document.getElementById('abekaChatPanel');
    const clearBtn = document.getElementById('abekaChatClear');
    const close = document.getElementById('abekaChatClose');
    const form = document.getElementById('abekaChatForm');
    const input = document.getElementById('abekaChatInput');
    const send = document.getElementById('abekaChatSend');
    const messages = document.getElementById('abekaChatMessages');
    const suggestions = document.getElementById('abekaChatSuggestions');

    const STORE_KEY = 'abeka_bida_history_v1';
    const OPEN_KEY = 'abeka_bida_open_v1';

    const loadHistory = () => {
        try {
            const raw = localStorage.getItem(STORE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    };

    const saveMessage = (text, sender) => {
        try {
            const list = loadHistory();
            list.push({ text, sender });
            localStorage.setItem(STORE_KEY, JSON.stringify(list.slice(-40)));
        } catch (e) {}
    };

    const setOpen = (open, focusInput = true) => {
        panel.hidden = !open;
        launch.setAttribute('aria-expanded', String(open));
        launch.setAttribute('aria-label', open ? 'Close Bida' : 'Open Bida');
        try { localStorage.setItem(OPEN_KEY, open ? '1' : '0'); } catch (e) {}
        if (focusInput) {
            if (open) input.focus();
            else launch.focus();
        }
    };

    const addMessage = (text, sender, extraClass = '', persist = true) => {
        const wrapper = document.createElement('div');
        wrapper.className = `abeka-chat-message ${sender}${extraClass ? ` ${extraClass}` : ''}`;
        const bubble = document.createElement('div');
        bubble.className = 'abeka-chat-bubble';

        // Safely render text and make internal .php links clickable without innerHTML.
        const linkRegex = /([a-zA-Z0-9_-]+\.php(?:\?[a-zA-Z0-9_=&%.-]+)?)/g;
        let lastIndex = 0;
        let match;
        while ((match = linkRegex.exec(text)) !== null) {
            if (match.index > lastIndex) {
                bubble.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
            }
            const a = document.createElement('a');
            a.href = match[1];
            a.textContent = match[1];
            a.style.fontWeight = '700';
            a.style.textDecoration = 'underline';
            a.style.color = 'inherit';
            bubble.appendChild(a);
            lastIndex = linkRegex.lastIndex;
        }
        if (lastIndex < text.length) {
            bubble.appendChild(document.createTextNode(text.slice(lastIndex)));
        }

        wrapper.appendChild(bubble);
        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;

        if (persist && !extraClass) {
            saveMessage(text, sender);
        }
        return wrapper;
    };

    // Restore saved chat history and open/closed state across page clicks, tabs, and refreshes.
    const saved = loadHistory();
    if (saved.length) {
        saved.forEach((item) => {
            if (item && item.text && item.sender) {
                addMessage(item.text, item.sender, '', false);
            }
        });
    }
    try {
        if (localStorage.getItem(OPEN_KEY) === '1') {
            setOpen(true, false);
        }
    } catch (e) {}

    const ask = async (message) => {
        const clean = message.trim();
        if (!clean || send.disabled) return;

        addMessage(clean, 'user');
        input.value = '';
        send.disabled = true;
        const typing = addMessage('Bida is typing…', 'bot', 'typing', false);

        try {
            const body = new URLSearchParams({ message: clean });
            const response = await fetch('chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-ABEKA-Token': root.dataset.token
                },
                body,
                credentials: 'same-origin'
            });
            const data = await response.json();
            typing.remove();
            if (!response.ok) throw new Error(data.reply || 'The assistant could not process your message.');
            addMessage(data.reply || 'I could not prepare a response. Please try again.', 'bot');
        } catch (error) {
            typing.remove();
            addMessage(error.message || 'I could not connect right now. Please try again.', 'bot');
        } finally {
            send.disabled = false;
            input.focus();
        }
    };

    launch.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            try { localStorage.removeItem(STORE_KEY); } catch (e) {}
            const firstGreeting = messages.firstElementChild ? messages.firstElementChild.cloneNode(true) : null;
            messages.innerHTML = '';
            if (firstGreeting) messages.appendChild(firstGreeting);
            input.focus();
        });
    }
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        ask(input.value);
    });
    suggestions.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-prompt]');
        if (button) ask(button.dataset.prompt);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
})();
