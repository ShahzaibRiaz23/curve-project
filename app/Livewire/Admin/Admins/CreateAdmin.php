<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Admins;

use Livewire\Component;
use App\Services\LogService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Forms\Admin\Admins\AdminForm;

#[Title('Create Admin')]
#[Layout('layouts.admin.app')]
class CreateAdmin extends Component
{
    public AdminForm $form;

    private string $guardName = 'admin'; // Guard name

    public function save(): void
    {
        (new LogService)->PermissionLog('create:admins', Auth::guard($this->guardName)->user());

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
            __('Create Admin') => '',
        ];
    }

    public function render()
    {
        return view('livewire.admin.admins.create-admin');
    }
}
