#Requires -Version 3.0
<#
.SYNOPSIS
  Deactivate McAfee / Trellix real-time antivirus on this Windows host.

.DESCRIPTION
  Stops McAfee/Trellix on-access / threat-prevention services, disables their
  automatic start, turns off scan scheduled tasks, and sets documented
  on-access "start disabled" registry flags when those keys already exist.

  Does not unload kernel trust drivers (mfevtp / MFEVTPS). Disabling those
  can prevent Windows from booting.

  Run as local Administrator. For AWX/Ansible this script is executed over
  WinRM by roles/mcafee.

.PARAMETER Strict
  When true, exit 2 if McAfee is still providing real-time protection.

.PARAMETER DisableStartup
  Disable automatic start for antivirus engine services (default: true).

.PARAMETER DisableFirewall
  Also stop McAfee/Trellix firewall services (default: false).

.PARAMETER DisableWebAdvisor
  Also stop McAfee WebAdvisor services (default: false).

.PARAMETER StopAgent
  Stop the McAfee/Trellix management agent so a policy refresh cannot
  immediately turn protection back on (default: true).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\deactivate-mcafee.ps1
#>
[CmdletBinding()]
param(
    [bool]$Strict = $true,
    [bool]$DisableStartup = $true,
    [bool]$DisableFirewall = $false,
    [bool]$DisableWebAdvisor = $false,
    [bool]$StopAgent = $true
)

$ErrorActionPreference = 'Continue'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-NameMatch {
    param(
        [string]$Name,
        [string[]]$Patterns
    )
    foreach ($pattern in $Patterns) {
        if ($Name -match $pattern) { return $true }
    }
    return $false
}

function Get-McafeeSecurityCenter {
    $entries = @()
    $products = @()
    try {
        $products = @(Get-CimInstance -Namespace root/SecurityCenter2 -ClassName AntivirusProduct -ErrorAction Stop)
    }
    catch {
        try {
            $products = @(Get-WmiObject -Namespace root/SecurityCenter2 -Class AntivirusProduct -ErrorAction Stop)
        }
        catch {
            return @()
        }
    }

    foreach ($av in $products) {
        if ($av.displayName -notmatch 'McAfee|Trellix') { continue }
        $state = [int]$av.productState
        $entries += [ordered]@{
            displayName  = [string]$av.displayName
            productState = $state
            enabled      = (($state -band 0x1000) -eq 0x1000)
        }
    }
    return $entries
}

$neverTouchNames = @(
    '^mfevtp$',
    '^MFEVTPS$',
    '^mfehidk$'
)

$avNamePatterns = @(
    '^McShield$',
    '^MCSHIELD$',
    '^mfetp$',
    '^MFETP$',
    '^mfeatp$',
    '^MFEATP$',
    '^McAfee Engine Service$'
)

$avDisplayPatterns = @(
    'McShield',
    'Threat Prevention',
    'Adaptive Threat',
    'On-Access',
    'On Access Scanner',
    'Real-Time Scanning',
    'Real Time Scanning',
    'AntiVirus Engine',
    'Antivirus Engine',
    'Antivirus',
    'Anti-Virus',
    'VirusScan'
)

$helperNamePatterns = @(
    '^mfemms$',
    '^MFEMMS$',
    '^McAfeeFramework$'
)

$agentNamePatterns = @(
    '^masvc$',
    '^MASVC$',
    '^macmnsvc$',
    '^MACMNSVC$',
    '^McAfeeFramework$'
)

$firewallNamePatterns = @(
    '^mfefire$',
    '^MFEFIRE$',
    '^mfefw$',
    '^MFEFW$'
)

$webAdvisorPatterns = @(
    'WebAdvisor',
    'ModuleCore',
    'SiteAdvisor'
)

$oasRegistryKeys = @(
    @{
        Path = 'HKLM:\SOFTWARE\McAfee\SystemCore\VSCore\On Access Scanner\McShield\Configuration'
        Name = 'bStartDisabled'
    },
    @{
        Path = 'HKLM:\SOFTWARE\Wow6432Node\McAfee\SystemCore\VSCore\On Access Scanner\McShield\Configuration'
        Name = 'bStartDisabled'
    },
    @{
        Path = 'HKLM:\SOFTWARE\McAfee\AVSolution\MCSHIELD\CONFIGURATION'
        Name = 'bStartDisabled'
    },
    @{
        Path = 'HKLM:\SOFTWARE\Network Associates\TVD\Shared Components\On Access Scanner\mcshield\Configuration'
        Name = 'bStartDisabled'
    }
)

$report = [ordered]@{
    found                 = $false
    products              = @()
    install_paths         = @()
    security_center       = @()
    services_found        = @()
    stopped               = @()
    disabled_startup      = @()
    stop_failed           = @()
    registry_updated      = @()
    tasks_disabled        = @()
    still_protected       = $false
    changed               = $false
    administrator         = (Test-IsAdministrator)
    message               = ''
}

try {
    if (-not $report.administrator) {
        $report.message = 'Administrator rights are required to deactivate McAfee.'
        if ($Strict) { throw $report.message }
    }

    $uninstallPaths = @(
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*'
    )
    $products = @(
        Get-ItemProperty -Path $uninstallPaths -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -match 'McAfee|Trellix' } |
            Sort-Object DisplayName -Unique |
            ForEach-Object {
                [ordered]@{
                    displayName = [string]$_.DisplayName
                    version     = [string]$_.DisplayVersion
                    publisher   = [string]$_.Publisher
                }
            }
    )
    $report.products = $products

    $knownPaths = @(
        'C:\Program Files\McAfee',
        'C:\Program Files (x86)\McAfee',
        'C:\Program Files\Trellix',
        'C:\Program Files (x86)\Trellix',
        'C:\Program Files\Common Files\McAfee'
    )
    $report.install_paths = @($knownPaths | Where-Object { Test-Path -LiteralPath $_ })

    $report.security_center = @(Get-McafeeSecurityCenter)

    $allServices = @(Get-Service -ErrorAction SilentlyContinue | Where-Object {
            $_.Name -match 'mcafee|trellix|mcshield|mfemms|mfevtp|mfefire|mfetp|mfeatp|mfeesp|masvc|macmnsvc|homenetsvc' -or
            $_.DisplayName -match 'McAfee|Trellix'
        })

    $report.services_found = @(
        $allServices | ForEach-Object {
            [ordered]@{
                name        = $_.Name
                displayName = $_.DisplayName
                status      = $_.Status.ToString()
                startType   = $_.StartType.ToString()
            }
        }
    )

    $report.found = (
        $report.products.Count -gt 0 -or
        $report.install_paths.Count -gt 0 -or
        $report.security_center.Count -gt 0 -or
        $report.services_found.Count -gt 0
    )

    if (-not $report.found) {
        $report.message = 'McAfee / Trellix was not found on this host.'
    }
    else {
        foreach ($key in $oasRegistryKeys) {
            if (-not (Test-Path -LiteralPath $key.Path)) { continue }
            try {
                $current = (Get-ItemProperty -LiteralPath $key.Path -Name $key.Name -ErrorAction SilentlyContinue).($key.Name)
                if ($current -ne 1) {
                    New-ItemProperty -LiteralPath $key.Path -Name $key.Name -PropertyType DWord -Value 1 -Force | Out-Null
                    $report.registry_updated += "$($key.Path)\$($key.Name)"
                    $report.changed = $true
                }
            }
            catch {
                $report.stop_failed += [ordered]@{
                    name  = "$($key.Path)\$($key.Name)"
                    error = $_.Exception.Message
                }
            }
        }

        try {
            $tasks = @(
                Get-ScheduledTask -ErrorAction SilentlyContinue |
                    Where-Object {
                        $_.TaskName -match 'McAfee|Trellix' -or
                        $_.TaskPath -match 'McAfee|Trellix'
                    }
            )
            foreach ($task in $tasks) {
                if ($task.State -eq 'Disabled') { continue }
                try {
                    Disable-ScheduledTask -TaskName $task.TaskName -TaskPath $task.TaskPath -ErrorAction Stop | Out-Null
                    $report.tasks_disabled += "$($task.TaskPath)$($task.TaskName)"
                    $report.changed = $true
                }
                catch {
                    $report.stop_failed += [ordered]@{
                        name  = "$($task.TaskPath)$($task.TaskName)"
                        error = $_.Exception.Message
                    }
                }
            }
        }
        catch {
            # Scheduled task cmdlets are unavailable on some older hosts.
        }

        $targets = @()
        foreach ($svc in $allServices) {
            if (Test-NameMatch -Name $svc.Name -Patterns $neverTouchNames) { continue }

            $isAv = (Test-NameMatch -Name $svc.Name -Patterns $avNamePatterns) -or
                (Test-NameMatch -Name $svc.DisplayName -Patterns $avDisplayPatterns)
            $isHelper = Test-NameMatch -Name $svc.Name -Patterns $helperNamePatterns
            $isAgent = $StopAgent -and (Test-NameMatch -Name $svc.Name -Patterns $agentNamePatterns)
            $isFirewall = $DisableFirewall -and (
                (Test-NameMatch -Name $svc.Name -Patterns $firewallNamePatterns) -or
                ($svc.DisplayName -match 'Firewall')
            )
            $isWebAdvisor = $DisableWebAdvisor -and (
                (Test-NameMatch -Name $svc.Name -Patterns $webAdvisorPatterns) -or
                (Test-NameMatch -Name $svc.DisplayName -Patterns $webAdvisorPatterns)
            )

            if ($isAv -or $isHelper -or $isAgent -or $isFirewall -or $isWebAdvisor) {
                $targets += [pscustomobject]@{
                    Service       = $svc
                    DisableStart  = [bool]($DisableStartup -and $isAv)
                }
            }
        }

        # Stop engine services before the controller so they are less likely to be restarted.
        $targets = @(
            $targets | Sort-Object { if ($_.DisableStart) { 0 } else { 1 } }, { $_.Service.Name }
        )

        for ($attempt = 1; $attempt -le 2; $attempt++) {
            foreach ($target in $targets) {
                $svc = Get-Service -Name $target.Service.Name -ErrorAction SilentlyContinue
                if ($null -eq $svc) { continue }

                if ($target.DisableStart -and $svc.StartType -ne 'Disabled') {
                    try {
                        Set-Service -Name $svc.Name -StartupType Disabled -ErrorAction Stop
                        if ($report.disabled_startup -notcontains $svc.Name) {
                            $report.disabled_startup += $svc.Name
                            $report.changed = $true
                        }
                    }
                    catch {
                        $report.stop_failed += [ordered]@{
                            name  = $svc.Name
                            error = "Could not disable startup: $($_.Exception.Message)"
                        }
                    }
                }

                if ($svc.Status -ne 'Stopped') {
                    try {
                        Stop-Service -Name $svc.Name -Force -ErrorAction Stop
                        if ($report.stopped -notcontains $svc.Name) {
                            $report.stopped += $svc.Name
                            $report.changed = $true
                        }
                    }
                    catch {
                        $already = $report.stop_failed | Where-Object { $_.name -eq $svc.Name }
                        if (-not $already) {
                            $report.stop_failed += [ordered]@{
                                name  = $svc.Name
                                error = $_.Exception.Message
                            }
                        }
                    }
                }
            }

            if ($attempt -eq 1) {
                Start-Sleep -Seconds 3
            }
        }

        $stillRunningAv = @()
        foreach ($svc in $allServices) {
            $isAv = (Test-NameMatch -Name $svc.Name -Patterns $avNamePatterns) -or
                (Test-NameMatch -Name $svc.DisplayName -Patterns $avDisplayPatterns)
            if (-not $isAv) { continue }
            $fresh = Get-Service -Name $svc.Name -ErrorAction SilentlyContinue
            if ($fresh -and $fresh.Status -ne 'Stopped') {
                $stillRunningAv += $fresh.Name
            }
        }

        $report.security_center = @(Get-McafeeSecurityCenter)

        $wscStillOn = @($report.security_center | Where-Object { $_.enabled }).Count -gt 0
        $report.still_protected = ($stillRunningAv.Count -gt 0)

        if ($report.still_protected) {
            $report.message = @(
                'McAfee / Trellix real-time protection is still running.'
                "Active engine service(s): $($stillRunningAv -join ', ')."
                'Self-protection is likely blocking the stop.'
                'On the PC, open McAfee → My Protection → Real-Time Scanning → Turn off (Never / until I turn it back on),'
                'or in VirusScan Console disable Access Protection → Prevent McAfee services from being stopped, then rerun.'
                'If the host is ePO/Trellix-managed, also assign a policy that turns off on-access scanning.'
            ) -join ' '
        }
        elseif ($wscStillOn -and $report.stopped.Count -eq 0 -and $report.disabled_startup.Count -eq 0) {
            $report.still_protected = $true
            $report.message = 'Windows Security Center still reports McAfee as enabled. Turn off Real-Time Scanning in the McAfee app, then rerun.'
        }
        else {
            $stoppedList = if ($report.stopped.Count -gt 0) { $report.stopped -join ', ' } else { 'none (already stopped)' }
            $report.message = "McAfee / Trellix real-time antivirus deactivated. Stopped: $stoppedList."
        }
    }
}
catch {
    if (-not $report.message) {
        $report.message = $_.Exception.Message
    }
    if (-not $report.still_protected -and $Strict) {
        $report.still_protected = $true
    }
}

$json = $report | ConvertTo-Json -Depth 8 -Compress
Write-Output $json

$runningUnderAnsible = [bool](Get-Variable -Name Ansible -Scope Global -ErrorAction SilentlyContinue)
if ($runningUnderAnsible) {
    $Ansible.Result = $report
    $Ansible.Changed = [bool]$report.changed
    exit 0
}

if ($Strict -and $report.still_protected) {
    exit 2
}
if (-not $report.administrator -and $Strict) {
    exit 1
}
exit 0
