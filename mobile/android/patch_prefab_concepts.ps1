# PowerShell script to patch C++20 concepts in React Native prefab headers
# Run this AFTER Gradle extracts prefab modules but BEFORE compilation
# Usage: Run this script, then build

$ErrorActionPreference = "Stop"

Write-Host "Patching C++20 concepts in React Native prefab headers for C++17..." -ForegroundColor Cyan

$gradleCache = "$env:USERPROFILE\.gradle\caches"
$reactNativeHeaders = Get-ChildItem -Path $gradleCache -Recurse -Filter "*.h" -ErrorAction SilentlyContinue | 
    Where-Object { $_.FullName -like "*react-android*" -and $_.FullName -like "*prefab*" }

$patchedCount = 0

foreach ($header in $reactNativeHeaders) {
    try {
        $content = Get-Content $header.FullName -Raw -ErrorAction Stop
        if ($null -eq $content) { continue }
        
        $originalContent = $content
        $modified = $false
        
        # Pattern 1: template<typename T> concept Name = condition;
        # Replace with: template<typename T> struct Name { static constexpr bool value = condition; };
        if ($content -match 'template\s*<[^>]+>\s*concept\s+\w+\s*=') {
            $content = $content -creplace 'template\s*<([^>]+)>\s*concept\s+(\w+)\s*=\s*([^;]+);', 
                'template<$1> struct $2 { static constexpr bool value = $3; };'
            $modified = $true
        }
        
        # Pattern 2: concept Name = condition; (no template)
        # Replace with: template<typename> struct Name { static constexpr bool value = condition; };
        if ($content -match '(?<!template\s*<[^>]*>)\s*concept\s+\w+\s*=') {
            $content = $content -creplace '(?<!template\s*<[^>]*>)\s*concept\s+(\w+)\s*=\s*([^;]+);', 
                'template<typename> struct $1 { static constexpr bool value = $2; };'
            $modified = $true
        }
        
        # Pattern 3: template <ConceptName T, ConceptName... Rest>
        # Replace with: template <typename T, typename... Rest, std::enable_if_t<ConceptName<T>::value && (ConceptName<Rest>::value && ...), int> = 0>
        # This is a simplified version - may need refinement
        if ($content -match 'template\s*<\s*(\w+)\s+(\w+)') {
            # Check for common concept names used in React Native
            $conceptNames = @('Hashable', 'RawPropsFilterable', 'instantiated_from', 'uncvref_instantiated_from', 'uncvref_same_as')
            foreach ($conceptName in $conceptNames) {
                # Replace: template <ConceptName T>
                $pattern = "template\s*<\s*$conceptName\s+(\w+)"
                if ($content -match $pattern) {
                    $content = $content -creplace "template\s*<\s*$conceptName\s+(\w+)", 
                        "template <typename `$1, std::enable_if_t<$conceptName<`$1>::value, int> = 0"
                    $modified = $true
                }
                # Replace: template <ConceptName T, ConceptName... Rest>
                $pattern2 = "template\s*<\s*$conceptName\s+(\w+),\s*$conceptName\.\.\.\s+(\w+)"
                if ($content -match $pattern2) {
                    $content = $content -creplace "template\s*<\s*$conceptName\s+(\w+),\s*$conceptName\.\.\.\s+(\w+)", 
                        "template <typename `$1, typename... `$2, std::enable_if_t<$conceptName<`$1>::value && ($conceptName<`$2>::value && ...), int> = 0"
                    $modified = $true
                }
            }
        }
        
        # Pattern 4: requires clause in concept definition
        # concept Name = requires(T a) { ... };
        # This is complex - for now, we'll replace requires with a simpler form
        if ($content -match 'requires\s*\([^)]+\)\s*\{') {
            # Replace requires(...) { ... } with true (simplified)
            $content = $content -creplace 'requires\s*\([^)]+\)\s*\{[^}]+\}', 'true'
            $modified = $true
        }
        
        if ($modified) {
            Set-Content -Path $header.FullName -Value $content -NoNewline -ErrorAction Stop
            $patchedCount++
            Write-Host "  Patched: $($header.Name)" -ForegroundColor Green
        }
    } catch {
        Write-Host "  Failed to patch: $($header.FullName) - $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "Patched $patchedCount header files" -ForegroundColor Cyan
Write-Host "`nNote: You may need to run this script after each Gradle clean" -ForegroundColor Yellow
