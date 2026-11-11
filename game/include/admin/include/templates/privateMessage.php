<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
?>
<?= AdminUi::page(
    'Private message',
    'Sets the shared banner shown to every player on this world only. HTML is allowed.',
    '<form method="post" action="admin.php?action=sendPrivateMessage">'
    . getCheckerInput()
    . AdminUi::textarea('message', 'Banner (HTML)', (string) ($params['message'] ?? ''), ['rows' => 12])
    . AdminUi::button('Save and show', 'green')
    . '</form>'
); ?>
