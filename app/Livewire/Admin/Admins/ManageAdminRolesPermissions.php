<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Admins;

use App\Models\Admin;
use Livewire\Component;
use App\Services\LogService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use App\Livewire\Forms\Admin\Admins\AdminPermissionForm;

#[Title('Manage Admin Role or Permissions')]
#[Layout('layouts.admin.app')]
class ManageAdminRolesPermissions extends Component
{
    public AdminPermissionForm $form;

    #[Locked]
    public $admin;

    public $user;

    private string $guardName = 'admin'; // Guard name

    public function mount()
    {
        (new LogService)->PermissionLog('update:admin-role-permissions', Auth::guard($this->guardName)->user());

        $this->user = $this->user();

        if ($this->user->id === Auth::guard($this->guardName)->id()) {
            return $this->redirect(route('admin.admins.index'), navigate: true);
        }

        $this->form->guard_name = $this->guardName;

        $this->form->loadAdminPermissions($this->user);

        return null;
    }

    #[Computed]
    public function user(): ?Admin
    {
        return Admin::query()->findOrFail($this->admin);
    }

    #[Computed]
    public function permissions()
    {
        return Permission::query()->where('guard_name', $this->guardName)
            ->orderByRaw("SUBSTRING_INDEX(name, ':', -1) ASC")
            ->get()
            ->groupBy(function ($permission): string {
                $parts = explode(':', $permission->name);

                return $parts[1] ?? 'undefined';
            });
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->where('guard_name', $this->guardName)
            ->get();
    }

    #[Computed]
    public function role_permissions()
    {
        return Role::query()->where('id', $this->form->role_id)->first()?->permissions()
            ->pluck('name')->toArray() ?? [];
    }

    public function save(): void
    {
        (new LogService)->PermissionLog('update:admin-role-permissions', Auth::guard($this->guardName)->user());

        $this->form->validate();

        $this->form->save();

        if ($this->form->swal !== []) {
            $this->dispatch('swal:alert', $this->form->swal);

            $this->form->swal = [];

            return;
        }
    }

    #[Computed]
    public function breadcrumb(): array
    {
        return [
            __('Dashboard') => route('admin.dashboard'),
            __('Admins') => route('admin.admins.index'),
            __('Roles & Permissions') => '',
        ];
    }

    public function render()
    {
        return view('livewire.admin.admins.manage-admin-roles-permissions');
    }
}
