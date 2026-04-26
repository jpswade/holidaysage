<x-layouts.app-shell :title="'Refine search: ' . $search->name . ' - HolidaySage'">
    <section class="mx-auto max-w-4xl">
        <a href="{{ route('searches.show', $search) }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-lucide-arrow-left class="h-4 w-4" />
            Back to results
        </a>

        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900">Refine your search</h1>
        <p class="mt-3 text-slate-600">Update dates, party, budget, and preferences. Refresh the search when you want new live results from providers.</p>

        <div class="mt-8 rounded-2xl border border-teal-200 bg-teal-50/70 p-5">
            <p class="text-sm font-semibold text-teal-900">Import from Jet2 or TUI</p>
            <p class="mt-1 text-sm text-teal-800">Paste a provider URL to overwrite matching fields on this form.</p>
            <form id="import-form" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf
                <input id="import-url" type="url" name="url" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="https://www.jet2holidays.com/..." />
                <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Import</button>
            </form>
            <p id="import-message" class="mt-2 text-sm text-slate-700"></p>
        </div>

        <form method="POST" action="{{ route('searches.update', $search) }}" class="mt-8 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PATCH')
            @include('searches.partials.search-form-fields', [
                'search' => $search,
                'submitLabel' => 'Save changes',
                'showFooterBadges' => false,
            ])
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
                    const nameInput = document.querySelector('[name="name"]');
                    const suggested = payload.suggested_name ? String(payload.suggested_name).trim() : '';
                    if (nameInput && suggested) {
                        const current = String(nameInput.value || '').trim();
                        if (
                            current === '' ||
                            /^import\b/i.test(current) ||
                            current.includes('(www.')
                        ) {
                            nameInput.value = suggested;
                        }
                    }
                    message.textContent = payload.message ?? 'Import complete.';
                } catch (error) {
                    message.textContent = 'Unable to import from this URL.';
                }
            });
        })();
    </script>
</x-layouts.app-shell>
