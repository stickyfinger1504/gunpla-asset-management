param(
    [string]$BackupFile = ""
)

$ErrorActionPreference = "Stop"
$BackupDir = Join-Path $PSScriptRoot "backups"
$ContainerName = "gunpla-asset-management-db-1"

function Test-ContainerRunning {
    $running = docker ps --filter "name=$ContainerName" --filter "status=running" -q 2>$null
    if (-not $running) {
        Write-Host "❌ DB container '$ContainerName' is not running." -ForegroundColor Red
        Write-Host "   Start it with: docker compose up -d" -ForegroundColor Yellow
        exit 1
    }
}

function Get-RootPassword {
    $envFile = Join-Path $PSScriptRoot ".env"
    if (Test-Path $envFile) {
        $line = Get-Content $envFile | Where-Object { $_ -match "^ROOT_PASSWORD=" }
        if ($line) { return ($line -split "=", 2)[1].Trim() }
    }
    return Read-Host "Enter MySQL root password"
}

if (-not $BackupFile) {
    $files = Get-ChildItem -Path $BackupDir -Filter "*.sql.gz" -ErrorAction SilentlyContinue |
             Sort-Object LastWriteTime -Descending

    if (-not $files) {
        Write-Host "❌ No backups found in '$BackupDir'." -ForegroundColor Red
        exit 1
    }

    Write-Host ""
    Write-Host "Available backups (newest first):" -ForegroundColor Cyan
    Write-Host ""
    for ($i = 0; $i -lt $files.Count; $i++) {
        $age = (Get-Date) - $files[$i].LastWriteTime
        $ageStr = if ($age.TotalHours -lt 24) { "today" }
                  elseif ($age.TotalDays -lt 2) { "yesterday" }
                  else { "$([int]$age.TotalDays) days ago" }
        Write-Host ("  [{0}] {1}  ({2})" -f ($i + 1), $files[$i].Name, $ageStr)
    }

    Write-Host ""
    $choice = Read-Host "Enter number to restore (or Q to quit)"
    if ($choice -eq "Q" -or $choice -eq "q") { exit 0 }

    $idx = [int]$choice - 1
    if ($idx -lt 0 -or $idx -ge $files.Count) {
        Write-Host "❌ Invalid selection." -ForegroundColor Red
        exit 1
    }

    $BackupFile = $files[$idx].FullName
}

$fileName = Split-Path $BackupFile -Leaf
Write-Host ""
Write-Host "⚠️  This will OVERWRITE the current database with:" -ForegroundColor Yellow
Write-Host "   $fileName" -ForegroundColor White
Write-Host ""
$confirm = Read-Host "Are you sure? (yes / N)"
if ($confirm -ne "yes") {
    Write-Host "Restore cancelled." -ForegroundColor Gray
    exit 0
}

Test-ContainerRunning
$rootPass = Get-RootPassword

Write-Host ""
Write-Host "Restoring $fileName..." -ForegroundColor Cyan

$envFile = Join-Path $PSScriptRoot ".env"
$dbName = "gunpladb"
if (Test-Path $envFile) {
    $line = Get-Content $envFile | Where-Object { $_ -match "^DATABASE=" }
    if ($line) { $dbName = ($line -split "=", 2)[1].Trim() }
}

try {
    # Decompress on the Windows host using .NET GZipStream
    $tempSql = [System.IO.Path]::GetTempFileName() + ".sql"

    $inputStream  = [System.IO.File]::OpenRead($BackupFile)
    $gzipStream   = [System.IO.Compression.GZipStream]::new($inputStream, [System.IO.Compression.CompressionMode]::Decompress)
    $outputStream = [System.IO.File]::Create($tempSql)
    $gzipStream.CopyTo($outputStream)
    $outputStream.Close()
    $gzipStream.Close()
    $inputStream.Close()

    # Copy SQL into container and run it there (more reliable than stdin pipe on Windows)
    docker cp $tempSql "${ContainerName}:/tmp/restore.sql"
    docker exec $ContainerName mysql -u root -p"$rootPass" $dbName -e "source /tmp/restore.sql"
    docker exec $ContainerName rm /tmp/restore.sql

    Remove-Item $tempSql -ErrorAction SilentlyContinue

    Write-Host ""
    Write-Host "✅ Restore complete." -ForegroundColor Green
} catch {
    Write-Host ""
    Write-Host "❌ Restore failed: $_" -ForegroundColor Red
    Remove-Item $tempSql -ErrorAction SilentlyContinue
    exit 1
}
