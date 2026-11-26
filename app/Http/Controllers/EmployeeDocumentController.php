<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Models\EmployeeDocument;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The employee document folder. Admin and RH add and remove documents (not in
 * their own folder: another HR person does), Paie reads every folder, and the
 * employee reads their own (Paramètres → Mes documents).
 */
class EmployeeDocumentController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);

        $validated = $request->validate([
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'title' => ['nullable', 'string', 'max:150'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', config('conge.document_mimes')),
                'max:'.config('conge.employee_document_max_kb'),
            ],
        ], [
            'file.required' => 'Choisissez un fichier.',
            'file.mimes' => 'Le document doit être un PDF ou une image (JPG, PNG).',
            'file.max' => 'Le document ne doit pas dépasser '.(config('conge.employee_document_max_kb') / 1024).' Mo.',
        ], [
            'category' => 'type de document',
            'title' => 'intitulé',
            'expires_on' => "date d'expiration",
            'file' => 'fichier',
        ]);

        $file = $request->file('file');

        $document = EmployeeDocument::create([
            'user_id' => $user->id,
            'uploaded_by' => $request->user()->id,
            'category' => $validated['category'],
            'title' => $validated['title'] ?? null,
            'expires_on' => $validated['expires_on'] ?? null,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            // Random file name: the original one never reaches the disk.
            'path' => $file->storeAs(
                "employee-documents/{$user->id}",
                Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
                EmployeeDocument::DISK,
            ),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        PersonalDataAccess::record($request->user(), $user, 'add_document', $document->title ?: $document->category->label());

        return back()->with('success', 'Document ajouté au dossier.');
    }

    /** Opens the file in the browser (PDF, image) under its original name. */
    public function show(Request $request, EmployeeDocument $document): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->canViewHr() || $document->user_id === $viewer->id, 403);
        PersonalDataAccess::record($viewer, $document->user, 'open_document', $document->title ?: $document->category->label());

        return Storage::disk(EmployeeDocument::DISK)->response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function destroy(Request $request, EmployeeDocument $document): RedirectResponse
    {
        abort_if($document->user_id === $request->user()->id, 403);

        $document->delete();
        PersonalDataAccess::record($request->user(), $document->user, 'delete_document', $document->title ?: $document->category->label());

        return back()->with('success', 'Document supprimé du dossier.');
    }

    /** The signed-in employee's own folder, read-only. */
    public function own(Request $request): Response
    {
        return Inertia::render('settings/documents', [
            'documents' => self::folder($request->user()),
        ]);
    }

    /**
     * An employee's documents, newest first, as the pages show them.
     *
     * @return list<array<string, mixed>>
     */
    public static function folder(User $user): array
    {
        return $user->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(fn (EmployeeDocument $document) => [
                'id' => $document->id,
                'category' => $document->category->value,
                'category_label' => $document->category->label(),
                'title' => $document->title,
                'expires_on' => $document->expires_on?->toDateString(),
                'expiry' => self::expiry($document),
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at->toDateString(),
            ])
            ->all();
    }

    /** 'expired', 'soon' (within the alert window) or null. */
    public static function expiry(EmployeeDocument $document): ?string
    {
        return match (true) {
            $document->expires_on === null => null,
            $document->expires_on->lt(today()) => 'expired',
            $document->expires_on->lte(today()->addDays(config('conge.document_expiry_alert_days'))) => 'soon',
            default => null,
        };
    }
}
