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
$lancamentos = [
['id' => 1, 'descricao' => 'Salário de Janeiro', 'valor' => 5000, 'categoria_id' => 1],
['id' => 2, 'descricao' => 'Aluguel de Janeiro', 'valor' => 1500, 'categoria_id' => 2],
['id' => 3, 'descricao' => 'Venda de Produto A', 'valor' => 2000, 'categoria_id' => 3],
['id' => 4, 'descricao' => 'Compra de Material B', 'valor' => 800, 'categoria_id' => 4],
];

// Get
$app->get('/categoria', function ($request, $response) use (&$categoria) {
    $response->getBody()->write(json_encode($categoria));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->get('/lancamento', function ($request, $response) use (&$lancamentos) {
    $response->getBody()->write(json_encode($lancamentos));
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

// Post
$app->post('/categoria', function ($request, $response) use (&$categoria) {
    $dados = $request->getParsedBody();
    $novoCategoria = ['id' => count($categoria) + 1, 'nome' => $dados['nome'], 'tipo' => $dados['tipo']];
    $categoria[] = $novoCategoria;
    $response->getBody()->write(json_encode($novoCategoria));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// Cria um lançamento vinculado a uma categoria
$app->post('/lancamento', function ($request, $response) use (&$categoria, &$lancamentos) {
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