<?php

namespace App\Filament\Pages;

use App\Models\OrganizationUnit;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A dedicated onboarding screen instead of the generic Users > Create form:
 * departments on the left (so an admin sees the org structure while
 * placing someone into it, and can add a missing one inline without losing
 * their place), the new person's identity + role + reporting line on the
 * right. No password field -- this app doesn't have Entra ID/SSO wired up
 * yet, but once it is, provisioning will happen through SCIM
 * (ScimUserController) instead of this form, and a locally-typed password
 * won't mean anything for an SSO-provisioned account anyway. A random one
 * is generated and shown once (same pattern as ScimToken/ApiToken/
 * WebhookSubscription secrets) so the account is immediately usable today.
 */
class AddUser extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Katılımcılar';

    protected static ?string $navigationLabel = 'Kullanıcı Ekle';

    protected static ?string $title = 'Kullanıcı Ekle';

    protected string $view = 'filament.pages.add-user';

    public ?array $data = [];

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
                Select::make('role')->label('Rol')
                    ->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi'])
                    ->default('learner')->required()->native(false),
                Select::make('organization_unit_id')->label('Departman')
                    ->options(fn () => OrganizationUnit::orderBy('name')->pluck('name', 'id'))
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

    public function create(): void
    {
        $data = $this->form->getState();
        $password = Str::password(16);
        $department = ($data['organization_unit_id'] ?? null) ? OrganizationUnit::find($data['organization_unit_id']) : null;

        User::create([
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

        Notification::make()
            ->title('Kullanıcı oluşturuldu')
            ->body("Kaydettiğiniz şifre (bir kereliğine gösteriliyor, ".$data['email']." için):\n\n{$password}")
            ->success()
            ->persistent()
            ->send();

        $this->form->fill();
    }
}
