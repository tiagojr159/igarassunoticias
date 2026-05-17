<?php
include 'topo.php';
require_once '../conexao.php';

$sql = "
SELECT c.id, c.whatsapp, c.comentario, n.titulo
FROM comentarios c
JOIN noticias n ON n.id = c.noticia_id
ORDER BY c.criado_em DESC
";
$res = $conn->query($sql);
?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Seu CSS -->
  <link href="../styles.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h4 class="mb-3">Comentários</h4>

    <table class="table table-dark table-striped">
        <tr>
            <th>Notícia</th>
            <th>WhatsApp</th>
            <th>Comentário</th>
            <th>Ação</th>
        </tr>

        <?php while ($c = $res->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($c['titulo']) ?></td>
            <td><?= htmlspecialchars($c['whatsapp']) ?></td>
            <td><?= htmlspecialchars($c['comentario']) ?></td>
            <td>
                <a href="comentario_excluir.php?id=<?= $c['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Excluir comentário?')">
                   Excluir
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
