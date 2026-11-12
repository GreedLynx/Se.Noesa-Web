const assert = require('node:assert/strict');
const fs = require('node:fs');

const source = fs.readFileSync('public/admin/api/orders.php', 'utf8');
const listQueryStart = source.indexOf('$items = q("SELECT p.id');
const listQueryEnd = source.indexOf('[$search, $search])->fetchAll();', listQueryStart);
const listQuery = source.slice(listQueryStart, listQueryEnd);
const orderBy = listQuery.match(/ORDER BY([\s\S]*)/)?.[1];

assert.ok(orderBy, 'admin order list query has an ORDER BY clause');
assert.match(listQuery, /CASE p\.status WHEN 'menunggu_pembayaran' THEN 0 WHEN 'menunggu_validasi' THEN 1 WHEN 'diproses' THEN 2 WHEN 'selesai' THEN 3 ELSE 4 END/);
assert.match(
    orderBy,
    /CASE WHEN p\.status = 'selesai' THEN COALESCE\(p\.status_updated_at, p\.created_at\) END DESC/,
    'completed orders must sort by their latest status date, falling back to creation date',
);
assert.match(orderBy, /p\.created_at DESC/, 'keep creation date as the tie-breaker and other-status order');

console.log('PASS: Admin completed orders sort by latest status date.');
