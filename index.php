<?php
//index.php

require_once 'conexao.php';
require_once 'cron_api_google.php';

/* ===============================
   ÚLTIMAS NOTÍCIAS
================================ */
$sql_ultimas = "
      SELECT id, titulo, slug, imagem_url, categoria,
       DATE_FORMAT(publicado_em, '%d/%m/%Y') AS data_formatada
FROM noticias
WHERE status = 'publicado'
ORDER BY publicado_em DESC
limit 50
";
$res = $conn->query($sql_ultimas);
$ultimasNoticias = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $ultimasNoticias[] = $row;
    }
}

$playlistArquivos = [];
$audioExtensoes = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
foreach ($audioExtensoes as $extensao) {
    foreach (glob(__DIR__ . '/midia/*.' . $extensao) ?: [] as $arquivoAudio) {
        $playlistArquivos[] = [
            'url' => 'midia/' . basename($arquivoAudio),
            'nome' => pathinfo($arquivoAudio, PATHINFO_FILENAME),
        ];
    }
}


/* ===============================
   IMAGENS RELACIONADAS (POR ID)
================================ */
$imgsRelacionadas = [];

$resImgs = $conn->query("
    SELECT noticia_id, arquivo
    FROM noticias_imagens
    ORDER BY criado_em ASC
");

if ($resImgs && $resImgs->num_rows > 0) {
    while ($row = $resImgs->fetch_assoc()) {
        // pega só a PRIMEIRA imagem por notícia
        if (!isset($imgsRelacionadas[$row['noticia_id']])) {
            $imgsRelacionadas[$row['noticia_id']] = $row['arquivo'];
        }
    }
}



$sql_total = "SELECT COUNT(*) AS total FROM noticias WHERE status='publicado'";
$res_total = $conn->query($sql_total);
$totalNoticias = 0;

if ($res_total && $row = $res_total->fetch_assoc()) {
    $totalNoticias = (int)$row['total'];
}






/* ===============================
   MAIS LIDAS
================================ */
$sql_mais_lidas = "
    SELECT titulo, noticia_url
    FROM noticias
    WHERE status='publicado'
    ORDER BY acessos DESC
    LIMIT 5
";
$resultado_mais_lidas = $conn->query($sql_mais_lidas);

/* ===============================
   CATEGORIAS
================================ */
$sql_categorias = "
    SELECT DISTINCT categoria
    FROM noticias
    WHERE status='publicado'
      AND categoria IS NOT NULL
    ORDER BY categoria ASC
";
$resultado_categorias = $conn->query($sql_categorias);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Igarassu Notícias e Rádio Cueiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f7a43">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Igarassu Notícias">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="logo.png">
    <link rel="stylesheet" href="styles.css">

    <style>
        /* ================= PLAYER AUTOMÁTICO ================= */
        .smart-player {
            position: relative;
            overflow: hidden;
            padding: 20px;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(17, 211, 110, 0.18), transparent 32%),
                linear-gradient(160deg, rgba(4, 11, 8, 0.98), rgba(10, 31, 20, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
            color: #fff;
        }

        .smart-player::before {
            content: "";
            position: absolute;
            inset: auto -40px -40px auto;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(0, 200, 83, 0.08);
            filter: blur(8px);
        }

        .smart-player-head {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .smart-player-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.68);
        }

        .smart-player-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: #10d768;
            box-shadow: 0 0 0 6px rgba(16, 215, 104, 0.15);
        }

        .smart-player-head h5 {
            margin: 8px 0 0;
            font-size: 22px;
            line-height: 1.15;
            font-family: Georgia, "Times New Roman", serif;
        }

        .smart-player-badge {
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.06);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.74);
            white-space: nowrap;
        }

        .smart-player-stage {
            position: relative;
            z-index: 1;
            gap: 16px;
        }

        .smart-player-visual {
            grid-template-columns: 92px 1fr;
            gap: 14px;
            align-items: center;
            padding: 14px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .smart-player-cover {
            width: 92px;
            height: 92px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.35);
        }

        .smart-player-now {
            font-size: 11px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.56);
        }

        .smart-player-track {
            margin: 6px 0 4px;
            font-size: 17px;
            line-height: 1.35;
            font-weight: 700;
        }

        .smart-player-news {
            margin: 0;
            font-size: 13px;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.72);
        }

        .smart-player-progress {
            position: relative;
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.08);
        }

        .smart-player-progress-bar {
            width: 0%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #09c457, #7bffb0);
            transition: width 0.2s linear;
        }

        .smart-player-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.62);
        }

        .smart-player-controls {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .smart-player-btn {
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 12px 6px;
            font-size: 11px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            font-weight: 700;
            cursor: pointer;
            background: rgba(15, 40, 26, 0.8);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15);
        }

        .smart-player-btn:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 18px 26px rgba(0, 0, 0, 0.45), inset 0 0 0 1px rgba(255, 255, 255, 0.22);
        }

        .smart-player-btn.play {
            color: #02140a;
            background: linear-gradient(135deg, #38ff9c, #1bd46f 60%, #0b7a43);
            box-shadow: 0 15px 30px rgba(16, 140, 64, 0.4);
        }

        .smart-player-btn.stop {
            color: #fff;
            background: rgba(2, 18, 6, 0.9);
        }

        .smart-player-btn.nav {
            color: #c9ffe1;
            background: rgba(2, 35, 23, 0.8);
            border: 1px solid rgba(21, 226, 130, 0.7);
            box-shadow: 0 12px 22px rgba(3, 26, 15, 0.5);
        }

        .smart-player-btn.nav {
            color: #d8ffe7;
            background: rgba(12, 58, 31, 0.68);
            border: 1px solid rgba(29, 213, 100, 0.22);
        }

        .smart-player-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.58);
        }

        @media (max-width: 768px) {
            .smart-player-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .smart-player-badge {
                align-self: flex-start;
            }

            .smart-player-visual {
                grid-template-columns: 1fr;
            }

            .smart-player-cover {
                width: 100%;
                height: 160px;
            }

            .smart-player-meta,
            .smart-player-footer {
                flex-direction: column;
            }

            .smart-player-controls {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* ================= TEMPO / DATA ================= */
        .weather-box {
            background: #01100a;
            border-radius: 8px;
            padding: 10px;
            font-size: 14px;
            text-align: center;
        }

        .weather-temp {
            font-size: 22px;
            font-weight: bold;
        }

        /* ================= FOTOS ================= */
        .photo-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
        }

        .photo-grid img {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
        }

        body {
            transition: background 0.55s ease, color 0.55s ease;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at top right, rgba(13, 222, 118, 0.35), transparent 45%),
                radial-gradient(circle at bottom left, rgba(15, 103, 63, 0.25), transparent 52%);
            opacity: 0;
            transition: opacity 0.45s ease;
            z-index: -1;
        }

        body.hover-cue::after {
            opacity: 1;
        }
    </style>
</head>

<body>

    <header>
        <div class="container header-flex">
            <h1>Igarassu Notícias e Rádio Cueiras</h1>

            <nav class="menu-topo">
                <a href="index.php">Início</a>
                <a href="tiago.php">Quem Somos</a>
                <a href="admin/index.php">Admin</a>
                <button id="installAppButton" class="install-app-btn" type="button" hidden>Instalar app</button>
            </nav>

            
        </div>
    </header>


    <div class="container main-content">

        <!-- ================= MAIN ================= -->
        <main>

            <!-- DESTAQUE -->
            <section class="destaque">
                <img src="tiago.png" alt="Rádio Cueiras">

                <!-- OVERLAY DE NOTÍCIAS -->
                <div class="news-ticker-overlay">
                    <ul id="newsTicker">
                        <?php foreach ($ultimasNoticias as $i => $n): ?>
                            <li class="<?= $i === 0 ? 'active' : '' ?>">
                                <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>">
                                    📰 <?= htmlspecialchars($n['titulo']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="destaque-content">
                    <h2>Tiago Antonio</h2>
                    <p>Rádio Cueiras Igarassu</p>
                    <a href="#" class="btn-leia-mais">24h - Ao vivo</a>


                </div>
            </section>











            
            <section class="cards-panel">
                <article class="mini-card card-cta">
                    <div class="card-kicker">Programa contínuo</div>
                    <h4>Player Cueiras Mix</h4>
                    <p><?= count($playlistArquivos) ?> faixas locais circulando em modo aleatório.</p>
                </article>

                <article class="mini-card card-info">
                    <div class="card-kicker">Conexão direta</div>
                    <h4>WhatsApp oficial</h4>
                    <p>Participe do grupo oficial da Rádio Cueiras e fique por dentro das ações.</p>
                    <a class="card-link" href="https://chat.whatsapp.com/BhebKnFDKK4LwcScWKh0U2" target="_blank" rel="noopener">Entrar no grupo</a>
                </article>

                <article class="mini-card card-highlight">
                    <div class="card-kicker">Tempo em Igarassu</div>
                    <h4><span id="currentDateCard"><?= date('d/m/Y'); ?></span></h4>
                    <p id="tempCard">--°C atualizado automaticamente.</p>
                </article>
            </section>

            <!-- ÚLTIMAS NOTÍCIAS -->
            <section class="ultimas-noticias">
                <h3>Últimas Notícias</h3>
                <div class="grid-noticias">
                    <?php foreach ($ultimasNoticias as $n): ?>
                        <?php
$img = 'logo.png';

// se a notícia tem imagem própria, usa ela
if (!empty($n['imagem_url'])) {
    $img = $n['imagem_url'];
}
// se não tiver, tenta imagem da tabela noticias_imagens
elseif (!empty($imgsRelacionadas[$n['id']])) {
    $img = $imgsRelacionadas[$n['id']];
}
?>
                        <article class="noticia-card">
                            <img src="<?= htmlspecialchars($img ?? 'logo.png') ?>">
                            <div class="card-content">
                                <span class="categoria"><?= htmlspecialchars($n['categoria'] ?? '') ?></span>
                                <h4>
                                    <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>">
                                        <?= htmlspecialchars($n['titulo']) ?>
                                    </a>
                                </h4>
                                <span class="data"><?= $n['data_formatada'] ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>


























        </main>

        <!-- ================= SIDEBAR ================= -->
        <aside>

            <div class="widget">
                <div class="smart-player">
                    <div class="smart-player-head">
                        <div>
                            <div class="smart-player-kicker">
                                <span class="smart-player-dot"></span>
                                Programa continuo
                            </div>
                            <h5>Player Cueiras Mix</h5>
                        </div>
                        <span class="smart-player-badge" id="playerStatusBadge">Auto mix</span>
                    </div>

                    <div class="smart-player-stage">
                        <div class="smart-player-visual">
                            <div>
                                <div class="smart-player-now" id="playerMode">Preparando sequencia</div>
                                <div class="smart-player-track" id="currentTrack">Carregando musicas da pasta midia...</div>
                                <p class="smart-player-news" id="currentHeadline">Entre uma faixa e outra, o player le uma noticia aleatoria da pagina inicial.</p>
                            </div>
                        </div>

                        <div class="smart-player-progress">
                            <div class="smart-player-progress-bar" id="playerProgressBar"></div>
                        </div>

                        <div class="smart-player-meta">
                            <span id="playerMetaLeft">Playlist randomica</span>
                            <span id="playerMetaRight"><?= count($playlistArquivos) ?> faixas</span>
                        </div>

                        <div class="smart-player-controls">
                            <button id="smartPlayerPrev" class="smart-player-btn nav" type="button"><<</button>
                            <button id="smartPlayerStart" class="smart-player-btn play" type="button">Player</button>
                            <button id="smartPlayerStop" class="smart-player-btn stop" type="button">Stop</button>
                            <button id="smartPlayerNext" class="smart-player-btn nav" type="button">>></button>
                        </div>

                        <div class="smart-player-footer">
                            <span id="playerVoiceStatus">Leitura automatica entre faixas</span>
                            <span>Acervo local</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔹 TEMPO + DATA -->
            <div class="widget">
                <h4>Whatsapp</h4>
                <a href="https://chat.whatsapp.com/BhebKnFDKK4LwcScWKh0U2" target="_blank" rel="noopener" style="color:white">
                    <img src="https://tix.life/wp-content/uploads/2020/06/whatsapp-grupo-icon.png"
                        alt="WhatsApp" style="width:80px; height:80px; vertical-align:middle; margin-right:8px;">
                    Entrar no grupo do WhatsApp
                </a>
            </div>



            <!-- 🔹 TOTAL DE NOTÍCIAS -->
            <div class="widget">
                <h4>Total de Notícias</h4>
                <div class="weather-box">
                    <div style="font-size:13px;">Notícias publicadas</div>
                    <div class="weather-temp">

                        <?= number_format($totalNoticias, 0, ',', '.') ?>
                    </div>
                    <div>Igarassu e região</div>
                </div>
            </div>


            <div class="widget">
                <h4>Anuncie Aqui:</h4>
                <a href="https://chat.whatsapp.com/BhebKnFDKK4LwcScWKh0U2" target="_blank" rel="noopener">
                    <img src="anunie.png"
                        alt="WhatsApp" style="width:380px; height:180px; vertical-align:middle; margin-right:8px;">
                </a>
            </div>


            <!-- 🔹 TEMPO + DATA -->
            <div class="widget">
                <h4>Tempo e Data</h4>
                <div id="weatherBox" class="weather-box">
                    <div id="currentDate"></div>
                    <div class="weather-temp" id="tempNow">--°C</div>
                    <div>Igarassu – PE</div>
                </div>
            </div>

            <!-- 🔹 FOTOS -->
            <div class="widget">
                <h4>Fotos de Igarassu</h4>
                <div id="photoGrid" class="photo-grid"></div>
            </div>

        </aside>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Igarassu Notícias e Rádio Cueiras</p>
        </div>
    </footer>

    <div id="pwaInstallTip" class="pwa-install-tip" role="status" aria-live="polite">
        <div>
            <strong>Instale o app no celular</strong>
            <span id="pwaInstallText">Toque em instalar para abrir o site como aplicativo.</span>
        </div>
        <button id="pwaInstallTipButton" type="button">Instalar</button>
    </div>

    <script>
        let deferredInstallPrompt = null;
        const isMobileDevice = /Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/i.test(navigator.userAgent));
        const isIosDevice = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/i.test(navigator.userAgent));
        const isStandalone = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("/igarassunoticias/service-worker.js", {
                    scope: "/igarassunoticias/"
                }).catch(() => {});
            });
        }

        const installAppButton = document.getElementById("installAppButton");
        const pwaInstallTip = document.getElementById("pwaInstallTip");
        const pwaInstallText = document.getElementById("pwaInstallText");
        const pwaInstallTipButton = document.getElementById("pwaInstallTipButton");

        const showInstallControls = () => {
            if (!isMobileDevice || isStandalone) {
                return;
            }

            if (installAppButton) {
                installAppButton.hidden = false;
                installAppButton.classList.add("is-mobile-cta");
            }

            if (pwaInstallTip) {
                pwaInstallTip.classList.add("is-visible");
            }

            if (pwaInstallText && isIosDevice) {
                pwaInstallText.textContent = "No iPhone, toque em Compartilhar e depois em Adicionar a Tela de Inicio.";
            }
        };

        const hideInstallControls = () => {
            if (installAppButton) {
                installAppButton.hidden = true;
            }
            if (pwaInstallTip) {
                pwaInstallTip.classList.remove("is-visible");
            }
        };

        const runInstallFlow = async () => {
            if (deferredInstallPrompt) {
                deferredInstallPrompt.prompt();
                await deferredInstallPrompt.userChoice;
                deferredInstallPrompt = null;
                hideInstallControls();
                return;
            }

            alert(isIosDevice
                ? "Para instalar no iPhone: toque no botao Compartilhar do Safari e escolha 'Adicionar a Tela de Inicio'."
                : "Abra o menu do navegador e toque em 'Instalar app' ou 'Adicionar a tela inicial'.");
        };

        window.addEventListener("beforeinstallprompt", (event) => {
            event.preventDefault();
            if (!isMobileDevice) {
                return;
            }
            deferredInstallPrompt = event;
            showInstallControls();
        });

        if (installAppButton) {
            installAppButton.addEventListener("click", (event) => {
                event.stopImmediatePropagation();
                runInstallFlow();
            }, true);
        }

        if (pwaInstallTipButton) {
            pwaInstallTipButton.addEventListener("click", runInstallFlow);
        }

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
            hideInstallControls();
        });

        window.addEventListener("load", () => {
            window.setTimeout(showInstallControls, 1200);
        });

        // ================= OVERLAY =================
        const items = document.querySelectorAll("#newsTicker li");
        let idx = 0;
        if (items.length > 1) {
            setInterval(() => {
                items[idx].classList.remove("active");
                idx = (idx + 1) % items.length;
                items[idx].classList.add("active");
            }, 4000);
        }

        // ================= PLAYER MIDIA + NOTICIAS =================
        const playlist = <?= json_encode($playlistArquivos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const noticiasAudio = <?= json_encode(array_map(
            fn($n) => [
                'titulo' => $n['titulo'],
                'categoria' => $n['categoria'] ?? 'Geral',
                'data' => $n['data_formatada'],
            ],
            $ultimasNoticias
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const smartAudio = new Audio();
        smartAudio.preload = "metadata";
        smartAudio.volume = 0.9;
        const voiceAudio = new Audio();
        voiceAudio.preload = "auto";
        voiceAudio.volume = 1;
        const neuralVoiceEndpoint = "api/tts/";
        const neuralVoiceName = "pt-BR-AntonioNeural";

        const prevButton = document.getElementById("smartPlayerPrev");
        const startButton = document.getElementById("smartPlayerStart");
        const stopButton = document.getElementById("smartPlayerStop");
        const nextButton = document.getElementById("smartPlayerNext");
        const playerMode = document.getElementById("playerMode");
        const currentTrack = document.getElementById("currentTrack");
        const currentHeadline = document.getElementById("currentHeadline");
        const playerBadge = document.getElementById("playerStatusBadge");
        const playerMetaLeft = document.getElementById("playerMetaLeft");
        const playerMetaRight = document.getElementById("playerMetaRight");
        const playerVoiceStatus = document.getElementById("playerVoiceStatus");
        const progressBar = document.getElementById("playerProgressBar");
        const canSpeak = typeof fetch === "function" && typeof Audio === "function" && typeof URL !== "undefined";

        let playerActive = false;
        let songQueue = [];
        let newsQueue = [];
        let songHistory = [];
        let newsHistory = [];
        let currentSong = null;
        let currentNews = null;
        let currentPhase = "idle";
        let transitionToken = 0;
        let currentVoiceUrl = "";
        let resolveCurrentVoice = null;

        const shuffle = (items) => {
            const clone = [...items];
            for (let i = clone.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [clone[i], clone[j]] = [clone[j], clone[i]];
            }
            return clone;
        };

        const formatTrackName = (name) =>
            name
                .replace(/[-_]+/g, " ")
                .replace(/\s+/g, " ")
                .replace(/\b\d+\.\s*/g, "")
                .trim();

        const refillSongs = () => {
            if (!songQueue.length) {
                songQueue = shuffle(playlist);
            }
        };

        const refillNews = () => {
            if (!newsQueue.length) {
                newsQueue = shuffle(noticiasAudio);
            }
        };

        const setPlayerState = (mode, badge, headline) => {
            playerMode.textContent = mode;
            playerBadge.textContent = badge;
            if (headline) {
                currentHeadline.textContent = headline;
            }
        };

        const cancelVoice = () => {
            if ("speechSynthesis" in window) {
                speechSynthesis.cancel();
            }
            voiceAudio.pause();
            voiceAudio.removeAttribute("src");
            voiceAudio.load();
            if (resolveCurrentVoice) {
                resolveCurrentVoice();
                resolveCurrentVoice = null;
            }
            if (currentVoiceUrl) {
                URL.revokeObjectURL(currentVoiceUrl);
                currentVoiceUrl = "";
            }
        };

        const beginTransition = () => {
            transitionToken += 1;
            return transitionToken;
        };

        const stopPlayback = () => {
            beginTransition();
            playerActive = false;
            currentPhase = "idle";
            smartAudio.pause();
            smartAudio.currentTime = 0;
            progressBar.style.width = "0%";
            currentSong = null;
            currentNews = null;
            cancelVoice();
            setPlayerState("Player em espera", "Pausado", "Pressione Player para retomar a sequencia de musicas e noticias.");
            currentTrack.textContent = playlist.length ? "Pronto para tocar novamente" : "Nenhuma faixa encontrada na pasta midia";
            playerMetaLeft.textContent = playlist.length ? "Playlist randomica" : "Adicione arquivos mp3 na pasta midia";
            playerVoiceStatus.textContent = canSpeak ? "Leitura automatica interrompida" : "Leitura por voz indisponivel neste navegador";
        };

        const playSong = (song, autostart = false, registerHistory = true) => {
            if (!song) {
                return;
            }

            currentPhase = "song";
            currentSong = song;
            currentNews = null;
            progressBar.style.width = "0%";

            if (registerHistory) {
                songHistory.push(song);
            }

            currentTrack.textContent = formatTrackName(song.nome);
            playerMetaLeft.textContent = "Tocando musica aleatoria";
            playerVoiceStatus.textContent = "Proxima leitura entre faixas";
            setPlayerState("Tocando agora", "No ar", currentHeadline.textContent);
            smartAudio.src = song.url;

            const playAttempt = smartAudio.play();
            if (playAttempt && typeof playAttempt.catch === "function") {
                playAttempt.catch(() => {
                    playerActive = false;
                    setPlayerState("Autoplay bloqueado", "Interacao", "O navegador bloqueou o autoplay. Clique em Player para iniciar.");
                    playerMetaLeft.textContent = "Autoplay aguardando clique";
                    playerVoiceStatus.textContent = canSpeak ? "Leitura automatica aguardando inicio" : "Leitura por voz indisponivel neste navegador";
                    currentTrack.textContent = formatTrackName(song.nome);
                    if (registerHistory && songHistory[songHistory.length - 1] === song) {
                        songHistory.pop();
                    }
                });
            }
        };

        const playNextSong = (autostart = false) => {
            if (!playlist.length) {
                stopPlayback();
                currentTrack.textContent = "Nenhuma faixa encontrada na pasta midia";
                playerMetaLeft.textContent = "Adicione musicas em midia/";
                playerBadge.textContent = "Sem audio";
                return;
            }

            refillSongs();
            playSong(songQueue.pop(), autostart, true);
        };

        const playNeuralVoice = async (texto, token) => {
            playerVoiceStatus.textContent = "Gerando voz Antonio Neural";

            const response = await fetch(neuralVoiceEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    text: texto,
                    voice: neuralVoiceName,
                }),
            });

            if (!response.ok) {
                throw new Error("Falha ao gerar audio neural");
            }

            const audioBlob = await response.blob();
            if (!playerActive || token !== transitionToken) {
                return;
            }

            if (currentVoiceUrl) {
                URL.revokeObjectURL(currentVoiceUrl);
            }

            currentVoiceUrl = URL.createObjectURL(audioBlob);
            voiceAudio.src = currentVoiceUrl;

            await new Promise((resolve) => {
                const finish = () => {
                    if (resolveCurrentVoice === finish) {
                        resolveCurrentVoice = null;
                    }
                    resolve();
                };
                resolveCurrentVoice = finish;
                voiceAudio.onended = finish;
                voiceAudio.onerror = finish;
                const playAttempt = voiceAudio.play();
                if (playAttempt && typeof playAttempt.catch === "function") {
                    playAttempt.catch(finish);
                }
            });

            if (currentVoiceUrl) {
                URL.revokeObjectURL(currentVoiceUrl);
                currentVoiceUrl = "";
            }
        };

        const speakNewsItem = async (noticia, registerHistory = true, token = transitionToken) => {
            if (!playerActive || !noticia || !canSpeak || token !== transitionToken) {
                return;
            }

            const texto = `Noticia em destaque. ${noticia.titulo}. Categoria ${noticia.categoria || "Geral"}. Publicada em ${noticia.data}.`;

            currentPhase = "news";
            currentNews = noticia;
            if (registerHistory) {
                newsHistory.push(noticia);
            }
            setPlayerState("Leitura de noticia", "Noticia", noticia.titulo);
            playerVoiceStatus.textContent = "Lendo noticia com Antonio Neural";
            cancelVoice();
            try {
                await playNeuralVoice(texto, token);
            } catch (error) {
                playerVoiceStatus.textContent = "Voz Antonio Neural indisponivel";
            }
        };

        const speakRandomNews = (token = transitionToken) => {
            if (!playerActive || !noticiasAudio.length || !canSpeak) {
                return Promise.resolve();
            }

            refillNews();
            return speakNewsItem(newsQueue.pop(), true, token);
        };

        const startPlayback = (autostart = false) => {
            if (!playlist.length) {
                stopPlayback();
                return;
            }

            playerActive = true;
            cancelVoice();

            if (smartAudio.src && smartAudio.paused && currentSong) {
                const resumeAttempt = smartAudio.play();
                if (resumeAttempt && typeof resumeAttempt.catch === "function") {
                    resumeAttempt.catch(() => playNextSong(autostart));
                }
                return;
            }

            playNextSong(autostart);
        };

        const goNext = () => {
            beginTransition();
            if (!playerActive) {
                playerActive = true;
            }

            if (currentPhase === "news") {
                cancelVoice();
                speakRandomNews(transitionToken).then(() => {
                    if (playerActive && currentPhase === "news") {
                        setTimeout(() => {
                            if (playerActive && currentPhase === "news") {
                                playNextSong(false);
                            }
                        }, 1200);
                    }
                });
                return;
            }

            smartAudio.pause();
            smartAudio.currentTime = 0;
            playNextSong(false);
        };

        const goPrevious = () => {
            beginTransition();
            if (!playerActive) {
                playerActive = true;
            }

            if (currentPhase === "news") {
                cancelVoice();
                if (newsHistory.length > 1) {
                    newsHistory.pop();
                    speakNewsItem(newsHistory[newsHistory.length - 1], false, transitionToken).then(() => {
                        if (playerActive && currentPhase === "news") {
                            setTimeout(() => {
                                if (playerActive && currentPhase === "news") {
                                    playNextSong(false);
                                }
                            }, 1200);
                        }
                    });
                    return;
                }
                speakRandomNews(transitionToken);
                return;
            }

            smartAudio.pause();
            smartAudio.currentTime = 0;
            if (songHistory.length > 1) {
                songHistory.pop();
                playSong(songHistory[songHistory.length - 1], false, false);
                return;
            }
            playNextSong(false);
        };

        prevButton.addEventListener("click", goPrevious);
        startButton.addEventListener("click", () => startPlayback(false));
        stopButton.addEventListener("click", stopPlayback);
        nextButton.addEventListener("click", goNext);

        const hoverStart = () => {
            document.body.classList.add("hover-cue");
            startPlayback(false);
        };
        document.body.addEventListener("pointerenter", hoverStart, { once: true });
        document.body.addEventListener("click", hoverStart, { once: true });

        smartAudio.addEventListener("play", () => {
            playerActive = true;
            setPlayerState("Tocando agora", "No ar", currentHeadline.textContent);
            playerMetaRight.textContent = `${playlist.length} faixas`;
        });

        smartAudio.addEventListener("timeupdate", () => {
            if (!smartAudio.duration) {
                progressBar.style.width = "0%";
                return;
            }
            const progress = (smartAudio.currentTime / smartAudio.duration) * 100;
            progressBar.style.width = `${progress}%`;
        });

        smartAudio.addEventListener("ended", async () => {
            const token = beginTransition();
            progressBar.style.width = "100%";
            await speakRandomNews(token);
            if (!playerActive || token !== transitionToken) {
                return;
            }
            setTimeout(() => {
                if (playerActive && token === transitionToken) {
                    playNextSong(false);
                }
            }, 1200);
        });

        smartAudio.addEventListener("error", () => {
            beginTransition();
            currentHeadline.textContent = "Nao foi possivel reproduzir esta faixa. Pulando para a proxima.";
            setTimeout(() => {
                if (playerActive) {
                    playNextSong(false);
                }
            }, 800);
        });

        window.addEventListener("load", () => {
            currentTrack.textContent = playlist.length
                ? `Pronto para tocar ${playlist.length} faixas locais`
                : "Nenhuma musica encontrada na pasta midia";
            if (!canSpeak) {
                playerVoiceStatus.textContent = "Leitura por voz indisponivel neste navegador";
            }
            setTimeout(() => startPlayback(true), 900);
        });

        // ================= TEMPO =================
        fetch("https://api.open-meteo.com/v1/forecast?latitude=-7.83&longitude=-34.90&current=temperature_2m&timezone=America/Recife")
            .then(r => r.json())
            .then(d => {
                document.getElementById("tempNow").textContent = d.current.temperature_2m + "°C";
                const tempCard = document.getElementById("tempCard");
                if (tempCard) {
                    tempCard.textContent = `${d.current.temperature_2m}°C em Igarassu`;
                }
            });

        // ================= DATA =================
        document.getElementById("currentDate").textContent =
            new Date().toLocaleString("pt-BR");
        const currentDateCard = document.getElementById("currentDateCard");
        if (currentDateCard) {
            currentDateCard.textContent = new Date().toLocaleDateString("pt-BR", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            });
        }

        // ================= FOTOS =================
        fetch("https://commons.wikimedia.org/w/api.php?origin=*&action=query&generator=categorymembers&gcmtitle=Category:Igarassu&gcmtype=file&gcmlimit=6&prop=imageinfo&iiprop=url&format=json")
            .then(r => r.json())
            .then(d => {
                if (!d.query) return;
                const imgs = Object.values(d.query.pages)
                    .map(p => `<img src="${p.imageinfo[0].url}">`)
                    .join("");
                document.getElementById("photoGrid").innerHTML = imgs;
            });
    </script>

</body>

</html>
