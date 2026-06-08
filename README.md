# LabCon

Sistema de gerenciamento de laboratórios, mesas, usuários e reservas.

## Arquitetura

O projeto foi organizado como um MVC simples em PHP:

```text
app/
  Controllers/   Recebem a requisição HTTP e retornam JSON.
  Services/      Concentram regras de negócio e validações.
  Repositories/  Isolam o acesso ao MySQL.
  Support/       Utilitários de request/response e bootstrap.
api/             Endpoints públicos que delegam para Controllers.
includes/        Configuração, sessão, conexão PDO, instalador e mapeadores legados.
src/             JavaScript da interface.
assets/          CSS e arquivos estáticos.
database/        Schema MySQL.
```

Fluxo recomendado:

```text
api/*.php -> Controller -> Service -> Repository -> MySQL
```

Exemplo:

```text
api/reservations.php
  -> App\Controllers\ReservationController
  -> App\Services\ReservationService
  -> App\Repositories\ReservationRepository
```

## Regras

- Controllers não devem conter SQL.
- Repositories não devem conter regra de negócio de tela.
- Services devem concentrar validações importantes, como conflito de horário em reservas.
- Os arquivos em `api/` devem permanecer pequenos, apenas carregando `app/bootstrap.php` e chamando o Controller.

## Primeira execução

Na primeira chamada ao banco, o sistema usa as constantes de `includes/config.php`:

```php
DB_HOST
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
DB_COLLATION
```

Com esses dados, `includes/install.php` conecta ao servidor MySQL, cria o banco `DB_NAME` se ele ainda não existir e cria as tabelas necessárias sem apagar dados existentes.

O instalador também cria um usuário administrador padrão caso ele ainda não exista:

- E-mail: `admin@labcon.local`
- Senha: `Admin@123`

Esse login permite acessar imediatamente a área administrativa após a instalação.

O arquivo `database/schema.sql` continua disponível para instalação manual, mas não é mais obrigatório para o primeiro uso local.
