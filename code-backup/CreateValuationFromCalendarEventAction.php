<?php

namespace App\Filament\Resources\CalendarEvents\Actions;

use App\Filament\Forms\Fields\ReusableInputs;
use App\Models\Branch;
use App\Models\Capacity;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Employee;
use App\Models\PropertyCategory;
use App\Models\PropertyPossessionStatus;
use App\Models\Valuation;
use App\Models\ValuationAvailability;
use App\Models\ValuationContact;
use App\Models\ValuationLeadSource;
use App\Models\ValuationReason;
use App\Models\ValuationTask;
use App\Models\ValuationTaskDefaults;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateValuationFromCalendarEventAction
{
    public static function make(): Action
    {
        return Action::make('calendarEventAddValuation')
            ->label('Add New')
            ->button()
            ->color('gray')
            ->modalHeading('Create Valuation')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Save')
            ->modalCancelActionLabel('Cancel')
            ->form(function (): array {
                return [
                    Section::make('Contact (Duplicate valuation check)')
                        ->schema([
                            ReusableInputs::contactSelect(
                                'contact_id',
                                'Contact',
                                true,
                                fn ($query) => $query->where('client_type', '!=', 2),
                            )
                                ->live()
                                ->afterStateUpdated(function ($state) {
                                    if (! $state) {
                                        return;
                                    }

                                    $valuationIds = ValuationContact::where('contact_id', $state)
                                        ->pluck('valuation_id')
                                        ->toArray();

                                    if ($valuationIds === []) {
                                        return;
                                    }

                                    $valuationIdsString = implode(', #', $valuationIds);
                                    Notification::make()
                                        ->title('Existing valuations found')
                                        ->body('This contact is already associated with valuation ID(s): #'.$valuationIdsString.'. Please review before proceeding.')
                                        ->warning()
                                        ->persistent()
                                        ->send();
                                })
                                ->columnSpanFull(),

                            Select::make('capacity')
                                ->label('Capacity')
                                ->options(fn () => Capacity::query()
                                    ->orderBy('capacity_name')
                                    ->pluck('capacity_name', 'capacity_id')
                                    ->toArray())
                                ->default(9)
                                ->required()
                                ->searchable()
                                ->preload(),

                            Textarea::make('valuation_legal_owner_contact_details')
                                ->label("Legal Owner's Contact Details")
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Section::make('Valuation property address')
                        ->schema([
                            TextInput::make('valuation_property_postcode')
                                ->label('Post Code')
                                ->required()
                                ->maxLength(8),
                            TextInput::make('valuation_property_apartment_number_name')
                                ->label('Apartment/Flat Number')
                                ->maxLength(60),
                            TextInput::make('valuation_property_number_name')
                                ->label('Building Number and/or Name')
                                ->required()
                                ->maxLength(60),
                            TextInput::make('valuation_property_address_line_1')
                                ->label('Address Line 1')
                                ->required()
                                ->maxLength(60),
                            TextInput::make('valuation_property_address_line_2')
                                ->label('Address Line 2')
                                ->maxLength(60),
                            TextInput::make('valuation_property_suburb')
                                ->label('Suburb')
                                ->maxLength(60),
                            TextInput::make('valuation_property_town_city')
                                ->label('Town/City')
                                ->maxLength(60),
                            Select::make('valuation_property_country')
                                ->label('Country')
                                ->options(fn () => Country::query()
                                    ->orderBy('country_name')
                                    ->pluck('country_name', 'country_id')
                                    ->toArray())
                                ->default(1)
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->columns(2),

                    Section::make('Valuation details')
                        ->schema([
                            Select::make('valuation_property_availability')
                                ->label('Availability')
                                ->options(fn () => ValuationAvailability::query()
                                    ->where('valuation_availability_archived', 2)
                                    ->orderBy('valuation_availability_name')
                                    ->pluck('valuation_availability_name', 'valuation_availability_id')
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('valuation_property_category')
                                ->label('Category')
                                ->options(fn () => PropertyCategory::query()
                                    ->orderBy('property_category_name')
                                    ->pluck('property_category_name', 'property_category_id')
                                    ->toArray())
                                ->default(1)
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('valuation_occupancy')
                                ->label('Occupancy')
                                ->options(fn () => PropertyPossessionStatus::query()
                                    ->orderBy('property_possession_status_name')
                                    ->pluck('property_possession_status_name', 'property_possession_status_id')
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('valuation_reason')
                                ->label('Reason')
                                ->options(fn () => ValuationReason::query()
                                    ->orderBy('valuation_reason_name')
                                    ->pluck('valuation_reason_name', 'valuation_reason_id')
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Textarea::make('valuation_reason_notes')
                                ->label('Reason Notes')
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),

                            Select::make('valuation_lead_source')
                                ->label('Lead Source')
                                ->options(fn () => ValuationLeadSource::query()
                                    ->orderBy('valuation_lead_source_name')
                                    ->pluck('valuation_lead_source_name', 'valuation_lead_source_id')
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Textarea::make('valuation_lead_source_notes')
                                ->label('Lead Source Notes')
                                ->rows(3)
                                ->columnSpanFull(),

                            Textarea::make('valuation_notes')
                                ->label('Public Notes')
                                ->rows(4)
                                ->columnSpanFull(),
                            Textarea::make('valuation_notes_private')
                                ->label('Private Notes')
                                ->rows(4)
                                ->columnSpanFull(),

                            Select::make('valuation_branch')
                                ->label('Branch')
                                ->options(fn () => Branch::query()
                                    ->orderBy('branch_name')
                                    ->pluck('branch_name', 'branch_id')
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('valuation_negotiator')
                                ->label('Negotiator')
                                ->options(fn () => Employee::query()
                                    ->where('employee_status', 1)
                                    ->orderBy('employee_first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Employee $e) => [$e->employee_id => $e->employee_first_name.' '.$e->employee_surname])
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('valuation_employee')
                                ->label('Valuer')
                                ->options(fn () => Employee::query()
                                    ->where('employee_status', 1)
                                    ->orderBy('employee_first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Employee $e) => [$e->employee_id => $e->employee_first_name.' '.$e->employee_surname])
                                    ->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->columns(2),

                    Text::make('You can also open the full valuation page in a new tab.')
                        ->columnSpanFull(),
                ];
            })
            ->action(function (array $data, Set $set): void {
                $employee = Auth::user()?->employee;
                $employeeId = (int) ($employee?->employee_id ?? 0);

                if ($employeeId <= 0) {
                    Notification::make()
                        ->title('Employee Required')
                        ->body('Current user must have an associated employee record.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $contactId = (int) ($data['contact_id'] ?? 0);
                if ($contactId <= 0) {
                    throw new Halt;
                }

                $postcode = self::formatPostcode((string) ($data['valuation_property_postcode'] ?? ''));
                $apartment = trim((string) ($data['valuation_property_apartment_number_name'] ?? ''));
                $number = trim((string) ($data['valuation_property_number_name'] ?? ''));
                $availability = (int) ($data['valuation_property_availability'] ?? 0);

                $contactSurname = Contact::query()
                    ->where('id', $contactId)
                    ->value('last_name');

                if ($postcode !== '' && $number !== '' && $availability > 0 && filled($contactSurname)) {
                    $today = date('Y-m-d');

                    $exactDuplicateToday = DB::table('valuation')
                        ->join('valuation_contact', 'valuation.valuation_id', '=', 'valuation_contact.valuation_id')
                        ->join('contacts', 'valuation_contact.contact_id', '=', 'contacts.id')
                        ->where('valuation.valuation_property_postcode', $postcode)
                        ->where('valuation.valuation_property_apartment_number_name', $apartment)
                        ->where('valuation.valuation_property_number_name', $number)
                        ->where('valuation.valuation_property_availability', $availability)
                        ->where('contacts.last_name', $contactSurname)
                        ->whereDate('valuation.valuation_date_created', $today)
                        ->pluck('valuation.valuation_id')
                        ->toArray();

                    if ($exactDuplicateToday !== []) {
                        $valuationIdsString = implode(', #', $exactDuplicateToday);
                        Notification::make()
                            ->title('Duplicate valuation')
                            ->body('The valuation could not be added as it is a duplicate of valuation ID(s) #'.$valuationIdsString.'. Please add a calendar event against the existing valuation.')
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }

                    $previousDuplicates = DB::table('valuation')
                        ->join('valuation_contact', 'valuation.valuation_id', '=', 'valuation_contact.valuation_id')
                        ->join('contacts', 'valuation_contact.contact_id', '=', 'contacts.id')
                        ->where('valuation.valuation_property_postcode', $postcode)
                        ->where('valuation.valuation_property_apartment_number_name', $apartment)
                        ->where('valuation.valuation_property_number_name', $number)
                        ->where('valuation.valuation_property_availability', $availability)
                        ->where('contacts.last_name', $contactSurname)
                        ->pluck('valuation.valuation_id')
                        ->toArray();

                    if ($previousDuplicates !== []) {
                        $valuationIdsString = implode(', #', $previousDuplicates);
                        Notification::make()
                            ->title('Duplicate valuation')
                            ->body('The valuation could not be added as it is a duplicate of valuation ID(s) #'.$valuationIdsString.'. Please add a calendar event against the existing valuation.')
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }
                }

                if ($postcode !== '' && $number !== '' && $availability > 0) {
                    $potentialDuplicates = DB::table('valuation')
                        ->where('valuation_property_postcode', $postcode)
                        ->where('valuation_property_apartment_number_name', $apartment)
                        ->where('valuation_property_number_name', $number)
                        ->where('valuation_property_availability', $availability)
                        ->pluck('valuation_id')
                        ->toArray();

                    if ($potentialDuplicates !== []) {
                        $valuationIdsString = implode(', #', $potentialDuplicates);
                        Notification::make()
                            ->title('Potential duplicate valuation')
                            ->body('The valuation may be a duplicate of valuation ID(s) #'.$valuationIdsString.'. Please review the previous valuation to establish whether it is a duplicate.')
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }

                $valuationData = $data;
                unset($valuationData['contact_id'], $valuationData['capacity']);

                $valuationData['valuation_property_postcode'] = $postcode;
                $valuationData['valuation_property_apartment_number_name'] = Str::title(Str::lower((string) ($valuationData['valuation_property_apartment_number_name'] ?? '')));
                $valuationData['valuation_property_apartment_number_name'] = str_replace(['Apt ', 'Apartment ', 'Flat '], '', (string) $valuationData['valuation_property_apartment_number_name']);
                $valuationData['valuation_property_number_name'] = Str::title(Str::lower((string) ($valuationData['valuation_property_number_name'] ?? '')));
                $valuationData['valuation_property_address_line_1'] = Str::title(Str::lower((string) ($valuationData['valuation_property_address_line_1'] ?? '')));
                $valuationData['valuation_property_address_line_2'] = Str::title(Str::lower((string) ($valuationData['valuation_property_address_line_2'] ?? '')));
                $valuationData['valuation_property_suburb'] = Str::title(Str::lower((string) ($valuationData['valuation_property_suburb'] ?? '')));
                $valuationData['valuation_property_town_city'] = Str::title(Str::lower((string) ($valuationData['valuation_property_town_city'] ?? '')));

                $valuationData['valuation_lead_source_notes'] = Str::ucfirst((string) ($valuationData['valuation_lead_source_notes'] ?? ''));
                $valuationData['valuation_reason_notes'] = Str::ucfirst((string) ($valuationData['valuation_reason_notes'] ?? ''));
                $valuationData['valuation_notes'] = Str::ucfirst((string) ($valuationData['valuation_notes'] ?? ''));
                $valuationData['valuation_notes_private'] = Str::ucfirst((string) ($valuationData['valuation_notes_private'] ?? ''));

                $valuationData['valuation_referral_solicitor_notes'] = 'Not at this stage.';
                $valuationData['valuation_referral_mortgage_notes'] = 'Not at this stage.';

                if (empty($valuationData['valuation_status'])) {
                    $valuationData['valuation_status'] = 1;
                }

                $valuationData['valuation_created_by'] = $employeeId;
                $valuationData['valuation_updated_by'] = $employeeId;
                $valuationData['valuation_date_created'] = now();
                $valuationData['valuation_date_updated'] = now();

                $valuation = new Valuation;
                $valuation->fill($valuationData);
                $valuation->save();

                ValuationContact::create([
                    'valuation_id' => $valuation->valuation_id,
                    'contact_id' => $contactId,
                    'capacity' => (int) ($data['capacity'] ?? 9),
                    'is_primary' => true,
                ]);

                $taskDefaultsType = in_array((string) ($valuation->valuation_property_availability ?? ''), ['1', '3'], true) ? '1' : '2';
                $taskDefaults = ValuationTaskDefaults::where('valuation_task_defaults_type', $taskDefaultsType)
                    ->orderBy('valuation_task_defaults_sort')
                    ->get();

                $taskData = [];
                foreach ($taskDefaults as $taskDefault) {
                    $taskData[] = [
                        'valuation_id' => $valuation->valuation_id,
                        'valuation_task_name' => $taskDefault->valuation_task_defaults_name,
                        'valuation_task_status' => $taskDefault->valuation_task_defaults_status,
                        'valuation_task_notes_private' => $taskDefault->valuation_task_defaults_notes,
                        'valuation_task_sort' => $taskDefault->valuation_task_defaults_sort,
                        'valuation_task_date_updated' => now(),
                        'valuation_task_updated_by' => $employeeId,
                    ];
                }

                if ($taskData !== []) {
                    ValuationTask::insert($taskData);
                }

                $set('calendar_event_valuation', $valuation->valuation_id);

                Notification::make()
                    ->title('Valuation created')
                    ->success()
                    ->send();
            });
    }

    private static function formatPostcode(string $postcode): string
    {
        $cleanPostcode = preg_replace('/[^A-Za-z0-9]/', '', $postcode);
        $cleanPostcode = strtoupper((string) $cleanPostcode);

        if (strlen($cleanPostcode) > 3) {
            return substr($cleanPostcode, 0, -3).' '.substr($cleanPostcode, -3);
        }

        return $cleanPostcode;
    }
}

