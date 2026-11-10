
<?php
// tests/FinancaTest.php

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class FinancaTest extends TestCase
{
    private function criarApp()
    {
        return require __DIR__ . '/../src/app.php';
    }

    private function criarRequest($metodo, $uri, $dados = null)
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($metodo, $uri);

        if ($dados !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($dados);
        }

        return $request;
    }

    public function testGetListaProdutos(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/produtos');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertCount(4, $corpo);
        $this->assertEquals('Teclado', $corpo[0]['nome']);
    }

    public function testGetProdutoPorId(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/produtos/2');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertEquals(2, $corpo['id']);
        $this->assertEquals('Mouse', $corpo['nome']);
    }

    public function testGetProdutoInexistente(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/produtos/99');
        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertEquals(
            'Produto não encontrado',
            $corpo['erro']
        );
    }

    public function testPostCriaProduto(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'POST',
            '/produtos',
            ['nome' => 'Webcam']
        );

        $response = $app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertEquals('Webcam', $corpo['nome']);
        $this->assertEquals(5, $corpo['id']);
    }

    public function testGetFiltraProdutosPorNome(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'GET',
            '/produtos?nome=mouse'
        );

        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertCount(1, $corpo);
        $this->assertEquals('Mouse', $corpo[0]['nome']);
    }

    public function testPutAtualizaProduto(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'PUT',
            '/produtos/1',
            ['nome' => 'Teclado Mecânico']
        );

        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $corpo = json_decode((string) $response->getBody(), true);

        $this->assertEquals('Teclado Mecânico', $corpo['nome']);
    }

    public function testDeleteRemoveProduto(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('DELETE', '/produtos/1');
        $response = $app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());

        $request = $this->criarRequest('GET', '/produtos/1');
        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteCategoriaComLancamentoVinculadoRetornaConflito(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Categoria sem vínculo', 'tipo' => 'despesa']
        );
        $response = $app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $categoria = json_decode((string) $response->getBody(), true);

        $request = $this->criarRequest('POST', '/lancamento', ['categoria_id' => $categoria['id']]);
        $response = $app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $request = $this->criarRequest('GET', '/categoria/1');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->criarRequest('DELETE', '/categoria/' . $categoria['id']);
        $response = $app->handle($request);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals(
            'Não é possível excluir uma categoria que possui lançamentos vinculados',
            json_decode((string) $response->getBody(), true)['erro']
        );

        $request = $this->criarRequest('GET', '/categoria/' . $categoria['id']);
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteCategoriaSemLancamentosVinculados(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Categoria sem lançamento', 'tipo' => 'despesa']
        );
        $response = $app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $categoria = json_decode((string) $response->getBody(), true);

        $request = $this->criarRequest('DELETE', '/categoria/' . $categoria['id']);
        $response = $app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testConsultarCategoriaNaoAlteraListaDeCategorias(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/categoria/1');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->criarRequest('GET', '/categoria');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(4, json_decode((string) $response->getBody(), true));
    }

    public function testGetLancamentosListaEConsultaPorId(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/lancamento');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(4, json_decode((string) $response->getBody(), true));

        $request = $this->criarRequest('GET', '/lancamento/1');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, json_decode((string) $response->getBody(), true)['id']);
    }
}
