<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function valid_holiday_date($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || substr($value, 0, 4) === '0000') {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    return $date && $date->format('Y-m-d') === $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request = body();
    $action = $request['action'] ?? 'save';
    $db = db();
    $db->beginTransaction();
    try {
        q('LOCK TABLE tanggal_libur_operasional IN SHARE ROW EXCLUSIVE MODE');

        if ($action === 'delete') {
            $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                $db->rollBack();
                json(['error' => 'ID tanggal libur tidak valid'], 422);
            }
            $deleted = q('DELETE FROM tanggal_libur_operasional WHERE id = ?', [$id])->rowCount();
            $db->commit();
            json($deleted ? ['ok' => true] : ['error' => 'Tanggal libur tidak ditemukan'], $deleted ? 200 : 404);
        }

        if ($action !== 'save') {
            $db->rollBack();
            json(['error' => 'Aksi tidak valid'], 422);
        }

        $id = null;
        if (array_key_exists('id', $request)) {
            $id = filter_var($request['id'], FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                $db->rollBack();
                json(['error' => 'ID tanggal libur tidak valid'], 422);
            }
        }

        $start = $request['tanggal_mulai'] ?? '';
        $end = $request['tanggal_selesai'] ?? '';
        if (!valid_holiday_date($start) || !valid_holiday_date($end)) {
            $db->rollBack();
            json(['error' => 'Tanggal libur harus berupa tanggal valid YYYY-MM-DD'], 422);
        }
        if ($start > $end) {
            $db->rollBack();
            json(['error' => 'Tanggal mulai harus sama dengan atau sebelum tanggal akhir'], 422);
        }

        if ($id !== null) {
            $updated = q('UPDATE tanggal_libur_operasional
                SET tanggal_mulai = ?, tanggal_selesai = ?
                WHERE id = ?', [$start, $end, $id])->rowCount();
            if (!$updated) {
                $db->rollBack();
                json(['error' => 'Tanggal libur tidak ditemukan'], 404);
            }
        } else {
            q('INSERT INTO tanggal_libur_operasional (tanggal_mulai, tanggal_selesai) VALUES (?, ?)', [$start, $end]);
        }
        $db->commit();
        json(['ok' => true]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[Se.Noesa holidays API] ' . $error->getMessage());
        json(['error' => 'Tanggal libur belum dapat disimpan'], 500);
    }
}

$rows = q('SELECT id, tanggal_mulai, tanggal_selesai
    FROM tanggal_libur_operasional
    ORDER BY tanggal_mulai DESC, id DESC')->fetchAll();
json(['items' => $rows]);
