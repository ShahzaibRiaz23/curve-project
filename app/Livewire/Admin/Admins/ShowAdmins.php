<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Admins;

use App\Models\Admin;
use App\Traits\Pager;
use Livewire\Component;
use App\Services\QrService;
use Livewire\Attributes\On;
use App\Services\LogService;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

#[Title('Admins')]
#[Layout('layouts.admin.app')]
class ShowAdmins extends Component
{
    use AuthorizesRequests, Pager, WithPagination;

    #[Url]
    public array $filters = [
        'search' => '',
        'role' => '',
    ];

    private string $guardName = 'admin'; // Guard name

    public function mount()
    {
        $this->authorize('view:admins', $this->guardName);
    }

    #[Computed]
    public function modernSearch(): array
    {
        return [
            [
                'title' => __('Filter By Roles'),
                'type' => 'select',
                'model' => 'role',
                'options' => Role::all()->pluck('name', 'id'),
                'builder' => fn ($qr, $val) => $qr->whereHas('roles', fn ($q) => $q->where('id', $val)),
            ],
            [
                'title' => __('Search'),
                'type' => 'text',
                'model' => 'search',
                'builder' => fn ($qr, $val) => $qr->whereAny(['first_name', 'last_name', 'email', 'phone', 'address'], 'like', '%'.$val.'%'),
            ],

        ];
    }

    #[On('downloadQr')]
    public function downloadQr($id)
    {
        $user = $this->rows()->find($id);

        (new LogService)->RegisterLog(Auth::guard($this->guardName)->user(), "Downloaded Qr {$user->full_name} site admin");
        $qr = (new QrService)->GenerateQr($user, '.');

        return response()->download(storage_path("app\private\\".$qr))->deleteFileAfterSend(true);
    }

    #[On('delete')]
    public function deleteAdmin($id): void
    {
        $admin = Admin::query()->findOrFail($id);

        (new LogService)->PermissionLog('delete:admins', Auth::guard($this->guardName)->user(), "Delete {$admin->full_name}");

        if ($admin->id === Auth::guard($this->guardName)->id()) {
            $this->dispatch('swal:alert', [
                'title' => __('Failed'),
                'text' => __('You cannot delete yourself'),
                'icon' => 'error',
            ]);

            return;
        }

        $admin->delete();

        $this->dispatch('swal:alert', [
            'title' => __('Admin Deleted'),
            'text' => __('Admin has been deleted successfully'),
            'icon' => 'success',
            'timer' => 2000,
            'bar' => true,
        ]);
    }

    #[On('qrGenerate')]
    public function qrGenerate()
    {
        try {

            $zipPath = (new QrService)->GenerateZip(Admin::whereIn('id', $this->group)->get(), 'site-admins-Qrs');

            $this->group = [];
            $this->allChecked = false;

            $this->dispatch('swal:alert', [
                'title' => __('Qr Codes Generated'),
                'text' => __('Qr Codes has been downloaded successfully'),
                'icon' => 'success',
                'timer' => 2000,
                'bar' => true,
            ]);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Throwable $th) {

            $this->dispatch('swal:alert', [
                'title' => __('Qr Codes Generate Service Failed'),
                'text' => __('Qr Codes ere failed to downloaded. Try again'),
                'icon' => 'error',
                'timer' => 2000,
                'bar' => true,
            ]);

            return;
        }

    }

    public function checked()
    {
        return $this->buildQuery(Admin::query())->pluck('id')->toArray();

    }

    #[Computed]
    public function rows()
    {
        return $this->buildQuery(Admin::query())
            ->with('roles')
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function breadcrumb(): array
    {
        return [
            __('Dashboard') => route('admin.dashboard'),
            __('Admins') => route('admin.admins.index'),
        ];
    }

    public function render()
    {
        return view('livewire.admin.admins.show-admins');
    }
}
