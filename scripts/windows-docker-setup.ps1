[CmdletBinding()]
param([switch]$Elevated)

$ErrorActionPreference = 'Stop'
$repository = Split-Path -Parent $PSScriptRoot
$logDirectory = Join-Path $repository 'logs/docker-setup'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$statusPath = Join-Path $logDirectory 'status.json'
$logPath = Join-Path $logDirectory 'setup.log'
$encoding = [Text.UTF8Encoding]::new($false)

function Write-SetupLog([string]$message) {
    [IO.File]::AppendAllText($logPath, ([DateTime]::UtcNow.ToString('o') + ' ' + $message + "`n"), $encoding)
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
$isAdministrator = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdministrator) {
    if ($Elevated) { throw 'Windows did not grant administrator privileges.' }
    Write-SetupLog 'Requesting Windows administrator privileges through UAC.'
    try {
        $child = Start-Process -FilePath "$env:SystemRoot/System32/WindowsPowerShell/v1.0/powershell.exe" `
            -Verb RunAs -WindowStyle Hidden -PassThru `
            -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + $PSCommandPath + '"'), '-Elevated')
        Write-Output "Administrator setup requested. Process: $($child.Id)"
        Write-Output "Status: $statusPath"
        exit 0
    } catch {
        Write-SetupLog ('UAC launch failed: ' + $_.Exception.Message)
        throw
    }
}

$state = [ordered]@{
    startedAt = [DateTime]::UtcNow.ToString('o')
    administrator = $true
    phase = 'features'
    restartRequired = $false
    features = @()
    wslInstallerExitCode = $null
    error = $null
}
if (Test-Path -LiteralPath $statusPath) {
    try {
        $previous = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        $bootTime = (Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToUniversalTime()
        if ($previous.restartRequired -and $bootTime -lt [DateTime]::Parse($previous.startedAt).ToUniversalTime()) {
            $state.restartRequired = $true
        }
    } catch { Write-SetupLog 'Could not read previous restart status; checking Windows feature state.' }
}
function Save-SetupState {
    [IO.File]::WriteAllText($statusPath, ($state | ConvertTo-Json -Depth 5), $encoding)
}

try {
    Save-SetupState
    foreach ($featureName in @('VirtualMachinePlatform', 'Microsoft-Windows-Subsystem-Linux')) {
        $before = Get-WindowsOptionalFeature -Online -FeatureName $featureName
        Write-SetupLog ("Feature {0}: {1}" -f $featureName, $before.State)
        if ($before.State -eq 'Disabled') {
            $result = Enable-WindowsOptionalFeature -Online -FeatureName $featureName -All -NoRestart
            if ($result.RestartNeeded) { $state.restartRequired = $true }
        } elseif ($before.State -eq 'EnablePending') {
            $state.restartRequired = $true
        } elseif ($before.State -ne 'Enabled') {
            throw "Unexpected feature state for ${featureName}: $($before.State)"
        }
        $after = Get-WindowsOptionalFeature -Online -FeatureName $featureName
        $state.features += [ordered]@{ name = $featureName; before = [string]$before.State; after = [string]$after.State }
        Save-SetupState
    }

    $state.phase = 'install-wsl'
    Save-SetupState
    # Use Microsoft's signed MSI so installation does not depend on Store availability.
    $release = Invoke-RestMethod -Uri 'https://api.github.com/repos/microsoft/WSL/releases/latest'
    $architecture = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64') { 'arm64' } else { 'x64' }
    $asset = $release.assets | Where-Object { $_.name -match ('.*\.' + $architecture + '\.msi$') } | Select-Object -First 1
    if (-not $asset) { throw "Official WSL MSI for $architecture not found." }
    if ([IO.Path]::GetFileName($asset.name) -ne $asset.name) { throw 'Invalid WSL installer filename.' }
    $msiPath = Join-Path $logDirectory $asset.name
    Write-SetupLog ('Downloading official WSL package: ' + $asset.name)
    Invoke-WebRequest -UseBasicParsing -Uri $asset.browser_download_url -OutFile $msiPath
    if ($asset.digest) {
        $expected = $asset.digest -replace '^sha256:', ''
        if ((Get-FileHash -LiteralPath $msiPath -Algorithm SHA256).Hash -ne $expected) {
            throw 'WSL installer SHA-256 mismatch.'
        }
    }
    $signature = Get-AuthenticodeSignature -LiteralPath $msiPath
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') {
        throw 'WSL installer must have a valid Microsoft signature.'
    }
    Write-SetupLog ('Verified Microsoft signature; installing ' + $release.tag_name)
    $msiLog = Join-Path $logDirectory 'wsl-msi.log'
    $installer = Start-Process -FilePath "$env:SystemRoot/System32/msiexec.exe" -WindowStyle Hidden -Wait -PassThru `
        -ArgumentList @('/i', ('"' + $msiPath + '"'), '/qn', '/norestart', '/L*v', ('"' + $msiLog + '"'))
    $state.wslInstallerExitCode = $installer.ExitCode
    if ($installer.ExitCode -eq 3010) { $state.restartRequired = $true }
    elseif ($installer.ExitCode -ne 0) { throw "WSL installer failed with exit code $($installer.ExitCode). See wsl-msi.log." }
    Remove-Item -LiteralPath $msiPath -Force
    $state.phase = if ($state.restartRequired) { 'restart-required' } else { 'ready' }
    Write-SetupLog ('Setup complete. Restart required: ' + $state.restartRequired)
    # No restart, boot configuration changes, Docker reset or volume deletion is performed.
    Save-SetupState
} catch {
    $state.phase = 'failed'
    $state.error = $_.Exception.Message
    Write-SetupLog ('Setup failed: ' + $state.error)
    Save-SetupState
    exit 1
}
