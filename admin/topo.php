<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            Painel Admin — <?= $_SESSION['admin_nome'] ?? 'Administrador' ?>
        </span>

        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-outline-light btn-sm">Inicio</a>
            <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="noticias.php" class="btn btn-outline-light btn-sm">Notícias</a>
            <a href="comentarios.php" class="btn btn-outline-light btn-sm">Comentários</a>
            <a href="usuarios.php" class="btn btn-outline-light btn-sm">Usuários</a>
            <a href="../inserir-noticia.php" class="btn btn-outline-light btn-sm">Carga de Notícias</a>
            <a href="../logout.php" class="btn btn-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>
