<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
?>
<?= AdminUi::page(
    'Public message',
    'Sets the shared banner shown to every player on every running world. HTML is allowed.',
    '<form method="post" action="admin.php?action=sendPublicMessage">'
    . getCheckerInput()
    . AdminUi::textarea('message', 'Banner (HTML)', (string) ($params['message'] ?? ''), ['rows' => 12])
    . AdminUi::button('Save and show', 'green')
    . '</form>'
); ?>
