const assert = require('node:assert/strict');
const fs = require('node:fs');

const root = `${__dirname}/../..`;
const clientFiles = [
    'game/deployment/public/game-client.js',
];

for (const clientFile of clientFiles) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    const start = source.indexOf('setupInfoboxItemsDeletionWithMessage: function (e, t) {');
    const end = source.indexOf('\n    updateGold: function', start);

    assert.notEqual(start, -1, `${clientFile} must retain infobox-delete bindings.`);
    assert.notEqual(end, -1, `${clientFile} must retain the infobox-delete binding boundary.`);

    const deletionBinding = source.slice(start, end);
    assert.match(
        deletionBinding,
        /Travian\.api\(\s*"infobox",\s*\{[\s\S]*?success:\s*function\s*\(\)\s*\{\s*Travian\.autoreload\(\);\s*\},\s*error:\s*function\s*\(e\)\s*\{\s*new Travian\.Dialog\.Dialog\(\{ preventFormSubmit: !0 \}\)\s*\.setContent\(e\.message\)\s*\.show\(\);\s*\}/,
        `${clientFile} must show a rejected infobox-delete error.`,
    );
}

console.log('Infobox delete client recovery contract passed.');
