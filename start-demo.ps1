[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$demoRoot = $PSScriptRoot
$runtimePath = Join-Path $demoRoot '.runtime'
$phpExecutable = (Get-Command php -ErrorAction Stop).Source
$pgControl = Join-Path $runtimePath 'pgsql/bin/pg_ctl.exe'
$pgData = Join-Path $runtimePath 'pgdata'
if (-not (Test-Path -LiteralPath $pgControl) -or -not (Test-Path -LiteralPath (Join-Path $pgData 'PG_VERSION'))) {
    throw 'PostgreSQL PoC belum disiapkan. Periksa panduan pada README.'
}

& $pgControl -D $pgData status > $null
if ($LASTEXITCODE -ne 0) {
    & $pgControl -D $pgData -l (Join-Path $runtimePath 'postgresql.log') -o '-h 127.0.0.1 -p 5433' -w start
    if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL gagal dijalankan.' }
}

$scanPath = Join-Path $runtimePath 'php'
New-Item -ItemType Directory -Path $scanPath -Force > $null
[System.IO.File]::WriteAllText((Join-Path $scanPath 'postgres.ini'), "extension=pdo_pgsql`n")
$previousScanPath = $env:PHP_INI_SCAN_DIR
$env:PHP_INI_SCAN_DIR = $scanPath
$processFile = Join-Path $runtimePath 'processes.json'
$managedProcesses = @{}
if (Test-Path -LiteralPath $processFile) {
    $previous = Get-Content -LiteralPath $processFile -Raw | ConvertFrom-Json
    foreach ($property in $previous.PSObject.Properties) { $managedProcesses[$property.Name] = $property.Value }
}

try {
    $apps = @(
        @{Name='A'; Path=$demoRoot; Port=8000; Page='/admin'; Marker='APLIKASI A'},
        @{Name='B'; Path=(Join-Path $demoRoot 'app-b'); Port=8001; Page='/'; Marker='APLIKASI B'},
        @{Name='C'; Path=(Join-Path $demoRoot 'app-c'); Port=8002; Page='/app-c'; Marker='APLIKASI C'}
    )
    foreach ($application in $apps) {
        $url = "http://127.0.0.1:$($application.Port)$($application.Page)"
        $socket = New-Object System.Net.Sockets.TcpClient
        $listening = $false
        try { $socket.Connect('127.0.0.1', $application.Port); $listening = $true } catch {} finally { $socket.Dispose() }
        if ($listening) {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 15
            if (-not $response.Content.Contains($application.Marker)) {
                throw "Port $($application.Port) digunakan aplikasi lain; tidak diubah."
            }
        } else {
            $router = Join-Path $application.Path 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
            $web = Start-Process -FilePath $phpExecutable -ArgumentList @('-S', "127.0.0.1:$($application.Port)", ('"' + $router + '"')) `
                -WorkingDirectory (Join-Path $application.Path 'public') -WindowStyle Hidden `
                -RedirectStandardOutput (Join-Path $runtimePath "$($application.Name)-web.log") `
                -RedirectStandardError (Join-Path $runtimePath "$($application.Name)-web-error.log") -PassThru
            $managedProcesses["$($application.Name)Web"] = $web.Id
        }
        Write-Output "Aplikasi $($application.Name): $url"
    }
    if ($managedProcesses.ContainsKey('CScheduler')) {
        $oldScheduler = Get-CimInstance Win32_Process -Filter "ProcessId = $($managedProcesses['CScheduler'])" -ErrorAction SilentlyContinue
        if ($oldScheduler -and $oldScheduler.CommandLine -match 'artisan.*schedule:work') {
            Stop-Process -Id $oldScheduler.ProcessId -ErrorAction Stop
        }
        $managedProcesses.Remove('CScheduler')
    }
    foreach ($role in @('B')) {
        $schedulerKey = "${role}Scheduler"
        $existing = $null
        if ($managedProcesses.ContainsKey($schedulerKey)) {
            $existing = Get-CimInstance Win32_Process -Filter "ProcessId = $($managedProcesses[$schedulerKey])" -ErrorAction SilentlyContinue
        }
        if (-not $existing -or $existing.CommandLine -notmatch 'artisan.*schedule:work') {
            $scheduler = Start-Process -FilePath $phpExecutable -ArgumentList @('artisan','schedule:work') `
                -WorkingDirectory (Join-Path $demoRoot "app-$($role.ToLower())") -WindowStyle Hidden `
                -RedirectStandardOutput (Join-Path $runtimePath "$role-scheduler.log") `
                -RedirectStandardError (Join-Path $runtimePath "$role-scheduler-error.log") -PassThru
            $managedProcesses[$schedulerKey] = $scheduler.Id
        }
    }
    $managedProcesses | ConvertTo-Json | Set-Content -LiteralPath $processFile
    Write-Output 'Scheduler clone A ke B aktif. Data B ke C ditarik manual lewat tombol pada dashboard C.'
} finally {
    $env:PHP_INI_SCAN_DIR = $previousScanPath
}
