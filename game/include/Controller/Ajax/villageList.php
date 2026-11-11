<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Caching\ProfileCache;
use Model\VillageGroupModel;

/**
 * POST village-list {data: [{id, villages}]}: the sidebar order after a drag. A group
 * entry carries its village ids; an ungrouped village has no village list.
 */
final class villageList extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to sort villages.', 403);
            return;
        }
        if ($this->session->isSitter()) {
            $this->error('As a sitter you do not have access to village groups.', 403);
            return;
        }

        $entries = $this->entries($_POST['data'] ?? null);
        if ($entries === null) {
            $this->error('Invalid village list request.');
            return;
        }

        try {
            (new VillageGroupModel())->setLayout($this->session->getPlayerId(), $entries);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return;
        }
        (new ProfileCache($this->session->get('profileCacheVersion')))->reValidateProfileVillages($this->session->getPlayerId());
    }

    /** @return list<array{id: int, villages: list<int>|null}>|null */
    private function entries(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        $entries = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || !$this->isId($entry['id'] ?? null)) {
                return null;
            }
            $villages = $entry['villages'] ?? null;
            if (is_array($villages)) {
                foreach ($villages as $kid) {
                    if (!$this->isId($kid)) {
                        return null;
                    }
                }
                $villages = array_map('intval', array_values($villages));
            } else {
                $villages = null;
            }
            $entries[] = ['id' => (int)$entry['id'], 'villages' => $villages];
        }

        return $entries;
    }

    private function isId(mixed $value): bool
    {
        return is_scalar($value) && ctype_digit((string)$value) && (int)$value > 0;
    }
}
