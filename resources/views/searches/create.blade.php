<x-layouts.app-shell title="Create Search - HolidaySage">
    <section class="mx-auto max-w-4xl">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tell us about your perfect holiday</h1>
        <p class="mt-3 text-slate-600">Define your preferences once, and we will continuously find and rank the best options from Jet2 and TUI.</p>

        <div class="mt-8 rounded-2xl border border-teal-200 bg-teal-50/70 p-5">
            <p class="text-sm font-semibold text-teal-900">Import from Jet2 or TUI</p>
            <p class="mt-1 text-sm text-teal-800">Paste a provider search URL to auto-fill your preferences.</p>
            <form id="import-form" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf
                <input id="import-url" type="url" name="url" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="https://www.jet2holidays.com/..." />
                <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Import</button>
            </form>
            <p id="import-message" class="mt-2 text-sm text-slate-700"></p>
        </div>

        <form method="POST" action="{{ route('searches.store') }}" class="mt-8 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Search name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" required />
                    @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Departure airport</label>
                    <input type="text" name="departure_airport_code" value="{{ old('departure_airport_code', 'MAN') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm uppercase shadow-sm focus:border-teal-500 focus:ring-teal-500" required />
                    @error('departure_airport_code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Total budget (£)</label>
                    <input type="number" step="0.01" min="0" name="budget_total" value="{{ old('budget_total') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('budget_total') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Earliest departure</label>
                    <input type="date" name="travel_start_date" value="{{ old('travel_start_date') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('travel_start_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Latest return</label>
                    <input type="date" name="travel_end_date" value="{{ old('travel_end_date') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('travel_end_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Min nights</label>
                    <input type="number" name="duration_min_nights" min="1" value="{{ old('duration_min_nights', 7) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('duration_min_nights') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Max nights</label>
                    <input type="number" name="duration_max_nights" min="1" value="{{ old('duration_max_nights', 10) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('duration_max_nights') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Adults</label>
                    <input type="number" name="adults" min="1" value="{{ old('adults', 2) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Children</label>
                    <input type="number" name="children" min="0" value="{{ old('children', 0) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Max flight (minutes)</label>
                    <input type="number" name="max_flight_minutes" min="30" value="{{ old('max_flight_minutes') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Max transfer (minutes)</label>
                    <input type="number" name="max_transfer_minutes" min="0" value="{{ old('max_transfer_minutes') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">What matters most?</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @php
                            $options = ['family_friendly' => 'Family friendly', 'near_beach' => 'Near beach', 'walkable' => 'Walkable area', 'swimming_pool' => 'Swimming pool', 'kids_club' => 'Kids club', 'adults_only' => 'Adults only', 'all_inclusive' => 'All inclusive', 'quiet_relaxing' => 'Quiet & relaxing', 'near_nightlife' => 'Near nightlife', 'spa_wellness' => 'Spa & wellness'];
                            $selected = old('feature_preferences', []);
                        @endphp
                        @foreach ($options as $value => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="feature_preferences[]" value="{{ $value }}" class="peer sr-only" {{ in_array($value, $selected, true) ? 'checked' : '' }} />
                                <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition peer-checked:border-teal-300 peer-checked:bg-teal-50 peer-checked:text-teal-800">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                <div class="grid grid-cols-3 gap-3 text-center text-xs text-slate-600">
                    <span class="rounded-lg bg-slate-50 px-3 py-2">2 providers tracked</span>
                    <span class="rounded-lg bg-slate-50 px-3 py-2">24/7 monitoring</span>
                    <span class="rounded-lg bg-slate-50 px-3 py-2">Smart recommendations</span>
                </div>
                <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
                    Find My Best Holiday Options
                </button>
            </div>
        </form>
    </section>

    <script>
        (() => {
            const form = document.getElementById('import-form');
            const message = document.getElementById('import-message');
            const importUrl = document.getElementById('import-url');
            if (!form || !message || !importUrl) return;
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                message.textContent = 'Importing criteria...';
                try {
                    const response = await fetch(@json(route('searches.import')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ url: importUrl.value }),
                    });
                    const payload = await response.json();
                    if (!response.ok) {
                        message.textContent = payload.message ?? 'Unable to import from this URL.';
                        return;
                    }
                    const criteria = payload.criteria ?? {};
                    Object.entries(criteria).forEach(([key, value]) => {
                        if (Array.isArray(value)) {
                            const values = value.map(String);
                            document.querySelectorAll(`input[name="${key}[]"]`).forEach((checkbox) => {
                                checkbox.checked = values.includes(checkbox.value);
                            });
                            return;
                        }
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = String(value);
                        }
                    });
                    message.textContent = payload.message ?? 'Import complete.';
                } catch (error) {
                    message.textContent = 'Unable to import from this URL.';
                }
            });
        })();
    </script>
</x-layouts.app-shell>
