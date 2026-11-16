<?php
// Uji concurrency pembuatan notifikasi perubahan menu.
// Dua proses PHP memakai koneksi PostgreSQL independen; proses pertama menahan
// transaksi (dan advisory lock) saat proses kedua mencoba membuat notifikasi.
// Tidak membaca .env aplikasi dan hanya memakai database uji terisolasi.
//
// Environment: SENOESA_TEST_PGPORT (wajib), SENOESA_TEST_PGDB
// (default senoesa_cancellation_test), SENOESA_TEST_PGUSER (default postgres),
// SENOESA_TEST_PGPASSWORD (default kosong).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}
ini_set('zend.exception_ignore_args', '1');
ini_set('session.save_path', sys_get_temp_dir());

function concurrency_config(): array
{
    $port = getenv('SENOESA_TEST_PGPORT');
    if (!$port || !ctype_digit($port)) {
        throw new RuntimeException(
            'Isi SENOESA_TEST_PGPORT untuk PostgreSQL uji; siapkan database uji '
            . '(default senoesa_cancellation_test).'
        );
    }

    return ['db' => [
        'host' => '127.0.0.1',
        'port' => $port,
        'name' => getenv('SENOESA_TEST_PGDB') ?: 'senoesa_cancellation_test',
        'user' => getenv('SENOESA_TEST_PGUSER') ?: 'postgres',
        'pass' => getenv('SENOESA_TEST_PGPASSWORD') ?: '',
        'sslmode' => 'disable',
    ]];
}

// Gunakan bootstrap asli dengan satu penggantian sumber konfigurasi khusus uji.
$bootstrap = file_get_contents(__DIR__ . '/../app/bootstrap.php');
$bootstrap = str_replace("require __DIR__ . '/../config/config.php'", 'concurrency_config()', $bootstrap);
eval('?>' . $bootstrap);
require_once __DIR__ . '/../app/menu-notifications.php';

class ConcurrencyResponse extends RuntimeException
{
    public function __construct(public array $data, public int $status) {}
}

function concurrency_noop_handler(callable $handler): void {}

function concurrency_source(string $path): string
{
    $source = file_get_contents($path);
    // Bootstrap dan helper sudah dimuat induk; cegah deklarasi ulang di proses anak.
    return preg_replace('/^require(?:_once)? __DIR__ .*;\R/m', '', $source);
}

if (($argv[1] ?? '') === '--create') {
    $schema = (string) ($argv[2] ?? '');
    $productId = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT);
    $note = (string) ($argv[4] ?? '');
    if (!preg_match('/^mnconc_test_[a-f0-9]+$/D', $schema) || $productId === false || $productId < 1) {
        fwrite(STDERR, "Argumen proses anak tidak valid\n");
        exit(3);
    }

    q('SELECT set_config(?, ?, false)', ['search_path', $schema]);
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action' => 'create',
        'produk_id' => $productId,
        'catatan_admin' => $note,
        'opsi' => ['hubungi_admin'],
    ];

    $source = concurrency_source(__DIR__ . '/../public/admin/api/menu-notifications.php');
    $source = preg_replace(
        '/function api_json\(\$data, int \$code = 200\): void\s*\{.*?\n\}/s',
        'function api_json($data, int $code = 200): void { throw new ConcurrencyResponse($data, $code); }',
        $source
    );
    // Biarkan exception naik ke harness agar rollback endpoint tetap diuji apa adanya.
    $source = str_replace('set_exception_handler(', 'concurrency_noop_handler(', $source);

    try {
        eval('?>' . $source);
    } catch (ConcurrencyResponse $response) {
        echo json_encode(['status' => $response->status, 'data' => $response->data]);
        exit(0);
    } catch (Throwable $error) {
        echo json_encode([
            'status' => $error instanceof DomainException ? $error->getCode() : 500,
            'data' => ['error' => $error->getMessage()],
        ]);
        exit(0);
    }

    echo json_encode(['status' => 500, 'data' => ['error' => 'Endpoint tidak menghasilkan respons']]);
    exit(0);
}

function concurrency_child(string $schema, int $productId, string $note): array
{
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--create', $schema, (string) $productId, $note],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Gagal menjalankan proses uji');
    }

    return [$process, $pipes];
}

function concurrency_finish(array $child): array
{
    [$process, $pipes] = $child;
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $decoded = json_decode(trim($output), true);

    return [
        'exit' => $exit,
        'output' => trim($output),
        'errors' => trim($errors),
        'status' => is_array($decoded) ? (int) ($decoded['status'] ?? 500) : 500,
        'data' => is_array($decoded) && is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
    ];
}

// Key publik yang harus stabil antar-deploy; fallback menjaga uji tetap bisa
// dijalankan sebelum konstanta produksi ditambahkan (RED).
function concurrency_namespace(): int
{
    return defined('MENU_NOTICE_CREATE_LOCK_NAMESPACE')
        ? (int) constant('MENU_NOTICE_CREATE_LOCK_NAMESPACE')
        : 0x4D4E4F54;
}

function concurrency_lock_state(int $productId): array
{
    $row = q(
        "SELECT COUNT(*) FILTER (WHERE granted) AS granted,
                COUNT(*) FILTER (WHERE NOT granted) AS waiting
         FROM pg_locks
         WHERE locktype = 'advisory' AND objsubid = 2
           AND classid = ?::oid AND objid = ?::oid",
        [concurrency_namespace(), $productId]
    )->fetch();

    return [(int) $row['granted'], (int) $row['waiting']];
}

function concurrency_wait_lock(int $productId, array $child, bool $waiting, string $message): void
{
    $deadline = microtime(true) + 10.0;
    while (microtime(true) < $deadline) {
        $state = concurrency_lock_state($productId);
        if ($waiting ? $state[1] >= 1 : $state[0] >= 1) {
            return;
        }
        if (!proc_get_status($child[0])['running']) {
            $result = concurrency_finish($child);
            throw new RuntimeException(
                $message . ' â€” proses anak berhenti lebih awal: HTTP ' . $result['status']
                . ' ' . $result['output'] . ' ' . $result['errors']
            );
        }
        usleep(50000);
    }
    throw new RuntimeException($message);
}

function concurrency_check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function concurrency_server_side_notification(int $productId): int
{
    return (int) q(
        'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu WHERE produk_id = ?',
        [$productId]
    )->fetchColumn();
}

$schema = null;

try {
    putenv('SENOESA_TEST_NOW=2031-01-01T00:00:00+07:00');
    $schema = 'mnconc_test_' . bin2hex(random_bytes(6));
    q('CREATE SCHEMA ' . $schema);
    q('SELECT set_config(?, ?, false)', ['search_path', $schema]);

    $schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $schemaSql = substr($schemaSql, 0, strpos($schemaSql, '-- SEED'));
    db()->exec($schemaSql);
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
        db()->exec(file_get_contents($file));
    }

    q("INSERT INTO users (id, username, email, password_hash, role)
       VALUES (1, 'admin-c5', 'admin-c5@example.invalid', 'x', 'admin'),
              (10, 'cust-c5', 'cust-c5@example.invalid', 'x', 'pelanggan')");
    q("INSERT INTO produk (id, kode, nama, kategori, harga, kuota_harian)
       VALUES (1, 'C5', 'Menu Concurrency', 'makanan', 12000, 0)");
    q("INSERT INTO pesanan (id, kode, user_id, nama_pemesan, status, sumber, total, tgl_kirim)
       VALUES (1, 'C5-1', 10, 'Pelanggan C5', 'diproses', 'online', 48000, '2031-01-10 12:00+07')");
    q('INSERT INTO detail_pesanan (id, pesanan_id, produk_id, qty, harga) VALUES (1, 1, 1, 4, 12000)');

    // Trigger membuat pembuatan notifikasi pertama menahan transaksi cukup lama
    // agar Admin kedua benar-benar menunggu lock, bukan sekadar berurutan.
    q("CREATE OR REPLACE FUNCTION c5_slow_notice() RETURNS trigger AS \$\$
       BEGIN
         IF NEW.catatan_admin LIKE 'C5-WAIT%' OR NEW.catatan_admin LIKE 'C5-FAIL%' THEN
           PERFORM pg_sleep(3);
         END IF;
         IF NEW.catatan_admin LIKE 'C5-FAIL%' THEN
           RAISE EXCEPTION 'simulasi gagal membuat notifikasi';
         END IF;
         RETURN NEW;
       END;
       \$\$ LANGUAGE plpgsql");
    q('CREATE TRIGGER c5_slow_notice BEFORE INSERT ON pemberitahuan_perubahan_menu
       FOR EACH ROW EXECUTE FUNCTION c5_slow_notice()');

    // Skenario 1: dua Admin membuat notifikasi untuk produk yang sama.
    $adminA = concurrency_child($schema, 1, 'C5-WAIT-A');
    concurrency_wait_lock(1, $adminA, false, 'Admin A tidak memegang advisory lock pembuatan notifikasi');
    $adminB = concurrency_child($schema, 1, 'C5-WAIT-B');
    concurrency_wait_lock(1, $adminB, true, 'Admin B tidak menunggu lock Admin A; transaksi tidak benar-benar berkompetisi');
    $contention = concurrency_lock_state(1);
    concurrency_check(
        $contention[0] >= 1 && $contention[1] >= 1,
        'Dua koneksi berkompetisi: Admin A memegang lock, Admin B menunggu di pg_locks'
    );
    $resultA = concurrency_finish($adminA);
    $resultB = concurrency_finish($adminB);
    concurrency_check(
        $resultA['exit'] === 0 && $resultB['exit'] === 0,
        'Kedua proses Admin selesai tanpa error proses'
    );

    concurrency_check(
        defined('MENU_NOTICE_CREATE_LOCK_NAMESPACE')
        && (int) constant('MENU_NOTICE_CREATE_LOCK_NAMESPACE') === 0x4D4E4F54,
        'Namespace advisory lock pembuatan notifikasi konsisten (0x4D4E4F54)'
    );
    $statuses = [$resultA['status'], $resultB['status']];
    sort($statuses);
    $loser = $resultA['status'] === 422 ? $resultA : $resultB;
    concurrency_check(
        $statuses === [201, 422],
        'Tepat satu Admin berhasil membuat notifikasi dan Admin kedua mendapat 422'
    );
    concurrency_check(
        str_contains((string) ($loser['data']['error'] ?? ''), 'belum dinotifikasi')
        && (int) ($loser['data']['dilewati_sudah_dinotifikasi'] ?? 0) === 1,
        'Admin kedua mendapat hasil jelas: target diperiksa ulang setelah lock'
    );
    concurrency_check(
        concurrency_server_side_notification(1) === 1
        && (int) q(
            'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu_pesanan
             WHERE detail_pesanan_id = 1'
        )->fetchColumn() === 1
        && (int) q(
            'SELECT COUNT(DISTINCT pemberitahuan_id) FROM pemberitahuan_perubahan_menu_pesanan
             WHERE detail_pesanan_id = 1'
        )->fetchColumn() === 1,
        'Hanya satu notifikasi aktif dan tidak ada duplikasi penerima untuk detail yang sama'
    );

    // Skenario 2: Admin A rollback; Admin B tetap dapat membuat notifikasi.
    q('DELETE FROM pemberitahuan_perubahan_menu_pesanan');
    q('DELETE FROM pemberitahuan_perubahan_menu_opsi');
    q('DELETE FROM pemberitahuan_perubahan_menu');

    $failingA = concurrency_child($schema, 1, 'C5-FAIL-A');
    concurrency_wait_lock(1, $failingA, false, 'Admin A (rollback) tidak memegang advisory lock');
    $waitingB = concurrency_child($schema, 1, 'C5-OK-B');
    concurrency_wait_lock(1, $waitingB, true, 'Admin B tidak menunggu lock Admin A pada skenario rollback');
    $rollbackContention = concurrency_lock_state(1);
    concurrency_check(
        $rollbackContention[0] >= 1 && $rollbackContention[1] >= 1,
        'Rollback: Admin A memegang lock dan Admin B menunggu sebelum rollback'
    );
    $resultFail = concurrency_finish($failingA);
    $resultOk = concurrency_finish($waitingB);
    concurrency_check(
        $resultFail['status'] === 500
        && $resultOk['status'] === 201
        && concurrency_server_side_notification(1) === 1
        && q('SELECT catatan_admin FROM pemberitahuan_perubahan_menu WHERE produk_id = 1')->fetchColumn() === 'C5-OK-B'
        && (int) q(
            'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu_pesanan WHERE detail_pesanan_id = 1'
        )->fetchColumn() === 1,
        'Rollback Admin A melepas lock dan Admin B berhasil membuat notifikasi'
    );

    // Skenario 3: notifikasi baru tetap boleh dibuat setelah yang lama selesai.
    $completedId = (int) q(
        'SELECT id FROM pemberitahuan_perubahan_menu_pesanan WHERE detail_pesanan_id = 1'
    )->fetchColumn();
    q("UPDATE pemberitahuan_perubahan_menu_pesanan SET status_respons = 'selesai' WHERE id = ?", [$completedId]);

    $resultAfterDone = concurrency_finish(concurrency_child($schema, 1, 'C5-AFTER-DONE'));
    concurrency_check(
        $resultAfterDone['status'] === 201
        && concurrency_server_side_notification(1) === 2
        && (int) q(
            'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu_pesanan
             WHERE detail_pesanan_id = 1 AND status_respons = \'selesai\''
        )->fetchColumn() === 1
        && (int) q(
            'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu_pesanan
             WHERE detail_pesanan_id = 1 AND status_respons = \'menunggu_respons\''
        )->fetchColumn() === 1,
        'Notifikasi baru dapat dibuat setelah notifikasi sebelumnya selesai tanpa menghapus riwayat'
    );

    echo "Seluruh uji concurrency notifikasi lulus.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($schema !== null) {
        try {
            db()->exec('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
        } catch (Throwable $ignored) {
        }
    }
}

