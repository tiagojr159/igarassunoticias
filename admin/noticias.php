<?php
// admin/noticias.php
session_start();
error_reporting(0);
if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
    }
    
    require_once '../conexao.php';
    require_once 'topo.php';
    
/* ======================================================
   FUNÇÃO: COMPRIMIR IMAGEM ATÉ 150KB
====================================================== */
function comprimirImagem($origem, $destino, $maxKB = 150) {
    $info = getimagesize($origem);

    if ($info['mime'] === 'image/png') {
        $img = imagecreatefrompng($origem);
    } else {
        $img = imagecreatefromjpeg($origem);
    }

    $qualidade = 90;
    do {
        imagejpeg($img, $destino, $qualidade);
        $qualidade -= 5;
        clearstatcache();
    } while (filesize($destino) / 1024 > $maxKB && $qualidade > 30);

    imagedestroy($img);
}

/* ======================================================
   UPLOAD DE IMAGEM (AJAX)
====================================================== */
if (isset($_GET['upload_imagem'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['id']) || !isset($_FILES['imagem'])) {
        echo json_encode(['erro' => 'Dados inválidos']);
        exit;
    }

    $id = (int)$_POST['id'];

    // Limite de 30 imagens
    $qtdRes = $conn->query("
        SELECT COUNT(*) AS total
        FROM noticias_imagens
        WHERE noticia_id = $id
    ");
    $total = (int)$qtdRes->fetch_assoc()['total'];

    if ($total >= 30) {
        echo json_encode(['erro' => 'Limite de 30 imagens atingido']);
        exit;
    }

    $dir = '../uploads/noticias/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $nome = 'noticia_' . $id . '_' . time() . '.jpg';
    $destino = $dir . $nome;

    comprimirImagem($_FILES['imagem']['tmp_name'], $destino, 150);

    $kb = round(filesize($destino) / 1024);
    $arquivo = 'uploads/noticias/' . $nome;

    $stmt = $conn->prepare("
        INSERT INTO noticias_imagens (noticia_id, arquivo, tamanho_kb)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('isi', $id, $arquivo, $kb);
    $stmt->execute();

    echo json_encode([
        'sucesso' => true,
        'arquivo' => '../' . $arquivo,
        'kb' => $kb,
        'restantes' => 30 - ($total + 1)
    ]);
    exit;
}

/* ======================================================
   ATUALIZAR NOTÍCIA
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {

    $id          = (int)$_POST['id'];
    $titulo      = $_POST['titulo'] ?? '';
    $subtitulo   = $_POST['subtitulo'] ?? null;
    $texto       = $_POST['texto'] ?? '';
    $imagem_url  = $_POST['imagem_url'] ?? null;
    $noticia_url = $_POST['noticia_url'] ?? null;
    $autor       = $_POST['autor'] ?? null;
    $categoria   = $_POST['categoria'] ?? null;
    $tags        = $_POST['tags'] ?? null;
    $fonte       = $_POST['fonte'] ?? null;
    $status      = $_POST['status'] ?? 'rascunho';
    $destaque    = isset($_POST['destaque']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE noticias SET
            titulo=?,
            subtitulo=?,
            texto=?,
            imagem_url=?,
            noticia_url=?,
            autor=?,
            categoria=?,
            tags=?,
            fonte=?,
            status=?,
            destaque=?
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ssssssssssii",
        $titulo,
        $subtitulo,
        $texto,
        $imagem_url,
        $noticia_url,
        $autor,
        $categoria,
        $tags,
        $fonte,
        $status,
        $destaque,
        $id
    );

    $stmt->execute();
    $mensagem = "Notícia atualizada com sucesso!";
}

/* ======================================================
   BUSCAR PARA EDIÇÃO
====================================================== */
$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $resEdit = $conn->query("SELECT * FROM noticias WHERE id = $id LIMIT 1");
    $editar = $resEdit->fetch_assoc();

    $resImgs = $conn->query("
        SELECT id, arquivo
        FROM noticias_imagens
        WHERE noticia_id = $id
        ORDER BY id DESC
    ");
}

/* ======================================================
   LISTAR NOTÍCIAS
====================================================== */
$res = $conn->query("
    SELECT id, titulo, status, publicado_em
    FROM noticias
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Admin | Notícias</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark text-light">
<div class="container mt-4">

<h3>Gerenciar Notícias</h3>

<?php if (!empty($mensagem)): ?>
<div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>

<?php if ($editar): ?>
<div class="card bg-black mb-4">
<div class="card-body">

<h5>Editar Notícia</h5>

<form method="post">
<input type="hidden" name="id" value="<?= $editar['id'] ?>">

<label>Título</label>
<input class="form-control mb-2" name="titulo" value="<?= htmlspecialchars($editar['titulo']) ?>">

<label>Subtítulo</label>
<input class="form-control mb-2" name="subtitulo" value="<?= htmlspecialchars($editar['subtitulo']) ?>">

<label>Texto</label>
<textarea class="form-control mb-2" name="texto" rows="6"><?= htmlspecialchars($editar['texto']) ?></textarea>

<label>Imagem principal (URL)</label>
<input class="form-control mb-2" name="imagem_url" id="imagem_url"
       value="<?= htmlspecialchars($editar['imagem_url']) ?>">

<!-- ================= UPLOAD GALERIA ================= -->
<label>Galeria da Notícia (até 30 imagens)</label>
<div id="dropArea"
     class="border border-success rounded p-3 text-center mb-3"
     style="cursor:pointer">
    Arraste a imagem aqui (PC) ou clique para escolher (Celular)
    <input type="file" id="fileInput" hidden accept="image/*">
</div>

<div id="preview" class="row">
<?php while ($img = $resImgs->fetch_assoc()): ?>
    <div class="col-4 mb-2">
        <img src="../<?= htmlspecialchars($img['arquivo']) ?>"
             class="img-fluid rounded">
    </div>
<?php endwhile; ?>
</div>

<label>Link da Notícia</label>
<input class="form-control mb-2" name="noticia_url"
       value="<?= htmlspecialchars($editar['noticia_url']) ?>">

<button class="btn btn-success mt-3">Salvar Alterações</button>
</form>

</div>
</div>
<?php endif; ?>

<table class="table table-dark table-striped">
<tr>
<th>Título</th>
<th>Status</th>
<th>Ações</th>
</tr>
<?php while ($n = $res->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($n['titulo']) ?></td>
<td><?= ucfirst($n['status']) ?></td>
<td>
<a class="btn btn-warning btn-sm" href="?editar=<?= $n['id'] ?>">Editar</a>
</td>
</tr>
<?php endwhile; ?>
</table>

</div>

<script>
const drop = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('preview');
const noticiaId = <?= $editar ? (int)$editar['id'] : 0 ?>;

if (drop) {
    drop.onclick = () => fileInput.click();

    drop.ondragover = e => { e.preventDefault(); drop.classList.add('bg-success'); };
    drop.ondragleave = () => drop.classList.remove('bg-success');

    drop.ondrop = e => {
        e.preventDefault();
        drop.classList.remove('bg-success');
        enviar(e.dataTransfer.files[0]);
    };

    fileInput.onchange = e => enviar(e.target.files[0]);
}

function enviar(file) {
    const fd = new FormData();
    fd.append('imagem', file);
    fd.append('id', noticiaId);

    fetch('?upload_imagem=1', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(j => {
        if (j.erro) {
            alert(j.erro);
            return;
        }

        preview.innerHTML += `
            <div class="col-4 mb-2">
                <img src="${j.arquivo}" class="img-fluid rounded">
                <small>${j.kb} KB | Restantes: ${j.restantes}</small>
            </div>
        `;
    });
}
</script>

</body>
</html>
