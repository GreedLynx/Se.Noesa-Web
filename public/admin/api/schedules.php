<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    error_log('[Se.Noesa schedules API] ' . $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Kesalahan server saat menyimpan jadwal PO: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request = body();
    $action = $request['action'] ?? 'save';

    if ($action === 'delete') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) {
            json(['error' => 'ID tidak valid'], 422);
        }
        $affectedRows = q('DELETE
            FROM jadwal_po
            WHERE id = ?', [$id])->rowCount();
        json($affectedRows ? ['ok' => true] : ['error' => 'Jadwal PO tidak ditemukan'], $affectedRows ? 200 : 404);
    }

    if ($action === 'toggle') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) {
            json(['error' => 'ID tidak valid'], 422);
        }
        $aktif = filter_var($request['aktif'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($aktif === null) {
            json(['error' => 'aktif harus boolean'], 422);
        }
        q('UPDATE jadwal_po
            SET aktif = ?
            WHERE id = ?', [$aktif, $id]);
        json(['ok' => true]);
    }

    // action=save (default)
    $id = isset($request['id']) ? filter_var($request['id'], FILTER_VALIDATE_INT) : null;
    if (isset($request['id']) && $id === false) {
        json(['error' => 'ID tidak valid'], 422);
    }

    $nama = trim($request['nama'] ?? '');
    if (!is_string($nama) || $nama === '' || mb_strlen($nama) > 80) {
        json(['error' => 'nama wajib diisi, maks 80 karakter'], 422);
    }

    $tglBuka = $request['tgl_buka'] ?? '';
    if (!is_string($tglBuka) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglBuka)) {
        json(['error' => 'tgl_buka harus format YYYY-MM-DD'], 422);
    }
    $parsedBuka = date_create_from_format('Y-m-d', $tglBuka);
    if (!$parsedBuka || $parsedBuka->format('Y-m-d') !== $tglBuka) {
        json(['error' => 'tgl_buka tidak valid'], 422);
    }

    $tglTutup = $request['tgl_tutup'] ?? '';
    if (!is_string($tglTutup) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglTutup)) {
        json(['error' => 'tgl_tutup harus format YYYY-MM-DD'], 422);
    }
    $parsedTutup = date_create_from_format('Y-m-d', $tglTutup);
    if (!$parsedTutup || $parsedTutup->format('Y-m-d') !== $tglTutup) {
        json(['error' => 'tgl_tutup tidak valid'], 422);
    }

    if ($parsedBuka > $parsedTutup) {
        json(['error' => 'tgl_buka harus <= tgl_tutup'], 422);
    }

    $kapasitas = filter_var($request['kapasitas'] ?? null, FILTER_VALIDATE_INT);
    if ($kapasitas === false || $kapasitas < 1) {
        json(['error' => 'kapasitas harus angka >= 1'], 422);
    }

    $aktif = isset($request['aktif']) ? filter_var($request['aktif'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
    if ($aktif === null) {
        json(['error' => 'aktif harus boolean'], 422);
    }

    if ($id) {
        q('UPDATE jadwal_po
            SET nama = ?, tgl_buka = ?, tgl_tutup = ?, kapasitas = ?, aktif = ?
            WHERE id = ?', [$nama, $tglBuka, $tglTutup, $kapasitas, $aktif, $id]);
    } else {
        q('INSERT INTO jadwal_po (nama, tgl_buka, tgl_tutup, kapasitas, aktif)
            VALUES (?, ?, ?, ?, ?)', [$nama, $tglBuka, $tglTutup, $kapasitas, $aktif]);
    }
    json(['ok' => true]);
}

$rows = q('SELECT id, nama, tgl_buka, tgl_tutup, kapasitas, aktif
    FROM jadwal_po
    ORDER BY tgl_buka DESC')->fetchAll();
json(['items' => $rows]);
