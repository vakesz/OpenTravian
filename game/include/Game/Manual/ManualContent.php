<?php

declare(strict_types=1);

namespace Game\Manual;

use Game\Formulas;
use resources\View\PHPBatchView;

/**
 * Renders the manual's building content for both the full page and dialog
 * adapters. The callers only supply a building id; prerequisites, timing,
 * costs, and help links remain local to this module.
 */
final class ManualContent
{
    /**
     * renders a building's manual art with the tribe that owns it; shared
     * buildings and the Asclepeion use the Gaul art (capture 2026-10-30).
     */
    private const ART_TRIBE = [41 => 'roman', 42 => 'egyptian', 43 => 'hun', 44 => 'hun', 45 => 'egyptian', 47 => 'spartan', 50 => 'viking'];

    public function renderOverview(): string
    {
        return PHPBatchView::render('manual/overview');
    }

    public function renderBuilding(int $buildingId): string
    {
        if ($buildingId < 1 || $buildingId > 50 || $buildingId === 12) {
            throw new \DomainException('The requested manual entry is not available.');
        }

        $building = Formulas::$data['buildings'][$buildingId - 1] ?? null;
        if (!is_array($building)) {
            throw new \DomainException('The requested manual entry is not available.');
        }

        $view = new PHPBatchView('manual/building');
        $view->vars = [
            'building' => [
                'gid' => $buildingId,
                'upTime' => Formulas::buildingUpgradeTime($buildingId, 1, 1, false),
                'cost' => Formulas::buildingUpgradeCosts($buildingId, 1),
                'cu' => Formulas::buildingCropConsumption($buildingId, 1),
                'artTribe' => self::ART_TRIBE[$buildingId] ?? 'gaul',
            ],
            'preRequests' => $this->prerequisites($buildingId, $building),
        ];

        return $view->output();
    }

    public function renderUnit(int $unitId): string
    {
        if ($unitId < 1 || $unitId > 90) {
            throw new \DomainException('The requested manual entry is not available.');
        }

        $race = unitIdToTribe($unitId);
        $unitNumber = unitIdToNr($unitId);
        $unit = Formulas::$data['units'][$race - 1][$unitNumber - 1] ?? null;
        if (!is_array($unit)) {
            throw new \DomainException('The requested manual entry is not available.');
        }

        $view = new PHPBatchView('manual/unit');
        $view->vars = [
            'unit' => [
                'race' => $race,
                'id' => $unitId,
                'attack_power' => $unit['off'],
                'def_inf' => $unit['def_i'],
                'def_cav' => $unit['def_c'],
                'carry' => $unit['cap'],
                'trainingTime' => Formulas::uTrainingTime($unitId, 1, 0),
                // The manual must advertise exactly what troop training
                // charges through the canonical cost accessor.
                'cost' => Formulas::uTrainingCost($unitId),
                'cu' => $unit['cu'],
                'speed' => Formulas::uSpeed($unitId),
            ],
            // Nature and Natar formations carry no flavour text, and Nature no prerequisites (manual).
            'description' => $race >= 4 && $race <= 5 ? '' : T('Troops', $unitId . '.desc', ''),
            'preRequests' => $race >= 4 && $race <= 5 ? '' : $this->unitPrerequisites($unit, $unitNumber, $race),
        ];

        return $view->output();
    }

    /** @param array<string, mixed> $building */
    private function prerequisites(int $buildingId, array $building): string
    {
        $requirements = [];
        foreach (($building['breq'] ?? []) as $requiredBuildingId => $level) {
            $requiredBuildingId = (int)$requiredBuildingId;
            $level = (int)$level;
            $requirements[] = $level < 0
                ? $this->buildingAnchor($requiredBuildingId, '<strike>' . T('Buildings', $requiredBuildingId . '.title') . '</strike>')
                : $this->buildingLink($requiredBuildingId, $level);
        }
        if ((($building['req'] ?? [])['capital'] ?? 0) == 1) {
            $requirements[] = T('Manual', 'Capital');
        }
        if ($buildingId === 40) {
            $requirements[] = T('Manual', 'BuildingPlan');
        }

        return $requirements === [] ? T('Manual', 'None') : implode(', ', $requirements);
    }

    /** @param array<string, mixed> $unit */
    private function unitPrerequisites(array $unit, int $unitNumber, int $tribe): string
    {
        if ($unitNumber === 10) {
            // the Hun Settler also lists its tribe-specific Command
            // Center route; other settlers retain Residence or Palace only.
            $requirements = [
                $this->buildingLink(25, 10),
                $this->buildingLink(26, 10),
            ];
            if ($tribe === 7) {
                $requirements[] = $this->buildingLink(44, 10);
            }

            return implode(' ' . T('Manual', 'or') . ' ', $requirements);
        }
        $requirements = [];
        foreach (($unit['breq'] ?? []) as $requiredBuildingId => $level) {
            $requirements[] = $this->buildingLink((int)$requiredBuildingId, (int)$level);
        }

        return $requirements === [] ? T('Manual', 'None') : implode(', ', $requirements);
    }

    private function buildingLink(int $buildingId, int $level): string
    {
        return $this->buildingAnchor($buildingId, T('Buildings', $buildingId . '.title')) . ' '
            . T('Buildings', 'level') . ' ' . $level;
    }

    private function buildingAnchor(int $buildingId, string $content): string
    {
        return '<a onclick="Travian.Game.Manual.open(\'building\', ' . $buildingId . ')">' . $content . '</a>';
    }
}
