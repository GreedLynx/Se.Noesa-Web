const assert = require('node:assert/strict');
const fs = require('node:fs');

const source = fs.readFileSync('public/admin/api/orders.php', 'utf8');

// Implementasi memakai variabel $orderBy yang diinterpolasi ke query $items,
// jadi urutan harus dibaca dari assignment $orderBy, bukan dari isi query.
const listQueryStart = source.indexOf('$items = q("SELECT p.id');
assert.ok(listQueryStart >= 0, 'admin order list query exists');
const listQueryEnd = source.indexOf('ORDER BY $orderBy', listQueryStart);
assert.ok(listQueryEnd > listQueryStart, 'admin order list query uses interpolated $orderBy');

const orderByStart = source.indexOf('$orderBy =');
assert.ok(orderByStart >= 0, '$orderBy assignment exists');
assert.ok(orderByStart < listQueryEnd, '$orderBy is assigned before the list query uses it');

const orderByEnd = source.indexOf(';', orderByStart);
assert.ok(orderByEnd > orderByStart, '$orderBy assignment is terminated');
const orderByBlock = source.slice(orderByStart, orderByEnd + 1);

// Tab pembatalan memakai waktu batal terbaru, bukan urutan status.
assert.match(
  orderByBlock,
  /\$tab === 'dibatalkan'\s*\?\s*'p\.status_updated_at DESC NULLS LAST, p\.id DESC'/,
  'cancelled tab sorts by cancellation time then id',
);

const defaultBranch = orderByBlock.match(/:\s*"([\s\S]*?)";/)?.[1];
assert.ok(defaultBranch, '$orderBy has a non-cancelled branch');

const statusRank = {
  menunggu_pembayaran: 0,
  menunggu_validasi: 1,
  diproses: 2,
  selesai: 3,
};
for (const [status, rank] of Object.entries(statusRank)) {
  assert.match(
    defaultBranch,
    new RegExp(`WHEN '${status}' THEN ${rank}`),
    `${status} has rank ${rank} in the status CASE`,
  );
}
assert.match(
  defaultBranch,
  /ELSE 4 END/,
  'rejected and cancelled statuses fall back to the last rank',
);
assert.match(
  defaultBranch,
  /CASE WHEN p\.status = 'selesai' THEN COALESCE\(p\.status_updated_at, p\.created_at\) END DESC/,
  'completed orders must sort by their latest status date, falling back to creation date',
);
assert.match(
  defaultBranch,
  /p\.created_at DESC/,
  'keep creation date as the tie-breaker and other-status order',
);

// Urutan klausa harus stabil: rank status, lalu tanggal selesai, lalu created_at.
const rankIndex = defaultBranch.indexOf('CASE p.status');
const completedIndex = defaultBranch.indexOf("CASE WHEN p.status = 'selesai'");
const createdIndex = defaultBranch.lastIndexOf('p.created_at DESC');
assert.ok(
  rankIndex >= 0 && rankIndex < completedIndex && completedIndex < createdIndex,
  'ORDER BY clauses are ordered: status rank, completed date, created date',
);

console.log('PASS: Admin completed orders sort by latest status date.');
