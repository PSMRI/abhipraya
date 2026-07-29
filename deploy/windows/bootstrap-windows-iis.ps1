[CmdletBinding(SupportsShouldProcess)]
param(
    [Parameter(Mandatory)] [string] $AppPath,
    [string] $PhpCgiPath,
    [string] $PhpZipPath,
    [string] $PhpInstallPath = 'C:\PHP',
    [string] $SiteName = 'Abhipraya',
    [int] $Port = 80,
    [string] $MySqlInstallerPath,
    [string] $MemuraiInstallerPath,
    [string] $MySqlBinPath,
    [string] $MemuraiBinPath,
    [switch] $InstallIis,
    [switch] $ApplySchema
)

$ErrorActionPreference = 'Stop'
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script from an elevated PowerShell session.'
}
if (-not (Test-Path -LiteralPath $AppPath)) { throw "Application path does not exist: $AppPath" }
if (-not $PhpCgiPath -and -not $PhpZipPath) { throw 'Provide an existing -PhpCgiPath or an approved -PhpZipPath for PHP installation.' }
if ($PhpZipPath -and -not (Test-Path -LiteralPath $PhpZipPath)) { throw "PHP ZIP path does not exist: $PhpZipPath" }
if ($PhpZipPath -and -not $PhpCgiPath) {
    $PhpCgiPath = Join-Path $PhpInstallPath 'php-cgi.exe'
    if ($PSCmdlet.ShouldProcess($PhpInstallPath, 'Extract approved PHP ZIP package')) {
        New-Item -ItemType Directory -Force -Path $PhpInstallPath | Out-Null
        Expand-Archive -LiteralPath $PhpZipPath -DestinationPath $PhpInstallPath -Force
        if (-not (Test-Path -LiteralPath $PhpCgiPath)) { throw "PHP ZIP did not contain php-cgi.exe at $PhpCgiPath" }
        $phpIni = Join-Path $PhpInstallPath 'php.ini'
        if (-not (Test-Path -LiteralPath $phpIni) -and (Test-Path -LiteralPath (Join-Path $PhpInstallPath 'php.ini-production'))) {
            Copy-Item (Join-Path $PhpInstallPath 'php.ini-production') $phpIni
        }
    }
}
if (-not (Test-Path -LiteralPath $PhpCgiPath) -and -not $WhatIfPreference) { throw "PHP CGI executable does not exist: $PhpCgiPath" }

if ($WhatIfPreference) {
    Write-Host "WhatIf: would configure IIS site '$SiteName', PHP FastCGI, application storage ACLs, supplied installers, and approved PHP/MySQL/Memurai PATH entries."
    return
}

if ($InstallIis -and $PSCmdlet.ShouldProcess('Windows Server', 'Install IIS and CGI features')) {
    Install-WindowsFeature Web-Server, Web-CGI, Web-Mgmt-Tools | Out-Null
}

foreach ($installer in @($MySqlInstallerPath, $MemuraiInstallerPath)) {
    if ($installer -and -not (Test-Path -LiteralPath $installer)) { throw "Installer path does not exist: $installer" }
    if ($installer -and $PSCmdlet.ShouldProcess($installer, 'Install MSI package')) {
        Start-Process msiexec.exe -ArgumentList @('/i', ('"' + $installer + '"'), '/qn', '/norestart') -Wait -NoNewWindow
    }
}

function Add-MachinePathEntry([string] $PathEntry) {
    if (-not $PathEntry) { return }
    if (-not (Test-Path -LiteralPath $PathEntry)) { throw "PATH directory does not exist: $PathEntry" }
    $current = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $entries = @($current -split ';' | Where-Object { $_ })
    if ($entries | Where-Object { $_.TrimEnd('\\') -ieq $PathEntry.TrimEnd('\\') }) { return }
    [Environment]::SetEnvironmentVariable('Path', (($entries + $PathEntry) -join ';'), 'Machine')
    Write-Host "Added machine PATH entry: $PathEntry"
}

Add-MachinePathEntry (Split-Path -Parent $PhpCgiPath)
Add-MachinePathEntry $MySqlBinPath
Add-MachinePathEntry $MemuraiBinPath

Import-Module WebAdministration
if (-not (Test-Path "IIS:\AppPools\$SiteName")) { New-WebAppPool -Name $SiteName | Out-Null }
Set-ItemProperty "IIS:\AppPools\$SiteName" -Name managedRuntimeVersion -Value ''
Set-ItemProperty "IIS:\AppPools\$SiteName" -Name processModel.identityType -Value ApplicationPoolIdentity
if (-not (Test-Path "IIS:\Sites\$SiteName")) {
    New-Website -Name $SiteName -PhysicalPath $AppPath -Port $Port -ApplicationPool $SiteName | Out-Null
}

$handlerName = 'Abhipraya-PHP-FastCGI'
Remove-WebConfigurationProperty -PSPath 'MACHINE/WEBROOT/APPHOST' -Filter "system.webServer/handlers" -Name '.' -AtElement @{name=$handlerName} -ErrorAction SilentlyContinue
Add-WebConfiguration -PSPath 'MACHINE/WEBROOT/APPHOST' -Filter 'system.webServer/handlers' -Value @{name=$handlerName;path='*.php';verb='*';modules='FastCgiModule';scriptProcessor=$PhpCgiPath;resourceType='Either';requireAccess='Script'}

$storage = Join-Path $AppPath 'api\storage'
New-Item -ItemType Directory -Force -Path $storage, (Join-Path $storage 'sessions'), (Join-Path $storage 'events') | Out-Null
$acl = Get-Acl $storage
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule("IIS AppPool\$SiteName", 'Modify', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
$acl.SetAccessRule($rule); Set-Acl -LiteralPath $storage -AclObject $acl

Write-Host 'IIS application configured. Enable mysqli, openssl and redis extensions in php.ini, then create a protected .env with DB_* and ABHIPRAYA_SESSION_HANDLER=redis.'
Write-Host 'Configure Memurai with private binding, password, memory limit and PHP redis session.save_path before enabling shared sessions.'
Write-Host 'Add the HTTPS certificate binding manually or through the organisation certificate automation.'
Write-Host 'Open a new PowerShell/IIS process after PATH changes so PHP, mysql.exe and memurai-cli.exe are discoverable.'

if ($ApplySchema) {
    $mysql = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if (-not $mysql) { throw 'mysql.exe is required on PATH to apply the schema.' }
    Write-Warning 'Apply the schema only to a new empty database. Run the command from the database setup guide with protected credentials.'
}
