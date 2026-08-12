<#
.SYNOPSIS
    One mysqldump of the site database, written outside the project, rotated.

.DESCRIPTION
    Written after a real incident: `php artisan migrate:fresh --env=testing`
    wiped `amadcraft_b2b` and 135 leads were lost permanently because no
    backup existed. §15.3 requires scheduled backups with a documented
    restore test; docs/backup.md is that document.

    Three decisions this script encodes:

    · The destination is OUTSIDE the project tree. A backup inside
      `storage/` dies with the folder it is meant to protect — and dies with
      the repo checkout, the failed deploy, and the accidental `rm -rf`.

    · Credentials are read from the project's .env at run time and handed to
      mysqldump through a temporary defaults file, never on the command line
      (§22.9, and a command line is readable by every process on the box).

    · `--result-file` rather than a PowerShell pipe. Redirecting mysqldump
      through `>` or `Out-File` re-encodes the stream — a UTF-16 or
      BOM-prefixed dump restores as mojibake, and you find out on the day you
      need it.

.PARAMETER EnvFile
    The .env to read DB_* from. Defaults to the project root's.

.PARAMETER Destination
    Where dumps are written. Defaults to C:\Backups\amadcraft-b2b.

.PARAMETER Keep
    How many dumps to retain. Older ones are deleted, newest first. Default 7.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup-db.ps1
#>

[CmdletBinding()]
param(
    [string] $EnvFile,
    [string] $Destination = 'C:\Backups\amadcraft-b2b',
    [int]    $Keep = 7
)

$ErrorActionPreference = 'Stop'

# Resolved here, not as a param default: under [CmdletBinding()] Windows
# PowerShell evaluates defaults before $PSScriptRoot is populated, so the
# obvious `Join-Path $PSScriptRoot '..\.env'` default fails on an empty path.
if (-not $EnvFile) { $EnvFile = Join-Path $PSScriptRoot '..\.env' }

function Write-Log {
    param([string] $Message)

    $line = '{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Write-Output $line

    if ($script:LogFile) {
        Add-Content -Path $script:LogFile -Value $line -Encoding utf8
    }
}

# --- Where is mysqldump ---------------------------------------------------
# Laragon does not put its MySQL on PATH, and the version folder changes when
# Laragon updates, so the fallback globs rather than hard-codes.
function Resolve-MysqlDump {
    $onPath = Get-Command mysqldump -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    $candidate = Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysqldump.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1

    if ($candidate) { return $candidate.FullName }

    throw 'mysqldump not found on PATH or under C:\laragon\bin\mysql.'
}

# --- Credentials ----------------------------------------------------------
function Read-EnvFile {
    param([string] $Path)

    if (-not (Test-Path $Path)) { throw "Env file not found: $Path" }

    $values = @{}

    foreach ($line in Get-Content $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }

        $split = $trimmed.IndexOf('=')
        if ($split -lt 1) { continue }

        $key = $trimmed.Substring(0, $split).Trim()
        $value = $trimmed.Substring($split + 1).Trim()

        # .env allows quoting; a quoted empty password must stay empty.
        if ($value.Length -ge 2 -and
            (($value.StartsWith('"') -and $value.EndsWith('"')) -or
             ($value.StartsWith("'") -and $value.EndsWith("'")))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $values[$key] = $value
    }

    return $values
}

# --- Run ------------------------------------------------------------------
if (-not (Test-Path $Destination)) {
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
}

$script:LogFile = Join-Path $Destination 'backup.log'

$defaultsFile = $null
$exitCode = 0

try {
    $dump = Resolve-MysqlDump
    $env_ = Read-EnvFile -Path $EnvFile

    $database = $env_['DB_DATABASE']
    if (-not $database) { throw "DB_DATABASE is not set in $EnvFile" }

    $host_ = if ($env_['DB_HOST']) { $env_['DB_HOST'] } else { '127.0.0.1' }
    $port  = if ($env_['DB_PORT']) { $env_['DB_PORT'] } else { '3306' }
    $user  = if ($env_['DB_USERNAME']) { $env_['DB_USERNAME'] } else { 'root' }
    $pass  = $env_['DB_PASSWORD']

    # Password via a temp defaults file, deleted in `finally` even on failure.
    $defaultsFile = Join-Path $env:TEMP ("amad-dump-{0}.cnf" -f [guid]::NewGuid())
    $cnf = "[client]`r`nuser=$user`r`npassword=$pass`r`nhost=$host_`r`nport=$port`r`n"
    Set-Content -Path $defaultsFile -Value $cnf -Encoding ascii

    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
    $target = Join-Path $Destination ("{0}_{1}.sql" -f $database, $stamp)

    Write-Log "backup start  db=$database  ->  $target"

    # --single-transaction: a consistent snapshot without locking the site out
    #   of its own tables (InnoDB throughout).
    # --routines --events --triggers: everything a restore needs to be the
    #   same database, not just the same rows.
    & $dump "--defaults-file=$defaultsFile" `
        --single-transaction `
        --quick `
        --routines --events --triggers `
        --default-character-set=utf8mb4 `
        --result-file="$target" `
        $database

    if ($LASTEXITCODE -ne 0) { throw "mysqldump exited with $LASTEXITCODE" }

    $size = (Get-Item $target).Length
    if ($size -lt 1024) { throw "dump is suspiciously small ($size bytes): $target" }

    Write-Log ("backup ok     {0:N0} bytes" -f $size)

    # --- Rotation ---------------------------------------------------------
    # Newest $Keep survive. Sorted by name, not LastWriteTime: the timestamp
    # is in the filename, so the order is stable even if a file is touched.
    $all = Get-ChildItem (Join-Path $Destination "$database`_*.sql") | Sort-Object Name -Descending
    $stale = $all | Select-Object -Skip $Keep

    foreach ($file in $stale) {
        Remove-Item $file.FullName -Force
        Write-Log "rotated out   $($file.Name)"
    }

    Write-Log ("retained      {0} of {1} allowed" -f ([math]::Min($all.Count, $Keep)), $Keep)
}
catch {
    Write-Log "BACKUP FAILED $($_.Exception.Message)"
    $exitCode = 1
}
finally {
    if ($defaultsFile -and (Test-Path $defaultsFile)) {
        Remove-Item $defaultsFile -Force
    }
}

exit $exitCode
