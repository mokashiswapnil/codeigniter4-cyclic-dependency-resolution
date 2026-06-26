<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Libraries\ModelRegistry;

class RoleModel extends Model
{
    protected $table      = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['name', 'description'];

    // ─── Constructor is clean — no model loading here ────────────────────────
    //
    // CI3 pattern that caused cycles:
    //   public function __construct()
    //   {
    //       $this->load->model('PermissionModel'); // ← triggered UserModel → RoleModel → PermissionModel loop
    //   }

    public function getRoleWithPermissions(int $roleId): ?array
    {
        $role = $this->find($roleId);
        if (! $role) {
            return null;
        }

        // PermissionModel is only created when this method is actually called.
        $permissions = ModelRegistry::getModel('PermissionModel')
            ->getByRoleId($roleId);

        return array_merge($role, ['permissions' => $permissions]);
    }

    public function getUsersInRole(int $roleId): array
    {
        // UserModel is resolved from the registry — same instance used elsewhere.
        return ModelRegistry::getModel('UserModel')
            ->where('role_id', $roleId)
            ->findAll();
    }
}
