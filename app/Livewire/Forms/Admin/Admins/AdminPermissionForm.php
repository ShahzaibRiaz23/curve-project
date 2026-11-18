<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin\Admins;

use Livewire\Form;
use App\Models\Admin;
use Spatie\Permission\Models\Role;

/**
 * AdminPermissionForm handles roles and permissions for admins.
 */
class AdminPermissionForm extends Form
{
    public string $guard_name = '';

    public ?int $role_id = 0;

    public array $permissions = [];

    public int $admin_id;

    public array $swal = [];

    public function rules(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * Load admin roles and permissions.
     */
    public function loadAdminPermissions(Admin $admin): void
    {
        $admin->load(['roles', 'permissions']);

        $this->admin_id = $admin->getKey();

        $this->role_id = $admin->roles->first()?->id ?? 0;

        $adminPermissions = $admin->permissions->pluck('name')->toArray();

        $this->permissions = $adminPermissions;
    }

    /**
     * Save the admin role and permissions.
     */
    public function save(): void
    {
        $admin = Admin::query()->find($this->admin_id);

        $admin_role = $admin->roles->first();

        if ($this->role_id !== $admin_role?->id) {
            $role = Role::query()->where('guard_name', $this->guard_name)
                ->where('id', $this->role_id)
                ->first();

            $admin->syncRoles($role);
        }

        $admin->syncPermissions($this->permissions);

        $this->swal = [
            'icon' => 'success',
            'title' => 'Role & Permissions Updated',
            'text' => 'The admin role and permissions have been updated successfully!',
            'url' => route('admin.admins.index'),
            'timer' => 1000,
        ];
    }
}
