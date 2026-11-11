<?php

declare(strict_types=1);

namespace Core\Caching;

use Core\Database\DB;
use Model\ProfileModel;

class ProfileCache
{
    public const PROFILE_VILLAGES_CACHE_KEY = 'P:V:%s:%s';
    public const PROFILE_VILLAGES_BY_NAME_CACHE_KEY = 'P:V:SN:%s:%s';
    public const PROFILE_VILLAGES_EDIT_CACHE_KEY = 'P:V:E:%s:%s';
    public const PROFILE_MEDALS_CACHE_KEY = 'P:M:%s:%s';
    private const TTL = 300;

    private Caching $cache;
    private int $version;

    /**
     * @param int|string|null $version the profileCacheVersion column as read from a session or user row
     */
    public function __construct(int|string|null $version)
    {
        $this->version = (int) $version;
        $this->cache = Caching::getInstance();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProfileVillages(int $uid): array
    {
        $key = $this->getKey($uid, self::PROFILE_VILLAGES_CACHE_KEY);
        $cached = $this->cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $villages = (new ProfileModel())->getPlayerVillages($uid)->fetch_all(MYSQLI_ASSOC);
        $this->cache->set($key, $villages, self::TTL);

        return $villages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProfileVillagesSortedByName(int $uid): array
    {
        $key = $this->getKey($uid, self::PROFILE_VILLAGES_BY_NAME_CACHE_KEY);
        $cached = $this->cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $villages = (new ProfileModel())->getPlayerVillages($uid, true)->fetch_all(MYSQLI_ASSOC);
        $this->cache->set($key, $villages, self::TTL);

        return $villages;
    }

    public function getKey(int $uid, string $type): string
    {
        return sprintf($type, $uid, $this->version);
    }

    /** The cached medals HTML, or false when it is not cached. */
    public function getProfileMedals(int $uid): string|false
    {
        $cached = $this->cache->get($this->getKey($uid, self::PROFILE_MEDALS_CACHE_KEY));

        return is_string($cached) && $cached !== '' ? $cached : false;
    }

    public function setProfileMedals(int $uid, string $html): void
    {
        $this->cache->set($this->getKey($uid, self::PROFILE_MEDALS_CACHE_KEY), $html, self::TTL);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProfileEditVillages(int $uid): array
    {
        $cached = $this->cache->get($this->getKey($uid, self::PROFILE_VILLAGES_EDIT_CACHE_KEY));
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $villages = (new ProfileModel())->getPlayerVillages($uid)->fetch_all(MYSQLI_ASSOC);
        $this->setProfileEditVillages($uid, $villages);

        return $villages;
    }

    public function reValidateProfileVillages(int $uid): void
    {
        $this->cache->delete($this->getKey($uid, self::PROFILE_VILLAGES_CACHE_KEY));
        $this->cache->delete($this->getKey($uid, self::PROFILE_VILLAGES_BY_NAME_CACHE_KEY));
        $this->cache->delete($this->getKey($uid, self::PROFILE_VILLAGES_EDIT_CACHE_KEY));

        DB::getInstance()->query("UPDATE users SET profileCacheVersion=profileCacheVersion+1 WHERE id=$uid");
        $this->version++;
    }

    /**
     * @param list<array<string, mixed>> $villages
     */
    public function setProfileEditVillages(int $uid, array $villages): void
    {
        $this->cache->set($this->getKey($uid, self::PROFILE_VILLAGES_EDIT_CACHE_KEY), $villages, self::TTL);
    }
}
