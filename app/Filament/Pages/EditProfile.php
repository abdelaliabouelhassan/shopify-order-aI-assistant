<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Filament\Notifications\Notification;

class EditProfile extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profile';
    protected static ?string $title = 'Edit Profile';

    protected static string $view = 'filament.pages.edit-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->email()
                            ->required(),
                    ]),
                Section::make('Change Password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->required()
                            ->rules(['current_password']),

                        TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rules([
                                'min:8',
                                Password::defaults()
                            ])
                            ->dehydrated(false),

                        TextInput::make('new_password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->required()
                            ->same('new_password')
                            ->dehydrated(false)
                    ])
            ])
            ->statePath('data');
    }

    public function update()
    {
        $data = $this->form->getState();
        $user = auth()->user();

        // Update name and email
        $user->update([
            'name' => $data['name'],
            'email' => $data['email']
        ]);

        // Change password if new password provided
        if (!empty($data['new_password'])) {
            $user->update([
                'password' => Hash::make($data['new_password'])
            ]);
        }

        Notification::make()
            ->success()
            ->title('Profile Updated')
            ->body('Your profile has been successfully updated.')
            ->send();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Account';
    }
}
