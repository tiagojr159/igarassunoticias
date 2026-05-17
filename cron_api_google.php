<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =========================================
   CONFIG
========================================= */
$arquivoData = __DIR__ . '/ultima_execucao_google.txt';
$arquivoApi  = __DIR__ . '/api_google.php';
$dataHoje    = date('Y-m-d');

/* =========================================
   CRON POR ACESSO
========================================= */
function executarUmaVezPorDia($arquivoData, $dataHoje, $arquivoApi)
{
    if (!file_exists($arquivoData)) {
        file_put_contents($arquivoData, '');
    }

    $ultimaData = trim(file_get_contents($arquivoData));

    if ($ultimaData === $dataHoje) {
      //  echo "⏭️ Já executado hoje ({$dataHoje}).";
        return;
    }

    // echo "▶️ Executando ingestão de notícias...\n\n";

    // executa o script COMPLETO
    require $arquivoApi;

    // salva data atual
    file_put_contents($arquivoData, $dataHoje);

   //  echo "\n\n✅ Execução registrada para {$dataHoje}.";
}

/* =========================================
   EXECUÇÃO
========================================= */
executarUmaVezPorDia($arquivoData, $dataHoje, $arquivoApi);
