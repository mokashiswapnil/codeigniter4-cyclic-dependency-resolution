<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Libraries\ModelRegistry;

class UserController extends Controller
{
    // ─── Clean constructor — no model loading ────────────────────────────────
    //
    // CI3 pattern (causes cascading instantiation in CI4):
    //
    //   public function __construct()
    //   {
    //       $this->load->model('UserModel');
    //       $this->load->model('RoleModel');
    //       $this->load->model('PermissionModel');
    //   }
    //
    // In CI4, each new Model() in the constructor fires immediately, which
    // means a controller with 5 models loaded loads ALL of them on every
    // request — even routes that only need one.
    //
    // With ModelRegistry, models are created on first access within the
    // method that actually uses them.

    public function show(int $id)
    {
        $user = ModelRegistry::getModel('UserModel')->getUserWithRole($id);

        if (! $user) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'User not found']);
        }

        return $this->response->setJSON($user);
    }

    public function permissions(int $id)
    {
        $permissions = ModelRegistry::getModel('UserModel')->getUserPermissions($id);

        return $this->response->setJSON(['permissions' => $permissions]);
    }

    public function deactivate(int $id)
    {
        $ok = ModelRegistry::getModel('UserModel')->deactivateUser($id);

        return $this->response->setJSON(['success' => $ok]);
    }

    public function roleUsers(int $roleId)
    {
        // Only RoleModel is needed here — UserModel and PermissionModel are never loaded.
        $users = ModelRegistry::getModel('RoleModel')->getUsersInRole($roleId);

        return $this->response->setJSON(['users' => $users]);
    }
}
