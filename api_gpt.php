<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conexao.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

/* =========================
   CONFIGURAÇÃO
========================= */
$OPENAI_API_KEY = "sk-SUA_CHAVE_AQUI"; // ← coloque sua chave aqui

$cidade = "Igarassu PE";
$limite = 10;

/* =========================
   FUNÇÕES
========================= */
function gerarSlug($titulo) {
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $titulo);
    if ($slug === false) $slug = $titulo;
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

/* =========================
   PROMPT DO BOT
========================= */
$prompt = "
Retorne APENAS JSON válido.
Liste $limite notícias recentes sobre $cidade.

Formato obrigatório:
{
  \"noticias\": [
    {
      \"titulo\": \"\",
      \"resumo\": \"\",
      \"fonte\": \"\",
      \"data\": \"YYYY-MM-DD HH:MM:SS\",
      \"link\": \"\"
    }
  ]
}

Não explique nada. Não escreva texto fora do JSON.
";

/* =========================
   CHAMADA OPENAI
========================= */
$data = [
    "model" => "gpt-3.5-turbo",
    "messages" => [
        ["role" => "system", "content" => "Você é um bot de notícias locais."],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.2
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $OPENAI_API_KEY"
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(["erro" => $error]);
    exit;
}

$result = json_decode($response, true);
$jsonText = $result["choices"][0]["message"]["content"] ?? "";

$newsData = json_decode($jsonText, true);

if (!isset($newsData["noticias"])) {
    http_response_code(500);
    echo json_encode([
        "erro" => "Resposta inválida do ChatGPT",
        "resposta" => $jsonText
    ]);
    exit;
}

/* =========================
   INSERT NO BANCO
========================= */
$stmt = $conn->prepare("
    INSERT IGNORE INTO noticias
    (titulo, slug, texto, noticia_url, fonte, status, publicado_em, hash_unico)
    VALUES (?, ?, ?, ?, ?, 'publicado', ?, ?)
");

$inseridas = 0;

foreach ($newsData["noticias"] as $n) {

    $titulo = trim($n["titulo"]);
    $texto  = trim($n["resumo"]);
    $fonte  = trim($n["fonte"]);
    $link   = strtok(trim($n["link"]), '?');
    $data   = strtotime($n["data"]);

    if (!$titulo || !$link || !$data) continue;

    $slug = gerarSlug($titulo);
    $hash = sha1(mb_strtolower($titulo . $link, 'UTF-8'));
    $publicadoEm = date('Y-m-d H:i:s', $data);

    $stmt->bind_param(
        "sssssss",
        $titulo,
        $slug,
        $texto,
        $link,
        $fonte,
        $publicadoEm,
        $hash
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $inseridas++;
    }
}

$stmt->close();
$conn->close();

/* =========================
   SAÍDA
========================= */
echo json_encode([
    "status" => "ok",
    "cidade" => $cidade,
    "inseridas" => $inseridas,
    "total_recebidas" => count($newsData["noticias"])
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
