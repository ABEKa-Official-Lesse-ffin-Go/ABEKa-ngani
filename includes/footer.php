</main>

<!-- Footer section -->
<footer>
    <span>© 2026 ABEKa For Kapampangan, Inc. All Rights Reserved.</span>

    <span>
        <a href="privacy.php">Privacy</a> |
        <a href="terms.php">Terms of Service</a> |
        <a href="disclosure.php">Disclosure Policy</a>
    </span>

    <?php
    // Public social media links (Instagram, Threads, and Facebook only).
    $social = [
        'Instagram' => [
            'https://www.instagram.com/abeka.official?rpxt=emJ2bjdmZXNobnVk&utm_source=qr',
            '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>'
        ],
        'Threads' => [
            'https://www.threads.net/@abeka.official',
            '<path d="M19.2 10.7C18.6 6.8 15.8 4.5 12.1 4.5 8.1 4.5 5 7.7 5 12s3.1 7.5 7.2 7.5c2.7 0 4.8-1 6-2.8M15.4 12.3c0 2.1-1.3 3.4-3.3 3.4-1.8 0-3-1.2-3-2.9 0-1.8 1.3-3 3.2-3 1.4 0 2.5.6 3.1 1.6v.9z"/>'
        ],
        'Facebook' => [
            'https://www.facebook.com/',
            '<path fill="currentColor" stroke="none" d="M13.5 22v-8.2h2.8l.5-3.3h-3.3V8.4c0-.9.4-1.7 1.8-1.7h1.6V3.8s-1.4-.3-2.7-.3c-2.6 0-4.3 1.6-4.3 4.4v2.6H6.8v3.3h2.8V22h3.9z"/>'
        ]
    ];
    ?>

    <!-- Social media and contact links -->
    <span class="social">
        <?php foreach ($social as $n => $s): ?>
            <a
                href="<?=e($s[0])?>"
                target="_blank"
                rel="noopener"
                title="<?=e($n)?>"
                aria-label="<?=e($n)?>"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <?=$s[1]?>
                </svg>
            </a>
        <?php endforeach; ?>
    </span>
</footer>

<?php
// Generate a chatbot token for the current session if one does not exist
if (empty($_SESSION['chatbot_token'])) {
    $_SESSION['chatbot_token'] = bin2hex(random_bytes(32));
}
?>

<!-- ABEKa Assistant: shared floating support widget -->
<div
    class="abeka-chat"
    id="abekaChat"
    data-token="<?=e($_SESSION['chatbot_token'])?>"
>

    <!-- Chat launcher button -->
    <button
        class="abeka-chat-launch"
        id="abekaChatLaunch"
        type="button"
        aria-label="Open Bida"
        aria-expanded="false"
        aria-controls="abekaChatPanel"
    >
        <span class="abeka-chat-launch-icon" aria-hidden="true">✦</span>
        <span class="abeka-chat-launch-label">Ask Bida</span>
    </button>

    <!-- Chat window -->
    <section
        class="abeka-chat-panel glass"
        id="abekaChatPanel"
        aria-label="Bida"
        hidden
    >

        <!-- Chat header -->
        <header class="abeka-chat-head">
            <div class="abeka-chat-avatar" aria-hidden="true">✦</div>

            <div class="abeka-chat-heading">
                <strong>Bida</strong>
                <small>
                    <span class="abeka-chat-status"></span>
                    Here to help you explore ABEKa
                </small>
            </div>

            <div class="abeka-chat-head-actions">
                <button
                    class="abeka-chat-clear"
                    id="abekaChatClear"
                    type="button"
                    title="Clear chat"
                    aria-label="Clear chat"
                >
                    Clear
                </button>
                <button
                    class="abeka-chat-close"
                    id="abekaChatClose"
                    type="button"
                    aria-label="Close chat"
                >
                    ×
                </button>
            </div>
        </header>

        <!-- Chat messages (loads saved progression for users & guests) -->
        <?php
        $chatHistory = [];
        try {
            $cu = user();
            if ($cu) {
                $chatHistory = q(
                    'SELECT user_message, bot_reply FROM chatbot_logs WHERE (user_id = ? OR session_id = ?) AND is_cleared = 0 ORDER BY id ASC LIMIT 50',
                    [$cu['id'], session_id()]
                )->fetchAll();
            } else {
                $chatHistory = q(
                    'SELECT user_message, bot_reply FROM chatbot_logs WHERE session_id = ? AND is_cleared = 0 ORDER BY id ASC LIMIT 50',
                    [session_id()]
                )->fetchAll();
            }
        } catch (Throwable $e) {
            $chatHistory = [];
        }
        ?>
        <div
            class="abeka-chat-messages"
            id="abekaChatMessages"
            aria-live="polite"
            aria-relevant="additions"
        >
            <div class="abeka-chat-message bot">
                <div class="abeka-chat-bubble">
                    Hi! I'm Bida, your ABEKa hero here to help. Ask me about Emergency Hotlines, E-Learnings &amp; CSE Reviewer, document uploads, job hiring, Pampanga LGUs, news, fuel/forex, or site policies!
                </div>
            </div>
            <?php foreach ($chatHistory as $ch): ?>
                <div class="abeka-chat-message user">
                    <div class="abeka-chat-bubble"><?=e($ch['user_message'])?></div>
                </div>
                <div class="abeka-chat-message bot">
                    <div class="abeka-chat-bubble"><?=nl2br(e($ch['bot_reply']))?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Suggested questions -->
        <div class="abeka-chat-suggestions" id="abekaChatSuggestions">
            <button type="button" data-prompt="Emergency hotlines">Emergency hotlines</button>
            <button type="button" data-prompt="Nearest government offices">Gov Offices</button>
            <button type="button" data-prompt="E-Learning and Civil Service Reviewer">E-Learning &amp; CSE</button>
            <button type="button" data-prompt="What documents can I upload?">Required documents</button>
            <button
                type="button"
                data-prompt="Show me the latest news"
            >
                Latest news
            </button>

            <button
                type="button"
                data-prompt="List all LGUs in Pampanga"
            >
                LGU directory
            </button>

            <button
                type="button"
                data-prompt="How do I apply for a job?"
            >
                How to apply
            </button>
        </div>

        <!-- Chat input form -->
        <form class="abeka-chat-form" id="abekaChatForm">
            <label class="sr" for="abekaChatInput">
                Your message
            </label>

            <input
                id="abekaChatInput"
                name="message"
                type="text"
                maxlength="500"
                placeholder="Ask me anything about ABEKa…"
                autocomplete="off"
                required
            >

            <button
                type="submit"
                id="abekaChatSend"
                aria-label="Send message"
            >
                ➤
            </button>
        </form>

        <!-- Chat disclaimer -->
        <p class="abeka-chat-note">
            Bida uses public website information. Please don't share passwords or private details.
        </p>

    </section>
</div>

<!-- Load JavaScript files -->
<script src="assets/js/app.js?v=<?=filemtime(__DIR__."/../assets/js/app.js")?>"></script>
<script src="assets/js/chatbot.js?v=<?=filemtime(__DIR__."/../assets/js/chatbot.js")?>" defer></script>

<!-- Sync Bida Clear button with saved database progression -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clearBtn = document.getElementById('abekaChatClear');
    const chatBox = document.getElementById('abekaChat');
    if (clearBtn && chatBox) {
        clearBtn.addEventListener('click', function () {
            const fd = new FormData();
            fd.append('action', 'clear');
            fetch('chatbot.php', {
                method: 'POST',
                headers: { 'X-ABEKA-TOKEN': chatBox.dataset.token || '' },
                body: fd
            }).catch(function () {});
        });
    }
});
</script>
</body>
</html>
