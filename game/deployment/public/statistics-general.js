(() => {
    'use strict';

    const root = document.getElementById('statisticsV2');
    if (!root) {
        return;
    }

    const setActive = (nodes, value, attribute) => {
        nodes.forEach((node) => node.classList.toggle('active', node.getAttribute(attribute) === value));
    };
    const setHidden = (nodes, predicate) => {
        nodes.forEach((node) => {
            node.hidden = !predicate(node);
        });
    };

    const resource = { period: 'per-day', mode: 'production' };
    const renderResource = () => {
        setActive(root.querySelectorAll('[data-statistics-resource-period]'), resource.period, 'data-statistics-resource-period');
        setHidden(root.querySelectorAll('[data-statistics-resource-rank]'), (node) => node.getAttribute('data-statistics-resource-rank') === resource.period);
        root.querySelectorAll('[data-statistics-resource-mode]').forEach((node) => {
            const enabled = resource.period === 'so-far' || node.getAttribute('data-statistics-resource-mode') === 'production';
            node.classList.toggle('disabled', !enabled);
            node.classList.toggle('active', enabled && node.getAttribute('data-statistics-resource-mode') === resource.mode);
        });
        setHidden(root.querySelectorAll('[data-statistics-resource-panel]'), (node) => node.getAttribute('data-statistics-resource-panel') === `${resource.period}:${resource.mode}`);
    };

    const strength = { mode: 'offence', villages: new Map() };
    const renderStrength = () => {
        setActive(root.querySelectorAll('[data-statistics-strength-mode]'), strength.mode, 'data-statistics-strength-mode');
        setHidden(root.querySelectorAll('[data-statistics-strength-mode-panel]'), (node) => node.getAttribute('data-statistics-strength-mode-panel') === strength.mode);
        const activePanel = root.querySelector(`[data-statistics-strength-mode-panel="${strength.mode}"]`);
        if (!activePanel) {
            return;
        }
        const selected = strength.villages.get(strength.mode) || activePanel.querySelector('[data-statistics-strength-village]')?.getAttribute('data-statistics-strength-village');
        if (!selected) {
            return;
        }
        strength.villages.set(strength.mode, selected);
        setActive(activePanel.querySelectorAll('[data-statistics-strength-village]'), selected, 'data-statistics-strength-village');
        setHidden(activePanel.querySelectorAll('[data-statistics-strength-rank]'), (node) => node.getAttribute('data-statistics-strength-rank') === selected);
        setHidden(activePanel.querySelectorAll('[data-statistics-strength-village-panel]'), (node) => node.getAttribute('data-statistics-strength-village-panel') === selected);
    };

    const culturePoints = { mode: 'comparison', period: 'per-day' };
    const renderCulturePoints = () => {
        setActive(root.querySelectorAll('[data-statistics-cp-mode]'), culturePoints.mode, 'data-statistics-cp-mode');
        setActive(root.querySelectorAll('[data-statistics-cp-period]'), culturePoints.period, 'data-statistics-cp-period');
        setHidden(root.querySelectorAll('[data-statistics-cp-mode-panel]'), (node) => node.getAttribute('data-statistics-cp-mode-panel') === culturePoints.mode);
        setHidden(root.querySelectorAll('[data-statistics-cp-period-panel]'), (node) => node.getAttribute('data-statistics-cp-period-panel') === culturePoints.period);
    };

    const activate = (event) => {
        const control = event.target.closest('[data-statistics-resource-period], [data-statistics-resource-mode], [data-statistics-strength-mode], [data-statistics-strength-village], [data-statistics-cp-mode], [data-statistics-cp-period]');
        if (!control || !root.contains(control)) {
            return;
        }
        event.preventDefault();
        if (control.hasAttribute('data-statistics-resource-period')) {
            resource.period = control.getAttribute('data-statistics-resource-period');
            if (resource.period === 'per-day') {
                resource.mode = 'production';
            }
            renderResource();
            return;
        }
        if (control.hasAttribute('data-statistics-resource-mode')) {
            if (control.classList.contains('disabled')) {
                return;
            }
            resource.mode = control.getAttribute('data-statistics-resource-mode');
            renderResource();
            return;
        }
        if (control.hasAttribute('data-statistics-strength-mode')) {
            strength.mode = control.getAttribute('data-statistics-strength-mode');
            renderStrength();
            return;
        }
        if (control.hasAttribute('data-statistics-strength-village')) {
            const panel = control.closest('[data-statistics-strength-mode-panel]');
            if (!panel) {
                return;
            }
            strength.mode = panel.getAttribute('data-statistics-strength-mode-panel');
            strength.villages.set(strength.mode, control.getAttribute('data-statistics-strength-village'));
            renderStrength();
            return;
        }
        if (control.hasAttribute('data-statistics-cp-mode')) {
            const mode = control.getAttribute('data-statistics-cp-mode');
            if (!root.querySelector(`[data-statistics-cp-mode-panel="${mode}"]`)) {
                return;
            }
            culturePoints.mode = mode;
            renderCulturePoints();
            return;
        }
        culturePoints.period = control.getAttribute('data-statistics-cp-period');
        renderCulturePoints();
    };

    root.addEventListener('click', activate);
    renderResource();
    renderStrength();
    renderCulturePoints();
})();
