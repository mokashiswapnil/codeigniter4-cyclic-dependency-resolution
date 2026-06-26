<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Libraries\ModelRegistry;

class UserModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['name', 'email', 'role_id', 'status'];

    // ─── CI3 pattern (DO NOT do this in CI4) ────────────────────────────────
    //
    // In CI3, you would load dependent models in the constructor:
    //
    //   public function __construct()
    //   {
    //       parent::__construct();
    //       $this->load->model('RoleModel');      // ← fine in CI3
    //       $this->load->model('PermissionModel'); // ← fine in CI3
    //   }
    //
    // In CI4, constructor injection causes a circular dependency chain:
    //
    //   UserModel  → new RoleModel()
    //   RoleModel  → new PermissionModel()
    //   PermissionModel → new UserModel()   ← CYCLE — PHP fatal error
    //
    // ─── CI4 solution: lazy loading via ModelRegistry ────────────────────────
    //
    // Remove all model instantiation from the constructor.
    // Call ModelRegistry::getModel() only inside the methods that need it.
    // The registry returns a cached instance, so there is no repeated overhead.

    public function getUserWithRole(int $userId): ?array
    {
        $user = $this->find($userId);
        if (! $user) {
            return null;
        }

        // Lazy-load RoleModel only when this method is called.
        // No circular dependency — RoleModel is not created at construction time.
        $role = ModelRegistry::getModel('RoleModel')->find($user['role_id']);

        return array_merge($user, ['role' => $role]);
    }

    public function getUserPermissions(int $userId): array
    {
        $user = $this->find($userId);
        if (! $user) {
            return [];
        }

        // Multiple models can be fetched from the registry in the same method.
        // Each is instantiated at most once per request.
        $role        = ModelRegistry::getModel('RoleModel')->find($user['role_id']);
        $permissions = ModelRegistry::getModel('PermissionModel')
            ->getByRoleId($role['id'] ?? 0);

        return $permissions;
    }

    public function deactivateUser(int $userId): bool
    {
        // ModelRegistry::getModel() inside a method is always safe —
        // the model is only created when execution actually reaches this line.
        $auditModel = ModelRegistry::getModel('AuditModel');
        $auditModel->log('user.deactivate', ['user_id' => $userId]);

        return $this->update($userId, ['status' => 'inactive']);
    }
}
