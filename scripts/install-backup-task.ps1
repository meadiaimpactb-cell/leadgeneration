<#
.SYNOPSIS
    Register (or re-register) the daily database backup as a Windows task.

.DESCRIPTION
    Kept in the repo rather than clicked together in Task Scheduler, for two
    reasons: it is part of the handover (§19 — the client must be able to
    stand this up on their own box), and because the defaults are wrong in a
    way that fails silently.

    `schtasks /Create` produces a task with:

        DisallowStartIfOnBatteries = True
        StopIfGoingOnBatteries     = True
        StartWhenAvailable         = False

    On a laptop that means the 03:00 backup does not run on battery, and a
    machine that was asleep at 03:00 never catches up. The task reports no
    error — it simply never fires, which is the same failure as having no
    backup at all, only harder to notice. All three are corrected here.

.PARAMETER At
    Daily run time, 24h. Default 03:00.

.PARAMETER TaskName
    Default 'AmadCraft B2B - daily DB backup'.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\install-backup-task.ps1
#>

[CmdletBinding()]
param(
    [string] $At = '03:00',
    [string] $TaskName = 'AmadCraft B2B - daily DB backup',
    [string] $ScriptPath
)

$ErrorActionPreference = 'Stop'

if (-not $ScriptPath) { $ScriptPath = Join-Path $PSScriptRoot 'backup-db.ps1' }

if (-not (Test-Path $ScriptPath)) { throw "Backup script not found: $ScriptPath" }

$ScriptPath = (Resolve-Path $ScriptPath).Path

$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument ('-NoProfile -ExecutionPolicy Bypass -File "{0}"' -f $ScriptPath)

$trigger = New-ScheduledTaskTrigger -Daily -At $At

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1) `
    -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'mysqldump of amadcraft_b2b to C:\Backups\amadcraft-b2b, 7 kept. See docs/backup.md.' `
    -Force | Out-Null

$info = Get-ScheduledTask -TaskName $TaskName

Write-Output "registered: $TaskName"
Write-Output ("  runs     : daily at {0}" -f $At)
Write-Output ("  command  : {0}" -f $ScriptPath)
Write-Output ("  next run : {0}" -f (Get-ScheduledTaskInfo -TaskName $TaskName).NextRunTime)
Write-Output ''
Write-Output 'settings that had to be corrected from the schtasks defaults:'
$info.Settings | Select-Object DisallowStartIfOnBatteries, StopIfGoingOnBatteries, StartWhenAvailable |
    Format-List | Out-String | Write-Output
