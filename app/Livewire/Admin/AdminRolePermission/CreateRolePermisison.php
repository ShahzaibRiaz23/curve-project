<?php

declare(strict_types=1);

namespace App\Livewire\Admin\AdminRolePermission;

use Livewire\Component;
use Illuminate\View\View;
use App\Services\LogService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use App\Livewire\Forms\Admin\AdminRolePermission\AdminRolePermissionForm;

#[Title('Create Admin Roles')]
#[Layout('layouts.admin.app')]
class CreateRolePermisison extends Component
{
    public AdminRolePermissionForm $form;

    private string $guardName = 'admin'; // Guard name

    public function mount(): void
    {
        (new LogService)->PermissionLog('create:admin-role-permissions', $this->guardName);
    }

    public function save(): void
    {
        (new LogService)->PermissionLog('create:admin-role-permissions', Auth::guard($this->guardName)->user());

        $this->form->validate();

        $this->form->save();

        if ($this->form->swal !== []) {
            $this->dispatch('swal:alert', $this->form->swal);
            $this->form->swal = [];

            return;
        }
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function permissions(): Collection
    {
        return Permission::query()->where('guard_name', $this->guardName)
            ->orderByRaw("SUBSTRING_INDEX(name, ':', -1) ASC")
            ->get()
            ->groupBy(function (Permission $permission): string {
                $parts = explode(':', $permission->name);

                return $parts[1];
            })
            ->map(fn ($group) => collect($group));
    }

    #[Computed]
    public function breadcrumb(): array
    {
        return [
            __('Dashboard') => route('admin.dashboard'),
            __('Admins') => route('admin.admins.index'),
            __('Roles & Permissions') => route('admin.admins.roles.index'),
            __('Create') => '',
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.admin-role-permission.create-role-permisison');
    }
}
