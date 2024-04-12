<h1>Seu Código de Recuperação de Senha</h1>

Seu código de recuperação de senha é {{ $token }}

Você pode usar o seguinte link para criar uma nova senha:
<a href="{{ route('password.recovery', $token_) }}">CRIE UMA NOVA SENHA</a>
