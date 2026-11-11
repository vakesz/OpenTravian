<?php

declare(strict_types=1);

namespace Game\FarmList;

use Controller\Ajax\AjaxBase;
use Core\Session;

use function T;

/**
 * Base of the farm-list API endpoints (Controller\Ajax\farmList\*): checks who may change farm lists, turns a
 * refusal into the API error, and answers with the lists that changed in the read model's shape.
 */
abstract class FarmListEndpoint extends AjaxBase
{
    /**
     * @return array{changed?: list<int>, deleted?: list<int>, id?: int, viewData?: array<string, mixed>, config?: array<string, mixed>, lists?: list<array<string, mixed>>} the lists to
     *         send back, the lists that are gone, the id of a created list, or the whole page data
     */
    abstract protected function handle(FarmListStore $store): array;

    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!$session->isValid() || $session->banned()) {
            $this->error((string)T('FarmList', $session->banned() ? 'avatar_locked' : 'errorDataChange'), 403);
            return;
        }
        if ($session->isSitter() && !$session->checkSitterPermission(Session::SITTER_CAN_RAID)) {
            $this->error((string)T('FarmList', 'sitterIsNotAllowedToSend'), 403);
            return;
        }
        try {
            $result = $this->handle(new FarmListStore());
        } catch (\DomainException $exception) {
            $status = $exception->getCode() >= 400 ? $exception->getCode() : 400;
            $this->error($exception->getMessage(), $status);
            if ($status === 409) {
                $this->response['error'] = 'raidList.targetExists';
            }
            return;
        }
        if (isset($result['viewData']) || isset($result['lists'])) {
            $this->response = array_intersect_key($result, ['viewData' => 1, 'config' => 1, 'lists' => 1]) + $this->response;
            return;
        }
        $this->response['farmLists'] = (new FarmListData())->lists($result['changed'] ?? []);
        $this->response['deletedFarmListIds'] = $result['deleted'] ?? [];
        if (isset($result['id'])) {
            $this->response['id'] = $result['id'];
        }
    }

    /** The id in the route (`farm-list/<id>`). */
    protected function routeId(): int
    {
        return (int)($_GET['id'] ?? 0);
    }
}
