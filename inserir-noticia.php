
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Seu CSS -->
  <link href="../styles.css" rel="stylesheet">
</head>
<body>
   <center> <label for="json"><pre><textarea id="jsonInput" rows="10"  name="json" placeholder="Cole aqui o JSON de notícias...">Você é um bot de notícias especializado em Igarassu, abreu e lima, itapissuma e ilha de itamaraca que ficam em Pernambuco.

Sempre que for acionado, você deve processar notícias dos ultimos 30 dias sobre Igarassu e as cidades messionada, fornecidas ao seu contexto por APIs, RSS de sites, blogs locais, instagran, facebook, twiter e fontes confiáveis já atualizadas.

Regras obrigatórias de saída:

Mostrar uma lista de 50 registros de noticias

O resumo de cada noticia deve ser o mais detalhado possivel;

A resposta DEVE ser exclusivamente em JSON válido

Não escreva texto fora do JSON

Cada notícia deve ser um objeto separado dentro de um array

Cada objeto representa uma única notícia

Caso não existam notícias nas últimas 24h, retorne um array vazio


Estrutura fixa do JSON:

{
  "cidade": "Igarassu-PE",
  "periodo": "últimas 30 dias",
  "noticias": [
    {
      "titulo": "",
      "resumo": "",
      "fonte": "",
      "data_publicacao": "",
      "link": "",
      "imagem": ""
    }
  ]
}


Regras de conteúdo:

Apenas notícias reais, recentes e relacionadas a Igarassu

Remover duplicidades

Linguagem jornalística, clara e objetiva

Não inventar dados

Não emitir opinião

Preencher link com a URL da notícia quando disponível

Preencher imagem com a URL da imagem principal da notícia quando disponível; caso contrário, usar string vazia ("")

Retorne somente o JSON, sem explicações adicionais.</textarea></pre></label></center>





<?php
// ===================================================
// inserir-noticia.php
// - insere notícias via JSON
// - sem verificação manual de duplicidade
// - banco decide (UNIQUE / regras)
// - erro em uma NÃO impede as outras
// ===================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexao.php'; // deve criar $conn (mysqli)

$SENHA_FIXA = '1234'; // 🔐 senha mocada
$mensagem = "";
$falhas = [];

// ===============================
// SLUG
// ===============================
function gerarSlug($texto) {
    $texto = trim($texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

// ===============================
// DATA BR → MYSQL
// ===============================
function converterData($dataBR) {
    if (!$dataBR) return date('Y-m-d H:i:s');
    $p = explode('/', $dataBR);
    if (count($p) !== 3) return date('Y-m-d H:i:s');
    return "{$p[2]}-{$p[1]}-{$p[0]} 00:00:00";
}

// ===============================
// COLUNAS DA TABELA
// ===============================
function getColunas($conn) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM noticias");
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r['Field'];
    }
    return $cols;
}

// ===============================
// PROCESSA POST
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $senha = $_POST['senha'] ?? '';
    $jsonTexto = $_POST['json'] ?? '';

    if ($senha !== $SENHA_FIXA) {
        $mensagem = "❌ Senha incorreta.";
    } else {

        $dados = json_decode($jsonTexto, true);

        if (!$dados || !isset($dados['noticias'])) {
            $mensagem = "❌ JSON inválido.";
        } else {

            $conn->set_charset('utf8mb4');
            $colunas = getColunas($conn);

            $ok = 0;
            $erro = 0;

            foreach ($dados['noticias'] as $i => $n) {

                $titulo = trim($n['titulo'] ?? '');
                $resumo = trim($n['resumo'] ?? '');

                if ($titulo === '' || $resumo === '') {
                    $erro++;
                    $falhas[] = "Item ".($i+1).": título ou resumo vazio";
                    continue;
                }

                $slug   = gerarSlug($titulo);
                $fonte  = $n['fonte'] ?? '';
                $link   = $n['link'] ?? '';
                $imagem = $n['imagem'] ?? '';
                $data   = converterData($n['data_publicacao'] ?? '');

                // monta insert dinâmico
                $f = [];
                $p = [];
                $t = '';
                $v = [];

                if (in_array('titulo', $colunas)) {
                    $f[]='titulo'; $p[]='?'; $t.='s'; $v[]=$titulo;
                }
                if (in_array('slug', $colunas)) {
                    $f[]='slug'; $p[]='?'; $t.='s'; $v[]=$slug;
                }
                if (in_array('texto', $colunas)) {
                    $f[]='texto'; $p[]='?'; $t.='s'; $v[]=$resumo;
                }
                if (in_array('fonte', $colunas)) {
                    $f[]='fonte'; $p[]='?'; $t.='s'; $v[]=$fonte;
                }
                if (in_array('noticia_url', $colunas)) {
                    $f[]='noticia_url'; $p[]='?'; $t.='s'; $v[]=$link;
                }
                if (in_array('imagem_url', $colunas)) {
                    $f[]='imagem_url'; $p[]='?'; $t.='s'; $v[]=$imagem;
                }
                if (in_array('publicado_em', $colunas)) {
                    $f[]='publicado_em'; $p[]='?'; $t.='s'; $v[]=$data;
                }
                if (in_array('status', $colunas)) {
                    $f[]='status'; $p[]='?'; $t.='s'; $v[]='publicado';
                }

                if (!$f) {
                    $erro++;
                    $falhas[] = "Item ".($i+1).": nenhuma coluna compatível";
                    continue;
                }

                $sql = "INSERT INTO noticias (".implode(',',$f).") VALUES (".implode(',',$p).")";
                $stmt = $conn->prepare($sql);

                try {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($t, ...$v);

                    // ✅ execute protegido individualmente
                    $stmt->execute();

                    $ok++;
                    $stmt->close();

                } catch (mysqli_sql_exception $e) {

                    // ✅ DUPLICADO (UNIQUE) -> IGNORA e segue o baile
                    if ((int)$e->getCode() === 1062) {
                        $duplicadas++;
                        // não conta como erro e não mata o processo
                        continue;
                    }

                    // 🔴 outro erro -> registra
                    $erro++;
                    $falhas[] = "Item ".($i+1).": ".$e->getMessage();
                }
            }

            $mensagem = "✅ Inseridas: $ok | ❌ Falharam: $erro";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Inserir Notícias</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial;background:#f2f4f8;margin:0}
.box{max-width:980px;margin:24px auto;background:#fff;padding:18px;border-radius:12px}
textarea{width:100%;min-height:360px;font-family:monospace;padding:12px}
button{background:#1976d2;color:#fff;border:0;padding:12px 18px;border-radius:8px}
.msg{margin-top:10px;font-weight:bold}
.err{background:#fff3f3;margin-top:10px;padding:10px;border-radius:8px}
</style>
<script>
function pedirSenha(){
  const s = prompt("Digite a senha de 4 números:");
  if(!s) return false;
  document.getElementById('senha').value = s;
  return true;
}
</script>
</head>
<body>

<div class="box">
<h2>📰 Inserir Notícias via JSON</h2>

<form method="post" onsubmit="return pedirSenha();">
<textarea name="json"><?= htmlspecialchars($_POST['json'] ?? '') ?></textarea>
<input type="hidden" name="senha" id="senha">
<br><br>
<button>Inserir</button>
</form>

<?php if ($mensagem): ?>
<div class="msg"><?= $mensagem ?></div>
<?php endif; ?>

<?php if ($falhas): ?>
<div class="err">
<b>Falhas:</b>
<ul>
<?php foreach ($falhas as $f): ?>
<li><?= htmlspecialchars($f) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
</div>

</body>
</html>
