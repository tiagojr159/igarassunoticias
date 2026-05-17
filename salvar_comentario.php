<?php
require_once 'conexao.php';

$noticia_id = (int)($_POST['noticia_id'] ?? 0);
$whatsapp   = trim($_POST['whatsapp'] ?? '');
$comentario = trim($_POST['comentario'] ?? '');
$captcha    = trim($_POST['captcha'] ?? '');
$slug       = $_POST['slug'] ?? '';

if ($captcha !== '7') {
    die('Captcha incorreto');
}

if (!$noticia_id || !$whatsapp || !$comentario) {
    die('Dados inválidos');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

$stmt = $conn->prepare("
    INSERT INTO comentarios (noticia_id, whatsapp, comentario, ip_usuario)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param('isss', $noticia_id, $whatsapp, $comentario, $ip);
$stmt->execute();

header("Location: noticia.php?slug=" . urlencode($slug));
exit;
