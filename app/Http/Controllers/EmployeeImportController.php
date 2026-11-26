<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\EmployeeImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Importing employees from Excel (role:admin,rh): download the template,
 * upload a file and see the check, then import it (all or nothing), then
 * invite the new employees to choose their password.
 *
 * The uploaded file waits on the private disk under a random name (the
 * "token") between the check and the import, and is deleted after.
 */
class EmployeeImportController extends Controller
{
    private const DIRECTORY = 'imports';

    public function __construct(private readonly EmployeeImport $import) {}

    public function index(Request $request): Response
    {
        $token = $this->token($request->query('fichier'));

        return Inertia::render('Admin/Import', [
            'token' => $token,
            'check' => $token ? $this->import->check($this->path($token), $request->user()) : null,
            'columns' => collect(EmployeeImport::COLUMNS)->map(fn (array $c) => ['heading' => $c[0], 'required' => $c[1], 'hint' => $c[2]])->values(),
            // Set after an import: the new accounts to invite.
            'imported' => session('imported'),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return response()->download($this->import->template(), 'modele-import-employes.xlsx')->deleteFileAfterSend();
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']],
            ['file.required' => 'Choisissez un fichier.', 'file.mimes' => 'Le fichier doit être un classeur Excel (.xlsx) ou un CSV.'],
        );

        $this->deleteOldFiles();
        $file = $request->file('file');
        $token = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $file->storeAs(self::DIRECTORY, $token, 'local');

        return to_route('admin.import.index', ['fichier' => $token]);
    }

    public function report(Request $request, string $token): BinaryFileResponse
    {
        $token = $this->token($token) ?? abort(404);

        return response()
            ->download($this->import->report($this->import->check($this->path($token), $request->user())), 'erreurs-import.xlsx')
            ->deleteFileAfterSend();
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $token = $this->token($token) ?? abort(404);
        $result = $this->import->import($this->path($token), $request->user());
        Storage::disk('local')->delete(self::DIRECTORY.'/'.$token);

        return to_route('admin.import.index')
            ->with('imported', [
                'created' => User::whereKey($result['created'])->orderBy('name')->get(['id', 'name', 'email']),
                'updated' => $result['updated'],
                'departments' => $result['departments'],
            ])
            ->with('success', count($result['created']).' employé(s) créé(s), '.$result['updated'].' mis à jour.');
    }

    /** Sends the welcome e-mail (link to choose a password, valid 7 days) to the given employees. */
    public function invite(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];
        $sent = 0;
        $failed = 0;

        foreach (User::whereKey($ids)->whereNull('deactivated_at')->get() as $user) {
            if (! $request->user()->canManageAccount($user) || $user->is($request->user())) {
                continue;
            }

            try {
                $user->notify(new EmployeeInvitation(Password::broker('invitations')->createToken($user)));
                $user->forceFill(['invited_at' => now()])->save();
                $sent++;
            } catch (TransportExceptionInterface $e) {
                report($e);
                $failed++;
            }
        }

        if ($failed) {
            return back()->withErrors(['invite' => "{$sent} invitation(s) envoyée(s), {$failed} en échec : vérifiez la configuration de l'envoi d'e-mails."]);
        }

        return back()->with('success', "{$sent} invitation(s) envoyée(s).");
    }

    /** A token we created (uuid + extension) whose file still exists, or null. */
    private function token(mixed $token): ?string
    {
        return is_string($token)
            && preg_match('/^[0-9a-f-]{36}\.(xlsx|xls|csv)$/', $token)
            && Storage::disk('local')->exists(self::DIRECTORY.'/'.$token) ? $token : null;
    }

    private function path(string $token): string
    {
        return Storage::disk('local')->path(self::DIRECTORY.'/'.$token);
    }

    /** Files left from imports never confirmed. */
    private function deleteOldFiles(): void
    {
        foreach (Storage::disk('local')->files(self::DIRECTORY) as $file) {
            if (Storage::disk('local')->lastModified($file) < now()->subDay()->timestamp) {
                Storage::disk('local')->delete($file);
            }
        }
    }
}
