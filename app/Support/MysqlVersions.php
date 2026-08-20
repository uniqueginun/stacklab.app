<?php

namespace App\Support;

final class MysqlVersions
{
    public const DEFAULT = '8.4';

    /**
     * @var list<string>
     */
    public const ALL = ['8.4', '8.0'];

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function default(): string
    {
        return self::DEFAULT;
    }
}
