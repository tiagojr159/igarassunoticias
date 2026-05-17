<?php
session_start();
require_once 'conexao.php';

$usuario = $_POST['usuario'] ?? '';
$senha   = $_POST['senha'] ?? '';

if ($usuario == '' || $senha == '') {
    die('Preencha todos os campos');
}

$sql = "
    SELECT * FROM usuarios
    WHERE usuario = ?
    AND senha = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $usuario, $senha);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 1) {
    $u = $res->fetch_assoc();

    $_SESSION['admin'] = true;
    $_SESSION['admin_nome'] = $u['nome'];

    header("Location: admin/index.php");
    exit;
} else {
    echo "Usuário ou senha incorretos";
}
