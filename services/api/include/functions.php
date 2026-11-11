<?php

declare(strict_types=1);

use Core\Translator;

function T(string $name): string
{
    return Translator::translate($name);
}

function get_random_string(int $length): string
{
    if ($length < 1) {
        return '';
    }

    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    $maxIndex = strlen($alphabet) - 1;
    for ($index = 0; $index < $length; ++$index) {
        $result .= $alphabet[random_int(0, $maxIndex)];
    }

    return $result;
}

function generate_guid(bool $trim = true): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $trim ? $uuid : '{' . $uuid . '}';
}
