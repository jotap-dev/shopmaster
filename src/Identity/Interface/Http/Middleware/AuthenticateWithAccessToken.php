<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Middleware;

use Closure;
use Identity\Application\AuthenticateAccessToken;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Domain\AuthenticatedUser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Shared\Http\ErrorResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * O middleware `auth.token`.
 *
 * Não toca no banco: identidade e papéis de plataforma vêm dos claims
 * assinados (RULE 7). O usuário resolvido é registrado no container da
 * requisição, e daí qualquer controller o recebe por injeção de tipo.
 *
 * `expired_token` é separado de `invalid_token` de propósito: o cliente
 * precisa saber quando basta usar o refresh e quando é caso de refazer o
 * login. E é informação que o portador do token já tem — o `exp` está no
 * payload, que ele pode ler.
 */
final readonly class AuthenticateWithAccessToken
{
    public function __construct(
        private AuthenticateAccessToken $authenticate,
        private Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cabecalho = (string) $request->header('Authorization', '');

        if (preg_match('/^Bearer\s+(\S+)$/i', $cabecalho, $partes) !== 1) {
            return $this->recusar('invalid_token', 'Token de acesso ausente ou malformado.');
        }

        try {
            $usuario = $this->authenticate->handle($partes[1]);
        } catch (ExpiredToken $e) {
            return $this->recusar('expired_token', $e->getMessage());
        } catch (InvalidToken $e) {
            return $this->recusar('invalid_token', $e->getMessage());
        }

        $this->container->instance(AuthenticatedUser::class, $usuario);

        return $next($request);
    }

    private function recusar(string $codigo, string $mensagem): Response
    {
        // O `WWW-Authenticate` é o que a RFC 9110 exige num 401 — é assim que
        // um cliente HTTP genérico descobre qual esquema a rota espera.
        return response()->json(ErrorResource::of($codigo, $mensagem), 401, [
            'WWW-Authenticate' => 'Bearer',
        ]);
    }
}
