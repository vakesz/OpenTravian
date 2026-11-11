<?php

declare(strict_types=1);

namespace Controller\Ajax\marketplace;

use Game\Account\DeletionLock;
use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\ResourcesHelper;
use Game\Ships;
use Model\MarketModel;
use Model\Quest;

/**
 * Validates marketplace shipments and atomically deducts an approved shipment.
 */
final class prepare extends AjaxBase
{
    /** send-resources composer posts lumber/clay/iron/crop. */
    private const RESOURCE_INPUTS = ['lumber', 'clay', 'iron', 'crop'];

    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to use the marketplace.', 403);
            return;
        }

        if (!$this->session->checkSitterPermission(Session::SITTER_CAN_SEND_RESOURCES)) {
            $this->response['errorMessage'] = T('MarketPlace', 'sitterNoPermissions');
            return;
        }
        if ($this->session->banned()) {
            $this->response['errorMessage'] = T('MarketPlace', 'PlayerBanned');
            return;
        }
        if ($this->session->isInVacationMode()) {
            $this->response['errorMessage'] = T('MarketPlace', 'sitterNoPermissions');
            return;
        }
        if (Config::getInstance()->dynamic->serverFinished) {
            $this->response['errorMessage'] = T('MarketPlace', 'serverFinished');
            return;
        }
        $deletion = DeletionLock::refusal((int) $this->session->getPlayerId(), 'cannot_send_resources_in_deletion');
        if ($deletion !== null) {
            $this->response['errorMessage'] = $deletion;
            return;
        }

        $village = Village::getInstance();
        $merchantCount = max($village->getTypeLevel(17));
        if ($merchantCount < 1) {
            $this->response['errorMessage'] = T('MarketPlace', 'not enough merchants');
            return;
        }

        $resources = $this->resources();
        if ($resources === null || array_sum($resources) < 1) {
            $this->response['errorMessage'] = T('MarketPlace', 'noResourcesEntered');
            return;
        }

        $repeat = $this->repeatCount($_POST['x2'] ?? 1);
        if ($repeat === null) {
            $this->response['errorMessage'] = T('MarketPlace', 'enterVillageNameOrCoordinate');
            return;
        }

        if (array_key_exists('a', $_POST)) {
            $this->sendShipment($village, $merchantCount, $resources, $repeat);
            return;
        }

        $target = $this->targetFromInputs();
        if ($target === null) {
            return;
        }

        $byShip = $this->usesShips();
        $merchantCapacity = $byShip ? $this->shipCapacity($village) : $this->merchantCapacity($village);
        $requiredMerchants = (int)ceil(array_sum($resources) / $merchantCapacity);
        $availableCarriers = $byShip
            ? Ships::homeShips((int)$village->getKid())[Ships::TRADE]
            : $this->availableMerchants($village, $merchantCapacity, $merchantCount);
        if ($requiredMerchants > $availableCarriers) {
            $this->response['errorMessage'] = T('MarketPlace', $byShip ? 'not enough trade ships' : 'not enough merchants');
            return;
        }
        if (!$this->hasResources($village->getCurrentResources(), $resources)) {
            $this->response['errorMessage'] = T('MarketPlace', 'not enough resources');
            return;
        }

        $this->response['confirmation'] = $this->confirmation($village, $target, $repeat, $requiredMerchants, $byShip);
    }

    /**
     * @param list<int> $resources
     */
    private function sendShipment(Village $village, int $merchantCount, array $resources, int $repeat): void
    {
        $sourceKid = $this->positiveInteger($_POST['a'] ?? null);
        $targetKid = $this->positiveInteger($_POST['kid'] ?? null);
        $checker = $_POST['c'] ?? null;
        if ($sourceKid === null
            || $targetKid === null
            || $sourceKid !== (int)$village->getKid()
            || !Session::claimAction($checker)) {
            $this->response['errorMessage'] = T('MarketPlace', 'enterVillageNameOrCoordinate');
            return;
        }

        $target = $this->targetByKid($targetKid);
        if ($target === null) {
            $this->response['errorMessage'] = T('MarketPlace', 'noVillageInCoordinate');
            return;
        }

        $merchantCapacity = $this->merchantCapacity($village);
        ResourcesHelper::updateVillageResources($sourceKid);
        $error = $this->persistShipment(
            $sourceKid,
            $targetKid,
            $resources,
            $repeat,
            $merchantCount,
            $merchantCapacity,
            $this->usesShips()
        );
        if ($error !== null) {
            $this->response['errorMessage'] = T('MarketPlace', $error);
            return;
        }

        Quest::getInstance()->setQuestBitwise('economy', 7, 1);
        $this->response['notice'] = T('MarketPlace', 'resourcesSent');
        $this->response['checker'] = $this->session->getChecker();
    }

    /** @return array{0: int, 1: int, 2: int, 3: int}|null */
    private function resources(): ?array
    {
        $resources = [];
        foreach (self::RESOURCE_INPUTS as $input) {
            $value = $_POST[$input] ?? null;
            if (!is_scalar($value)) {
                return null;
            }
            if ((string)$value === '') {
                $resources[] = 0;
                continue;
            }
            if (!preg_match('/^[0-9]{1,15}$/', (string)$value)) {
                return null;
            }
            $resources[] = (int)$value;
        }

        return $resources;
    }

    private function repeatCount(mixed $repeat): ?int
    {
        if (!is_scalar($repeat) || !in_array((string)$repeat, ['1', '2', '3'], true)) {
            return null;
        }

        $repeat = (int)$repeat;
        if ($repeat === 3 && !$this->session->hasGoldClub()) {
            return null;
        }
        if ($repeat === 2 && !$this->session->hasGoldClub() && !$this->session->hasPlus()) {
            return null;
        }

        return $repeat;
    }

    /** @return array{kid: int, name: string, owner: int, playerName: string, allianceName: string}|null */
    private function targetFromInputs(): ?array
    {
        $name = $_POST['dname'] ?? '';
        if (is_scalar($name) && trim((string)$name) !== '') {
            $name = trim((string)$name);
            $target = $this->targetByName($name);
            if ($target === null) {
                // The composer inserts this JSON value with jQuery.text(), so
                // the player-provided village name remains plain text.
                $this->response['errorMessage'] = str_replace(
                    '[NAME]',
                    $name,
                    T('MarketPlace', 'noVillageWithName')
                );
            }

            return $target;
        }

        $xInput = $_POST['x'] ?? null;
        $yInput = $_POST['y'] ?? null;
        $hasCoordinateInput = (is_scalar($xInput) && trim((string)$xInput) !== '')
            || (is_scalar($yInput) && trim((string)$yInput) !== '');
        if (!$hasCoordinateInput) {
            $this->response['errorMessage'] = T('MarketPlace', 'enterVillageNameOrCoordinate');
            return null;
        }

        $x = $this->coordinate($xInput);
        $y = $this->coordinate($yInput);
        if ($x === null || $y === null) {
            $this->response['errorMessage'] = T('MarketPlace', 'invalidCoordinates');
            return null;
        }

        $target = $this->targetByKid(Formulas::xy2kid($x, $y));
        if ($target === null) {
            $this->response['errorMessage'] = T('MarketPlace', 'noVillageInCoordinate');
        }

        return $target;
    }

    /** @return array{kid: int, name: string, owner: int, playerName: string, allianceName: string}|null */
    private function targetByName(string $name): ?array
    {
        if (mb_strlen($name) > 45) {
            return null;
        }

        return $this->target('v.name=?', [$name]);
    }

    /** @return array{kid: int, name: string, owner: int, playerName: string, allianceName: string}|null */
    private function targetByKid(int $kid): ?array
    {
        return $this->target('v.kid=?', [$kid]);
    }

    /**
     * @return array{kid: int, name: string, owner: int, playerName: string, allianceName: string}|null
     *
     * @param list<int>|list<string> $parameters
     */
    private function target(string $condition, array $parameters): ?array
    {
        $statement = DB::getInstance()->run(
            'SELECT v.kid, v.name, v.owner, u.name AS player_name, COALESCE(a.tag, \'-\') AS alliance_name '
            . 'FROM vdata v JOIN users u ON u.id=v.owner LEFT JOIN alidata a ON a.id=u.aid '
            . "WHERE {$condition} AND v.owner>0 AND v.hidden=0 LIMIT 1",
            $parameters
        );
        $result = $statement ? $statement->get_result() : false;
        $row = $result ? $result->fetch_assoc() : null;
        if ($row === null) {
            return null;
        }

        return [
            'kid' => (int)$row['kid'],
            'name' => (string)$row['name'],
            'owner' => (int)$row['owner'],
            'playerName' => (string)$row['player_name'],
            'allianceName' => (string)$row['alliance_name'],
        ];
    }

    private function coordinate(mixed $coordinate): ?int
    {
        if (!is_scalar($coordinate) || !preg_match('/^-?[0-9]{1,4}$/', (string)$coordinate)) {
            return null;
        }

        return Formulas::coordinateFixer((int)$coordinate);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,8}$/', (string)$value)) {
            return null;
        }

        return (int)$value;
    }

    /** True when the player chose to send with trade ships instead of merchants. */
    private function usesShips(): bool
    {
        return !empty($_POST['useShip']);
    }

    /** Resources one trade ship carries: the tribe's ship capacity with the Trade Office and the alliance bonus. */
    private function shipCapacity(Village $village): int
    {
        $allianceBonus = $this->session->getAllianceId() > 0
            ? AllianceBonus::getTradersBonus($this->session->getAllianceId(), $this->session->getAllianceJoinTime())
            : 1;

        return max(1, Ships::tradeCapacity((int)$this->session->getRace(), (int)max($village->getTypeLevel(28)), (float)$allianceBonus));
    }

    private function merchantCapacity(Village $village): int
    {
        $allianceBonus = $this->session->getAllianceId() > 0
            ? AllianceBonus::getTradersBonus($this->session->getAllianceId(), $this->session->getAllianceJoinTime())
            : 1;

        return max(1, (int)floor(Formulas::merchantCAP(
            $this->session->getRace(),
            max($village->getTypeLevel(28)),
            $allianceBonus
        )));
    }

    private function availableMerchants(Village $village, int $merchantCapacity, int $merchantCount): int
    {
        $market = new MarketModel();
        $kid = $village->getKid();

        return max(
            0,
            $merchantCount
            - $market->getOfferingMerchantsCount($kid, $merchantCapacity)
            - $market->getOnTheWayMerchantsCount($kid, $merchantCapacity)
        );
    }

    /**
     * @param list<mixed> $stock
     * @param array<mixed>|list<int> $resources
     */
    private function hasResources(array $stock, array $resources): bool
    {
        foreach ($resources as $index => $amount) {
            if (floor((float)$stock[$index]) < $amount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return 'not enough resources'|'not enough merchants'|'enterVillageNameOrCoordinate'|null
     * @param array<mixed> $resources
     */
    private function persistShipment(
        int $sourceKid,
        int $targetKid,
        array $resources,
        int $repeat,
        int $merchantCount,
        int $merchantCapacity,
        bool $byShip = false
    ): ?string {
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return 'enterVillageNameOrCoordinate';
        }

        try {
            $source = $this->lockedSource($db, $sourceKid);
            if ($source === null || (int)$source['owner'] !== (int)$this->session->getPlayerId()) {
                throw new \RuntimeException('enterVillageNameOrCoordinate');
            }
            if (!$this->hasResources([
                $source['wood'],
                $source['clay'],
                $source['iron'],
                $source['crop'],
            ], $resources)) {
                throw new \RuntimeException('not enough resources');
            }

            if ($byShip) {
                // Trade ships are extra carriers: they leave the harbor with the delivery and dock again when they are back.
                $shipCapacity = $this->shipCapacity(Village::getInstance());
                $shipsNeeded = (int)ceil(array_sum($resources) / $shipCapacity);
                $took = $db->run('UPDATE units SET ship1=ship1-? WHERE kid=? AND ship1>=?', [$shipsNeeded, $sourceKid, $shipsNeeded]);
                if (!$took || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('not enough trade ships');
                }
            } else {
                $requiredMerchants = (int)ceil(array_sum($resources) / $merchantCapacity);
                if ($requiredMerchants > (new MarketModel())->availableMerchantsLocked($sourceKid, $merchantCapacity, $merchantCount)) {
                    throw new \RuntimeException('not enough merchants');
                }
            }

            $deduct = $db->run(
                'UPDATE vdata SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-? '
                . 'WHERE kid=? AND owner=? AND wood>=? AND clay>=? AND iron>=? AND crop>=?',
                [
                    $resources[0], $resources[1], $resources[2], $resources[3],
                    $sourceKid, $this->session->getPlayerId(),
                    $resources[0], $resources[1], $resources[2], $resources[3],
                ]
            );
            if (!$deduct || $db->affectedRows() !== 1) {
                throw new \RuntimeException('not enough resources');
            }

            $arrival = time() + Ships::tradeTravelSeconds($sourceKid, $targetKid, (int)$this->session->getRace(), $byShip);
            $shipment = $db->run(
                'INSERT INTO send (kid, to_kid, wood, clay, iron, crop, x, ships, mode, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
                [$sourceKid, $targetKid, $resources[0], $resources[1], $resources[2], $resources[3], $repeat, $byShip ? $shipsNeeded : 0, $arrival]
            );
            if (!$shipment || $db->affectedRows() !== 1 || !$db->commit()) {
                throw new \RuntimeException('enterVillageNameOrCoordinate');
            }

            return null;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            return in_array($exception->getMessage(), ['not enough resources', 'not enough merchants', 'not enough trade ships'], true)
                ? $exception->getMessage()
                : 'enterVillageNameOrCoordinate';
        }
    }

    /** @return array<string, mixed>|null */
    private function lockedSource(DB $db, int $kid): ?array
    {
        $statement = $db->run(
            'SELECT owner, wood, clay, iron, crop FROM vdata WHERE kid=? FOR UPDATE',
            [$kid]
        );
        $result = $statement ? $statement->get_result() : false;

        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * @return array{kid: int, name: string, playerName: string, allianceName: string, x: int, y: int, duration: string, arrival: string, merchants: int, repeat: int, checker: string}
     * @param array<string, int|string> $target
     */
    private function confirmation(Village $village, array $target, int $repeat, int $requiredMerchants, bool $byShip = false): array
    {
        $coordinates = Formulas::kid2xy($target['kid']);
        $duration = Ships::tradeTravelSeconds((int)$village->getKid(), (int)$target['kid'], (int)$this->session->getRace(), $byShip);
        return [
            'kid' => $target['kid'],
            'checker' => $this->session->getChecker(),
            'name' => $target['name'],
            'playerName' => $target['playerName'],
            'allianceName' => $target['allianceName'],
            'x' => $coordinates['x'],
            'y' => $coordinates['y'],
            'duration' => secondsToString($duration),
            'arrival' => TimezoneHelper::date('H:i', time() + $duration),
            'merchants' => $requiredMerchants,
            'repeat' => $repeat,
        ];
    }
}
