<form method="GET" class="flex flex-wrap items-center gap-2">
    <select
        name="role"
        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 sm:text-sm"
        onchange="this.form.submit()"
    >
        @foreach($options ?? [] as $value => $label)
            <option value="{{ $value }}" {{ ($currentRole ?? '') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <noscript>
        <button type="submit" class="fi-btn fi-btn-size-sm fi-btn-color-primary">Load</button>
    </noscript>
</form>
