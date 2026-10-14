<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Controllers;

use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\RegisterUser;
use Identity\Domain\InvalidEmailAddress;
use Identity\Domain\InvalidPassword;
use Identity\Domain\InvalidPersonName;
use Identity\Interface\Http\Requests\RegisterUserRequest;
use Identity\Interface\Http\Resources\RegisteredUserResource;
use Illuminate\Http\JsonResponse;
use Shared\Http\ErrorResource;

final class RegisterUserController
{
    public function store(RegisterUserRequest $request, RegisterUser $registerUser): JsonResponse
    {
        try {
            $usuario = $registerUser->handle(
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
                plainPassword: $request->string('password')->toString(),
            );
        } catch (EmailAlreadyRegistered $e) {
            return response()->json(ErrorResource::of('email_already_registered', $e->getMessage()), 422);
        } catch (InvalidPersonName $e) {
            return response()->json(ErrorResource::of('invalid_name', $e->getMessage()), 422);
        } catch (InvalidEmailAddress $e) {
            return response()->json(ErrorResource::of('invalid_email', $e->getMessage()), 422);
        } catch (InvalidPassword $e) {
            // Alcançável apesar do Form Request: as invariantes do domínio são
            // a última linha, não a única. Se outra rota vier a aceitar senha
            // e esquecer uma regra, o erro sai como 422 e não como 500.
            return response()->json(ErrorResource::of('invalid_password', $e->getMessage()), 422);
        }

        return response()->json(RegisteredUserResource::from($usuario), 201);
    }
}
