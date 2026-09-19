<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jejak akses dokumen — siapa melihat/mengunduh apa dan kapan.
 *
 * admin_id NULL berarti tamu yang datang lewat tautan berbagi (guru yang
 * tidak punya akun). Tabel ini hanya ditulis-tambah, tak pernah diubah.
 */
class DokumenAksesLogModel extends Model
{
    protected $table         = 'dokumen_akses_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['dokumen_id', 'share_id', 'aksi', 'admin_id', 'ip', 'user_agent', 'created_at'];

    // created_at diisi manual supaya bisa dipakai di konteks tanpa timestamp otomatis.
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;

    /**
     * Catat satu akses. Sengaja TIDAK melempar error bila gagal —
     * pencatatan jejak tak boleh menggagalkan pengunduhan berkas.
     */
    public function catat(string $aksi, ?int $dokumenId, ?int $shareId = null, ?int $adminId = null): void
    {
        if (! in_array($aksi, ['lihat', 'pratinjau', 'unduh'], true)) {
            return;
        }

        try {
            $req = service('request');
            // CLIRequest tak punya getUserAgent() — modul ini juga dipakai
            // dari perintah spark (pemeliharaan), jadi jangan diasumsikan ada.
            $ua = method_exists($req, 'getUserAgent') ? (string) $req->getUserAgent() : 'CLI';
            $ip = method_exists($req, 'getIPAddress') ? (string) $req->getIPAddress() : '';

            $this->insert([
                'dokumen_id' => $dokumenId,
                'share_id'   => $shareId,
                'aksi'       => $aksi,
                'admin_id'   => $adminId,
                'ip'         => substr($ip, 0, 45),
                'user_agent' => substr($ua, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mencatat akses dokumen: ' . $e->getMessage());
        }
    }

    /** Riwayat akses satu dokumen (terbaru dulu). */
    public function untukDokumen(int $dokumenId, int $batas = 100): array
    {
        return $this->select('dokumen_akses_log.*, admins.full_name AS admin_nama')
            ->join('admins', 'admins.id = dokumen_akses_log.admin_id', 'left')
            ->where('dokumen_akses_log.dokumen_id', $dokumenId)
            // id ikut diurutkan karena beberapa akses bisa jatuh di detik yang
            // sama — tanpa ini urutannya tak menentu.
            ->orderBy('dokumen_akses_log.created_at', 'DESC')
            ->orderBy('dokumen_akses_log.id', 'DESC')
            ->findAll($batas);
    }

    /** Riwayat akses lewat satu tautan berbagi (terbaru dulu). */
    public function untukShare(int $shareId, int $batas = 100): array
    {
        return $this->where('share_id', $shareId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($batas);
    }

    /** Buang jejak yang lebih tua dari N hari (pola AuditModel::purgeOlderThan). */
    public function purgeOlderThan(int $hari = 180): int
    {
        $batas = date('Y-m-d H:i:s', strtotime('-' . max(1, $hari) . ' days'));
        $this->where('created_at <', $batas)->delete();

        return $this->db->affectedRows();
    }
}
