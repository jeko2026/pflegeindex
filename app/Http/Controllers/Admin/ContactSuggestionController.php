<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSuggestion;
use App\Services\ContactSuggestionImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContactSuggestionController extends Controller
{
    public function upload(Request $request, ContactSuggestionImporter $importer): RedirectResponse
    {
        $request->validate([
            'results_file' => ['required', 'file', 'max:10240'],
        ]);
        $path = $request->file('results_file')?->getRealPath();

        if (! is_string($path)) {
            return back()->withErrors(['results_file' => 'Die hochgeladene Datei konnte nicht gelesen werden.']);
        }

        try {
            $summary = $importer->import($path);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['results_file' => 'Die Datei enthält keine gültigen Parser-Ergebnisse.']);
        }

        return back()->with('import_summary', $summary);
    }

    public function index(Request $request): View
    {
        $decision = trim((string) $request->query('decision', 'pending'));
        $parserStatus = trim((string) $request->query('parser_status', ''));

        $suggestions = ContactSuggestion::query()
            ->with(['facility.city', 'reviewer'])
            ->when(in_array($decision, ['pending', 'accepted', 'rejected'], true), fn (Builder $query) => $query->where('decision', $decision))
            ->when(in_array($parserStatus, ['verified', 'not_found'], true), fn (Builder $query) => $query->where('parser_status', $parserStatus))
            ->orderByDesc('checked_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.suggestions.index', compact('suggestions', 'decision', 'parserStatus'));
    }

    public function show(ContactSuggestion $suggestion): View
    {
        $suggestion->load(['facility.city', 'reviewer']);

        return view('admin.suggestions.show', compact('suggestion'));
    }

    public function accept(Request $request, ContactSuggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->decision === 'pending', 422);

        DB::transaction(function () use ($request, $suggestion): void {
            $facility = $suggestion->facility()->lockForUpdate()->firstOrFail();

            if ($suggestion->parser_status === 'verified') {
                $facility->update([
                    'phone' => $suggestion->phone ?? $facility->phone,
                    'email' => $suggestion->email ?? $facility->email,
                    'website' => $suggestion->website ?? $facility->website,
                    'contact_source' => $suggestion->phone_source ?? $suggestion->email_source ?? $facility->contact_source,
                    'contact_status' => 'verified',
                    'contact_checked_at' => $suggestion->checked_at ?? now(),
                    'contact_locked' => true,
                ]);
            } elseif ($facility->phone === null && $facility->email === null && $facility->website === null) {
                $facility->update([
                    'contact_status' => 'not_found',
                    'contact_checked_at' => $suggestion->checked_at ?? now(),
                ]);
            }

            $suggestion->update([
                'decision' => 'accepted',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('admin.suggestions.index')->with('status', 'Vorschlag wurde angenommen.');
    }

    public function reject(Request $request, ContactSuggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->decision === 'pending', 422);

        $suggestion->update([
            'decision' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.suggestions.index')->with('status', 'Vorschlag wurde abgelehnt.');
    }
}
