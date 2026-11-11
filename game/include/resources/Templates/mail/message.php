<?php

declare(strict_types=1);

use Core\Config;

/** @var array{name: string, body: string, unsubscribeUrl: string} $vars */
$worldUrl = (string) Config::getProperty('settings', 'gameWorldUrl');
?>
<div style="font-size:16px;line-height:25px;font-family:Helvetica, sans-serif;color:#666666;direction:<?= getDirection(); ?>;text-align:<?= getDirection() == 'LTR' ? 'left' : 'right'; ?>">
    <?= T('Mail', 'Hello'); ?> <b><?= $vars['name']; ?></b>,
    <br/><br/>
    <?= $vars['body']; ?>
    <br/><br/>
    <?= T('Mail', 'Game world Url'); ?>: <a href="<?= htmlspecialchars($worldUrl, ENT_QUOTES, 'UTF-8'); ?>" style="color:#f88c1f;" target="_blank"><?= htmlspecialchars($worldUrl, ENT_QUOTES, 'UTF-8'); ?></a>
    <hr style="border:0;border-top:1px solid #cccccc;margin:20px 0"/>
    <div style="font-size:13px;line-height:19px;color:#888888">
        <?= T('Mail', 'You are receiving this email because you opted in on this game world.'); ?>
        <a href="<?= htmlspecialchars($vars['unsubscribeUrl'], ENT_QUOTES, 'UTF-8'); ?>" style="color:#888888;"><?= T('Mail', 'Unsubscribe'); ?></a>
    </div>
</div>
