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
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Katılımcılar';

    protected static ?string $navigationLabel = 'Departmanlar';

    protected static ?string $title = 'Departmanlar';

    protected string $view = 'filament.pages.add-user';

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
                TextInput::make('name')->label('Ad soyad')->required()->maxLength(255),
                TextInput::make('email')->label('E-posta (kullanıcı adı)')->email()->required()
                    ->unique(table: 'users', column: 'email')
                    ->maxLength(255)
                    ->helperText('Kullanıcı bu e-posta ile giriş yapar.'),
                Select::make('role')->label('Birincil rol')
                    ->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi'])
                    ->default('learner')->required()->native(false)
                    ->helperText('Giriş yaptığında varsayılan olarak yönlendirileceği çalışma alanı.'),
                CheckboxList::make('extra_role_keys')->label('Ek roller')
                    ->options(fn () => Role::orderBy('name')->pluck('name', 'key'))
                    ->columns(2)
                    ->helperText('Bu kişi birden fazla çalışma alanına ihtiyaç duyuyorsa (ör. hem Eğitmen hem Yönetici) buradan ek rol verin.'),
                Select::make('organization_unit_id')->label('Departman')
                    ->options(fn () => OrganizationUnit::orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => $this->selectedDepartmentId)
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->label('Departman adı')->required()->maxLength(160),
                        Select::make('parent_id')->label('Üst birim')
                            ->options(fn () => OrganizationUnit::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Boş bırakılırsa en üst seviye birim olur.'),
                    ])
                    ->createOptionModalHeading('Yeni departman ekle')
                    ->createOptionUsing(fn (array $data) => OrganizationUnit::create($data)->getKey())
                    ->helperText('Listede yoksa "+" ile yeni departman ekleyebilirsiniz -- kurum yapınız büyüdükçe buradan genişletilebilir.'),
                Select::make('manager_id')->label('Bağlı olduğu yönetici')
                    ->options(fn () => User::where('role', 'manager')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->helperText('Bu kişi hangi yöneticinin ekibinde görünsün.'),
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
            ->title('Kullanıcı oluşturuldu')
            ->body("Kaydettiğiniz şifre (bir kereliğine gösteriliyor, ".$data['email']." için):\n\n{$password}")
            ->success()
            ->persistent()
            ->send();

        $this->form->fill(['organization_unit_id' => $this->selectedDepartmentId]);
    }
}
