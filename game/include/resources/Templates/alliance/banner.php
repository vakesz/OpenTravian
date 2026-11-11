<?php

declare(strict_types=1);

/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */

$screen = (string) ($vars['screen'] ?? 'create');
$config = $vars['config'] ?? [];
$playerBanner = $vars['playerBanner'] ?? null;
$allianceBanner = $vars['allianceBanner'] ?? null;
$gallery = $vars['gallery'] ?? [];
$canSet = !empty($vars['canSetAllianceBanner']);
$tabIndicator = '<div class="tabIndicator"><svg viewBox="0 0 141.7 9.4" preserveAspectRatio="xMidYMid slice"><path d="M70.9,8.7L75,5h66.4" stroke-linecap="square" class="stroke"></path><path d="M0.7,5H67l3.8,3.7" stroke-linecap="square" class="stroke"></path><path d="M68.1,2.8L70.9,0l2.8,2.8l-2.8,2.9L68.1,2.8z" class="square"></path></svg></div>';

echo $vars['bannerTemplateSvg'] ?? '';
?>
<div id="allianceBanner" class="contentV2">
<?php if ($screen === 'create'): ?>
    <?php
    $initial = is_array($playerBanner) ? $playerBanner : \Model\AllianceBannerModel::defaultBanner();
    ?>
    <div class="creator" id="allianceBannerCreator"
         data-config="<?= htmlspecialchars(json_encode($config, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
         data-banner="<?= htmlspecialchars(json_encode($initial, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>">
        <div class="subNavigation">
            <a class="item active" data-key="baseForm" href="#"><?= T('Alliance', 'Base form'); ?><?= $tabIndicator ?></a>
            <div class="divider"></div>
            <a class="item" data-key="pattern" href="#"><?= T('Alliance', 'Pattern'); ?><?= $tabIndicator ?></a>
            <div class="divider"></div>
            <a class="item" data-key="backSymbol" href="#"><?= T('Alliance', 'Back symbol'); ?><?= $tabIndicator ?></a>
            <div class="divider"></div>
            <a class="item" data-key="frontSymbol" href="#"><?= T('Alliance', 'Front symbol'); ?><?= $tabIndicator ?></a>
        </div>
        <div class="elementSelection baseForm" data-panel="baseForm">
            <h5><?= T('Alliance', 'Choose a base form'); ?></h5>
            <div class="elementsWrapper" data-elements="baseForm"></div>
        </div>
        <div class="elementSelection pattern" data-panel="pattern" style="display:none">
            <h5><?= T('Alliance', 'Choose a pattern'); ?></h5>
            <div class="elementsWrapper" data-elements="pattern"></div>
        </div>
        <div class="elementSelection backSymbol" data-panel="backSymbol" style="display:none">
            <h5><?= T('Alliance', 'Choose a back symbol'); ?></h5>
            <div class="elementsWrapper" data-elements="backSymbol"></div>
        </div>
        <div class="elementSelection frontSymbol" data-panel="frontSymbol" style="display:none">
            <h5><?= T('Alliance', 'Choose a front symbol'); ?></h5>
            <div class="elementsWrapper" data-elements="frontSymbol"></div>
        </div>
        <div class="colorSelection">
            <div class="subNavigation colorsNavigation" id="allianceBannerColorNav"></div>
            <div class="colorsWrapper" id="allianceBannerColorSwatches"></div>
        </div>
        <div class="preview">
            <h5><?= T('Alliance', 'Preview'); ?></h5>
            <div class="allianceBannerFrame" id="allianceBannerPreviewFrame"></div>
        </div>
        <button class="textButtonV2 buttonFramed rectangle withIcon green" type="button" id="allianceBannerUndo" title="<?= T('Alliance', 'Reset banner'); ?>">
            <svg viewBox="0 0 20 19" class="outline" aria-hidden="true"><path d="M11.83 8.35A2.23 2.23 0 0 0 10.05 7l-4.6-.6a2.24 2.24 0 0 0-2 .8L.51 10.74A2.2 2.2 0 0 0 .16 13l1.59 4a2.25 2.25 0 0 0 1.79 1.39l4.59.61a2.24 2.24 0 0 0 2-.79l2.94-3.58a2.23 2.23 0 0 0 .35-2.23Z"></path></svg>
        </button>
        <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" id="allianceBannerContinue" form="allianceBannerCreateForm">
            <div><?= T('Alliance', 'Continue'); ?></div>
        </button>
    </div>
    <form method="post" action="/alliance/options/banner/create" id="allianceBannerCreateForm">
        <?= \Core\Session::getCheckerInput(); ?>
        <input type="hidden" name="bannerAction" value="save" />
        <input type="hidden" name="bannerJson" id="allianceBannerJson" value="" />
    </form>
    <script>
    (function () {
        var root = document.getElementById('allianceBannerCreator');
        if (!root) { return; }
        var tabIndicator = <?= json_encode($tabIndicator, JSON_UNESCAPED_SLASHES) ?>;
        var labels = {
            baseColor: <?= json_encode(T('Alliance', 'Base color')) ?>,
            borderColor: <?= json_encode(T('Alliance', 'Border color')) ?>,
            patternPrimaryColor: <?= json_encode(T('Alliance', 'Primary color')) ?>,
            patternSecondaryColor: <?= json_encode(T('Alliance', 'Secondary color')) ?>,
            symbolColor: <?= json_encode(T('Alliance', 'Color')) ?>
        };
        var config = JSON.parse(root.getAttribute('data-config') || '{}');
        var defaults = <?= json_encode(\Model\AllianceBannerModel::defaultBanner(), JSON_UNESCAPED_SLASHES) ?>;
        var banner = Object.assign({}, defaults, JSON.parse(root.getAttribute('data-banner') || '{}'));
        var palette = config.colorPalette || [];
        var activeElementTab = 'baseForm';
        var activeColorKey = 'baseColor';
        var pathFor = {
            baseForm: function (k) { return '#allianceBanner_baseForm' + k + 'Path'; },
            pattern: function (k) { return '#allianceBanner_pattern' + k + 'Primary'; },
            backSymbol: function (k) { return '#allianceBanner_backSymbol' + k + 'Path'; },
            frontSymbol: function (k) { return '#allianceBanner_frontSymbol' + k + 'Path'; }
        };
        function colorTabsFor(elementTab) {
            if (elementTab === 'pattern') {
                return [
                    { key: 'patternPrimaryColor', label: labels.patternPrimaryColor },
                    { key: 'patternSecondaryColor', label: labels.patternSecondaryColor }
                ];
            }
            if (elementTab === 'backSymbol') {
                return [{ key: 'backSymbolColor', label: labels.symbolColor }];
            }
            if (elementTab === 'frontSymbol') {
                return [{ key: 'frontSymbolColor', label: labels.symbolColor }];
            }
            return [
                { key: 'baseColor', label: labels.baseColor },
                { key: 'borderColor', label: labels.borderColor }
            ];
        }
        function renderColorNav() {
            var nav = document.getElementById('allianceBannerColorNav');
            var tabs = colorTabsFor(activeElementTab);
            if (tabs.every(function (t) { return t.key !== activeColorKey; })) {
                activeColorKey = tabs[0].key;
            }
            var html = '';
            tabs.forEach(function (tab, idx) {
                if (idx > 0) { html += '<div class="divider"></div>'; }
                html += '<a class="item' + (tab.key === activeColorKey ? ' active' : '') + '" data-key="' + tab.key + '" href="#">' + tab.label + tabIndicator + '</a>';
            });
            nav.innerHTML = html;
            fillColors(activeColorKey);
        }
        function fillElements(kind) {
            var wrap = root.querySelector('[data-elements="' + kind + '"]');
            if (!wrap) { return; }
            wrap.innerHTML = '';
            var keys = config[kind] || [];
            if (kind !== 'baseForm') {
                var none = document.createElement('a');
                none.className = 'selection elementFrame' + ((banner[kind] || 0) === 0 ? ' selected' : '');
                none.href = '#';
                none.setAttribute('data-element', kind);
                none.setAttribute('data-key', '0');
                none.innerHTML = '<svg viewBox="0 0 300 300"></svg>';
                wrap.appendChild(none);
            }
            keys.forEach(function (key) {
                var a = document.createElement('a');
                a.className = 'selection elementFrame' + (String(banner[kind]) === String(key) ? ' selected' : '');
                a.href = '#';
                a.setAttribute('data-element', kind);
                a.setAttribute('data-key', String(key));
                a.innerHTML = '<svg viewBox="0 0 300 300"><use href="' + pathFor[kind](key) + '" fill="#000000"></use></svg>';
                wrap.appendChild(a);
            });
        }
        function fillColors(kind) {
            var wrap = document.getElementById('allianceBannerColorSwatches');
            if (!wrap) { return; }
            wrap.innerHTML = '';
            wrap.setAttribute('data-colors', kind);
            palette.forEach(function (color) {
                var a = document.createElement('a');
                a.className = 'selection colorFrame' + (String(banner[kind] || '').toUpperCase() === String(color).toUpperCase() ? ' selected' : '');
                a.href = '#';
                a.setAttribute('data-element', kind);
                a.setAttribute('data-key', color);
                a.style.backgroundColor = color;
                wrap.appendChild(a);
            });
        }
        function renderPreview() {
            var frame = document.getElementById('allianceBannerPreviewFrame');
            if (!frame) { return; }
            var base = banner.baseForm || 4;
            var pattern = banner.pattern || 0;
            var back = banner.backSymbol || 0;
            var front = banner.frontSymbol || 0;
            var html = '<svg class="allianceBanner" width="300" height="300" viewBox="0 0 300 300" id="allianceBanner_creatorPreview">';
            html += '<clipPath id="allianceBanner_creatorPreview_clipPath"><use href="#allianceBanner_baseForm' + base + 'Path"></use></clipPath>';
            if (back > 0) {
                html += '<svg x="40" y="40" width="220" height="220" viewBox="0 0 300 300" overflow="visible"><use href="#allianceBanner_backSymbol' + back + 'Path" fill="' + banner.backSymbolColor + '"></use></svg>';
            }
            html += '<use href="#allianceBanner_baseForm' + base + 'Path" fill="' + banner.baseColor + '"></use>';
            if (pattern > 0) {
                html += '<use clip-path="url(#allianceBanner_creatorPreview_clipPath)" href="#allianceBanner_pattern' + pattern + 'Primary" fill="' + banner.patternPrimaryColor + '"></use>';
                if ((config.patternsWithSecondaryColors || []).indexOf(pattern) !== -1) {
                    html += '<use clip-path="url(#allianceBanner_creatorPreview_clipPath)" href="#allianceBanner_pattern' + pattern + 'Secondary" fill="' + banner.patternSecondaryColor + '"></use>';
                }
            }
            html += '<use href="#allianceBanner_baseForm' + base + 'Path" stroke-width="5" stroke="' + banner.borderColor + '" fill="transparent"></use>';
            if (front > 0) {
                html += '<svg x="40" y="40" width="220" height="220" viewBox="0 0 300 300" overflow="visible"><use href="#allianceBanner_frontSymbol' + front + 'Path" fill="' + banner.frontSymbolColor + '"></use></svg>';
            }
            html += '</svg>';
            frame.innerHTML = html;
            var field = document.getElementById('allianceBannerJson');
            if (field) { field.value = JSON.stringify(banner); }
        }
        function setElementTab(key) {
            activeElementTab = key;
            root.querySelectorAll('.creator > .subNavigation .item').forEach(function (el) {
                el.classList.toggle('active', el.getAttribute('data-key') === key);
            });
            root.querySelectorAll('.elementSelection').forEach(function (panel) {
                panel.style.display = panel.getAttribute('data-panel') === key ? '' : 'none';
            });
            renderColorNav();
        }
        ['baseForm', 'pattern', 'backSymbol', 'frontSymbol'].forEach(fillElements);
        setElementTab('baseForm');
        renderPreview();
        root.addEventListener('click', function (ev) {
            var item = ev.target.closest('.creator > .subNavigation a.item');
            if (item) {
                ev.preventDefault();
                setElementTab(item.getAttribute('data-key'));
                return;
            }
            var colorTab = ev.target.closest('.colorsNavigation a.item');
            if (colorTab) {
                ev.preventDefault();
                activeColorKey = colorTab.getAttribute('data-key');
                renderColorNav();
                return;
            }
            var sel = ev.target.closest('a.selection');
            if (!sel) { return; }
            ev.preventDefault();
            var element = sel.getAttribute('data-element');
            var key = sel.getAttribute('data-key');
            if (!element) { return; }
            sel.parentNode.querySelectorAll('a.selection').forEach(function (el) { el.classList.remove('selected'); });
            sel.classList.add('selected');
            if (element.indexOf('Color') !== -1 || element === 'baseColor' || element === 'borderColor') {
                banner[element] = key;
            } else {
                banner[element] = parseInt(key, 10) || 0;
                if (element === 'pattern') {
                    renderColorNav();
                }
            }
            renderPreview();
        });
        document.getElementById('allianceBannerUndo').addEventListener('click', function () {
            banner = Object.assign({}, defaults);
            ['baseForm', 'pattern', 'backSymbol', 'frontSymbol'].forEach(fillElements);
            setElementTab(activeElementTab);
            renderPreview();
        });
        document.getElementById('allianceBannerCreateForm').addEventListener('submit', function () {
            document.getElementById('allianceBannerJson').value = JSON.stringify(banner);
        });
    })();
    </script>
<?php elseif ($screen === 'my'): ?>
    <?php
    $submitDisabled = $playerBanner === null;
    $setDisabled = $playerBanner === null || !$canSet;
    $ornamentSvg = (string) file_get_contents(__DIR__ . '/banner/alliance-banner-ornament.svg');
    ?>
    <div class="myBanner">
        <?= $ornamentSvg ?>
        <div class="bannerPresentation<?= $playerBanner !== null ? ' animated' : '' ?>">
            <div class="bannerBackground">
                <?php if ($playerBanner !== null): ?>
                    <?= \Model\AllianceBannerModel::renderSvg($playerBanner, 'allianceBanner_myBanner'); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="bannerCreatorLink">
            <a href="/alliance/options/banner/create">
                <svg viewBox="0 0 12.8 18.8" aria-hidden="true"><path d="M5.5 16.6.8 18.8 0 13.6c0-.1.1-.1.1-.1l5.4 2.9c.1.1.1.2 0 .2zm6.7-14.4L8.4.1C7.9-.1 7.3 0 7 .5L1.2 11.2c-.3.5-.1 1.1.3 1.4h.1l3.9 2.1c.5.3 1.1.1 1.4-.4l5.8-10.7c.3-.4.1-1.1-.5-1.4.1 0 .1 0 0 0z"></path></svg>
                <span><?= T('Alliance', $playerBanner === null ? 'Create banner' : 'Edit banner'); ?></span>
            </a>
        </div>
        <div class="bannerStateDescription">
            <strong><?= T('Alliance', 'My Banner'); ?></strong>
            <?php if ($playerBanner === null): ?>
                <p><?= T('Alliance', 'You have not created a banner yet.'); ?></p>
            <?php endif; ?>
            <?php if ($canSet): ?>
                <p><?= T('Alliance', 'You have the right to set the alliance banner. After doing so, it will be shown in the alliance description and throughout the game.'); ?></p>
            <?php else: ?>
                <p><?= T('Alliance', 'Only members with the right rights can set the alliance banner.'); ?></p>
            <?php endif; ?>
        </div>
        <div class="bannerActions">
            <form method="post" action="/alliance/options/banner/my" style="display:contents">
                <?= \Core\Session::getCheckerInput(); ?>
                <input type="hidden" name="bannerAction" value="submitGallery" />
                <button id="submitToGallery" class="textButtonV2 buttonFramed<?= $submitDisabled ? ' disabled' : '' ?> rectangle withText green" type="submit"<?= $submitDisabled ? ' disabled' : '' ?>>
                    <div><?= T('Alliance', 'Submit to gallery'); ?></div>
                </button>
            </form>
            <form method="post" action="/alliance/options/banner/my" style="display:contents">
                <?= \Core\Session::getCheckerInput(); ?>
                <input type="hidden" name="bannerAction" value="setAlliance" />
                <button id="setAllianceBanner" class="textButtonV2 buttonFramed<?= $setDisabled ? ' disabled' : '' ?> rectangle withText green" type="submit"<?= $setDisabled ? ' disabled' : '' ?>>
                    <div><?= T('Alliance', 'Set alliance banner'); ?></div>
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="gallery">
        <?php if ($gallery === []): ?>
            <div class="noBanners"><?= sprintf(
                T('Alliance', 'No banners have been submitted yet. Go to %s to submit yours.'),
                '<a href="/alliance/options/banner/create">' . T('Alliance', 'Create my banner') . '</a>'
            ); ?></div>
        <?php else: ?>
            <div class="allianceBannerGalleryOverview" id="allianceBannerGalleryOverview">
                <?php foreach ($gallery as $i => $row): ?>
                    <?php
                    $uid = (int) ($row['uid'] ?? 0);
                    $playerName = (string) ($row['playerName'] ?? ('#' . $uid));
                    $bannerJson = htmlspecialchars(json_encode($row['banner'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="galleryBanner" data-index="<?= $i ?>" data-uid="<?= $uid ?>" data-banner="<?= $bannerJson ?>">
                        <div class="bannerBackground" role="button" tabindex="0">
                            <?= \Model\AllianceBannerModel::renderSvg($row['banner'], 'allianceBanner_gallery_' . $i); ?>
                        </div>
                        <a class="playerLink" href="<?= htmlspecialchars(profileUrl($uid), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="galleryBannerDetailView" id="allianceBannerGalleryDetail" hidden>
                <?= (string) file_get_contents(__DIR__ . '/banner/alliance-banner-ornament.svg') ?>
                <div class="bannerPresentation animated">
                    <div class="bannerBackground" id="allianceBannerGalleryDetailBanner"></div>
                </div>
                <a class="playerLink" id="allianceBannerGalleryDetailPlayer" href="#"></a>
                <div class="bannerActions">
                    <button type="button" class="textButtonV2 buttonFramed rectangle withText green" id="backToGallery">
                        <div><?= T('Alliance', 'Banner gallery'); ?></div>
                    </button>
                    <?php if ($canSet): ?>
                        <form method="post" action="/alliance/options/banner/gallery" style="display:contents" id="allianceBannerGallerySetForm">
                            <?= \Core\Session::getCheckerInput(); ?>
                            <input type="hidden" name="bannerAction" value="setAllianceFromGallery" />
                            <input type="hidden" name="bannerJson" id="allianceBannerGallerySetJson" value="" />
                            <button id="setAllianceBanner" class="textButtonV2 buttonFramed rectangle withText green" type="submit">
                                <div><?= T('Alliance', 'Set alliance banner'); ?></div>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            (function () {
                var overview = document.getElementById('allianceBannerGalleryOverview');
                var detail = document.getElementById('allianceBannerGalleryDetail');
                var detailBanner = document.getElementById('allianceBannerGalleryDetailBanner');
                var detailPlayer = document.getElementById('allianceBannerGalleryDetailPlayer');
                var setJson = document.getElementById('allianceBannerGallerySetJson');
                var back = document.getElementById('backToGallery');
                if (!overview || !detail || !detailBanner || !detailPlayer || !back) { return; }
                function openDetail(card) {
                    var bg = card.querySelector('.bannerBackground');
                    var link = card.querySelector('.playerLink');
                    if (!bg || !link) { return; }
                    detailBanner.innerHTML = bg.innerHTML;
                    detailPlayer.href = link.getAttribute('href') || '#';
                    detailPlayer.textContent = link.textContent || '';
                    if (setJson) { setJson.value = card.getAttribute('data-banner') || ''; }
                    overview.hidden = true;
                    detail.hidden = false;
                }
                function closeDetail() {
                    detail.hidden = true;
                    overview.hidden = false;
                    detailBanner.innerHTML = '';
                }
                overview.querySelectorAll('.galleryBanner .bannerBackground').forEach(function (el) {
                    el.addEventListener('click', function () {
                        openDetail(el.closest('.galleryBanner'));
                    });
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            openDetail(el.closest('.galleryBanner'));
                        }
                    });
                });
                back.addEventListener('click', closeDetail);
            })();
            </script>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
