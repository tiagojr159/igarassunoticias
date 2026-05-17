<?php
require_once '../conexao.php';

header('Content-Type: application/json');

if (!isset($_POST['noticia_id']) || !isset($_FILES['imagem'])) {
    echo json_encode(['erro' => 'Dados inválidos']);
    exit;
}

$noticia_id = (int)$_POST['noticia_id'];
$arquivo = $_FILES['imagem'];

$dir = '../uploads/noticias/';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
    echo json_encode(['erro' => 'Formato inválido']);
    exit;
}

$nome_final = 'noticia_' . $noticia_id . '_' . time() . '.jpg';
$caminho = $dir . $nome_final;

/* =====================
   COMPRESSÃO
===================== */
function comprimirImagem($origem, $destino, $maxKB = 150) {
    $info = getimagesize($origem);

    if ($info['mime'] == 'image/png') {
        $img = imagecreatefrompng($origem);
    } else {
        $img = imagecreatefromjpeg($origem);
    }

    $qualidade = 90;
    do {
        imagejpeg($img, $destino, $qualidade);
        $qualidade -= 5;
        clearstatcache();
    } while (filesize($destino) / 1024 > $maxKB && $qualidade > 30);

    imagedestroy($img);
}

comprimirImagem($arquivo['tmp_name'], $caminho, 150);

$tamanho_kb = round(filesize($caminho) / 1024);

$stmt = $conn->prepare("
    INSERT INTO noticias_imagens (noticia_id, arquivo, tamanho_kb)
    VALUES (?, ?, ?)
");
$stmt->bind_param('isi', $noticia_id, $nome_final, $tamanho_kb);
$stmt->execute();

echo json_encode([
    'sucesso' => true,
    'arquivo' => 'uploads/noticias/' . $nome_final,
    'tamanho_kb' => $tamanho_kb
]);
