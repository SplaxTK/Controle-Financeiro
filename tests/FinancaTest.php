
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

    public function testGetLancamentosFiltraPorPeriodo(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-01-15&data_final=2023-01-20'
        );
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $lancamentos = json_decode((string) $response->getBody(), true);
        $this->assertSame([1, 3], array_column($lancamentos, 'id'));
    }

    public function testGetLancamentosFiltraPorCategoriaETipo(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-01-01&data_final=2023-01-20&categoria_id=2&tipo=despesa'
        );
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $lancamentos = json_decode((string) $response->getBody(), true);
        $this->assertSame([2], array_column($lancamentos, 'id'));

        $request = $this->criarRequest(
            'GET',
            '/lancamento?categoria_id=2&tipo=receita'
        );
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], json_decode((string) $response->getBody(), true));

        $request = $this->criarRequest('GET', '/lancamento?tipo=receita');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $lancamentos = json_decode((string) $response->getBody(), true);
        $this->assertSame([1, 3], array_column($lancamentos, 'id'));
    }

    public function testGetLancamentosPorDataNaUrl(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest('GET', '/lancamento/2023/01/15');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $lancamentos = json_decode((string) $response->getBody(), true);
        $this->assertSame([1], array_column($lancamentos, 'id'));
    }

    public function testGetLancamentosRejeitaPeriodoInvalido(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-02-01&data_final=2023-01-01'
        );
        $response = $app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertArrayHasKey(
            'erro',
            json_decode((string) $response->getBody(), true)
        );
    }

    public function testLancamentoCriadoComDataPodeSerFiltrado(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 1,
                'descricao' => 'Salário de fevereiro',
                'valor' => 5000,
                'data' => '2023-02-01',
            ]
        );
        $response = $app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $request = $this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-02-01&data_final=2023-02-28'
        );
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $lancamentos = json_decode((string) $response->getBody(), true);
        $this->assertCount(1, $lancamentos);
        $this->assertSame('Salário de fevereiro', $lancamentos[0]['descricao']);
    }

    public function testGetSaldoEGetReceitasCalculamPelosTiposDasCategorias(): void
    {
        $app = $this->criarApp();

        $response = $app->handle($this->criarRequest('GET', '/saldo'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['saldo' => 4700],
            json_decode((string) $response->getBody(), true)
        );

        $response = $app->handle($this->criarRequest('GET', '/receitas'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['total_receitas' => 7000],
            json_decode((string) $response->getBody(), true)
        );
    }

    public function testGetSaldoEGetReceitasConsideramNovosLancamentos(): void
    {
        $app = $this->criarApp();

        $request = $this->criarRequest(
            'POST',
            '/lancamento',
            ['categoria_id' => 2, 'valor' => 100]
        );
        $this->assertEquals(201, $app->handle($request)->getStatusCode());

        $response = $app->handle($this->criarRequest('GET', '/saldo'));
        $this->assertEquals(4600, json_decode((string) $response->getBody(), true)['saldo']);

        $response = $app->handle($this->criarRequest('GET', '/receitas'));
        $this->assertEquals(7000, json_decode((string) $response->getBody(), true)['total_receitas']);
    }
}
