<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Core\Session;
use InvalidArgumentException;
use mysqli_result;

/**
 * Alliance forum data. Ids reach these methods straight from requests, so every one is cast to int before it is
 * interpolated into SQL and all text is bound. Rows and result sets are read over the text protocol so columns stay
 * strings for the callers.
 */
class ForumModel
{
    private function positiveInteger(mixed $value): int
    {
        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @return int[]
     */
    private function positiveIntegerList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = $this->positiveInteger($value);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function haveIVoted(int|string $topicId, int|string $uid): bool
    {
        $topicId = (int) $topicId;
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_vote WHERE topicId=$topicId AND uid=$uid") > 0;
    }

    public function voteOptionExists(int|string $topicId, int|string $id): bool
    {
        $topicId = (int) $topicId;
        $id = (int) $id;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_options WHERE topicId=$topicId AND id=$id") > 0;
    }

    public function vote(int|string $uid, int|string $topicId, int|string $optionId): void
    {
        DB::getInstance()->run('INSERT INTO forum_vote (uid, topicId, value) VALUES (?, ?, ?)', [(int) $uid, (int) $topicId, (int) $optionId]);
    }

    public function lockTopic(int|string $id): void
    {
        $this->setTopicFlag($id, 'close', 1);
    }

    public function unlockTopic(int|string $id): void
    {
        $this->setTopicFlag($id, 'close', 0);
    }

    public function stickTopic(int|string $id): void
    {
        $this->setTopicFlag($id, 'stick', 1);
    }

    public function unstickTopic(int|string $id): void
    {
        $this->setTopicFlag($id, 'stick', 0);
    }

    /** @param 'close'|'stick' $flag */
    private function setTopicFlag(int|string $id, string $flag, int $value): void
    {
        DB::getInstance()->run("UPDATE forum_topic SET $flag=? WHERE id=?", [$value, (int) $id]);
    }

    /**
     * @param int|string $id post id
     * @param int|string $uid player id who edits the post
     * @param mixed $post new post text
     */
    public function editPost(int|string $id, int|string $uid, mixed $post): false|null
    {
        $id = $this->positiveInteger($id);
        $uid = $this->positiveInteger($uid);
        if ($id === 0 || $uid === 0 || !is_string($post)) {
            return false;
        }
        $db = DB::getInstance();
        $db->run('INSERT INTO forum_edit (uid, postId, time) VALUES (?, ?, ?)', [$uid, $id, time()]);
        $db->run('UPDATE forum_post SET post=? WHERE id=? AND deleted=0', [$post, $id]);

        return null;
    }

    /**
     * How many times a post was edited and by whom last.
     *
     * @return array<string, mixed>|null
     */
    public function getPostEdits(int|string $postId): ?array
    {
        $postId = (int) $postId;

        return $this->fetchRow("SELECT COUNT(id) AS `count`, uid, time FROM forum_edit WHERE postId=$postId ORDER BY time DESC LIMIT 1");
    }

    public function deletePost(int|string $pid): void
    {
        $pid = (int) $pid;
        $db = DB::getInstance();
        $db->query("UPDATE forum_post SET deleted=1 WHERE id=$pid");
        $db->query("DELETE FROM forum_edit WHERE postId=$pid");
    }

    /**
     * @param int|string $uid user id of the first post in the topic
     * @param int|string $aid alliance the topic belongs to
     * @param int|string $forumId forum the topic goes to
     * @param mixed $thread short description of the topic
     * @param mixed $text text of the first post
     * @param mixed $umfrage_thema survey name; when not empty the topic carries a survey
     * @param mixed $options survey options
     * @param mixed $ends time the survey ends
     *
     * @return int|false the topic id
     */
    public function addTopic(int|string $uid, int|string $aid, int|string $forumId, mixed $thread, mixed $text, mixed $umfrage_thema, mixed $options, mixed $ends): int|false
    {
        $db = DB::getInstance();
        $uid = $this->positiveInteger($uid);
        $aid = $this->positiveInteger($aid);
        $forumId = $this->positiveInteger($forumId);
        if ($uid === 0 || $aid === 0 || $forumId === 0 || !is_string($thread) || !is_string($text) || !is_string($umfrage_thema)) {
            return false;
        }
        $ends = is_scalar($ends) ? (int) $ends : 0;
        //getting forum for area option.(if it's closed forum this topic is also closed)
        $area = $this->fetchRow("SELECT area FROM forum_forums WHERE id=$forumId AND aid=$aid")['area'] ?? null;
        if ($area === null) {
            return false;
        }
        $closed = (int) $area === 3 || (int) $area === 0 ? 1 : 0;
        $db->run(
            'INSERT INTO forum_topic (forumId, thread, close, SurveyStartTime, Survey, end_time) VALUES (?, ?, ?, ?, ?, ?)',
            [$forumId, $thread, $closed, time(), $umfrage_thema, $ends]
        );
        $topicId = (int) $db->lastInsertId();
        //adding options on the survey.
        if (!empty($umfrage_thema) && is_array($options)) {
            foreach ($options as $option) {
                if (empty($option)) {
                    continue;
                }
                $this->addOption($topicId, $option);
            }
        }
        $isAdmin = Session::getInstance()->hasAlliancePermission(AllianceModel::MANAGE_FORUM);
        $this->addPost($uid, $aid, $forumId, $topicId, $text, Session::getInstance()->isSitter(), $isAdmin);

        return $topicId;
    }

    /**
     * Adds an option to a topic's survey.
     */
    public function addOption(int|string $topicId, mixed $desc): false|null
    {
        $topicId = $this->positiveInteger($topicId);
        if ($topicId === 0 || !is_string($desc)) {
            return false;
        }
        DB::getInstance()->run('INSERT INTO forum_options (topicId, option_desc) VALUES (?, ?)', [$topicId, $desc]);

        return null;
    }

    /**
     * @param int|string $uid player who sends the post
     * @param int|string $aid alliance of the current session
     * @param int|string $forumId forum the post belongs to
     * @param int|string $topicId topic the post goes to
     * @param string $post post message
     * @param bool $isAdmin admins may post in closed topics of their own alliance
     */
    public function addPost(int|string $uid, int|string $aid, int|string $forumId, int|string $topicId, string $post, bool $isSitter = false, bool $isAdmin = false): false|null
    {
        $uid = (int) $uid;
        $aid = (int) $aid;
        $forumId = (int) $forumId;
        $topicId = (int) $topicId;
        if (!$aid) {
            return false;
        } //how you will access here i don't know but you can't.
        //checking for closed forum.
        $forum = $this->fetchRow("SELECT aid, area, sitter FROM forum_forums WHERE id=$forumId");
        if ($forum === null) {
            return false;
        } //no forum found!
        if ($isSitter && (int) $forum['sitter'] === 0) {
            return false;
        } //this forum is not allowed for sitter to post in.
        $isAdmin = $isAdmin && (int) $forum['aid'] === $aid; //check whether it's own alliance or not! maybe we are admin for other alliances!
        if ((int) $forum['area'] === 1 && (int) $forum['aid'] !== $aid && !$this->isForumShownForAlliance($forumId, (int) $forum['aid'], $aid)) {
            //conf forum.
            return false;
        }
        $topic = $this->fetchRow("SELECT close FROM forum_topic WHERE id=$topicId");
        if ($topic === null) {
            return false;
        } //topic not found.
        //maybe the topic is closed.
        if (!$isAdmin && $topic['close'] && (!in_array((int) $forum['area'], [1, 3], true) || !$this->isTopicOpenedForUser($forumId, $uid))) {
            return false;
        }
        //all done ....
        DB::getInstance()->run(
            'INSERT INTO forum_post (aid, uid, forumId, topicId, post, time, deleted) VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$aid, $uid, $forumId, $topicId, $post, time()]
        );

        return null;
    }

    /**
     * Whether alliance $aid2 may see the confederacies forum of alliance $aid1 (0: look the owner up from the forum).
     */
    public function isForumShownForAlliance(int|string $forumId, int|string $aid1, int|string $aid2): bool
    {
        $forumId = (int) $forumId;
        $aid1 = (int) $aid1;
        $aid2 = (int) $aid2;
        $db = DB::getInstance();
        if ($aid1 === 0) {
            //get alliance ID from forum.
            $aid1 = (int) $db->fetchScalar("SELECT aid FROM forum_forums WHERE id=$forumId");
            if (!$aid1) {
                return false;
            }
        }
        //check for alliances.
        if (0 < (int) $db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE ((aid1=$aid1 AND aid2=$aid2) OR (aid1=$aid2 AND aid2=$aid1)) AND accepted=1")) {
            return true;
        }

        return 0 < (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_open_alliances WHERE forumId=$forumId AND aid=$aid2");
    }

    /**
     * Whether the player is on the forum's list of players allowed to post in its closed topics.
     */
    public function isTopicOpenedForUser(int|string $forumId, int|string $uid): bool
    {
        $forumId = (int) $forumId;
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_open_players WHERE forumId=$forumId AND uid=$uid") > 0;
    }

    public function getTopicVotesCount(int|string $topicId, int|string $value): int
    {
        $topicId = (int) $topicId;
        $value = (int) $value;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_vote WHERE topicId=$topicId AND value=$value");
    }

    public function getTotalVotes(int|string $topicId): int
    {
        $topicId = (int) $topicId;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_vote WHERE topicId=$topicId");
    }

    public function getTotalPostsCount(int|string $topicId): int
    {
        $topicId = (int) $topicId;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_post WHERE topicId=$topicId AND deleted=0");
    }

    public function getPlayerPostsCount(int|string $aid, int|string $uid): int
    {
        $aid = (int) $aid;
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_post WHERE aid=$aid AND uid=$uid");
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getPost(int|string $id): array|false
    {
        $id = (int) $id;

        return $this->fetchRow("SELECT * FROM forum_post WHERE id=$id AND deleted=0") ?? false;
    }

    public function getPosts(int|string $topicId, int $page): mysqli_result|bool
    {
        $topicId = (int) $topicId;

        return DB::getInstance()->query("SELECT * FROM forum_post WHERE topicId=$topicId AND deleted=0 LIMIT " . (($page - 1) * 10) . ', 10');
    }

    public function hasForumNewEntries(int|string $forumId): bool
    {
        return $this->getForumLastPost($forumId, true) !== false;
    }

    /**
     * The newest post of a forum (only its time with $justTime).
     *
     * @return array<string, mixed>|false
     */
    public function getForumLastPost(int|string $forumId, bool $justTime = false): array|false
    {
        $forumId = (int) $forumId;

        return $this->fetchRow('SELECT ' . ($justTime ? 'time' : '*') . " FROM forum_post WHERE forumId=$forumId AND deleted=0 ORDER BY time DESC LIMIT 1") ?? false;
    }

    /** Survey options of a topic. */
    public function getOptions(int|string $topicId): mysqli_result|bool
    {
        $topicId = (int) $topicId;

        return DB::getInstance()->query("SELECT * FROM forum_options WHERE topicId=$topicId");
    }

    /** The public forums of an alliance. */
    public function getPublicForum(int|string $aid): mysqli_result|bool
    {
        $aid = (int) $aid;

        return DB::getInstance()->query("SELECT * FROM forum_forums WHERE aid=$aid AND area=0 ORDER BY pos ASC");
    }

    public function getForumTopicsCount(int|string $forumId): int
    {
        $forumId = (int) $forumId;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM forum_topic WHERE forumId=$forumId");
    }

    /**
     * The player's name; "[?]" markup (or null with $NA) when the player does not exist.
     */
    public function getPlayerName(int|string $uid, bool $NA = false): ?string
    {
        $uid = (int) $uid;
        $name = DB::getInstance()->fetchScalar("SELECT name FROM users WHERE id=$uid");
        if (!$name) {
            return $NA ? null : '<span class="none2">[?]</span>';
        }

        return (string) $name;
    }

    /**
     * @param string $cols comma-separated column list (callers pass literals)
     * @return array<string, mixed>
     */
    public function getPlayerRow(int|string $uid, string $cols = '*'): array
    {
        if (!preg_match('/^\*$|^[A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*$/', $cols)) {
            throw new InvalidArgumentException('Invalid column list.');
        }
        $uid = (int) $uid;

        return $this->fetchRow("SELECT $cols FROM users WHERE id=$uid") ?? [];
    }

    /**
     * The newest post of a topic (only its time with $justTime).
     *
     * @return array<string, mixed>|false
     */
    public function getTopicLastPost(int|string $topicId, bool $justTime = false): array|false
    {
        $topicId = (int) $topicId;

        return $this->fetchRow('SELECT ' . ($justTime ? 'time' : '*') . " FROM forum_post WHERE topicId=$topicId AND deleted=0 ORDER BY time DESC LIMIT 1") ?? false;
    }

    /** All topics of a forum. */
    public function getForumTopics(int|string $forumId): mysqli_result|bool
    {
        $forumId = (int) $forumId;

        return DB::getInstance()->query("SELECT * FROM forum_topic WHERE forumId=$forumId");
    }

    /**
     * The confederacies forums of alliance $aid that alliance $aid2 (the current session's) may see.
     *
     * @return list<array<string, mixed>>
     */
    public function getConfederaciesForums(int|string $aid, int|string $aid2): array
    {
        $aid = (int) $aid;
        $aid2 = (int) $aid2;
        $assoc = [];
        $find = DB::getInstance()->query("SELECT * FROM forum_forums WHERE aid=$aid AND area=1");
        while ($find && ($row = $find->fetch_assoc())) {
            if ($aid2 !== $aid && (!$aid2 || !$this->isForumShownForAlliance($row['id'], $row['aid'], $aid2))) {
                continue;
            }
            $assoc[] = $row;
        }

        return $assoc;
    }

    /**
     * @param mixed $row topic row
     */
    public function editTopic(mixed $row, int|string $aid, mixed $name, int|string $fid): false|null
    {
        if (!is_array($row) || !is_string($name)) {
            return false;
        }
        $db = DB::getInstance();
        $aid = $this->positiveInteger($aid);
        $fid = $this->positiveInteger($fid);
        if ($aid === 0 || $fid === 0) {
            return false;
        }
        if ($fid !== (int) $row['forumId'] && !(int) $db->fetchScalar("SELECT COUNT(id) FROM forum_forums WHERE aid=$aid AND id=$fid")) {
            $fid = (int) $row['forumId'];
        }
        $db->run('UPDATE forum_topic SET thread=?, forumId=? WHERE id=?', [$name, $fid, (int) $row['id']]);

        return null;
    }

    public function AllianceHasAnyForums(int|string $aid1, int|string $aid2): bool
    {
        $aid1 = (int) $aid1;
        $aid2 = (int) $aid2;
        $db = DB::getInstance();
        //always we have public forum.
        $total = (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_forums WHERE aid=$aid1 AND area=0");
        if ($aid2 > 0 && $aid1 === $aid2) {
            //other my alliance forums.
            $total += (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_forums WHERE aid=$aid1 AND (area>0)");
        } else {
            $find = $db->query("SELECT id, aid FROM forum_forums WHERE aid=$aid1 AND area=1");
            while ($find && ($row = $find->fetch_assoc())) {
                if ($aid2 && $this->isForumShownForAlliance($row['id'], $aid1, $aid2)) {
                    ++$total;
                }
            }
        }

        return $total > 0;
    }

    /** The forums only the alliance's own members see. */
    public function getAllianceForum(int|string $aid): mysqli_result|bool
    {
        $aid = (int) $aid;

        return DB::getInstance()->query("SELECT * FROM forum_forums WHERE aid=$aid AND area=2 ORDER BY pos ASC");
    }

    /** The closed forums only allowed players can post in. */
    public function getClosedForum(int|string $aid): mysqli_result|bool
    {
        $aid = (int) $aid;

        return DB::getInstance()->query("SELECT * FROM forum_forums WHERE aid=$aid AND area=3");
    }

    /**
     * @return int the new forum id, 0 when the input was refused
     */
    public function addForum(int|string $aid, mixed $name, mixed $desc, mixed $area, mixed $sitter, mixed $allys_by_id, mixed $allys_by_name, mixed $users_by_id, mixed $users_by_name): int
    {
        $db = DB::getInstance();
        $aid = $this->positiveInteger($aid);
        $area = is_scalar($area) ? (int) $area : 0;
        $sitter = is_scalar($sitter) ? (int) $sitter : 0;
        if ($aid === 0 || !is_string($name) || !is_string($desc)) {
            return 0;
        }
        // The form permits 20-byte names and 38-byte descriptions.  Keep the
        // server-side boundary in sync so a value accepted by the native form
        // is not silently discarded after the post/redirect cycle.
        if (strlen($name) > 20) {
            return 0;
        }
        if (strlen($desc) > 38) {
            return 0;
        }

        $pos = (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_forums WHERE aid=$aid AND area=$area");
        $db->run(
            'INSERT INTO forum_forums (aid, name, forum_desc, area, sitter, pos) VALUES (?, ?, ?, ?, ?, ?)',
            [$aid, $name, $desc, $area, $sitter, $pos]
        );
        $forumId = (int) $db->lastInsertId();
        if ($area === 1) {
            //conf forum
            $this->addAllowedAlliances($forumId, $allys_by_id, $allys_by_name);
        } elseif ($area === 3) {
            //closed forum
            $this->addAllowedUsers($forumId, $users_by_id, $users_by_name);
        }

        return $forumId;
    }

    private function addAllowedAlliances(int $forumId, mixed $byId, mixed $byName): void
    {
        foreach (is_array($byId) ? $byId : [] as $allianceId) {
            $this->addAllowedAlliance($forumId, $allianceId);
        }
        foreach (is_array($byName) ? $byName : [] as $allianceName) {
            $this->addAllowedAlliance($forumId, $allianceName, true);
        }
    }

    private function addAllowedUsers(int $forumId, mixed $byId, mixed $byName): void
    {
        foreach (is_array($byId) ? $byId : [] as $userId) {
            $this->addAllowedUser($forumId, $userId);
        }
        foreach (is_array($byName) ? $byName : [] as $userName) {
            $this->addAllowedUser($forumId, $userName, true);
        }
    }

    /**
     * Lets an alliance (by id, or by tag with $tag) see a confederacies forum.
     */
    private function addAllowedAlliance(int $forumId, mixed $allianceId, bool $tag = false): void
    {
        if ($forumId === 0) {
            return;
        }
        $db = DB::getInstance();
        if ($tag) {
            if (!is_string($allianceId) || $allianceId === '') {
                return;
            }
            $allianceId = (int) $db->fetchScalar('SELECT id FROM alidata WHERE tag=? LIMIT 1', [$allianceId]);
        } else {
            $allianceId = $this->positiveInteger($allianceId);
        }
        if ($allianceId === 0
            || 0 < (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_open_alliances WHERE aid=$allianceId AND forumId=$forumId")
            || !(int) $db->fetchScalar("SELECT COUNT(id) FROM alidata WHERE id=$allianceId")) {
            return;
        }
        $db->query("INSERT INTO forum_open_alliances (forumId, aid) VALUES ($forumId, $allianceId)");
    }

    /**
     * Lets a player (by id, or by name with $name) post in a closed forum.
     */
    private function addAllowedUser(int $forumId, mixed $uid, bool $name = false): void
    {
        if ($forumId === 0) {
            return;
        }
        $db = DB::getInstance();
        if ($name) {
            if (!is_string($uid) || $uid === '') {
                return;
            }
            $uid = (int) $db->fetchScalar('SELECT id FROM users WHERE name=? LIMIT 1', [$uid]);
        } else {
            $uid = $this->positiveInteger($uid);
        }
        if ($uid === 0
            || (int) $db->fetchScalar("SELECT COUNT(id) FROM forum_open_players WHERE uid=$uid AND forumId=$forumId")
            || !(int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE id=$uid")) {
            return;
        }
        $db->query("INSERT INTO forum_open_players (uid, forumId) VALUES ($uid, $forumId)");
    }

    public function editForum(int|string $forumId, mixed $name, mixed $desc, mixed $sitter, mixed $bnds, mixed $allys_by_id, mixed $allys_by_name, mixed $users, mixed $users_by_id, mixed $users_by_name): false|null
    {
        $db = DB::getInstance();
        $forumId = $this->positiveInteger($forumId);
        if ($forumId === 0 || !is_string($name) || !is_string($desc)) {
            return false;
        }
        $sitter = is_scalar($sitter) ? (int) $sitter : 0;
        $users = $this->positiveIntegerList($users);
        $bnds = $this->positiveIntegerList($bnds);
        $db->query("DELETE FROM forum_open_players WHERE forumId=$forumId " . ($users ? ' AND uid NOT IN(' . implode(',', $users) . ')' : ''));
        $db->query("DELETE FROM forum_open_alliances WHERE forumId=$forumId " . ($bnds ? ' AND aid NOT IN(' . implode(',', $bnds) . ')' : ''));
        $this->addAllowedAlliances($forumId, $allys_by_id, $allys_by_name);
        $this->addAllowedUsers($forumId, $users_by_id, $users_by_name);
        $db->run('UPDATE forum_forums SET name=?, forum_desc=?, sitter=? WHERE id=?', [$name, $desc, $sitter, $forumId]);

        return null;
    }

    /**
     * Deletes a forum with all its topics and permissions.
     */
    public function deleteForum(int|string $forumId, int|string $aid): void
    {
        $forumId = (int) $forumId;
        $db = DB::getInstance();
        $db->query("DELETE FROM forum_open_players WHERE forumId=$forumId");
        $db->query("DELETE FROM forum_open_alliances WHERE forumId=$forumId");
        $topics = $db->query("SELECT id FROM forum_topic WHERE forumId=$forumId");
        while ($topics && ($row = $topics->fetch_assoc())) {
            $this->deleteTopic($row['id'], $aid);
        }
        $db->query("DELETE FROM forum_forums WHERE id=$forumId");
    }

    /**
     * Deletes a topic with its survey, votes, posts and edits.
     *
     * @return int|false the forum the topic belonged to, false when the topic is unknown or not the alliance's
     */
    public function deleteTopic(int|string $id, int|string $aid): int|false
    {
        $id = (int) $id;
        $db = DB::getInstance();
        //getting forumId from topic to validate alliances.
        $topic = $this->fetchRow("SELECT forumId FROM forum_topic WHERE id=$id");
        if ($topic === null) {
            return false;
        } //no topic found!
        $forumId = (int) $topic['forumId'];
        //getting parent forum alliance Id
        $forum = $this->fetchRow("SELECT aid FROM forum_forums WHERE id=$forumId");
        if ($forum === null || (int) $aid !== (int) $forum['aid']) {
            return false;
        } //no forum found, or the alliances don't match!
        $db->query("DELETE FROM forum_options WHERE topicId=$id");
        $db->query("DELETE FROM forum_vote WHERE topicId=$id");
        //getting posts to delete edits.
        $find = $db->query("SELECT id FROM forum_post WHERE topicId=$id");
        while ($find && ($row = $find->fetch_assoc())) {
            $db->query('DELETE FROM forum_edit WHERE postId=' . (int) $row['id']);
        }
        $db->query("UPDATE forum_post SET deleted=1 WHERE topicId=$id");
        $db->query("DELETE FROM forum_topic WHERE id=$id");

        return $forumId;
    }

    public function getTopicAnswersCount(int|string $topicId): int
    {
        $topicId = (int) $topicId;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id)-1 FROM forum_post WHERE topicId=$topicId");
    }

    /**
     * @return array<string, mixed>|false false when the forum does not exist or is hidden from alliance $aid2
     */
    public function getForum(int|string $aid, int|string $aid2, int|string $forumId): array|false
    {
        $aid = (int) $aid;
        $aid2 = (int) $aid2;
        $forumId = (int) $forumId;
        $forum = $this->fetchRow("SELECT * FROM forum_forums WHERE id=$forumId");
        if ($forum === null) {
            return false;
        }
        if ($aid !== $aid2 && (int) $forum['area'] === 1 && !$this->isForumShownForAlliance($forumId, $aid, $aid2)) {
            return false;
        }

        return $forum;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getTopic(int|string $id): array|false
    {
        $id = (int) $id;

        return $this->fetchRow("SELECT * FROM forum_topic WHERE id=$id") ?? false;
    }

    public function getAllowedAlliancesForForum(int|string $forumId): mysqli_result|bool
    {
        $forumId = (int) $forumId;

        return DB::getInstance()->query("SELECT * FROM forum_open_alliances WHERE forumId=$forumId");
    }

    public function getAllowedPlayersForForum(int|string $forumId): mysqli_result|bool
    {
        $forumId = (int) $forumId;

        return DB::getInstance()->query("SELECT * FROM forum_open_players WHERE forumId=$forumId");
    }

    /**
     * @return array<string, mixed> name and tag, both null when the alliance does not exist
     */
    public function getAllianceNameAndTag(int|string $aid): array
    {
        $aid = (int) $aid;

        return $this->fetchRow("SELECT name, tag FROM alidata WHERE id=$aid") ?? ['name' => null, 'tag' => null];
    }

    /**
     * Moves a forum one position up (+1) or down (-1) within its area.
     *
     * @param int|string $aid alliance of the current session
     */
    public function changeForumPosition(int|string $aid, int|string $forumId, int|string $pos): void
    {
        $aid = (int) $aid;
        $forumId = (int) $forumId;
        $pos = (int) $pos;
        $forum = $this->fetchRow("SELECT pos, area FROM forum_forums WHERE aid=$aid AND id=$forumId");
        if ($forum === null) {
            return;
        }
        $db = DB::getInstance();
        $cur_pos = (int) $forum['pos'];
        $area = (int) $forum['area'];
        $step = $pos === 1 ? '+1' : '-1';
        $db->query("UPDATE forum_forums SET pos=pos-1 WHERE pos>$cur_pos AND area=$area");
        $db->query("UPDATE forum_forums SET pos=pos+1 WHERE pos<$cur_pos AND area=$area");
        $db->query("UPDATE forum_forums SET pos=IF(pos$step>0, pos$step, 0) WHERE id=$forumId");
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
