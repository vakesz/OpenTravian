<?php

declare(strict_types=1);

namespace Game;

/**
 * users.access values. Source: admin UsersCtrl::statusLabel() (0 Banned, 1 Player,
 * 2 Administrator, 3 Fake user) and RegisterModel::addUser() ("1: player, 3: bot").
 */
final class AccessLevel
{
    /** Banned (Session::banned()). The installer also gives Multihunter 0, which keeps it off leaderboards (StatisticsModel). */
    public const BANNED = 0;
    public const PLAYER = 1;
    /** Legacy administrator flag (Session::isReallyAdmin()); real administrators come from AdminAccounts. */
    public const ADMIN = 2;
    /** Generated fake player (FakeUserModel). */
    public const FAKE_USER = 3;
}
