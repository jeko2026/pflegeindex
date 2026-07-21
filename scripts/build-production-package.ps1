[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [string] $ReleaseRef = 'HEAD',

    [switch] $SelfCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$buildBase = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'build\production'))
$script:temporaryRoot = $null

function Write-Utf8NoBom {
    param([string] $Path, [string] $Content)

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Get-RelativePackagePath {
    param([string] $Root, [string] $Path)

    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd('\', '/')
    $pathFull = [System.IO.Path]::GetFullPath($Path)

    if (-not $pathFull.StartsWith($rootFull + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Path is outside the expected root."
    }

    return $pathFull.Substring($rootFull.Length + 1).Replace('\', '/')
}

function Assert-SafeChildPath {
    param([string] $Path, [string] $AllowedRoot)

    $rootFull = [System.IO.Path]::GetFullPath($AllowedRoot).TrimEnd('\', '/')
    $pathFull = [System.IO.Path]::GetFullPath($Path).TrimEnd('\', '/')

    if ($pathFull -eq $rootFull -or
        -not $pathFull.StartsWith($rootFull + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing unsafe filesystem operation outside the production build directory."
    }
}

function Remove-SafeDirectory {
    param([string] $Path, [string] $AllowedRoot)

    Assert-SafeChildPath -Path $Path -AllowedRoot $AllowedRoot

    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force
    }
}

function Invoke-LoggedNativeCommand {
    param(
        [string] $Command,
        [string[]] $Arguments,
        [string] $WorkingDirectory
    )

    Push-Location $WorkingDirectory
    try {
        $previousErrorActionPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try {
            $output = @(& $Command @Arguments 2>&1)
            $exitCode = $LASTEXITCODE
        } finally {
            $ErrorActionPreference = $previousErrorActionPreference
        }

        foreach ($line in $output) {
            Write-Host ($line.ToString())
        }

        return [pscustomobject]@{
            ExitCode = $exitCode
        }
    } finally {
        Pop-Location
    }
}

function Invoke-NativeCommand {
    param(
        [string] $Command,
        [string[]] $Arguments,
        [string] $WorkingDirectory
    )

    $result = Invoke-LoggedNativeCommand -Command $Command -Arguments $Arguments -WorkingDirectory $WorkingDirectory
    if ($result.ExitCode -ne 0) {
        throw "External command failed with exit code $($result.ExitCode): $Command"
    }
}

function Resolve-ReleaseCommit {
    param([string] $Ref)

    $resolved = & git -C $repoRoot rev-parse --verify "$Ref`^{commit}" 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace(($resolved -join ''))) {
        throw "Git release ref does not resolve to a commit: $Ref"
    }

    return ($resolved | Select-Object -First 1).Trim()
}

function Export-ReleaseCommit {
    param([string] $Commit, [string] $Destination)

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    $archivePath = Join-Path (Split-Path $Destination -Parent) 'source-export.zip'

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }

    Invoke-NativeCommand -Command 'git' -Arguments @(
        '-C', $repoRoot, 'archive', '--format=zip', "--output=$archivePath", $Commit
    ) -WorkingDirectory $repoRoot

    Expand-Archive -LiteralPath $archivePath -DestinationPath $Destination -Force
    Remove-Item -LiteralPath $archivePath -Force

    if (Test-Path -LiteralPath (Join-Path $Destination '.git')) {
        throw 'Clean export unexpectedly contains .git metadata.'
    }
}

function Test-SourceRequirements {
    param([string] $SourceRoot)

    foreach ($required in @('artisan', 'composer.json', 'composer.lock', 'app', 'bootstrap', 'config', 'database\migrations', 'resources\views', 'routes', 'public')) {
        if (-not (Test-Path -LiteralPath (Join-Path $SourceRoot $required))) {
            throw "Required release source is missing: $required"
        }
    }

    $composer = Get-Content -Raw -Encoding UTF8 (Join-Path $SourceRoot 'composer.json') | ConvertFrom-Json
    if ($composer.config.platform.php -ne '8.2.27') {
        throw 'composer.json must target PHP platform 8.2.27.'
    }
}

function Copy-DirectoryContents {
    param([string] $Source, [string] $Destination)

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    Get-ChildItem -LiteralPath $Source -Force | Copy-Item -Destination $Destination -Recurse -Force
}

function New-StorageSkeleton {
    param([string] $PrivateCore)

    $directories = @(
        'storage\app\private',
        'storage\app\public',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\logs'
    )

    foreach ($relative in $directories) {
        $directory = Join-Path $PrivateCore $relative
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
        Write-Utf8NoBom -Path (Join-Path $directory '.gitignore') -Content "*`n!.gitignore`n"
    }
}

function Get-BladeAssetReferences {
    param([string] $ViewsRoot)

    $references = New-Object System.Collections.Generic.HashSet[string] ([System.StringComparer]::OrdinalIgnoreCase)
    $pattern = 'asset\(\s*[''"]([^''"]+)[''"]\s*\)'

    Get-ChildItem -LiteralPath $ViewsRoot -Recurse -File -Filter '*.blade.php' | ForEach-Object {
        $content = Get-Content -Raw -Encoding UTF8 $_.FullName
        foreach ($match in [regex]::Matches($content, $pattern)) {
            [void] $references.Add($match.Groups[1].Value)
        }
    }

    return @($references | Sort-Object)
}

function Test-PublicAssets {
    param([string] $SourceRoot, [string] $PublicRoot)

    $required = @(
        '.htaccess',
        'index.php',
        'assets\styles.css',
        'assets\admin.css',
        'assets\app.js',
        'assets\og-image.png',
        'favicon.svg',
        'logo.svg',
        'logo-light.svg'
    )

    foreach ($relative in $required) {
        if (-not (Test-Path -LiteralPath (Join-Path $PublicRoot $relative) -PathType Leaf)) {
            throw "Required public asset is missing: $relative"
        }
    }

    foreach ($reference in Get-BladeAssetReferences -ViewsRoot (Join-Path $SourceRoot 'resources\views')) {
        if (-not (Test-Path -LiteralPath (Join-Path $PublicRoot $reference) -PathType Leaf)) {
            throw "Blade asset() reference is missing from public-webroot: $reference"
        }
    }
}

function Test-ProductionIndex {
    param([string] $Path)

    $content = Get-Content -Raw -Encoding UTF8 $Path
    $placeholderCount = ([regex]::Matches($content, '__PRIVATE_CORE_PATH__')).Count

    if ($placeholderCount -lt 2 -or
        $content -notmatch "vendor/autoload\.php" -or
        $content -notmatch "bootstrap/app\.php" -or
        $content -match '[A-Za-z]:[\\/]') {
        throw 'Production index.php is missing its safe private-core placeholder or contains a local absolute path.'
    }
}

function Test-ProductionHtaccess {
    param([string] $Path)

    $content = Get-Content -Raw -Encoding UTF8 $Path
    $requiredPatterns = @(
        'Options -MultiViews -Indexes',
        '\^www\\\.pflegeindex\\\.com\$',
        'https://pflegeindex\.com%\{REQUEST_URI\}',
        '%\{HTTPS\} !=on',
        'Protect hidden files',
        '\[F,L,NC\]',
        'RewriteRule \^ index\.php \[L\]'
    )

    foreach ($pattern in $requiredPatterns) {
        if ($content -notmatch $pattern) {
            throw "Production .htaccess validation failed for pattern: $pattern"
        }
    }

    if ($content -match 'server-version|pflege-core-7f3c91a2') {
        throw 'Production .htaccess references the obsolete deployment structure.'
    }
}

function Install-ProductionDependencies {
    param([string] $SourceRoot)

    $php = Get-Command php -ErrorAction SilentlyContinue
    $composer = Get-Command composer -ErrorAction SilentlyContinue

    if ($null -eq $php -or $null -eq $composer) {
        return [pscustomobject]@{
            status = 'blocked'
            message = 'Local PHP CLI and Composer are both required.'
            reason = 'Local PHP CLI and Composer are both required; no existing vendor directory was copied.'
            phpVersion = $null
            composerInstallExitCode = $null
            platformCheckExitCode = $null
            vendorPresent = $false
            autoloadPresent = $false
        }
    }

    $phpVersion = (& $php.Source -r 'echo PHP_VERSION;').Trim()
    if ($LASTEXITCODE -ne 0 -or $phpVersion -notmatch '^8\.2\.') {
        throw 'Dependency build must run with PHP 8.2.x.'
    }

    $installResult = Invoke-LoggedNativeCommand -Command $composer.Source -Arguments @(
        'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--prefer-dist'
    ) -WorkingDirectory $SourceRoot
    if ($installResult.ExitCode -ne 0) {
        throw "Composer production install failed with exit code $($installResult.ExitCode)."
    }

    $vendorPresent = Test-Path -LiteralPath (Join-Path $SourceRoot 'vendor') -PathType Container
    $autoloadPresent = Test-Path -LiteralPath (Join-Path $SourceRoot 'vendor\autoload.php') -PathType Leaf
    if (-not $vendorPresent -or -not $autoloadPresent) {
        throw 'Composer reported success but production vendor/autoload.php is missing.'
    }

    $platformResult = Invoke-LoggedNativeCommand -Command $composer.Source -Arguments @(
        'check-platform-reqs', '--no-dev'
    ) -WorkingDirectory $SourceRoot
    if ($platformResult.ExitCode -ne 0) {
        throw "Composer production platform check failed with exit code $($platformResult.ExitCode)."
    }

    foreach ($devPackage in @(
        'vendor\phpunit',
        'vendor\fakerphp',
        'vendor\mockery',
        'vendor\laravel\pail',
        'vendor\nunomaduro\collision'
    )) {
        if (Test-Path -LiteralPath (Join-Path $SourceRoot $devPackage)) {
            throw "Development dependency is present after --no-dev install: $devPackage"
        }
    }

    return [pscustomobject]@{
        status = 'complete'
        message = 'Production dependencies and platform requirements verified.'
        reason = 'composer install and composer check-platform-reqs completed successfully.'
        phpVersion = $phpVersion
        composerInstallExitCode = $installResult.ExitCode
        platformCheckExitCode = $platformResult.ExitCode
        vendorPresent = $vendorPresent
        autoloadPresent = $autoloadPresent
    }
}

function Assert-DependencyResult {
    param([object] $Result)

    if ($null -eq $Result -or $Result -is [array]) {
        throw 'Dependency install must return exactly one structured result object.'
    }

    foreach ($property in @(
        'status',
        'message',
        'composerInstallExitCode',
        'platformCheckExitCode',
        'vendorPresent',
        'autoloadPresent'
    )) {
        if ($Result.PSObject.Properties.Name -notcontains $property) {
            throw "Dependency result is missing required property: $property"
        }
    }
}

function Copy-ProductionPayload {
    param(
        [string] $SourceRoot,
        [string] $OutputRoot,
        [bool] $DependenciesComplete
    )

    $privateCore = Join-Path $OutputRoot 'private-core'
    $publicRoot = Join-Path $OutputRoot 'public-webroot'
    $manifestRoot = Join-Path $OutputRoot 'manifest'

    New-Item -ItemType Directory -Path $privateCore, $publicRoot, $manifestRoot -Force | Out-Null

    foreach ($directory in @('app', 'bootstrap', 'config', 'routes')) {
        Copy-DirectoryContents -Source (Join-Path $SourceRoot $directory) -Destination (Join-Path $privateCore $directory)
    }

    Copy-DirectoryContents -Source (Join-Path $SourceRoot 'database\migrations') -Destination (Join-Path $privateCore 'database\migrations')
    Copy-DirectoryContents -Source (Join-Path $SourceRoot 'resources\views') -Destination (Join-Path $privateCore 'resources\views')

    foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
        Copy-Item -LiteralPath (Join-Path $SourceRoot $file) -Destination (Join-Path $privateCore $file) -Force
    }

    New-StorageSkeleton -PrivateCore $privateCore

    if ($DependenciesComplete) {
        Copy-DirectoryContents -Source (Join-Path $SourceRoot 'vendor') -Destination (Join-Path $privateCore 'vendor')
    }

    Copy-Item -LiteralPath (Join-Path $repoRoot 'deployment\index.production.php') -Destination (Join-Path $publicRoot 'index.php') -Force
    Copy-Item -LiteralPath (Join-Path $repoRoot 'deployment\public.production.htaccess') -Destination (Join-Path $publicRoot '.htaccess') -Force

    foreach ($file in @(
        'assets\styles.css',
        'assets\admin.css',
        'assets\app.js',
        'assets\og-image.png',
        'favicon.svg',
        'logo.svg',
        'logo-light.svg'
    )) {
        $destination = Join-Path $publicRoot $file
        New-Item -ItemType Directory -Path (Split-Path $destination -Parent) -Force | Out-Null
        Copy-Item -LiteralPath (Join-Path $SourceRoot ('public\' + $file)) -Destination $destination -Force
    }

    Copy-Item -LiteralPath (Join-Path $repoRoot 'deployment\.env.production.template') -Destination (Join-Path $manifestRoot '.env.production.template') -Force
    Copy-Item -LiteralPath (Join-Path $repoRoot 'deployment\DATABASE_DEPLOYMENT_DECISION.md') -Destination (Join-Path $manifestRoot 'DATABASE_DEPLOYMENT_DECISION.md') -Force
}

function Get-ForbiddenFindings {
    param([string] $PackageRoot)

    $findings = New-Object System.Collections.Generic.List[object]
    $textExtensions = @('.php', '.json', '.md', '.txt', '.xml', '.yml', '.yaml', '.js', '.css', '.html', '.template', '.lock')
    $secretNames = 'APP_KEY|DB_PASSWORD|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY|API_KEY|ACCESS_TOKEN|SECRET_KEY'
    $localUserName = [Environment]::UserName

    Get-ChildItem -LiteralPath $PackageRoot -Recurse -File -Force | ForEach-Object {
        $relative = Get-RelativePackagePath -Root $PackageRoot -Path $_.FullName
        $segments = $relative -split '/'
        $leaf = $_.Name

        if ($leaf -eq '.env' -or ($leaf -like '.env.*' -and $leaf -ne '.env.production.template')) {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'environment file' })
        }
        if ($leaf -match '\.(sqlite|sqlite3)$|-(wal|shm)$') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'SQLite payload' })
        }
        if ($leaf -match '\.log$') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'log file' })
        }
        if ($leaf -match '\.zip$') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'nested ZIP archive' })
        }
        if ($segments -contains '.git' -or $segments -contains '.github') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'Git metadata' })
        }
        if ($segments[0] -in @('tests', 'node_modules', 'vendor-old', 'server-version')) {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'development or obsolete directory' })
        }
        if ($relative -notlike 'private-core/vendor/*' -and $leaf -match '(?i)(^|[-_.])(backup|bak)([-_.]|$)') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'backup file' })
        }
        if ($relative -match '^private-core/vendor/(phpunit|fakerphp|mockery|laravel/pail|nunomaduro/collision)/') {
            $findings.Add([pscustomobject]@{ Path = $relative; Type = 'development dependency' })
        }

        if ($textExtensions -contains $_.Extension.ToLowerInvariant() -or $leaf -in @('.htaccess', 'artisan')) {
            $content = Get-Content -Raw -Encoding UTF8 $_.FullName

            $containsLocalUserPath = $false
            if (-not [string]::IsNullOrWhiteSpace($localUserName)) {
                $userPathPattern = '(?i)(?:[A-Z]:[\\/]Users[\\/]|/Users/|/home/)' + [regex]::Escape($localUserName) + '([\\/]|$)'
                $containsLocalUserPath = $content -match $userPathPattern
            }

            $localRepoPattern = [regex]::Escape($repoRoot) -replace '\\\\', '[\\/]'
            if ($content -match $localRepoPattern -or $containsLocalUserPath) {
                $findings.Add([pscustomobject]@{ Path = $relative; Type = 'local absolute path or username' })
            }

            foreach ($match in [regex]::Matches($content, "(?m)^\s*($secretNames)\s*=\s*([^\r\n]*)$")) {
                $value = $match.Groups[2].Value.Trim().Trim('"', "'")
                if ($value -ne '' -and $value -notmatch '^__[A-Z0-9_]+__$' -and $value -notmatch '^\$\{[A-Z0-9_]+\}$') {
                    $findings.Add([pscustomobject]@{ Path = $relative; Type = "non-placeholder secret variable $($match.Groups[1].Value)" })
                }
            }
        }
    }

    return @($findings | ForEach-Object { $_ })
}

function Assert-NoForbiddenFiles {
    param([string] $PackageRoot)

    $findings = @(Get-ForbiddenFindings -PackageRoot $PackageRoot)
    if ($findings.Count -gt 0) {
        $summary = $findings | ForEach-Object { "$($_.Path) [$($_.Type)]" }
        throw "Forbidden-file scan failed:`n$($summary -join "`n")"
    }
}

function New-ChecksumManifest {
    param([string] $PackageRoot)

    $checksumPath = Join-Path $PackageRoot 'manifest\files.sha256'
    $files = Get-ChildItem -LiteralPath $PackageRoot -Recurse -File -Force |
        Where-Object { $_.FullName -ne $checksumPath } |
        Sort-Object FullName
    $lines = foreach ($file in $files) {
        $relative = Get-RelativePackagePath -Root $PackageRoot -Path $file.FullName
        $hash = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        "$hash  $relative"
    }

    Write-Utf8NoBom -Path $checksumPath -Content (($lines -join "`n") + "`n")
}

function Test-ChecksumManifest {
    param([string] $PackageRoot)

    $checksumPath = Join-Path $PackageRoot 'manifest\files.sha256'
    foreach ($line in Get-Content -Encoding UTF8 $checksumPath) {
        if ($line -notmatch '^([a-f0-9]{64})  (.+)$') {
            throw 'Invalid files.sha256 format.'
        }

        $expected = $Matches[1]
        $relative = $Matches[2]
        $target = Join-Path $PackageRoot ($relative.Replace('/', '\'))
        $actual = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant()

        if ($actual -ne $expected) {
            throw "Checksum verification failed: $relative"
        }
    }
}

function Write-ReleaseManifest {
    param(
        [string] $OutputRoot,
        [string] $Commit,
        [string] $ShortCommit,
        [string] $Ref,
        [string] $BuildDate,
        [string] $LaravelVersion,
        [string] $ComposerLockHash,
        [object] $DependencyResult
    )

    $privateRoot = Join-Path $OutputRoot 'private-core'
    $publicRoot = Join-Path $OutputRoot 'public-webroot'
    $privateFiles = @(Get-ChildItem -LiteralPath $privateRoot -Recurse -File -Force)
    $publicFiles = @(Get-ChildItem -LiteralPath $publicRoot -Recurse -File -Force)
    $payloadBytes = ($privateFiles + $publicFiles | Measure-Object -Property Length -Sum).Sum
    if ($null -eq $payloadBytes) { $payloadBytes = 0 }

    $manifestTemplate = @'
# PflegeIndex Production Release Manifest

- Full commit: `{0}`
- Short commit: `{1}`
- Requested ref: `{2}`
- Build date (UTC): `{3}`
- PHP target: `8.2.27`
- Laravel version: `{4}`
- composer.lock SHA-256: `{5}`
- Private-core files: `{6}`
- Public-webroot files: `{7}`
- Payload size: `{8}` bytes
- Composer dependency build: `{9}`
- Composer detail: `{10}`
- Forbidden-file scan: `passed`

## Exact package composition

`private-core/` contains `app/`, `bootstrap/`, `config/`,
`database/migrations/`, `resources/views/`, `routes/`, a clean `storage/`
skeleton, `artisan`, `composer.json`, `composer.lock`, and `vendor/` only when
the production dependency build is complete.

`public-webroot/` contains `.htaccess`, the deployment `index.php`,
`assets/admin.css`, `assets/app.js`, `assets/og-image.png`,
`assets/styles.css`, `favicon.svg`, `logo-light.svg`, and
`logo.svg`.

`manifest/` contains this manifest, `.env.production.template`,
`DATABASE_DEPLOYMENT_DECISION.md`, `build-info.json`, and `files.sha256`.

## Excluded categories

Secrets and real `.env` files; SQLite and database backups; logs and runtime
caches; Git metadata; tests and PHPUnit cache; Node/Vite build inputs;
`node_modules`; dev dependencies; `vendor-old`; `server-version`; old ZIP
archives; documentation and local audit output not required at runtime.

## Required production actions

1. Replace `__PRIVATE_CORE_PATH__` in `public-webroot/index.php` with the
   absolute private-core path before switching the site. The placeholder makes
   the packaged front controller return HTTP 503 intentionally.
2. Create the real private-core `.env` from the safe template. Keep the
   existing production `APP_KEY`; never upload `.env` to webroot.
3. Keep SQLite outside the release directory and webroot. Follow
   `DATABASE_DEPLOYMENT_DECISION.md`; GeoCore is not populated automatically.
'@
    $manifest = $manifestTemplate -f @(
        $Commit,
        $ShortCommit,
        $Ref,
        $BuildDate,
        $LaravelVersion,
        $ComposerLockHash,
        $privateFiles.Count,
        $publicFiles.Count,
        $payloadBytes,
        $DependencyResult.status,
        $DependencyResult.reason
    )

    Write-Utf8NoBom -Path (Join-Path $OutputRoot 'manifest\RELEASE_MANIFEST.md') -Content $manifest

    $buildInfo = [ordered]@{
        schemaVersion = 1
        commit = $Commit
        shortCommit = $ShortCommit
        requestedRef = $Ref
        buildDateUtc = $BuildDate
        phpTarget = '8.2.27'
        buildPhpVersion = $DependencyResult.phpVersion
        laravelVersion = $LaravelVersion
        composerLockSha256 = $ComposerLockHash
        privateCoreFileCount = $privateFiles.Count
        publicWebrootFileCount = $publicFiles.Count
        payloadBytes = [long] $payloadBytes
        dependencyBuild = [ordered]@{
            status = $DependencyResult.status
            detail = $DependencyResult.reason
            composerInstallExitCode = $DependencyResult.composerInstallExitCode
            platformCheckExitCode = $DependencyResult.platformCheckExitCode
            vendorPresent = $DependencyResult.vendorPresent
            autoloadPresent = $DependencyResult.autoloadPresent
        }
        forbiddenFileScan = 'passed'
        indexRequiresPrivatePathReplacement = $true
        databaseIncluded = $false
        archivesCreated = $false
    }

    Write-Utf8NoBom -Path (Join-Path $OutputRoot 'manifest\build-info.json') -Content ($buildInfo | ConvertTo-Json -Depth 5)
}

function New-ZipFromDirectory {
    param([string] $SourceDirectory, [string] $DestinationZip)

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if (Test-Path -LiteralPath $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $SourceDirectory,
        $DestinationZip,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )
}

function Test-ZipArchive {
    param([string] $ZipPath, [string] $ExpectedRootEntry)

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    try {
        $names = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
        if ($names -notcontains $ExpectedRootEntry) {
            throw "ZIP archive has an unexpected top-level layout: $ZipPath"
        }

        foreach ($name in $names) {
            if ($name -match '(^|/)(\.env|database\.sqlite|tests|node_modules|vendor-old|server-version)(/|$)' -or
                $name -match '\.(sqlite|sqlite3|log|zip)$|-(wal|shm)$') {
                throw "ZIP archive contains a forbidden entry type: $ZipPath"
            }
        }
    } finally {
        $archive.Dispose()
    }
}

function Invoke-SelfCheck {
    param([string] $Commit)

    New-Item -ItemType Directory -Path $buildBase -Force | Out-Null
    $root = Join-Path $buildBase ('.self-check-' + [guid]::NewGuid().ToString('N'))
    Assert-SafeChildPath -Path $root -AllowedRoot $buildBase
    New-Item -ItemType Directory -Path $root -Force | Out-Null
    $passed = 0

    try {
        $source = Join-Path $root 'source'
        Export-ReleaseCommit -Commit $Commit -Destination $source
        Test-SourceRequirements -SourceRoot $source
        $passed++

        $trackedPath = 'resources/views/layouts/app.blade.php'
        $archiveHash = (& git -C $repoRoot hash-object (Join-Path $source $trackedPath)).Trim()
        $commitHash = (& git -C $repoRoot rev-parse "$Commit`:$trackedPath").Trim()
        if ($archiveHash -ne $commitHash) { throw 'Clean export included working-tree content.' }
        $passed++

        foreach ($case in @(
            @{ Name = 'env'; File = '.env' },
            @{ Name = 'sqlite'; File = 'database.sqlite' },
            @{ Name = 'log'; File = 'laravel.log' },
            @{ Name = 'local-path'; File = 'local-path.php'; Content = 'D:\Websites\Pflegeindex\laravel' }
        )) {
            $fixture = Join-Path $root ('scan-' + $case.Name)
            New-Item -ItemType Directory -Path $fixture -Force | Out-Null
            $fixtureContent = if ($case.ContainsKey('Content')) { $case.Content } else { 'test fixture only' }
            Write-Utf8NoBom -Path (Join-Path $fixture $case.File) -Content $fixtureContent
            $rejected = $false
            try { Assert-NoForbiddenFiles -PackageRoot $fixture } catch { $rejected = $_.Exception.Message -like 'Forbidden-file scan failed:*' }
            if (-not $rejected) { throw "Scanner accepted forbidden $($case.Name) fixture." }
            $passed++
        }

        $publicFixture = Join-Path $root 'public-missing'
        New-Item -ItemType Directory -Path $publicFixture -Force | Out-Null
        $rejected = $false
        try { Test-PublicAssets -SourceRoot $source -PublicRoot $publicFixture } catch { $rejected = $true }
        if (-not $rejected) { throw 'Missing public asset was accepted.' }
        $passed++

        $sourceFixture = Join-Path $root 'source-missing-lock'
        Copy-DirectoryContents -Source $source -Destination $sourceFixture
        Remove-Item -LiteralPath (Join-Path $sourceFixture 'composer.lock') -Force
        $rejected = $false
        try { Test-SourceRequirements -SourceRoot $sourceFixture } catch { $rejected = $true }
        if (-not $rejected) { throw 'Missing composer.lock was accepted.' }
        $passed++

        $badIndex = Join-Path $root 'bad-index.php'
        Write-Utf8NoBom -Path $badIndex -Content '<?php require "/private/vendor/autoload.php";'
        $rejected = $false
        try { Test-ProductionIndex -Path $badIndex } catch { $rejected = $true }
        if (-not $rejected) { throw 'Production index without placeholder was accepted.' }
        $passed++

        $checksumFixture = Join-Path $root 'checksum'
        New-Item -ItemType Directory -Path (Join-Path $checksumFixture 'manifest') -Force | Out-Null
        Write-Utf8NoBom -Path (Join-Path $checksumFixture 'payload.txt') -Content 'checksum fixture'
        New-ChecksumManifest -PackageRoot $checksumFixture
        Test-ChecksumManifest -PackageRoot $checksumFixture
        $passed++

        $repeatFixture = Join-Path $root 'repeat-output'
        New-Item -ItemType Directory -Path $repeatFixture -Force | Out-Null
        Write-Utf8NoBom -Path (Join-Path $repeatFixture 'stale.txt') -Content 'must disappear'
        Remove-SafeDirectory -Path $repeatFixture -AllowedRoot $root
        New-Item -ItemType Directory -Path $repeatFixture -Force | Out-Null
        if (Test-Path -LiteralPath (Join-Path $repeatFixture 'stale.txt')) { throw 'Repeated build mixed stale files.' }
        $passed++

        $php = Get-Command php -ErrorAction SilentlyContinue
        $composer = Get-Command composer -ErrorAction SilentlyContinue
        if ($null -ne $php -and $null -ne $composer) {
            $dependencyResult = Install-ProductionDependencies -SourceRoot $source
            Assert-DependencyResult -Result $dependencyResult
            if ($dependencyResult.status -ne 'complete' -or
                -not $dependencyResult.vendorPresent -or
                -not $dependencyResult.autoloadPresent -or
                $dependencyResult.composerInstallExitCode -ne 0 -or
                $dependencyResult.platformCheckExitCode -ne 0) {
                throw 'Successful Composer dependency integration check failed.'
            }

            $dependencyPackage = Join-Path $root 'dependency-package'
            Copy-ProductionPayload -SourceRoot $source -OutputRoot $dependencyPackage -DependenciesComplete $true
            if (-not (Test-Path -LiteralPath (Join-Path $dependencyPackage 'private-core\vendor\autoload.php') -PathType Leaf)) {
                throw 'Package build did not continue after successful dependency installation.'
            }
            $passed++

            if ($passed -ne 12) { throw "Self-check count mismatch: $passed/12" }
            Write-Host 'SELF-CHECK PASSED: 12/12 checks, including real Composer dependency installation.' -ForegroundColor Green
        } else {
            if ($passed -ne 11) { throw "Self-check count mismatch: $passed/11" }
            Write-Host 'SELF-CHECK PASSED: 11/11 core checks.' -ForegroundColor Green
            Write-Warning 'Composer dependency integration check skipped because PHP or Composer is unavailable.'
        }
    } finally {
        Remove-SafeDirectory -Path $root -AllowedRoot $buildBase
    }
}

function Invoke-ProductionBuild {
    param([string] $Commit, [string] $ShortCommit, [string] $Ref)

    New-Item -ItemType Directory -Path $buildBase -Force | Out-Null
    $initialStatus = @(& git -C $repoRoot status --porcelain=v1)
    $workRoot = Join-Path $buildBase ('.work-' + $ShortCommit + '-' + [guid]::NewGuid().ToString('N'))
    $script:temporaryRoot = $workRoot
    Assert-SafeChildPath -Path $workRoot -AllowedRoot $buildBase
    New-Item -ItemType Directory -Path $workRoot -Force | Out-Null

    $sourceRoot = Join-Path $workRoot 'source'
    $stagingRoot = Join-Path $workRoot 'package'
    Export-ReleaseCommit -Commit $Commit -Destination $sourceRoot
    Test-SourceRequirements -SourceRoot $sourceRoot

    $dependencyResult = Install-ProductionDependencies -SourceRoot $sourceRoot
    Assert-DependencyResult -Result $dependencyResult
    $dependenciesComplete = $dependencyResult.status -eq 'complete'
    Copy-ProductionPayload -SourceRoot $sourceRoot -OutputRoot $stagingRoot -DependenciesComplete $dependenciesComplete

    Test-PublicAssets -SourceRoot $sourceRoot -PublicRoot (Join-Path $stagingRoot 'public-webroot')
    Test-ProductionIndex -Path (Join-Path $stagingRoot 'public-webroot\index.php')
    Test-ProductionHtaccess -Path (Join-Path $stagingRoot 'public-webroot\.htaccess')

    $lockHash = (Get-FileHash -LiteralPath (Join-Path $sourceRoot 'composer.lock') -Algorithm SHA256).Hash.ToLowerInvariant()
    $lock = Get-Content -Raw -Encoding UTF8 (Join-Path $sourceRoot 'composer.lock') | ConvertFrom-Json
    $laravelVersion = ($lock.packages | Where-Object { $_.name -eq 'laravel/framework' } | Select-Object -First 1).version
    $buildDate = [DateTime]::UtcNow.ToString('o')

    Assert-NoForbiddenFiles -PackageRoot $stagingRoot
    Write-ReleaseManifest -OutputRoot $stagingRoot -Commit $Commit -ShortCommit $ShortCommit -Ref $Ref `
        -BuildDate $buildDate -LaravelVersion $laravelVersion -ComposerLockHash $lockHash -DependencyResult $dependencyResult
    Assert-NoForbiddenFiles -PackageRoot $stagingRoot
    New-ChecksumManifest -PackageRoot $stagingRoot
    Test-ChecksumManifest -PackageRoot $stagingRoot
    Assert-NoForbiddenFiles -PackageRoot $stagingRoot

    $targetRoot = Join-Path $buildBase $ShortCommit
    Assert-SafeChildPath -Path $targetRoot -AllowedRoot $buildBase
    Remove-SafeDirectory -Path $targetRoot -AllowedRoot $buildBase
    Copy-DirectoryContents -Source $stagingRoot -Destination $targetRoot

    $finalStatus = @(& git -C $repoRoot status --porcelain=v1)
    if (($initialStatus -join "`n") -ne ($finalStatus -join "`n")) {
        throw 'Main working-tree status changed during package build.'
    }

    $archiveRoot = Join-Path $buildBase 'archives'
    New-Item -ItemType Directory -Path $archiveRoot -Force | Out-Null
    $archives = @()

    if ($dependenciesComplete) {
        Assert-NoForbiddenFiles -PackageRoot $targetRoot
        Test-ChecksumManifest -PackageRoot $targetRoot

        $privateZip = Join-Path $archiveRoot "pflegeindex-private-core-$ShortCommit.zip"
        $publicZip = Join-Path $archiveRoot "pflegeindex-public-webroot-$ShortCommit.zip"
        $manifestZip = Join-Path $archiveRoot "pflegeindex-manifest-$ShortCommit.zip"
        New-ZipFromDirectory -SourceDirectory (Join-Path $targetRoot 'private-core') -DestinationZip $privateZip
        New-ZipFromDirectory -SourceDirectory (Join-Path $targetRoot 'public-webroot') -DestinationZip $publicZip
        New-ZipFromDirectory -SourceDirectory (Join-Path $targetRoot 'manifest') -DestinationZip $manifestZip
        Test-ZipArchive -ZipPath $privateZip -ExpectedRootEntry 'artisan'
        Test-ZipArchive -ZipPath $publicZip -ExpectedRootEntry '.htaccess'
        Test-ZipArchive -ZipPath $manifestZip -ExpectedRootEntry 'RELEASE_MANIFEST.md'
        $archives = @($privateZip, $publicZip, $manifestZip)

        $buildInfoPath = Join-Path $targetRoot 'manifest\build-info.json'
        $buildInfo = Get-Content -Raw -Encoding UTF8 $buildInfoPath | ConvertFrom-Json
        $buildInfo.archivesCreated = $true
        Write-Utf8NoBom -Path $buildInfoPath -Content ($buildInfo | ConvertTo-Json -Depth 5)
        New-ChecksumManifest -PackageRoot $targetRoot
        Test-ChecksumManifest -PackageRoot $targetRoot
    }

    Write-Host "Release commit: $Commit"
    Write-Host "Package directory: build/production/$ShortCommit"
    Write-Host "Dependency build: $($dependencyResult.status)"
    Write-Host 'Forbidden-file scan: passed'
    Write-Host 'Checksums: verified'
    Write-Host 'Production index.php: __PRIVATE_CORE_PATH__ replacement required'
    if ($archives.Count -gt 0) {
        Write-Host 'Archives:'
        foreach ($archive in $archives) {
            $item = Get-Item -LiteralPath $archive
            Write-Host "  $($item.Name) ($($item.Length) bytes)"
        }
    } else {
        Write-Warning 'ZIP archives were not created because production dependencies are incomplete.'
    }

    return [pscustomobject]@{
        Complete = $dependenciesComplete
        OutputRoot = $targetRoot
        DependencyStatus = $dependencyResult.status
        Archives = $archives
    }
}

try {
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot '.git'))) {
        throw 'Run this script from the PflegeIndex Laravel Git repository.'
    }

    $commit = Resolve-ReleaseCommit -Ref $ReleaseRef
    $shortCommit = (& git -C $repoRoot rev-parse --short=8 $commit).Trim()

    if ($SelfCheck) {
        Invoke-SelfCheck -Commit $commit
        exit 0
    }

    $result = Invoke-ProductionBuild -Commit $commit -ShortCommit $shortCommit -Ref $ReleaseRef
    if (-not $result.Complete) {
        Write-Host 'PACKAGE BLOCKED: install production-only vendor with local PHP 8.2.x and Composer, then rebuild.' -ForegroundColor Red
        exit 2
    }

    Write-Host 'PACKAGE READY WITH WARNINGS: replace the private-core path and prepare production .env/database separately.' -ForegroundColor Green
    exit 0
} catch {
    Write-Error ("{0}`n{1}" -f $_.Exception.Message, $_.ScriptStackTrace)
    exit 1
} finally {
    if ($null -ne $script:temporaryRoot -and (Test-Path -LiteralPath $script:temporaryRoot)) {
        Remove-SafeDirectory -Path $script:temporaryRoot -AllowedRoot $buildBase
    }
}
