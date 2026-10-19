[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [Parameter(Mandatory = $true)][string]$ServerInstance,
    [string]$SourceDatabase = 'db_toko_a',
    [string]$TargetDatabase = 'db_keuangan_b',
    [Parameter(Mandatory = $true)][string]$BackupFile,
    [Parameter(Mandatory = $true)][string]$DataFile,
    [Parameter(Mandatory = $true)][string]$LogFile,
    [switch]$AllowReplaceTarget,
    [switch]$TrustServerCertificate,
    [switch]$Unattended
)

$ErrorActionPreference = 'Stop'
foreach ($databaseName in @($SourceDatabase, $TargetDatabase)) {
    if ($databaseName -notmatch '^[A-Za-z][A-Za-z0-9_]{0,127}$' -or
        $databaseName -in @('master', 'model', 'msdb', 'tempdb')) {
        throw 'Nama database harus berupa identifier sederhana dan bukan database sistem.'
    }
}
if ($SourceDatabase -eq $TargetDatabase) {
    throw 'Database sumber dan tujuan harus berbeda.'
}
if (@($BackupFile, $DataFile, $LogFile | Select-Object -Unique).Count -ne 3) {
    throw 'File backup, data, dan log harus berbeda.'
}

function ConvertTo-SqlLiteral([string]$Value) {
    return "N'" + $Value.Replace("'", "''") + "'"
}

$backupLiteral = ConvertTo-SqlLiteral $BackupFile
$dataLiteral = ConvertTo-SqlLiteral $DataFile
$logLiteral = ConvertTo-SqlLiteral $LogFile
$replace = if ($AllowReplaceTarget) { 1 } else { 0 }
$query = @"
SET NOCOUNT ON;
DECLARE @source sysname = N'$SourceDatabase', @target sysname = N'$TargetDatabase';
DECLARE @backup nvarchar(4000) = $backupLiteral, @data nvarchar(4000) = $dataLiteral, @log nvarchar(4000) = $logLiteral;
IF DB_ID(@source) IS NULL THROW 50001, 'Database sumber tidak ditemukan.', 1;
IF DB_ID(@target) IS NOT NULL AND $replace = 0 THROW 50002, 'Tujuan sudah ada. Gunakan AllowReplaceTarget untuk penimpaan.', 1;
IF (SELECT COUNT(*) FROM sys.master_files WHERE database_id = DB_ID(@source)) <> 2
    THROW 50003, 'Skrip PoC hanya mendukung satu file data dan satu log.', 1;
DECLARE @dataName sysname, @logName sysname, @sql nvarchar(max);
SELECT @dataName = name FROM sys.master_files WHERE database_id = DB_ID(@source) AND type = 0;
SELECT @logName = name FROM sys.master_files WHERE database_id = DB_ID(@source) AND type = 1;
IF @dataName IS NULL OR @logName IS NULL THROW 50004, 'Susunan file sumber tidak didukung.', 1;
IF EXISTS (SELECT 1 FROM sys.master_files WHERE physical_name = @backup OR (physical_name IN (@data, @log) AND database_id <> ISNULL(DB_ID(@target), -1)))
    THROW 50005, 'File tujuan dimiliki database lain.', 1;
SET @sql = N'BACKUP DATABASE ' + QUOTENAME(@source) + N' TO DISK = @path WITH COPY_ONLY, INIT, CHECKSUM';
EXEC sys.sp_executesql @sql, N'@path nvarchar(4000)', @path = @backup;
RESTORE VERIFYONLY FROM DISK = @backup WITH CHECKSUM;
BEGIN TRY
    IF DB_ID(@target) IS NOT NULL
    BEGIN
        SET @sql = N'ALTER DATABASE ' + QUOTENAME(@target) + N' SET SINGLE_USER WITH ROLLBACK IMMEDIATE';
        EXEC (@sql);
    END;
    SET @sql = N'RESTORE DATABASE ' + QUOTENAME(@target) + N' FROM DISK = @path WITH MOVE @dn TO @df, MOVE @ln TO @lf, CHECKSUM, RECOVERY'
        + CASE WHEN $replace = 1 THEN N', REPLACE' ELSE N'' END;
    EXEC sys.sp_executesql @sql, N'@path nvarchar(4000), @dn sysname, @df nvarchar(4000), @ln sysname, @lf nvarchar(4000)',
        @path = @backup, @dn = @dataName, @df = @data, @ln = @logName, @lf = @log;
    SET @sql = N'ALTER DATABASE ' + QUOTENAME(@target) + N' SET MULTI_USER';
    EXEC (@sql);
END TRY
BEGIN CATCH
    IF DB_ID(@target) IS NOT NULL
    BEGIN
        SET @sql = N'ALTER DATABASE ' + QUOTENAME(@target) + N' SET MULTI_USER';
        EXEC (@sql);
    END;
    THROW;
END CATCH;
"@

if ($Unattended -or $PSCmdlet.ShouldProcess("$ServerInstance / $TargetDatabase", "Backup $SourceDatabase ke $BackupFile (file backup ditimpa), lalu restore ke B")) {
    $sqlcmd = Get-Command sqlcmd -ErrorAction Stop
    $sqlArguments = @('-S', $ServerInstance, '-E', '-d', 'master', '-b', '-x')
    if ($TrustServerCertificate) { $sqlArguments += '-C' }
    $sqlArguments += @('-Q', $query)
    & $sqlcmd.Source @sqlArguments
    if ($LASTEXITCODE -ne 0) {
        throw 'Clone gagal. Periksa keluaran SQL Server; jangan lanjut menerbitkan snapshot B.'
    }
    Write-Output 'Clone A ke B selesai. Jalankan finance:publish-snapshot di instalasi B.'
}
