-Get /status
![alt text](./img/image.png)

-Get /categoria
![alt text](./img/image2.png)

-Get /categoria/{id}
![alt text](./img/image3.png)

-Get /lancamento
![alt text](./img/image6.png)

-Get /lancamento{id}
![alt text](./img/image7.png)

-Put /categoria/{id}
-Body -Raw
{
    "nome": "Aaa",
    "tipo": "despesa"
}
![alt text](./img/image4.png)

-Put /lancamento
-Body -Raw
{
    "id": 1,
    "descricao": "Salário de Fevereiro",
    "valor": 5000,
    "categoria_id": 1
}
![alt text](./img/image8.png)

-delete /categoria/{id}
![alt text](./img/image5.png)

`GET /lancamento` aceita filtros combináveis por período, categoria e tipo:

- `data_inicial` e `data_final`: datas no formato `AAAA-MM-DD` (inclusive).
- `categoria_id`: ID da categoria.
- `tipo`: `receita` ou `despesa` (tipo definido pela categoria).

Exemplo: `GET /lancamento?data_inicial=2023-01-10&data_final=2023-01-20&categoria_id=1&tipo=receita`.
![alt text](./img/image9.png)
ou
Exemplo: `GET /lancamento/2023/01/15`
![alt text](./img/image10.png)

Envie o campo `data` no `POST /lancamento`, no formato `AAAA-MM-DD`.
![alt text](./img/image11.png)

Os totais podem ser consultados em `GET /saldo` e `GET /receitas`. O tipo de cada
lançamento é obtido a partir do tipo da categoria vinculada.

Resulmo dos resultados
-Get /saldo
![alt text](./img/image12.png)

-Get /receitas
![alt text](./img/image13.png)

