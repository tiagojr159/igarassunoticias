<?php
declare(strict_types=1);

use Afaya\EdgeTTS\Service\EdgeTTS;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require __DIR__ . '/../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(['error' => 'Use POST para sintetizar audio.'], 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
  $payload = $_POST;
}

$text = trim((string)($payload['text'] ?? ''));
$voice = trim((string)($payload['voice'] ?? 'pt-BR-AntonioNeural'));

$allowedVoices = [
  'pt-BR-AntonioNeural',
  'pt-BR-FranciscaNeural',
  'pt-BR-ThalitaMultilingualNeural',
];

if ($text === '') {
  jsonResponse(['error' => 'Texto vazio.'], 400);
}

$textLength = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

if ($textLength > 2500) {
  jsonResponse(['error' => 'Texto muito longo.'], 413);
}

if (!in_array($voice, $allowedVoices, true)) {
  $voice = 'pt-BR-AntonioNeural';
}

try {
  $tts = new EdgeTTS();
  $safeText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

  ob_start();
  $tts->synthesize($safeText, $voice, [
    'rate' => '0%',
    'volume' => '0%',
    'pitch' => '0Hz',
  ]);
  ob_end_clean();

  $audio = $tts->toRaw();
} catch (Throwable $e) {
  if (ob_get_level() > 0) {
    ob_end_clean();
  }

  jsonResponse([
    'error' => 'Falha ao gerar voz neural Edge TTS.',
    'detail' => $e->getMessage(),
  ], 502);
}

if ($audio === '') {
  jsonResponse(['error' => 'Audio neural vazio.'], 502);
}

header('Content-Type: audio/mpeg');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($audio));
echo $audio;

function jsonResponse(array $payload, int $status): void
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}
