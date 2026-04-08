<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AppSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'App Settings';
    protected static string $view = 'filament.pages.app-settings';

    public ?string $min_version = '';
    public ?string $update_url = '';
    public bool $force_update = false;

    public function mount(): void
    {
        $config = AppSetting::getAppConfig();

        $this->min_version = $config['min_version'];
        $this->update_url = $config['update_url'];
        $this->force_update = $config['force_update'];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Force Update')->schema([
                TextInput::make('min_version')
                    ->label('Minimum Version')
                    ->required()
                    ->placeholder('1.0.0')
                    ->helperText('Minimum app version required (semver format: x.y.z)'),
                TextInput::make('update_url')
                    ->label('Update URL')
                    ->url()
                    ->placeholder('https://play.google.com/store/apps/details?id=...')
                    ->helperText('URL to open when user taps "Update Now"'),
                Toggle::make('force_update')
                    ->label('Force Update Enabled')
                    ->helperText('When enabled, users below min_version will be blocked from using the app'),
            ]),
        ]);
    }

    public function save(): void
    {
        AppSetting::updateOrCreate(['key' => 'min_version'], ['value' => $this->min_version]);
        AppSetting::updateOrCreate(['key' => 'update_url'], ['value' => $this->update_url]);
        AppSetting::updateOrCreate(['key' => 'force_update'], ['value' => $this->force_update ? 'true' : 'false']);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
