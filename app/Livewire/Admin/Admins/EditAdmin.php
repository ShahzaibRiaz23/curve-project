<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Admins;

use App\Models\Admin;
use Livewire\Component;
use App\Services\LogService;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Forms\Admin\Admins\AdminForm;
use Illuminate\Validation\ValidationException;

#[Title('Admins')]
#[Layout('layouts.admin.app')]

class EditAdmin extends Component
{
    use WithFileUploads;

    public AdminForm $form;

    #[Locked]
    public $admin;

    #[Validate(['image' => 'required|image|max:1024'])]
    public $image = '';

    public $user;

    private string $guardName = 'admin'; // Guard name

    public function mount()
    {
        (new LogService)->PermissionLog('update:admins', Auth::guard($this->guardName)->user());

        $this->user = $this->user();

        if ($this->user->id === Auth::guard($this->guardName)->id()) {
            return $this->redirect(route('admin.profile'), navigate: true);
        }

        $this->form->id = $this->user->id;
        $this->form->first_name = $this->user->first_name;
        $this->form->last_name = $this->user->last_name;
        $this->form->address = $this->user->address;
        $this->form->phone = $this->user->phone;
        $this->form->username = $this->user->username;
        $this->form->email = $this->user->email;

        return null;

    }

    public function updatedImage(): void
    {
        try {
            $this->validateOnly('image', [
                'image' => 'nullable|image|mimes:jpg,png,jpeg,webp',
            ]);
        } catch (ValidationException) {
            if (is_object($this->image) && method_exists($this->image, 'delete')) {
                $this->image->delete();
            }
            $this->reset('image');

            return;
        }

        $this->form->image = $this->image;
    }

    public function save(): void
    {
        (new LogService)->PermissionLog('update:admins', Auth::guard($this->guardName)->user());

        $this->form->validate();

        $this->form->save();

        if ($this->form->swal !== []) {
            $this->dispatch('swal:alert', $this->form->swal);

            $this->form->swal = [];

            return;
        }
    }

    #[Computed]
    public function user(): ?Admin
    {
        return Admin::query()->findOrFail($this->admin);
    }

    #[Computed]
    public function breadcrumb(): array
    {
        return [
            __('Dashboard') => route('admin.dashboard'),
            __('Admins') => route('admin.admins.index'),
            __('Edit Admin') => '',
        ];
    }

    public function render()
    {
        return view('livewire.admin.admins.edit-admin');
    }
}
