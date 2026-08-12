<#
.SYNOPSIS
    Restore a mysqldump into a database, and prove the restore is faithful.

.DESCRIPTION
    A backup nobody has restored is a guess, not a backup (§15.3 asks for a
    *documented restore test*). This script is that test, and it is the same
    script used in a real emergency — a rehearsal on a different path than
    the real thing proves nothing.

    It refuses any target whose name does not end in `_test` unless -Force is
    given. That mirrors the guard in AppServiceProvider, deliberately: the
    incident that started all of this was a destructive command pointed at
    the wrong database, and one guard in one place is one guard too few.

.PARAMETER Dump
    Path to the .sql file. Defaults to the newest dump in -Destination.

.PARAMETER Database
    Target database. Defaults to DB_DATABASE from .env.testing.

.PARAMETER Source
    Database the dump came from. Used only for the row-count comparison that
    proves the restore. Defaults to DB_DATABASE from .env. Pass '' to skip.

.PARAMETER Force
    Allow a target that does not end in `_test`. Type it deliberately.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\restore-db.ps1
#>

[CmdletBinding()]
param(
    [string] $Dump,
    [string] $Database,
    [string] $Source,
    [string] $Destination = 'C:\Backups\amadcraft-b2b',
    [string] $EnvFile,
    [string] $TestEnvFile,
    [switch] $Force
)

$ErrorActionPreference = 'Stop'

# See backup-db.ps1: $PSScriptRoot is not populated yet when [CmdletBinding()]
# evaluates parameter defaults.
if (-not $EnvFile) { $EnvFile = Join-Path $PSScriptRoot '..\.env' }
if (-not $TestEnvFile) { $TestEnvFile = Join-Path $PSScriptRoot '..\.env.testing' }

function Resolve-MysqlClient {
    param([string] $Name)

    $onPath = Get-Command $Name -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    $candidate = Get-ChildItem "C:\laragon\bin\mysql\*\bin\$Name.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1

    if ($candidate) { return $candidate.FullName }

    throw "$Name not found on PATH or under C:\laragon\bin\mysql."
}

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

        if ($value.Length -ge 2 -and
            (($value.StartsWith('"') -and $value.EndsWith('"')) -or
             ($value.StartsWith("'") -and $value.EndsWith("'")))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $values[$key] = $value
    }

    return $values
}

$mysql = Resolve-MysqlClient -Name 'mysql'
$live = Read-EnvFile -Path $EnvFile

if (-not $Database) {
    $testing = Read-EnvFile -Path $TestEnvFile
    $Database = $testing['DB_DATABASE']
}

if (-not $Database) { throw 'No target database resolved.' }

if (-not $Database.EndsWith('_test') -and -not $Force) {
    throw "Refusing to restore over '$Database': the name does not end in _test. Pass -Force if you mean it."
}

if (-not $PSBoundParameters.ContainsKey('Source')) {
    $Source = $live['DB_DATABASE']
}

if (-not $Dump) {
    $Dump = (Get-ChildItem (Join-Path $Destination '*.sql') |
        Sort-Object Name -Descending |
        Select-Object -First 1).FullName
}

if (-not $Dump -or -not (Test-Path $Dump)) { throw "No dump file found (looked in $Destination)." }

$defaultsFile = Join-Path $env:TEMP ("amad-restore-{0}.cnf" -f [guid]::NewGuid())

try {
    $host_ = if ($live['DB_HOST']) { $live['DB_HOST'] } else { '127.0.0.1' }
    $port  = if ($live['DB_PORT']) { $live['DB_PORT'] } else { '3306' }
    $user  = if ($live['DB_USERNAME']) { $live['DB_USERNAME'] } else { 'root' }
    $pass  = $live['DB_PASSWORD']

    $cnf = "[client]`r`nuser=$user`r`npassword=$pass`r`nhost=$host_`r`nport=$port`r`n"
    Set-Content -Path $defaultsFile -Value $cnf -Encoding ascii

    Write-Output "restoring $Dump"
    Write-Output "      into $Database"

    # Dropped and recreated, not restored on top: leftover tables that the
    # dump does not mention would otherwise survive and make the restore look
    # richer than the backup actually is.
    $reset = "DROP DATABASE IF EXISTS ``$Database``; CREATE DATABASE ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $reset | & $mysql "--defaults-file=$defaultsFile"
    if ($LASTEXITCODE -ne 0) { throw "failed to recreate $Database" }

    # `-e source ...` rather than a pipe: same encoding reasoning as the dump.
    & $mysql "--defaults-file=$defaultsFile" --default-character-set=utf8mb4 $Database -e "source $($Dump -replace '\\', '/')"
    if ($LASTEXITCODE -ne 0) { throw 'restore failed' }

    Write-Output ''
    Write-Output 'restored. verifying:'
    Write-Output ''

    $countSql = @"
SELECT t.table_name AS tbl,
       (SELECT COUNT(*) FROM information_schema.columns c
         WHERE c.table_schema = t.table_schema AND c.table_name = t.table_name) AS cols
  FROM information_schema.tables t
 WHERE t.table_schema = '$Database' AND t.table_type = 'BASE TABLE'
 ORDER BY t.table_name;
"@

    $tables = $countSql | & $mysql "--defaults-file=$defaultsFile" --batch --skip-column-names |
        ForEach-Object { ($_ -split "`t")[0] } |
        Where-Object { $_ }

    Write-Output ("tables restored: {0}" -f $tables.Count)

    if ($Source) {
        # The comparison that makes this a test rather than a demonstration:
        # every table in the source, row for row, against the restore.
        #
        # These are printed even at zero rows. `leads` is the site's one
        # metric (§1) and the table the incident destroyed; "leads restored"
        # has to be readable in the output, and "0 rows in both" is itself
        # worth knowing rather than hiding among the tables with content.
        $critical = @('leads', 'crm_sync_logs', 'users', 'media', 'settings', 'activity_log')

        $mismatch = 0
        $checked = 0
        $rows = @()

        foreach ($table in $tables) {
            $q = "SELECT (SELECT COUNT(*) FROM ``$Source``.``$table``), (SELECT COUNT(*) FROM ``$Database``.``$table``);"
            $line = $q | & $mysql "--defaults-file=$defaultsFile" --batch --skip-column-names

            if ($LASTEXITCODE -ne 0) { throw "count failed for $table" }

            $parts = $line -split "`t"
            $checked++

            if ($parts[0] -ne $parts[1]) {
                $mismatch++
                Write-Output ("  MISMATCH  {0,-32} source={1} restored={2}" -f $table, $parts[0], $parts[1])
            }
            elseif ([int]$parts[0] -gt 0 -or $critical -contains $table) {
                $rows += ("  ok        {0,-32} {1} rows" -f $table, $parts[0])
            }
        }

        $rows | ForEach-Object { Write-Output $_ }

        Write-Output ''
        Write-Output ("compared {0} tables against {1}: {2}" -f $checked, $Source, $(if ($mismatch -eq 0) { 'all row counts match' } else { "$mismatch MISMATCHED" }))

        # --- Arabic integrity -------------------------------------------
        # Row counts cannot see mojibake: a dump re-encoded on the way out
        # restores the right number of rows full of question marks, and the
        # count comparison passes. This hashes the Arabic text itself on both
        # sides. It is the check that would have caught the encoding mistake
        # backup-db.ps1's `--result-file` exists to avoid.
        $hashes = @()

        foreach ($db in @($Source, $Database)) {
            $q = "SET SESSION group_concat_max_len = 16000000; SELECT MD5(GROUP_CONCAT(heading, '~', COALESCE(body, '') ORDER BY id SEPARATOR '|')) FROM ``$db``.section_translations WHERE locale = 'ar';"
            $hashes += ($q | & $mysql "--defaults-file=$defaultsFile" --default-character-set=utf8mb4 --batch --skip-column-names)
        }

        Write-Output ''
        Write-Output ("arabic md5 source  : {0}" -f $hashes[0])
        Write-Output ("arabic md5 restored: {0}" -f $hashes[1])

        if ($hashes[0] -ne $hashes[1]) {
            Write-Output 'ARABIC TEXT DIFFERS — the restore is not faithful.'
            $mismatch++
        }
        else {
            Write-Output 'arabic text identical (section_translations.heading + body, locale=ar)'
        }

        if ($mismatch -gt 0) { exit 1 }
    }
}
finally {
    if (Test-Path $defaultsFile) { Remove-Item $defaultsFile -Force }
}

exit 0
