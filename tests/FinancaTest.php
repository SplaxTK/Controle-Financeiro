<?php

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class FinancaTest extends TestCase
{
    private function criarApp()
    {
        return require __DIR__ . '/../src/app.php';
    }

    private function criarRequest(string $metodo, string $uri, ?array $dados = null)
    {
        $request = (new ServerRequestFactory())->createServerRequest($metodo, $uri);

        if ($dados !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($dados)));
        }

        return $request;
    }

    private function corpoJson($response): array
    {
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        return json_decode((string) $response->getBody(), true);
    }

    private function assertErro($response, int $status, string $mensagem): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame(['erro' => $mensagem], $this->corpoJson($response));
    }

    public function testGetStatusRetornaSucesso(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/status'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => 'ok'], $this->corpoJson($response));
    }

    public function testGetCategoriasRetornaLista(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/categoria'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            ['id' => 1, 'nome' => 'Salário', 'tipo' => 'receita'],
            ['id' => 2, 'nome' => 'Aluguel', 'tipo' => 'despesa'],
            ['id' => 3, 'nome' => 'Vendas', 'tipo' => 'receita'],
            ['id' => 4, 'nome' => 'Compras', 'tipo' => 'despesa'],
        ], $this->corpoJson($response));
    }

    public function testGetCategoriaPorIdRetornaCategoria(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/categoria/1'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => 1, 'nome' => 'Salário', 'tipo' => 'receita'],
            $this->corpoJson($response)
        );
    }

    public function testGetCategoriaInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/categoria/99'));

        $this->assertErro($response, 404, 'Categoria não encontrada');
    }

    public function testPostCategoriaCriaComCamposValidos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Investimentos', 'tipo' => 'receita']
        ));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 5, 'nome' => 'Investimentos', 'tipo' => 'receita'],
            $this->corpoJson($response)
        );
    }

    public function testPostCategoriaRejeitaCamposObrigatoriosAusentes(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Investimentos']
        ));

        $this->assertErro($response, 400, 'nome e tipo são obrigatórios');
    }

    public function testPostCategoriaRejeitaTipoInvalido(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Investimentos', 'tipo' => 'transferencia']
        ));

        $this->assertErro($response, 422, 'tipo deve ser receita ou despesa');
    }

    public function testPostCategoriaRejeitaNomeDuplicado(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => ' salário ', 'tipo' => 'receita']
        ));

        $this->assertErro($response, 422, 'Já existe uma categoria com esse nome');
    }

    public function testPutCategoriaAtualizaComCamposValidos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/categoria/1',
            ['nome' => 'Salário atualizado', 'tipo' => 'receita']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => 1, 'nome' => 'Salário atualizado', 'tipo' => 'receita'],
            $this->corpoJson($response)
        );
    }

    public function testPutCategoriaRejeitaCamposObrigatoriosAusentes(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/categoria/1',
            ['nome' => 'Nome sem tipo']
        ));

        $this->assertErro($response, 400, 'nome e tipo são obrigatórios');
    }

    public function testPutCategoriaRejeitaNomeDuplicado(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/categoria/1',
            ['nome' => 'Aluguel', 'tipo' => 'receita']
        ));

        $this->assertErro($response, 422, 'Já existe uma categoria com esse nome');
    }

    public function testPutCategoriaInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/categoria/99',
            ['nome' => 'Categoria', 'tipo' => 'despesa']
        ));

        $this->assertErro($response, 404, 'Categoria não encontrada');
    }

    public function testDeleteCategoriaSemLancamentosRemoveRecurso(): void
    {
        $app = $this->criarApp();
        $respostaCriacao = $app->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Temporária', 'tipo' => 'despesa']
        ));
        $this->assertSame(201, $respostaCriacao->getStatusCode());
        $categoria = $this->corpoJson($respostaCriacao);

        $response = $app->handle($this->criarRequest('DELETE', '/categoria/' . $categoria['id']));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());

        $response = $app->handle($this->criarRequest('GET', '/categoria/' . $categoria['id']));
        $this->assertErro($response, 404, 'Categoria não encontrada');
    }

    public function testDeleteCategoriaComLancamentoVinculadoRetornaConflito(): void
    {
        $app = $this->criarApp();
        $respostaCriacao = $app->handle($this->criarRequest(
            'POST',
            '/categoria',
            ['nome' => 'Vinculada', 'tipo' => 'despesa']
        ));
        $this->assertSame(201, $respostaCriacao->getStatusCode());
        $categoria = $this->corpoJson($respostaCriacao);

        $respostaLancamento = $app->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => $categoria['id'],
                'data' => '2023-02-01',
                'valor' => 100,
                'tipo' => 'despesa',
            ]
        ));
        $this->assertSame(201, $respostaLancamento->getStatusCode());
        $this->assertSame(
            [
                'id' => 5,
                'descricao' => '',
                'valor' => 100,
                'data' => '2023-02-01',
                'tipo' => 'despesa',
                'categoria_id' => $categoria['id'],
            ],
            $this->corpoJson($respostaLancamento)
        );

        $response = $app->handle($this->criarRequest('DELETE', '/categoria/' . $categoria['id']));
        $this->assertErro(
            $response,
            409,
            'Não é possível excluir uma categoria que possui lançamentos vinculados'
        );
    }

    public function testDeleteCategoriaInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('DELETE', '/categoria/99'));

        $this->assertErro($response, 404, 'Categoria não encontrada');
    }

    public function testGetLancamentosRetornaLista(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/lancamento'));

        $this->assertSame(200, $response->getStatusCode());
        $lancamentos = $this->corpoJson($response);
        $this->assertCount(4, $lancamentos);
        $this->assertSame([
            'id' => 1,
            'descricao' => 'Salário de Janeiro',
            'valor' => 5000,
            'data' => '2023-01-15',
            'tipo' => 'receita',
            'categoria_id' => 1,
        ], $lancamentos[0]);
    }

    public function testGetLancamentoPorIdRetornaLancamento(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/lancamento/1'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'id' => 1,
            'descricao' => 'Salário de Janeiro',
            'valor' => 5000,
            'data' => '2023-01-15',
            'tipo' => 'receita',
            'categoria_id' => 1,
        ], $this->corpoJson($response));
    }

    public function testGetLancamentoInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/lancamento/99'));

        $this->assertErro($response, 404, 'Lançamento não encontrado');
    }

    public function testGetLancamentosFiltraPorPeriodoCategoriaETipo(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-01-10&data_final=2023-01-20&categoria_id=2&tipo=despesa'
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([2], array_column($this->corpoJson($response), 'id'));
    }

    public function testGetLancamentosRejeitaFiltroInvalido(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'GET',
            '/lancamento?data_inicial=2023-02-01&data_final=2023-01-01'
        ));

        $this->assertErro(
            $response,
            400,
            'Informe datas válidas no formato AAAA-MM-DD e um período coerente'
        );
    }

    public function testGetLancamentosPorDataRetornaCorrespondencia(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'GET',
            '/lancamento/2023/01/15'
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([1], array_column($this->corpoJson($response), 'id'));
    }

    public function testGetLancamentosPorDataInvalidaRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'GET',
            '/lancamento/2023/02/30'
        ));

        $this->assertErro($response, 400, 'Data inválida');
    }

    public function testGetLancamentosPorDataSemResultadosRetornaListaVazia(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'GET',
            '/lancamento/2023/02/01'
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->corpoJson($response));
    }

    public function testPostLancamentoCriaComDadosValidos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 1,
                'descricao' => 'Salário de fevereiro',
                'valor' => 5000,
                'data' => '2023-02-01',
                'tipo' => 'receita',
            ]
        ));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'id' => 5,
            'descricao' => 'Salário de fevereiro',
            'valor' => 5000,
            'data' => '2023-02-01',
            'tipo' => 'receita',
            'categoria_id' => 1,
        ], $this->corpoJson($response));
    }

    public function testPostLancamentoRejeitaCategoriaAusente(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            ['descricao' => 'Lançamento sem categoria']
        ));

        $this->assertErro(
            $response,
            400,
            'categoria_id é obrigatório e deve ser um inteiro positivo'
        );
    }

    public function testPostLancamentoRejeitaCategoriaInexistente(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 99,
                'data' => '2023-02-01',
                'valor' => 100,
                'tipo' => 'receita',
            ]
        ));

        $this->assertErro($response, 404, 'Categoria não encontrada');
    }

    public function testPostLancamentoRejeitaDataInvalida(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 1,
                'data' => '2023-02-30',
                'valor' => 100,
                'tipo' => 'receita',
            ]
        ));

        $this->assertErro($response, 422, 'data deve ser uma data válida no formato AAAA-MM-DD');
    }

    public function testPostLancamentoRejeitaDataAusente(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            ['categoria_id' => 1, 'valor' => 100, 'tipo' => 'receita']
        ));

        $this->assertErro($response, 400, 'data é obrigatória');
    }

    public function testPostLancamentoRejeitaValorAusente(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            ['categoria_id' => 1, 'data' => '2023-02-01', 'tipo' => 'receita']
        ));

        $this->assertErro($response, 400, 'valor é obrigatório');
    }

    public function testPostLancamentoRejeitaValorNaoPositivoOuNaoNumerico(): void
    {
        foreach ([0, -1, 'invalido'] as $valor) {
            $response = $this->criarApp()->handle($this->criarRequest(
                'POST',
                '/lancamento',
                [
                    'categoria_id' => 1,
                    'data' => '2023-02-01',
                    'valor' => $valor,
                    'tipo' => 'receita',
                ]
            ));

            $this->assertErro($response, 422, 'valor deve ser um número maior que zero');
        }
    }

    public function testPostLancamentoRejeitaTipoAusenteOuInvalido(): void
    {
        $semTipo = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            ['categoria_id' => 1, 'data' => '2023-02-01', 'valor' => 100]
        ));
        $this->assertErro($semTipo, 400, 'tipo é obrigatório');

        $tipoInvalido = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 1,
                'data' => '2023-02-01',
                'valor' => 100,
                'tipo' => 'transferencia',
            ]
        ));
        $this->assertErro($tipoInvalido, 422, 'tipo deve ser receita ou despesa');
    }

    public function testPostLancamentoRejeitaTipoDiferenteDaCategoria(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'POST',
            '/lancamento',
            [
                'categoria_id' => 1,
                'data' => '2023-02-01',
                'valor' => 100,
                'tipo' => 'despesa',
            ]
        ));

        $this->assertErro(
            $response,
            422,
            'tipo do lançamento deve corresponder ao tipo da categoria'
        );
    }

    public function testPutCategoriaNaoPermiteMudarTipoComLancamentos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/categoria/1',
            ['nome' => 'Salário', 'tipo' => 'despesa']
        ));

        $this->assertErro(
            $response,
            409,
            'Não é possível alterar o tipo de uma categoria que possui lançamentos'
        );
    }

    public function testPutLancamentoAtualizaComDadosValidos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/lancamento/1',
            [
                'descricao' => 'Salário atualizado',
                'valor' => 5200,
                'data' => '2023-02-01',
                'tipo' => 'receita',
                'categoria_id' => 1,
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'id' => 1,
            'descricao' => 'Salário atualizado',
            'valor' => 5200,
            'data' => '2023-02-01',
            'tipo' => 'receita',
            'categoria_id' => 1,
        ], $this->corpoJson($response));
    }

    public function testPutLancamentoRejeitaDadosInvalidos(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/lancamento/1',
            [
                'descricao' => 'Valor inválido',
                'valor' => 0,
                'data' => '2023-02-01',
                'tipo' => 'receita',
                'categoria_id' => 1,
            ]
        ));

        $this->assertErro($response, 422, 'valor deve ser um número maior que zero');
    }

    public function testPutLancamentoInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest(
            'PUT',
            '/lancamento/99',
            [
                'descricao' => 'Atualização',
                'valor' => 100,
                'data' => '2023-02-01',
                'tipo' => 'receita',
                'categoria_id' => 1,
            ]
        ));

        $this->assertErro($response, 404, 'Lançamento não encontrado');
    }

    public function testDeleteLancamentoRemoveRecurso(): void
    {
        $app = $this->criarApp();

        $response = $app->handle($this->criarRequest('DELETE', '/lancamento/1'));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());

        $response = $app->handle($this->criarRequest('GET', '/lancamento/1'));
        $this->assertErro($response, 404, 'Lançamento não encontrado');
    }

    public function testDeleteLancamentoInexistenteRetornaErro(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('DELETE', '/lancamento/99'));

        $this->assertErro($response, 404, 'Lançamento não encontrado');
    }

    public function testGetSaldoRetornaTotal(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/saldo'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['saldo' => 4700], $this->corpoJson($response));
    }

    public function testGetReceitasRetornaTotal(): void
    {
        $response = $this->criarApp()->handle($this->criarRequest('GET', '/receitas'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['total_receitas' => 7000], $this->corpoJson($response));
    }
}
