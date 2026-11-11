const assert = require('node:assert/strict');
const fs = require('node:fs');

// Regression: Travian.Game.getDirection() does not exist; MobileOptimizations.init crashed
// on every page. getDirection lives on the top-level Travian object.
const root = `${__dirname}/../..`;
for (const file of ['game/deployment/public/game-client.js']) {
    const source = fs.readFileSync(`${root}/${file}`, 'utf8');
    assert.ok(!/Travian\.Game\.getDirection/.test(source), `${file} must not call Travian.Game.getDirection`);
    assert.ok(/Travian\.getDirection\(\)/.test(source), `${file} must call Travian.getDirection()`);
    assert.ok(/^\s*getDirection:\s*function/m.test(source), `${file} must define Travian.getDirection`);
}
console.log('Travian getDirection client contract passed.');
