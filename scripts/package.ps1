$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$themePath = Join-Path $projectRoot 'theme/supreme-shop'
$outputDirectory = Join-Path $projectRoot 'dist'
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
$archivePath = Join-Path $outputDirectory 'supreme-shop-wordpress.zip'
Compress-Archive -LiteralPath $themePath -DestinationPath $archivePath -Force
Write-Output "Tema listo: $archivePath"
