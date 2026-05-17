<?php
include 'topo.php';
require_once '../conexao.php';

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $usuario = trim($_POST['usuario'] ?? '');
  $senha = trim($_POST['senha'] ?? '');

  if ($nome === '' || $usuario === '' || $senha === '') {
    $erro = "Preencha todos os campos.";
  } else {
    $chk = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
    $chk->bind_param("s", $usuario);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $erro = "Este usuário (login) já existe.";
    } else {
      $stmt = $conn->prepare("INSERT INTO usuarios (nome, usuario, senha) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $nome, $usuario, $senha);
      $stmt->execute();
      $ok = "Usuário criado com sucesso.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Novo Usuário</title>
</head>
<body class="bg-dark text-white">

<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="m-0">Novo Usuário</h4>
    <a href="usuarios.php" class="btn btn-outline-light btn-sm">← Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
  <?php endif; ?>

  <div class="card bg-black text-white border-secondary">
    <div class="card-body">
      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Usuário (login)</label>
          <input class="form-control" name="usuario" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Senha</label>
          <input class="form-control" name="senha" required>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success">Cadastrar</button>
          <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
