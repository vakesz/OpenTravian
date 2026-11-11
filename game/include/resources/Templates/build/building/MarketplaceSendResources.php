<?php

declare(strict_types=1);

require_once __DIR__ . '/../../dailyQuest/icons.php';

$resourceNames = [
    T('inGame', 'resources.r1'),
    T('inGame', 'resources.r2'),
    T('inGame', 'resources.r3'),
    T('inGame', 'resources.r4'),
];
$resourceIcons = ['lumber_medium', 'clay_medium', 'iron_medium', 'crop_medium'];
$resourceAmounts = array_pad(array_map('intval', $vars['resources']), 4, 0);
$state = [
    'sourceKid' => (int) \Core\Village::getInstance()->getKid(),
    'merchantCapacity' => (int) $vars['merchantCapacityValue'],
    'merchantsAvailable' => (int) $vars['merchantsAvailable'],
    'merchantsTotal' => (int) $vars['total_merchants'],
    'tradeShips' => (int) ($vars['tradeShips'] ?? 0),
    'tradeShipCapacity' => (int) ($vars['tradeShipCapacity'] ?? 0),
    'stock' => $resourceAmounts,
    'targetName' => (string) $vars['dname'],
    'targetX' => (string) $vars['x'],
    'targetY' => (string) $vars['y'],
    'hasGoldClub' => (bool) $vars['hasGoldClub'],
    'hasPlus' => (bool) $vars['hasPlus'],
    'deliveries' => is_array($vars['deliveries'] ?? null)
        ? $vars['deliveries']
        : ['incoming' => [], 'outgoing' => []],
    'deliveryEmptyLabels' => [
        'incoming' => T('MarketPlace', 'No incoming deliveries'),
        'outgoing' => T('MarketPlace', 'No outgoing deliveries'),
    ],
];

/** @param list<array<string, mixed>> $deliveries */
$renderDeliveryRows = static function (array $deliveries, string $empty): void {
    if ($deliveries === []) {
        ?>
        <div class="noDelivery"><?= htmlspecialchars($empty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <?php
        return;
    }

    foreach ($deliveries as $delivery) {
        $villageKid = max(0, (int)($delivery['villageKid'] ?? 0));
        $villageName = (string)($delivery['villageName'] ?? '[?]');
        $repeat = max(1, (int)($delivery['repeat'] ?? 1));
        $resources = array_pad(array_map('intval', (array)($delivery['resources'] ?? [])), 4, 0);
        $endTime = (int)($delivery['endTime'] ?? time());
        $returning = (bool)($delivery['returning'] ?? false);
        ?>
        <div class="route">
            <div class="routeHeader">
                <a href="/karte.php?d=<?= $villageKid; ?>"><?= stored_name_html((string)$villageName); ?></a>
                <?php if ($repeat > 1): ?>
                    <div class="runs"><span class="value"><?= $repeat; ?>×</span></div>
                <?php endif; ?>
            </div>
            <div class="delivery<?= $returning ? ' return' : ''; ?>">
                <?php foreach ($resources as $index => $amount): ?>
                    <i class="r<?= $index + 1; ?>" aria-hidden="true"></i><span class="value"><?= number_format_x($amount); ?></span>
                <?php endforeach; ?>
                <div class="arriveInAt"><?= appendTimer($endTime - time()); ?></div>
            </div>
        </div>
        <?php
    }
};
?>
<div id="marketplaceSendResources" class="contentV2">
    <div class="deliveriesOverview">
        <div class="merchantsInformation">
            <div class="available">
                <?= T('MarketPlace', 'Merchants'); ?>:&nbsp;<span class="value">&#8237;&#8237;<span data-market-merchants-available><?= (int) $vars['merchantsAvailable']; ?></span>&#8236;/&#8237;<?= (int) $vars['total_merchants']; ?>&#8236;&#8236;</span>
            </div>
            <div class="capacity">
                <?= T('MarketPlace', 'Capacity per merchant'); ?>:&nbsp;<span class="value"><?= number_format_x((int) $vars['merchantCapacityValue']); ?></span>
            </div>
            <?php if (!empty($vars['tradeShips'])): ?>
                <div class="useShip">
                    <label>
                        <input type="checkbox" data-market-use-ship>
                        <span><?= T('MarketPlace', 'Use trade ships'); ?> (<?= (int) $vars['tradeShips']; ?>, <?= T('MarketPlace', 'Capacity per ship'); ?>: <?= number_format_x((int) $vars['tradeShipCapacity']); ?>)</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <form id="marketSend" action="/build.php" method="dialog">
        <div class="contentV2 sendResourcesView formV2">
            <h3><?= T('MarketPlace', 'SendResources'); ?></h3>
            <div class="sendResourcesForm formV2">
            <div class="targetSelection">
                <div class="inputWrapper">
                    <label class="input search">
                        <input id="enterVillageName" type="text" maxlength="45" tabindex="1"
                               value="<?= htmlspecialchars((string) $vars['dname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <div class="label"><?= T('MarketPlace', 'Village name'); ?></div>
                    </label>
                    <label class="input coordinateX withCustomValidationRenderElement">
                        <input id="xCoordInput" name="x" type="text" inputmode="numeric" maxlength="4" tabindex="2"
                               value="<?= htmlspecialchars((string) $vars['x'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <div class="label">X</div>
                        <div class="validation" data-rule="notEmpty" data-render-into=".targetSelectionValidation"><?= T('MarketPlace', 'enterCoordinatesOrVillageName'); ?></div>
                    </label>
                    <label class="input coordinateY withCustomValidationRenderElement">
                        <input id="yCoordInput" name="y" type="text" inputmode="numeric" maxlength="4" tabindex="3"
                               value="<?= htmlspecialchars((string) $vars['y'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <div class="label">Y</div>
                        <div class="validation" data-rule="notEmpty" data-render-into=".targetSelectionValidation"><?= T('MarketPlace', 'enterCoordinatesOrVillageName'); ?></div>
                    </label>
                </div>
                <div class="targetSelectionResultWrapper">
                    <div class="customValidationRenderElement targetSelectionValidation" aria-live="polite"></div>
                    <div class="targetWrapper">
                        <div class="player"><?= T('MarketPlace', 'Player'); ?>:&nbsp;<span class="value" data-market-player>-</span></div>
                        <div class="duration"><?= T('MarketPlace', 'Duration'); ?>:&nbsp;<span class="value" data-market-duration>-</span></div>
                        <div class="alliance"><?= T('MarketPlace', 'Alliance'); ?>:&nbsp;<span class="value" data-market-alliance>-</span></div>
                        <div class="arrival"><?= T('MarketPlace', 'Arrival'); ?>:&nbsp;<span class="value" data-market-arrival>-</span></div>
                    </div>
                </div>
            </div>

            <div class="merchantCarryInfo">
                <p><?= T('MarketPlace', 'Each of your merchants can carry'); ?> <strong><?= number_format_x((int) $vars['merchantCapacityValue']); ?></strong> <?= T('MarketPlace', 'Each of your merchants can carry resources'); ?>.</p>
            </div>

            <div class="resourceSelector">
                <?php
                $resourceFieldNames = ['lumber', 'clay', 'iron', 'crop'];
                foreach ($resourceNames as $offset => $name):
                    $number = $offset + 1;
                    $fieldName = $resourceFieldNames[$offset];
                    ?>
                    <i class="icon <?= $resourceIcons[$offset]; ?>" title="<?= htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></i>
                    <div class="progressBar preventMobileSwipeNavigation" data-market-resource="<?= $offset; ?>">
                        <div class="bar">
                            <?= daily_quest_svg('decoration_start'); ?>
                            <div class="filling primary blue"></div>
                            <?= daily_quest_svg('decoration_end'); ?>
                        </div>
                        <button type="button" class="textButtonV2 buttonFramed plus rectangle withIcon green" title="<?= T('MarketPlace', 'Add merchant'); ?>">
                            <svg viewBox="0 0 18 18" aria-hidden="true"><path d="M8 2h2v6h6v2h-6v6H8v-6H2V8h6z"></path></svg>
                        </button>
                        <button type="button" class="textButtonV2 buttonFramed minus rectangle withIcon green" title="<?= T('MarketPlace', 'Subtract merchant'); ?>">
                            <svg viewBox="0 0 18 18" aria-hidden="true"><path d="M2 8h14v2H2z"></path></svg>
                        </button>
                        <div class="slider" aria-hidden="true"><div class="handle blue" style="margin-inline-start: 0%; transition-duration: 250ms;"></div></div>
                    </div>
                    <button type="button" class="textButtonV2 buttonFramed fillup rectangle withIcon green" data-market-fillup="<?= $offset; ?>" title="<?= T('MarketPlace', 'Fill up resources'); ?>">
                        <svg viewBox="0 0 22 22" aria-hidden="true"><path d="M6 0L6 12L0 12L11 22L22 12L16 12L16 0Z"></path></svg>
                    </button>
                    <div class="inputRatio stock isLtr">
                        <span class="nominator"><label class="input amount"><input name="<?= $fieldName; ?>" type="text" inputmode="numeric" maxlength="15" value="0" tabindex="<?= $number + 3; ?>" autocomplete="off"></label></span>
                        <span class="separator">/</span>
                        <span class="denominator" data-market-stock="<?= $offset; ?>">&#8237;<?= number_format_x($resourceAmounts[$offset]); ?>&#8236;</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary">
                <div><?= T('MarketPlace', 'Total'); ?>: &#8237;<span class="nominator" data-market-total>&#8237;0&#8236;</span>&nbsp;/&nbsp;<span class="denominator" data-market-capacity>&#8237;0&#8236;</span>&#8236;</div>
                <div class=""><?= T('MarketPlace', 'Merchants'); ?>:&nbsp;&#8237;<span class="nominator" data-market-required>&#8237;0&#8236;</span>&nbsp;/&nbsp;<span class="denominator" data-market-available>&#8237;<?= (int) $vars['merchantsAvailable']; ?>&#8236;</span>&#8236;</div>
            </div>
            <div class="deliveriesSelector">
                <div><?= T('MarketPlace', 'Deliveries'); ?></div>
                <?php for ($repeat = 1; $repeat <= 3; ++$repeat): ?>
                    <?php $enabled = $repeat === 1
                        || ($repeat === 2 && ($vars['hasPlus'] || $vars['hasGoldClub']))
                        || ($repeat === 3 && $vars['hasGoldClub']); ?>
                    <label class="radio<?= $enabled ? '' : ' disabledInput'; ?>"<?= $enabled ? '' : ' title="' . T('MarketPlace', $repeat === 2 ? 'Gold Club or OpenTravian Plus required' : 'Gold Club required') . '"'; ?>>
                        <input type="radio" name="deliveries" data-repeat="<?= $repeat; ?>" tabindex="8"<?= $repeat === 1 ? ' checked' : ''; ?><?= $enabled ? '' : ' disabled'; ?>>
                        <svg viewBox="0 0 20 20" class="radio" aria-hidden="true"><path d="M10,0A10,10,0,1,0,20,10,10,10,0,0,0,10,0Z"></path><rect class="indeterminate" width="10" height="5" x="5" y="7.5"></rect><circle class="checked" cx="10" cy="10" r="5"></circle></svg>
                        <div class="label"><span class="value"><?= $repeat; ?>×</span><i class="deliveries_<?= $repeat; ?>x"></i></div>
                    </label>
                <?php endfor; ?>
            </div>
            <div class="actionButtons">
                <button type="submit" class="textButtonV2 buttonFramed send disabled rectangle withText green"><div><?= T('MarketPlace', 'SendResources'); ?></div></button>
            </div>
            </div>
        </div>
        </form>
        <h3><?= T('MarketPlace', 'Delivery overview'); ?></h3>
        <?php foreach ([
            ['incoming', 'Incoming deliveries', 'No incoming deliveries'],
            ['outgoing', 'Outgoing deliveries', 'No outgoing deliveries'],
        ] as [$direction, $heading, $empty]): ?>
            <?php $deliveries = $state['deliveries'][$direction] ?? []; ?>
            <div class="group expanded" data-delivery-group="<?= $direction; ?>">
                <div class="groupHeader">
                    <div class="title"><?= T('MarketPlace', $heading); ?></div>
                    <div class="totalCount"><?= count($deliveries); ?></div>
                    <div class="expandCollapse expanded"><svg viewBox="0 0 12 7.41" aria-hidden="true"><path d="M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z"></path></svg></div>
                </div>
                <div class="listHeader"><div class="resources"><?= T('MarketPlace', 'Resources'); ?></div><div class="arrival"><?= T('MarketPlace', 'Arrival'); ?></div></div>
                <div class="routes"><?php $renderDeliveryRows($deliveries, T('MarketPlace', $empty)); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    jQuery(function () {
        var root = jQuery('#marketplaceSendResources');
        var form = jQuery('#marketSend');
        if (!root.length || !form.length) {
            return;
        }

        var state = <?= json_encode($state, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>;
        state.amounts = [0, 0, 0, 0];
        state.confirmation = null;
        state.pending = 0;
        state.reloading = false;
        state.refreshAfterPending = false;
        state.repeat = 1;
        state.canSend = false;
        // Trade ships replace merchants as the carriers while "use trade ships" is ticked.
        state.useShip = false;
        state.merchantCapacityDefault = state.merchantCapacity;
        state.merchantsAvailableDefault = state.merchantsAvailable;

        var resourceNames = ['lumber', 'clay', 'iron', 'crop'];
        var inputs = resourceNames.map(function (name) {
            return form.find('input[name="' + name + '"]');
        });
        var sendButton = form.find('button.send');
        var validation = root.find('.targetSelectionValidation');
        var targetResult = root.find('.targetSelectionResultWrapper');

        function resourcePayload() {
            return {
                lumber: state.amounts[0],
                clay: state.amounts[1],
                iron: state.amounts[2],
                crop: state.amounts[3]
            };
        }

        // The reference wraps each figure in left-to-right isolates so RTL pages keep digit order.
        function ltr(value) {
            return '\u202d' + value + '\u202c';
        }

        function integer(value) {
            var parsed = parseInt(String(value || '').replace(/[^0-9]/g, ''), 10);
            return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        }

        function total() {
            return state.amounts.reduce(function (sum, amount) { return sum + amount; }, 0);
        }

        function merchantLimit() {
            return state.merchantsAvailable * state.merchantCapacity;
        }

        function requiredMerchants() {
            return Math.ceil(total() / state.merchantCapacity) || 0;
        }

        function clamp(index, requested) {
            var withoutCurrent = total() - state.amounts[index];
            return Math.min(Math.max(0, requested), state.stock[index], Math.max(0, merchantLimit() - withoutCurrent));
        }

        function setAmount(index, requested) {
            state.amounts[index] = clamp(index, requested);
            render();
        }

        function setTarget(confirmation) {
            state.confirmation = confirmation || null;
            if (!state.confirmation) {
                root.find('[data-market-player], [data-market-duration], [data-market-alliance], [data-market-arrival]').text('-');
                return;
            }
            root.find('[data-market-player]').text(state.confirmation.playerName);
            root.find('[data-market-duration]').text(state.confirmation.duration);
            root.find('[data-market-alliance]').text(state.confirmation.allianceName || '-');
            root.find('[data-market-arrival]').text(state.confirmation.arrival);
        }

        function addResourceCell(delivery, resource, amount) {
            delivery.append(jQuery('<i>', { 'class': 'r' + (resource + 1), 'aria-hidden': 'true' }));
            delivery.append(jQuery('<span>', { 'class': 'value' }).text(Number(amount || 0).toLocaleString()));
        }

        function addTimer(delivery, endTime) {
            var seconds = Math.max(0, Number(endTime) - Math.floor(Date.now() / 1000));
            var timer = jQuery('<span>', {
                'class': 'timer',
                counting: 'down',
                value: seconds
            }).text(Travian.TimersAndCounters.intToTime(seconds, false).time);
            delivery.append(jQuery('<div>', { 'class': 'arriveInAt' }).append(timer));
        }

        function renderDeliveryGroup(direction, deliveries) {
            var group = root.find('[data-delivery-group="' + direction + '"]');
            var routes = group.find('.routes').empty();
            group.find('.totalCount').text(deliveries.length);
            if (!deliveries.length) {
                routes.append(jQuery('<div>', { 'class': 'noDelivery' }).text(
                    state.deliveryEmptyLabels[direction] || ''
                ));
                return;
            }

            deliveries.forEach(function (movement) {
                var route = jQuery('<div>', { 'class': 'route' });
                var header = jQuery('<div>', { 'class': 'routeHeader' });
                header.append(jQuery('<a>', {
                    href: '/karte.php?d=' + encodeURIComponent(movement.villageKid)
                }).text(movement.villageName));
                if (Number(movement.repeat) > 1) {
                    header.append(jQuery('<div>', { 'class': 'runs' }).append(
                        jQuery('<span>', { 'class': 'value' }).text(movement.repeat + '×')
                    ));
                }
                var delivery = jQuery('<div>', {
                    'class': 'delivery' + (movement.returning ? ' return' : '')
                });
                (movement.resources || []).slice(0, 4).forEach(function (amount, resource) {
                    addResourceCell(delivery, resource, amount);
                });
                addTimer(delivery, movement.endTime);
                route.append(header, delivery);
                routes.append(route);
            });
            Travian.TimersAndCounters.initTimersInContext(routes.get(0));
        }

        function renderDeliveryGroups(deliveries) {
            state.deliveries = deliveries || { incoming: [], outgoing: [] };
            renderDeliveryGroup('incoming', state.deliveries.incoming || []);
            renderDeliveryGroup('outgoing', state.deliveries.outgoing || []);
        }

        function render() {
            var selected = total();
            var required = requiredMerchants();
            var allowed = selected > 0 && required <= state.merchantsAvailable && !state.pending;
            inputs.forEach(function (input, index) {
                input.val(state.amounts[index]);
                var ratio = state.stock[index] ? (state.amounts[index] / state.stock[index]) * 100 : 0;
                var resource = root.find('[data-market-resource="' + index + '"]');
                var percentage = Math.min(100, Math.max(0, ratio)) + '%';
                resource.find('.filling').css('width', percentage);
                resource.find('.slider .handle').css('margin-inline-start', percentage);
                resource.find('button.minus').prop('disabled', state.amounts[index] === 0 || state.pending);
            });
            root.find('[data-market-total]').text(ltr(selected.toLocaleString()));
            root.find('[data-market-capacity]').text(ltr(merchantLimit().toLocaleString()));
            root.find('[data-market-required]').text(ltr(required));
            root.find('[data-market-available]').text(ltr(state.merchantsAvailable));
            root.find('[data-market-merchants-available]').text(state.merchantsAvailable);
            state.canSend = allowed;
            sendButton
                .prop('disabled', false)
                .attr('class', 'textButtonV2 buttonFramed send' + (allowed ? '' : ' disabled') + ' rectangle withText green');
        }

        function request(data, success) {
            state.pending += 1;
            render();
            Travian.api('marketplace/prepare', {
                data: data,
                success: function (response) {
                    if (response.errorMessage) {
                        validation.text(response.errorMessage);
                        targetResult.addClass('hasError');
                        return;
                    }
                    validation.empty();
                    targetResult.removeClass('hasError');
                    success(response);
                },
                error: function (response) {
                    validation.text(response.message || 'Unable to send resources.');
                    targetResult.addClass('hasError');
                },
                complete: function () {
                    state.pending = Math.max(0, state.pending - 1);
                    render();
                    if (state.pending === 0 && state.refreshAfterPending) {
                        state.refreshAfterPending = false;
                        reloadMarketplace();
                    }
                }
            });
        }

        function reloadMarketplace() {
            if (state.pending || state.reloading) {
                return;
            }

            state.reloading = true;
            Travian.api('marketplace/reload', {
                data: {},
                success: function (reload) {
                    state.merchantsAvailableDefault = reload.merchantsAvailable;
                    state.tradeShips = reload.ships;
                    state.merchantsAvailable = state.useShip ? state.tradeShips : reload.merchantsAvailable;
                    state.stock = [reload.storage.l1, reload.storage.l2, reload.storage.l3, reload.storage.l4];
                    root.find('[data-market-stock]').each(function (index, element) {
                        jQuery(element).text(ltr(Number(state.stock[index]).toLocaleString()));
                    });
                    Travian.Game.Layout.updateResources();
                    renderDeliveryGroups(reload.deliveries);
                    render();
                },
                complete: function () {
                    state.reloading = false;
                }
            });
        }

        function sendConfirmedShipment(confirmation) {
            request(Object.assign(resourcePayload(), {
                x2: confirmation.repeat,
                a: state.sourceKid,
                kid: confirmation.kid,
                c: confirmation.checker,
                useShip: state.useShip ? 1 : 0
            }), function (response) {
                state.confirmation = null;
                state.amounts = [0, 0, 0, 0];
                setTarget(null);
                validation.text(response.notice || 'Resources sent.');
                // The request remains pending during this callback. Let the
                // completion handler refresh once it can see the new movement.
                state.refreshAfterPending = true;
            });
        }

        form.on('submit', function (event) {
            event.preventDefault();
            if (!state.canSend) {
                return;
            }
            validation.empty();
            targetResult.removeClass('hasError');
            if (!state.confirmation) {
                request(Object.assign(resourcePayload(), {
                    dname: jQuery('#enterVillageName').val(),
                    x: jQuery('#xCoordInput').val(),
                    y: jQuery('#yCoordInput').val(),
                    x2: state.repeat || 1,
                    useShip: state.useShip ? 1 : 0
                }), function (response) {
                    setTarget(response.confirmation);
                    window.setTimeout(function () {
                        sendConfirmedShipment(response.confirmation);
                    }, 0);
                });
                return;
            }
            sendConfirmedShipment(state.confirmation);
        });

        inputs.forEach(function (input, index) {
            input.on('input change', function () { setAmount(index, integer(input.val())); });
        });
        root.find('[data-market-use-ship]').on('change', function () {
            state.useShip = jQuery(this).prop('checked');
            state.merchantCapacity = state.useShip ? state.tradeShipCapacity : state.merchantCapacityDefault;
            state.merchantsAvailable = state.useShip ? state.tradeShips : state.merchantsAvailableDefault;
            state.amounts = [0, 0, 0, 0];
            setTarget(null);
            render();
        });
        root.find('input[name="deliveries"]').on('change', function () {
            var selectedRepeat = integer(jQuery(this).attr('data-repeat'));
            if (selectedRepeat < 1 || selectedRepeat > 3 || jQuery(this).prop('disabled')) {
                return;
            }
            state.repeat = selectedRepeat;
            setTarget(null);
        });
        root.find('.progressBar').each(function () {
            var progress = jQuery(this);
            var index = integer(progress.data('market-resource'));
            progress.find('button.minus').on('click', function () { setAmount(index, state.amounts[index] - state.merchantCapacity); });
            progress.find('button.plus').on('click', function () { setAmount(index, state.amounts[index] + state.merchantCapacity); });
            progress.find('.slider').on('pointerdown', function (event) {
                if (state.pending) {
                    return;
                }
                event.preventDefault();
                var slider = this;
                var setFromPointer = function (pointerEvent) {
                    var nativeEvent = pointerEvent.originalEvent || pointerEvent;
                    var bounds = slider.getBoundingClientRect();
                    if (!bounds.width) {
                        return;
                    }
                    var ratio = Math.min(1, Math.max(0, (nativeEvent.clientX - bounds.left) / bounds.width));
                    setAmount(index, Math.round(ratio * state.stock[index]));
                };
                setFromPointer(event);
                jQuery(document)
                    .off('.marketplaceResourceSlider')
                    .on('pointermove.marketplaceResourceSlider', setFromPointer)
                    .one('pointerup.marketplaceResourceSlider pointercancel.marketplaceResourceSlider', function () {
                        jQuery(document).off('.marketplaceResourceSlider');
                    });
            });
        });
        root.find('[data-market-fillup]').on('click', function () { setAmount(integer(jQuery(this).data('market-fillup')), merchantLimit()); });
        root.on('click', '.groupHeader .expandCollapse', function () {
            var control = jQuery(this);
            control.closest('.group').toggleClass('expanded collapsed');
            control.toggleClass('expanded');
        });
        jQuery('#enterVillageName').on('input', function () { jQuery('#xCoordInput, #yCoordInput').val(''); setTarget(null); });
        jQuery('#xCoordInput, #yCoordInput').on('input', function () { jQuery('#enterVillageName').val(''); setTarget(null); });
        if (Travian.Game.AutoCompleter && Travian.Game.AutoCompleter.VillageName) {
            new Travian.Game.AutoCompleter.VillageName(jQuery('#enterVillageName'));
        }
        renderDeliveryGroups(state.deliveries);
        render();
        window.setInterval(reloadMarketplace, 15000);
    });
</script>
