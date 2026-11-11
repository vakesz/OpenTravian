<center>
    <span style="color: red;font-weight: bold;">
        <?=T("EVerify", "NEED_VERIFICATION_FOR_FEATURES");?>
    </span>
</center>
<br/>
<script type="text/javascript">
    var errors = <?=json_encode(T("EVerify", "errors"));?>;
</script>
<?php $emailDelivery = \Game\SmtpConfig::isConfigured(); ?>
<?php if (!$emailDelivery): ?>
    <p role="alert"><?= T("EVerify", "errors.deliveryNotConfigured"); ?></p>
<?php endif; ?>
<?php if (!$vars['activationInProgress']): ?>
    <?php if ($emailDelivery): ?>
        <?php require("newProgress.php");?>
    <?php endif; ?>
<?php else: ?>
    <?php require("inProgress.php");?>
<?php endif; ?>
<?php require("limitations.php");?>