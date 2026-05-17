<?php
include 'topo.php';
require_once '../conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: usuarios.php"); exit; }

// Protege para não apagar o primeiro admin (opcional)
if ($id === 1) {
  header("Location: usuarios.php");
  exit;
}

$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: usuarios.php");
exit;
