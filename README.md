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
includes/        Configuração, sessão, conexão PDO e mapeadores legados.
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
