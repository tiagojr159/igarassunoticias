<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ======================================================
   CONEXÃO ROBUSTA (INDEPENDE DO conexao.php)
====================================================== */
$conn = null;

// tenta carregar conexao.php (se existir)
$conexaoPath = __DIR__ . '/conexao.php';
if (file_exists($conexaoPath)) {
    $ret = require $conexaoPath;

    // caso 1: conexao.php define $conn
    if (isset($conn) && $conn instanceof mysqli) {
        // ok
    }
    // caso 2: conexao.php retorna mysqli
    elseif ($ret instanceof mysqli) {
        $conn = $ret;
    }
    // caso 3: conexao.php define $mysqli
    elseif (isset($mysqli) && $mysqli instanceof mysqli) {
        $conn = $mysqli;
    }
}

// fallback: cria conexão aqui
if (!$conn instanceof mysqli) {

    // 🔴 AJUSTE AQUI SE NECESSÁRIO
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'igarassunoticias';

    $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_errno) {
        die('Falha ao conectar ao banco: ' . $conn->connect_error);
    }
}

$conn->set_charset('utf8mb4');

/* ======================================================
   FUNÇÃO GERAR SLUG (UTF-8 SAFE)
====================================================== */
function gerarSlug($titulo)
{
    $titulo = mb_convert_encoding($titulo, 'UTF-8', 'UTF-8');
    $titulo = preg_replace('/[^\P{C}]+/u', '', $titulo);

    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo);
    if ($slug === false) {
        $slug = $titulo;
    }

    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-');
}

/* ======================================================
   RSS GOOGLE NEWS
====================================================== */
$rssUrl = 'https://news.google.com/rss/search?q=Igarassu&hl=pt-BR&gl=BR&ceid=BR:pt-419';
$rss = @simplexml_load_file($rssUrl);

if (!$rss || !isset($rss->channel->item)) {
    echo "RSS indisponível\n";
    return;
}

/* ======================================================
   INSERT (BANCO DECIDE DUPLICIDADE)
====================================================== */
$sql = "
INSERT INTO noticias
(
    titulo,
    slug,
    texto,
    noticia_url,
    fonte,
    status,
    publicado_em
)
VALUES (?, ?, ?, ?, ?, 'publicado', ?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro no prepare(): " . $conn->error);
}

$inseridas = 0;
$falharam  = 0;

foreach ($rss->channel->item as $item) {

    $titulo = trim((string)$item->title);
    $texto  = strip_tags((string)$item->description);
    $link   = trim((string)$item->link);
    $fonte  = (string)($item->source ?? 'Google News');
    $data   = strtotime((string)$item->pubDate);

    if ($titulo === '' || !$data) {
        continue;
    }

    $link = strtok($link, '?');
    $slug = gerarSlug($titulo);
    $publicadoEm = date('Y-m-d H:i:s', $data);

    $stmt->bind_param(
        'ssssss',
        $titulo,
        $slug,
        $texto,
        $link,
        $fonte,
        $publicadoEm
    );

    try {
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inseridas++;
        }

    } catch (mysqli_sql_exception $e) {

        if ($e->getCode() === 1062) {
            $falharam++; // duplicado ignorado
            continue;
        }

        error_log(
            '[API GOOGLE NEWS] Erro: ' . $e->getMessage()
        );
    }
}


$stmt->close();
$conn->close();

//echo "Execução concluída em " . date('d/m/Y H:i:s');
//echo " | Inseridas: {$inseridas}";
//echo " | Falharam: {$falharam}";
