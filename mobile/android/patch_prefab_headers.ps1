# PowerShell script to patch C++20 concepts in React Native prefab headers
# This must run after Gradle extracts prefab modules but before compilation

$ErrorActionPreference = "Stop"

Write-Host "Patching React Native prefab headers for C++20 compatibility..." -ForegroundColor Cyan

$gradleCache = "$env:USERPROFILE\.gradle\caches"
$reactNativeHeaders = Get-ChildItem -Path $gradleCache -Recurse -Filter "hash_combine.h" -ErrorAction SilentlyContinue | 
    Where-Object { $_.FullName -like "*react-android*" -and $_.FullName -like "*prefab*" }

$patchedCount = 0

foreach ($header in $reactNativeHeaders) {
    try {
        $content = Get-Content $header.FullName -Raw -ErrorAction Stop
        
        # Replace C++20 concept syntax with C++17 compatible code
        $newContent = $content
        
        # Replace: concept Hashable = ...; with template struct
        if ($newContent -match 'template\s+<typename\s+T>\s+concept\s+Hashable') {
            $newContent = $newContent -replace 'template\s+<typename\s+T>\s+concept\s+Hashable\s*=\s*([^;]+);', 
                'template <typename T> struct Hashable { static constexpr bool value = $1; };'
        }
        
        # Replace: template <Hashable T, Hashable... Rest> with SFINAE
        if ($newContent -match 'template\s+<Hashable') {
            $newContent = $newContent -replace 'template\s+<Hashable\s+(\w+),\s+Hashable\.\.\.\s+(\w+)>', 
                'template <typename $1, typename... $2, std::enable_if_t<Hashable<$1>::value && (Hashable<$2>::value && ...), int> = 0>'
        }
        
        if ($newContent -ne $content) {
            Set-Content -Path $header.FullName -Value $newContent -NoNewline -ErrorAction Stop
            $patchedCount++
            Write-Host "  Patched: $($header.Name)" -ForegroundColor Green
        }
    } catch {
        Write-Host "  Failed to patch: $($header.FullName) - $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "Patched $patchedCount header files" -ForegroundColor Cyan
