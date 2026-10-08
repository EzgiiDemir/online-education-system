<?php

namespace App\Filament\Pages;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use App\Filament\Concerns\TranslatesNavigation;

/**
 * Departments + onboarding in one screen instead of two: pick a department
 * on the left to see who's in it (rolled up through its sub-units, same as
 * ReportController's organization_unit filter), add a new person on the
 * right pre-scoped to that department. No password field -- this app
 * doesn't have Entra ID/SSO wired up yet, but once it is, provisioning will
 * happen through SCIM (ScimUserController) instead of this form, and a
 * locally-typed password won't mean anything for an SSO-provisioned account
 * anyway. A random one is generated and shown once (same pattern as
 * ScimToken/ApiToken/WebhookSubscription secrets) so the account is
 * immediately usable today.
 */
class AddUser extends Page
{
    use TranslatesNavigation;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Katılımcılar';

    protected static ?string $navigationLabel = 'Departmanlar';

    protected static ?string $title = 'Departmanlar';

    protected string $view = 'filament.pages.add-user';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Departmanlar');
    }

    public ?array $data = [];

    #[Url]
    public ?int $selectedDepartmentId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Ad soyad'))->required()->maxLength(255),
                TextInput::make('email')->label(__('E-posta (kullanıcı adı)'))->email()->required()
                    ->unique(table: 'users', column: 'email')
                    ->maxLength(255)
                    ->helperText(__('Kullanıcı bu e-posta ile giriş yapar.')),
                Select::make('role')->label(__('Birincil rol'))
                    ->options(['learner' => __('Öğrenci'), 'instructor' => __('Eğitmen'), 'manager' => __('Yönetici (Manager)'), 'admin' => __('Sistem Yöneticisi')])
                    ->default('learner')->required()->native(false)
                    ->helperText(__('Giriş yaptığında varsayılan olarak yönlendirileceği çalışma alanı.')),
                CheckboxList::make('extra_role_keys')->label(__('Ek roller'))
                    ->options(fn () => Role::orderBy('name')->get()->mapWithKeys(fn ($role) => [$role->key => __($role->name)]))
                    ->columns(2)
                    ->helperText(__('Bu kişi birden fazla çalışma alanına ihtiyaç duyuyorsa (ör. hem Eğitmen hem Yönetici) buradan ek rol verin.')),
                Select::make('organization_unit_id')->label(__('Departman'))
                    ->options(fn () => OrganizationUnit::orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => $this->selectedDepartmentId)
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('Departman adı'))->required()->maxLength(160),
                        Select::make('parent_id')->label(__('Üst birim'))
                            ->options(fn () => OrganizationUnit::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText(__('Boş bırakılırsa en üst seviye birim olur.')),
                    ])
                    ->createOptionModalHeading('Yeni departman ekle')
                    ->createOptionUsing(fn (array $data) => OrganizationUnit::create($data)->getKey())
                    ->helperText(__('Listede yoksa "+" ile yeni departman ekleyebilirsiniz -- kurum yapınız büyüdükçe buradan genişletilebilir.')),
                Select::make('manager_id')->label(__('Bağlı olduğu yönetici'))
                    ->options(fn () => User::where('role', 'manager')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->helperText(__('Bu kişi hangi yöneticinin ekibinde görünsün.')),
            ])
            ->statePath('data');
    }

    public function getDepartments()
    {
        return OrganizationUnit::withCount('users')->with('parent')->orderBy('name')->get();
    }

    public function getSelectedDepartment(): ?OrganizationUnit
    {
        return $this->selectedDepartmentId ? OrganizationUnit::find($this->selectedDepartmentId) : null;
    }

    /**
     * Rolled up through sub-units -- a department's own detail view should
     * count the same people ReportController's organization_unit filter
     * would surface for it, not just users assigned to that exact row.
     */
    public function getSelectedDepartmentMembers()
    {
        $department = $this->getSelectedDepartment();

        return $department ? $department->allUsers()->orderBy('name')->get() : collect();
    }

    public function getSelectedDepartmentRoleCounts(): array
    {
        $members = $this->getSelectedDepartmentMembers();

        return [
            'total' => $members->count(),
            'manager' => $members->where('role', 'manager')->count(),
            'instructor' => $members->where('role', 'instructor')->count(),
            'learner' => $members->where('role', 'learner')->count(),
            'admin' => $members->where('role', 'admin')->count(),
        ];
    }

    public function selectDepartment(?int $id): void
    {
        $this->selectedDepartmentId = $id;
        $this->form->fill(['organization_unit_id' => $id]);
    }

    public function create(): void
    {
        $data = $this->form->getState();
        $password = Str::password(16);
        $department = ($data['organization_unit_id'] ?? null) ? OrganizationUnit::find($data['organization_unit_id']) : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => $data['role'],
            'organization_unit_id' => $data['organization_unit_id'] ?? null,
            // Kept in sync for the older free-text department reports/
            // filters (ReportController) still read -- see OrganizationUnit's
            // own docblock for why the structured column is the primary one.
            'department' => $department?->name,
            'manager_id' => $data['manager_id'] ?? null,
        ]);

        $roleKeys = array_unique([$data['role'], ...($data['extra_role_keys'] ?? [])]);
        $user->roles()->sync(Role::whereIn('key', $roleKeys)->pluck('id'));

        Notification::make()
            ->title(__('Kullanıcı oluşturuldu'))
            ->body("Kaydettiğiniz şifre (bir kereliğine gösteriliyor, ".$data['email']." için):\n\n{$password}")
            ->success()
            ->persistent()
            ->send();

        $this->form->fill(['organization_unit_id' => $this->selectedDepartmentId]);
    }
}
