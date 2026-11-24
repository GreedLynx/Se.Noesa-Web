const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const root = path.join('public', 'assets', 'component');
const css = fs.readFileSync(path.join(root, 'icons.css'), 'utf8');
const icons = fs.readdirSync(root).filter(name => name.endsWith('.svg'));
assert.equal(icons.length, 46);
for (const name of icons) {
    const slug = name.slice(0, -4);
    const svg = fs.readFileSync(path.join(root, name), 'utf8');
    assert.match(svg, /viewBox="0 0 24 24"/);
    assert.match(svg, /fill="none"/);
    assert.ok(css.includes(`.icon-${slug} { --ui-icon: url("./${name}"); }`));
}

const source = fs.readFileSync('public/assets/js/app.js', 'utf8');
const begin = source.indexOf('/* toggle password */');
const end = source.indexOf('// UPDATE LOGOUT', begin);
assert.ok(begin >= 0 && end > begin);
const classes = new Set(['icon-mata']);
const input = {type: 'password', name: 'password'};
const button = {
    previousElementSibling: input,
    label: '',
    setAttribute(name, value) { if (name === 'aria-label') this.label = value; },
    querySelector() { return {classList: {toggle(name, active) {
        if (active) classes.add(name); else classes.delete(name);
    }}}; },
};
vm.runInNewContext(source.slice(begin, end), {$$: () => [button]});
button.onclick();
assert.equal(input.type, 'text');
assert.equal(button.label, 'Sembunyikan password');
assert.deepEqual([...classes], ['icon-mata-tutup']);
button.onclick();
assert.equal(input.type, 'password');
assert.equal(button.label, 'Tampilkan password');
assert.deepEqual([...classes], ['icon-mata']);
console.log('PASS: 46 aset dan stylesheet cocok; tombol mata mengubah ikon serta label aksesibel');
