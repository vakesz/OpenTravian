<?php

declare(strict_types=1);

namespace Game;

/**
 * Markup helpers for the admin panel and the setup wizard. Both render the
 * self-hosted Gpack V2 components (formV2 labels, textButtonV2 buttons,
 * contentNavi subNavi tabs) so the two pages share one design language.
 *
 * Pure functions with no framework dependencies: setup.php runs before the
 * world is installed, so it must be able to require this file on its own.
 */
final class AdminUi
{
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     */
    public static function esc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string,scalar|null> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $html .= $value === true
                ? ' ' . $name
                : ' ' . $name . '="' . self::esc($value) . '"';
        }
        return $html;
    }

    /**
     * Gpack framed button. A non-empty "href" renders an anchor, otherwise a
     * button. Colours follow the in-game pairing: green primary, grey secondary.
     *
     * @param array<string,scalar|null> $attributes
     */
    public static function button(string $label, string $color = 'green', array $attributes = []): string
    {
        $color = $color === 'grey' ? 'grey' : 'green';
        $class = 'textButtonV2 buttonFramed rectangle withText ' . $color;
        if (isset($attributes['class'])) {
            $class .= ' ' . $attributes['class'];
            unset($attributes['class']);
        }
        if (isset($attributes['href'])) {
            return '<a class="' . self::esc($class) . '"' . self::attributes($attributes) . '><div>'
                . self::esc($label) . '</div></a>';
        }
        $attributes += ['type' => 'submit'];
        return '<button class="' . self::esc($class) . '"' . self::attributes($attributes) . '><div>'
            . self::esc($label) . '</div></button>';
    }

    /**
     * contentNavi subNavi tab strip.
     *
     * @param list<array{label:string,href:string,active?:bool,attributes?:array<string,scalar|null>}> $tabs
     */
    public static function tabs(array $tabs, string $ariaLabel): string
    {
        $html = '<nav class="contentNavi subNavi" aria-label="' . self::esc($ariaLabel) . '">'
            . '<button type="button" class="scrollFrom" disabled="disabled" aria-hidden="true"></button>'
            . '<div class="scrollingContainer">';
        foreach ($tabs as $tab) {
            $active = !empty($tab['active']);
            $attributes = ($tab['attributes'] ?? []) + [
                'href' => $tab['href'],
                'class' => 'tabItem ' . ($active ? 'active' : 'normal'),
                'aria-current' => $active ? 'page' : null,
            ];
            $html .= '<div class="content"><a' . self::attributes($attributes) . '>'
                . self::esc($tab['label']) . '</a></div>';
        }
        return $html . '</div><button type="button" class="scrollTo" disabled="disabled" aria-hidden="true"></button></nav>';
    }

    /**
     * formV2 text-like input with a fixed label above it.
     *
     * @param array<string,scalar|null> $attributes extra attributes for the input element
     * @param string $value
     */
    public static function input(string $name, string $label, $value = '', array $attributes = [], string $hint = ''): string
    {
        $attributes += ['type' => 'text', 'name' => $name, 'id' => 'f_' . $name, 'value' => (string)$value];
        return '<label class="input fixed"><input' . self::attributes($attributes) . '>'
            . '<div class="label">' . self::esc($label) . '</div>'
            . self::hint($hint) . '</label>';
    }

    /**
     * formV2 password input with the Gpack visibility toggle wired by admin.js-free markup:
     * the wizard and the panel both attach the same small toggle script.
     *
     * @param array<string, string> $attributes
     */
    public static function secret(string $name, string $label, array $attributes = [], string $hint = ''): string
    {
        $attributes += ['type' => 'password', 'name' => $name, 'id' => 'f_' . $name, 'autocomplete' => 'new-password'];
        return '<label class="input fixed secret"><input' . self::attributes($attributes) . '>'
            . '<div class="label">' . self::esc($label) . '</div>'
            . '<button type="button" class="adminSecretToggle" aria-label="Show value" aria-pressed="false" data-target="'
            . self::esc($attributes['id']) . '"></button>'
            . self::hint($hint) . '</label>';
    }

    /**
     * @param array<scalar,string> $options value => label
     * @param array<string,scalar|null> $attributes extra attributes for the select element
     * @param string $selected
     */
    public static function select(string $name, string $label, array $options, $selected = '', array $attributes = [], string $hint = ''): string
    {
        $attributes += ['name' => $name, 'id' => 'f_' . $name, 'size' => 1];
        $html = '<label class="select fixed"><select' . self::attributes($attributes) . '>';
        foreach ($options as $value => $text) {
            $html .= '<option value="' . self::esc($value) . '"'
                . ((string)$value === (string)$selected ? ' selected' : '') . '>' . self::esc($text) . '</option>';
        }
        return $html . '</select><div class="label">' . self::esc($label) . '</div>' . self::hint($hint) . '</label>';
    }

    /**
     * @param array<string, int|string> $attributes
     */
    public static function textarea(string $name, string $label, string $value = '', array $attributes = [], string $hint = ''): string
    {
        $attributes += ['name' => $name, 'id' => 'f_' . $name, 'rows' => 6];
        return '<label class="textarea fixed"><textarea' . self::attributes($attributes) . '>' . self::esc($value) . '</textarea>'
            . '<div class="label">' . self::esc($label) . '</div>' . self::hint($hint) . '</label>';
    }

    /**
     * formV2 checkbox.
     *
     * @param array<mixed> $attributes
     */
    public static function checkbox(string $name, string $label, bool $checked = false, string $value = '1', array $attributes = []): string
    {
        $attributes += ['type' => 'checkbox', 'name' => $name, 'value' => $value, 'checked' => $checked];
        return '<label class="checkbox"><input' . self::attributes($attributes) . '>'
            . '<svg viewBox="0 0 18 18" class="checkbox" aria-hidden="true">'
            . '<path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path>'
            . '<rect class="indeterminate" width="10" height="2" x="4" y="8"></rect>'
            . '<path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path></svg>'
            . '<div class="label">' . self::esc($label) . '</div></label>';
    }

    /** Framed content box with a title row. */
    public static function box(string $title, string $body, string $class = ''): string
    {
        return '<section class="adminBox' . ($class !== '' ? ' ' . self::esc($class) : '') . '">'
            . ($title !== '' ? '<h2 class="adminBoxTitle">' . self::esc($title) . '</h2>' : '')
            . '<div class="adminBoxBody">' . $body . '</div></section>';
    }

    /** @param string $tone one of error, warning, success, info */
    public static function notice(string $message, string $tone = 'info'): string
    {
        $tone = in_array($tone, ['error', 'warning', 'success', 'info'], true) ? $tone : 'info';
        $role = $tone === 'error' ? 'alert' : 'status';
        return '<div class="adminNotice ' . $tone . '" role="' . $role . '">' . self::esc($message) . '</div>';
    }

    /**
     * A page: title, a one-sentence lead and the body. Every panel page opens the same way.
     */
    public static function page(string $title, string $lead, string $body): string
    {
        return '<h1>' . self::esc($title) . '</h1>' . ($lead !== '' ? '<p class="adminLead">' . $lead . '</p>' : '') . $body;
    }

    /** What a list shows when it has no rows. */
    public static function empty(string $message): string
    {
        return '<p class="adminMuted adminEmpty">' . self::esc($message) . '</p>';
    }

    /**
     * Markup that only the Advanced view shows (the CSS hides it in Simple through the
     * data-mode of the page or form). Nothing is removed, so a direct URL still works.
     */
    public static function advanced(string $html): string
    {
        return '<div class="advancedOnly">' . $html . '</div>';
    }

    /** Markup that only the Simple view shows. */
    public static function simple(string $html): string
    {
        return '<div class="simpleOnly">' . $html . '</div>';
    }

    /**
     * A button that asks before it submits (the panel's script shows the message).
     *
     * @param array<string,scalar|null> $attributes
     */
    public static function confirmButton(string $label, string $message, string $color = 'grey', array $attributes = []): string
    {
        return self::button($label, $color, ['data-confirm' => $message] + $attributes);
    }

    /**
     * The Simple / Advanced switch, plain links that set the remembered view.
     *
     * @param string $mode the current mode, simple or advanced
     * @param string $url the page to come back to, without a view parameter
     */
    public static function viewToggle(string $mode, string $url): string
    {
        $glue = str_contains($url, '?') ? '&' : '?';
        $link = static fn (string $view, string $label): string => '<a href="' . self::esc($url . $glue . 'view=' . $view) . '"'
            . ($mode === $view ? ' aria-current="true"' : '') . '>' . self::esc($label) . '</a>';

        return '<nav class="adminViewToggle" aria-label="View">' . $link('simple', 'Simple') . $link('advanced', 'Advanced') . '</nav>';
    }

    private static function hint(string $hint): string
    {
        return $hint === '' ? '' : '<div class="adminHint">' . $hint . '</div>';
    }
}
