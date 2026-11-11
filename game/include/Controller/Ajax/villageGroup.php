<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Helper\PreferencesHelper;
use Model\VillageGroupModel;

/**
 * Village groups as the reference client calls them: POST village-group {name, color}
 * creates, PUT village-group/<id> {name, color} edits and DELETE village-group/<id> removes.
 */
final class villageGroup extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to manage village groups.', 403);
            return;
        }
        if ($this->session->isSitter()) {
            $this->error('As a sitter you do not have access to village groups.', 403);
            return;
        }

        $uid = $this->session->getPlayerId();
        $groups = new VillageGroupModel();
        $id = $_GET['id'] ?? null;
        $body = $this->body();
        $name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
        $color = is_string($body['color'] ?? null) ? trim($body['color']) : 'grey';

        try {
            switch ($this->method()) {
                case 'POST':
                    $this->response['id'] = $groups->create($uid, $name, $color);
                    return;
                case 'PUT':
                    $groups->update($uid, $this->groupId($id), $name, $color);
                    return;
                case 'DELETE':
                    $groupId = $this->groupId($id);
                    $groups->delete($uid, $groupId);
                    PreferencesHelper::setPreference('villageListGroup' . $groupId . '_collapsed', null);
                    return;
                default:
                    $this->error('Unsupported village-group request.', 405);
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function groupId(mixed $value): int
    {
        if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value < 1) {
            throw new \RuntimeException('Invalid village group request.');
        }

        return (int)$value;
    }
}
