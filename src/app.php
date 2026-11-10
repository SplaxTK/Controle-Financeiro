<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();

// status
$app->get('/status', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$categoria = [
['id' => 1, 'nome' => 'Salário', 'tipo' => 'receita'],
['id' => 2, 'nome' => 'Aluguel', 'tipo' => 'despesa'],
['id' => 3, 'nome' => 'Vendas', 'tipo' => 'receita'],
['id' => 4, 'nome' => 'Compras', 'tipo' => 'despesa'],
];
$validarData = static function ($data): bool {
    if (!is_string($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return false;
    }

    [$ano, $mes, $dia] = array_map('intval', explode('-', $data));
    return checkdate($mes, $dia, $ano);
};
$validarCategoria = static function ($dados, array $categorias, ?int $categoriaAtualId = null): ?array {
    if (!is_array($dados)
        || !isset($dados['nome'])
        || !is_string($dados['nome'])
        || trim($dados['nome']) === ''
        || !isset($dados['tipo'])) {
        return ['status' => 400, 'mensagem' => 'nome e tipo são obrigatórios'];
    }

    if (!in_array($dados['tipo'], ['receita', 'despesa'], true)) {
        return ['status' => 422, 'mensagem' => 'tipo deve ser receita ou despesa'];
    }

    $nomeNormalizado = function_exists('mb_strtolower')
        ? mb_strtolower(trim($dados['nome']), 'UTF-8')
        : strtolower(trim($dados['nome']));
    foreach ($categorias as $categoriaExistente) {
        $nomeExistente = function_exists('mb_strtolower')
            ? mb_strtolower($categoriaExistente['nome'], 'UTF-8')
            : strtolower($categoriaExistente['nome']);
        if ($categoriaExistente['id'] !== $categoriaAtualId && $nomeExistente === $nomeNormalizado) {
            return ['status' => 422, 'mensagem' => 'Já existe uma categoria com esse nome'];
        }
    }

    return null;
};
$lancamentos = [
['id' => 1, 'descricao' => 'Salário de Janeiro', 'valor' => 5000, 'data' => '2023-01-15', 'tipo' => 'receita', 'categoria_id' => 1],
['id' => 2, 'descricao' => 'Aluguel de Janeiro', 'valor' => 1500, 'data' => '2023-01-10', 'tipo' => 'despesa', 'categoria_id' => 2],
['id' => 3, 'descricao' => 'Venda de Produto A', 'valor' => 2000, 'data' => '2023-01-20', 'tipo' => 'receita', 'categoria_id' => 3],
['id' => 4, 'descricao' => 'Compra de Material B', 'valor' => 800, 'data' => '2023-01-25', 'tipo' => 'despesa', 'categoria_id' => 4],
];
$calcularTotais = static function (array $lancamentos, array $categorias): array {
    $tiposPorCategoria = [];
    foreach ($categorias as $item) {
        $tiposPorCategoria[$item['id']] = $item['tipo'];
    }

    return array_reduce($lancamentos, static function ($totais, $lancamento) use ($tiposPorCategoria) {
        $tipo = $tiposPorCategoria[$lancamento['categoria_id']] ?? null;
        if ($tipo === 'receita' || $tipo === 'despesa') {
            $totais[$tipo] += $lancamento['valor'] ?? 0;
        }

        return $totais;
    }, ['receita' => 0, 'despesa' => 0]);
};
$validarLancamento = static function ($dados, array $categorias) use ($validarData): array {
    if (!is_array($dados)) {
        return ['status' => 400, 'erro' => 'categoria_id é obrigatório e deve ser um inteiro positivo'];
    }

    $categoriaId = filter_var($dados['categoria_id'] ?? null, FILTER_VALIDATE_INT);
    if ($categoriaId === false || $categoriaId === null || $categoriaId < 1) {
        return ['status' => 400, 'erro' => 'categoria_id é obrigatório e deve ser um inteiro positivo'];
    }

    $categoria = current(array_filter($categorias, fn ($item) => $item['id'] === $categoriaId));
    if ($categoria === false) {
        return ['status' => 404, 'erro' => 'Categoria não encontrada'];
    }

    if (!isset($dados['data'])) {
        return ['status' => 400, 'erro' => 'data é obrigatória'];
    }

    if (!$validarData($dados['data'])) {
        return ['status' => 422, 'erro' => 'data deve ser uma data válida no formato AAAA-MM-DD'];
    }

    if (!array_key_exists('valor', $dados)) {
        return ['status' => 400, 'erro' => 'valor é obrigatório'];
    }

    if (!is_numeric($dados['valor']) || !is_finite((float) $dados['valor']) || (float) $dados['valor'] <= 0) {
        return ['status' => 422, 'erro' => 'valor deve ser um número maior que zero'];
    }

    if (!isset($dados['tipo'])) {
        return ['status' => 400, 'erro' => 'tipo é obrigatório'];
    }

    if (!in_array($dados['tipo'], ['receita', 'despesa'], true)) {
        return ['status' => 422, 'erro' => 'tipo deve ser receita ou despesa'];
    }

    if ($dados['tipo'] !== $categoria['tipo']) {
        return ['status' => 422, 'erro' => 'tipo do lançamento deve corresponder ao tipo da categoria'];
    }

    return [
        'descricao' => $dados['descricao'] ?? '',
        'valor' => (float) $dados['valor'],
        'data' => $dados['data'],
        'tipo' => $dados['tipo'],
        'categoria_id' => $categoriaId,
    ];
};

// Get
$app->get('/categoria', function ($request, $response) use (&$categoria) {
    $response->getBody()->write(json_encode($categoria));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->get('/saldo', function ($request, $response) use (&$categoria, &$lancamentos, $calcularTotais) {
    $totais = $calcularTotais($lancamentos, $categoria);
    $saldo = $totais['receita'] - $totais['despesa'];

    $response->getBody()->write(json_encode(['saldo' => $saldo]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->get('/receitas', function ($request, $response) use (&$categoria, &$lancamentos, $calcularTotais) {
    $totais = $calcularTotais($lancamentos, $categoria);

    $response->getBody()->write(json_encode(['total_receitas' => $totais['receita']]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->get('/lancamento', function ($request, $response) use (&$lancamentos, &$categoria, $validarData) {
    $filtros = $request->getQueryParams();
    $dataInicial = $filtros['data_inicial'] ?? null;
    $dataFinal = $filtros['data_final'] ?? null;
    $categoriaId = null;
    $tipo = $filtros['tipo'] ?? null;

    if (($dataInicial !== null && !$validarData($dataInicial))
        || ($dataFinal !== null && !$validarData($dataFinal))
        || ($dataInicial !== null && $dataFinal !== null && $dataInicial > $dataFinal)) {
        $response->getBody()->write(json_encode(['erro' => 'Informe datas válidas no formato AAAA-MM-DD e um período coerente']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    if (isset($filtros['categoria_id'])) {
        $categoriaId = filter_var($filtros['categoria_id'], FILTER_VALIDATE_INT);
        if ($categoriaId === false || $categoriaId < 1) {
            $response->getBody()->write(json_encode(['erro' => 'categoria_id deve ser um inteiro positivo']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    if ($tipo !== null && !in_array($tipo, ['receita', 'despesa'], true)) {
        $response->getBody()->write(json_encode(['erro' => 'tipo deve ser receita ou despesa']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $tiposPorCategoria = [];
    foreach ($categoria as $item) {
        $tiposPorCategoria[$item['id']] = $item['tipo'];
    }

    $lancamentosFiltrados = array_values(array_filter($lancamentos, function ($lancamento) use (
        $dataInicial,
        $dataFinal,
        $categoriaId,
        $tipo,
        $tiposPorCategoria
    ) {
        $data = $lancamento['data'] ?? null;
        if (($dataInicial !== null && ($data === null || $data < $dataInicial))
            || ($dataFinal !== null && ($data === null || $data > $dataFinal))
            || ($categoriaId !== null && $lancamento['categoria_id'] !== $categoriaId)
            || ($tipo !== null && ($tiposPorCategoria[$lancamento['categoria_id']] ?? null) !== $tipo)) {
            return false;
        }

        return true;
    }));

    $response->getBody()->write(json_encode($lancamentosFiltrados));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// Get c/ filtro
$app->get('/categoria/{id}', function ($request, $response, $args) use (&$categoria) {
    $categoriaEncontrada = current(array_filter($categoria, fn ($item) => $item['id'] === (int) $args['id'])) ?: null;
    if (!$categoriaEncontrada) {
        $response->getBody()->write(json_encode(['erro' => 'Categoria não encontrada']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($categoriaEncontrada));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->get('/lancamento/{id}', function ($request, $response, $args) use (&$lancamentos) {
    $lancamentoEncontrado = current(array_filter($lancamentos, fn ($item) => $item['id'] === (int) $args['id'])) ?: null;
    if (!$lancamentoEncontrado) {
        $response->getBody()->write(json_encode(['erro' => 'Lançamento não encontrado']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($lancamentoEncontrado));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->get('/lancamento/{ano}/{mes}/{dia}', function ($request, $response, $args) use (&$lancamentos, $validarData) {
    if (!preg_match('/^\d{4}$/', $args['ano'])
        || !preg_match('/^\d{1,2}$/', $args['mes'])
        || !preg_match('/^\d{1,2}$/', $args['dia'])) {
        $response->getBody()->write(json_encode(['erro' => 'Data inválida']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $data = sprintf('%04d-%02d-%02d', $args['ano'], $args['mes'], $args['dia']);
    if (!$validarData($data)) {
        $response->getBody()->write(json_encode(['erro' => 'Data inválida']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $lancamentosFiltrados = array_values(array_filter(
        $lancamentos,
        fn ($item) => ($item['data'] ?? null) === $data
    ));

    $response->getBody()->write(json_encode($lancamentosFiltrados));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Post
$app->post('/categoria', function ($request, $response) use (&$categoria, $validarCategoria) {
    $dados = $request->getParsedBody();
    $erro = $validarCategoria($dados, $categoria);
    if ($erro !== null) {
        $response->getBody()->write(json_encode(['erro' => $erro['mensagem']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($erro['status']);
    }

    $novoCategoria = [
        'id' => empty($categoria) ? 1 : max(array_column($categoria, 'id')) + 1,
        'nome' => trim($dados['nome']),
        'tipo' => $dados['tipo'],
    ];
    $categoria[] = $novoCategoria;
    $response->getBody()->write(json_encode($novoCategoria));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// Cria um lançamento vinculado a uma categoria
$app->post('/lancamento', function ($request, $response) use (&$categoria, &$lancamentos, $validarLancamento) {
    $novoLancamento = $validarLancamento($request->getParsedBody(), $categoria);
    if (isset($novoLancamento['erro'])) {
        $status = $novoLancamento['status'];
        unset($novoLancamento['status']);
        $response->getBody()->write(json_encode($novoLancamento));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    $novoLancamento = ['id' => empty($lancamentos) ? 1 : max(array_column($lancamentos, 'id')) + 1] + $novoLancamento;
    $lancamentos[] = $novoLancamento;
    $response->getBody()->write(json_encode($novoLancamento));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->put('/lancamento/{id}', function ($request, $response, $args) use (&$categoria, &$lancamentos, $validarLancamento) {
    $lancamentoId = filter_var($args['id'], FILTER_VALIDATE_INT);
    $lancamentoIndice = null;
    foreach ($lancamentos as $indice => $item) {
        if ($lancamentoId !== false && $item['id'] === $lancamentoId) {
            $lancamentoIndice = $indice;
            break;
        }
    }

    if ($lancamentoIndice === null) {
        $response->getBody()->write(json_encode(['erro' => 'Lançamento não encontrado']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $lancamentoAtualizado = $validarLancamento($request->getParsedBody(), $categoria);
    if (isset($lancamentoAtualizado['erro'])) {
        $status = $lancamentoAtualizado['status'];
        unset($lancamentoAtualizado['status']);
        $response->getBody()->write(json_encode($lancamentoAtualizado));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    $lancamentoAtualizado = ['id' => $lancamentoId] + $lancamentoAtualizado;
    $lancamentos[$lancamentoIndice] = $lancamentoAtualizado;
    $response->getBody()->write(json_encode($lancamentoAtualizado));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->delete('/lancamento/{id}', function ($request, $response, $args) use (&$lancamentos) {
    $lancamentoId = filter_var($args['id'], FILTER_VALIDATE_INT);
    foreach ($lancamentos as $indice => $item) {
        if ($lancamentoId !== false && $item['id'] === $lancamentoId) {
            unset($lancamentos[$indice]);
            $lancamentos = array_values($lancamentos);
            return $response->withStatus(204);
        }
    }

    $response->getBody()->write(json_encode(['erro' => 'Lançamento não encontrado']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
});

//PUT
$app->put('/categoria/{id}', function ($request, $response, $args) use (&$categoria, &$lancamentos, $validarCategoria) {
    $categoriaId = filter_var($args['id'], FILTER_VALIDATE_INT);
    $categoriaIndice = null;
    foreach ($categoria as $indice => $item) {
        if ($categoriaId !== false && $item['id'] === $categoriaId) {
            $categoriaIndice = $indice;
            break;
        }
    }

    if ($categoriaIndice === null) {
        $response->getBody()->write(json_encode(['erro' => 'Categoria não encontrada']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $dados = $request->getParsedBody();
    $erro = $validarCategoria($dados, $categoria, $categoriaId);
    if ($erro !== null) {
        $response->getBody()->write(json_encode(['erro' => $erro['mensagem']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($erro['status']);
    }

    $tipoAnterior = $categoria[$categoriaIndice]['tipo'];
    if ($dados['tipo'] !== $tipoAnterior) {
        $possuiLancamentos = count(array_filter(
            $lancamentos,
            fn ($item) => $item['categoria_id'] === $categoriaId
        )) > 0;
        if ($possuiLancamentos) {
            $response->getBody()->write(json_encode(['erro' => 'Não é possível alterar o tipo de uma categoria que possui lançamentos']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
    }

    $categoria[$categoriaIndice]['nome'] = trim($dados['nome']);
    $categoria[$categoriaIndice]['tipo'] = $dados['tipo'];
    $response->getBody()->write(json_encode($categoria[$categoriaIndice]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

//DELETE
$app->delete('/categoria/{id}', function ($request, $response, $args) use (&$categoria, &$lancamentos) {
    $categoriaId = (int) $args['id'];
    $categoriaExiste = count(array_filter($categoria, fn ($item) => $item['id'] === $categoriaId)) > 0;

    if (!$categoriaExiste) {
        $response->getBody()->write(json_encode(['erro' => 'Categoria não encontrada']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $possuiLancamentos = count(array_filter($lancamentos, fn ($item) => $item['categoria_id'] === $categoriaId)) > 0;
    if ($possuiLancamentos) {
        $response->getBody()->write(json_encode(['erro' => 'Não é possível excluir uma categoria que possui lançamentos vinculados']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
    }

    $categoria = array_values(array_filter($categoria, fn ($item) => $item['id'] !== $categoriaId));
    return $response->withStatus(204);
});

if (php_sapi_name() !== 'cli') {
    $app->run();
}

return $app;