<?php
include 'topo.php';
require_once '../conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: usuarios.php"); exit; }

// Carrega usuário
$stmt = $conn->prepare("SELECT id, nome, usuario, senha FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { die("Usuário não encontrado."); }
$u = $res->fetch_assoc();

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $usuario = trim($_POST['usuario'] ?? '');
  $senha = trim($_POST['senha'] ?? ''); // se vier vazio, não altera

  if ($nome === '' || $usuario === '') {
    $erro = "Preencha nome e usuário.";
  } else {
    // checa se usuario já existe em outro id
    $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1");
    $stmtCheck->bind_param("si", $usuario, $id);
    $stmtCheck->execute();
    $rCheck = $stmtCheck->get_result();
    if ($rCheck->num_rows > 0) {
      $erro = "Este usuário (login) já existe. Escolha outro.";
    } else {
      if ($senha !== '') {
        $stmtUp = $conn->prepare("UPDATE usuarios SET nome = ?, usuario = ?, senha = ? WHERE id = ?");
        $stmtUp->bind_param("sssi", $nome, $usuario, $senha, $id);
      } else {
        $stmtUp = $conn->prepare("UPDATE usuarios SET nome = ?, usuario = ? WHERE id = ?");
        $stmtUp->bind_param("ssi", $nome, $usuario, $id);
      }
      $stmtUp->execute();
      $ok = "Usuário atualizado com sucesso.";

      // Recarrega dados atualizados
      $stmt = $conn->prepare("SELECT id, nome, usuario, senha FROM usuarios WHERE id = ? LIMIT 1");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $u = $stmt->get_result()->fetch_assoc();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Editar Usuário</title>
</head>
<body class="bg-dark text-white">

<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="m-0">Editar Usuário #<?= (int)$u['id'] ?></h4>
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
          <input class="form-control" name="nome" value="<?= htmlspecialchars($u['nome'] ?? '') ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Usuário (login)</label>
          <input class="form-control" name="usuario" value="<?= htmlspecialchars($u['usuario'] ?? '') ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Nova senha</label>
          <input class="form-control" name="senha" placeholder="Deixe vazio para não alterar">
          <div class="form-text text-secondary">Senha em texto puro (como você pediu).</div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success">Salvar alterações</button>
          <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
