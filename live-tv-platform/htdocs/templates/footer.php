<?php
class FooterTemplate
{
    public static function render(): void
    {
?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-inner">
                <div class="footer-brand">
                    <div class="logo"><a href="/"><?= APP_NAME ?></a></div>
                    <p>Stream licensed live TV channels with AI-powered assistance.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Twitter">𝕏</a>
                        <a href="#" aria-label="Discord">💬</a>
                    </div>
                </div>
                <div class="footer-links">
                    <div class="footer-col">
                        <h4>Company</h4>
                        <a href="/about.php">About</a>
                        <a href="/contact.php">Contact</a>
                        <a href="/privacy.php">Privacy Policy</a>
                        <a href="/terms.php">Terms of Service</a>
                    </div>
                    <div class="footer-col">
                        <h4>Categories</h4>
                        <?php
                        $categories = getCategories();
                        foreach ($categories as $cat):
                        ?>
                        <a href="/channels.php?category=<?= $cat['slug'] ?>"><?= htmlspecialchars($cat['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="footer-col">
                        <h4>Support</h4>
                        <a href="/help.php">Help Center</a>
                        <a href="/status.php">System Status</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. | Version <?= APP_VERSION ?></p>
            </div>
        </div>
    </footer>
    <div class="ai-chat-widget" id="ai-chat-widget" style="display: none;">
    <div class="ai-chat-container">
        <div class="ai-chat-header">
            <span class="ai-bot-icon">🤖</span>
            <span class="ai-bot-name">HDHome Assistant</span>
            <button class="ai-chat-close" id="ai-chat-close">✕</button>
        </div>
        <div class="ai-chat-messages" id="ai-chat-messages"></div>
        <div class="ai-chat-input">
            <input type="text" id="ai-message-input" placeholder="Ask about channels..." autocomplete="off">
            <button class="ai-send-btn" id="ai-send-btn">▶</button>
        </div>
    </div>
</div>
<script src="/assets/js/chat.js"></script>
</body>
</html>
<?php
    }
}
FooterTemplate::render();
