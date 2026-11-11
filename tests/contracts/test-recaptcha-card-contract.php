<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/RecaptchaCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/system/recaptcha.php');

if ($controller === false || $template === false
    || !str_contains($controller, "new Securimage(['namespace' => self::CAPTCHA_NAMESPACE])")
    || !str_contains($controller, "Session::validateChecker()")
    || !str_contains($controller, "throttle_public_form('clickunblock', 5, 3600)")
    || !str_contains($template, 'id="recaptchaReload"')
    || !str_contains($template, 'id="captchaCode"')
    || !str_contains($template, 'Session::getCheckerInput()') && !str_contains($template, "<?=\$vars['checker'];?>")) {
    fwrite(STDERR, "The CAPTCHA gate must retain its validation contract and native controls.\n");
    exit(1);
}

echo "CAPTCHA card contract passed.\n";
