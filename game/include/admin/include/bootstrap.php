<?php
define("ADMIN_PANEL", true);
require __DIR__ . '/Core/Dispatcher.php';
require __DIR__ . '/Core/AdminLog.php';
$adminPage = Dispatcher::getInstance()->data;
$adminPage['content'] = AdminRequestGuard::injectTokens($adminPage['content']);
Template::getInstance()->load($adminPage, 'tpl/layout.tpl')->display();