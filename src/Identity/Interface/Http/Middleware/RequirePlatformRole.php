<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Middleware;

use Closure;
use Identity\Domain\AuthenticatedUser;
use Identity\Domain\PlatformRole;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Shared\Http\ErrorResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `role:{papel}` — RF-007.
 *
 * Lê o papel **do token** (RULE 7), não do banco. Conceder ou revogar um
 * papel só passa a valer nesta guarda depois do próximo refresh (ou depois
 * que o access expirar e for renovado).
 *
 * Aceita um ou mais papéis — basta ter **um** deles. 403 e não 404: a rota
 * administrativa existe; o que falta é permissão.
 */
final readonly class RequirePlatformRole
{
    public function __construct(private Container $container) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var AuthenticatedUser $usuario */
        $usuario = $this->container->make(AuthenticatedUser::class);

        foreach ($this->exigidos($roles) as $papel) {
            $conhecido = PlatformRole::tryFrom($papel);

            if ($conhecido !== null && $usuario->hasRole($conhecido)) {
                return $next($request);
            }
        }

        return response()->json(
            ErrorResource::of('forbidden', 'Voce nao tem permissao para esta acao.'),
            403,
        );
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function exigidos(array $roles): array
    {
        $exigidos = [];

        foreach ($roles as $papel) {
            foreach (explode(',', $papel) as $parte) {
                $parte = trim($parte);

                if ($parte !== '') {
                    $exigidos[] = $parte;
                }
            }
        }

        return $exigidos;
    }
}
