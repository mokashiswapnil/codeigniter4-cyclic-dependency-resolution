<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table      = 'permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['role_id', 'resource', 'action'];

    public function getByRoleId(int $roleId): array
    {
        return $this->where('role_id', $roleId)->findAll();
    }

    public function userCan(int $roleId, string $resource, string $action): bool
    {
        return $this
            ->where('role_id', $roleId)
            ->where('resource', $resource)
            ->where('action', $action)
            ->countAllResults() > 0;
    }
}
