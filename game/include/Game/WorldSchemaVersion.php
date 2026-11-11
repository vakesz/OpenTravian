<?php

namespace Game;

/**
 * Version of the complete schema shipped in database/world.sql.
 *
 * Fresh installations must start at this version rather than replaying every
 * historical migration against an already-current schema.
 */
final class WorldSchemaVersion
{
    public const CURRENT = 110;
}
