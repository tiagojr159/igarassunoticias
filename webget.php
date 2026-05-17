<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Igarassu Agora — Tempo, Notícias e Fotos</title>
  <style>
    :root{
      --bg:#0b1020; --card:#121a33; --muted:#9fb0ff; --txt:#e9ecff;
      --line: rgba(255,255,255,.10); --ok:#4ade80; --warn:#fbbf24;
    }
    *{box-sizing:border-box}
    body{
      margin:0; color:var(--txt);
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: radial-gradient(1000px 600px at 10% 0%, #1b2a7a33, transparent 55%),
                  radial-gradient(900px 600px at 90% 10%, #7a1b5a22, transparent 60%),
                  var(--bg);
    }
    header{
      padding:18px 16px;
      border-bottom:1px solid var(--line);
      position: sticky; top:0; backdrop-filter: blur(10px);
      background: linear-gradient(to bottom, rgba(11,16,32,.92), rgba(11,16,32,.65));
      z-index: 5;
    }
    .wrap{max-width:1100px;margin:0 auto}
    h1{margin:0;font-size:20px;letter-spacing:.3px}
    .sub{margin-top:6px;color:var(--muted);font-size:13px;display:flex;gap:10px;flex-wrap:wrap}
    .pill{
      border:1px solid var(--line); padding:6px 10px; border-radius:999px;
      background: rgba(255,255,255,.04);
    }
    main{padding:16px}
    .grid{
      display:grid; gap:14px;
      grid-template-columns: 1.1fr .9fr;
    }
    @media (max-width: 900px){ .grid{grid-template-columns:1fr} }
    .card{
      background: rgba(18,26,51,.86);
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
      box-shadow: 0 18px 50px rgba(0,0,0,.28);
    }
    .card h2{margin:0 0 10px;font-size:16px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .kpi{
      flex:1; min-width:150px;
      border:1px solid var(--line);
      background: rgba(255,255,255,.03);
      border-radius:14px;
      padding:10px 12px;
    }
    .kpi .lbl{font-size:12px;color:var(--muted)}
    .kpi .val{font-size:20px;margin-top:4px}
    .small{font-size:12px;color:var(--muted)}
    .btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    button{
      appearance:none;border:1px solid var(--line);
      background: rgba(255,255,255,.05);
      color:var(--txt);
      padding:10px 12px;border-radius:12px;cursor:pointer;
      font-weight:600;
    }
    button:hover{background: rgba(255,255,255,.08)}
    a{color:#b9c6ff;text-decoration:none}
    a:hover{text-decoration:underline}
    ul{margin:0;padding-left:18px}
    li{margin:10px 0}
    .news-item{
      border-top:1px dashed rgba(255,255,255,.12);
      padding-top:10px;
    }
    .news-item:first-child{border-top:0;padding-top:0}
    .tag{
      display:inline-flex;align-items:center;gap:6px;
      font-size:12px;color:var(--muted);
    }
    .dot{width:8px;height:8px;border-radius:99px;background:var(--warn)}
    .dot.ok{background:var(--ok)}
    .gallery{
      display:grid; gap:10px;
      grid-template-columns: repeat(3, 1fr);
    }
    @media (max-width: 700px){ .gallery{grid-template-columns: repeat(2, 1fr)} }
    @media (max-width: 420px){ .gallery{grid-template-columns: 1fr} }
    figure{margin:0}
    img{
      width:100%;height:160px;object-fit:cover;
      border-radius:14px;border:1px solid var(--line);
      background:#0a0f22;
    }
    figcaption{margin-top:6px;font-size:12px;color:var(--muted);line-height:1.25}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace}
  </style>
</head>

<body>
<header>
  <div class="wrap">
    <h1>Igarassu Agora</h1>
    <div class="sub">
      <span class="pill">📍 Igarassu-PE</span>
      <span class="pill">🌦️ Tempo: Open-Meteo</span>
      <span class="pill">📰 Notícias: Prefeitura + fontes</span>
      <span class="pill">🖼️ Fotos: Wikimedia Commons</span>
      <span class="pill mono" id="lastUpdate">Atualizando…</span>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="grid">

    <!-- NOTÍCIAS -->
    <section class="card">
      <h2>📰 Últimas notícias (Igarassu)</h2>
      <div class="small">
        Coleta automática e atualização a cada <b>2 min</b>. Se alguma fonte bloquear CORS, o proxy público pode falhar.
      </div>

      <div class="btns">
        <button id="btnRefreshNews">Atualizar agora</button>
        <button id="btnToggleFilter" title="Esconde manchetes sensíveis por palavra-chave">Filtro sensível: ON</button>
      </div>

      <div class="tag" style="margin-top:10px">
        <span class="dot" id="newsDot"></span>
        <span id="newsStatus">Carregando…</span>
      </div>

      <div id="newsList" style="margin-top:10px"></div>
    </section>

    <!-- TEMPO -->
    <aside class="card">
      <h2>🌦️ Previsão do tempo (precisão por hora)</h2>
      <div class="row" id="weatherKpis">
        <div class="kpi"><div class="lbl">Agora</div><div class="val">—</div><div class="small">—</div></div>
        <div class="kpi"><div class="lbl">Próx. 3h</div><div class="val">—</div><div class="small">—</div></div>
        <div class="kpi"><div class="lbl">Chuva (próx. 6h)</div><div class="val">—</div><div class="small">—</div></div>
      </div>

      <div class="tag" style="margin-top:10px">
        <span class="dot" id="wxDot"></span>
        <span id="wxStatus">Carregando…</span>
      </div>

      <div class="btns">
        <button id="btnRefreshWx">Atualizar tempo</button>
      </div>

      <div class="small" style="margin-top:10px">
        Coordenadas usadas: <span class="mono" id="coords"></span>
      </div>
    </aside>

  </div>

  <!-- FOTOS -->
  <section class="card" style="margin-top:14px">
    <h2>🖼️ Fotos de Igarassu (Wikimedia Commons)</h2>
    <div class="small">Carrega 12 imagens aleatórias da categoria “Igarassu”. Atualiza a cada <b>10 min</b>.</div>

    <div class="btns">
      <button id="btnRefreshPhotos">Trocar fotos</button>
    </div>

    <div class="tag" style="margin-top:10px">
      <span class="dot" id="phDot"></span>
      <span id="phStatus">Carregando…</span>
    </div>

    <div class="gallery" id="photoGrid" style="margin-top:12px"></div>
  </section>
</main>

<script>
/* ==========================
   CONFIG
   ========================== */
// Igarassu (lat/lon)
const IGARASSU = { lat: -7.83437, lon: -34.90635 };

// Intervalos
const NEWS_REFRESH_MS  = 2 * 60 * 1000;   // 2 min
const WX_REFRESH_MS    = 5 * 60 * 1000;   // 5 min
const PHOTOS_REFRESH_MS= 10 * 60 * 1000;  // 10 min

// Filtro simples para manchetes sensíveis (ajuste como quiser)
let filtroSensivelOn = true;
const SENSITIVE_WORDS = [
  "corpo", "morto", "morta", "homicídio", "assassin", "violência", "violento", "tiro", "crime"
];

// Proxy público para evitar CORS quando a fonte não libera fetch do browser.
// Se você preferir, troque por um endpoint seu (ex.: /proxy.php?url=...)
const PROXY = (url) => `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`;

// Fontes de notícia (você pode adicionar mais URLs)
const NEWS_SOURCES = [
  // Página de notícias da Prefeitura (WordPress)
  { name: "Prefeitura de Igarassu", url: "https://igarassu.pe.gov.br/noticias/" },

  // Exemplo: portal (pode bloquear CORS; aí entra o PROXY)
  // { name: "Diário de Pernambuco (busca)", url: "https://www.diariodepernambuco.com.br/busca/?q=igarassu" },
];

/* ==========================
   HELPERS UI
   ========================== */
function setDot(dotEl, ok){
  dotEl.classList.toggle("ok", !!ok);
}
function setStatus(dotEl, statusEl, ok, text){
  setDot(dotEl, ok);
  statusEl.textContent = text;
}
function setLastUpdate(){
  const d = new Date();
  document.getElementById("lastUpdate").textContent =
    "⏱️ " + d.toLocaleString("pt-BR");
}
document.getElementById("coords").textContent =
  `${IGARASSU.lat.toFixed(5)}, ${IGARASSU.lon.toFixed(5)}`;

/* ==========================
   TEMPO — Open-Meteo
   ========================== */
async function loadWeather(){
  const dot = document.getElementById("wxDot");
  const st  = document.getElementById("wxStatus");
  setStatus(dot, st, false, "Atualizando…");

  // Open-Meteo: dados atuais + previsão por hora
  const url = `https://api.open-meteo.com/v1/forecast?latitude=${IGARASSU.lat}&longitude=${IGARASSU.lon}` +
              `&current=temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation` +
              `&hourly=temperature_2m,precipitation,precipitation_probability,wind_speed_10m` +
              `&timezone=America/Recife`;

  try{
    const r = await fetch(url, { cache: "no-store" });
    if(!r.ok) throw new Error("HTTP " + r.status);
    const data = await r.json();

    const cur = data.current || {};
    const hourly = data.hourly || {};
    const t = (cur.temperature_2m ?? "—") + "°C";
    const hum = (cur.relative_humidity_2m ?? "—") + "%";
    const wind = (cur.wind_speed_10m ?? "—") + " km/h";
    const pnow = (cur.precipitation ?? "—") + " mm";

    // Próximas horas
    const temps = hourly.temperature_2m || [];
    const prcp  = hourly.precipitation || [];
    const prob  = hourly.precipitation_probability || [];

    const next3h = (temps.slice(1,4).filter(v=>v!=null).reduce((a,b)=>a+b,0) / Math.max(1, temps.slice(1,4).filter(v=>v!=null).length));
    const next3hTxt = Number.isFinite(next3h) ? (next3h.toFixed(1) + "°C") : "—";

    const prcp6 = prcp.slice(1,7).filter(v=>v!=null).reduce((a,b)=>a+b,0);
    const prcp6Txt = Number.isFinite(prcp6) ? (prcp6.toFixed(1) + " mm") : "—";

    const maxProb6 = Math.max(...prob.slice(1,7).filter(v=>v!=null));
    const probTxt = Number.isFinite(maxProb6) ? (maxProb6 + "%") : "—";

    // Render KPIs
    const kpis = document.getElementById("weatherKpis");
    kpis.innerHTML = `
      <div class="kpi">
        <div class="lbl">Agora</div>
        <div class="val">${t}</div>
        <div class="small">Umidade ${hum} · Vento ${wind} · Chuva ${pnow}</div>
      </div>
      <div class="kpi">
        <div class="lbl">Próx. 3h</div>
        <div class="val">${next3hTxt}</div>
        <div class="small">Tendência média (horária)</div>
      </div>
      <div class="kpi">
        <div class="lbl">Chuva (próx. 6h)</div>
        <div class="val">${prcp6Txt}</div>
        <div class="small">Prob. máx.: ${probTxt}</div>
      </div>
    `;

    setStatus(dot, st, true, "OK (Open-Meteo)");
    setLastUpdate();
  }catch(err){
    console.error(err);
    setStatus(dot, st, false, "Falha ao carregar tempo");
  }
}

/* ==========================
   NOTÍCIAS — raspagem simples
   ========================== */
function isSensitiveTitle(title){
  const t = (title || "").toLowerCase();
  return SENSITIVE_WORDS.some(w => t.includes(w));
}

function normalizeWhitespace(s){
  return (s || "").replace(/\s+/g, " ").trim();
}

async function fetchHtml(url){
  // tenta direto; se falhar por CORS, usa PROXY
  try{
    const r = await fetch(url, { cache: "no-store" });
    if(!r.ok) throw new Error("HTTP " + r.status);
    return await r.text();
  }catch(_){
    const r2 = await fetch(PROXY(url), { cache: "no-store" });
    if(!r2.ok) throw new Error("Proxy HTTP " + r2.status);
    return await r2.text();
  }
}

function parsePrefeituraNoticias(html, baseUrl){
  const doc = new DOMParser().parseFromString(html, "text/html");
  // WordPress: normalmente títulos ficam em links dentro de article
  const anchors = Array.from(doc.querySelectorAll("a"))
    .map(a => ({
      title: normalizeWhitespace(a.textContent),
      href: a.href || a.getAttribute("href") || ""
    }))
    .filter(x => x.title && x.title.length > 18)
    .filter(x => x.href && x.href.startsWith("http"))
    .filter(x => x.href.includes("igarassu.pe.gov.br"))
    .filter(x => !x.href.includes("/wp-content/"))
    .slice(0, 15);

  // Dedup por href
  const seen = new Set();
  const items = [];
  for(const a of anchors){
    if(seen.has(a.href)) continue;
    seen.add(a.href);
    items.push({
      fonte: "Prefeitura de Igarassu",
      titulo: a.title,
      link: a.href
    });
  }
  return items;
}

async function loadNews(){
  const dot = document.getElementById("newsDot");
  const st  = document.getElementById("newsStatus");
  const list= document.getElementById("newsList");
  setStatus(dot, st, false, "Atualizando…");
  list.innerHTML = "";

  try{
    let all = [];

    for(const src of NEWS_SOURCES){
      const html = await fetchHtml(src.url);
      if(src.url.includes("igarassu.pe.gov.br/noticias")){
        all = all.concat(parsePrefeituraNoticias(html, src.url));
      }
      // Se você adicionar outras fontes, crie outros parsers aqui
    }

    // Dedup por link
    const byLink = new Map();
    for(const it of all){
      if(!byLink.has(it.link)) byLink.set(it.link, it);
    }
    let items = Array.from(byLink.values()).slice(0, 12);

    if(filtroSensivelOn){
      items = items.filter(it => !isSensitiveTitle(it.titulo));
    }

    if(items.length === 0){
      list.innerHTML = `<div class="small">Nenhuma notícia encontrada (ou filtrada).</div>`;
      setStatus(dot, st, true, "OK (vazio)");
      setLastUpdate();
      return;
    }

    list.innerHTML = items.map(it => `
      <div class="news-item">
        <div style="font-weight:800; margin-bottom:6px;">${escapeHtml(it.titulo)}</div>
        <div class="small">
          Fonte: <b>${escapeHtml(it.fonte)}</b> ·
          <a href="${it.link}" target="_blank" rel="noopener">abrir</a>
        </div>
      </div>
    `).join("");

    setStatus(dot, st, true, `OK (${items.length} itens)`);
    setLastUpdate();
  }catch(err){
    console.error(err);
    list.innerHTML = `<div class="small">Falha ao carregar notícias. Dica: crie um <span class="mono">proxy.php</span> no seu servidor para evitar bloqueios.</div>`;
    setStatus(dot, st, false, "Falha ao carregar notícias");
  }
}

function escapeHtml(str){
  return (str || "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

/* ==========================
   FOTOS — Wikimedia Commons
   ========================== */
async function loadPhotos(){
  const dot = document.getElementById("phDot");
  const st  = document.getElementById("phStatus");
  const grid= document.getElementById("photoGrid");
  setStatus(dot, st, false, "Atualizando…");
  grid.innerHTML = "";

  // MediaWiki API (CORS OK) — categoria: Igarassu
  const api = "https://commons.wikimedia.org/w/api.php?origin=*";
  const url = api + "&action=query&format=json" +
    "&generator=categorymembers&gcmtitle=Category:Igarassu" +
    "&gcmtype=file&gcmlimit=50" +
    "&prop=imageinfo&iiprop=url|extmetadata&iiurlwidth=700";

  try{
    const r = await fetch(url, { cache: "no-store" });
    if(!r.ok) throw new Error("HTTP " + r.status);
    const data = await r.json();

    const pages = data?.query?.pages ? Object.values(data.query.pages) : [];
    const files = pages
      .map(p => {
        const ii = (p.imageinfo && p.imageinfo[0]) ? p.imageinfo[0] : null;
        const meta = ii?.extmetadata || {};
        return {
          title: p.title?.replace(/^File:/,"") || "Imagem",
          thumb: ii?.thumburl || ii?.url || "",
          pageUrl: "https://commons.wikimedia.org/wiki/" + encodeURIComponent(p.title || ""),
          author: meta?.Artist?.value ? stripTags(meta.Artist.value) : "",
          license: meta?.LicenseShortName?.value ? stripTags(meta.LicenseShortName.value) : ""
        };
      })
      .filter(f => f.thumb);

    // pega 12 aleatórias
    const picked = shuffle(files).slice(0, 12);

    grid.innerHTML = picked.map(f => `
      <figure>
        <a href="${f.pageUrl}" target="_blank" rel="noopener">
          <img loading="lazy" src="${f.thumb}" alt="${escapeHtml(f.title)}" />
        </a>
        <figcaption>
          <div style="font-weight:700">${escapeHtml(f.title)}</div>
          <div>${escapeHtml([f.author, f.license].filter(Boolean).join(" · "))}</div>
        </figcaption>
      </figure>
    `).join("");

    setStatus(dot, st, true, `OK (${picked.length} fotos)`);
    setLastUpdate();
  }catch(err){
    console.error(err);
    setStatus(dot, st, false, "Falha ao carregar fotos");
    grid.innerHTML = `<div class="small">Falha ao carregar as fotos do Wikimedia.</div>`;
  }
}

function stripTags(html){
  const d = new DOMParser().parseFromString(html, "text/html");
  return (d.body.textContent || "").trim();
}
function shuffle(arr){
  const a = arr.slice();
  for(let i=a.length-1;i>0;i--){
    const j = Math.floor(Math.random()*(i+1));
    [a[i],a[j]] = [a[j],a[i]];
  }
  return a;
}

/* ==========================
   BOOT
   ========================== */
document.getElementById("btnRefreshWx").addEventListener("click", loadWeather);
document.getElementById("btnRefreshNews").addEventListener("click", loadNews);
document.getElementById("btnRefreshPhotos").addEventListener("click", loadPhotos);

document.getElementById("btnToggleFilter").addEventListener("click", (e) => {
  filtroSensivelOn = !filtroSensivelOn;
  e.target.textContent = `Filtro sensível: ${filtroSensivelOn ? "ON" : "OFF"}`;
  loadNews();
});

loadWeather();
loadNews();
loadPhotos();

setInterval(loadNews, NEWS_REFRESH_MS);
setInterval(loadWeather, WX_REFRESH_MS);
setInterval(loadPhotos, PHOTOS_REFRESH_MS);
</script>
</body>
</html>
