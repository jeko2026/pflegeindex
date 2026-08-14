<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ContactSuggestion;
use App\Models\Facility;
use App\Rules\AbsoluteHttpUrl;
use App\Support\HttpUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $content = trim((string) $request->query('content', ''));
        $phone = trim((string) $request->query('phone', ''));
        $email = trim((string) $request->query('email', ''));
        $website = trim((string) $request->query('website', ''));
        $source = trim((string) $request->query('source', ''));
        $city = is_numeric($request->query('city')) ? (int) $request->query('city') : null;
        $selectedCity = $city !== null ? City::query()->find($city) : null;
        $missing = trim((string) $request->query('missing', ''));
        if ($missing === 'phone') {
            $phone = 'without';
        } elseif ($missing === 'email') {
            $email = 'without';
        } elseif ($missing === 'website') {
            $website = 'without';
        } else {
            $missing = '';
        }

        $facilities = Facility::query()
            ->with('city')
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $search) use ($query): void {
                    $like = "%{$query}%";
                    $search->where('name', 'like', $like)
                        ->orWhere('postal_code', 'like', $like)
                        ->orWhereHas('city', fn (Builder $city) => $city->where('name', 'like', $like));
                });
            })
            ->when($city !== null, fn (Builder $builder) => $builder->where('city_id', $city))
            ->when($status === 'missing', fn (Builder $builder) => $builder->whereNull('contact_status'))
            ->when($status === 'unverified', fn (Builder $builder) => $builder->where(fn (Builder $open) => $open->whereNull('contact_status')->orWhere('contact_status', '!=', 'verified')))
            ->when(in_array($status, ['verified', 'pending', 'not_found'], true), fn (Builder $builder) => $builder->where('contact_status', $status))
            ->when($phone === 'with', fn (Builder $builder) => $builder->whereNotNull('phone')->where('phone', '!=', ''))
            ->when($phone === 'without', fn (Builder $builder) => $builder->where(fn (Builder $missing) => $missing->whereNull('phone')->orWhere('phone', '')))
            ->when($email === 'with', fn (Builder $builder) => $builder->whereNotNull('email')->where('email', '!=', ''))
            ->when($email === 'without', fn (Builder $builder) => $builder->where(fn (Builder $missing) => $missing->whereNull('email')->orWhere('email', '')))
            ->when($email === 'official_absent', fn (Builder $builder) => $builder->where('official_email_absent', true))
            ->when($website === 'with', fn (Builder $builder) => $builder->whereNotNull('website')->where('website', '!=', ''))
            ->when($website === 'without', fn (Builder $builder) => $builder->where(fn (Builder $missing) => $missing->whereNull('website')->orWhere('website', '')))
            ->when($website === 'official_absent', fn (Builder $builder) => $builder->where('official_website_absent', true))
            ->when($source === 'with', fn (Builder $builder) => $builder->whereNotNull('contact_source')->where('contact_source', '!=', ''))
            ->when($source === 'without', fn (Builder $builder) => $builder->where(fn (Builder $missing) => $missing->whereNull('contact_source')->orWhere('contact_source', '')))
            ->when($content === 'draft', fn (Builder $builder) => $builder->whereNotNull('description_draft'))
            ->when($content === 'published', fn (Builder $builder) => $builder->whereNotNull('description_checked_at'))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $hasDraftsOnPage = $facilities->getCollection()
            ->contains(static fn (Facility $facility): bool => filled($facility->description_draft));
        $activeFilters = [];
        if ($selectedCity) {
            $activeFilters[] = 'Stadt: '.$selectedCity->name;
        }
        if ($status === 'unverified') {
            $activeFilters[] = 'Status: Offen';
        } elseif ($status === 'verified') {
            $activeFilters[] = 'Status: Geprüft';
        } elseif ($status === 'pending') {
            $activeFilters[] = 'Status: In Prüfung';
        } elseif ($status === 'not_found') {
            $activeFilters[] = 'Status: Nicht gefunden';
        }
        if ($phone === 'without') {
            $activeFilters[] = 'Fehlende Angabe: Telefon';
        }
        if ($email === 'without') {
            $activeFilters[] = 'Fehlende Angabe: E-Mail';
        } elseif ($email === 'official_absent') {
            $activeFilters[] = 'E-Mail: Offiziell nicht vorhanden';
        }
        if ($website === 'without') {
            $activeFilters[] = 'Fehlende Angabe: Website';
        } elseif ($website === 'official_absent') {
            $activeFilters[] = 'Website: Offiziell nicht gefunden';
        }

        return view('admin.facilities.index', compact(
            'facilities',
            'query',
            'status',
            'content',
            'phone',
            'email',
            'website',
            'source',
            'missing',
            'city',
            'selectedCity',
            'activeFilters',
            'hasDraftsOnPage',
        ));
    }

    public function edit(Request $request, Facility $facility): View
    {
        $facility->load('city');
        $suggestion = null;

        if ($request->filled('suggestion')) {
            $suggestion = $facility->contactSuggestions()
                ->whereKey($request->integer('suggestion'))
                ->first();
        }

        return view('admin.facilities.edit', compact('facility', 'suggestion'));
    }

    public function update(Request $request, Facility $facility): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:3000'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'official_email_absent' => ['sometimes', 'boolean'],
            'website' => ['nullable', new AbsoluteHttpUrl],
            'official_website_absent' => ['sometimes', 'boolean'],
            'contact_source' => ['nullable', new AbsoluteHttpUrl],
            'contact_status' => ['nullable', Rule::in(['verified', 'pending', 'not_found'])],
            'contact_locked' => ['required', 'boolean'],
            'suggestion_id' => ['nullable', 'integer'],
        ]);

        $suggestionId = $validated['suggestion_id'] ?? null;
        unset($validated['suggestion_id']);
        $validated['official_website_absent'] = (bool) ($validated['official_website_absent'] ?? false);
        $validated['official_email_absent'] = (bool) ($validated['official_email_absent'] ?? false);

        foreach (['description', 'address', 'postal_code', 'phone', 'email', 'website', 'contact_source'] as $field) {
            if (is_string($validated[$field] ?? null)) {
                $validated[$field] = trim($validated[$field]);
                if ($validated[$field] === '') {
                    $validated[$field] = null;
                }
            }
        }

        foreach (['website', 'contact_source'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = HttpUrl::normalize($validated[$field]);
            }
        }

        if (is_string($validated['email'] ?? null)) {
            $validated['email'] = Str::lower($validated['email']);
        }

        if (filled($validated['email'] ?? null)) {
            $validated['official_email_absent'] = false;
        } elseif ($validated['official_email_absent']) {
            $wasAlreadyConfirmed = (bool) $facility->official_email_absent;
            if (! $wasAlreadyConfirmed && ! filled($validated['contact_source'] ?? null)) {
                return back()
                    ->withErrors(['contact_source' => 'Für die Bestätigung einer fehlenden offiziellen E-Mail-Adresse ist die URL der geprüften Quelle erforderlich.'])
                    ->withInput();
            }
            $validated['email'] = null;
        }

        $addressChanged = isset($validated['address'])
            && $this->addressKey($validated['address']) !== $this->addressKey((string) $facility->address);
        $postalCodeChanged = isset($validated['postal_code'])
            && trim((string) $validated['postal_code']) !== trim((string) $facility->postal_code);
        if ($addressChanged || $postalCodeChanged) {
            if (! filled($validated['contact_source'] ?? null)) {
                return back()
                    ->withErrors(['contact_source' => 'Für eine offizielle Adressänderung ist die URL der offiziellen Quelle erforderlich.'])
                    ->withInput();
            }
        }

        if (isset($validated['address']) && ! $addressChanged) {
            $validated['address'] = $facility->address;
        }

        if (($validated['official_website_absent'] ?? false) && filled($validated['website'] ?? null)) {
            return back()
                ->withErrors(['website' => 'Website und „Kein offizieller Internetauftritt gefunden“ können nicht gleichzeitig gespeichert werden.'])
                ->withInput();
        }

        $hasContact = filled($validated['phone'] ?? null)
            || filled($validated['email'] ?? null)
            || filled($validated['website'] ?? null);

        if (($validated['contact_status'] ?? null) === 'verified' && ! $hasContact) {
            return back()
                ->withErrors(['contact_status' => 'Mindestens eine Kontaktmöglichkeit (Telefon, E-Mail oder Website) ist erforderlich, um den Status „Geprüft“ zu setzen.'])
                ->withInput();
        }

        if (($validated['contact_status'] ?? null) === 'verified' && ! $hasContact) {
            return back()
                ->withErrors(['contact_status' => 'Für den Status „Geprüft“ muss mindestens ein Kontakt eingetragen sein.'])
                ->withInput();
        }

        if (($validated['contact_status'] ?? null) === 'not_found' && $hasContact) {
            return back()
                ->withErrors(['contact_status' => '„Nicht gefunden“ kann nicht zusammen mit vorhandenen Kontaktdaten gespeichert werden.'])
                ->withInput();
        }

        $facility->update([
            ...$validated,
            'contact_checked_at' => now(),
        ]);

        $suggestion = $suggestionId
            ? ContactSuggestion::query()
                ->whereKey($suggestionId)
                ->where('facility_id', $facility->id)
                ->first()
            : null;

        if ($suggestion !== null) {
            return redirect()
                ->route('admin.suggestions.show', $suggestion)
                ->with('status', 'Kontaktdaten wurden gespeichert. Prüfen Sie jetzt die Entscheidung für das Parser-Ergebnis.');
        }

        return redirect()
            ->route('admin.facilities.edit', $facility)
            ->with('status', 'Einrichtungsdaten wurden gespeichert.');
    }

    private function addressKey(string $address): string
    {
        $value = Str::lower(trim($address));
        $value = preg_replace('/\bstr\.?\b/u', 'strasse', $value) ?? $value;
        $value = str_replace('straße', 'strasse', $value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
    }

    public function reviewDescriptionDraft(Request $request, Facility $facility): RedirectResponse
    {
        $action = (string) $request->input('action', 'save');

        if ($action === 'discard') {
            $facility->update([
                'description_draft' => null,
                'description_draft_sources' => null,
                'description_draft_checked_at' => null,
            ]);

            return redirect()
                ->route('admin.facilities.edit', $facility)
                ->with('status', 'KI-Entwurf wurde verworfen. Die öffentliche Beschreibung blieb unverändert.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['save', 'publish'])],
            'description_draft' => ['required', 'string', 'max:3000'],
            'description_draft_sources' => ['required', 'string', 'max:5000'],
            'description_draft_checked_at' => ['required', 'date_format:Y-m-d'],
        ]);
        $sources = array_values(array_unique(array_filter(array_map(
            static fn (string $source): string => trim($source),
            preg_split('/\R+/', $validated['description_draft_sources']) ?: [],
        ))));

        foreach ($sources as $source) {
            if (! HttpUrl::isValid($source)) {
                throw ValidationException::withMessages([
                    'description_draft_sources' => 'Jede Quelle muss eine vollständige Webadresse mit http:// oder https:// sein.',
                ]);
            }
        }

        $sources = array_map(static fn (string $source): string => HttpUrl::normalize($source), $sources);

        if ($sources === []) {
            throw ValidationException::withMessages([
                'description_draft_sources' => 'Mindestens eine Quelle ist erforderlich.',
            ]);
        }

        $draft = trim($validated['description_draft']);
        $checkedAt = $validated['description_draft_checked_at'];

        if ($action === 'publish') {
            $facility->update([
                'description' => $draft,
                'description_sources' => $sources,
                'description_checked_at' => $checkedAt,
                'description_ai_assisted' => true,
                'description_draft' => null,
                'description_draft_sources' => null,
                'description_draft_checked_at' => null,
            ]);

            return redirect()
                ->route('admin.facilities.edit', $facility)
                ->with('status', 'Beschreibung wurde geprüft und veröffentlicht.');
        }

        $facility->update([
            'description_draft' => $draft,
            'description_draft_sources' => $sources,
            'description_draft_checked_at' => $checkedAt,
        ]);

        return redirect()
            ->route('admin.facilities.edit', $facility)
            ->with('status', 'KI-Entwurf wurde gespeichert, aber noch nicht veröffentlicht.');
    }

    public function publishDescriptionDrafts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facility_ids' => ['required', 'array', 'min:1', 'max:30'],
            'facility_ids.*' => ['required', 'integer', 'distinct', 'exists:facilities,id'],
        ], [
            'facility_ids.required' => 'Wählen Sie mindestens einen Entwurf aus.',
            'facility_ids.max' => 'Es können höchstens 30 Entwürfe gleichzeitig veröffentlicht werden.',
        ]);

        $facilities = Facility::query()
            ->whereIn('id', $validated['facility_ids'])
            ->get();
        $published = 0;
        $skipped = 0;

        DB::transaction(function () use ($facilities, &$published, &$skipped): void {
            foreach ($facilities as $facility) {
                $sources = is_array($facility->description_draft_sources)
                    ? array_values(array_filter($facility->description_draft_sources))
                    : [];

                if (! filled($facility->description_draft)
                    || $sources === []
                    || collect($sources)->contains(fn (mixed $source): bool => ! HttpUrl::isValid($source))
                    || $facility->description_draft_checked_at === null) {
                    $skipped++;

                    continue;
                }

                $sources = array_map(static fn (string $source): string => HttpUrl::normalize($source), $sources);

                $facility->update([
                    'description' => trim((string) $facility->description_draft),
                    'description_sources' => $sources,
                    'description_checked_at' => $facility->description_draft_checked_at,
                    'description_ai_assisted' => true,
                    'description_draft' => null,
                    'description_draft_sources' => null,
                    'description_draft_checked_at' => null,
                ]);
                $published++;
            }
        });

        $message = $published.' Beschreibung'.($published === 1 ? '' : 'en').' wurde'.($published === 1 ? '' : 'n').' veröffentlicht.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' unvollständige Entwürfe wurden ausgelassen.';
        }

        return back()->with('status', $message);
    }
}
