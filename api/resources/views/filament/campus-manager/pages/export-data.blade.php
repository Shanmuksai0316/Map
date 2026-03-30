<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Export Form --}}
        <x-filament::section>
            <x-slot name="heading">
                Export Data
            </x-slot>

            <x-slot name="description">
                Request data exports for download. Exports are processed asynchronously and you'll receive a notification when ready.
            </x-slot>

            <form wire:submit="export">
                {{ $this->form }}

                <div class="mt-6 flex justify-end">
                    <x-filament::button type="submit">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-5 h-5 mr-2" />
                        Request Export
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Recent Exports --}}
        <x-filament::section>
            <x-slot name="heading">
                Recent Exports
            </x-slot>

            <x-slot name="description">
                Your recent export requests. Download links are valid for 15 minutes.
            </x-slot>

            <div class="space-y-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <x-filament::icon icon="heroicon-o-document-arrow-down" class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="font-medium text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">
                                        Students Export
                                    </p>
                                    <p class="text-xs text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                                        Requested 5 minutes ago • CSV
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="success">
                                Ready
                            </x-filament::badge>
                            <x-filament::button size="sm" outlined>
                                Download
                            </x-filament::button>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <x-filament::icon icon="heroicon-o-document-arrow-down" class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="font-medium text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">
                                        Out-Passes Export
                                    </p>
                                    <p class="text-xs text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                                        Requested 2 hours ago • Excel
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="warning">
                                Processing
                            </x-filament::badge>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 opacity-50">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <x-filament::icon icon="heroicon-o-document-arrow-down" class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="font-medium text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">
                                        Attendance Export
                                    </p>
                                    <p class="text-xs text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                                        Requested yesterday • CSV
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="gray">
                                Expired
                            </x-filament::badge>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <p class="text-xs text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                    Export files are automatically deleted after 7 days
                </p>
            </div>
        </x-filament::section>

        {{-- Export Guidelines --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                Export Guidelines
            </x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <ul class="space-y-2 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                    <li><strong>Data Retention:</strong> Export files are kept for 7 days before automatic deletion.</li>
                    <li><strong>Download Links:</strong> Download links are valid for 15 minutes after generation.</li>
                    <li><strong>Large Exports:</strong> Exports with more than 10,000 records may take several minutes to process.</li>
                    <li><strong>Filters:</strong> Use date ranges and filters to reduce export size and processing time.</li>
                    <li><strong>Format:</strong> CSV is recommended for large datasets; Excel (XLSX) is better for formatted reports.</li>
                    <li><strong>Privacy:</strong> All exports are encrypted and accessible only to authorized users.</li>
                    <li><strong>Audit Trail:</strong> All export requests are logged for security and compliance purposes.</li>
                </ul>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

