<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The company's details and branding: printed on attestations, shown in the
 * sidebar and on the sign-in pages. A single row (current()). The logo is a
 * PNG/JPG on the private disk, served by /brand/logo and embedded in PDFs.
 *
 * @property string|null $name
 * @property string|null $city
 * @property string|null $brand_color
 * @property string|null $logo_path
 */
class CompanySetting extends Model
{
    public const DISK = 'local';

    /** The accent color when HR has not chosen one. */
    public const DEFAULT_COLOR = '#C71618';

    /** Editable on the "Entreprise" page. */
    public const FIELDS = [
        'name', 'legal_form', 'address', 'city', 'phone', 'email',
        'ice', 'rc', 'if_number', 'cnss_number', 'signatory_name', 'signatory_title', 'brand_color',
        'cndp_number', 'privacy_contact',
    ];

    /** Needed before any attestation can be issued. */
    public const REQUIRED = ['name' => 'raison sociale', 'address' => 'adresse', 'city' => 'ville', 'signatory_name' => 'nom du signataire'];

    /** Small words left out of the initials ("Société de Distribution" → SD). */
    private const NOT_INITIALS = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'l', 'd', 'sarl', 'sa', 'sas', 'sarlau'];

    protected $fillable = self::FIELDS;

    protected $hidden = ['logo_path'];

    public static function current(): self
    {
        return static::query()->first() ?? new static;
    }

    /** @return list<string> Labels of the required details still empty. */
    public function missingRequired(): array
    {
        return array_values(array_filter(self::REQUIRED, fn (string $label, string $field) => blank($this->{$field}), ARRAY_FILTER_USE_BOTH));
    }

    public function color(): string
    {
        return $this->brand_color ?: self::DEFAULT_COLOR;
    }

    /** Up to two letters for the badge shown when there is no logo. */
    public function initials(): string
    {
        $words = collect(preg_split('/[\s\'’\-]+/u', (string) $this->name, -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn (string $word) => in_array(Str::lower(Str::ascii($word)), self::NOT_INITIALS, true));

        return Str::upper($words->take(2)->map(fn (string $word) => Str::substr($word, 0, 1))->implode(''));
    }

    /**
     * What every page needs to show the company (shared with Inertia, guests
     * included). Without a company name the app keeps its own name.
     *
     * @return array{name: ?string, color: string, initials: string, logo: ?string}
     */
    public function brand(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color(),
            'initials' => $this->initials(),
            // The version makes browsers fetch a new logo after an upload.
            'logo' => $this->logo_path ? route('brand.logo', ['v' => $this->updated_at?->timestamp]) : null,
        ];
    }

    /** The logo as a data URI for the PDF, or null. */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk(self::DISK)->exists($this->logo_path)) {
            return null;
        }

        $mime = str_ends_with($this->logo_path, '.png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64,".base64_encode(Storage::disk(self::DISK)->get($this->logo_path));
    }
}
