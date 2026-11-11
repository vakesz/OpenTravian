const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = `${__dirname}/../..`;
const clientFiles = [
    'game/deployment/public/game-client.js',
];

function draggableConstructor(source) {
    const startMarker = '(Travian.Draggable = function (e, t) {';
    const endMarker = '\n  (Travian.Moveable = function';
    const start = source.indexOf(startMarker);
    const end = source.indexOf(endMarker, start);

    assert.notEqual(start, -1, 'Missing the draggable constructor.');
    assert.notEqual(end, -1, 'Missing the movable constructor after Draggable.');

    return source
        .slice(start, end)
        .replace(startMarker, 'function Draggable(e, t) {')
        .replace(/\}\),\s*$/, '}');
}

for (const clientFile of clientFiles) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    const removedTouchHandlers = [];
    const element = {
        parent() {
            return this;
        },
        on() {
            return this;
        },
        off() {
            return this;
        },
    };
    const documentWrapper = {
        off() {
            return this;
        },
    };
    const document = {
        selectstart: true,
        removeEventListener(name, callback) {
            removedTouchHandlers.push({name, callback});
        },
    };
    const sandbox = {
        Object,
        document,
        jQuery(value) {
            return value === document ? documentWrapper : value;
        },
    };
    let stoppedWith;

    vm.runInNewContext(draggableConstructor(source), sandbox, {filename: clientFile});
    const draggable = new sandbox.Draggable(element, {
        handle: element,
        onStop(target, event) {
            stoppedWith = {target, event};
        },
    });
    const event = {type: 'mouseup'};

    draggable.stop(event);
    draggable.cancel();

    assert.deepEqual(stoppedWith, {target: element, event}, `${clientFile} must run a drag stop hook.`);
    assert.deepEqual(
        removedTouchHandlers,
        [
            {name: 'touchmove', callback: draggable.bound.drag},
            {name: 'touchmove', callback: draggable.bound.check},
        ],
        `${clientFile} must release touch handlers after dragging or cancelling.`,
    );
}

console.log('Dialog drag client recovery contract passed.');
