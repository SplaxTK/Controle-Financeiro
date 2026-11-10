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

// Get
$app->get('/categoria', function ($request, $response) use (&$categoria) {
    $response->getBody()->write(json_encode($categoria));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// Get c/ filtro
$app->get('/categoria/{id}', function ($request, $response, $args) use (&$categoria) {
$categoria = current(array_filter($categoria, fn ($p) => $p['id'] === (int) $args['id'])) ?: null;
if (!$categoria) {
$response->getBody()->write(json_encode(['erro' => 'Categoria não encontrada']));
return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
}
$response->getBody()->write(json_encode($categoria));
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
$app->delete('/categoria/{id}', function ($request, $response, $args) use (&$categoria) {
$categoria = array_values(array_filter($categoria, fn ($p) => $p['id'] !== (int) $args['id']));
return $response->withStatus(204);
});

if (php_sapi_name() !== 'cli') {
    $app->run();
}

return $app;