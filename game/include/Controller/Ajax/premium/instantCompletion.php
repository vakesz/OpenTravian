<?php

declare(strict_types=1);

namespace Controller\Ajax\premium;

use Core\Config;
use Core\Database\DB;
use Core\Village;
use Game\FinishConstruction;
use Game\GoldHelper;
use resources\View\PHPBatchView;

/** Finish now: completes the eligible construction, research and demolition of the active village. */
final class instantCompletion extends InstantPurchase
{
    protected function action(): string
    {
        return 'instant-completion';
    }

    protected function price(): int
    {
        return (int)Config::getInstance()->gold->finishNowGold;
    }

    protected function target(): array|string
    {
        $village = Village::getInstance();
        $villageId = $_POST['villageId'] ?? null;
        if ($villageId !== null && (!is_scalar($villageId) || (int)$villageId !== (int)$village->getKid())) {
            return T('map', 'please_resend_all_data');
        }
        if ($village->isWW()) {
            return T('inGame', 'finishNowWWDisabled');
        }

        $buildings = $this->eligibleBuildingOrders($village);
        $researches = $this->researchOrders((int)$village->getKid());
        $demolitions = $this->demolitionOrders($village);
        $buildingGids = array_map(static fn(array $building): int => (int)$building['gid'], $buildings);
        if (!FinishConstruction::hasEligibleFinishNowQueues($buildingGids, $researches !== [], $demolitions !== [], false)) {
            return T('finishNowPopup', 'No construction orders or research that could be completed instantly');
        }

        return ['buildings' => $buildings, 'researches' => $researches, 'demolitions' => $demolitions];
    }

    protected function dialogHtml(array $target): string
    {
        $view = new PHPBatchView('gold/finishNowPopup');
        $view->vars['buildings'] = $target['buildings'];
        $view->vars['researches'] = $target['researches'];
        $view->vars['demolitions'] = $target['demolitions'];
        $view->vars['goldCost'] = $this->price();

        return $view->output();
    }

    protected function perform(array $target): ?string
    {
        return match ((new GoldHelper())->finishImmediatelyBuildings()) {
            'completed' => null,
            'notEnoughGold' => 'notEnoughGold',
            'worldWonder' => T('inGame', 'finishNowWWDisabled'),
            default => T('finishNowPopup', 'No construction orders or research that could be completed instantly'),
        };
    }

    /**
     * Same Residence/Palace policy as GoldHelper::finishImmediatelyBuildings.
     *
     * @return list<array{gid:int,name:string,level:int}>
     */
    private function eligibleBuildingOrders(Village $village): array
    {
        $orders = [];
        $queuedByField = [];
        $rows = $village->onLoadBuildings['normal'] ?? [];
        uasort($rows, static fn(array $a, array $b): int => $a['commence'] <=> $b['commence']);

        foreach ($rows as $building) {
            $fieldId = (int)$building['building_field'];
            $field = $village->getField($fieldId);
            $itemId = (int)$field['item_id'];
            if (!FinishConstruction::canFinishWithGold($itemId, $village->isWW())) {
                continue;
            }
            $queuedByField[$fieldId] = ($queuedByField[$fieldId] ?? 0) + 1;
            $orders[] = [
                'gid' => $itemId,
                'name' => (string)T('Buildings', $itemId . '.title'),
                'level' => (int)$field['level'] + $queuedByField[$fieldId],
            ];
        }

        return $orders;
    }

    /** @return list<array{label:string}> */
    private function researchOrders(int $kid): array
    {
        $statement = DB::getInstance()->run(
            'SELECT nr, mode FROM research WHERE kid=? ORDER BY end_time ASC, id ASC',
            [$kid]
        );
        if ($statement === false) {
            return [];
        }

        $orders = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $unitName = (string)T('Troops', (int)$row['nr'] . '.title');
            $buildingLabel = (int)$row['mode'] === 1
                ? (string)T('finishNowPopup', 'smithy')
                : (string)T('finishNowPopup', 'academy');
            $orders[] = ['label' => trim($unitName . ' — ' . $buildingLabel)];
        }

        return $orders;
    }

    /** @return list<array{name:string,level:int}> */
    private function demolitionOrders(Village $village): array
    {
        $statement = DB::getInstance()->run(
            'SELECT building_field, complete FROM demolition WHERE kid=? ORDER BY end_time ASC, id ASC',
            [(int)$village->getKid()]
        );
        if ($statement === false) {
            return [];
        }

        $orders = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $field = $village->getField((int)$row['building_field']);
            $itemId = (int)$field['item_id'];
            if ($itemId < 1) {
                continue;
            }
            $orders[] = [
                'name' => (string)T('Buildings', $itemId . '.title'),
                'level' => !empty($row['complete']) ? 0 : max(0, (int)$field['level'] - 1),
            ];
        }

        return $orders;
    }
}
