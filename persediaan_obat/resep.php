<?php
require_once "config.php";
require_once "auth.php";
require_login();

/**
 * =========================
 * MODE CETAK (PRINT) - CETAK 1 TRANSAKSI (MULTI OBAT)
 * =========================
 * Cetak berdasarkan resep id -> ambil header (pasien, apoteker, member, created_at)
 * lalu ambil semua item resep yang created_at + pasien + apoteker + member sama.
 */
if (isset($_GET['print'])) {
    $id = (int)$_GET['print'];
    if ($id <= 0) die("ID tidak valid");

    // ambil "header" transaksi dari salah satu baris resep
    $hq = mysqli_query($conn, "
        SELECT r.*, a.nama AS nama_apoteker
        FROM resep r
        LEFT JOIN apoteker a ON r.apoteker_id=a.id
        WHERE r.id=$id
        LIMIT 1
    ");
    if (!$hq || mysqli_num_rows($hq) === 0) die("Data resep tidak ditemukan");
    $head = mysqli_fetch_assoc($hq);

    $created_at  = $head['created_at'];
    $nama_pasien = $head['nama_pasien'];
    $apoteker_id = $head['apoteker_id'];
    $member_id   = $head['member_id'];

    // ambil semua item dalam transaksi yang sama
    $whereApoteker = is_null($apoteker_id) || $apoteker_id === '' ? "IS NULL" : "= " . (int)$apoteker_id;
    $whereMember   = is_null($member_id)   || $member_id === ''   ? "IS NULL" : "= " . (int)$member_id;

    $iq = mysqli_query($conn, "
        SELECT r.*, o.nama AS nama_obat
        FROM resep r
        JOIN obat o ON r.obat_id=o.id
        WHERE r.created_at = '".mysqli_real_escape_string($conn, $created_at)."'
          AND r.nama_pasien = '".mysqli_real_escape_string($conn, $nama_pasien)."'
          AND r.apoteker_id $whereApoteker
          AND r.member_id   $whereMember
        ORDER BY r.id ASC
    ");

    if (!$iq || mysqli_num_rows($iq) === 0) die("Item transaksi tidak ditemukan");

    // hitung total keseluruhan
    $items = [];
    $grandTotal = 0;
    while ($r = mysqli_fetch_assoc($iq)) {
        $harga_satuan = (int)$r['harga'];              // asumsi harga satuan
        $total = $harga_satuan * (int)$r['jumlah'];
        $grandTotal += $total;
        $r['_harga_satuan'] = $harga_satuan;
        $r['_total'] = $total;
        $items[] = $r;
    }

    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cetak Resep</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 14px; }
            .wrap { max-width: 900px; margin: 20px auto; }
            .header { display:flex; justify-content:space-between; align-items:flex-start; gap: 16px; }
            h2,h3 { margin: 0 0 6px 0; }
            .muted { color:#666; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
            th { background: #f5f5f5; }
            .right { text-align:right; }
            .totalbox { margin-top: 10px; display:flex; justify-content:flex-end; }
            .totalbox div { min-width: 280px; border:1px solid #ddd; padding:10px; }
            @media print { .no-print { display:none; } }
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="header">
            <div>
                <h2>Resep Obat</h2>
                <div class="muted">Tanggal: <?php echo htmlspecialchars($created_at); ?></div>
            </div>
            <div class="no-print">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Tutup</button>
            </div>
        </div>

        <hr>

        <table>
            <tr>
                <th width="30%">Nama Pasien</th>
                <td><?php echo htmlspecialchars($nama_pasien); ?></td>
            </tr>
            <tr>
                <th>Member ID</th>
                <td><?php echo htmlspecialchars((string)$member_id); ?></td>
            </tr>
            <tr>
                <th>Apoteker</th>
                <td><?php echo htmlspecialchars($head['nama_apoteker'] ?? '-'); ?></td>
            </tr>
        </table>

        <h3 style="margin-top:16px;">Detail Obat</h3>
        <table>
            <thead>
            <tr>
                <th style="width: 26px;">No</th>
                <th>Obat</th>
                <th class="right">Jumlah</th>
                <th>Dosis</th>
                <th>Keterangan</th>
                <th class="right">Harga Satuan</th>
                <th class="right">Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td class="right"><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($it['nama_obat']); ?></td>
                    <td class="right"><?php echo (int)$it['jumlah']; ?></td>
                    <td><?php echo htmlspecialchars($it['dosis']); ?></td>
                    <td><?php echo htmlspecialchars($it['keterangan']); ?></td>
                    <td class="right">Rp <?php echo number_format((int)$it['_harga_satuan'],0,',','.'); ?></td>
                    <td class="right"><b>Rp <?php echo number_format((int)$it['_total'],0,',','.'); ?></b></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totalbox">
            <div>
                <div class="right muted">Total Keseluruhan</div>
                <div class="right" style="font-size:18px;"><b>Rp <?php echo number_format($grandTotal,0,',','.'); ?></b></div>
            </div>
        </div>

        <div style="margin-top:24px; display:flex; justify-content:space-between;">
            <div>
                <div class="muted">TTD Pasien</div>
                <div style="height:60px;"></div>
                <div>(_____________________)</div>
            </div>
            <div>
                <div class="muted">TTD Apoteker</div>
                <div style="height:60px;"></div>
                <div>(_____________________)</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function(){ window.print(); };
    </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * =========================
 * DELETE (kembalikan stok)
 * =========================
 */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $rq = mysqli_query($conn, "SELECT obat_id, jumlah FROM resep WHERE id=$id LIMIT 1");
    if ($rq && mysqli_num_rows($rq) > 0) {
        $r = mysqli_fetch_assoc($rq);
        $obat_id_old = (int)$r['obat_id'];
        $jumlah_old  = (int)$r['jumlah'];
        mysqli_query($conn, "UPDATE obat SET stok = stok + $jumlah_old WHERE id=$obat_id_old");
    }

    mysqli_query($conn, "DELETE FROM resep WHERE id=$id");
    header("Location: resep.php");
    exit;
}

/**
 * =========================
 * UPDATE (EDIT 1 BARIS RESEP) VIA POST
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['id'];

    $member_id   = $_POST['member_id'] ?: null;
    $nama_pasien = trim($_POST['nama_pasien']);
    $apoteker_id = $_POST['apoteker_id'] ?: null;
    $obat_id_new = (int)$_POST['obat_id'];
    $jumlah_new  = (int)$_POST['jumlah'];
    $dosis       = trim($_POST['dosis']);
    $ket         = trim($_POST['keterangan']);
    $harga       = (int)$_POST['harga']; // harga satuan

    $oldQ = mysqli_query($conn, "SELECT obat_id, jumlah FROM resep WHERE id=$id LIMIT 1");
    if (!$oldQ || mysqli_num_rows($oldQ) === 0) {
        header("Location: resep.php");
        exit;
    }
    $old = mysqli_fetch_assoc($oldQ);
    $obat_id_old = (int)$old['obat_id'];
    $jumlah_old  = (int)$old['jumlah'];

    mysqli_begin_transaction($conn);
    try {
        // balikin stok lama
        mysqli_query($conn, "UPDATE obat SET stok = stok + $jumlah_old WHERE id=$obat_id_old");

        // update resep
        $stmt = mysqli_prepare($conn, "UPDATE resep
            SET member_id=?, nama_pasien=?, apoteker_id=?, obat_id=?, jumlah=?, dosis=?, keterangan=?, harga=?
            WHERE id=?");
        mysqli_stmt_bind_param($stmt, "isiiissii",
            $member_id, $nama_pasien, $apoteker_id, $obat_id_new, $jumlah_new, $dosis, $ket, $harga, $id
        );
        mysqli_stmt_execute($stmt);

        // kurangi stok baru
        mysqli_query($conn, "UPDATE obat SET stok = GREATEST(stok - $jumlah_new,0) WHERE id=$obat_id_new");

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
    }

    header("Location: resep.php");
    exit;
}

/**
 * =========================
 * CREATE (INPUT RESEP MULTI OBAT) VIA POST
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'update')) {
    $member_id   = $_POST['member_id'] ?: null;
    $nama_pasien = trim($_POST['nama_pasien']);
    $apoteker_id = $_POST['apoteker_id'] ?: null;
    $tanggal     = date('Y-m-d H:i:s');

    // array input
    $obat_ids  = $_POST['obat_id'] ?? [];
    $jumlahs   = $_POST['jumlah'] ?? [];
    $dosis_arr = $_POST['dosis'] ?? [];
    $kets      = $_POST['keterangan'] ?? [];
    $hargas    = $_POST['harga'] ?? [];

    // minimal 1 item
    if (count($obat_ids) === 0) {
        header("Location: resep.php");
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        foreach ($obat_ids as $i => $obat_id) {
            $obat_id = (int)$obat_id;
            $jumlah  = (int)($jumlahs[$i] ?? 0);
            $dosis   = trim((string)($dosis_arr[$i] ?? '-'));
            $ket     = trim((string)($kets[$i] ?? '-'));
            $harga   = (int)($hargas[$i] ?? 0);

            // skip baris kosong
            if ($obat_id <= 0 || $jumlah <= 0) continue;

            $stmt = mysqli_prepare($conn, "INSERT INTO resep
                (member_id, nama_pasien, apoteker_id, obat_id, jumlah, dosis, keterangan, harga, created_at)
                VALUES (?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "isiiissis",
                $member_id, $nama_pasien, $apoteker_id,
                $obat_id, $jumlah, $dosis, $ket, $harga, $tanggal
            );
            mysqli_stmt_execute($stmt);

            // stok keluar per item
            mysqli_query($conn, "UPDATE obat SET stok = GREATEST(stok - $jumlah,0) WHERE id=$obat_id");
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
    }

    header("Location: resep.php");
    exit;
}

/**
 * =========================
 * MODE EDIT (GET edit) - edit 1 baris resep
 * =========================
 */
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if ($id > 0) {
        $rq = mysqli_query($conn, "SELECT * FROM resep WHERE id=$id LIMIT 1");
        if ($rq && mysqli_num_rows($rq) > 0) {
            $edit = mysqli_fetch_assoc($rq);
        }
    }
}

include "partials/header.php";
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0"><?php echo $edit ? "Edit Resep (1 Item)" : "Input Resep"; ?></h5>
            <small class="text-muted"><?php echo $edit ? "Ubah 1 baris item resep." : "Catat resep obat pasien."; ?></small>
        </div>
        <?php if ($edit): ?>
            <a href="resep.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Batal Edit
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <form method="post" class="row g-2">
            <?php if ($edit): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>">
            <?php endif; ?>

            

            <div class="col-md-3">
                <label class="form-label small">Nama Pasien</label>
                <input type="text" name="nama_pasien" class="form-control" required
                       value="<?php echo $edit ? htmlspecialchars($edit['nama_pasien']) : ""; ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label small">Apoteker</label>
                <select name="apoteker_id" class="form-select">
                    <option value="">-- Pilih Apoteker --</option>
                    <?php
                    $a = mysqli_query($conn, "SELECT id, nama FROM apoteker ORDER BY nama");
                    while ($row = mysqli_fetch_assoc($a)) {
                        $selected = ($edit && (string)$edit['apoteker_id'] === (string)$row['id']) ? "selected" : "";
                        echo "<option value='{$row['id']}' $selected>".htmlspecialchars($row['nama'])."</option>";
                    }
                    ?>
                </select>
            </div>

            <?php if ($edit): ?>
                <!-- MODE EDIT: 1 BARIS -->
                <div class="col-md-3">
                    <label class="form-label small">Obat</label>
                    <select name="obat_id" class="form-select" required id="obatEditSelect">
                        <option value="">-- Pilih Obat --</option>
                        <?php
                        $o = mysqli_query($conn, "SELECT id, nama, stok, harga FROM obat ORDER BY nama");
                        while ($row = mysqli_fetch_assoc($o)) {
                            $selected = ((int)$edit['obat_id'] === (int)$row['id']) ? "selected" : "";
                            $label = $row['nama']." (stok: ".$row['stok'].")";
                            echo "<option value='{$row['id']}' data-harga='{$row['harga']}' $selected>".htmlspecialchars($label)."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Jumlah</label>
                    <input type="number" name="jumlah" class="form-control" required value="<?php echo (int)$edit['jumlah']; ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Dosis</label>
                    <select name="dosis" class="form-select">
                        <?php
                        $dosisOptions = ['-', '3x1', '2x1', '1x1'];
                        $currentDosis = $edit ? $edit['dosis'] : '-';
                        foreach ($dosisOptions as $d) {
                            $sel = ($currentDosis === $d) ? 'selected' : '';
                            echo "<option value='$d' $sel>$d</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Keterangan</label>
                    <select name="keterangan" class="form-select">
                        <?php
                        $ketOptions = ['-', 'Sebelum Makan', 'Sesudah Makan'];
                        $currentKet = $edit ? $edit['keterangan'] : '-';
                        foreach ($ketOptions as $k) {
                            $sel = ($currentKet === $k) ? 'selected' : '';
                            echo "<option value='$k' $sel>$k</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Harga (Rp)</label>
                    <input type="number" name="harga" id="hargaInputEdit" class="form-control" required value="<?php echo (int)$edit['harga']; ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Update
                    </button>
                </div>

            <?php else: ?>
                <!-- MODE INPUT: MULTI BARIS -->
                <div class="col-12">
                    <hr>
                    <h6 class="mb-0">Daftar Obat</h6>
                    <small class="text-muted">Klik + Tambah Obat untuk menambah baris obat.</small>
                </div>

                <div class="col-12" id="obat-wrapper">
                    <div class="row g-2 obat-row mb-2">
                        <div class="col-md-3">
                            <label class="form-label small">Obat</label>
                            <select name="obat_id[]" class="form-select obat-select" required>
                                <option value="">-- Pilih Obat --</option>
                                <?php
                                $o = mysqli_query($conn, "SELECT id, nama, stok, harga FROM obat ORDER BY nama");
                                while ($row = mysqli_fetch_assoc($o)) {
                                    $label = $row['nama']." (stok: ".$row['stok'].")";
                                    echo "<option value='{$row['id']}' data-harga='{$row['harga']}'>".htmlspecialchars($label)."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small">Jumlah</label>
                            <input type="number" name="jumlah[]" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small">Dosis</label>
                            <select name="dosis[]" class="form-select">
                                <option value="-">-</option>
                                <option value="3x1">3x1</option>
                                <option value="2x1">2x1</option>
                                <option value="1x1">1x1</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small">Keterangan</label>
                            <select name="keterangan[]" class="form-select">
                                <option value="-">-</option>
                                <option value="Sebelum Makan">Sebelum Makan</option>
                                <option value="Sesudah Makan">Sesudah Makan</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small">Harga (Rp)</label>
                            <input type="number" name="harga[]" class="form-control harga-input" required>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-danger btn-sm btnHapusBaris" style="display:none;">
                                Hapus Baris
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="button" id="btnTambahObat" class="btn btn-outline-primary">
                        + Tambah Obat
                    </button>

                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Resep
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-2">Data Resep</h6>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Pasien</th>
                    <th>Obat</th>
                    <th>Jumlah</th>
                    <th>Dosis</th>
                    <th>Keterangan</th>
                    <th>Harga Satuan</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT r.*, o.nama AS nama_obat FROM resep r JOIN obat o ON r.obat_id=o.id ORDER BY r.created_at DESC, r.id DESC");
                if (mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)):
                        $harga_satuan = (int)$row['harga'];
                        $total = $harga_satuan * (int)$row['jumlah'];
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_pasien']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_obat']); ?></td>
                            <td><?php echo (int)$row['jumlah']; ?></td>
                            <td><?php echo htmlspecialchars($row['dosis']); ?></td>
                            <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                            <td>Rp <?php echo number_format($harga_satuan,0,',','.'); ?></td>
                            <td><b>Rp <?php echo number_format($total,0,',','.'); ?></b></td>
                            <td class="d-flex gap-1">
                                <a href="resep.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="resep.php?print=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-success">
                                    <i class="bi bi-printer"></i>
                                </a>

                                <a href="resep.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus resep ini?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ===== MULTI INPUT: tambah baris obat
document.addEventListener('DOMContentLoaded', function() {
    const btnTambah = document.getElementById('btnTambahObat');
    const wrapper = document.getElementById('obat-wrapper');

    if (btnTambah && wrapper) {
        btnTambah.addEventListener('click', function () {
            const first = wrapper.querySelector('.obat-row');
            const clone = first.cloneNode(true);

            // reset value
            clone.querySelectorAll('input').forEach(i => i.value = '');
            clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);

            // tampilkan tombol hapus pada clone & semua baris jika >1
            clone.querySelector('.btnHapusBaris').style.display = 'inline-block';

            wrapper.appendChild(clone);

            // kalau sudah lebih dari 1 baris, tombol hapus pada baris pertama juga muncul
            const rows = wrapper.querySelectorAll('.obat-row');
            if (rows.length > 1) {
                rows.forEach(r => {
                    r.querySelector('.btnHapusBaris').style.display = 'inline-block';
                });
            }
        });

        // hapus baris
        wrapper.addEventListener('click', function(e){
            if(e.target.classList.contains('btnHapusBaris')){
                const rows = wrapper.querySelectorAll('.obat-row');
                if(rows.length > 1){
                    e.target.closest('.obat-row').remove();
                }
                // kalau tinggal 1 baris, sembunyikan tombol hapus
                const rows2 = wrapper.querySelectorAll('.obat-row');
                if(rows2.length === 1){
                    rows2[0].querySelector('.btnHapusBaris').style.display = 'none';
                }
            }
        });

        // auto isi harga per baris
        wrapper.addEventListener('change', function(e){
            if(e.target.classList.contains('obat-select')){
                const opt = e.target.selectedOptions[0];
                const harga = opt ? (opt.dataset.harga || 0) : 0;
                const row = e.target.closest('.obat-row');
                const hargaInput = row.querySelector('.harga-input');
                if (hargaInput) hargaInput.value = harga;
            }
        });
    }

    // auto isi harga di mode edit (single)
    const obatEdit = document.getElementById('obatEditSelect');
    const hargaEdit = document.getElementById('hargaInputEdit');
    if (obatEdit && hargaEdit) {
        obatEdit.addEventListener('change', function() {
            const opt = this.selectedOptions[0];
            if (opt && opt.dataset.harga) {
                hargaEdit.value = opt.dataset.harga;
            }
        });
    }
});
</script>

<?php include "partials/footer.php"; ?>
