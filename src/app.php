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
$lancamentos = [
['id' => 1, 'descricao' => 'Salário de Janeiro', 'valor' => 5000, 'data' => '2023-01-15', 'categoria_id' => 1],
['id' => 2, 'descricao' => 'Aluguel de Janeiro', 'valor' => 1500, 'data' => '2023-01-10', 'categoria_id' => 2],
['id' => 3, 'descricao' => 'Venda de Produto A', 'valor' => 2000, 'data' => '2023-01-20', 'categoria_id' => 3],
['id' => 4, 'descricao' => 'Compra de Material B', 'valor' => 800, 'data' => '2023-01-25', 'categoria_id' => 4],
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
$app->post('/categoria', function ($request, $response) use (&$categoria) {
    $dados = $request->getParsedBody();
    $novoCategoria = ['id' => count($categoria) + 1, 'nome' => $dados['nome'], 'tipo' => $dados['tipo']];
    $categoria[] = $novoCategoria;
    $response->getBody()->write(json_encode($novoCategoria));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// Cria um lançamento vinculado a uma categoria
$app->post('/lancamento', function ($request, $response) use (&$categoria, &$lancamentos, $validarData) {
    $dados = $request->getParsedBody();
    $categoriaId = is_array($dados) ? filter_var($dados['categoria_id'] ?? null, FILTER_VALIDATE_INT) : false;

    if ($categoriaId === false || $categoriaId === null || $categoriaId < 1) {
        $response->getBody()->write(json_encode(['erro' => 'categoria_id é obrigatório e deve ser um inteiro positivo']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $categoriaExiste = count(array_filter($categoria, fn ($item) => $item['id'] === $categoriaId)) > 0;
    if (!$categoriaExiste) {
        $response->getBody()->write(json_encode(['erro' => 'Categoria não encontrada']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $novoLancamento = ['id' => count($lancamentos) + 1, 'categoria_id' => $categoriaId];
    if (array_key_exists('data', $dados)) {
        if (!$validarData($dados['data'])) {
            $response->getBody()->write(json_encode(['erro' => 'data deve ser uma data válida no formato AAAA-MM-DD']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $novoLancamento['data'] = $dados['data'];
    }

    foreach (['descricao', 'valor'] as $campo) {
        if (array_key_exists($campo, $dados)) {
            $novoLancamento[$campo] = $dados[$campo];
        }
    }

    $lancamentos[] = $novoLancamento;
    $response->getBody()->write(json_encode($novoLancamento));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

//PUT
$app->put('/categoria/{id}', function ($request, $response, $args) use (&$categoria) {
$dados = $request->getParsedBody();
foreach ($categoria as &$item) {
if ($item['id'] === (int) $args['id']) {
$item['nome'] = $dados['nome'];
$item['tipo'] = $dados['tipo'];
$response->getBody()->write(json_encode($item));
return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
}
}
return $response->withStatus(404);
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