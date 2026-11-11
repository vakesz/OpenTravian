<div class="noAllianceBackground">
    <div>
        <p><?= htmlspecialchars(
            (string) T('Alliance', 'You are currently not in an alliance.'),
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
        <p><?= sprintf(
            (string) T(
                'Alliance',
                'To join an alliance, you need an %s to accept invitations or you can found your own once your Embassy reaches level 3'
            ),
            '<strong>' .
                htmlspecialchars((string) T('Buildings', '18.title'), ENT_QUOTES, 'UTF-8') .
                '</strong>'
        ) ?></p>
    </div>
</div>
