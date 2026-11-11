<?php

declare(strict_types=1);

use Game\ProfileLanguages;

$e = static fn(string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    "UTF-8"
);
$gender = (int) $vars["gender"];
$radioSvg =
    '<svg viewBox="0 0 20 20" class="radio">' .
    '<path d="M10,0A10,10,0,1,0,20,10,10,10,0,0,0,10,0Z"></path>' .
    '<rect class="indeterminate" width="10" height="5" x="5" y="7.5"></rect>' .
    '<circle class="checked" cx="10" cy="10" r="5"></circle>' .
    "</svg>";
$plusSvg =
    '<svg><svg viewBox="0 0 18 18" class="outline"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg><svg viewBox="0 0 18 18" class="icon"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg></svg>';
$languages = ProfileLanguages::normalize($vars['additionalLanguages'] ?? [], 'en-US');
$languageDefinitions = ProfileLanguages::definitions();
$descPlaceholder = (string) T("Profile", "descriptionPlaceholder");
$locationLabel = (string) T("Profile", "Your location");
$maxLen = (string) T("Profile", "Max length");
?>
<form class="formV2 ">

    <h3><?= $e((string) T("Profile", "Location")) ?></h3>
    <label class="input">
        <input maxlength="30" tabindex="1" type="text" name="location" value="<?= $e(
            (string) $vars["location"]
        ) ?>">
        <div class="label"><?= $e($locationLabel) ?></div>
    </label>

    <h3><?= $e((string) T("Profile", "Gender")) ?></h3>
    <div class="genderOptions">
        <?php
        $genderOptions = [
            0 => (string) T("Profile", "Do not specify"),
            2 => (string) T("Profile", "Female"),
            1 => (string) T("Profile", "Male"),
            3 => (string) T("Profile", "Other"),
        ];
        foreach ($genderOptions as $value => $label): ?>
            <label class="radio">
                <input tabindex="2" type="radio" name="gender"<?= $gender ===
$value
    ? " checked"
    : "" ?>>
                <?= $radioSvg ?>
                <div class="label"><?= $e($label) ?></div>
            </label>
        <?php endforeach;
        ?>
    </div>

    <h3><?= $e(
        (string) T("Profile", "Languages you speak or understand")
    ) ?></h3>
    <div id="editPlayerLanguages">
        <div class="languages">
            <?php foreach ($languages as $language): ?>
                <div class="language<?= count($languages) > 1 ? ' deletable' : ' ' ?>"><?php if (count($languages) > 1): ?><svg viewBox="0 0 86.29 100" class="delete"><path d="M75 100H11.32C8.9 93.78 7 89.76 6.89 82.9Q6.73 73 6.72 63c0-13.43.05-26.86.05-40.29H79.5c0 14-.22 28-.13 42C79.46 77.58 80.5 88.09 75 100zM25.8 86V32h-9v54zm22 0V32h-9v54zm13-54v54h9V32z"></path><path d="M0 4.66C10.8 4 21.12 7 29.64 0h27c8.51 7 18.87 4 29.66 4.66L85.76 18H.53z"></path></svg><?php endif; ?><i class="flag <?= $e($languageDefinitions[$language]['flag']) ?>"></i></div>
            <?php endforeach; ?>
        </div>
        <button class="textButtonV2 buttonFramed addLanguageButton rectangle withIcon green" type="button">
            <?= $plusSvg ?>
        </button>
    </div>

    <h3><?= $e((string) T("Profile", "Description")) ?></h3>
    <div class="bbEditor">
        <?= \resources\View\PHPBatchView::render('messages/bbToolbar', ['id' => 'profile', 'class' => 'noBackground']) ?>
    </div>
    <div class="description messages">
        <div class="description1">
            <label class="textarea fixed">
                <textarea name="description1" maxlength="5000" tabindex="4" placeholder="<?= $e(
                    $descPlaceholder
                ) ?>"><?= $e((string) $vars["desc1"]) ?></textarea>
            </label>
            <div class="characterLimit  ">
                <span><?= $e($maxLen) ?>: </span><span>‭‭<?= (int) $vars["desc1Len"] ?>‬/‭5000‬‬</span>
            </div>
        </div>
        <div class="description2">
            <label class="textarea fixed">
                <textarea name="description2" maxlength="5000" tabindex="5" placeholder="<?= $e(
                    $descPlaceholder
                ) ?>"><?= $e((string) $vars["desc2"]) ?></textarea>
            </label>
            <div class="characterLimit  ">
                <span><?= $e($maxLen) ?>: </span><span>‭‭<?= (int) $vars["desc2Len"] ?>‬/‭5000‬‬</span>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(function () {
            // One toolbar for both description fields: it works on the one that was last used.
            var editor = new Travian.Game.BBEditor('profile', false);
            var fields = jQuery('#playerProfile textarea[name=description1], #playerProfile textarea[name=description2]');
            editor.textArea = fields.first();
            fields.on('focus click', function () {
                editor.textArea = jQuery(this);
                editor.hideToolbarWindow();
            });
            // The preview shows both descriptions in place of their fields, like the reference.
            var previews = [];
            var hidePreviews = function () {
                previews.forEach(function (preview) { preview.remove(); });
                previews = [];
                fields.show();
            };
            jQuery('#profile_previewButton').off('click').on('click', function (event) {
                event.preventDefault();
                if (previews.length > 0) {
                    hidePreviews();
                    return;
                }
                fields.each(function () {
                    var field = jQuery(this);
                    if (field.val().length < 1) { return; }
                    Travian.api('bb', {
                        data: { nl2br: 1, target: 'profile', message: field.val() },
                        success: function (data) {
                            var preview = jQuery('<div class="preview"></div>').html(data.text);
                            previews.push(preview);
                            field.hide().closest('label').after(preview);
                            Travian.Tip.updateAllInElement(preview);
                        },
                        error: function (data) { alert(data.message); }
                    });
                });
            });
            fields.on('focus', hidePreviews);
            // A tag the toolbar inserts counts towards the length like typed text.
            jQuery('#profile_toolbar').on('click', 'a, button', function () {
                setTimeout(function () { editor.textArea.trigger('input'); }, 0);
            });
            jQuery('#profile_toolbar').on('click', '.bbButton:not(#profile_previewButton)', hidePreviews);
        });
    </script>

    <h3><?= $e((string) T("Profile", "Your lifetime medals")) ?></h3>
    <div class="medals lifetime"><?= $vars["lifetimeMedals"] !== ""
        ? $vars["lifetimeMedals"]
        : "" ?></div>

    <table class="villages borderGap">
        <thead>
        <tr>
            <th class="tribe"></th>
            <th class="name"><?= $e(
                (string) T("Profile", "Village name")
            ) ?></th>
            <th class="additionalInfoColumn"></th>
            <th class="oases"><?= $e((string) T("Profile", "Oases")) ?></th>
            <th class="inhabitants"><i class="population_medium"></i></th>
            <th class="coordinates"><?= $e(
                (string) T("Profile", "Coordinates")
            ) ?></th>
        </tr>
        </thead>
        <tbody>
        <?= $vars["villages"] ?>
        </tbody>
    </table>

    <div class="save">
        <button class="textButtonV2 buttonFramed rectangle withText green" type="submit">
            <div><span><?= $e(
                (string) T("Profile", "Save changes")
            ) ?></span></div>
        </button>
    </div>
</form>
<script type="text/javascript">
    jQuery(function () {
        var $form = jQuery('#playerProfile form.formV2');
        var $languageRoot = $form.find('#editPlayerLanguages');
        var definitions = <?= json_encode($languageDefinitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        var languages = <?= json_encode($languages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        var deleteSvg = '<svg viewBox="0 0 86.29 100" class="delete"><path d="M75 100H11.32C8.9 93.78 7 89.76 6.89 82.9Q6.73 73 6.72 63c0-13.43.05-26.86.05-40.29H79.5c0 14-.22 28-.13 42C79.46 77.58 80.5 88.09 75 100zM25.8 86V32h-9v54zm22 0V32h-9v54zm13-54v54h9V32z"></path><path d="M0 4.66C10.8 4 21.12 7 29.64 0h27c8.51 7 18.87 4 29.66 4.66L85.76 18H.53z"></path></svg>';

        var renderLanguages = function () {
            var $container = $languageRoot.find('.languages').empty();
            var deletable = languages.length > 1;
            languages.forEach(function (language) {
                var definition = definitions[language];
                if (!definition) {
                    return;
                }
                var $language = jQuery('<div class="language"></div>').toggleClass('deletable', deletable);
                if (deletable) {
                    $language.append(deleteSvg);
                }
                $language.append(jQuery('<i></i>').addClass('flag ' + definition.flag));
                $container.append($language);
            });
            $languageRoot.find('.addLanguageButton').prop('disabled', languages.length >= 4);
        };

        $languageRoot.on('click', '.language.deletable .delete', function () {
            var index = jQuery(this).parent().index();
            languages.splice(index, 1);
            renderLanguages();
        });

        $languageRoot.find('.addLanguageButton').on('click', function (event) {
            event.preventDefault();
            if (languages.length >= 4) {
                return;
            }
            var query = jQuery.param({query: '', languages: languages});
            Travian.api('profile-languages?' + query, {
                success: function (html) {
                    var dialog = new Travian.Dialog.Dialog({
                        version: 2,
                        preventFormSubmit: true,
                        buttonOk: false,
                        cssClass: 'profileLanguages'
                    });
                    dialog.setContent(html).show();
                    var $content = dialog.form.find('#dialogLanguagesList');
                    var selected = null;
                    $content.on('click', 'label', function () {
                        selected = jQuery(this).data('language');
                    });
                    $content.on('input', 'input[type="text"]', function () {
                        var term = jQuery(this).val().toLocaleLowerCase();
                        $content.find('.allLanguages label').each(function () {
                            jQuery(this).toggle(jQuery(this).data('name').toLocaleLowerCase().indexOf(term) !== -1);
                        });
                    });
                    $content.on('click', '.buttonContainer button', function () {
                        if (selected && definitions[selected] && languages.indexOf(selected) === -1) {
                            languages.push(selected);
                            renderLanguages();
                        }
                        dialog.close();
                    });
                }
            }, 'GET', 'html');
        });

        $form.find('textarea').on('input', function () {
            var $textarea = jQuery(this);
            $textarea.closest('.description1, .description2').find('.characterLimit span').last()
                .text('\u202D\u202D' + $textarea.val().length + '\u202C/\u202D5000\u202C\u202C');
        }).trigger('input');

        $form.on('submit', function (event) {
            event.preventDefault();
            var villageNames = [];
            $form.find('input[name^="village"]').each(function () {
                villageNames.push({id: jQuery(this).data('village-id'), name: jQuery(this).val()});
            });
            var genderNames = ['NotSpecified', 'Female', 'Male', 'Other'];
            var genderIndex = $form.find('input[name="gender"]').index($form.find('input[name="gender"]:checked'));
            Travian.api('profile', {
                data: {
                    additionalLanguages: languages,
                    description1: $form.find('textarea[name="description1"]').val(),
                    description2: $form.find('textarea[name="description2"]').val(),
                    gender: genderNames[genderIndex < 0 ? 0 : genderIndex],
                    location: $form.find('input[name="location"]').val(),
                    showCountryFlag: <?= !empty($vars['showCountryFlag']) ? 'true' : 'false' ?>,
                    villageNames: villageNames
                }
            }, 'PUT');
        });
    });
</script>
