<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;

use function T;

/**
 * Single source for the top-bar hero HUD state: the health and experience arc
 * paths rendered by layout/topBarBoxHero and the payloads that refresh them
 * (hero/dataForHUD and the progressive-task collect response).
 */
final class HeroHud
{
    /** Arc geometry in degrees; the masks sweep clockwise-negative from these starts. */
    private const HEALTH_START = 98;
    private const HEALTH_SPAN = 102;
    private const EXPERIENCE_START = 114;
    private const EXPERIENCE_SPAN = 88;

    /** @return array{mask: string, title: string} */
    public static function healthPaths(float $percent): array
    {
        return [
            'mask' => self::arc(self::HEALTH_START, -self::HEALTH_SPAN * self::clamp($percent) / 100, 0),
            'title' => self::arc(self::HEALTH_START, -self::HEALTH_SPAN, 0),
        ];
    }

    /** @return array{mask: string, title: string} */
    public static function experiencePaths(float $percent): array
    {
        return [
            'mask' => self::arc(self::EXPERIENCE_START, self::EXPERIENCE_SPAN * self::clamp($percent) / 100, 1),
            'title' => self::arc(self::EXPERIENCE_START, self::EXPERIENCE_SPAN, 1),
        ];
    }

    /** The hero tooltip text: at home the reference links the village name, every other state keeps its message. */
    public static function longStatus(SessionHero $hero, int|string $status): string
    {
        if ($status !== SessionHero::STATUS_HOME) {
            return (string)$hero->getLongStatus();
        }
        $kid = (int)($hero->hero['kid'] ?? 0);
        $name = (string)DB::getInstance()->fetchScalar('SELECT name FROM vdata WHERE kid=? LIMIT 1', [$kid], '');

        return sprintf(T('HeroGlobal', 'inventoryStatus.Home'), $kid, stored_name_html($name));
    }

    /** CSS class of the portrait for the hero's gender (reference: `heroGenderImage`). */
    public static function genderImageClass(int $uid): string
    {
        $gender = (string)DB::getInstance()->fetchScalar('SELECT gender FROM face WHERE uid=?', [$uid], '');

        return $gender === 'female' ? 'heroImageFemale' : 'heroImageMale';
    }

    /** CSS class of the status icon next to the portrait. */
    public static function statusClass(int|string $status): string
    {
        return match ($status) {
            SessionHero::STATUS_HOME => 'heroHome',
            SessionHero::STATUS_DEAD => 'heroDead',
            SessionHero::STATUS_REVIVING => 'heroReviving',
            SessionHero::STATUS_TRAPPED => 'heroTrapped',
            SessionHero::STATUS_ON_REINFORCE, SessionHero::STATUS_IN_ENFORCEMENT => 'heroReinforcing',
            default => 'heroRunning',
        };
    }

    /**
     * Reference `hero/dataForHUD` payload. A fresh SessionHero is read so the
     * numbers include changes made earlier in the same request.
     *
     * @return array<mixed>
     */
    public static function dataForHud(?SessionHero $hero = null, ?bool $levelUp = null): array
    {
        $session = Session::getInstance();
        $uid = (int)$session->getPlayerId();
        $hero ??= new SessionHero();
        $status = $hero->getHeroStatus();

        $healthPercent = $status === SessionHero::STATUS_REVIVING
            ? (float)$hero->getRegeneratedHealth()
            : (float)$hero->getHeroHealth();
        $healthPercent = self::clamp($healthPercent);
        $experiencePercent = self::clamp((float)$hero->getHeroExpPercent());
        $level = (int)$hero->getLevel();
        $nextLevelExp = (int)Formulas::heroExperience($level + 1);
        $missing = max(0, $nextLevelExp - (int)$hero->getHeroExp());
        $statusClass = self::statusClass($status);
        $longStatus = self::longStatus($hero, $status);
        $overview = T('HeroGlobal', 'HeroOverview') . '||' . $longStatus;
        $experienceLabel = T('HeroGlobal', 'Experience') . ' ' . self::percentLabel($experiencePercent);

        return [
            'levelUp' => $levelUp ?? ($hero->getAvailablePoints() > 0 && $hero->getAvailablePoints() % 4 === 0),
            'heroImagePath' => HeroImage::headUrl($uid),
            'heroGenderImage' => self::genderImageClass($uid),
            'url' => '/hero',
            'heroButtonTitle' => $overview,
            'heroStatusTitle' => $overview,
            'experience' => (int)$hero->getHeroExp(),
            'experiencePercent' => round($experiencePercent, 1),
            'healthStatus' => $status === SessionHero::STATUS_DEAD ? 'dead' : 'alive',
            'health' => (int)round($healthPercent),
            'level' => $level,
            'statusInlineIcon' => '<i class="' . $statusClass . '" ></i>',
            'tooltipForExperience' => $experienceLabel . '||' . sprintf(
                T('HeroGlobal', 'experienceMissing', 'Your hero needs %1$s experience to achieve level %2$s.'),
                $missing,
                $level + 1
            ),
            'bars' => [
                'health' => [
                    'startAngle' => -self::HEALTH_START,
                    'endAngle' => -(self::HEALTH_START - self::HEALTH_SPAN),
                    'currentValue' => (int)round($healthPercent),
                    'title' => T('HeroGlobal', 'health') . ': ' . self::percentLabel($healthPercent, 0),
                    'image' => 'health',
                    'paths' => self::healthPaths($healthPercent),
                ],
                'experience' => [
                    'startAngle' => -self::EXPERIENCE_START,
                    'endAngle' => -(self::EXPERIENCE_START + self::EXPERIENCE_SPAN),
                    'currentValue' => round($experiencePercent, 1),
                    'title' => T('HeroGlobal', 'Experience') . ': ' . self::percentLabel($experiencePercent),
                    'image' => 'experience',
                    'paths' => self::experiencePaths($experiencePercent),
                ],
            ],
        ];
    }

    /**
     * Hero block the task board consumes: the flat HUD payload split into
     * `data` and the `bars` the top-bar updaters need.
     *
     * @return array<string, mixed>
     */
    public static function heroBlock(?SessionHero $hero = null): array
    {
        $hud = self::dataForHud($hero ?? new SessionHero());
        $bars = $hud['bars'];
        unset($hud['bars']);

        return ['data' => $hud, 'bars' => $bars];
    }

    private static function arc(int $startDegrees, float $sweepDegrees, int $sweepFlag): string
    {
        $start = deg2rad($startDegrees);
        $end = deg2rad($startDegrees + $sweepDegrees);

        $n = static fn (float $value): string => rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return sprintf(
            'M55 55L%s %sA 55 55 0 0 %d %s %s',
            $n(55 + 55 * cos($start)),
            $n(55 + 55 * sin($start)),
            $sweepFlag,
            $n(55 + 55 * cos($end)),
            $n(55 + 55 * sin($end))
        );
    }

    private static function clamp(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }

    /** Direction-isolated percentage as emitted by the reference tooltips. */
    private static function percentLabel(float $percent, int $decimals = 0): string
    {
        return '&#x202d;&#x202d;' . number_format($percent, $decimals, '.', '') . '&#x202c;&#37;&#x202c;';
    }
}
