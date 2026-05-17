<?php
require_once '../conexao.php';

$nome = 'Administrador';
$usuario = 'admin';
$senha = password_hash('123456', PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO usuarios (nome, usuario, senha)
    VALUES (?, ?, ?)
");
$stmt->bind_param('sss', $nome, $usuario, $senha);
$stmt->execute();

echo 'Usuário admin criado';
