[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$SourceServer,
    [Parameter(Mandatory=$true)][string]$TargetServer,
    [Parameter(Mandatory=$true)][string]$SourceDatabase,
    [Parameter(Mandatory=$true)][string]$TargetDatabase,
    [Parameter(Mandatory=$true)][string]$SourceBackupFile,
    [Parameter(Mandatory=$true)][string]$TransferSourceFile,
    [Parameter(Mandatory=$true)][string]$TransferDestinationFile,
    [Parameter(Mandatory=$true)][string]$TargetBackupFile,
    [switch]$SourceTrustCertificate,
    [switch]$TargetTrustCertificate
)

$ErrorActionPreference = 'Stop'
foreach ($database in @($SourceDatabase,$TargetDatabase)) {
    if ($database -notmatch '^[A-Za-z][A-Za-z0-9_]{0,127}$' -or $database -in @('master','model','msdb','tempdb')) {
        throw 'Nama database clone tidak valid.'
    }
}
foreach ($file in @($SourceBackupFile,$TransferSourceFile,$TransferDestinationFile,$TargetBackupFile)) {
    if (-not $file.EndsWith('.bak', [System.StringComparison]::OrdinalIgnoreCase)) { throw 'Seluruh lokasi backup harus memakai ekstensi .bak.' }
    if (-not [System.IO.Path]::IsPathRooted($file)) { throw 'Seluruh lokasi backup harus berupa path absolut.' }
}
if (-not $TransferSourceFile.StartsWith('\\')) { throw 'Sumber transfer harus UNC share.' }
if (-not [System.IO.Path]::IsPathRooted($TransferDestinationFile)) { throw 'Tujuan transfer harus path absolut.' }
$transferTarget = [System.IO.Path]::GetFullPath($TransferDestinationFile)
if (-not (Test-Path -LiteralPath (Split-Path $transferTarget) -PathType Container)) { throw 'Folder penerima backup belum tersedia.' }

function Open-CloneConnection([string]$Server, [string]$Username, [string]$Password, [bool]$TrustCertificate) {
    $builder = New-Object System.Data.SqlClient.SqlConnectionStringBuilder
    $builder.DataSource = $Server
    $builder.InitialCatalog = 'master'
    $builder.Encrypt = $true
    $builder.TrustServerCertificate = $TrustCertificate
    $builder.ConnectTimeout = 20
    $builder.ApplicationName = 'PoC network database clone'
    if ([string]::IsNullOrEmpty($Username)) { $builder.IntegratedSecurity = $true }
    else { $builder.UserID = $Username; $builder.Password = $Password }
    $connection = New-Object System.Data.SqlClient.SqlConnection($builder.ConnectionString)
    $connection.Open()
    return $connection
}

function Invoke-CloneSql($Connection, [string]$Sql, [hashtable]$Parameters, [switch]$Rows) {
    $command = $Connection.CreateCommand()
    $command.CommandText = $Sql
    $command.CommandTimeout = 1200
    foreach ($name in $Parameters.Keys) { $null = $command.Parameters.AddWithValue($name, $Parameters[$name]) }
    try {
        if ($Rows) {
            $table = New-Object System.Data.DataTable
            $adapter = New-Object System.Data.SqlClient.SqlDataAdapter($command)
            try { $null = $adapter.Fill($table) } finally { $adapter.Dispose() }
            return ,$table
        }
        $null = $command.ExecuteNonQuery()
    } finally { $command.Dispose() }
}

$sourceConnection = $null
$targetConnection = $null
try {
    $sourceConnection = Open-CloneConnection $SourceServer $env:CLONE_A_SQL_USERNAME $env:CLONE_A_SQL_PASSWORD $SourceTrustCertificate.IsPresent
    $targetConnection = Open-CloneConnection $TargetServer $env:CLONE_B_SQL_USERNAME $env:CLONE_B_SQL_PASSWORD $TargetTrustCertificate.IsPresent
    $identitySql = "SELECT CONVERT(nvarchar(128),SERVERPROPERTY('ServerName')) AS server_name, CONVERT(int,SERVERPROPERTY('ProductMajorVersion')) AS major_version"
    $sourceInfo = Invoke-CloneSql $sourceConnection $identitySql @{} -Rows
    $targetInfo = Invoke-CloneSql $targetConnection $identitySql @{} -Rows
    if ($sourceInfo.Rows[0].server_name -eq $targetInfo.Rows[0].server_name -and $SourceDatabase -eq $TargetDatabase) {
        throw 'Sumber dan tujuan menunjuk database yang sama pada server yang sama.'
    }
    if ([int]$targetInfo.Rows[0].major_version -lt [int]$sourceInfo.Rows[0].major_version) { throw 'Versi SQL Server B lebih lama daripada A; backup tidak dapat direstore.' }

    $targetFiles = Invoke-CloneSql $targetConnection 'SELECT type,physical_name FROM sys.master_files WHERE database_id=DB_ID(@target)' @{'@target'=$TargetDatabase} -Rows
    $defaultPaths = Invoke-CloneSql $targetConnection "SELECT CONVERT(nvarchar(260),SERVERPROPERTY('InstanceDefaultDataPath')) AS data_path, CONVERT(nvarchar(260),SERVERPROPERTY('InstanceDefaultLogPath')) AS log_path" @{} -Rows
    if (-not $defaultPaths.Rows[0].data_path -or -not $defaultPaths.Rows[0].log_path) { throw 'Direktori data/log B tidak tersedia.' }
    $targetData = Join-Path $defaultPaths.Rows[0].data_path "$TargetDatabase.mdf"
    $targetLog = Join-Path $defaultPaths.Rows[0].log_path "${TargetDatabase}_log.ldf"
    if ($targetFiles.Rows.Count -notin @(0,2)) { throw 'Target PoC harus memiliki satu file data dan satu log.' }
    foreach ($row in $targetFiles.Rows) {
        if ([int]$row.type -eq 0) { $targetData = $row.physical_name }
        elseif ([int]$row.type -eq 1) { $targetLog = $row.physical_name }
        else { throw 'Susunan file target tidak didukung.' }
    }

    $backupSql = @'
IF DB_ID(@source) IS NULL THROW 50001, 'Database A tidak ditemukan.', 1;
IF (SELECT COUNT(*) FROM sys.master_files WHERE database_id=DB_ID(@source)) <> 2 THROW 50002, 'PoC mendukung satu file data dan satu log.', 1;
DECLARE @sql nvarchar(max)=N'BACKUP DATABASE '+QUOTENAME(@source)+N' TO DISK=@file WITH COPY_ONLY, INIT, CHECKSUM';
EXEC sys.sp_executesql @sql,N'@file nvarchar(4000)',@file=@backup;
'@
    Invoke-CloneSql $sourceConnection $backupSql @{'@source'=$SourceDatabase;'@backup'=$SourceBackupFile}
    $sourceHeader = Invoke-CloneSql $sourceConnection 'RESTORE HEADERONLY FROM DISK=@backup' @{'@backup'=$SourceBackupFile} -Rows
    if ($sourceHeader.Rows.Count -ne 1 -or $sourceHeader.Rows[0].DatabaseName -ne $SourceDatabase -or -not $sourceHeader.Rows[0].BackupSetGUID) {
        throw 'Identitas backup A tidak valid.'
    }
    if (-not (Test-Path -LiteralPath $TransferSourceFile -PathType Leaf)) { throw 'Backup A tidak terlihat melalui share jaringan.' }
    $sourceHash = (Get-FileHash -LiteralPath $TransferSourceFile -Algorithm SHA256).Hash
    $sourceResolved = [System.IO.Path]::GetFullPath($TransferSourceFile)
    if (-not $sourceResolved.Equals($transferTarget, [System.StringComparison]::OrdinalIgnoreCase)) {
        Copy-Item -LiteralPath $TransferSourceFile -Destination $transferTarget -Force
    }
    if ((Get-FileHash -LiteralPath $transferTarget -Algorithm SHA256).Hash -ne $sourceHash -or
        (Get-FileHash -LiteralPath $TransferSourceFile -Algorithm SHA256).Hash -ne $sourceHash) {
        throw 'Checksum transfer tidak cocok; restore dibatalkan.'
    }
    $localHash = (Get-FileHash -LiteralPath $transferTarget -Algorithm SHA256).Hash
    Invoke-CloneSql $targetConnection 'RESTORE VERIFYONLY FROM DISK=@backup WITH CHECKSUM' @{'@backup'=$TargetBackupFile}
    $targetHeader = Invoke-CloneSql $targetConnection 'RESTORE HEADERONLY FROM DISK=@backup' @{'@backup'=$TargetBackupFile} -Rows
    if ($targetHeader.Rows.Count -ne 1 -or $targetHeader.Rows[0].DatabaseName -ne $SourceDatabase -or
        $targetHeader.Rows[0].BackupSetGUID -ne $sourceHeader.Rows[0].BackupSetGUID) {
        throw 'File yang terlihat oleh SQL Server B bukan backup A terbaru; periksa pemetaan path.'
    }
    $files = Invoke-CloneSql $targetConnection 'RESTORE FILELISTONLY FROM DISK=@backup' @{'@backup'=$TargetBackupFile} -Rows
    $dataFiles = @($files.Rows | Where-Object { $_.Type -eq 'D' })
    $logFiles = @($files.Rows | Where-Object { $_.Type -eq 'L' })
    if ($files.Rows.Count -ne 2 -or $dataFiles.Count -ne 1 -or $logFiles.Count -ne 1) { throw 'Susunan file backup tidak didukung.' }
    if ((Get-FileHash -LiteralPath $transferTarget -Algorithm SHA256).Hash -ne $localHash) { throw 'Backup berubah sebelum restore.' }

    $restoreSql = @'
IF EXISTS(SELECT 1 FROM sys.master_files WHERE physical_name=@backup OR (physical_name IN(@df,@lf) AND database_id<>ISNULL(DB_ID(@target),-1))) THROW 50003,'Lokasi file dimiliki database lain.',1;
DECLARE @sql nvarchar(max);
BEGIN TRY
 IF DB_ID(@target) IS NOT NULL BEGIN
  SET @sql=N'ALTER DATABASE '+QUOTENAME(@target)+N' SET SINGLE_USER WITH ROLLBACK IMMEDIATE'; EXEC(@sql);
 END;
 SET @sql=N'RESTORE DATABASE '+QUOTENAME(@target)+N' FROM DISK=@file WITH MOVE @dataName TO @dataPath, MOVE @logName TO @logPath, CHECKSUM, RECOVERY, REPLACE';
 EXEC sys.sp_executesql @sql,N'@file nvarchar(4000),@dataName sysname,@dataPath nvarchar(4000),@logName sysname,@logPath nvarchar(4000)',@file=@backup,@dataName=@dn,@dataPath=@df,@logName=@ln,@logPath=@lf;
 SET @sql=N'ALTER DATABASE '+QUOTENAME(@target)+N' SET MULTI_USER'; EXEC(@sql);
END TRY
BEGIN CATCH
 IF DB_ID(@target) IS NOT NULL BEGIN SET @sql=N'ALTER DATABASE '+QUOTENAME(@target)+N' SET MULTI_USER'; EXEC(@sql); END;
 THROW;
END CATCH;
'@
    Invoke-CloneSql $targetConnection $restoreSql @{'@target'=$TargetDatabase;'@backup'=$TargetBackupFile;'@dn'=$dataFiles[0].LogicalName;'@df'=$targetData;'@ln'=$logFiles[0].LogicalName;'@lf'=$targetLog}
    Write-Output 'Backup A, transfer jaringan, verifikasi dan restore B berhasil.'
} finally {
    if ($sourceConnection) { $sourceConnection.Dispose() }
    if ($targetConnection) { $targetConnection.Dispose() }
}
