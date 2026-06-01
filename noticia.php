<?php
require_once 'conexao.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

/* ==========================
   BUSCA NOTÍCIA
========================== */
$sql = "
    SELECT id, titulo, texto, imagem_url, autor, categoria,
           DATE_FORMAT(publicado_em, '%d/%m/%Y') AS data_formatada
    FROM noticias
    WHERE slug = ?
      AND status = 'publicado'
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $slug);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('<h2>Notícia não encontrada</h2>');
}

$noticia = $res->fetch_assoc();
$noticiaId = (int)$noticia['id'];

/* incrementa acessos */
$conn->query("UPDATE noticias SET acessos = acessos + 1 WHERE id = $noticiaId");

/* ==========================
   IMAGENS RELACIONADAS
========================== */
$imgs = $conn->query("
    SELECT arquivo
    FROM noticias_imagens
    WHERE noticia_id = $noticiaId
    ORDER BY criado_em ASC
");

$imagensRelacionadas = [];
if ($imgs && $imgs->num_rows > 0) {
    while ($i = $imgs->fetch_assoc()) {
        $imagensRelacionadas[] = $i['arquivo'];
    }
}

/* ==========================
   DEFINIÇÃO DA IMAGEM DE DESTAQUE
========================== */
if (count($imagensRelacionadas) > 0) {
    $imagemDestaque = $imagensRelacionadas[0];
} elseif (!empty($noticia['imagem_url'])) {
    $imagemDestaque = $noticia['imagem_url'];
} else {
    $imagemDestaque = 'logo.png';
}

/* ==========================
   COMENTÁRIOS
========================== */
$stmtC = $conn->prepare("
    SELECT whatsapp, comentario, criado_em
    FROM comentarios
    WHERE noticia_id = ?
      AND status = 'aprovado'
    ORDER BY criado_em DESC
");
$stmtC->bind_param('i', $noticiaId);
$stmtC->execute();
$comentarios = $stmtC->get_result();

/* ==========================
   OUTRAS NOTÍCIAS
========================== */
$stmt2 = $conn->prepare("
    SELECT titulo, slug, imagem_url,
           DATE_FORMAT(publicado_em, '%d/%m/%Y') AS data_formatada
    FROM noticias
    WHERE status = 'publicado'
      AND slug != ?
    ORDER BY RAND()
    LIMIT 10
");
$stmt2->bind_param('s', $slug);
$stmt2->execute();
$outras = $stmt2->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($noticia['titulo']); ?> | Igarassu Notícias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f7a43">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Igarassu Notícias">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="logo.png">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <header>
        <div class="container header-flex">
            <h1>Igarassu Notícias e Rádio Cueiras</h1>
            <div class="header-actions">
                <span class="on-air">● AO VIVO</span>
                <button id="installAppButton" class="install-app-btn" type="button" hidden>Instalar app</button>
            </div>
        </div>
    </header>

    <div class="container main-content">

        <main>

            <a href="index.php" class="btn btn-success mb-3">← Voltar</a>

            <article class="glass">

                <!-- IMAGEM DE DESTAQUE -->
                <img src="<?= htmlspecialchars($imagemDestaque); ?>"
                    style="border-radius:16px;margin-bottom:16px;width:100%;">

                <?php if ($noticia['categoria']): ?>
                    <div class="categoria"><?= htmlspecialchars($noticia['categoria']); ?></div>
                <?php endif; ?>

                <h1><?= htmlspecialchars($noticia['titulo']); ?></h1>

                <div style="color:var(--branco);font-size:14px;margin-bottom:20px;">
                    Publicado em <?= $noticia['data_formatada']; ?>
                    <?php if ($noticia['autor']): ?>
                        | Por <?= htmlspecialchars($noticia['autor']); ?>
                    <?php endif; ?>
                </div>

                <div style="font-size:17px;line-height:1.8;">
                    <?= nl2br(htmlspecialchars($noticia['texto'])); ?>
                </div>

                <!-- GALERIA (SE TIVER MAIS DE UMA IMAGEM) -->
                <?php if (count($imagensRelacionadas) > 1): ?>
                    <div class="row mt-4">
                        <?php foreach ($imagensRelacionadas as $k => $img): ?>
                            <?php if ($k === 0) continue; // pula a imagem de destaque 
                            ?>
                            <div class="col-md-4 col-6 mb-3">
                                <img src="<?= htmlspecialchars($img); ?>"
                                    style="width:100%;border-radius:12px;object-fit:cover;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- BOTÃO COMENTAR -->
                <button class="btn btn-success mt-4" data-bs-toggle="modal" data-bs-target="#modalComentario">
                    💬 Comentar
                </button>

            </article>

            <!-- ================= COMENTÁRIOS ================= -->
            <div class="glass mt-4">
                <h4>Comentários</h4>

                <?php if ($comentarios->num_rows === 0): ?>
                    <p style="color:var(--branco)">Nenhum comentário ainda.</p>
                <?php endif; ?>

                <?php while ($c = $comentarios->fetch_assoc()): ?>
                    <div style="border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px;padding-bottom:10px;">
                        <?php
                        $whatsapp = preg_replace('/\D/', '', $c['whatsapp']);
                        $final = substr($whatsapp, -2);
                        $whatsappMascara = '•••• ••' . $final;
                        ?>
                        <strong>📱 <?= $whatsappMascara; ?></strong><br>
                        <span style="font-size:12px;color:var(--branco);">
                            <?= date('d/m/Y H:i', strtotime($c['criado_em'])); ?>
                        </span>
                        <p style="margin-top:6px;">
                            <?= nl2br(htmlspecialchars($c['comentario'])); ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>

        </main>

        <aside>

            <div class="widget">
                <div class="widget-header">
                    <h4>Outras Notícias</h4>
                    <span>Seleção curada</span>
                </div>

                <div class="news-sidebar">
                    <?php while ($n = $outras->fetch_assoc()):
                        $thumb = trim($n['imagem_url']) ?: 'logo.png';
                    ?>
                        <article class="news-card">
                            <div class="news-card-media">
                                <img src="<?= htmlspecialchars($thumb); ?>" alt="<?= htmlspecialchars($n['titulo']); ?>">
                            </div>
                            <div class="news-card-body">
                                <p class="news-card-date"><?= $n['data_formatada']; ?></p>
                                <h5><a href="noticia.php?slug=<?= urlencode($n['slug']); ?>"><?= htmlspecialchars($n['titulo']); ?></a></h5>
                                <span class="news-card-label">Ao vivo</span>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

            </div>

        </aside>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y'); ?> Igarassu Notícias e Rádio Cueiras</p>
        </div>
    </footer>

    <!-- MODAL COMENTÁRIO -->
    <style>
        #modalComentario .modal-dialog {
            max-width: 520px;
            margin: 2rem auto;
        }

        #modalComentario .modal-content {
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: linear-gradient(145deg, rgba(13, 27, 42, 0.95), rgba(11, 36, 58, 0.9));
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
            color: #fff;
        }

        #modalComentario .modal-header {
            border-bottom: none;
            padding: 1.6rem 1.8rem 0.8rem;
        }

        #modalComentario .modal-title {
            font-size: 1.25rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        #modalComentario .btn-close {
            filter: invert(1);
            opacity: 0.7;
        }

        #modalComentario .modal-body {
            padding: 1.5rem 1.8rem 0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        #modalComentario label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.45rem;
            display: block;
        }

        #modalComentario .form-control {
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            padding: 0.85rem 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        #modalComentario .form-control:focus {
            border-color: #f2c94c;
            box-shadow: 0 0 0 0.15rem rgba(242, 201, 76, 0.4);
        }

        #modalComentario textarea.form-control {
            min-height: 140px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        #modalComentario .captcha-hint {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.35rem;
        }

        #modalComentario .modal-footer {
            border-top: none;
            padding: 1.2rem 1.8rem 1.6rem;
            background: transparent;
            display: flex;
            justify-content: flex-end;
        }

        #modalComentario .btn-submit {
            border-radius: 14px;
            padding: 0.85rem 1.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            background: #1d7c3e;
            border: none;
            box-shadow: 0 10px 18px rgba(29, 124, 62, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        #modalComentario .btn-submit:hover {
            transform: translateY(-2px);
            background: #26a150;
            box-shadow: 0 12px 22px rgba(26, 155, 78, 0.45);
        }

        @media (max-width: 576px) {
            #modalComentario .modal-dialog {
                margin: 1rem;
            }

            #modalComentario .modal-footer {
                justify-content: center;
            }
        }
    </style>

    <div class="modal fade" id="modalComentario" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="salvar_comentario.php" class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Enviar comentário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="noticia_id" value="<?= $noticiaId; ?>">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug); ?>">

                    <div class="form-grid">
                        <div>
                            <label>WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control" placeholder="(xx) xxxxx-xxxx" required>
                            <small class="captcha-hint">Usaremos apenas para identificar o comentário.</small>
                        </div>

                        <div>
                            <label>Quanto é 3 + 4?</label>
                            <input type="text" name="captcha" class="form-control" placeholder="Resposta" required>
                            <small class="captcha-hint">Ajuda a evitar envios automatizados.</small>
                        </div>
                    </div>

                    <div>
                        <label>Comentário</label>
                        <textarea name="comentario" class="form-control" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success btn-submit">Enviar comentário</button>
                </div>

            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let deferredInstallPrompt = null;
        const isMobileDevice = /Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(navigator.userAgent);

        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("service-worker.js").catch(() => {});
            });
        }

        const installAppButton = document.getElementById("installAppButton");

        window.addEventListener("beforeinstallprompt", (event) => {
            event.preventDefault();
            if (!isMobileDevice) {
                return;
            }
            deferredInstallPrompt = event;
            if (installAppButton) {
                installAppButton.hidden = false;
            }
        });

        if (installAppButton) {
            installAppButton.addEventListener("click", async () => {
                if (deferredInstallPrompt) {
                    deferredInstallPrompt.prompt();
                    await deferredInstallPrompt.userChoice;
                    deferredInstallPrompt = null;
                    installAppButton.hidden = true;
                    return;
                }

                alert("Se o botão automático não aparecer, abra o menu do navegador e toque em 'Instalar app' ou 'Adicionar à tela inicial'.");
            });
        }

        window.addEventListener("appinstalled", () => {
            deferredInstallPrompt = null;
            if (installAppButton) {
                installAppButton.hidden = true;
            }
        });
    </script>

</body>

</html>
