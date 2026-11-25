<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The company's details printed on attestations, and its branding (name, logo,
 * accent color) shown in the sidebar and on the sign-in pages. Edited by Admin
 * and RH; the logo itself is public (/brand/logo).
 */
class CompanyController extends Controller
{
    public function edit(): Response
    {
        $company = CompanySetting::current();

        return Inertia::render('Admin/Company', [
            'company' => $company->only(CompanySetting::FIELDS),
            'logo' => $company->brand()['logo'],
            'defaultColor' => CompanySetting::DEFAULT_COLOR,
            'missing' => $company->missingRequired(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_form' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            // ICE: 15 digits.
            'ice' => ['nullable', 'regex:/^\d{15}$/'],
            'rc' => ['nullable', 'string', 'max:50'],
            'if_number' => ['nullable', 'string', 'max:30'],
            'cnss_number' => ['nullable', 'string', 'max:30'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_title' => ['nullable', 'string', 'max:100'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'ice.regex' => "L'ICE doit contenir 15 chiffres.",
            'brand_color.regex' => 'Choisissez une couleur au format #RRGGBB.',
        ], [
            'name' => 'raison sociale',
            'legal_form' => 'forme juridique',
            'address' => 'adresse',
            'city' => 'ville',
            'phone' => 'téléphone',
            'ice' => 'ICE',
            'rc' => 'RC',
            'if_number' => 'identifiant fiscal',
            'cnss_number' => 'N° CNSS',
            'signatory_name' => 'nom du signataire',
            'signatory_title' => 'fonction du signataire',
            'brand_color' => 'couleur',
        ]);

        $company = CompanySetting::current();
        $company->fill($validated)->save();

        return back()->with('success', 'Informations de l\'entreprise enregistrées.');
    }

    /** PNG or JPG only: the PDF engine does not draw SVG reliably. */
    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(
            ['logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024']],
            ['logo.mimes' => 'Le logo doit être une image PNG ou JPG.', 'logo.max' => 'Le logo ne doit pas dépasser 1 Mo.'],
        );

        $company = CompanySetting::current();
        $old = $company->logo_path;
        $file = $request->file('logo');
        $company->logo_path = $file->storeAs('company', 'logo-'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension()), CompanySetting::DISK);
        $company->save();

        if ($old) {
            Storage::disk(CompanySetting::DISK)->delete($old);
        }

        return back()->with('success', 'Logo enregistré.');
    }

    public function deleteLogo(): RedirectResponse
    {
        $company = CompanySetting::current();

        if ($company->logo_path) {
            Storage::disk(CompanySetting::DISK)->delete($company->logo_path);
            $company->logo_path = null;
            $company->save();
        }

        return back()->with('success', 'Logo supprimé.');
    }

    public function logo(): StreamedResponse
    {
        $company = CompanySetting::current();
        abort_unless($company->logo_path && Storage::disk(CompanySetting::DISK)->exists($company->logo_path), 404);

        return Storage::disk(CompanySetting::DISK)->response($company->logo_path);
    }
}
