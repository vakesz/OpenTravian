<?php

declare(strict_types=1);

namespace Game\Webhook;

/**
 * The Discord message of an event. The text is built from a fixed template and a whitelist of public values only
 * (the world name, a World Wonder level, an alliance's public name and tag): never a player name, id, e-mail,
 * coordinate or address. Discord mentions are switched off and markdown in the public names is escaped.
 */
final class WebhookMessage
{
    private const COLORS = [
        'worldStart' => 0x4CAF50,
        'worldEnd' => 0x9E9E9E,
        'artifactsReleased' => 0xFFC107,
        'wwPlansReleased' => 0xFF9800,
        'wwMilestone' => 0xF44336,
        'allianceFounded' => 0x2196F3,
        'roundWinner' => 0xE91E63,
        'test' => 0x607D8B,
    ];

    /**
     * @param array{world:string,level?:int,alliance?:array{name:string,tag:string}|null} $facts
     * @return array<string,mixed> the JSON body
     */
    public static function build(string $event, array $facts): array
    {
        $world = self::text($facts['world']);
        $alliance = isset($facts['alliance']) ? '[' . self::text($facts['alliance']['tag']) . '] ' . self::text($facts['alliance']['name']) : null;
        [$title, $description] = match ($event) {
            'worldStart' => ['World started', "$world is now open."],
            'worldEnd' => ['World ended', "$world has ended."],
            'artifactsReleased' => ['Artifacts released', "The artifacts of $world are now in play."],
            'wwPlansReleased' => ['World Wonder plans released', "The World Wonder plans of $world are now in play."],
            'wwMilestone' => ['World Wonder level ' . (int) ($facts['level'] ?? 0), 'A World Wonder in ' . $world . ' has reached level ' . (int) ($facts['level'] ?? 0) . '.'],
            'allianceFounded' => ['Alliance founded', ($alliance ?? 'An alliance') . " was founded in $world."],
            'roundWinner' => ['Round winner', $alliance !== null ? "$alliance won the round in $world." : "The round in $world has been won."],
            default => ['Webhook test', "The world-event webhook of $world is working."],
        };

        return [
            'username' => 'Travian',
            'allowed_mentions' => ['parse' => []],
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'color' => self::COLORS[$event] ?? self::COLORS['test'],
            ]],
        ];
    }

    /** One line of public text with markdown and mention characters made inert. */
    private static function text(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
        $value = mb_substr($value, 0, 60);

        return str_replace('@', "@\u{200B}", (string) preg_replace('/([\\\\*_`~|>\[\]()#:])/', '\\\\$1', $value));
    }
}
