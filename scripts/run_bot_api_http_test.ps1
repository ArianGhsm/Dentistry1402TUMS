[CmdletBinding()]
param([int]$Port = 18764)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$temporaryRoot = Join-Path $root (".codex-local\bot-api-http-" + (Get-Date -Format "yyyyMMddHHmmss"))
$storageRoot = Join-Path $temporaryRoot "storage"
$serverRoot = Join-Path $temporaryRoot "server-only"
New-Item -ItemType Directory -Force -Path $storageRoot, $serverRoot | Out-Null

$secretHex = "ab" * 32
$env:DENT_STORAGE_ROOT = $storageRoot
$env:DENT_SERVER_ONLY_ROOT = $serverRoot
$env:DENT_BOT_SERVICE_SECRET = $secretHex
$env:DENT_AUTH_SECRET_KEY = [Convert]::ToBase64String([byte[]](1..32))
$env:DENT_SITE_PUBLIC_URL = "https://example.test"
$env:DENT_APP_ENV = "test"
$env:DENT_STUDENT_ASSISTANT_TEST_CONNECTOR = "1"
$env:DENT_STUDENT_ASSISTANT_TEST_CAPTCHA_ANSWER = "A7K2P"
$env:DENT_BOT_NOTIFICATIONS_SINCE = (Get-Date).AddMinutes(-5).ToUniversalTime().ToString("o")
$env:DENT_TEST_BOT_API_URL = "http://127.0.0.1:$Port/api/bot_api.php?action=service"
$env:DENT_TEST_BOT_API_SECRET_HEX = $secretHex
$env:DENT_TEST_BOT_STORE_PATH = Join-Path $storageRoot "integrations\bot_links.json"

& php -d "extension_dir=C:\php\ext" -d "extension=php_openssl.dll" (Join-Path $PSScriptRoot "setup_bot_api_http_fixture.php") | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Bot API HTTP fixture setup failed." }

$process = Start-Process -FilePath php -ArgumentList @(
    "-d", "extension_dir=C:\php\ext",
    "-d", "extension=php_openssl.dll",
    "-S", "127.0.0.1:$Port"
) -WorkingDirectory (Join-Path $root "public_html") `
    -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $temporaryRoot "php.out") `
    -RedirectStandardError (Join-Path $temporaryRoot "php.err")
try {
    Start-Sleep -Seconds 2
    & python (Join-Path $PSScriptRoot "test_bot_api_http.py")
    if ($LASTEXITCODE -ne 0) { throw "Bot API HTTP integration test failed." }
}
finally {
    if ($process -and -not $process.HasExited) {
        Stop-Process -Id $process.Id -Force
    }
    Remove-Item Env:DENT_BOT_NOTIFICATIONS_SINCE -ErrorAction SilentlyContinue
    Remove-Item Env:DENT_STUDENT_ASSISTANT_TEST_CONNECTOR -ErrorAction SilentlyContinue
    Remove-Item Env:DENT_STUDENT_ASSISTANT_TEST_CAPTCHA_ANSWER -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
