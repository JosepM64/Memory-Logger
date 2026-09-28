# Memory Logger Pro — deploy FTP simple (13.4.3)
# Uso: .\deploy.ps1 -Remote "/wp-content/plugins/memory-logger-pro"
param([string]$Remote = "/wp-content/plugins/memory-logger-pro")
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$ignore = @()
if (Test-Path "$root\.distignore") { $ignore = Get-Content "$root\.distignore" | Where-Object { $_ -and -not $_.StartsWith("#") } }
Get-ChildItem -Recurse -File $root | Where-Object {
  $rel = $_.FullName.Substring($root.Length + 1) -replace "\\","/"
  -not ($ignore | Where-Object { $rel -like $_ })
} | ForEach-Object { $_.FullName }
Write-Host "Puja per FTP els fitxers llistats a $Remote (canvi versió obliga)."
