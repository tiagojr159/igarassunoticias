<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../conexao.php';

$id = (int)($_GET['id'] ?? 0);

$conn->query("DELETE FROM comentarios WHERE id = $id");

header('Location: index.php');
exit;
