<?php
declare(strict_types=1);

/**
 * Ambil data utama yang dibutuhkan untuk export CHR (Word/PDF).
 *
 * @return array{
 *   revInfo: array<mixed>,
 *   chrSheet: array<mixed>,
 *   chrRows: array<int, array<mixed>>,
 *   filename_base: string
 * }|null
 */
function chr_export_load(mysqli $conn, int $rid): ?array {
  $revInfo = null;
  $sqlInfo = "SELECT r.kode, r.periode_mulai, r.periode_selesai, u.nama unit_nama, j.nama jenis_nama
              FROM reviu r
              JOIN unit_kerja u ON u.id=r.unit_id
              JOIN jenis_reviu j ON j.id=r.jenis_id
              WHERE r.id=?";
  if ($stmt = $conn->prepare($sqlInfo)) {
    $stmt->bind_param("i", $rid);
    if ($stmt->execute()) {
      $revInfo = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
  }
  if (!$revInfo) { return null; }

  $chrSheet = chr_form_fetch($conn, $rid, $revInfo);

  $chrRows = [];
  $sqlChr = "SELECT deskripsi, rekomendasi, due_date, status_tl, tl_catatan, created_at, updated_at
             FROM reviu_chr
             WHERE reviu_id=?
             ORDER BY created_at ASC";
  if ($stmt = $conn->prepare($sqlChr)) {
    $stmt->bind_param("i", $rid);
    if ($stmt->execute()) {
      $chrRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
  }

  $filenameBase = $revInfo['kode'] ?? ('REVIU-'.$rid);
  $filenameBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$filenameBase);
  if ($filenameBase === '') { $filenameBase = 'REVIU-'.$rid; }

  return [
    'revInfo' => $revInfo,
    'chrSheet' => $chrSheet,
    'chrRows' => $chrRows,
    'filename_base' => $filenameBase,
  ];
}

