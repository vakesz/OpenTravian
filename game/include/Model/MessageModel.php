<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Helper\Notification;
use Core\Session;
use Game\Mail\EventMail;
use mysqli_result;

use function clean_string_from_white;
use function profileUrl;

/**
 * Result-set and row readers interpolate only ints and use the text protocol on purpose: the message screens and
 * Ajax responses expect string column values, which prepared statements would turn into native ints. Everything that
 * carries player-supplied text goes through prepared statements.
 */
class MessageModel
{
    public function getFriendList(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM friendlist WHERE uid=$uid OR to_uid=$uid");
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getMessageAdmin(int $id): array|false
    {
        return $this->fetchRow("SELECT * FROM mdata WHERE id=$id") ?? false;
    }

    public function getPlayerLastLoginTime(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT last_login_time FROM users WHERE id=$uid");
    }

    public function ignorePlayer(int $uid, int $ignore_id): bool
    {
        if ($ignore_id <= 2 || $uid === $ignore_id) {
            return false;
        }
        if ($this->isPlayerIgnored($uid, $ignore_id) || !$this->checkIgnoreListLimit($uid)) {
            return false;
        }
        DB::getInstance()->run('INSERT INTO ignoreList (uid, ignore_id) VALUES (?, ?)', [$uid, $ignore_id]);
        Caching::getInstance()->delete("ignoreList{$uid}");

        return true;
    }

    public function isPlayerIgnored(int $uid, int|string $ignore_id): bool
    {
        $ignore_id = (int) $ignore_id;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM ignoreList WHERE uid=$uid AND ignore_id=$ignore_id") > 0;
    }

    public function checkIgnoreListLimit(int $uid, int $max = 20): bool
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM ignoreList WHERE uid=$uid") < $max;
    }

    public function getIgnoreList(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM ignoreList WHERE uid=$uid");
    }

    public function unIgnorePlayer(int $uid, int $ignore_id): int
    {
        $db = DB::getInstance();
        $db->query("DELETE FROM ignoreList WHERE uid=$uid AND ignore_id=$ignore_id");
        if ($db->affectedRows()) {
            Caching::getInstance()->delete("ignoreList{$uid}");

            return 1;
        }

        return 0;
    }

    public function getPlayerName(int $uid): string
    {
        return (string) DB::getInstance()->fetchScalar("SELECT name FROM users WHERE id=$uid");
    }

    public function isPlayerInFriendList(int $id): bool
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM friendlist WHERE uid=$id OR to_uid=$id") > 0;
    }

    public function getTotalFriendListCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM friendlist WHERE uid=$uid OR to_uid=$uid");
    }

    public function deleteFriend(int|string $id, int $uid): void
    {
        $id = (int) $id;
        DB::getInstance()->query("DELETE FROM friendlist WHERE id=$id AND (uid=$uid OR to_uid=$uid)");
    }

    public function setMessageAsViewed(int $id, int $uid): void
    {
        DB::getInstance()->query("UPDATE mdata SET viewed=1 WHERE id=$id AND to_uid=$uid");
    }

    public function setMessageAsUnViewed(int $id, int $uid): void
    {
        DB::getInstance()->query("UPDATE mdata SET viewed=0 WHERE id=$id AND to_uid=$uid");
    }

    public function reportReceivedMessage(int $messageId, int $recipientId, int $reason, string $reasonName): bool
    {
        if ($messageId <= 0 || $recipientId <= 2 || $reason < 1 || $reason > 4) {
            return false;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }
        try {
            $message = $db->query(
                "SELECT uid FROM mdata WHERE id=$messageId AND to_uid=$recipientId AND delete_receiver=0" .
                    ' AND autoType=0 AND reported=0 FOR UPDATE'
            );
            $reportedUid = $message instanceof mysqli_result ? (int) ($message->fetch_assoc()['uid'] ?? 0) : 0;
            if ($reportedUid <= 2 || $reportedUid === $recipientId) {
                throw new \DomainException('The message cannot be reported.');
            }

            $db->query("UPDATE mdata SET reported=$reason WHERE id=$messageId AND to_uid=$recipientId AND reported=0");
            if ($db->affectedRows() !== 1) {
                throw new \RuntimeException('The message report could not be saved.');
            }

            $report = $db->run(
                'INSERT INTO messages_report (uid, reported_uid, message_id, type, time) VALUES (?, ?, ?, ?, ?)',
                [$recipientId, $reportedUid, $messageId, $reasonName, time()]
            );
            if ($report === false) {
                throw new \RuntimeException('The message report could not be saved.');
            }
            if (!$db->commit()) {
                throw new \RuntimeException('The message report could not be saved.');
            }
        } catch (\Throwable) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getMessage(int $id, int $uid): array|false
    {
        $query = $uid <= 2
            ? "SELECT * FROM mdata WHERE id=$id"
            : "SELECT * FROM mdata WHERE (uid=$uid OR to_uid=$uid) AND id=$id";

        return $this->fetchRow($query) ?? false;
    }

    public function acceptFriend(int|string $id, int $uid): void
    {
        $id = (int) $id;
        DB::getInstance()->query("UPDATE friendlist SET accepted=1 WHERE id=$id AND to_uid=$uid");
    }

    public function addFriendList(int $uid, int $to_uid): void
    {
        DB::getInstance()->run('INSERT INTO friendlist (uid, to_uid) VALUES (?, ?)', [$uid, $to_uid]);
    }

    public function getPlayerIdByName(mixed $name): int|false
    {
        $name = strip_tags(is_string($name) ? $name : '');
        $id = DB::getInstance()->fetchScalar('SELECT id FROM users WHERE name=?', [$name]);

        return $id === false ? false : (int) $id;
    }

    public function deleteInboxMessage(int $id, int $to_uid): void
    {
        // A message a player wrote to themselves is one message: on the reference deleting it from the inbox
        // takes it out of the sent list too.
        DB::getInstance()->query("UPDATE mdata SET delete_receiver=1, viewed=1, delete_sender=IF(uid=to_uid, 1, delete_sender) WHERE to_uid=$to_uid AND id=$id");
    }

    public function archiveMessage(int $id, int $to_uid): void
    {
        DB::getInstance()->query("UPDATE mdata SET archived=1, viewed=1 WHERE to_uid=$to_uid AND id=$id");
    }

    public function deleteSendBoxMessage(int $id, int $uid): void
    {
        DB::getInstance()->query("UPDATE mdata SET delete_sender=1 WHERE uid=$uid AND id=$id");
    }

    /** Stores the player's notepad text; returns the number of changed rows. */
    public function setNote(int $uid, string $text): int
    {
        $statement = DB::getInstance()->run('UPDATE users SET note=? WHERE id=?', [$text, $uid]);

        return $statement === false ? 0 : max(0, $statement->affected_rows);
    }

    public function recoverMessage(int $id, int $uid): void
    {
        DB::getInstance()->query("UPDATE mdata SET archived=0 WHERE to_uid=$uid AND id=$id");
    }

    /**
     * Spam fingerprint of a message. The subject and text are SQL-escaped before hashing, exactly as before, so
     * fingerprints of earlier messages keep matching.
     */
    private function getHash(string|int|float|null $subject, string|int|float|null $text): string
    {
        $db = DB::getInstance();
        // Automatic system messages intentionally have no subject.
        $subject = $db->real_escape_string(clean_string_from_white((string) $subject));
        $text = (string) filter_var($db->real_escape_string((string) $text));
        $maximum_text_size = strlen($text);
        $length = (int) ceil($maximum_text_size / 4);
        $offset = $maximum_text_size - $length * 2;

        return md5($subject . substr($text, $offset, $offset));
    }

    public function checkLastMessage(int $uid, bool $banned, int|false $to_uid, string|int|float|null $subject, string|int|float|null $text): bool
    {
        if ($to_uid !== false && $to_uid <= 2) {
            return true;
        }
        if ($uid <= 2) {
            return true;
        }
        $limit = $banned ? 5 : 15;
        $db = DB::getInstance();
        $since = time() - 10 * 60;
        $result = (int) $db->fetchScalar(
            "SELECT COUNT(id) FROM mdata WHERE uid=$uid AND to_uid>2 AND to_uid!=$uid AND isAlliance=0 AND time>=$since"
        );
        $alliance = (int) $db->fetchScalar(
            "SELECT COUNT(id) FROM mdata WHERE uid=$uid AND to_uid>2 AND to_uid!=$uid AND isAlliance=1 AND time>=$since"
        );
        if ($result >= $limit || $alliance >= ($limit - $result) * 20) {
            return false;
        }

        return 2 >= (int) $db->fetchScalar(
            'SELECT COUNT(id) FROM mdata WHERE uid=? AND md5_checksum=? AND time >= ?',
            [$uid, $this->getHash($subject, $text), time() - 600]
        );
    }

    public function getInbox(int $page, int $uid, int|bool $order = 0): mysqli_result|bool
    {
        return $this->pagedBox('delete_receiver=0 AND archived=0', $page, $uid, (bool) $order);
    }

    public function getSentBox(int $page, int $uid, int|bool $order = 0): mysqli_result|bool
    {
        $perPage = (int) Session::getInstance()->getReportsRecordsPerPage();

        return DB::getInstance()->query(
            "SELECT * FROM mdata WHERE delete_sender=0 AND archived=0 AND uid=$uid ORDER BY id " . ($order ? 'ASC' : 'DESC')
                . ' LIMIT ' . ($page - 1) * $perPage . ", $perPage"
        );
    }

    public function getSentBoxCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM mdata WHERE delete_sender=0 AND archived=0 AND uid=$uid");
    }

    public function getInboxCount(int $uid): int
    {
        return $this->boxCount('delete_receiver=0 AND archived=0', $uid);
    }

    public function getArchive(int $page, int $uid, int|bool $order = 0): mysqli_result|bool
    {
        return $this->pagedBox('delete_receiver=0 AND archived=1', $page, $uid, (bool) $order);
    }

    public function getArchiveCount(int $uid): int
    {
        return $this->boxCount('delete_receiver=0 AND archived=1', $uid);
    }

    /**
     * @param string $state fixed delete/archive filter of the box
     */
    private function pagedBox(string $state, int $page, int $uid, bool $ascending): mysqli_result|bool
    {
        $perPage = (int) Session::getInstance()->getReportsRecordsPerPage();

        return DB::getInstance()->query(
            "SELECT * FROM mdata WHERE $state AND to_uid=$uid " . $this->ignoreFilter($uid) . ' ORDER BY id ' . ($ascending ? 'ASC' : 'DESC')
                . ' LIMIT ' . ($page - 1) * $perPage . ", $perPage"
        );
    }

    private function boxCount(string $state, int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM mdata WHERE $state AND to_uid=$uid " . $this->ignoreFilter($uid));
    }

    /** Excludes messages from players on the player's ignore list. */
    private function ignoreFilter(int $uid): string
    {
        $ignored = [];
        $find = DB::getInstance()->query("SELECT ignore_id FROM ignoreList WHERE uid=$uid");
        while ($find && ($row = $find->fetch_assoc())) {
            $ignored[] = (int) $row['ignore_id'];
        }

        return $ignored === [] ? '' : 'AND uid NOT IN(' . implode(',', $ignored) . ')';
    }

    /**
     * @param int $autoType
     * AutoType:
     * 1 => Alliance Invitation Received
     * 2 => Alliance Invitation Revoked
     */
    public function sendMessage(int $uid, int $to_uid, string|int|float|null $subject, string|int|float|null $text, int $autoType = 0, bool $isAlliance = false): int|string
    {
        if ($to_uid === 0) {
            $to_uid = 2;
        }
        $md5_checksum = $this->getHash($subject, $text);
        $db = DB::getInstance();
        $subject = clean_string_from_white((string) $subject);
        $text = (string) filter_var((string) $text);
        $db->run(
            'INSERT INTO mdata (uid, to_uid, topic, message, md5_checksum, time, autoType, isAlliance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$uid, $to_uid, $subject, $text, $md5_checksum, time(), $autoType, $isAlliance ? 1 : 0]
        );
        $insertId = $db->lastInsertId();
        if ($autoType === 0 && !$isAlliance) {
            EventMail::newMessage($to_uid, $uid);
        }
        if ($uid > 2 && $to_uid <= 2) {
            $gameWorldUrl = rtrim((string) Config::getProperty('settings', 'gameWorldUrl'), '/');
            $link = '<a href="' . $gameWorldUrl . '/messages?id=' . $insertId . '">» Answer message</a>';
            $subjectLink = '<a href="' . $gameWorldUrl . '/messages?id=' . $insertId . '">' . htmlspecialchars($subject, ENT_QUOTES) . '</a>';
            $player = '<a href="' . $gameWorldUrl . profileUrl($uid) . '">' . htmlspecialchars($this->getPlayerName($uid), ENT_QUOTES) . '</a>';
            $world = '<a href="' . $gameWorldUrl . '">' . Config::getProperty('settings', 'worldId') . '</a>';
            Notification::RealTimeNotify(
                'Support needed',
                "You have a new message with subject of $subjectLink from player $player on world $world. <br /> $link"
            );
        }

        return $insertId;
    }

    /**
     * The welcome message every new player finds in the inbox, from Support. Like the reference's, it is
     * about days of beginner's protection and the Taskmaster, and carries the player's name.
     */
    public const AUTO_TYPE_WELCOME = 7;

    /** The subject of an answer: "RE: subject" first, then "RE^2: subject", "RE^3: subject" ... */
    public static function answerSubject(string $subject): string
    {
        if (preg_match('/^RE\^([0-9]+):/i', $subject, $count) === 1) {
            return (string) preg_replace('/^RE\^[0-9]+:/i', 'RE^' . ((int) $count[1] + 1) . ':', $subject);
        }
        if (preg_match('/^RE:/i', $subject) === 1) {
            return (string) preg_replace('/^RE:/i', 'RE^2:', $subject);
        }

        return 'RE: ' . $subject;
    }

    public function sendWelcomeMessage(int $uid, string $name): void
    {
        // Auto type 7: the subject and the text are built in the reader's language when the message is shown.
        $this->sendMessage(0, $uid, 'welcome', $name, self::AUTO_TYPE_WELCOME);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
