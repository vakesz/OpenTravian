<?php

namespace Game\Hero;

class HeroFace
{
    /** @var array<string, array<string, array<string, bool|int>>> */
    public $faceData = [
        "male"      => [
            "headProfile"  => [
                "start" => 50, "end" => 64, "none" => -1,
            ], "hairColor" => [
                "start" => 1500, "end" => 1511, "none" => -1,
            ], "hairStyle" => [
                "start" => 1000, "end" => 1014, "none" => 1499, "storageNone" => 5, "storageSkipNone" => true,
            ], "ears"      => [
                "start" => 2000, "end" => 2014, "none" => -1,
            ], "eyebrow"   => [
                "start" => 7000, "end" => 7014, "none" => -1,
            ], "eyes"      => [
                "start" => 4000, "end" => 4014, "none" => -1,
            ], "nose"      => [
                "start" => 3000, "end" => 3014, "none" => -1,
            ], "mouth"     => [
                "start" => 6000, "end" => 6014, "none" => -1,
            ], "beard"     => [
                "start" => 5000, "end" => 5014, "none" => 5999, "storageNone" => 5, "storageSkipNone" => true,
            ], "scar"      => [
                "start" => 8001, "end" => 8015, "none" => 8000, "storageNone" => 0, "storageOffset" => 1,
            ], "tattoo"    => [
                "start" => 9001, "end" => 9015, "none" => 9000, "storageNone" => 0, "storageOffset" => 1,
            ],
        ], "female" => [
            "headProfile"  => [
                "start" => 51, "end" => 65, "none" => -1,
            ], "hairColor" => ["start" => 1500, "end" => 1511, "none" => -1],
            "hairStyle"    => ["start" => 1000, "end" => 1014, "none" => -1],
            "ears"         => ["start" => 2000, "end" => 2014, "none" => -1],
            "eyebrow"      => ["start" => 7000, "end" => 7014, "none" => -1],
            "eyes"         => ["start" => 4000, "end" => 4014, "none" => -1],
            "nose"         => ["start" => 3000, "end" => 3014, "none" => -1],
            "mouth"        => ["start" => 6000, "end" => 6014, "none" => -1],
            "beard"        => ["start" => 0, "end" => 0, "none" => -1],
            "scar"         => ["start" => 8001, "end" => 8015, "none" => 8000, "storageNone" => 0, "storageOffset" => 1],
            "tattoo"       => ["start" => 9001, "end" => 9015, "none" => 9000, "storageNone" => 0, "storageOffset" => 1],
        ],
    ];

    /**
     * @param string $gender
     * @return array<string, mixed>
     */
    public function getRandomHeroFace($gender = "male")
    {
        $result = [];
        if (is_numeric($gender)) {
            $gender = ['male', 'female'];
            $gender = $gender[mt_rand(0, 1)];
        }
        foreach ($this->faceData[$gender] as $key => $value) {
            $result[$key] = mt_rand($value['start'], $value['end']);
            if ($value['none'] > 0 && mt_rand(1, 20) == 5) {
                $result[$key] = $value['none'];
            }
        }
        $result['gender'] = $gender;
        $result['skinColor'] = self::SKIN_COLORS[array_rand(self::SKIN_COLORS)];
        $result['eyeColor'] = self::EYE_COLORS[array_rand(self::EYE_COLORS)];

        return $this->normalizeEmptySlots($result);
    }

    public const SKIN_COLORS = ['skin1', 'skin2', 'skin3', 'skin4'];
    public const EYE_COLORS = [
        'black', 'brown', 'grey', 'green', 'blue', 'blind',
        'yellow', 'red', 'gold', 'silver', 'greenblue', 'brownblue',
    ];
    public const HAIR_COLORS = [
        'black', 'brown', 'red', 'brightbrown', 'blonde', 'grey',
        'white', 'violet', 'signalred', 'blondepink', 'blondered', 'redblack',
    ];

    /** Appearance attribute => reference category class, in editor order. */
    public const CATEGORIES = [
        'headProfile' => 'jaw',
        'eyes' => 'eyes',
        'eyebrow' => 'brows',
        'nose' => 'nose',
        'mouth' => 'mouth',
        'ears' => 'ears',
        'hairStyle' => 'hair',
        'beard' => 'beard',
        'scar' => 'scar',
        'tattoo' => 'tattoo',
    ];

    /**
     * The reference option tiles per gender and category, in its fixed display order, with each
     * option's gold price ("slot:price"; "empty" is the tile without that feature). Options the
     * hero cannot use yet follow the usable ones, keeping this order.
     */
    private const OPTION_TABLE = [
        'male' => [
            'jaw' => '1:0 3:0 2:0 6:0 4:0 5:0 7:10 11:10 9:10 10:10 8:10 12:10 13:40 14:40 15:40',
            'eyes' => '1:0 5:0 3:0 6:0 2:0 4:0 11:10 12:10 13:10 15:10 7:10 9:10 8:40 10:40 14:40',
            'brows' => '1:0 9:0 10:0 5:0 2:0 4:0 3:10 6:10 8:10 13:10 14:10 15:10 7:40 11:40 12:40',
            'nose' => '1:0 2:0 4:0 6:0 7:0 13:0 5:10 8:10 9:10 11:10 12:40 15:10 3:40 10:40 14:40',
            'mouth' => '10:0 4:0 5:0 9:0 1:0 12:0 6:10 14:10 8:10 13:10 11:10 7:10 3:40 2:40 15:40',
            'ears' => '1:0 5:0 3:0 4:0 2:0 6:0 7:10 8:10 9:10 10:10 11:10 13:10 12:40 14:40 15:40',
            'hair' => '1:0 2:0 5:0 6:0 15:0 10:0 4:30 8:30 11:30 12:30 13:30 14:30 3:120 7:120 9:120',
            'beard' => 'empty:0 9:0 6:0 14:0 1:0 2:0 8:30 7:30 10:30 12:30 15:30 3:30 11:120 4:120 5:120',
            'scar' => 'empty:0 2:0 1:0 8:20 9:20 4:20 14:20 11:20 12:20 13:20 10:20 15:20 3:80 5:80 7:80',
            'tattoo' => 'empty:0 7:0 9:0 11:20 4:20 13:20 3:20 12:20 5:20 1:20 2:20 14:20 6:80 10:80 15:80',
        ],
        'female' => [
            'jaw' => '6:0 4:0 3:0 5:0 1:0 2:0 8:10 9:10 10:10 11:10 12:10 7:10 13:40 14:40 15:40',
            'eyes' => '1:0 2:0 3:0 4:0 5:0 6:0 7:10 15:10 11:10 8:10 13:10 12:10 10:40 9:40 14:40',
            'brows' => '2:0 1:0 11:0 4:0 5:0 7:0 3:10 8:10 9:10 12:10 13:10 15:10 6:40 10:40 14:40',
            'nose' => '1:0 2:0 3:0 5:0 8:0 15:0 14:10 9:10 10:10 11:10 13:40 4:10 7:40 12:40 6:40',
            'mouth' => '1:0 3:0 4:0 6:0 11:0 2:0 5:10 8:10 10:10 13:10 15:10 7:10 9:40 12:40 14:40',
            'ears' => '1:0 2:0 3:0 4:0 5:0 7:0 11:10 10:10 14:10 6:10 8:10 13:10 9:40 12:40 15:40',
            'hair' => '1:0 2:0 5:0 15:0 8:0 4:0 7:30 13:30 11:30 9:30 12:30 14:30 3:120 6:120 10:120',
            'scar' => 'empty:0 2:0 1:0 8:20 9:20 4:20 14:20 11:20 12:20 13:20 10:20 15:20 3:80 5:80 7:80',
            'tattoo' => 'empty:0 7:0 9:0 11:20 4:20 13:20 3:20 12:20 5:20 1:20 2:20 14:20 6:80 10:80 15:80',
        ],
    ];

    /** Range slots that have no artwork: the reference shows the Empty tile in their place. */
    private const EMPTY_SLOTS = ['beard' => 13, 'scar' => 6, 'tattoo' => 8];

    /** @return list<array{slot: int|string, price: int}> */
    private function optionTable(string $gender, string $category): array
    {
        $table = [];
        foreach (explode(' ', self::OPTION_TABLE[$gender][$category] ?? '') as $entry) {
            if ($entry === '') {
                continue;
            }
            [$slot, $price] = explode(':', $entry);
            $table[] = ['slot' => $slot === 'empty' ? 'empty' : (int)$slot, 'price' => (int)$price];
        }

        return $table;
    }

    /** The persisted value of an option tile: a slot inside the range, or the category's none value. */
    private function optionValue(string $gender, string $attribute, int|string $slot): int
    {
        $field = $this->faceData[$gender][$attribute];

        return $slot === 'empty' ? (int)$field['none'] : (int)$field['start'] + $slot - 1;
    }

    /**
     * Categories the editor offers for a gender (women have no beard).
     *
     * @return array<string, string> attribute => category class
     */
    public function categoriesFor(string $gender): array
    {
        $categories = self::CATEGORIES;
        if ($gender !== 'male') {
            unset($categories['beard']);
        }

        return $categories;
    }

    /** The reference `.categories` tab strip; the first tab is selected. */
    public function getCategoriesHtml(string $gender): string
    {
        $html = '<div class="categories">';
        $first = true;
        foreach ($this->categoriesFor($gender) as $category) {
            $title = htmlspecialchars((string)T('HeroFace', ucfirst(['jaw' => 'head', 'brows' => 'brows'][$category] ?? $category)), ENT_QUOTES, 'UTF-8');
            $html .= '<div class="category ' . $category . ($first ? ' selected' : '')
                . '" data-category="' . $category . '" title="' . $title . '"><i></i></div>';
            $first = false;
        }

        return $html . '</div>';
    }

    /**
     * The reference `.page` list, one page per category. Previews are drawn
     * onto the option canvases by hero-v2-appearance.js from the local Hero V2
     * atlases.
     *
     * @param mixed $gender
     * @param array<string, mixed> $selectedAttributes
     * @param array<string, bool> $unlockedOptions
     */
    public function getAllHeroFaceAttributesHtml($gender, array $selectedAttributes = [], array $unlockedOptions = []): string
    {
        $icons = self::icons();
        $html = '';
        $page = 0;
        foreach ($this->categoriesFor($gender) as $attribute => $category) {
            $field = $this->faceData[$gender][$attribute];
            $html .= '<div class="page page' . $page . ($page === 0 ? ' active' : '') . '"><div class="optionsWrapper">';
            $options = [];
            foreach ($this->optionTable($gender, $category) as $position => $entry) {
                $slot = $entry['slot'];
                $value = $this->optionValue($gender, $attribute, $slot);
                $selected = isset($selectedAttributes[$attribute]) && (int)$selectedAttributes[$attribute] === $value;
                $owned = isset($unlockedOptions[$attribute . ':' . $value]);
                // The option the hero wears is never shown as locked.
                $locked = !$selected && $entry['price'] > 0 && !$owned;
                $options[] = ['slot' => $slot, 'position' => $position, 'selected' => $selected, 'price' => $entry['price'], 'locked' => $locked, 'paid' => $entry['price'] > 0];
            }
            // Like the reference, the options the hero can use come first and the locked ones follow; owned
            // paid options sit after the free ones, each group in the reference order.
            usort($options, static fn (array $a, array $b): int => $a['locked'] <=> $b['locked']
                ?: $a['paid'] <=> $b['paid']
                ?: $a['position'] <=> $b['position']);
            foreach ($options as $option) {
                $name = $option['slot'] === 'empty' ? 'Empty' : (string)$option['slot'];
                $html .= '<div class="option option' . $name . ($option['selected'] ? ' selected' : '')
                    . '" data-option="' . ($option['slot'] === 'empty' ? 'empty' : $name) . '"><div class="optionPreview ' . $gender . ' ' . $category
                    . ' option' . $name . '"><canvas width="875" height="1574"></canvas></div>'
                    . ($option['selected'] ? '<div class="selectedIndicator"></div>' : '')
                    . ($option['locked'] ? str_replace('%AMOUNT%', (string)$option['price'], $icons['lockedGold']) : '')
                    . '</div>';
            }
            $html .= '</div></div>';
            ++$page;
        }

        return $html;
    }

    /** @return array<string, string> verbatim reference icon markup */
    public static function icons(): array
    {
        static $icons = null;

        if ($icons === null) {
            $icons = require dirname(__DIR__, 2) . '/resources/Templates/hero/appearanceIcons.php';
            // The reference buttons carry these tooltips.
            $titles = [
                'toggleGender' => 'Change gender', 'shuffleConfig' => 'Shuffle', 'toggleZoom' => 'Zoom out', 'resetChanges' => 'Revert changes',
            ];
            foreach ($icons as $key => $markup) {
                $icons[$key] = preg_replace_callback(
                    '/<button class="([^"]*\b(toggleGender|shuffleConfig|toggleZoom|resetChanges)\b[^"]*)"/',
                    static fn (array $match): string => $match[0] . ' title="' . htmlspecialchars((string)T('HeroFace', $titles[$match[2]]), ENT_QUOTES, 'UTF-8') . '"'
                        . ($match[2] === 'toggleZoom' ? ' data-title-zoom-in="' . htmlspecialchars((string)T('HeroFace', 'Zoom in'), ENT_QUOTES, 'UTF-8') . '" data-title-zoom-out="' . htmlspecialchars((string)T('HeroFace', 'Zoom out'), ENT_QUOTES, 'UTF-8') . '"' : ''),
                    $markup
                );
            }
        }

        return $icons;
    }

    /**
     * @param array<mixed, mixed>|array<mixed> $attributes
     * @return mixed
     */
    public function encodeAttribute($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (!isset($this->faceData[$attributes['gender']][$key])) {
                continue;
            }
            $k = $this->faceData[$attributes['gender']][$key];
            $value = (int)$value;
            if (($k['none'] ?? -1) > 0 && $value === (int)($k['storageNone'] ?? 5)) {
                $value = (int)$k['none'];
            } else {
                if (($k['storageSkipNone'] ?? false) && $value > (int)$k['storageNone']) {
                    --$value;
                }
                $value -= (int)($k['storageOffset'] ?? 0);
                $value += (int)$k['start'];
            }
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param mixed $attributes
     * @return mixed
     */
    public function decodeAttribute($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (!isset($this->faceData[$attributes['gender']][$key])) {
                continue;
            }
            $k = $this->faceData[$attributes['gender']][$key];
            $value = (int)$value;
            if (($k['none'] ?? -1) > 0 && $value === (int)$k['none']) {
                $value = (int)($k['storageNone'] ?? 5);
            } else {
                $value -= (int)$k['start'];
                $value += (int)($k['storageOffset'] ?? 0);
                if (($k['storageSkipNone'] ?? false) && $value >= (int)$k['storageNone']) {
                    ++$value;
                }
            }
            $attributes[$key] = $value;
        }

        return $attributes;
    }
    /**
     * @param int $id
     * @param string $gender
     * @param string $type
     */
    public function isValid($id, $gender, $type): bool
    {
        if (!isset($this->faceData[$gender][$type])) {
            return false;
        }
        $k = $this->faceData[$gender][$type];
        if (($id >= $k['start'] && $id <= $k['end']) || ($id == $k['none'] && $k['none'] > 0)) {
            return true;
        }
        return false;
    }

    public function isValidColor(string $color, string $type): bool
    {
        return match ($type) {
            'skinColor' => in_array($color, self::SKIN_COLORS, true),
            'eyeColor' => in_array($color, self::EYE_COLORS, true),
            default => false,
        };
    }
    /**
     * Face assets use persisted indices rather than the V2 picker values.
     * Centralize the mapping so HUD, editor and body previews stay identical.
     */
    public static function hairColorName(int $hairColor): string
    {
        return match ($hairColor) {
            0 => 'black',
            1 => 'brown',
            2 => 'brightbrown',
            3 => 'blonde',
            4 => 'red',
            5 => 'grey',
            6 => 'white',
            7 => 'violet',
            8 => 'signalred',
            9 => 'blondepink',
            10 => 'blondered',
            11 => 'redblack',
            default => 'brown',
        };
    }

    public function getAppearanceOptionCost(string $attribute, int $option, string $gender): int
    {
        $field = $this->faceData[$gender][$attribute] ?? null;
        $category = self::CATEGORIES[$attribute] ?? null;
        if ($field === null || $category === null || $option === (int)($field['none'] ?? -1)) {
            return 0;
        }
        foreach ($this->optionTable($gender, $category) as $entry) {
            if ($entry['slot'] !== 'empty' && $this->optionValue($gender, $attribute, $entry['slot']) === $option) {
                return $entry['price'];
            }
        }

        return 0;
    }

    /** Every persisted face attribute the editor submits. */
    public const ATTRIBUTE_FIELDS = [
        'headProfile', 'hairColor', 'hairStyle', 'ears', 'eyebrow', 'eyes', 'nose', 'mouth', 'beard', 'scar', 'tattoo',
    ];

    /**
     * The premium options a save or unlock request still has to pay for. An option the hero already
     * wears (same gender and value in the stored face) is never charged again.
     *
     * @param array<string, mixed> $attributes       requested picker values, with the gender
     * @param array<string, mixed> $storedAttributes the stored face as picker values, with the gender
     * @param array<string, bool>  $unlockedOptions  "attribute:value" keys the player owns
     * @return list<array{0: string, 1: int, 2: int}> attribute, value and gold price
     */
    public function lockedSelections(array $attributes, array $storedAttributes, array $unlockedOptions): array
    {
        $locked = [];
        $sameGender = ($storedAttributes['gender'] ?? null) === $attributes['gender'];
        foreach (self::ATTRIBUTE_FIELDS as $field) {
            $value = (int)$attributes[$field];
            if ($sameGender && isset($storedAttributes[$field]) && (int)$storedAttributes[$field] === $value) {
                continue;
            }
            $cost = $this->getAppearanceOptionCost($field, $value, $attributes['gender']);
            if ($cost > 0 && !isset($unlockedOptions[$field . ':' . $value])) {
                $locked[] = [$field, $value, $cost];
            }
        }

        return $locked;
    }

    /**
     * Values on a slot without artwork stand for "none" (older heroes may carry them).
     *
     * @param array<string, mixed> $attributes picker values
     * @return array<string, mixed>
     */
    public function normalizeEmptySlots(array $attributes): array
    {
        $gender = ($attributes['gender'] ?? 'male') === 'female' ? 'female' : 'male';
        foreach (self::EMPTY_SLOTS as $attribute => $slot) {
            $field = $this->faceData[$gender][$attribute] ?? null;
            if ($field !== null && ($field['none'] ?? -1) > 0
                && isset($attributes[$attribute]) && (int)$attributes[$attribute] === (int)$field['start'] + $slot - 1) {
                $attributes[$attribute] = $field['none'];
            }
        }

        return $attributes;
    }
}
