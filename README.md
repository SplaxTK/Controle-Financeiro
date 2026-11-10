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
