## API V2 do Projeto EtnoJuris

Desenvolvida por Rodrigo B. S. Orrego e Cassius Correa

## Tabelas

As tabelas abaixo podem ser chamadas nos endpoints da API utilizando ou os nomes verdadeiros delas (no lado esquerdo) ou utilizando as palavras-chave (no lado direito), abaixo:
(Obs: as tabelas que não iniciam com TB são tabelas que foram originadas por outros scripts (ex. o que baixa dados da JusBrasil, o que gera a lista de tribunais com códigos, etc.) ou tabelas do próprio Laravel)
(Obs-2: a tabela oficial de usuários do sistema é a User (users) e a tabela legada (TB_Usuario) foi deprecada)

- 'TB_Advogado' => ['advogado', 'advogados'],
- 'TB_Defensoria' => ['defensoria', 'defensorias'],
- 'TB_Glossario' => ['glossario', 'glossarios'],
- 'TB_Localidade' => ['localidade', 'localidades'],
- 'TB_Parte' => ['parte', 'partes'],
- 'TB_Participante' => ['participante', 'participantes'],
- 'TB_Processo' => ['processo', 'processos'],
- 'TB_Procurador' => ['procurador', 'procuradores'],
- 'TB_Quilombo' => ['quilombo', 'quilombos'],
- 'TB_Repositorio' => ['repositorio', 'repositorios'],
- 'TB_Usuario' => ['tb_usuario', 'tb_usuarios', 'tb_user', 'tb_users'],
- 'User' => ['usuario', 'usuarios', 'user', 'users'],
- 'Acessos' => ['acesso', 'acesso'],
- 'Anexos' => ['anexos', 'anexo'],
- 'Audiencias' => ['audiencias',  'audiencia'],
- 'Classes' => ['classes',  'classe'],
- 'Customs' => ['customs',  'custom'],
- 'Movs' => ['movimentos',  'movimento', 'movs', 'mov'],
- 'Partes' => ['partes_jubsrasil', 'parte_jusbrasil'],
- 'Processo' => ['processos_jubsrasil', 'processos_jusbrasil'],
- 'Tribunais' => ['tribunais', 'tribunal'],

Essas tabelas abaixo estão protegidas, ou seja, precisam que o usuário da API seja Administrador para poder gerenciá-las via API (senão irão retornar um erro com o aviso citado):

- 'TB_Usuario',
- 'User',

Essas tabelas abaixo não são acessíveis via API (irão retornar um erro com o aviso citado):

- 'failed_jobs',
- 'migrations',
- 'password_reset_tokens',
- 'personal_access_tokens',
- 'users_verify'

Se você tentar acessar alguma tabela que não existe, o sistema irá retornar que a tabela não existe.

## Token Bearer

Para gerenciar os dados do sistema via API, o usuário precisa enviar como parametro das requisições o seu token bearer. Cada usuário tem um token bearer que é gerado a cada login na API.
Então, o fluxo para geração do token bearer é o seguinte:
Novo usuario: Registrar > verificar e-mail > Login > recebe o token bearer > utiliza as requisições com o token bearer > logout > perde o token bearer e precisa gerar um novo
Usuario existente: Login > recebe o token bearer > utiliza as requisições com o token bearer > logout > perde o token bearer e precisa gerar um novo
A cada geração de um novo token bearer, ele é salvo no usuario, no banco de dados.

## Endpoints API

Os endpoints, a partir de agora, respeitam o padrão REST, e são os seguintes, abaixo. Eles devem vir precedidos pelo prefixo '/api' e exigem o envio do Token Bearer como parametro:
(exemplo, para acessar o register, a chamada será: <IP>/api/register)

- Route::get('/{tabela}/{id}', [Controller::class, 'get']);
- Route::put('/{tabela}/{id}', [Controller::class, 'update']) => colunas da tabela deverão ser enviadas por parametro;
- Route::delete('/{tabela}/{id}', [Controller::class, 'delete']);
- Route::get('/{tabela}', [Controller::class, 'list']);
- Route::post('/{tabela}', [Controller::class, 'post']) => colunas da tabela deverão ser enviadas por parametro;

## Endpoints API sem Bearer

Os endpoints abaixo não exigem envio do Token Bearer para acessá-los:

- Route::post('/register', [AuthController::class, 'register']) => parametros são os campos da tabela usuario;
- Route::post('/login', [AuthController::class, 'login']) => parametros são e-mail e senha;
- Route::post('/logout', [AuthController::class, 'logout']) => parametro é e-mail ou id;
- Route::post('/forgot_password', [VerificationController::class, 'forgot_password']) => parametro é e-mail ou id;
- Route::post('/email/resend', 'App\Http\Controllers\VerificationController@resend')->name('verification.resend') => parametro é o e-mail ou id;

## Endpoints que retornam 405

Os endpoints abaixo irão retornar 'Método não implementado (405):

- Route::patch('/{tabela}', [Controller::class, 'patch']);
- Route::options('/{tabela}', [Controller::class, 'options']);

## Endpoints sem prefixo API:

Os endpoints abaixo não tem o prefixo '/api' e são os endopints dos links que são enviados por e-mail (onde são acessados via get). Também podem ser acessados via post:

Parametros para verificar e-mail é o token;
Parametros para trocar a senha são: token, nova senha e e-mail ou id;

- Route::get('/verify_email', 'App\Http\Controllers\VerificationController@get_verify')->name('verification.verify');
- Route::post('/verify_email_post', 'App\Http\Controllers\VerificationController@post_verify')->name('verification.verify_post');
- Route::get('/password_recovery', 'App\Http\Controllers\VerificationController@get_change_password')->name('password.recovery');
- Route::post('/password_recovery_post', 'App\Http\Controllers\VerificationController@post_change_password')->name('password.recovery_post');

## Enpoint '/':

É a página que abre quando é acessado o link da API no navegador. Não foi implementada, então é a 'default' do Laravel.

Route::get('/', function () {
    return view('welcome');
});

## Endpoints Legados

Os endpoints abaixo, legados e fora do padrão REST, foram implementados apenas para compatibilidade com o legado. Eles podem ser desativados, assim que julgarmos que não são mais necessários.
(Obs: nestes endpoints, o ID do item deve ser enviado como parâmetro na chamada)

- Route::get('/{tabela}/list', [Controller::class, 'list']);
- Route::get('/{tabela}/view', [Controller::class, 'get_legado']);
- Route::put('/{tabela}/update', [Controller::class, 'update_legado']); => além do id, colunas da tabela deverão ser enviadas por parametro;
- Route::delete('/{tabela}/delete', [Controller::class, 'delete_legado']) => além do id, colunas da tabela deverão ser enviadas por parametro;
- Route::post('/{tabela}/create', [Controller::class, 'post']);

## Views dos E-Mails enviados:

Os e-mails que são enviados pela API são montados por duas views no projeto, que são as seguintes, abaixo:
(Obs: elas precisam ser montadas, pois, no momento da publicação desse texto, elas estão enviando placeholder)
(Obs2: os links get estão retornando a mesma coisa que as rotas post: um retorno com json e o codigo http de status)

- /resources/views/email/emailRememberPasswordEmail.blade.php => que envia o código de recuperação de senha do e-mail
- /resources/views/email/emailVerificationEmail.blade.php => que envia o link de verificação do e-mail

## Laravel

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
