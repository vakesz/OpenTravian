<?php
$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$gender = (int) $vars["gender"];
$genderLabel = match ($gender) {
    1 => T("Profile", "Male"),
    2 => T("Profile", "Female"),
    3 => T("Profile", "Other"),
    default => T("Profile", "Not specified"),
};
$horse = null;
foreach ($vars["heroEquipment"] as $slot) {
    if ($slot["slot"] === "horse" && $slot["type"] > 0) {
        $horse = $slot["type"];
    }
}
$arrowsSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 62" class="arrows" aria-hidden="true"><path class="top" d="M31 4l2-4h-4l2 4z"></path><path class="bottom" d="M29 62h4l-2-4-2 4z"></path><path class="from" d="M0 33l4-2-4-2v4z"></path><path class="to" d="M58 31l4 2v-4l-4 2z"></path></svg>';
$rankRows = [
    [T("Profile", "Population"), $vars["rank_pop"], $vars["total_pop"], T("Profile", "Population")],
    [T("Reports", "Attacker"), $vars["rank_att"], $vars["total_att"], T("Profile", "Points")],
    [T("Reports", "Defender"), $vars["rank_def"], $vars["total_def"], T("Profile", "Points")],
    [T("HeroInventory", "heroLevel"), $vars["hero_lvl"], $vars["hero_exp"], T("Profile", "Experience")],
];
?>
<div class="heroV2 playerDetails">
    <table>
        <tbody>
        <tr><th class="header" colspan="3"><?= $e(T("Profile", "Details")) ?></th></tr>
        <tr>
            <th><?= $e(T("Profile", "Tribe")) ?></th>
            <td colspan="2"><?= $e(T("Global", "races." . $vars["race"])) ?></td>
        </tr>
        <tr>
            <th><?= $e(T("Profile", "Alliance")) ?></th>
            <td colspan="2"><?= $vars["alliance"] ?></td>
        </tr>
        <tr>
            <th><?= $e(T("Profile", "Villages")) ?></th>
            <td colspan="2"><?= $e($vars["total_villages"]) ?></td>
        </tr>
        <tr>
            <th><?= $e(T("Profile", "Gender")) ?></th>
            <td colspan="2"><?= $e($genderLabel) ?></td>
        </tr>
        <?php if (isset($vars["age"])): ?>
            <tr>
                <th><?= $e(T("Profile", "Age")) ?></th>
                <td colspan="2"><?= $e($vars["age"]) ?></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($vars["location"])): ?>
            <tr>
                <th><?= $e(T("Profile", "Location")) ?></th>
                <td colspan="2"><?= $e($vars["location"]) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th><?= $e(T("Profile", "Days playing")) ?></th>
            <td colspan="2"><?= (int) $vars["daysPlaying"] ?></td>
        </tr>
        <?php if (!empty($vars["language"]) && $vars["uid"] > 2): ?>
            <tr class="multiLanguage">
                <td colspan="3"><i class="flag <?= $e(strtolower(preg_replace("/[^a-z0-9_-]/i", "", (string) $vars["language"]))) ?>"></i></td>
            </tr>
        <?php endif; ?>
        <tr><th class="header" colspan="3"><?= $e(T("Profile", "Ranks and level")) ?></th></tr>
        <?php foreach ($rankRows as [$label, $rank, $value, $unit]): ?>
            <tr>
                <th><?= $e($label) ?></th>
                <td class="rank"><?= $e($rank) ?></td>
                <td class="value"><?= $e($value) ?> <?= $e($unit) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="personalNote">
        <?php if ($vars["showNote"] && (string) $vars["note"] !== ""): ?>
            <div class="text"><a href="#" id="playerNoteEditLink" title="<?= $e(T("Profile", "Note")) ?>"><?= $e($vars["note"]) ?></a></div>
        <?php endif; ?>
    </div>
    <?php if ($vars["isMe"]): ?>
        <button type="button" class="textButtonV2 buttonFramed editProfile rectangle withText green"
                onclick="window.location.href='/profile/edit'">
            <div><?= $e(T("Profile", "Edit Profile")) ?></div>
        </button>
    <?php else: ?>
        <div class="actionButtons" id="player_message-ignore-buttons-block"></div>
        <div class="notification" id="player_profile_notification"></div>
    <?php endif; ?>
    <div class="hero<?= $horse !== null ? " horse item" . $horse : "" ?> <?= $e($vars["heroGender"]) ?>">
        <img class="heroBodyImage" src="<?= $e(\Game\Hero\HeroImage::bodyUrl((int) $vars["uid"])) ?>" alt="<?= T("HeroInventory", "Body") ?>">
        <div class="equipmentSlots">
            <?php foreach ($vars["heroEquipment"] as $slot): ?>
                <?php $filled = $slot["type"] > 0; ?>
                <div class="disabled<?= $filled ? "" : " empty" ?> <?= $e($slot["slot"]) ?> heroItem heroItemV1 neutral<?= $filled ? " tier" . $slot["tier"] : "" ?>"<?= $filled && $slot["title"] !== "" ? ' title="' . $e($slot["title"]) . '"' : "" ?>>
                    <?= $arrowsSvg ?>
                    <div class="item<?= $filled ? " item" . $slot["type"] . " neutral" : "" ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="playerDescription">
    <?php if ($vars["isAdmin"]): ?>
        <div style="text-align: <?= getDirection() == "LTR" ? "right" : "left" ?>">
            <a target="_editPlayer" href="/admin.php?action=editPlayer&uid=<?= (int) $vars["uid"] ?>">
                <?= getButton(["type" => "button", "class" => "textButtonV1 green"], [], "Edit Player") ?>
            </a>
        </div>
    <?php endif; ?>
    <div class="description1"><?= $vars["desc1"] ?></div>
    <div class="description2"><?= $vars["desc2"] ?></div>
</div>
<?= $vars["specialMedals"] ?>
<table class="borderGap villages">
    <thead>
    <tr>
        <th class="tribe"></th>
        <th class="name"><?= $e(T("Profile", "Village name")) ?></th>
        <th class="oases"><?= $e(T("Profile", "Oases")) ?></th>
        <th class="inhabitants"><i class="population_medium"></i></th>
        <th class="coordinates"><?= $e(T("Profile", "Coordinates")) ?></th>
    </tr>
    </thead>
    <tbody>
    <?= $vars["villages"] ?>
    </tbody>
</table>
<script type="text/javascript" data-cmp-info="6">
    jQuery(function() {
                'use strict';
        var showProfileActionError = function(error) {
            new Travian.Dialog.Dialog({preventFormSubmit: true})
                .setContent(error.message)
                .show();
        };

        var applyProfileActions = function(response) {
            if (response.result !== undefined) {
                jQuery('#player_message-ignore-buttons-block').html(response.result);
                jQuery('#player_profile_notification').html(response.notification || '');
                Travian.Tip.refresh();
            }
        };

        var renderProfileActionButtons = function() {
            var targetPlayer = '<?= $vars["uid"] ?>';
            Travian.api('player/ignore', {
                data: {
                    method: 'render_profile_action_buttons',
                    params: {
                        targetPlayer: targetPlayer
                    }
                },
                success: applyProfileActions,
                error: showProfileActionError
            });
        };

        jQuery('#player_message-ignore-buttons-block').on('click', function(event) {

            var target = jQuery(event.target);
            var targetPlayer;

            switch(true) {
                case target.closest('button.ignore').length > 0:
                    target = target.closest('button.ignore');
                    targetPlayer = target.attr('data-uid');
                    event.preventDefault();

                    var isIgnored = target.attr('data-player-ignored') !== 'false',
                        method = isIgnored ? 'stop_ignore_target_player' : 'ignore_target_player';

                    Travian.api('player/ignore', {
                        data: {
                            method: method,
                            renderProfileActionButtons: true,
                            params: {
                                targetPlayer: targetPlayer
                            }
                        },
                        success: applyProfileActions,
                        error: showProfileActionError
                    });
                    break;
                case target.closest('button.accusation').length > 0:
                    target = target.closest('button.accusation');
                    event.preventDefault();
                    if (target.hasClass('disabled')) {
                        return;
                    }
                    new Travian.Dialog.Ajax('player/accuse', {
                        data: {action: 'edit', uid: target.attr('data-uid')},
                        context: 'accusePlayer',
                        version: 2,
                        cssClass: 'plain contentV2 playerProfile',
                        buttonOk: false,
                        darkOverlay: true
                    });
                    break;
                case target.closest('button.invite').length > 0:
                    target = target.closest('button.invite');
                    event.preventDefault();
                    if (target.hasClass('disabled')) {
                        return;
                    }
                    Travian.api('alliance/invite', {
                        data: {playerId: target.attr('data-uid')},
                        success: applyProfileActions,
                        error: showProfileActionError
                    });
                    break;
                default:
                    return;
            }

        });

        if (jQuery('#player_message-ignore-buttons-block').length > 0) {
            renderProfileActionButtons();
        }
    });
</script>
<?php if ($vars["showNote"]): ?>
<script type="text/javascript" data-cmp-info="6">
    jQuery(function() {
        var noteDoubleClickPreventer = new Travian.DoubleClickPreventer();
        noteDoubleClickPreventer.timeout = 1000;
        // The Notes button is injected with the other action buttons, so the handler is delegated.
        jQuery(document).on('click', '#playerNoteEditLink, #player_message-ignore-buttons-block button.notes', function(e) {
            e.preventDefault();

            if (!noteDoubleClickPreventer.check()) {
                return false;
            }
            new Travian.Dialog.Ajax('alliance/player-note', {
                    data: {
                        hasNote: true,
                        affectedPlayerID: <?= (int) $vars["uid"] ?>,
                        action: 'edit'
                    },
                    context: 'playerNote',
                    buttonOk: false,
                    darkOverlay: true
                }
            );
        });
    });
</script>
<?php endif; ?>
