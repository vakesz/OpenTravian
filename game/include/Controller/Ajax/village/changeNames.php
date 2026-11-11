<?php

declare(strict_types=1);

namespace Controller\Ajax\village;

use Controller\Ajax\AjaxBase;
use Core\Caching\ProfileCache;
use Core\Database\DB;
use Core\Helper\StringChecker;
use Core\Session;
use Game\Formulas;
use Game\NoticeHelper;
use Model\Quest;

/** Renames own villages: body {data: [{villageId, name}, ...]}, like the reference client. */
final class changeNames extends AjaxBase
{
    /** Travian.Constants.MAX_CHARS_VILLAGE_NAME */
    private const MAX_NAME_LENGTH = 20;

    public function dispatch(): void
    {
        $entries = $_POST['data'] ?? null;
        if (!is_array($entries) || $entries === []) {
            $this->error(T('map', 'please_resend_all_data'));
            return;
        }
        if (!Session::getInstance()->isValid()) {
            $this->error('You must be logged in to rename villages.', 403);
            return;
        }
        if (Session::getInstance()->isSitter()) {
            $this->error('As a sitter you don`t have access to this part.', 403);
            return;
        }

        $session = Session::getInstance();
        $db = DB::getInstance();
        $renamed = false;
        // The request body is stripped of tags before it reaches $_POST; the reference refuses a name that contains them.
        $rawPayload = json_decode((string)file_get_contents('php://input'), true);
        $rawEntries = is_array($rawPayload) && is_array($rawPayload['data'] ?? null) ? array_values($rawPayload['data']) : [];
        foreach (array_values($entries) as $index => $entry) {
            if (!is_array($entry)
                || !is_scalar($entry['villageId'] ?? null) || !ctype_digit((string)$entry['villageId'])
                || !is_string($entry['name'] ?? null)) {
                $this->error(T('map', 'please_resend_all_data'));
                return;
            }
            $did = (int)$entry['villageId'];
            $rawName = $rawEntries[$index]['name'] ?? $entry['name'];
            if (is_string($rawName) && $rawName !== strip_tags($rawName)) {
                $this->error(T('Dorf1', 'villageNameUnavailable'));
                return;
            }
            // Like the reference, a longer name is cut to the maximum instead of refused.
            $name = mb_substr(clean_string_from_white(strip_tags($entry['name'])), 0, self::MAX_NAME_LENGTH);
            $current = $db->run('SELECT name FROM vdata WHERE kid=? AND owner=?', [$did, $session->getPlayerId()]);
            $row = $current === false ? null : $current->get_result()->fetch_assoc();
            if ($row === null) {
                $this->error(T('Dorf1', 'villageNotFromUser'), 403);
                return;
            }
            if ($name === '') {
                $this->error(T('Global', 'errors.cant_be_empty'));
                return;
            }
            if ($name === $row['name']) {
                continue;
            }
            if (!StringChecker::isValidName($name)) {
                $this->error(T('inGame', 'Inadmissible name/message'));
                return;
            }
            Quest::getInstance()->setQuestBitwise('world', 2, Quest::QUEST_FINISHED);
            $xy = Formulas::kid2xy($did);
            NoticeHelper::addSurrounding(
                $xy['x'],
                $xy['y'],
                NoticeHelper::SURROUNDING_VILLAGE_RENAME,
                [$session->getPlayerId(), $session->getName(), $did, $row['name'], $name],
                time()
            );
            $stored = htmlspecialchars(str_replace("'", '`', $name), ENT_QUOTES);
            $db->run('UPDATE vdata SET name=? WHERE kid=?', [$stored, $did]);
            $renamed = true;
        }

        if ($renamed) {
            (new ProfileCache($session->get('profileCacheVersion')))->reValidateProfileVillages($session->getPlayerId());
        }
    }
}
