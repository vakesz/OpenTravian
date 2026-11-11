<?php

declare(strict_types=1);

namespace Game\Account;

use Core\Database\DBI;
use Game\Formulas;
use Game\SocialAuth\LinkedIdentities;

/**
 * The player's own data as one JSON document: account, villages, hero, reports, messages and linked social
 * accounts. Left out on purpose: the password hash and every token or code, two-factor and session data, IP
 * history, the provider subject of a social identity, the private keys of shared reports, and any other player's
 * data beyond the public names the game already showed (the other end of a message or report).
 */
final class DataExport
{
    public const FORMAT = 1;
    /** Newest rows kept per list, so one request cannot read a whole table. */
    public const LIST_LIMIT = 10000;

    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function build(int $userId): array
    {
        $db = DBI::getInstance();
        $user = $db->fetchSingleRow('SELECT * FROM users WHERE id=?', [$userId]);
        if (!is_array($user)) {
            return ['format' => self::FORMAT, 'account' => null];
        }

        return [
            'format' => self::FORMAT,
            'exportedAt' => gmdate('c'),
            'account' => self::account($user),
            'villages' => self::villages($userId),
            'hero' => self::hero($userId),
            'reports' => self::reports($userId),
            'messages' => self::messages($userId),
            'socialAccounts' => array_map(static fn (array $identity): array => [
                'provider' => $identity['provider']->value,
                'email' => $identity['email'],
                'emailVerified' => $identity['verified'],
                'linked' => gmdate('c', $identity['created']),
                'lastSignIn' => gmdate('c', $identity['lastLogin']),
            ], LinkedIdentities::forUser($userId)),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private static function account(array $user): array
    {
        $db = DBI::getInstance();
        $alliance = (int) $user['aid'] > 0 ? $db->fetchSingleRow('SELECT name, tag FROM alidata WHERE id=?', [(int) $user['aid']]) : false;
        $sitters = [];
        foreach ([1, 2] as $slot) {
            $sitter = (int) $user['sit' . $slot . 'Uid'];
            $name = $sitter > 0 ? $db->fetchScalar('SELECT name FROM users WHERE id=?', [$sitter], '') : '';
            if ($name !== '') {
                $sitters[] = ['name' => (string) $name, 'permissions' => (int) $user['sit' . $slot . 'Permissions']];
            }
        }
        $time = static fn (mixed $stamp): ?string => (int) $stamp > 0 ? gmdate('c', (int) $stamp) : null;

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'emailVerified' => (int) $user['email_verified'] === 1,
            'newsletter' => (int) $user['newsletter'] === 1,
            'tribe' => (int) $user['race'],
            'gender' => $user['gender'],
            'birthday' => $user['birthday'],
            'location' => $user['location'],
            'language' => $user['language'],
            'timezone' => $user['timezone'],
            'description' => [(string) $user['desc1'], (string) $user['desc2']],
            'notes' => (string) $user['note'],
            'registered' => $time($user['signupTime']),
            'lastSignIn' => $time($user['last_login_time']),
            'protectionUntil' => $time($user['protection']),
            'vacationUntil' => $time($user['vacationActiveTil']),
            'gold' => ['gift' => (int) $user['gift_gold'], 'bought' => (int) $user['bought_gold']],
            'silver' => (int) $user['silver'],
            'alliance' => is_array($alliance)
                ? ['name' => (string) $alliance['name'], 'tag' => (string) $alliance['tag'], 'role' => (string) $user['alliance_role_name'], 'joined' => $time($user['alliance_join_time'])]
                : null,
            'sitters' => $sitters,
            'settings' => [
                'display' => (string) $user['display'],
                'autoComplete' => (string) $user['autoComplete'],
                'reportFilters' => (string) $user['reportFilters'],
                'favouriteTabs' => (string) $user['favorTabs'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function villages(int $userId): array
    {
        $db = DBI::getInstance();
        $villages = [];
        foreach ($db->run('SELECT * FROM vdata WHERE owner=? ORDER BY kid', [$userId])->fetchAll(\PDO::FETCH_ASSOC) as $village) {
            $kid = (int) $village['kid'];
            $xy = Formulas::kid2xy($kid);
            $fields = $db->fetchSingleRow('SELECT * FROM fdata WHERE kid=?', [$kid]);
            $buildings = [];
            for ($slot = 1; is_array($fields) && $slot <= 40; $slot++) {
                if ((int) $fields['f' . $slot . 't'] > 0) {
                    $buildings[] = ['slot' => $slot, 'gid' => (int) $fields['f' . $slot . 't'], 'level' => (int) $fields['f' . $slot]];
                }
            }
            $units = $db->fetchSingleRow('SELECT * FROM units WHERE kid=?', [$kid]);
            $troops = [];
            for ($unit = 1; is_array($units) && $unit <= 11; $unit++) {
                if ((int) $units['u' . $unit] > 0) {
                    $troops['u' . $unit] = (int) $units['u' . $unit];
                }
            }
            $villages[] = [
                'id' => $kid,
                'name' => htmlspecialchars_decode((string) $village['name'], ENT_QUOTES),
                'x' => (int) $xy['x'],
                'y' => (int) $xy['y'],
                'capital' => (int) $village['capital'] === 1,
                'population' => (int) $village['pop'],
                'culturePoints' => (float) $village['cp'],
                'loyalty' => (float) $village['loyalty'],
                'founded' => (int) $village['created'] > 0 ? gmdate('c', (int) $village['created']) : null,
                'resources' => ['wood' => (float) $village['wood'], 'clay' => (float) $village['clay'], 'iron' => (float) $village['iron'], 'crop' => (float) $village['crop']],
                'buildings' => $buildings,
                'troops' => $troops,
            ];
        }

        return $villages;
    }

    /** @return array<string, mixed>|null */
    private static function hero(int $userId): ?array
    {
        $db = DBI::getInstance();
        $hero = $db->fetchSingleRow('SELECT * FROM hero WHERE uid=?', [$userId]);
        if (!is_array($hero)) {
            return null;
        }
        $inventory = $db->fetchSingleRow('SELECT * FROM inventory WHERE uid=?', [$userId]);

        return [
            'experience' => (int) $hero['exp'],
            'health' => (float) $hero['health'],
            'power' => (int) $hero['power'],
            'offenceBonus' => (int) $hero['offBonus'],
            'defenceBonus' => (int) $hero['defBonus'],
            'production' => (int) $hero['production'],
            'productionType' => (int) $hero['productionType'],
            'village' => (int) $hero['kid'],
            'equipment' => is_array($inventory)
                ? array_intersect_key($inventory, array_flip(['helmet', 'body', 'leftHand', 'rightHand', 'shoes', 'horse', 'bag']))
                : null,
        ];
    }

    /**
     * Metadata and loot only: when, what kind, between which villages, and what was carried off. The stored report
     * body shows the other side's troops, and a report's share key is a secret, so neither is exported.
     *
     * @return list<array<string, mixed>>
     */
    private static function reports(int $userId): array
    {
        $rows = DBI::getInstance()->run(
            'SELECT id, type, time, kid, to_kid, bounty, viewed, archive FROM ndata WHERE uid=? AND deleted=0 ORDER BY id DESC LIMIT ' . self::LIST_LIMIT,
            [$userId]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $reports = [];
        foreach ($rows as $row) {
            $bounty = array_map('intval', explode(',', (string) $row['bounty']));
            $from = Formulas::kid2xy((int) $row['kid']);
            $to = Formulas::kid2xy((int) $row['to_kid']);
            $reports[] = [
                'id' => (int) $row['id'],
                'type' => (int) $row['type'],
                'time' => gmdate('c', (int) $row['time']),
                'from' => ['x' => (int) $from['x'], 'y' => (int) $from['y']],
                'to' => ['x' => (int) $to['x'], 'y' => (int) $to['y']],
                'loot' => ['wood' => $bounty[0] ?? 0, 'clay' => $bounty[1] ?? 0, 'iron' => $bounty[2] ?? 0, 'crop' => $bounty[3] ?? 0],
                'read' => (int) $row['viewed'] === 1,
                'archived' => (int) $row['archive'] === 1,
            ];
        }

        return $reports;
    }

    /**
     * Messages the player sent and received, in full, with the other player's public name.
     *
     * @return list<array<string, mixed>>
     */
    private static function messages(int $userId): array
    {
        $rows = DBI::getInstance()->run(
            'SELECT m.id, m.uid, m.to_uid, m.topic, m.message, m.time, m.viewed, m.archived, m.isAlliance, s.name AS senderName, r.name AS recipientName'
            . ' FROM mdata m LEFT JOIN users s ON s.id = m.uid LEFT JOIN users r ON r.id = m.to_uid'
            . ' WHERE (m.uid = ? AND m.delete_sender = 0) OR (m.to_uid = ? AND m.delete_receiver = 0) ORDER BY m.id DESC LIMIT ' . self::LIST_LIMIT,
            [$userId, $userId]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $messages = [];
        foreach ($rows as $row) {
            $sent = (int) $row['uid'] === $userId;
            $messages[] = [
                'id' => (int) $row['id'],
                'direction' => $sent ? 'sent' : 'received',
                'from' => (string) ($row['senderName'] ?? ''),
                'to' => (string) ($row['recipientName'] ?? ''),
                'subject' => (string) $row['topic'],
                'body' => (string) $row['message'],
                'time' => gmdate('c', (int) $row['time']),
                'read' => (int) $row['viewed'] === 1,
                'archived' => (int) $row['archived'] === 1,
                'allianceMessage' => (int) $row['isAlliance'] === 1,
            ];
        }

        return $messages;
    }
}
