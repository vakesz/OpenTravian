<script>
    jQuery(function ($) {
        const captchaUrl = <?=json_encode((string)$vars['captchaUrl'], JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);?>;

        $('#recaptchaReload').on('click', function () {
            $('#recaptchaImage').attr('src', captchaUrl + Date.now());
        });
    });
</script>

<div class="recaptchaCard">
    <h1 class="recaptchaHeading"><?=T('reCaptcha', 'title');?></h1>
    <form id="verifyForm" class="recaptchaForm" action="<?=htmlspecialchars((string)$vars['formAction'], ENT_QUOTES, 'UTF-8');?>" method="post">
        <?=$vars['checker'];?>
        <img id="recaptchaImage" class="recaptchaImage" src="<?=htmlspecialchars((string)$vars['captchaUrl'], ENT_QUOTES, 'UTF-8');?><?=bin2hex(random_bytes(8));?>" alt="CAPTCHA">
        <button id="recaptchaReload" class="recaptchaReload" type="button"><?=T('farmListLockHandle', 'newCode');?></button>

        <label for="captchaCode"><?=T('farmListLockHandle', 'captcha');?></label>
        <input id="captchaCode" class="text" type="text" name="captcha_code" maxlength="16" autocomplete="off" required>
        <?php if ($vars['error'] !== ''): ?>
            <p class="recaptchaError" role="alert"><?=htmlspecialchars((string)$vars['error'], ENT_QUOTES, 'UTF-8');?></p>
        <?php endif; ?>

        <div class="recaptchaSubmit">
            <button type="submit" class="textButtonV1 green " version="textButtonV1"><?=T('Login', 'continueButton');?></button>
        </div>
    </form>
</div>
