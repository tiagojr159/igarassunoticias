<?php
error_reporting(0);

/**
 * NOTÍCIAS IGARASSU (arquivo único)
 * - Página HTML (canvas) + API JSON (?api=1)
 * - Agrega múltiplas fontes (RSS/Atom + Google News RSS + Bing RSS + NE10 + G1-PE)
 * - Filtra por Igarassu, deduplica e ordena por data
 */

header_remove('X-Powered-By');

/* =========================
   CONFIG
   ========================= */
const MAX_ITEMS  = 10;   // quantas notícias mostrar no planeta
const CACHE_TTL  = 60;   // cache da API (segundos) para não sobrecarregar as fontes

// Palavras-chave (ajuste livre)
const KEYWORDS = [
  'igarassu',
  'igarassu pe',
  'igarassu - pe',
  'igarassu pernambuco',
  'sítio histórico de igarassu',
  'sitio historico de igarassu',
  'cruz de rebouças',
  'cruz de reboucas'
];

/* =========================
   UTIL: HTTP
   ========================= */
function curl_get($url)
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) IgarassuNewsBot/1.0',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => '',
  ]);
  $data = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($data === false || $http >= 400) return null;
  return $data;
}

function normalize_txt($s)
{
  $s = (string)$s;
  $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $s = strip_tags($s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
}

function matches_igarassu($title, $link, $source = '')
{
  $hay = normalize_txt($title . ' ' . $link . ' ' . $source);
  foreach (KEYWORDS as $k) {
    if (mb_strpos($hay, normalize_txt($k)) !== false) return true;
  }
  return false;
}

/* =========================
   FEEDS (RSS/ATOM)
   ========================= */
function parse_feed_xml($xmlString)
{
  $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
  if (!$xml) return [];

  $items = [];

  // RSS
  if (isset($xml->channel->item)) {
    foreach ($xml->channel->item as $it) {
      $title = trim((string)$it->title);
      $link  = trim((string)$it->link);
      $date  = (string)$it->pubDate;

      // fallback: dc:date
      if (!$date) {
        $dc = $it->children('http://purl.org/dc/elements/1.1/');
        if (isset($dc->date)) $date = (string)$dc->date;
      }

      $ts = $date ? strtotime($date) : time();
      $items[] = ['title' => $title, 'link' => $link, 'ts' => (int)$ts];
    }
    return $items;
  }

  // Atom
  if (isset($xml->entry)) {
    foreach ($xml->entry as $entry) {
      $title = trim((string)$entry->title);

      $link = '';
      if ($entry->link) {
        foreach ($entry->link as $lnk) {
          $attrs = $lnk->attributes();
          $rel = (string)($attrs['rel'] ?? '');
          if ($rel === '' || $rel === 'alternate') {
            $link = (string)($attrs['href'] ?? '');
            break;
          }
        }
      }

      $date = (string)$entry->updated ?: (string)$entry->published;
      $ts   = $date ? strtotime($date) : time();

      $items[] = ['title' => $title, 'link' => $link, 'ts' => (int)$ts];
    }
    return $items;
  }

  return [];
}

/* =========================
   GOOGLE NEWS RSS (busca)
   ========================= */
function google_news_rss($query)
{
  // pt-BR / BR
  $q = urlencode($query);
  return "https://news.google.com/rss/search?q={$q}&hl=pt-BR&gl=BR&ceid=BR:pt-419";
}

/* =========================
   WP JSON (Prefeitura) - opcional
   ========================= */
function fetch_wp_posts($baseUrl)
{
  // Se o site for WordPress e estiver com REST ligado, isso funciona sem token
  $url = rtrim($baseUrl, '/') . "/wp-json/wp/v2/posts?per_page=20&_fields=link,title,date_gmt,modified_gmt";
  $json = curl_get($url);
  if (!$json) return [];

  $arr = json_decode($json, true);
  if (!is_array($arr)) return [];

  $items = [];
  foreach ($arr as $p) {
    $t = '';
    if (isset($p['title']) && is_array($p['title'])) $t = $p['title']['rendered'] ?? '';
    $title = trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $link  = trim((string)($p['link'] ?? ''));

    $date = (string)($p['date_gmt'] ?? '') ?: (string)($p['modified_gmt'] ?? '');
    $ts = $date ? strtotime($date . 'Z') : time();

    if ($title && $link) {
      $items[] = ['title' => $title, 'link' => $link, 'ts' => (int)$ts];
    }
  }

  return $items;
}

/* =========================
   FONTES (adicione mais aqui)
   ========================= */
function get_sources()
{
  return [
    // Melhor cobertura (agregador de várias mídias):
    ['name' => 'Google News (Igarassu)',                 'type' => 'rss', 'url' => google_news_rss('Igarassu Pernambuco')],
    ['name' => 'Google News (Prefeitura de Igarassu)',   'type' => 'rss', 'url' => google_news_rss('"Prefeitura de Igarassu"')],
    ['name' => 'Google News (site igarassu.pe.gov.br)',  'type' => 'rss', 'url' => google_news_rss('Igarassu site:igarassu.pe.gov.br')],

    // Alternativa agregadora:
    ['name' => 'Bing News (Igarassu)',                   'type' => 'rss', 'url' => 'https://www.bing.com/news/search?q=' . urlencode('Igarassu') . '&format=rss'],

    // Portais PE (vem muita coisa, então filtra por Igarassu):
    ['name' => 'NE10 (home)',                            'type' => 'rss', 'url' => 'https://ne10.uol.com.br/rss/home/rss.xml', 'force_filter' => true],
    ['name' => 'G1 Pernambuco',                          'type' => 'rss', 'url' => 'https://g1.globo.com/dynamo/pernambuco/rss2.xml', 'force_filter' => true],

    // Câmara de Igarassu (pode falhar dependendo do bloqueio do servidor, mas fica como extra):
    ['name' => 'Câmara de Igarassu (notícias)',          'type' => 'rss', 'url' => 'https://www.igarassu.pe.leg.br/institucional/noticias/RSS', 'force_filter' => true, 'optional' => true],

    // Prefeitura (se for WordPress com REST API habilitada):
    ['name' => 'Prefeitura de Igarassu (WP JSON)',       'type' => 'wp',  'base' => 'https://igarassu.pe.gov.br', 'optional' => true],

    // Instagram SEM token: só via terceiros (RSSHub/Picuki) e pode cair por anti-crawling
    // LIGUE SOMENTE SE QUISER TESTAR (troque disabled => false)
    ['name' => 'Instagram Prefeitura (RSSHub/Picuki)',   'type' => 'rss', 'url' => 'https://rsshub.app/picuki/profile/prefeituradeigarassu', 'force_filter' => false, 'optional' => true, 'disabled' => true],
  ];
}

/* =========================
   AGREGAÇÃO
   ========================= */
function aggregate_news()
{
  $sources = get_sources();
  $map = [];

  foreach ($sources as $src) {
    if (!empty($src['disabled'])) continue;

    $got = [];

    if ($src['type'] === 'wp') {
      $got = fetch_wp_posts($src['base']);
    } else {
      $xml = curl_get($src['url']);
      if (!$xml) continue;
      $got = parse_feed_xml($xml);
    }

    foreach ($got as $it) {
      $title = trim((string)($it['title'] ?? ''));
      $link  = trim((string)($it['link'] ?? ''));
      $ts    = (int)($it['ts'] ?? time());

      if (!$title || !$link) continue;

      // filtro forte quando a fonte é geral
      $needFilter = !empty($src['force_filter']);

      // Google News já é “busca”, mas mantemos um filtro leve por segurança
      if ($needFilter || strpos($src['url'] ?? '', 'news.google.com/rss/search') !== false) {
        if (!matches_igarassu($title, $link, $src['name'])) continue;
      } else {
        // por padrão, ainda filtra para não entrar lixo
        if (!matches_igarassu($title, $link, $src['name'])) continue;
      }

      $key = md5(normalize_txt($link));
      if (isset($map[$key])) {
        if ($ts > $map[$key]['data']) $map[$key]['data'] = $ts;
        continue;
      }

      $map[$key] = [
        'titulo' => $title,
        'link'   => $link,
        'data'   => $ts,
        'fonte'  => $src['name'],
      ];
    }
  }

  $items = array_values($map);
  usort($items, fn($a, $b) => $b['data'] <=> $a['data']);
  return array_slice($items, 0, MAX_ITEMS);
}

/* =========================
   CACHE (arquivo local)
   ========================= */
function cache_path()
{
  return __DIR__ . '/cache_igarassu.json';
}

function cache_read()
{
  $p = cache_path();
  if (!file_exists($p)) return null;

  $raw = @file_get_contents($p);
  if (!$raw) return null;

  $obj = json_decode($raw, true);
  if (!is_array($obj) || empty($obj['t']) || !isset($obj['data'])) return null;

  if (time() - (int)$obj['t'] > CACHE_TTL) return null;
  return $obj['data'];
}

function cache_write($data)
{
  $p = cache_path();
  @file_put_contents(
    $p,
    json_encode(['t' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
}

/* =========================
   API JSON
   ========================= */
if (isset($_GET['api']) && $_GET['api'] == '1') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

  $data = cache_read();
  if (!$data) {
    $data = aggregate_news();
    cache_write($data);
  }

  echo json_encode([
    'ok' => true,
    'count' => count($data),
    'items' => $data,
    'generated_at' => time()
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Igarassu • Notícias em Tempo Real</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
      font-family: Arial, sans-serif;
      overflow: hidden;
    }

    .container { text-align: center; position: relative; }

    canvas {
      border-radius: 50%;
      box-shadow: 0 0 50px rgba(100, 149, 237, 0.3),
                  0 0 100px rgba(100, 149, 237, 0.2),
                  inset 0 0 30px rgba(0, 0, 0, 0.3);
      animation: float 6s ease-in-out infinite;
      cursor: grab;
      user-select: none;
    }
    canvas:active { cursor: grabbing; }

    @keyframes float {
      0%, 100% { transform: translateY(0px) scale(1); }
      50% { transform: translateY(-10px) scale(1.02); }
    }

    .stars {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      z-index: -1;
    }
    .star {
      position: absolute;
      background: white;
      border-radius: 50%;
      animation: twinkle 3s infinite alternate;
    }
    @keyframes twinkle {
      0% { opacity: 0.3; }
      100% { opacity: 1; }
    }

    .label {
      position: absolute;
      color: #FFD700;
      background: rgba(0, 0, 0, 0.85);
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 14px;
      width: 320px;
      white-space: normal;
      word-wrap: break-word;
      animation: pulse 4s infinite;
      text-align: left;
      box-shadow: 0 2px 6px rgba(0,0,0,0.4);
    }
    @keyframes pulse {
      0% { transform: scale(1); opacity: 0.9; }
      50% { transform: scale(1.05); opacity: 1; }
      100% { transform: scale(1); opacity: 0.9; }
    }

    .label a { pointer-events: auto; color: inherit; text-decoration: none; }
    .label a:hover { text-decoration: underline; }

    .info { color: white; margin-top: 30px; font-size: 14px; opacity: 0.85; }

    .moon-button {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(180px, -180px);
      width: 100px;
      height: 100px;
      background: url('https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/FullMoon2010.jpg/100px-FullMoon2010.jpg') no-repeat center center / cover;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      z-index: 1;
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
    }

    @media (max-width: 768px) {
      canvas { width: 90vw !important; height: 90vw !important; }
      .label { font-size: 12px; width: 240px; padding: 4px 8px; }
      .info { font-size: 12px; margin-top: 20px; }
      .moon-button { width: 60px; height: 60px; transform: translate(120px, -120px); }
    }
    @media (max-width: 480px) {
      .label { width: 190px; font-size: 11px; }
      .moon-button { width: 50px; height: 50px; transform: translate(100px, -100px); }
    }
  </style>
</head>
<body>
  <div class="stars" id="stars"></div>

  <div class="container">
    <canvas id="earthCanvas" width="800" height="800" style="max-width: 100%; height: auto;"></canvas>

    <div class="info">
      🌍 Igarassu • Manchetes recentes<br>
      <small>☕ Arraste para girar • 🔊 Clique na lua para ouvir todas</small>
    </div>

    <button class="moon-button" onclick="ouvirTodas()" title="Ouvir Manchetes"></button>

    <?php for ($i = 0; $i < MAX_ITEMS; $i++): ?>
      <div class="label" id="label<?= $i ?>"></div>
    <?php endfor; ?>
  </div>

  <script>
    const API_URL = "<?= htmlspecialchars(basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>?api=1";

    const canvas = document.getElementById('earthCanvas');
    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = 370;

    let rotation = 0;
    let isDragging = false;
    let lastMouseX = 0;

    let earthImage = new Image();
    earthImage.crossOrigin = 'anonymous';
    earthImage.src = 'https://i0.wp.com/narceliodesa.com/wp-content/uploads/2019/10/11.jpg?fit=750%2C410&ssl=1';

    const labels = Array.from({ length: <?= MAX_ITEMS ?> }, (_, i) => document.getElementById('label' + i));
    const labelRadius = radius + 130;

    let noticiasAtual = [];
    let ultimaNoticiaFaladaKey = "";

    function createStars() {
      const starsContainer = document.getElementById('stars');
      for (let i = 0; i < 100; i++) {
        const star = document.createElement('div');
        star.className = 'star';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.width = star.style.height = (Math.random() * 3 + 1) + 'px';
        star.style.animationDelay = Math.random() * 3 + 's';
        starsContainer.appendChild(star);
      }
    }

    function drawEarth() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      ctx.save();
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
      ctx.clip();

      const imgW = radius * 2.5;
      const offsetX = (rotation * 100) % imgW;

      ctx.translate(centerX, centerY);
      ctx.drawImage(earthImage, -offsetX - imgW / 2, -imgW / 2, imgW, imgW);
      ctx.drawImage(earthImage, -offsetX + imgW / 2, -imgW / 2, imgW, imgW);
      ctx.restore();

      const gradient = ctx.createRadialGradient(centerX, centerY, radius * 0.8, centerX, centerY, radius * 1.2);
      gradient.addColorStop(0, 'rgba(100, 149, 237, 0)');
      gradient.addColorStop(1, 'rgba(100, 149, 237, 0.4)');
      ctx.fillStyle = gradient;
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius * 1.2, 0, Math.PI * 2);
      ctx.fill();
    }

    function animate() {
      if (!isDragging) rotation += 0.005;
      drawEarth();

      labels.forEach((label, i) => {
        const angle = -rotation + i * ((2 * Math.PI) / labels.length);
        const x = centerX + labelRadius * Math.cos(angle);
        const y = centerY + labelRadius * Math.sin(angle) * 0.6;

        label.style.left = `${x + canvas.offsetLeft - label.offsetWidth / 2}px`;
        label.style.top  = `${y + canvas.offsetTop - label.offsetHeight / 2}px`;
      });

      requestAnimationFrame(animate);
    }

    // drag mouse/touch
    canvas.addEventListener('mousedown', (e) => { isDragging = true; lastMouseX = e.clientX; });
    canvas.addEventListener('mousemove', (e) => {
      if (isDragging) { const dx = e.clientX - lastMouseX; rotation += dx * 0.01; lastMouseX = e.clientX; }
    });
    canvas.addEventListener('mouseup',   () => isDragging = false);
    canvas.addEventListener('mouseleave',() => isDragging = false);

    canvas.addEventListener('touchstart', (e) => { isDragging = true; lastMouseX = e.touches[0].clientX; }, {passive:true});
    canvas.addEventListener('touchmove', (e) => {
      if (isDragging) { const dx = e.touches[0].clientX - lastMouseX; rotation += dx * 0.01; lastMouseX = e.touches[0].clientX; }
    }, {passive:true});
    canvas.addEventListener('touchend', () => isDragging = false);

    function tempoDecorrido(ts) {
      const agora = Math.floor(Date.now() / 1000);
      const diff = agora - ts;
      if (diff < 60) return 'há poucos segundos';
      if (diff < 3600) return `há ${Math.floor(diff / 60)} minutos`;
      if (diff < 86400) return `há ${Math.floor(diff / 3600)} horas`;
      return `há ${Math.floor(diff / 86400)} dias`;
    }

    function setLabel(i, item) {
      const cor = (i === 0) ? '#FFD700' : 'white';
      const tempo = tempoDecorrido(item.data);
      const fonte = item.fonte ? ` • <small style="opacity:.85">${item.fonte}</small>` : '';

      labels[i].innerHTML = `
        <a href="${item.link}" target="_blank" style="color:${cor};">
          ${item.titulo}
          <small style="opacity:.85">(${tempo})</small>
          ${fonte}
        </a>
      `;
    }

    async function carregarNoticias() {
      try {
        const res = await fetch(API_URL, { cache: 'no-store' });
        const json = await res.json();
        if (!json || !json.ok) return;

        noticiasAtual = json.items || [];

        // fala a primeira manchete quando mudar
        if (noticiasAtual.length) {
          const key = noticiasAtual[0].link || noticiasAtual[0].titulo;
          if (key && key !== ultimaNoticiaFaladaKey) {
            falarUltimaNoticia(noticiasAtual[0].titulo);
            ultimaNoticiaFaladaKey = key;
          }
        }

        for (let i = 0; i < labels.length; i++) {
          if (noticiasAtual[i]) setLabel(i, noticiasAtual[i]);
          else labels[i].innerHTML = '';
        }
      } catch (e) {
        console.error("Erro ao carregar notícias:", e);
      }
    }

    function falarUltimaNoticia(texto) {
      if (!('speechSynthesis' in window)) return;
      const msg = new SpeechSynthesisUtterance("Última notícia: " + texto);
      msg.lang = 'pt-BR';
      msg.rate = 2.2;
      speechSynthesis.cancel();
      speechSynthesis.speak(msg);
    }

    function ouvirTodas(index = 0) {
      if (!('speechSynthesis' in window)) return;
      if (!noticiasAtual.length) return;

      if (index >= noticiasAtual.length) return;
      const msg = new SpeechSynthesisUtterance(noticiasAtual[index].titulo);
      msg.lang = 'pt-BR';
      msg.rate = 2.2;
      msg.onend = () => ouvirTodas(index + 1);
      speechSynthesis.speak(msg);
    }

    createStars();
    earthImage.onload = () => {
      animate();
      carregarNoticias();              // primeira carga
      setInterval(carregarNoticias, 60_000); // atualiza a cada 60s
    };
  </script>
</body>
</html>
