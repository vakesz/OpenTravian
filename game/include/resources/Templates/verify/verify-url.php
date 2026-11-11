<?php
$state = 'error';
$message = '';

if (isset($vars['codeEmpty'])) {
    $state = 'warning';
    $message = T('EVerify', 'NO_CODE_ENTERED') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
} elseif (isset($vars['invalidCode'])) {
    $message = T('EVerify', 'ENTERED_CODE_INVALID') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
} elseif (isset($vars['emailExists'])) {
    $message = T('EVerify', 'EMAIL_EXISTS_CANT_VERIFY') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
} elseif (isset($vars['emailBlacklisted'])) {
    $message = T('EVerify', 'EMAIL_BLACKLISTED_CANT_VERIFY') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
} elseif (isset($vars['notFound'])) {
    $state = 'warning';
    $message = T('EVerify', 'NO_MATCHING_ROWS_FOUND') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
} elseif (isset($vars['success'])) {
    $state = 'success';
    $message = T('EVerify', 'YOUR_ACCOUNT_WAS_VERIFIED_SUCCESSFULLY') . ' ' . T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE');
}

$role = $state === 'error' ? 'alert' : 'status';
?>
<div class="verificationCard verificationCard--<?=$state;?>" role="<?=$role;?>">
    <h1 class="verificationHeading"><?= htmlspecialchars((string) T('EVerify', 'Email verification'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="verificationMessage"><?=$message;?></p>
</div>
