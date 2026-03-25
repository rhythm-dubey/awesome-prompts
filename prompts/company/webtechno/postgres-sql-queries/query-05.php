use App\Models\Valuation;
use App\Models\ValuationContact;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

$result = DB::transaction(function () {
    $valuations = Valuation::query()->orderByDesc('valuation_id')->take(2)->get();

    if ($valuations->count() < 2) {
        throw new Exception('Need at least 2 valuation records.');
    }

    $source = $valuations[0];
    $target = $valuations[1];

    $contact = Contact::query()
        ->whereNotNull('last_name')
        ->where('last_name', '<>', '')
        ->orderByDesc('id')
        ->first();

    if (! $contact) {
        throw new Exception('Need at least 1 contact with non-empty last_name.');
    }

    $capacity = ValuationContact::query()
        ->whereNotNull('capacity')
        ->orderByDesc('id')
        ->value('capacity') ?? 1;

    $target->valuation_property_apartment_number_name = $source->valuation_property_apartment_number_name;
    $target->valuation_property_number_name = $source->valuation_property_number_name;
    $target->valuation_property_postcode = $source->valuation_property_postcode;
    $target->valuation_property_availability = $source->valuation_property_availability;
    $target->save();

    ValuationContact::query()
        ->whereIn('valuation_id', [$source->valuation_id, $target->valuation_id])
        ->where('is_primary', 1)
        ->delete();

    ValuationContact::create([
        'valuation_id' => $source->valuation_id,
        'contact_id' => $contact->id,
        'capacity' => $capacity,
        'is_primary' => 1,
    ]);

    ValuationContact::create([
        'valuation_id' => $target->valuation_id,
        'contact_id' => $contact->id,
        'capacity' => $capacity,
        'is_primary' => 1,
    ]);

    return [
        'source_valuation_id' => $source->valuation_id,
        'target_valuation_id' => $target->valuation_id,
        'contact_id' => $contact->id,
        'last_name' => $contact->last_name,
    ];
});


$result = DB::transaction(function () { $valuations = Valuation::query()->orderByDesc('valuation_id')->take(2)->get(); if ($valuations->count() < 2) { throw new Exception('Need at least 2 valuation records.'); } $source = $valuations[0]; $target = $valuations[1]; $contact = Contact::query()->whereNotNull('last_name')->where('last_name', '<>', '')->orderByDesc('id')->first(); if (! $contact) { throw new Exception('Need at least 1 contact with non-empty last_name.'); } $capacity = ValuationContact::query()->whereNotNull('capacity')->orderByDesc('id')->value('capacity') ?? 1; $target->valuation_property_apartment_number_name = $source->valuation_property_apartment_number_name; $target->valuation_property_number_name = $source->valuation_property_number_name; $target->valuation_property_postcode = $source->valuation_property_postcode; $target->valuation_property_availability = $source->valuation_property_availability; $target->save(); ValuationContact::query()->whereIn('valuation_id', [$source->valuation_id, $target->valuation_id])->where('is_primary', 1)->delete(); ValuationContact::create(['valuation_id' => $source->valuation_id, 'contact_id' => $contact->id, 'capacity' => $capacity, 'is_primary' => 1]); ValuationContact::create(['valuation_id' => $target->valuation_id, 'contact_id' => $contact->id, 'capacity' => $capacity, 'is_primary' => 1]); return ['source_valuation_id' => $source->valuation_id, 'target_valuation_id' => $target->valuation_id, 'contact_id' => $contact->id, 'last_name' => $contact->last_name]; });

$rows = DB::select("SELECT v.valuation_id FROM valuation v JOIN valuation_contact vc ON vc.valuation_id = v.valuation_id AND vc.is_primary = 1 JOIN contacts c ON c.id = vc.contact_id WHERE c.last_name IS NOT NULL AND c.last_name <> '' AND EXISTS ( SELECT 1 FROM valuation d JOIN valuation_contact dvc ON dvc.valuation_id = d.valuation_id AND dvc.is_primary = 1 JOIN contacts dc ON dc.id = dvc.contact_id WHERE d.valuation_id <> v.valuation_id AND d.valuation_property_apartment_number_name IS NOT DISTINCT FROM v.valuation_property_apartment_number_name AND d.valuation_property_number_name IS NOT DISTINCT FROM v.valuation_property_number_name AND d.valuation_property_postcode IS NOT DISTINCT FROM v.valuation_property_postcode AND d.valuation_property_availability IS NOT DISTINCT FROM v.valuation_property_availability AND dc.last_name = c.last_name ) ORDER BY v.valuation_id");