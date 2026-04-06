# RBAC + dynamic ACL — implementation prompt (Laravel 12/13 + Vue / Inertia)

Use this document as the **single source of truth** when scaffolding RBAC in a **fresh Laravel 12 or 13** app that already uses **Vue 3 + Inertia** (e.g. Breeze / starter kit with `HandleInertiaRequests`, Ziggy, and a `resources/js/pages` tree). Adapt namespaces and UI primitives to match the starter; **preserve the data model, behavior, and route shape** below.

---

## Role for the AI

Act as a senior Laravel architect. Implement a **production-ready, fully dynamic RBAC + ACL hybrid**: permissions are **not** free-form strings stored on users. Each assignable permission is a row in `model_action_permissions` (one **model** + one **action**). Roles receive many such rows via `role_permissions`. Users receive many roles via `role_user`.

---

## 1. Database (single migration recommended)

Create one migration (e.g. `create_rbac_tables`) with **no** classic `permissions` table. Use this schema:

| Table | Purpose |
|-------|---------|
| `roles` | `id`, `name`, `slug` (unique), `description` (nullable text), timestamps |
| `actions` | `id`, `name`, `slug` (unique), `type` (`tinyInteger`, default `0`), timestamps |
| `models` | `id`, `name`, `slug` (unique), timestamps — **business “resource” types** (not Eloquent model classes) |
| `model_action_permissions` | `id`, `model_id` → `models.id` (cascade), `action_id` → `actions.id` (cascade), timestamps, **unique** `(model_id, action_id)` |
| `role_permissions` | `id`, `role_id` → `roles.id` (cascade), `model_action_permission_id` → `model_action_permissions.id` (cascade), timestamps, **unique** `(role_id, model_action_permission_id)` |
| `role_user` | `id`, `role_id`, `user_id` (both constrained, cascade), timestamps, **unique** `(role_id, user_id)` |

**Drop order in `down()`:** `role_user`, `role_permissions`, `model_action_permissions`, `models`, `actions`, `roles`.

---

## 2. Action types (constants)

On the `Action` Eloquent model define:

- `TYPE_CORE = 0` — CRUD-style actions shared across models (e.g. create, read, update, delete).
- `TYPE_CUSTOM = 1` — optional per-model actions (e.g. approve, publish).

Add query scopes: `scopeCore`, `scopeCustom`. Add static helper `coreSlugs(): array` returning `['create', 'read', 'update', 'delete']` for `canCRUD` checks.

---

## 3. Eloquent models

| Class | Table / notes |
|-------|----------------|
| `ModelEntity` | `protected $table = 'models';` — avoids clashing with Laravel’s `Model` class |
| `Action` | As above |
| `ModelActionPermission` | Pivot entity; `modelEntity()` / `action()` / `roles()` |
| `Role` | `users()`, `permissions()` → `ModelActionPermission` via `role_permissions`; methods `syncPermissions`, `givePermissionTo`, `revokePermissionTo` (each must **bump** permission cache after changes) |
| `User` | Use trait `HasDynamicPermissions`; `roles()` many-to-many |

**Relationships:**

- `User` ↔ `Role` (many-to-many, `role_user`).
- `Role` ↔ `ModelActionPermission` (many-to-many, `role_permissions`).
- `ModelEntity` ↔ `Action` (many-to-many through `model_action_permissions`); also `hasMany` `modelActionPermissions`.

---

## 4. Dynamic behavior (must implement)

1. **New `ModelEntity`:** On `created`, automatically create `model_action_permissions` for **every core action** (`Action::query()->core()`). Method name example: `ensureCorePermissions()`.

2. **`ModelEntity::syncAvailableActions(array $actionIds)`:** Merge submitted action IDs with **all core** action IDs, `firstOrCreate` rows for each pair, **delete** `model_action_permissions` for this model that are not in the kept set, then bump cache.

3. **`Action` lifecycle:** When a **core** action is **created** or when an action is **updated** from custom → core, create missing `model_action_permissions` for **all** `ModelEntity` rows. **Custom** actions only attach to models given at create time or via sync endpoint.

4. **`ActionController::syncModels`:** For core actions, ensure every model has the permission; for custom actions, sync the given `model_ids` (add missing, remove extras for that action).

5. **Cache invalidation:** Any change to roles, role_permissions, role_user, model_action_permissions, models, or actions should increment a global **version** so user permission caches do not go stale (see §6).

---

## 5. User API — `HasDynamicPermissions` trait

Implement on `User`:

- `hasRole(string|array $role): bool` — match role **slug** or **name** (case-insensitive).
- `canAccess(string $model, string $action): bool` — `$model` and `$action` are **slugs** (case-insensitive); resolve effective permissions from the user’s roles → `model_action_permissions` → joined `models.slug` + `actions.slug`.
- `canCRUD(string $model, string $action): bool` — same as `canAccess` but only if `$action` is one of `Action::coreSlugs()`.
- `permissionStrings(): array` — flat list of `"{modelSlug}.{actionSlug}"` for the authenticated user, sorted unique (for Inertia / frontend).
- `syncRoles`, `assignRole`, `removeRole` — sync pivot and clear in-memory matrix + bump cache.

**`cachedPermissions()`:** Return shape `array<string, list<string>>` keyed by **model slug**, values = **action slugs**. Use `Cache::remember` with a key that includes **PermissionCache::version()** (see below). **In-request memoization:** if version matches, reuse built matrix on the same user instance.

**`buildPermissionsMatrix()`:** Query `model_action_permissions` joined through `role_permissions` and `role_user` for the current user id; eager load `modelEntity` (id, slug) and `action` (id, slug); group by model slug, unique action slugs.

---

## 6. `App\Support\PermissionCache`

- `VERSION_KEY` in cache (e.g. `rbac.permissions.version`), `version(): int`, `bump(): int` (increment forever).
- `userKey($userId): string` includes version, e.g. `rbac.user.{id}.permissions.v{version}`.

Call `PermissionCache::bump()` from:

- `Role` saved/deleted; `ModelActionPermission` saved/deleted; `ModelEntity` created/saved/deleted; `Action` saved/deleted; `Role::syncPermissions` / `givePermissionTo` / `revokePermissionTo`; `ModelEntity::syncAvailableActions`; user role sync/assign/remove in the trait.

---

## 7. Middleware

**`CheckAccess`:** Signature `handle($request, $next, string $model, string $action)`. Require auth; `abort_unless($user->canAccess($model, $action), 403, ...)`.

**`CheckRole`:** `handle($request, $next, string ...$roles)`. Require auth; `abort_unless($user->hasRole($roles), 403, ...)`.

Register aliases in `bootstrap/app.php` (or equivalent):

- `'access' => \App\Http\Middleware\CheckAccess::class`
- `'role' => \App\Http\Middleware\CheckRole::class`

Usage example: `->middleware(['auth', 'access:property,approve'])` (model slug, action slug).

---

## 8. Gate (optional but recommended)

In `AppServiceProvider::boot()`:

```php
Gate::define('access', fn ($user, string $model, string $action) => $user->canAccess($model, $action));
```

---

## 9. Routes — dedicated `routes/rbac.php`

Keep [`routes/web.php`](routes/web.php) limited to public/app shell routes; **require** RBAC routes:

```php
require __DIR__.'/rbac.php';
```

In **`routes/rbac.php`**, define:

1. **Example guarded route** (for tests / docs):  
   `GET rbac/examples/property-approve` → JSON stub, middleware `auth`, `access:property,approve`, name `rbac.examples.property-approve`.

2. **Admin group:** `middleware(['auth', 'access:rbac,manage'])`, `prefix('rbac')`, then:
   - `Route::resource('roles', RoleController::class)->except(['create', 'edit']);`
   - `PUT roles/{role}/users` → `syncUsers`, name `roles.users.sync`
   - `GET|POST|PUT|DELETE` routes for role permissions matrix (`RolePermissionController`): `roles/{role}/permissions`, sync, destroy single permission
   - `Route::resource('models', ModelController::class)->parameters(['models' => 'modelEntity'])->except(['create', 'edit']);`
   - `PUT models/{modelEntity}/actions` → `syncActions`, name `models.actions.sync`
   - `Route::resource('actions', ActionController::class)->except(['create', 'edit']);`
   - `PUT actions/{action}/models` → `syncModels`, name `actions.models.sync`

Route **names** stay short (`roles.index`, `actions.store`, …) while URLs are under `/rbac/...`.

---

## 10. Controllers (Inertia)

Implement **inertia responses** for index pages; use redirects for mutating actions; use `redirect()->back()->with('status', ...)` where appropriate.

| Controller | Responsibilities |
|------------|------------------|
| `RoleController` | List roles with users; CRUD; `syncUsers` |
| `ModelController` | List models with linked actions; CRUD; `syncActions` |
| `ActionController` | List actions with linked models; CRUD; `syncModels`; `show` redirects to index |
| `RolePermissionController` | Matrix page: models × actions; `store` / `destroy` / `sync` of `model_action_permission` IDs; `resolvePermission` supports either `model_action_permission_id` or `model` + `action` slugs |

---

## 11. Form requests (Action controller)

Under `App\Http\Requests\Rbac\`:

- **`StoreActionRequest`** — `name`, `slug` (unique `actions,slug`, `alpha_dash`), `type` (in `[TYPE_CORE, TYPE_CUSTOM]`), `model_ids` array, `model_ids.*` exists `models,id`.
- **`UpdateActionRequest`** — same fields as update in app (no `model_ids`); slug unique ignoring `$this->route('action')`.
- **`SyncActionModelsRequest`** — `model_ids` + wildcard exists rules.

Type-hint these on `store`, `update`, `syncModels`; use `$request->validated()`. `authorize(): true` unless policies exist.

*(Optional later: mirror the same pattern for `RoleController`, `ModelController`, `RolePermissionController`.)*

---

## 12. Inertia shared data

In `HandleInertiaRequests::share`, for authenticated users `loadMissing('roles')` and expose:

```php
'auth' => [
    'user' => $user,
    'roles' => /* id, name, slug list */,
    'permissions' => $user?->permissionStrings() ?? [],
],
```

Frontend can check `permissions.includes('property.approve')` or similar.

---

## 13. Vue UI (match starter styling)

Add pages under `resources/js/pages/rbac/` (path casing per kit):

| Page | Inertia name | Purpose |
|------|----------------|---------|
| `RolesIndex.vue` | `rbac/RolesIndex` | CRUD roles, assign users, link to matrix |
| `RolePermissionMatrix.vue` | `rbac/RolePermissionMatrix` | Toggle grid; bulk `PUT roles.permissions.sync` |
| `ModelsIndex.vue` | `rbac/ModelsIndex` | CRUD models, sync actions |
| `ActionsIndex.vue` | `rbac/ActionsIndex` | CRUD actions, sync models, core vs custom |

Reusable editors (optional but recommended): `components/rbac/RoleEditor.vue`, `ModelEditor.vue`, `ActionEditor.vue`. Use Ziggy `route()` for all form posts.

Add **navigation links** only for users who can `access:rbac,manage` (e.g. hide RBAC sidebar items unless `permissions` contains `rbac.manage`).

---

## 14. `RbacSeeder`

1. **Role:** `admin` (name “Admin”, description).
2. **Models:** at least `property`, `lease`, `application`, and **`rbac`** (name “RBAC”) — the last is for guarding the admin UI with `rbac.manage`.
3. **Actions:** core — create, read, update, delete; custom — approve, publish, **manage** (for RBAC admin).
4. For **every** `ModelEntity`, create `model_action_permissions` for **all core actions**.
5. **Custom actions per model** (example): property → approve, publish; lease → approve; application → approve; rbac → manage.
6. **`$adminRole->syncPermissions(ModelActionPermission::query()->pluck('id')->all());`**
7. Call `PermissionCache::bump()` at end.

Register `RbacSeeder` from `DatabaseSeeder`. Optionally assign the first user the `admin` role.

---

## 15. Feature tests (Pest or PHPUnit)

Cover at least:

- User with a role that has one `model_action_permission` can `canAccess` / `permissionStrings` correctly and cannot access unrelated actions.
- Creating a new `ModelEntity` auto-creates core `model_action_permissions`.
- `access` middleware returns 403 without permission, 200 with permission.
- RBAC admin routes forbidden without `rbac.manage`, allowed with it.
- Inertia shared `auth.permissions` contains expected strings for a seeded admin.

---

## 16. Deliverables checklist

- [ ] Migration(s) for all tables above  
- [ ] Models + `HasDynamicPermissions` + `PermissionCache`  
- [ ] Middleware + `bootstrap/app.php` aliases  
- [ ] `Gate::define('access', ...)`  
- [ ] `routes/rbac.php` + `require` from `web.php`  
- [ ] Four controllers + Inertia pages (+ optional editor components)  
- [ ] `App\Http\Requests\Rbac\*` for `ActionController`  
- [ ] `RbacSeeder` + `DatabaseSeeder` wiring  
- [ ] Feature tests  
- [ ] Short README note: permission = `model_slug` + `action_slug`; use `middleware('access:model,action')` or `$user->canAccess('model','action')`

---

## 17. Non-goals

- Do **not** introduce a separate global `permissions` string table unless you also keep `model_action_permissions` as the assignable unit of record.
- Do **not** hardcode permission checks scattered as raw strings without going through `canAccess` / `access` middleware / shared `auth.permissions`.

---

*This spec matches the reference implementation in this repository (dynamic matrix, versioned cache, Inertia admin UI, and route split). Adjust only where the starter kit forces different file paths or UI components.*
