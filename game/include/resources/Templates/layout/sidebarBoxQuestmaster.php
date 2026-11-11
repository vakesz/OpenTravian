<?php

use Core\Session;

$session = Session::getInstance();
$currentKid = (int)$session->getKid();
$race = (int)$session->getRace();
$mentorGradientId = 'mentor_gradient_' . $currentKid;
$taskOverviewUrl = '/tasks';
$taskOverviewTitleUrl = '/tasks';
?>
<div id="sidebarBoxQuestmaster" class="sidebarBox expanded">
        <div class="header ">
            <button type="button"
                    id="questmasterButton"
                    class="vid_<?=$race; ?><?=$vars['hasNewReward'] ? ' claimable' : ''; ?>">
                <svg width="160" height="122" viewBox="0 0 160 122" aria-hidden="true" focusable="false">
                    <path fill="url(#<?=htmlspecialchars($mentorGradientId, ENT_QUOTES, 'UTF-8');?>)" d="<?=\Game\MentorSilhouettes::path($race);?>"/>
                    <defs>
                        <linearGradient id="<?=htmlspecialchars($mentorGradientId, ENT_QUOTES, 'UTF-8');?>" gradientTransform="rotate(90)">
                            <stop class="colorStop1" offset="0%"></stop>
                            <stop class="colorStop2" offset="30%"></stop>
                            <stop class="colorStop1" offset="60%"></stop>
                            <stop class="colorStop3" offset="95%"></stop>
                            <stop class="colorStop4" offset="100%"></stop>
                        </linearGradient>
                    </defs>
                </svg>
                <img class="mentor" alt="" src="img/spacer.gif">
                <?php if ($vars['hasNewReward']): ?>
                    <div class="bigSpeechBubble newQuestSpeechBubble" aria-hidden="true">
                        <img src="img/spacer.gif" alt="">
                    </div>
                <?php endif; ?>
            </button>
            <script type="text/javascript">
                jQuery(function () {
                    jQuery('#questmasterButton').on('click', function () {
                        window.location.assign(<?=json_encode($taskOverviewUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);?>);
                    });
                });
            </script>
        </div>
        <div class="content">
			<a href="<?=htmlspecialchars($taskOverviewTitleUrl, ENT_QUOTES, 'UTF-8');?>" class="progressiveTasksTitle" onclick="event.stopPropagation();">
				<div class="boxTitle"><?=T("Quest", "taskOverview");?></div>
			</a>
        </div>
</div>
