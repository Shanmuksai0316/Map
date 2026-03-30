<x-filament-panels::page>
    <style>
        @keyframes pulse-red {
            0%, 100% { background-color: rgb(254 226 226); }
            50% { background-color: rgb(254 202 202); }
        }
        .dark .animate-pulse-red {
            animation: pulse-red-dark 1.5s ease-in-out infinite;
        }
        @keyframes pulse-red-dark {
            0%, 100% { background-color: rgb(127 29 29 / 0.2); }
            50% { background-color: rgb(185 28 28 / 0.3); }
        }
        .animate-pulse-red {
            animation: pulse-red 1.5s ease-in-out infinite;
        }
    </style>
    
    {{ $this->table }}
</x-filament-panels::page>

