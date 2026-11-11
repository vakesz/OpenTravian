<?php

declare(strict_types=1);

namespace Core\Jobs;

use Core\Automation;
use Core\Database\DB;
use Core\Mail\QueueWorker;
use Game\Mail\Reminders;
use Game\Webhook\QueueWorker as WebhookQueueWorker;
use Model\AdventureModel;
use Model\AuctionModel;
use Model\AutoExtendModel;
use Model\DailyQuestModel;
use Model\FakeUserModel;
use Model\InactiveModel;
use Model\MedalsModel;
use Model\NatarsModel;
use Model\OasesModel;
use Model\StatisticsGeneralModel;

class Launcher
{
    private static ?self $_self = null;

    public static function lunchJobs(): void
    {
        set_time_limit(0);
        self::getInstance()->buildComplete();
        self::getInstance()->movementComplete();
        self::getInstance()->trainingComplete();
        self::getInstance()->gameProgress();
        self::getInstance()->routineJobs();
        self::getInstance()->AIProgress();
        self::getInstance()->mailDelivery();
        self::getInstance()->webhookDelivery();
    }

    public function buildComplete(): void
    {
        $jobs = [];
        {
            $job = [Automation::getInstance(), 'tradeRoutes'];
            $jobs[] = new Job('marketComplete:tradeRoute', 5, $job);
        }
        {
            $job = [Automation::getInstance(), 'researchComplete'];
            $jobs[] = new Job('marketComplete:researchComplete', getGameSpeed() > 20 ? 1 : 3, $job);
        }
        {
            $job = [Automation::getInstance(), 'marketComplete'];
            $jobs[] = new Job('marketComplete:marketComplete', 2, $job);
        }
        {
            $job = [new AuctionModel(), 'doAuction'];
            $jobs[] = new Job('auctionComplete:doAuction', 5, $job);
        }
        {
            $job = [new AuctionModel(), 'fakeAuction'];
            $jobs[] = new Job('routineJobs:fakeAuction', 600, $job);
        }
        {
            $job = [Automation::getInstance(), 'buildComplete'];
            $jobs[] = new Job('buildComplete:buildComplete', 1, $job);
        }
        new Job('buildComplete', 1, $jobs, TRUE);
    }

    public static function getInstance(): self
    {
        return self::$_self ??= new self();
    }

    public function movementComplete(): void
    {
        foreach ([[0, 'attackMovementComplete'], [1, 'otherMovementComplete']] as [$mode, $method]) {
            // Same filter as the worker's query, served by the (mode, end_time) index.
            $nextDue = static function () use ($mode) {
                $next = DB::getInstance()->fetchScalar("SELECT MIN(end_time) FROM movement WHERE mode=$mode");

                return $next === false || $next === null ? null : (float)$next;
            };
            new Job('movementComplete', 0, [Automation::getInstance(), $method], TRUE, $nextDue);
        }
    }

    public function trainingComplete(): void
    {
        // Training retains its normal one-second cadence at published/local low
        // speeds. High-speed worlds use Job's bounded 100ms idle cadence so a
        // millisecond/nanosecond queue is not visibly delayed by a full second.
        new Job('trainingComplete', getGameSpeed() > 20 ? 0.1 : 1, [Automation::getInstance(), 'trainingComplete'], TRUE);
    }

    public function gameProgress(): void
    {
        $jobs = [];
        {
            $job = [Automation::getInstance(), 'checkAutoFinish'];
            $jobs[] = new Job('gameProgress:checkAutoFinish', 30, $job);
        }
        {
            $job = [Automation::getInstance(), 'setUpNewServer'];
            $jobs[] = new Job('gameProgress:setUpNewServer', 60, $job);
        }
        {
            $job = [Automation::getInstance(), 'boughtGoldMessage'];
            $jobs[] = new Job('gameProgress:boughtGoldMessage', 10, $job);
        }
        {
            $job = [Automation::getInstance(), 'banProgress'];
            $jobs[] = new Job('gameProgress:banProgress', 120, $job);
        }
        {
            $job = [Automation::getInstance(), 'referenceCheck'];
            $jobs[] = new Job('gameProgress:referenceCheck', 30, $job);
        }
        {
            $job = [new NatarsModel(), 'runJobs'];
            $jobs[] = new Job('gameProgress:ArtifactReleases', 30, $job);
        }
        {
            $job = [new MedalsModel(), 'resetMedals'];
            $jobs[] = new Job('gameProgress:resetMedals', 30, $job);
        }
        {
            $job = [new StatisticsGeneralModel(), 'snapshotDaily'];
            $jobs[] = new Job('gameProgress:statisticsSnapshot', 900, $job);
        }
        {
            $job = [new DailyQuestModel(), 'resetDailyQuest'];
            $jobs[] = new Job('gameProgress:resetDailyQuest', 30, $job);
        }
        {
            // Neutral oasis population must not depend on someone opening the
            // map or attacking it. The model itself limits each pass to a
            // bounded batch and serializes per-oasis changes.
            $job = [OasesModel::class, 'trainDueOases'];
            $jobs[] = new Job('gameProgress:trainDueOases', 15, $job);
        }
        {
            $job = [new AutoExtendModel(), 'processAutoExtend'];
            $jobs[] = new Job('generalProgress:autoExtend', 60, $job);
        }
        {
            $job = [Automation::getInstance(), 'updateFoolArtifact'];
            $jobs[] = new Job('generalProgress:updateFoolArtifact', 120, $job);
        }
        {
            $job = [Automation::getInstance(), 'checkForArtifactActivation'];
            $jobs[] = new Job('generalProgress:checkForArtifactActivation', 5, $job);
        }
        {
            $job = [Automation::getInstance(), 'cleanupServer'];
            $jobs[] = new Job('generalProgress:cleanupServer', 300, $job);
        }
        {
            $job = [Automation::getInstance(), 'deleteOasisComplete'];
            $jobs[] = new Job('loyaltyAndCulturePoint:deleteOasisComplete', 60, $job);
        }
        {
            $job = [Automation::getInstance(), 'handleAllianceBonusTasks'];
            $jobs[] = new Job('gameProgress:allianceBonus', 60, $job);
        }
        {
            $job = [Automation::getInstance(), 'zeroPopVillages'];
            $jobs[] = new Job('generalProgress:zeroPopVillages', 10, $job);
        }
        new Job('gameProgress', 10, $jobs, TRUE);
    }

    public function routineJobs(): void
    {
        $jobs = [];
        {
            $job = [new AdventureModel(), 'checkForNewAdventures'];
            $jobs[] = new Job('routineJobs:checkForNewAdventures', 10, $job);
        }
        {
            $job = [Automation::getInstance(), 'checkGameFinish'];
            $jobs[] = new Job('checkIndexFunctions:checkGameFinish', 30, $job);
        }
        {
            $job = [new InactiveModel(), 'startWorker'];
            $jobs[] = new Job('checkIndexFunctions:clearAndDeleting', 30, $job);
        }
        {
            $job = [new Reminders(), 'vacationEnding'];
            $jobs[] = new Job('routineJobs:vacationEndingReminders', 600, $job);
        }
        {
            $job = [Automation::getInstance(), 'cleanupIndex'];
            $jobs[] = new Job('checkIndexFunctions:cleanupIndex', 600, $job);
        }
        {
            $job = [Automation::getInstance(), 'refreshCountryFlag'];
            $jobs[] = new Job('mayExitJobs:refreshCountryFlag', 100, $job);
        }
        {
            $job = [Automation::getInstance(), 'resetDailyGold'];
            $jobs[] = new Job('mayExitJobs:resetDailyGold', 45, $job);
        }
        {
            $job = [Automation::getInstance(), 'backup'];
            $jobs[] = new Job('mayExitJobs:backup', 360, $job);
        }
        {
            $job = [Automation::getInstance(), 'pruneAuditLog'];
            $jobs[] = new Job('mayExitJobs:pruneAuditLog', 3600, $job);
        }
        {
            $job = static function () {
                $startedAt = (int)getenv('TRAVIAN_ENGINE_STARTED_AT');
                if ($startedAt > 0) {
                    EngineHeartbeat::requeueStaleCodeQuarantines($startedAt);
                }
            };
            $jobs[] = new Job('routineJobs:requeueStaleCodeQuarantines', 600, $job);
        }
        new Job('routineJobs', 20, $jobs, TRUE);
    }

    public function AIProgress(): void
    {
        $fakeModel = new FakeUserModel();
        $jobs = [];
        {
            $job = [$fakeModel, 'handleFakeUsers'];
            $jobs[] = new Job('AIProgress:handleFakeUsers', 45, $job);
        }
        {
            $job = [$fakeModel, 'handleFakeUserExpands'];
            $jobs[] = new Job('AIProgress:handleFakeUserExpands', 45, $job);
        }
        $natarsModel = new NatarsModel();
        {
            $job = [$natarsModel, 'handleNatarVillages'];
            $jobs[] = new Job('AIProgress:handleNatarVillages', 15, $job);
        }
        {
            $job = [$natarsModel, 'handleNatarExpansion'];
            $jobs[] = new Job('AIProgress:handleNatarExpansion', 15, $job);
        }
        new Job('AIProgress', 5, $jobs, TRUE);
    }

    public function mailDelivery(): void
    {
        new Job('mailDelivery', 5, [new QueueWorker(), 'drain'], TRUE);
    }

    /** The Discord world-event webhook: one post at most per tick, each bounded by a 3 second request limit. */
    public function webhookDelivery(): void
    {
        new Job('webhookDelivery', 5, [new WebhookQueueWorker(), 'drain'], TRUE);
    }
}
