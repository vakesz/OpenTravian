<?php

declare(strict_types=1);

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$selectedTab = (string)($vars['selectedTab'] ?? 'invite');
$inviteLink = (string)($vars['inviteLink'] ?? '');
$inviteGold = (int)($vars['inviteGold'] ?? 0);
$villageReward = (int)($vars['villageReward'] ?? $inviteGold);
$tenthVillageReward = (int)($vars['tenthVillageReward'] ?? ($inviteGold * 2));
$maxReward = (int)($vars['maxReward'] ?? ($inviteGold * 10));
$refLimit = (int)($vars['refLimit'] ?? 0);
$invitationOpen = !empty($vars['invitationOpen']);
$invitees = is_array($vars['invitees'] ?? null) ? $vars['invitees'] : [];
$checker = (string)($vars['checker'] ?? '');
$emailDefaultMessage = (string)($vars['emailDefaultMessage'] ?? ($vars['defaultMessage'] ?? ''));
$inviteFlash = (string)($vars['inviteFlash'] ?? '');

$goldCoinSvg = '<svg viewBox="0 0 50 50" class="goldCoin" aria-hidden="true">'
    . '<defs>'
    . '<linearGradient id="raf-gold-1" x1="25" x2="25" y1="2" y2="48" gradientUnits="userSpaceOnUse">'
    . '<stop offset="0" stop-color="#ffdc79"></stop>'
    . '<stop offset="0.47" stop-color="#fcbf60"></stop>'
    . '<stop offset="0.8" stop-color="#af7029"></stop>'
    . '<stop offset="1" stop-color="#c09957"></stop>'
    . '</linearGradient>'
    . '<linearGradient id="raf-gold-2" x1="25" x2="25" y1="7.9" y2="42.19" gradientUnits="userSpaceOnUse">'
    . '<stop offset="0.06" stop-color="#81481b"></stop>'
    . '<stop offset="1" stop-color="#fef6a9"></stop>'
    . '</linearGradient>'
    . '</defs>'
    . '<circle cx="25" cy="25" r="25" fill="#522d1c"></circle>'
    . '<circle cx="25" cy="25" r="23" fill="url(#raf-gold-1)"></circle>'
    . '<circle cx="25" cy="25" r="17" fill="url(#raf-gold-2)"></circle>'
    . '</svg>';

$goldIcon = static function (int $amount) use ($e, $goldCoinSvg): string {
    return '<span class="inlineIcon">' . $goldCoinSvg . '<span>' . $e((string)$amount) . '</span></span>';
};

$rewardBadge = static function (int $amount) use ($e, $goldCoinSvg): string {
    return '<div class="reward">' . $goldCoinSvg . '<div class="content">' . $e((string)$amount) . '</div></div>';
};

$copyConfirmSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 20 20" aria-hidden="true"><path d="M.3 8.5s5 4.4 6.3 8.1C8.5 7.8 14.3.3 14.3.3"></path></svg>';
$facebookSvg = '<svg viewBox="0 0 36 36" aria-hidden="true"><path d="M18 4a14 14 0 1 0 0 28 14 14 0 0 0 0-28z" fill="#fff"></path><path d="M19.5 31.8V21h3.6l.6-4.2h-4.2v-2.6c0-1.2.4-2 2.1-2h2.2V8.5c-.4 0-1.7-.2-3.2-.2-3.2 0-5.3 1.9-5.3 5.5v3H14V21h3.4v10.8z"></path></svg>';
$validSvg = '<svg viewBox="0 0 20 20" class="valid" aria-hidden="true"><path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"></path></svg>';
$invalidSvg = '<svg viewBox="0 0 22 19" class="invalid" aria-hidden="true"><path d="M11 4l7.5 13h-15L11 4m0-4L0 19h22L11 0z"></path><path d="M12 14h-2v2h2zM12 8h-2v5h2z"></path></svg>';
?>
<div id="referAFriend" class="contentV2">
<div>
    <h1 class="titleInHeader"><?= $e(T('ReferAFriend', 'title')); ?></h1>
    <div>
    <?= \resources\View\PHPBatchView::render('layout/tabStrip', [
        'tabs' => [
            ['label' => T('ReferAFriend', 'title'), 'href' => '/referAFriend', 'active' => $selectedTab === 'invite'],
            ['label' => T('ReferAFriend', 'overview'), 'href' => '/referAFriend?t=overview', 'active' => $selectedTab === 'overview'],
        ],
        'favor' => true,
        'wrapperClass' => 'tabFavorWrapper',
        'ids' => false,
    ]); ?>
    <div class="navigationSpacer"></div>
    </div>

    <?php if ($selectedTab === 'overview'): ?>
        <div class="referralOverview">
            <div class="referralSummary">
                <div>
                    <div class="caption"><?= $e(T('ReferAFriend', 'playersInvited')); ?></div>
                    <div class="amount"><span class="content"><?= count($invitees); ?></span></div>
                </div>
                <div>
                    <div class="caption"><?= $e(T('ReferAFriend', 'rewardPerFriend')); ?></div>
                    <div class="amount"><span class="content"><?= $maxReward; ?></span></div>
                </div>
            </div>
            <?php if ($invitees === []): ?>
                <p class="empty"><?= $e(T('ReferAFriend', 'noInvitees')); ?></p>
            <?php else: ?>
                <div class="invitees">
                    <?php foreach ($invitees as $invitee): ?>
                        <div class="invitee">
                            <div class="playerFrame">
                                <a href="/profile?uid=<?= (int)$invitee['uid']; ?>">
                                    <?= $e((string)$invitee['name']); ?>
                                </a>
                                <div class="villages">
                                    <?= $e(sprintf(T('ReferAFriend', 'villageCount'), (int)$invitee['villages'])); ?>
                                </div>
                                <div class="collectible">
                                    <?php if ((int)$invitee['rewardGiven'] === 1): ?>
                                        <div class="reward"><div class="content"><?= $villageReward; ?></div></div>
                                    <?php else: ?>
                                        <span><?= $e(T('ReferAFriend', 'pendingReward')); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="referAFriendInfo">
            <div class="header">
                <div class="badgeBigDecoration">
                    <div class="maxReward"><?= $goldCoinSvg; ?><?= $maxReward; ?></div>
                    <div class="caption"><?= $e(T('ReferAFriend', 'perFriend')); ?></div>
                </div>
            </div>
            <div class="descriptionHeader"><?= $e(T('ReferAFriend', 'descriptionHeader')); ?></div>
            <div class="description">
                <?= sprintf(
                    T('ReferAFriend', 'description'),
                    $goldIcon($villageReward),
                    $goldIcon($tenthVillageReward),
                    $e((string)$refLimit)
                ); ?>
            </div>
            <div class="infoGraphic" aria-hidden="true">
                <div class="badge badgeBig friendJoins">
                    <div class="title"><?= $e(T('ReferAFriend', 'friendJoins')); ?></div>
                </div>
                <div class="badge badgeSmall secondVillage">
                    <div class="title"><strong><?= $e(T('ReferAFriend', 'ordinal2nd')); ?></strong> <?= $e(T('ReferAFriend', 'village')); ?></div>
                    <?= $rewardBadge($villageReward); ?>
                </div>
                <div class="badge badgeSmall thirdVillage">
                    <div class="title"><strong><?= $e(T('ReferAFriend', 'ordinal3rd')); ?></strong> <?= $e(T('ReferAFriend', 'village')); ?></div>
                    <?= $rewardBadge($villageReward); ?>
                </div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="badge badgeSmall ninthVillage">
                    <div class="title"><strong><?= $e(T('ReferAFriend', 'ordinal9th')); ?></strong> <?= $e(T('ReferAFriend', 'village')); ?></div>
                    <?= $rewardBadge($villageReward); ?>
                </div>
                <div class="badge badgeSmallDecoration tenthVillage">
                    <div class="title"><strong><?= $e(T('ReferAFriend', 'ordinal10th')); ?></strong> <?= $e(T('ReferAFriend', 'village')); ?></div>
                    <?= $rewardBadge($tenthVillageReward); ?>
                </div>
            </div>
            <?php if ($invitationOpen): ?>
                <div class="registrationOpen">
                    <div><?= $e(T('ReferAFriend', 'yourInviteLink')); ?></div>
                    <div class="copyLinkWrapper">
                        <input id="referralLinkInput" class="referralLink" type="text" readonly value="<?= $e($inviteLink); ?>">
                        <button type="button" class="textButtonV2 buttonFramed copyLink rectangle withText green">
                            <div>
                                <div class="action"><?= $e(T('ReferAFriend', 'copyLink')); ?></div>
                                <div class="confirm"><?= $copyConfirmSvg; ?> <?= $e(T('ReferAFriend', 'copied')); ?></div>
                            </div>
                        </button>
                    </div>
                    <div class="shareButtonsWrapper">
                        <button type="button" class="textButtonV2 buttonFramed openEmailForm rectangle withText green">
                            <div><?= $e(T('ReferAFriend', 'inviteViaEmail')); ?></div>
                        </button>
                        <div class="fb-share-button" role="button" tabindex="0"><?= $facebookSvg; ?><?= $e(T('ReferAFriend', 'shareOnFacebook', 'Share on Facebook')); ?></div>
                    </div>
                    <div class="infoBox">
                        <div class="title"><?= $e(T('ReferAFriend', 'tipsTitle')); ?></div>
                        <ul>
                            <li class="tip1"><?= $e(T('ReferAFriend', 'tip1')); ?></li>
                            <li class="tip1"><?= sprintf(T('ReferAFriend', 'tip2'), $goldIcon($maxReward)); ?></li>
                        </ul>
                    </div>
                </div>
                <div id="inviteViaEmailDialogTemplate" hidden>
                    <div id="inviteViaEmailDialog">
                        <h1><?= $e(T('ReferAFriend', 'inviteDialogTitle')); ?></h1>
                        <?php if ($inviteFlash !== ''): ?>
                            <div class="successMessage"><?= $e($inviteFlash); ?></div>
                        <?php endif; ?>
                        <form class="formV2" method="post" action="/referAFriend" id="inviteViaEmailForm">
                            <input type="hidden" name="c" value="<?= $e($checker); ?>">
                            <input type="hidden" name="inviteEmail" value="1">
                            <label class="input" id="name">
                                <input type="text" name="friendName" value="" autocomplete="name" required>
                                <div class="label"><?= $e(T('ReferAFriend', 'friendName')); ?></div>
                                <?= $validSvg; ?>
                                <?= $invalidSvg; ?>
                                <div class="validation" data-rule="notEmpty"><?= $e(T('ReferAFriend', 'friendNameRequired')); ?></div>
                            </label>
                            <label class="input" id="email">
                                <input type="email" name="friendEmail" value="" autocomplete="email" required>
                                <div class="label"><?= $e(T('ReferAFriend', 'friendEmail')); ?></div>
                                <?= $validSvg; ?>
                                <?= $invalidSvg; ?>
                                <div class="validation" data-rule="notEmpty"><?= $e(T('ReferAFriend', 'friendEmailRequired')); ?></div>
                                <div class="validation" data-rule="isEmail"><?= $e(T('ReferAFriend', 'friendEmailInvalid')); ?></div>
                            </label>
                            <label class="textarea fixed" id="message">
                                <textarea name="message" maxlength="2000" required><?= $e($emailDefaultMessage); ?></textarea>
                                <div class="label pinned"><?= $e(T('ReferAFriend', 'personalizeMessage')); ?></div>
                                <?= $validSvg; ?>
                                <?= $invalidSvg; ?>
                                <div class="validation" data-rule="notEmpty"><?= $e(T('ReferAFriend', 'messageRequired')); ?></div>
                            </label>
                            <label class="checkbox" id="copyCheckbox">
                                <input type="checkbox" name="sendCopy" value="1">
                                <svg viewBox="0 0 18 18" class="checkbox" aria-hidden="true">
                                    <path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path>
                                    <rect class="indeterminate" width="10" height="2" x="4" y="8"></rect>
                                    <path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path>
                                </svg>
                                <div class="label"><?= $e(T('ReferAFriend', 'sendCopy')); ?></div>
                            </label>
                            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" id="inviteButton">
                                <div><?= $e(T('ReferAFriend', 'inviteFriend')); ?></div>
                            </button>
                        </form>
                    </div>
                </div>
                <script type="text/javascript">
                    jQuery(function () {
                        var inviteLink = <?= json_encode($inviteLink, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                        var shareTitle = <?= json_encode(T('ReferAFriend', 'title'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

                        var copyInviteLink = function () {
                            var input = document.getElementById('referralLinkInput');
                            var $copyBtn = jQuery('.copyLink');
                            if (!input) { return; }
                            input.select();
                            input.setSelectionRange(0, input.value.length);
                            var done = function () {
                                $copyBtn.addClass('success');
                                window.setTimeout(function () { $copyBtn.removeClass('success'); }, 1600);
                            };
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(input.value).then(done).catch(function () { document.execCommand('copy'); done(); });
                            } else {
                                document.execCommand('copy');
                                done();
                            }
                        };

                        jQuery('.copyLink').on('click', copyInviteLink);
                        jQuery('.fb-share-button').on('click keydown', function (event) {
                            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') { return; }
                            event.preventDefault();
                            var payload = { title: shareTitle, url: inviteLink, text: inviteLink };
                            var openFacebookShare = function () {
                                window.open(
                                    'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(inviteLink),
                                    'facebook-share-dialog',
                                    'width=526,height=436'
                                );
                            };
                            if (!navigator.share) {
                                openFacebookShare();
                                return;
                            }
                            // Match live RAF share: attach refer image when Web Share Level 2 allows files.
                            var imagePath = (Travian.Game && Travian.Game.getImageDirectoryPath)
                                ? Travian.Game.getImageDirectoryPath() + 'views/refer-a-friend/refer_a_friend.jpg'
                                : null;
                            var share = function (data) {
                                navigator.share(data).catch(function () { /* user cancel / unsupported */ });
                            };
                            if (!imagePath || !navigator.canShare) {
                                share(payload);
                                return;
                            }
                            fetch(imagePath).then(function (response) {
                                return response.ok ? response.blob() : Promise.reject();
                            }).then(function (blob) {
                                var file = new File([blob], 'raf.jpg', { type: 'image/jpeg' });
                                var withFile = { title: payload.title, url: payload.url, text: payload.text, files: [file] };
                                if (navigator.canShare(withFile)) {
                                    share(withFile);
                                } else {
                                    share(payload);
                                }
                            }).catch(function () {
                                share(payload);
                            });
                        });

                        var openInviteDialog = function () {
                            var template = document.getElementById('inviteViaEmailDialogTemplate');
                            if (!template) { return; }
                            var dialog = new Travian.Dialog.Dialog({
                                preventFormSubmit: false,
                                buttonOk: false,
                                cssClass: 'basic referAFriendInvite',
                                context: 'referAFriend'
                            });
                            dialog.setContent(template.innerHTML);
                            dialog.show();
                            jQuery('.dialog.referAFriendInvite input[name="friendName"]').trigger('focus');
                        };

                        jQuery('.openEmailForm').on('click', function (event) {
                            event.preventDefault();
                            openInviteDialog();
                        });

                        <?php if ($inviteFlash !== ''): ?>
                        openInviteDialog();
                        <?php endif; ?>
                    });
                </script>
            <?php else: ?>
                <p class="error"><?= $e(T('ReferAFriend', 'invitationsClosed')); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>
<script type="text/javascript">
    jQuery(function () {
        Travian.Game.TabScrollNavigation();
    });
</script>
