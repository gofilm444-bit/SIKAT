<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(mysqli $conn, string $table): bool {
        $esc = $conn->real_escape_string($table);
        $res = @$conn->query("SHOW TABLES LIKE '$esc'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('db_column_exists')) {
    function db_column_exists(mysqli $conn, string $table, string $column): bool {
        if (!db_table_exists($conn, $table)) return false;
        $tbl = $conn->real_escape_string($table);
        $col = $conn->real_escape_string($column);
        $res = @$conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('db_column_info')) {
    function db_column_info(mysqli $conn, string $table, string $column): ?array {
        if (!db_table_exists($conn, $table)) return null;
        $tbl = $conn->real_escape_string($table);
        $col = $conn->real_escape_string($column);
        $sql = "SELECT COLUMN_NAME, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS "
             . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND COLUMN_NAME = '$col' LIMIT 1";
        $res = @$conn->query($sql);
        return $res ? ($res->fetch_assoc() ?: null) : null;
    }
}

if (!function_exists('db_index_exists')) {
    function db_index_exists(mysqli $conn, string $table, string $index): bool {
        if (!db_table_exists($conn, $table)) return false;
        $tbl = $conn->real_escape_string($table);
        $idx = $conn->real_escape_string($index);
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS "
             . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND INDEX_NAME = '$idx' LIMIT 1";
        $res = @$conn->query($sql);
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('db_exec')) {
    function db_exec(mysqli $conn, string $sql): void {
        try {
            @$conn->query($sql);
        } catch (mysqli_sql_exception $ex) {
            // suppress schema adjustment failures; runtime code handles missing structures gracefully
        }
    }
}

// ====== pelaporan table creation/upgrades ======
if (!db_table_exists($conn, 'pelaporan')) {
    db_exec($conn, "CREATE TABLE pelaporan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(50) NOT NULL UNIQUE,
        judul VARCHAR(255) NOT NULL,
        kategori VARCHAR(150) NOT NULL,
        isi TEXT NULL,
        anonim TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'Belum diproses',
        tanggal DATE NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if (db_table_exists($conn, 'pelaporan')) {
    if (!db_column_exists($conn, 'pelaporan', 'kode')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN kode VARCHAR(50) NULL AFTER id");
    }
    if (!db_column_exists($conn, 'pelaporan', 'kategori')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN kategori VARCHAR(150) NOT NULL DEFAULT 'Umum' AFTER judul");
    }
    if (!db_column_exists($conn, 'pelaporan', 'isi')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN isi TEXT NULL AFTER kategori");
    }
    if (!db_column_exists($conn, 'pelaporan', 'anonim')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN anonim TINYINT(1) NOT NULL DEFAULT 0 AFTER isi");
    }
    if (!db_column_exists($conn, 'pelaporan', 'created_at')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN created_at DATETIME NULL AFTER tanggal");
    }
    if (!db_column_exists($conn, 'pelaporan', 'updated_at')) {
        db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN updated_at DATETIME NULL AFTER created_at");
    }
    // assign kode untuk baris lama jika kosong
    $conn->query("UPDATE pelaporan SET kode = CONCAT('LAP-', LPAD(id,4,'0')) WHERE kode IS NULL OR kode=''");
}

// ====== pengguna column upgrades ======
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'password_hash')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN password_hash VARCHAR(255) NULL AFTER password");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'nip')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN nip VARCHAR(30) NULL AFTER nama");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'jabatan')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN jabatan VARCHAR(200) NULL AFTER nip");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'unit_id')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN unit_id INT NULL AFTER jabatan");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'akses_dashboard')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN akses_dashboard TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'akses_pelaporan')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN akses_pelaporan TINYINT(1) NOT NULL DEFAULT 0 AFTER akses_dashboard");
}
if (db_table_exists($conn, 'pengguna') && !db_column_exists($conn, 'pengguna', 'akses_review')) {
    db_exec($conn, "ALTER TABLE pengguna ADD COLUMN akses_review TINYINT(1) NOT NULL DEFAULT 0 AFTER akses_pelaporan");
}

// ====== feedback ======
if (!db_table_exists($conn, 'feedback')) {
    db_exec($conn, "CREATE TABLE feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(150) NULL,
        email VARCHAR(150) NULL,
        isi TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== mail_recipients ======
if (!db_table_exists($conn, 'mail_recipients')) {
    db_exec($conn, "CREATE TABLE mail_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL UNIQUE,
        nama VARCHAR(150) NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== pelaporan_contact ======
if (!db_table_exists($conn, 'pelaporan_contact')) {
    db_exec($conn, "CREATE TABLE pelaporan_contact (
        kode VARCHAR(50) PRIMARY KEY,
        nama VARCHAR(150) NULL,
        email VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== reviu_log ======
if (!db_table_exists($conn, 'reviu_log')) {
    db_exec($conn, "CREATE TABLE reviu_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        status_from VARCHAR(50) NULL,
        status_to VARCHAR(50) NOT NULL,
        note TEXT NULL,
        user_id INT NULL,
        user_name VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    if (!db_column_exists($conn, 'reviu_log', 'status_from')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN status_from VARCHAR(50) NULL AFTER reviu_id");
    }
    if (!db_column_exists($conn, 'reviu_log', 'status_to')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN status_to VARCHAR(50) NOT NULL DEFAULT '' AFTER status_from");
    }
    if (!db_column_exists($conn, 'reviu_log', 'note')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN note TEXT NULL AFTER status_to");
    }
    if (!db_column_exists($conn, 'reviu_log', 'user_id')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN user_id INT NULL AFTER note");
    }
    if (!db_column_exists($conn, 'reviu_log', 'user_name')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN user_name VARCHAR(150) NULL AFTER user_id");
    }
    if (!db_column_exists($conn, 'reviu_log', 'created_at')) {
        db_exec($conn, "ALTER TABLE reviu_log ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER user_name");
    }
}

// ====== reviu_laporan ======
if (!db_table_exists($conn, 'reviu_laporan')) {
    db_exec($conn, "CREATE TABLE reviu_laporan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL UNIQUE,
        ringkasan MEDIUMTEXT NULL,
        rekomendasi MEDIUMTEXT NULL,
        tindak_lanjut TEXT NULL,
        lampiran VARCHAR(255) NULL,
        ttd_kepala_nama VARCHAR(150) NULL,
        ttd_kepala_tanggal DATE NULL,
        ttd_kepala_file VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} elseif (db_table_exists($conn, 'reviu_laporan')) {
    if (!db_column_exists($conn, 'reviu_laporan', 'ttd_kepala_nama')) {
        db_exec($conn, "ALTER TABLE reviu_laporan ADD COLUMN ttd_kepala_nama VARCHAR(150) NULL AFTER lampiran");
    }
    if (!db_column_exists($conn, 'reviu_laporan', 'ttd_kepala_tanggal')) {
        db_exec($conn, "ALTER TABLE reviu_laporan ADD COLUMN ttd_kepala_tanggal DATE NULL AFTER ttd_kepala_nama");
    }
    if (!db_column_exists($conn, 'reviu_laporan', 'ttd_kepala_file')) {
        db_exec($conn, "ALTER TABLE reviu_laporan ADD COLUMN ttd_kepala_file VARCHAR(255) NULL AFTER ttd_kepala_tanggal");
    }
}

// ====== reviu_monitoring ======
if (!db_table_exists($conn, 'reviu_monitoring')) {
    db_exec($conn, "CREATE TABLE reviu_monitoring (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Belum Dipantau',
        due_date DATE NULL,
        catatan TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} elseif (!db_column_exists($conn, 'reviu_monitoring', 'reviu_id')) {
    // table exists but legacy structure, skip
} else {
    if (!db_index_exists($conn, 'reviu_monitoring', 'uniq_reviu')) {
        db_exec($conn, "ALTER TABLE reviu_monitoring ADD UNIQUE KEY uniq_reviu (reviu_id)");
    }
}

// ====== reviu_early_warning ======
if (!db_table_exists($conn, 'reviu_early_warning')) {
    db_exec($conn, "CREATE TABLE reviu_early_warning (
        reviu_id INT PRIMARY KEY,
        last_level VARCHAR(20) NOT NULL,
        notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== pelaporan_files ======
if (!db_table_exists($conn, 'pelaporan_files')) {
    db_exec($conn, "CREATE TABLE pelaporan_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(50) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NULL,
        size_bytes BIGINT NULL,
        rel_path VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_kode (kode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== pelaporan_log ======
if (!db_table_exists($conn, 'pelaporan_log')) {
    db_exec($conn, "CREATE TABLE pelaporan_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(50) NOT NULL,
        status_from VARCHAR(50) NULL,
        status_to VARCHAR(50) NOT NULL,
        note TEXT NULL,
        user_id INT NULL,
        user_name VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_kode (kode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== unit_kerja ======
if (!db_table_exists($conn, 'unit_kerja')) {
    db_exec($conn, "CREATE TABLE unit_kerja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(191) NOT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== jenis_reviu ======
if (!db_table_exists($conn, 'jenis_reviu')) {
    db_exec($conn, "CREATE TABLE jenis_reviu (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(191) NOT NULL,
        deskripsi TEXT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if (db_table_exists($conn, 'jenis_reviu')) {
    db_exec($conn, "UPDATE jenis_reviu
        SET nama='Reviu RKA/RKAKL',
            deskripsi=COALESCE(NULLIF(deskripsi,''), 'Reviu atas RKA-K/L dan RKAKL berdasarkan format catatan hasil reviu RKA')
        WHERE LOWER(TRIM(nama)) IN ('reviu anggaran', 'reviu rka', 'reviu rkakl', 'rka', 'rkakl', 'rka/rkakl', 'rka-k/l', 'reviu rka-k/l')");
    db_exec($conn, "INSERT INTO jenis_reviu (nama, deskripsi, aktif)
        SELECT 'Reviu RKA/RKAKL', 'Reviu atas RKA-K/L dan RKAKL berdasarkan format catatan hasil reviu RKA', 1
        WHERE NOT EXISTS (
            SELECT 1 FROM jenis_reviu
            WHERE LOWER(TRIM(nama)) IN ('reviu rka/rkakl', 'reviu rka-k/l', 'reviu rkakl', 'reviu rka')
        )");
    db_exec($conn, "UPDATE jenis_reviu
        SET nama='Reviu Manajemen Risiko',
            deskripsi=COALESCE(NULLIF(deskripsi,''), 'Reviu terhadap penerapan dan pemantauan manajemen risiko unit kerja')
        WHERE LOWER(TRIM(nama)) IN ('reviu manajemen resiko', 'reviu manajemen risiko', 'manajemen resiko', 'manajemen risiko', 'manrisk', 'reviu manrisk')");
    db_exec($conn, "INSERT INTO jenis_reviu (nama, deskripsi, aktif)
        SELECT 'Reviu Manajemen Risiko', 'Reviu terhadap penerapan dan pemantauan manajemen risiko unit kerja', 1
        WHERE NOT EXISTS (
            SELECT 1 FROM jenis_reviu
            WHERE LOWER(TRIM(nama)) IN ('reviu manajemen risiko', 'reviu manajemen resiko', 'reviu manrisk', 'manrisk')
        )");
    foreach ([
        ['Reviu Pengembangan Pegawai', 'Reviu atas proses dan dokumen pengembangan pegawai', "'reviu pengembangan pegawai','pengembangan pegawai','reviu pengembangan sdm','pengembangan sdm'"],
        ['Reviu LHKPN dan LHKASN', 'Reviu kepatuhan pelaporan LHKPN dan LHKASN', "'reviu lhkpn dan lhkasn','reviu lhkpn & lhkasn','reviu lhkpn/lhkasn','lhkpn','lhkasn'"],
        ['Reviu IKU-IKT', 'Reviu indikator kinerja utama dan indikator kinerja tambahan', "'reviu iku-ikt','reviu iku/ikt','iku-ikt','iku/ikt','iku','ikt'"],
        ['Reviu Laporan Kinerja', 'Reviu laporan kinerja/LKJ satuan kerja atau unit kerja', "'reviu laporan kinerja','reviu lkj','reviu lakip','laporan kinerja','lkj','lakip'"],
        ['Reviu PIPK', 'Reviu pengendalian intern atas pelaporan keuangan tingkat satker', "'reviu pipk','pipk'"],
        ['Reviu RKBMN', 'Reviu rencana kebutuhan barang milik negara', "'reviu rkbmn','rkbmn'"],
    ] as $jenisSeed) {
        $nama = $conn->real_escape_string($jenisSeed[0]);
        $deskripsi = $conn->real_escape_string($jenisSeed[1]);
        db_exec($conn, "INSERT INTO jenis_reviu (nama, deskripsi, aktif)
            SELECT '{$nama}', '{$deskripsi}', 1
            WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE LOWER(TRIM(nama)) IN ({$jenisSeed[2]}))");
    }
}

// ====== reviu (jadwal) ======
if (!db_table_exists($conn, 'reviu')) {
    db_exec($conn, "CREATE TABLE reviu (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(50) NOT NULL UNIQUE,
        jenis_id INT NOT NULL,
        unit_id INT NOT NULL,
        periode_mulai DATE NOT NULL,
        periode_selesai DATE NOT NULL,
        tgl_deadline DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Terjadwal',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    $colInfo = db_column_info($conn, 'reviu', 'id');
    if (!$colInfo || ($colInfo['COLUMN_KEY'] ?? '') !== 'PRI') {
        db_exec($conn, "ALTER TABLE reviu ADD PRIMARY KEY (id)");
        $colInfo = db_column_info($conn, 'reviu', 'id');
    }
    if ($colInfo && ($colInfo['COLUMN_KEY'] ?? '') === 'PRI' && stripos($colInfo['EXTRA'] ?? '', 'auto_increment') === false) {
        db_exec($conn, "ALTER TABLE reviu MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    }
}

// ====== reviu_penugasan ======
if (!db_table_exists($conn, 'reviu_penugasan')) {
    db_exec($conn, "CREATE TABLE reviu_penugasan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        role VARCHAR(20) NOT NULL,
        user_id INT NULL,
        nama VARCHAR(150) NULL,
        email VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    $colInfo = db_column_info($conn, 'reviu_penugasan', 'id');
    if (!$colInfo || ($colInfo['COLUMN_KEY'] ?? '') !== 'PRI') {
        db_exec($conn, "ALTER TABLE reviu_penugasan ADD PRIMARY KEY (id)");
        $colInfo = db_column_info($conn, 'reviu_penugasan', 'id');
    }
    if ($colInfo && stripos($colInfo['EXTRA'] ?? '', 'auto_increment') === false) {
        db_exec($conn, "ALTER TABLE reviu_penugasan MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    }
}

// ====== reviu_chr ======
if (!db_table_exists($conn, 'reviu_chr')) {
    db_exec($conn, "CREATE TABLE reviu_chr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        deskripsi TEXT NOT NULL,
        rekomendasi TEXT NOT NULL,
        due_date DATE NOT NULL,
        status_tl VARCHAR(20) NOT NULL DEFAULT 'Belum TL',
        tl_catatan TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY idx_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} elseif (!db_column_exists($conn, 'reviu_chr', 'updated_at')) {
    db_exec($conn, "ALTER TABLE reviu_chr ADD COLUMN updated_at DATETIME NULL AFTER created_at");
}

// ====== reviu_verifikasi ======
if (!db_table_exists($conn, 'reviu_verifikasi')) {
    db_exec($conn, "CREATE TABLE reviu_verifikasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        tahap VARCHAR(30) NOT NULL,
        verifikator VARCHAR(150) NOT NULL,
        status VARCHAR(20) NOT NULL,
        catatan TEXT NULL,
        tgl_verifikasi DATETIME NOT NULL,
        KEY idx_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== reviu_dokumen ======
if (!db_table_exists($conn, 'reviu_dokumen')) {
    db_exec($conn, "CREATE TABLE reviu_dokumen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviu_id INT NOT NULL,
        kategori VARCHAR(50) NOT NULL,
        judul VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== audit_log ======
if (!db_table_exists($conn, 'audit_log')) {
    db_exec($conn, "CREATE TABLE audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(150) NULL,
        role VARCHAR(50) NULL,
        action VARCHAR(50) NOT NULL,
        entity VARCHAR(50) NOT NULL,
        entity_id INT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        details LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user (user_id),
        KEY idx_entity (entity, entity_id),
        KEY idx_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== helper indexes/updates ======
if (db_table_exists($conn, 'pelaporan') && !db_column_exists($conn, 'pelaporan', 'status')) {
    db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Belum diproses'");
}

if (db_table_exists($conn, 'pelaporan') && !db_column_exists($conn, 'pelaporan', 'tanggal')) {
    db_exec($conn, "ALTER TABLE pelaporan ADD COLUMN tanggal DATE NULL");
}

// ensure kode uniqueness constraint
if (db_table_exists($conn, 'pelaporan') && !db_index_exists($conn, 'pelaporan', 'uniq_kode')) {
    db_exec($conn, "ALTER TABLE pelaporan ADD UNIQUE KEY uniq_kode (kode)");
}
