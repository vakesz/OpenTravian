<?php

namespace Controller;

/**
 * Parses the independent hero inventory and face forms.
 *
 * A regular hero-page GET has no appearance fields. Only a complete valid form
 * is eligible for persistence, so an absent request cannot clear equipment.
 */
final class HeroInventoryUpdate
{
    private const INVENTORY_FIELDS = ['horse', 'helmet', 'body', 'leftHand', 'rightHand', 'shoes'];

    private const FACE_FIELDS = [
        'beard',
        'headProfile',
        'hairColor',
        'hairStyle',
        'ears',
        'eyebrow',
        'eyes',
        'nose',
        'mouth',
    ];

    /**
     * @return array{inventory: ?array<string, int>, face: ?array<string, int|string>}
     * @param array<mixed> $post
     */
    public static function fromPost(array $post): array
    {
        return [
            'inventory' => self::readUnsignedIntegers($post, self::INVENTORY_FIELDS, 4294967295),
            'face' => self::readFace($post),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string> $fields
     * @return ?array<string, int>
     */
    private static function readUnsignedIntegers(array $post, array $fields, int $maximum): ?array
    {
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $post) || !is_scalar($post[$field])) {
                return null;
            }

            $value = (string)$post[$field];
            if (!ctype_digit($value) || (int)$value > $maximum) {
                return null;
            }

            $values[$field] = (int)$value;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $post
     * @return ?array<string, int|string>
     */
    private static function readFace(array $post): ?array
    {
        $values = self::readUnsignedIntegers($post, self::FACE_FIELDS, 32767);
        if ($values === null || !isset($post['gender']) || !is_string($post['gender'])) {
            return null;
        }

        if (!in_array($post['gender'], ['male', 'female'], true)) {
            return null;
        }

        $values['gender'] = $post['gender'];
        return $values;
    }
}
