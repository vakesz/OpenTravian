<?php

namespace Controller\Ajax\alliance;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Model\AllianceBonusModel;
use resources\View\PHPBatchView;

final class bonusOverview extends AjaxBase
{
    private const BONUS_PERCENTAGE = [
        AllianceBonusModel::TYPE_TRAINING => 2,
        AllianceBonusModel::TYPE_CP => 4,
        AllianceBonusModel::TYPE_ARMOR => 2,
        AllianceBonusModel::TYPE_TRADE => 30,
    ];

    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->getAllianceId() <= 0) {
            $this->error('Alliance bonus information is unavailable.', 403);
            return;
        }

        $model = new AllianceBonusModel();
        $limit = Formulas::getAllianceBonusDonationLimit($model->getMaxAllianceBonusLevel($this->session->getAllianceId()));
        $this->response['overview'] = self::render();
        $this->response['contributionForm'] = (new PHPBatchView('alliance/bonuses/contributionForm'))->output();
        $this->response['limitBar'] = (new PHPBatchView('alliance/bonuses/myDailyContributionLimit'))->output();
        $this->response['total'] = (int)$this->session->getAllianceContribution();
        $this->response['limit'] = $limit;
    }

    public static function render(): string
    {
        $view = new PHPBatchView('alliance/bonuses/overview');
        return $view->output();
    }

    public static function renderProgressBar(int $type): string
    {
        if (!isset(self::BONUS_PERCENTAGE[$type])) {
            return '';
        }

        $session = Session::getInstance();
        $aid = (int)$session->getAllianceId();
        if ($aid <= 0) {
            return '';
        }

        $model = new AllianceBonusModel();
        $params = $model->getAllianceBonusTypeParams($aid, $type);
        if (!is_array($params)) {
            return '';
        }

        $level = min(5, max(0, (int)$params['level']));
        $contributions = max(0, (int)$params['contributions']);
        $isMaxed = $level >= 5;
        $isUpgrading = !$isMaxed && $model->isUnlockingNextLevel($aid, $type);

        $currentThreshold = Formulas::getAllianceBonusContributesNeededForLevel($level);
        $nextThreshold = $isMaxed
            ? $currentThreshold
            : Formulas::getAllianceBonusContributesNeededForLevel($level + 1);
        $stageTarget = max(1, $nextThreshold - $currentThreshold);
        $stageProgress = min($stageTarget, max(0, $contributions - $currentThreshold));
        $percentage = $isMaxed || $isUpgrading
            ? 100
            : min(100, (int)round(($stageProgress / $stageTarget) * 100));

        $icons = [
            AllianceBonusModel::TYPE_TRAINING => 'Recruitment',
            AllianceBonusModel::TYPE_CP => 'Philosophy',
            AllianceBonusModel::TYPE_ARMOR => 'Metallurgy',
            AllianceBonusModel::TYPE_TRADE => 'Commerce',
        ];
        // Pixel offsets of the five level marks inside the 494px track (matches the Gpack .levels rules).
        $marks = [0, 38, 93, 179, 310, 494];
        $fill = $marks[$level];
        if (!$isMaxed) {
            $fill += (int) round(($marks[$level + 1] - $marks[$level]) * ($percentage / 100));
        }
        $lro = static fn (int $n): string => "\u{202D}+\u{202D}" . $n . "\u{202C}%\u{202C}";
        $step = self::BONUS_PERCENTAGE[$type];

        $percentages = '';
        $levels = '';
        for ($i = 1; $i <= 5; ++$i) {
            $state = $i <= $level ? 'reached' : 'notreached' . ($isUpgrading && $i === $level + 1 ? ' upgrading' : '');
            $percentages .= '<div class="level' . $i . ' ' . $state . '">' . $lro($i * $step) . '</div>';
            $tooltip = !$isMaxed && $i === $level + 1
                ? sprintf(T('AllianceBonus', 'Amount of resources needed to reach next level: %s'), "\u{202D}" . number_format_x(max(0, $nextThreshold - $contributions)) . "\u{202C}")
                : '';
            $levels .= '<span class="levelTooltip' . $i . '" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '"> </span><div class="level' . $i . ' ' . $state . '" title=""></div>';
        }

        return '<div class="bonusBar">'
            . '<img src="/img/spacer.gif" title="" alt="" class="bonusIcon' . $icons[$type] . '"/>'
            . '<div class="progressBar' . ($isMaxed ? ' complete' : '') . '">'
            . '<div class="level' . $level . ' levelIndicator"><div class="indicator">' . $lro($level * $step)
            . '<div class="arrow"></div><div class="crown"></div></div></div>'
            . '<div class="levelPercentages">' . $percentages . '</div>'
            . '<div class="levels">' . $levels . '</div>'
            . '<div class="front"><div class="back" style="width: ' . max(1, $fill) . 'px"></div></div>'
            . '</div><div class="clear"></div>'
            . '<div class="bonusActivationMessage hide"></div>'
            . '</div>';
    }

    public static function renderContributors(int $type, bool $weekly): string
    {
        if (!isset(self::BONUS_PERCENTAGE[$type])) {
            return '';
        }

        $aid = (int)Session::getInstance()->getAllianceId();
        if ($aid <= 0) {
            return '';
        }

        $model = new AllianceBonusModel();
        $contributors = $weekly
            ? $model->getContributorsOfTheWeek($aid, $type)
            : $model->getAllTimeContributors($aid, $type);
        if (!$contributors) {
            return '';
        }

        $id = 'top5_contributors_' . ($weekly ? 'week' : 'all');
        // The reference prints the all-time head cells without the column classes.
        $headClass = static fn (string $class): string => $weekly ? ' class="' . $class . '"' : '';
        $html = '<table cellspacing="1" cellpadding="1" class="top5 row_table_data" id="' . $id . '"><thead><tr>'
            . '<td' . $headClass('ra') . '>' . T('AllianceBonus', 'NoColumn') . '</td>'
            . '<td' . $headClass('pla') . '>' . T('AllianceBonus', 'Player(s)') . '</td>'
            . '<td' . $headClass('val') . '>' . T('AllianceBonus', 'Resources') . '</td>'
            . '</tr></thead><tbody>';
        $rank = 1;
        while ($contributor = $contributors->fetch_assoc()) {
            $name = htmlspecialchars((string)$contributor['name'], ENT_QUOTES, 'UTF-8');
            $html .= '<tr class="hover"><td class="ra fc">' . $rank++ . '.&nbsp;</td>'
                . '<td class="pla"><a href="' . profileUrl((int) $contributor['id']) . '">' . $name . '</a></td>'
                . '<td class="val lc">' . self::isolatedNumber((int)$contributor['points']) . '</td></tr>';
        }
        for (; $rank <= 5; ++$rank) {
            $html .= '<tr class="hover"><td class="ra fc">' . $rank . '.&nbsp;</td>'
                . '<td class="pla"><span class="archived">-</span></td>'
                . '<td class="val lc">' . self::isolatedNumber(0) . '</td></tr>';
        }

        $session = Session::getInstance();
        $column = ($weekly ? AllianceBonusModel::USER_WEEK_CONTRIBUTION_PARAMS : AllianceBonusModel::USER_TOTAL_CONTRIBUTION_PARAMS)[$type];
        $db = DB::getInstance();
        $uid = (int) $session->getPlayerId();
        $own = (int) $db->fetchScalar("SELECT $column FROM users WHERE id=$uid");
        $ownRank = 1 + (int) $db->fetchScalar("SELECT COUNT(*) FROM users WHERE aid=$aid AND $column > $own");
        $html .= '<tr><td colspan="3"></td></tr>'
            . '<tr class="hl own"><td class="ra fc">' . $ownRank . '.&nbsp;</td>'
            . '<td class="pla"><a href="' . profileUrl($uid) . '">' . htmlspecialchars((string) $session->getName(), ENT_QUOTES, 'UTF-8') . '</a></td>'
            . '<td class="val lc">' . self::isolatedNumber($own) . '</td></tr>';

        return $html . '</tbody></table>';
    }

    private static function isolatedNumber(int $value): string
    {
        return "\u{202D}" . number_format($value) . "\u{202C}";
    }
}
