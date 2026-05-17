<?php
include 'topo.php';
require_once '../conexao.php';

$res = $conn->query("SELECT id, nome, usuario, senha FROM usuarios ORDER BY id DESC");
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Seu CSS -->
  <link href="../styles.css" rel="stylesheet">
</head>
<body>
<body class="bg-dark text-white">

<?php /* topo.php já imprime o navbar e o bootstrap */ ?>

<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="m-0">Usuários</h4>
    <a href="usuarios_novo.php" class="btn btn-success btn-sm">+ Novo usuário</a>
  </div>

  <div class="table-responsive">
    <table class="table table-dark table-striped align-middle">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Nome</th>
          <th>Usuário</th>
          <th style="width:160px;">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($u = $res->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= htmlspecialchars($u['nome'] ?? '') ?></td>
          <td><?= htmlspecialchars($u['usuario'] ?? '') ?></td>
          <td class="d-flex gap-2">
            <a class="btn btn-warning btn-sm"
               href="usuario_editar.php?id=<?= (int)$u['id'] ?>">Editar</a>

            <?php if ((int)$u['id'] !== 1): ?>
              <a class="btn btn-danger btn-sm"
                 href="usuario_excluir.php?id=<?= (int)$u['id'] ?>"
                 onclick="return confirm('Excluir este usuário?');">Excluir</a>
            <?php else: ?>
              <button class="btn btn-secondary btn-sm" disabled>Excluir</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="text-secondary small mt-2">
    Obs.: Eu bloqueei o usuário ID=1 de excluir (evita você ficar sem admin). Se quiser, removo.
  </div>
</div>

</body>
</html>
