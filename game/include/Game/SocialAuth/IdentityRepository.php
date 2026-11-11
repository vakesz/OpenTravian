<?php

declare(strict_types=1);

namespace Game\SocialAuth;

interface IdentityRepository
{
    /** @return int|null the linked user id for this provider subject */
    public function findUserId(Provider $provider, string $subject): ?int;

    /** True when any user or pending registration already owns this email address. */
    public function emailInUse(string $email): bool;

    /** True when the user already has an identity from this provider. */
    public function providerLinked(int $userId, Provider $provider): bool;

    public function link(int $userId, IdentityClaims $claims): bool;

    /** The stored email of an existing user, or null when the user does not exist. */
    public function userEmail(int $userId): ?string;

    /** @return array{id:int,name:string,password:string}|null null for missing, banned or deleting users */
    public function userForLogin(int $userId): ?array;
}
