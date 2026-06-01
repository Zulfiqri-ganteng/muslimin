<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table            = 'admins';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'full_name', 'email', 'username', 'password', 'phone', 'photo', 'role',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'full_name' => 'required|max_length[150]',
        'username'  => 'required|min_length[4]|max_length[100]|is_unique[admins.username,id,{id}]',
        'email'     => 'required|valid_email|is_unique[admins.email,id,{id}]',
    ];

    /**
     * Cari admin berdasarkan username atau email (untuk login).
     */
    public function findByLogin(string $login)
    {
        return $this->groupStart()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->groupEnd()
            ->first();
    }
}
