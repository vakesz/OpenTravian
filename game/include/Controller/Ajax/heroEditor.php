<?php

namespace Controller\Ajax;

use Core\Database\DB;
use Core\Session;
use Game\GoldHelper;
use Game\Hero\HeroFace;
use Model\HeroFaceModel;

/** Handles the hero appearance editor's preview, randomize, gender, and save actions. */
class heroEditor extends AjaxBase
{
    private const COLOR_FIELDS = ['skinColor', 'eyeColor'];

    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!$session->isValid() || $session->isSitter() || $session->banned()) {
            $this->error('You cannot change the hero appearance from this session.', 403);
            return;
        }

        $action = $_POST['action'] ?? null;
        $rawAttributes = $_POST['attribs'] ?? null;
        if (!is_string($action) || !in_array($action, ['show', 'random', 'gender', 'save', 'unlock'], true)
            || !is_array($rawAttributes)) {
            $this->error('Invalid hero appearance request.');
            return;
        }

        $face = new HeroFace();
        if ($action === 'gender') {
            $gender = $rawAttributes['gender'] ?? null;
            if (!is_string($gender) || !in_array($gender, ['male', 'female'], true)) {
                $this->error('Invalid hero appearance request.');
                return;
            }
            $attributes = $face->getRandomHeroFace($gender);
        } elseif ($action === 'random') {
            $gender = $rawAttributes['gender'] ?? null;
            if (!is_string($gender) || !in_array($gender, ['male', 'female'], true)) {
                $this->error('Invalid hero appearance request.');
                return;
            }
            $attributes = $face->getRandomHeroFace($gender);
        } else {
            $attributes = $this->readAttributes($face, $rawAttributes);
            if ($attributes === null) {
                $this->error('Invalid hero appearance attributes.');
                return;
            }
        }

        $uid = (int)$session->getPlayerId();
        $appearanceModel = new HeroFaceModel();
        if ($action === 'unlock') {
            $db = DB::getInstance();
            if (!$db->begin_transaction()) {
                $this->error('Could not unlock the selected hero appearance options.', 503);
                return;
            }

            try {
                $unlockedOptions = $appearanceModel->getUnlockedOptions($uid, true);
                $toUnlock = $this->getLockedSelections($face, $attributes, $unlockedOptions);
                $cost = array_sum(array_column($toUnlock, 2));
                if ($cost > 0 && !GoldHelper::decreaseGold($uid, $cost, true)) {
                    $db->rollback();
                    $this->error('You do not have enough gold to unlock the selected appearance options.');
                    return;
                }
                if (!$appearanceModel->unlockOptions($uid, array_map(
                    static fn(array $option): array => [$option[0], $option[1]],
                    $toUnlock
                )) || !$db->commit()) {
                    if ($db->inTransaction()) {
                        $db->rollback();
                    }
                    $this->error('Could not unlock the selected hero appearance options.', 503);
                    return;
                }
            } catch (\Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $this->error('Could not unlock the selected hero appearance options.', 503);
                return;
            }
        }

        if ($action === 'save') {
            $db = DB::getInstance();
            if (!$db->begin_transaction()) {
                $this->error('Could not save the hero appearance.', 503);
                return;
            }

            try {
                if ($this->getLockedSelections($face, $attributes, $appearanceModel->getUnlockedOptions($uid, true)) !== []) {
                    $db->rollback();
                    $this->error('Unlock the selected hero appearance options before saving.');
                    return;
                }
                $storedAttributes = $face->decodeAttribute($attributes);
                $saved = (new HeroFaceModel())->modifyWholeHeroFace(
                    $uid,
                    $storedAttributes
                );
                if (!$saved || !$db->commit()) {
                    if ($db->inTransaction()) {
                        $db->rollback();
                    }
                    $this->error('Could not save the hero appearance.', 503);
                    return;
                }
            } catch (\Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $this->error('Could not save the hero appearance.', 503);
                return;
            }
        }

        $this->response['attributes'] = $attributes;
        if ($action === 'gender' || $action === 'random' || $action === 'unlock') {
            $this->response['attributesHtml'] = $face->getAllHeroFaceAttributesHtml(
                $attributes['gender'],
                $attributes,
                $appearanceModel->getUnlockedOptions($uid)
            );
        }
    }

    /**
     * @param array<mixed, mixed> $rawAttributes
     * @return array<mixed>
     */
    private function readAttributes(HeroFace $face, array $rawAttributes): ?array
    {
        $gender = $rawAttributes['gender'] ?? null;
        if (!is_string($gender) || !in_array($gender, ['male', 'female'], true)) {
            return null;
        }

        $attributes = ['gender' => $gender];
        foreach (HeroFace::ATTRIBUTE_FIELDS as $field) {
            $value = $rawAttributes[$field] ?? null;
            if (!is_scalar($value) || !ctype_digit((string)$value)) {
                return null;
            }
            $value = (int)$value;
            if (!$face->isValid($value, $gender, $field)) {
                return null;
            }
            $attributes[$field] = $value;
        }
        foreach (self::COLOR_FIELDS as $field) {
            $value = $rawAttributes[$field] ?? null;
            if (!is_string($value) || !$face->isValidColor($value, $field)) {
                return null;
            }
            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * Return exactly the premium attribute values the current request still
     * needs to own. The UI is advisory; the server owns both price and state,
     * including which options the hero already wears (read from the stored face).
     *
     * @return array<int, array{0: string, 1: int, 2: int}>
     * @param array<mixed> $attributes
     * @param array<string, bool> $unlockedOptions
     */
    private function getLockedSelections(HeroFace $face, array $attributes, array $unlockedOptions): array
    {
        $statement = DB::getInstance()->run('SELECT * FROM face WHERE uid=?', [(int)Session::getInstance()->getPlayerId()]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        $stored = is_array($row) ? $face->normalizeEmptySlots($face->encodeAttribute($row)) : [];

        return $face->lockedSelections($attributes, $stored, $unlockedOptions);
    }
}
