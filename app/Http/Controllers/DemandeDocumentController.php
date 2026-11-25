<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\DemandeEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Supporting documents of a leave request: upload, open, remove. Who may do what: DemandePolicy. */
class DemandeDocumentController extends Controller
{
    public function store(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('manageDocuments', $demande);

        $request->validate(self::rules(), self::messages());

        if ($demande->documents()->count() >= config('conge.documents_per_request')) {
            return back()->withErrors(['document' => 'Nombre maximal de justificatifs atteint pour cette demande.']);
        }

        self::attach($demande, $request->file('document'), $request->user()->id);

        return back()->with('success', 'Justificatif ajouté.');
    }

    /** Opens the file in the browser (PDF, image) under its original name. */
    public function show(DemandeDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocuments', $document->demande);

        return Storage::disk(DemandeDocument::DISK)->response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function destroy(DemandeDocument $document): RedirectResponse
    {
        Gate::authorize('manageDocuments', $document->demande);

        $document->delete();
        DemandeEvent::recordDocument($document->demande, 'document_removed', $document->original_name);

        return back()->with('success', 'Justificatif supprimé.');
    }

    /** @return array<string, mixed> */
    public static function rules(bool $required = true): array
    {
        return [
            'document' => [
                $required ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', config('conge.document_mimes')),
                'max:'.config('conge.document_max_kb'),
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'document.mimes' => 'Le justificatif doit être un PDF ou une image (JPG, PNG).',
            'document.max' => 'Le justificatif ne doit pas dépasser '.(config('conge.document_max_kb') / 1024).' Mo.',
        ];
    }

    /** Stores an uploaded file privately under a random name and records it. */
    public static function attach(Demande $demande, UploadedFile $file, ?int $uploadedBy): DemandeDocument
    {
        $path = $file->storeAs(
            "documents/{$demande->id}",
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            DemandeDocument::DISK,
        );

        $document = $demande->documents()->create([
            'uploaded_by' => $uploadedBy,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        DemandeEvent::recordDocument($demande, 'document_added', $document->original_name);

        return $document;
    }
}
