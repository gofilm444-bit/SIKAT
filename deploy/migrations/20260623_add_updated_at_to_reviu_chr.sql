-- Migration: add updated_at to reviu_chr
-- Required by review.php query using COALESCE(updated_at, created_at)

ALTER TABLE reviu_chr
ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
