<div class="inlineIcon messageShortInfo" title="<?= htmlspecialchars((string) $vars['title'], ENT_QUOTES, 'UTF-8'); ?>">
    <svg viewBox="0 0 20 14.28" class="message<?= $vars['unreadCount'] ? 'New' : 'Read'; ?>">
        <path d="M.72.93A1.69 1.69 0 0 1 .33.21L.54 0h19a1.56 1.56 0 0 1 .16.2 1.73 1.73 0 0 1-.39.73l-7.2 7.78a2.79 2.79 0 0 1-4.18 0zm13.59 7.89l3.55 2.5-4.92-1.06a4.31 4.31 0 0 1-5.84 0l-4.89 1.06 3.53-2.49L0 2.62v10.06a1.54 1.54 0 0 0 1.47 1.6h17.06a1.54 1.54 0 0 0 1.47-1.6v-10z"></path>
    </svg>
    <span class="value ">‭‭<?= $vars['unreadCount'] ? $vars['unreadCount'] : $vars['total']; ?>×‬</span>
</div>
