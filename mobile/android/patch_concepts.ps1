# PowerShell script to patch C++20 concepts in React Native headers
# This runs after Gradle extracts prefab modules but before compilation

$ErrorActionPreference = "Stop"

Write-Host "Patching C++20 concepts in React Native headers..." -ForegroundColor Cyan

$gradleCache = "$env:USERPROFILE\.gradle\caches"
$reactNativeHeaders = Get-ChildItem -Path $gradleCache -Recurse -Filter "*.h" -ErrorAction SilentlyContinue | 
    Where-Object { $_.FullName -like "*react-android*" -and $_.FullName -like "*prefab*" }

$patchedCount = 0

foreach ($header in $reactNativeHeaders) {
    $content = Get-Content $header.FullName -Raw -ErrorAction SilentlyContinue
    if ($null -eq $content) { continue }
    
    $originalContent = $content
    $modified = $false
    
    # Replace concept declarations with template structs
    # concept Name = condition; -> template<bool = true> struct Name { static constexpr bool value = condition; };
    if ($content -match 'concept\s+(\w+)\s*=\s*([^;]+);') {
        $content = $content -replace 'concept\s+(\w+)\s*=\s*([^;]+);', 'template<bool = true> struct $1 { static constexpr bool value = $2; };'
        $modified = $true
    }
    
    # Replace concept in template parameters
    # template<concept T> -> template<typename T, std::enable_if_t<...>>
    # This is more complex and may need manual fixes
    
    if ($modified) {
        try {
            Set-Content -Path $header.FullName -Value $content -NoNewline -ErrorAction Stop
            $patchedCount++
            Write-Host "  Patched: $($header.Name)" -ForegroundColor Green
        } catch {
            Write-Host "  Failed to patch: $($header.FullName)" -ForegroundColor Red
        }
    }
}

Write-Host "Patched $patchedCount header files" -ForegroundColor Cyan
