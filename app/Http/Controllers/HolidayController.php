<?php

namespace App\Http\Controllers;

use App\Services\UnifiedPropertyQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public unified-property detail page at /holidays/{slug}.
 *
 * The {slug} is a canonical property slug shared by all Hotel rows that represent
 * the same real-world property (across providers). The page is property-first:
 * one hero, then packages grouped by provider.
 */
class HolidayController extends Controller
{
    public function show(Request $request, string $slug, UnifiedPropertyQuery $unifiedProperty): View
    {
        $property = $unifiedProperty->forSlug($slug);
        abort_if($property === null, 404);

        $highlightPackageId = $this->highlightPackageIdFromRequest($request);

        return view('holidays.show', [
            'property' => $property,
            'highlightPackageId' => $highlightPackageId,
        ]);
    }

    private function highlightPackageIdFromRequest(Request $request): ?int
    {
        $value = $request->query('p');
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
