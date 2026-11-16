const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

// Run the real endpoint validation/transactions with a query double, without application config.
const phpHarness = `
$payload = json_decode($argv[1], true);
$missing = $argv[2] === 'missing';
$queries = [];
$connection = new class {
    public bool $active = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    function beginTransaction() { $this->active = true; }
    function inTransaction() { return $this->active; }
    function commit() { $this->active = false; $this->commits++; }
    function rollBack() { $this->active = false; $this->rollbacks++; }
};
function db() { return $GLOBALS['connection']; }
function body() { return $GLOBALS['payload']; }
function require_role($role) {
    if ($role !== 'admin') throw new RuntimeException('Unexpected role');
}
function q($sql, $params = []) {
    $GLOBALS['queries'][] = ['sql' => $sql, 'params' => $params];
    return new class {
        function rowCount() { return $GLOBALS['missing'] ? 0 : 1; }
    };
}
function json($data, $status = 200) {
    echo json_encode(['data' => $data, 'status' => $status,
        'queries' => $GLOBALS['queries'], 'db' => db()]);
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$source = file_get_contents('public/admin/api/holidays.php');
$source = preg_replace('/^require __DIR__ .*;\\R/m', '', $source);
eval('?>' . $source);
`;
function callApi(payload, missing = false) {
    return JSON.parse(execFileSync('php', ['-r', phpHarness, JSON.stringify(payload), missing ? 'missing' : 'found'], { encoding: 'utf8' }));
}
const edit = { action: 'save', id: 7, tanggal_mulai: '2030-11-20', tanggal_selesai: '2030-11-23' };
const saved = callApi(edit);
assert.equal(saved.status, 200);
assert.match(saved.queries[0].sql, /LOCK TABLE tanggal_libur_operasional/);
assert.match(saved.queries[1].sql, /UPDATE tanggal_libur_operasional[\s\S]*WHERE id = \?/);
assert.deepEqual(saved.queries[1].params, ['2030-11-20', '2030-11-23', 7]);
assert.equal(saved.queries.length, 2);
assert.equal(saved.db.commits, 1);
for (const id of [0, -1, '', null, 'invalid', 1.5]) {
    const response = callApi({ ...edit, id });
    assert.equal(response.status, 422);
    assert.equal(response.queries.length, 1, 'Invalid ID must not write');
    assert.equal(response.db.rollbacks, 1);
}
for (const dates of [
    { tanggal_mulai: '2030-02-30' },
    { tanggal_mulai: '0000-01-01' },
    { tanggal_selesai: '2030-11-19' },
]) {
    const response = callApi({ ...edit, ...dates });
    assert.equal(response.status, 422);
    assert.equal(response.queries.length, 1, 'Invalid dates must not write');
}
const missing = callApi(edit, true);
assert.equal(missing.status, 404);
assert.equal(missing.db.rollbacks, 1);
assert.equal(missing.db.commits, 0);
const { id, ...create } = edit;
assert.match(callApi(create).queries[1].sql, /INSERT INTO tanggal_libur_operasional/);

const holiday = { id: 7, tanggal_mulai: '2030-11-20', tanggal_selesai: '2030-11-23' };
const elements = Object.fromEntries(['id', 'tanggal_mulai', 'tanggal_selesai'].map(key => [key, { value: '' }]));
const form = { elements, reset() { Object.values(elements).forEach(input => { input.value = ''; }); } };
const dialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
const editButton = { dataset: { editHoliday: '7' } };
const dom = {
    '#holiday-form': form, '#holiday-dialog': dialog, '#holiday-form-title': {},
    '#holiday-error': {}, '#holiday-rows': {}, '#new-holiday': {},
};
let request, fail = false;
const context = {
    $: selector => dom[selector],
    $$: selector => selector === '[data-edit-holiday]' ? [editButton] : [],
    esc: String, toast() {},
    api: async (url, options) => {
        if (!options) return { items: [holiday] };
        request = JSON.parse(JSON.stringify(options.body));
        if (fail) throw new Error('Tanggal libur tidak ditemukan');
        return { ok: true };
    },
};
vm.createContext(context);
const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
vm.runInContext(source.slice(source.indexOf('async function loadHolidays()'), source.indexOf('function shell()')), context);
(async () => {
    await context.schedulesPage();
    await context.loadHolidays();
    assert.match(dom['#holiday-rows'].innerHTML, /data-edit-holiday="7"/);
    editButton.onclick();
    assert.equal(elements.id.value, 7);
    assert.equal(elements.tanggal_mulai.value, holiday.tanggal_mulai);
    assert.equal(elements.tanggal_selesai.value, holiday.tanggal_selesai);
    assert.equal(dom['#holiday-form-title'].textContent, 'Edit tanggal libur');
    elements.tanggal_selesai.value = '2030-11-24';
    await form.onsubmit({ preventDefault() {} });
    assert.deepEqual(request, { ...edit, tanggal_selesai: '2030-11-24' });
    assert.equal(dialog.open, false);
    dom['#new-holiday'].onclick();
    assert.equal(elements.id.value, '');
    assert.equal(elements.tanggal_mulai.value, '');
    elements.tanggal_mulai.value = '2030-12-01';
    elements.tanggal_selesai.value = '2030-12-02';
    await form.onsubmit({ preventDefault() {} });
    assert.equal('id' in request, false, 'Add after edit must create a new range');
    editButton.onclick();
    fail = true;
    await form.onsubmit({ preventDefault() {} });
    assert.equal(dialog.open, true);
    assert.equal(elements.id.value, 7);
    assert.equal(dom['#holiday-error'].textContent, 'Tanggal libur tidak ditemukan');
    console.log('PASS: holiday edit/add API validation and transactions (query double), form prefill/reset/error flow');
})().catch(error => { console.error(error); process.exitCode = 1; });
