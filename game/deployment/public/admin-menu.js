/*
 * Control panel sidebar: collapsible categories.
 * Markup contract (game/include/admin/include/templates/layout.php):
 *   nav.adminMenu > .adminMenuGroup[data-menu-group][data-current]
 *     > h2 > button.adminMenuToggle[aria-controls]  +  .adminMenuList[id]
 *   button.adminMenuAll[hidden]  (expand all / collapse all)
 * Without this script every list stays visible.
 */
(function (root) {
    'use strict';

    var STORAGE_KEY = 'adminMenuState';

    function readState(storage) {
        try {
            var parsed = JSON.parse(storage.getItem(STORAGE_KEY) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function writeState(storage, state) {
        try {
            storage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* storage blocked or full: the menu still works for this page view */
        }
    }

    function getStorage(win) {
        try {
            return win.localStorage || null;
        } catch (e) {
            return null;
        }
    }

    function init(nav, storage) {
        if (!nav) {
            return null;
        }
        var memory = storage ? readState(storage) : {};
        var groups = Array.prototype.slice.call(nav.querySelectorAll('.adminMenuGroup'));
        var allButton = nav.querySelector('.adminMenuAll');
        var entries = [];

        groups.forEach(function (group) {
            var toggle = group.querySelector('.adminMenuToggle');
            var list = group.querySelector('.adminMenuList');
            if (!toggle || !list) {
                return;
            }
            var id = group.getAttribute('data-menu-group');
            var isCurrent = group.getAttribute('data-current') === '1';
            var entry = {id: id, toggle: toggle, list: list, current: isCurrent};
            entries.push(entry);
            // The current page's category always starts open; others follow the saved state, closed by default.
            setOpen(entry, isCurrent || memory[id] === true, false);
            toggle.addEventListener('click', function () {
                setOpen(entry, toggle.getAttribute('aria-expanded') !== 'true', true);
            });
        });

        function setOpen(entry, open, persist) {
            entry.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            entry.list.hidden = !open;
            if (persist) {
                memory[entry.id] = open;
                if (storage) {
                    writeState(storage, memory);
                }
            }
            refreshAll();
        }

        function refreshAll() {
            if (!allButton) {
                return;
            }
            var anyClosed = entries.some(function (entry) {
                return entry.toggle.getAttribute('aria-expanded') !== 'true';
            });
            allButton.textContent = anyClosed ? 'Expand all' : 'Collapse all';
            allButton.setAttribute('data-mode', anyClosed ? 'expand' : 'collapse');
        }

        if (allButton) {
            allButton.hidden = false;
            allButton.addEventListener('click', function () {
                var open = allButton.getAttribute('data-mode') === 'expand';
                entries.forEach(function (entry) {
                    // Collapse all keeps the current page's category open so the page stays located.
                    setOpen(entry, open || entry.current, true);
                });
            });
            refreshAll();
        }
        return entries;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {init: init, STORAGE_KEY: STORAGE_KEY};
    } else if (root.document) {
        init(root.document.querySelector('.adminMenu'), getStorage(root));
    }
}(typeof window !== 'undefined' ? window : this));
