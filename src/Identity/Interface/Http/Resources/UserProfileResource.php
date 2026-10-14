<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Resources;

use Identity\Domain\UserProfile;

/**
 * "Minha conta", como o **dono** dela a vê.
 *
 * O documento aparece por inteiro porque quem pede é o próprio titular, e ele
 * precisa conferir o que está gravado. Este Resource nunca pode ser
 * reaproveitado em rota de terceiro: CPF é dado sensível, e vitrine de loja,
 * listagem de pedido ou avaliação nunca devem trazê-lo (RULE 4).
 *
 * **Sem `roles`.** Papel é autorização, não perfil: quem responde "o que eu
 * posso fazer agora" é `GET /v1/auth/session`, que lê do token — a mesma
 * fonte que o middleware usa para decidir.
 */
final class UserProfileResource
{
    /** @return array<string, mixed> */
    public static function from(UserProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name->value(),
            'email' => $profile->email->value(),
            'phone' => $profile->phone?->value(),
            'phone_formatted' => $profile->phone?->formatted(),
            'document' => $profile->document === null ? null : [
                'type' => $profile->document->type->value,
                'value' => $profile->document->value,
            ],
        ];
    }
}
