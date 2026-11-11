<?php

declare(strict_types=1);

namespace Game\Mail;

use function T;

/**
 * The kinds of email a player can opt into. The newsletter is `users.newsletter`; every other kind is a row in
 * email_preference. Each kind has its own rate limit: at most `limit` mails per `window` seconds per player, a
 * further event inside the window is dropped.
 */
enum EmailKind: string
{
    case Newsletter = 'newsletter';
    case Attack = 'attack';
    case Construction = 'construction';
    case Message = 'message';
    case VacationEnding = 'vacationEnding';
    case VacationStarted = 'vacationStarted';
    case SitterSignIn = 'sitterSignIn';

    /** @return array{0: int, 1: int} [mails, seconds] */
    public function rateLimit(): array
    {
        return match ($this) {
            self::Newsletter => [1, 12 * 3600],
            self::Attack => [1, 1800],
            self::Construction => [1, 3600],
            self::Message => [1, 900],
            self::VacationEnding, self::VacationStarted => [1, 86400],
            self::SitterSignIn => [1, 86400],
        };
    }

    /** The approved player wording of the kind (Mail catalogue). */
    public function label(): string
    {
        return (string) T('Mail', match ($this) {
            self::Newsletter => 'Newsletter',
            self::Attack => 'Incoming attacks',
            self::Construction => 'Construction finished',
            self::Message => 'New messages',
            self::VacationEnding => 'Vacation ending soon',
            self::VacationStarted => 'Vacation started',
            self::SitterSignIn => 'A sitter signed in',
        });
    }

    /**
     * The kinds a player switches on in Options, in display order.
     *
     * @return list<self>
     */
    public static function notifications(): array
    {
        return [self::Attack, self::Construction, self::Message, self::VacationEnding, self::VacationStarted, self::SitterSignIn];
    }
}
