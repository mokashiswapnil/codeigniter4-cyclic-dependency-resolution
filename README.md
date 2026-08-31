# CodeIgniter 4 Model Registry (CI4 Circular Dependency Fix)

> **Resolve circular model dependency crashes, infinite constructor recursion, and cross-loading issues when migrating from CodeIgniter 3 to CodeIgniter 4.**

A lightweight, zero-dependency `ModelRegistry` library for **CodeIgniter 4 (CI4)** providing lazy singleton model instantiation. It replaces CI3's `$this->load->model()` cross-loading in constructors without causing fatal recursion errors or tight coupling.

Drop in `app/Libraries/ModelRegistry.php` and replace constructor model loading with `ModelRegistry::getModel('ModelName')->methodName()`.


---

## The Problem — Cyclic Dependencies in CI3 → CI4 Migration

### How CI3 worked

In CI3, `$this->load->model()` was a deferred loader — it registered the model but did not deeply instantiate its dependencies right then. You could freely cross-load models inside constructors:

```php
// CI3 — works fine
class User_model extends CI_Model {
    public function __construct() {
        $this->load->model('Role_model');       // lazy, no cycle
        $this->load->model('Permission_model'); // lazy, no cycle
    }
}
```

### Why it breaks in CI4

CI4 models use native PHP constructors. Every `new ModelClass()` runs immediately and synchronously. If two models load each other in their constructors, PHP enters infinite recursion and crashes with a fatal error.

```
UserModel  constructor → new RoleModel()
RoleModel  constructor → new PermissionModel()
PermissionModel constructor → new UserModel()   ← CYCLE → Fatal error
```

The larger the codebase, the harder these cycles are to untangle — especially when the dependency graph spans dozens of models across modules.

### The naive CI4 "fix" that doesn't scale

One approach is to inject models as constructor parameters:

```php
class UserModel extends Model {
    public function __construct(
        private RoleModel $roleModel,
        private PermissionModel $permModel,
    ) {}
}
```

This works for simple cases but forces you to declare every dependency upfront, breaks when models are mutually dependent, and requires changes across every call site.

---

## The Solution — Lazy Singleton Registry

`ModelRegistry::getModel()` resolves models on first use and caches the instance for the rest of the request. No model is constructed until the line of code that actually needs it runs.

```
Request lifecycle
─────────────────
UserController::show()
  └─ ModelRegistry::getModel('UserModel')   ← UserModel created here (first call)
       └─ UserModel::getUserWithRole()
            └─ ModelRegistry::getModel('RoleModel')  ← RoleModel created here
                 (no constructor deps → no cycle possible)
```

Because each model is created lazily (inside a method, not a constructor), there is no moment when two models are simultaneously in each other's constructors.

---

## Installation

Copy `app/Libraries/ModelRegistry.php` into your CI4 project's `app/Libraries/` directory. No Composer package needed — it is a single file.

---

## Usage

### 1. Drop the library file

```
app/
└── Libraries/
    └── ModelRegistry.php   ← copy this file
```

### 2. Remove model loading from constructors

**Before (CI3 pattern — breaks in CI4):**

```php
class UserModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        // $this->load->model('RoleModel');       ← remove this
        // $this->load->model('PermissionModel'); ← remove this
    }

    public function getUserWithRole(int $userId): ?array
    {
        $user = $this->find($userId);
        $role = $this->RoleModel->find($user['role_id']); // CI3 style
        return array_merge($user, ['role' => $role]);
    }
}
```

**After (CI4 with ModelRegistry):**

```php
use App\Libraries\ModelRegistry;

class UserModel extends Model
{
    // Constructor is clean — no model loading

    public function getUserWithRole(int $userId): ?array
    {
        $user = $this->find($userId);
        if (! $user) return null;

        // Load RoleModel only when this method is called
        $role = ModelRegistry::getModel('RoleModel')->find($user['role_id']);

        return array_merge($user, ['role' => $role]);
    }

    public function getUserPermissions(int $userId): array
    {
        $user = $this->find($userId);
        $role = ModelRegistry::getModel('RoleModel')->find($user['role_id']);

        // Multiple models — each instantiated at most once per request
        return ModelRegistry::getModel('PermissionModel')
            ->getByRoleId($role['id'] ?? 0);
    }

    public function deactivateUser(int $userId): bool
    {
        ModelRegistry::getModel('AuditModel')
            ->log('user.deactivate', ['user_id' => $userId]);

        return $this->update($userId, ['status' => 'inactive']);
    }
}
```

### 3. Use the same pattern in Controllers

```php
use App\Libraries\ModelRegistry;

class UserController extends Controller
{
    // No constructor model loading

    public function show(int $id)
    {
        $user = ModelRegistry::getModel('UserModel')->getUserWithRole($id);
        return $this->response->setJSON($user);
    }

    public function permissions(int $id)
    {
        $permissions = ModelRegistry::getModel('UserModel')->getUserPermissions($id);
        return $this->response->setJSON(['permissions' => $permissions]);
    }
}
```

### Mutual dependencies — now safe

```php
// RoleModel references UserModel
class RoleModel extends Model
{
    public function getUsersInRole(int $roleId): array
    {
        return ModelRegistry::getModel('UserModel')
            ->where('role_id', $roleId)
            ->findAll();
    }
}

// UserModel references RoleModel
class UserModel extends Model
{
    public function getRole(int $userId): ?array
    {
        $user = $this->find($userId);
        return ModelRegistry::getModel('RoleModel')->find($user['role_id']);
    }
}
```

These two models reference each other. With constructor injection this would be a fatal cycle. With `ModelRegistry`, each is created only when its method is called — by that point both constructors have long finished.

---

## API Reference

```php
// Get or create a model instance
ModelRegistry::getModel('UserModel');

// Register a pre-built instance (useful in tests)
ModelRegistry::register('UserModel', $mockUserModel);

// Check if a model has been initialized
ModelRegistry::has('UserModel'); // bool

// Release a specific model (next call re-creates it)
ModelRegistry::reset('UserModel');

// Release all models
ModelRegistry::reset();

// Use a different base namespace (default: App\Models\)
ModelRegistry::setNamespace('Modules\\Auth\\Models\\');
```

---

## Testing

In unit tests, register mock instances before the code under test runs:

```php
use App\Libraries\ModelRegistry;

class UserModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ModelRegistry::reset(); // ensure clean state per test
    }

    public function testGetUserWithRole(): void
    {
        $mockRole = $this->createMock(RoleModel::class);
        $mockRole->method('find')->willReturn(['id' => 1, 'name' => 'admin']);

        // Inject mock — ModelRegistry::getModel('RoleModel') will return this
        ModelRegistry::register('RoleModel', $mockRole);

        $userModel = new UserModel();
        $result = $userModel->getUserWithRole(42);

        $this->assertEquals('admin', $result['role']['name']);
    }
}
```

---

## Migration Checklist

When migrating a CI3 model to CI4:

- [ ] Remove all `$this->load->model(...)` calls from `__construct()`
- [ ] Remove all `parent::__construct()` calls that loaded models (keep if doing other init)
- [ ] Replace `$this->ModelName->method()` with `ModelRegistry::getModel('ModelName')->method()`
- [ ] Move model usage to the methods that actually need it (not the constructor)
- [ ] Add `use App\Libraries\ModelRegistry;` at the top of each file that uses the registry

---

## Why Not Use CI4's Built-in `model()` Helper?

CI4 ships a global `model('UserModel')` helper that also caches instances. It works for simple cases but:

- It uses the Services container, which can still trigger eager instantiation in complex graphs
- It does not give you a clean `::register()` hook for test mocks
- It requires the full CI4 bootstrap to be running (harder to unit test in isolation)

`ModelRegistry` is a pure static class with zero dependencies — it works anywhere PHP runs, including CLI scripts and test suites that don't boot the full framework.

---

## License

MIT
