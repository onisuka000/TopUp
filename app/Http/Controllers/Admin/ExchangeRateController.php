<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\TokovoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function __construct(
        protected TokovoucherService $tokovoucherService
    ) {}

    /**
     * Display a listing of currencies and exchange rates.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('viewonly::product')) {
            abort(403, 'Unauthorized: You do not have permission to view exchange rates.');
        }

        $currencies = Currency::orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get();

        $defaultCurrency = Currency::getDefault();

        return Inertia::render('Admin/ExchangeRates/Index', [
            'currencies'      => $currencies,
            'defaultCurrency' => $defaultCurrency,
        ]);
    }

    /**
     * Store a new currency and exchange rate.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('create::product')) {
            abort(403, 'Unauthorized: You do not have permission to create currencies.');
        }

        $validated = $request->validate([
            'currency'      => 'required|string|max:100',
            'currency_code' => 'required|string|max:10|unique:currencies,currency_code',
            'symbol'        => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'is_default'    => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['currency_code']));
        $isDefault = (bool) ($validated['is_default'] ?? false);

        if ($isDefault) {
            Currency::query()->update(['is_default' => false]);
        }

        $currency = Currency::create([
            'currency'      => $validated['currency' ],
            'currency_code' => $code,
            'symbol'        => trim($validated['symbol']),
            'exchange_rate' => (float) $validated['exchange_rate'],
            'is_default'    => $isDefault,
            'is_active'     => (bool) ($validated['is_active'] ?? true),
        ]);

        // If IDR added/updated, recalculate Tokovoucher costs
        if ($code === 'IDR') {
            $this->tokovoucherService->recalculateCosts((float) $validated['exchange_rate']);
        }

        return back()->with('success', "Currency {$code} ({$currency->currency}) added successfully.");
    }

    /**
     * Update an existing currency and exchange rate.
     */
    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('update::product')) {
            abort(403, 'Unauthorized: You do not have permission to update currencies.');
        }

        $validated = $request->validate([
            'currency'      => 'required|string|max:100',
            'currency_code' => 'required|string|max:10|unique:currencies,currency_code,' . $currency->id,
            'symbol'        => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'is_default'    => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['currency_code']));
        $isDefault = (bool) ($validated['is_default'] ?? false);

        if ($isDefault && ! $currency->is_default) {
            Currency::where('id', '!=', $currency->id)->update(['is_default' => false]);
        }

        $oldRate = (float) $currency->exchange_rate;
        $newRate = (float) $validated['exchange_rate'];

        $currency->update([
            'currency'      => $validated['currency'],
            'currency_code' => $code,
            'symbol'        => trim($validated['symbol']),
            'exchange_rate' => $newRate,
            'is_default'    => $isDefault,
            'is_active'     => (bool) ($validated['is_active'] ?? true),
        ]);

        // If IDR updated, recalculate Tokovoucher product USD costs
        if ($code === 'IDR' && $oldRate != $newRate) {
            $count = $this->tokovoucherService->recalculateCosts($newRate);
            return back()->with('success', "Updated IDR rate to " . number_format($newRate) . ". Recalculated cost prices for {$count} products.");
        }

        return back()->with('success', "Currency {$code} updated successfully.");
    }

    /**
     * Delete a currency.
     */
    public function destroy(Request $request, Currency $currency): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('delete::product')) {
            abort(403, 'Unauthorized: You do not have permission to delete currencies.');
        }

        if ($currency->is_default) {
            return back()->with('error', 'Cannot delete default base currency (USD).');
        }

        $code = $currency->currency_code;
        $currency->delete();

        return back()->with('success', "Currency {$code} deleted successfully.");
    }
}
