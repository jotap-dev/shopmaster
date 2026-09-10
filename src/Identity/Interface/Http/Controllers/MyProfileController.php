<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Controllers;

use Identity\Application\DocumentAlreadyInUse;
use Identity\Application\DocumentAlreadySet;
use Identity\Application\GetMyProfile;
use Identity\Application\ProfileNotFound;
use Identity\Application\UpdateMyProfile;
use Identity\Domain\AuthenticatedUser;
use Identity\Domain\InvalidDocument;
use Identity\Domain\InvalidPersonName;
use Identity\Domain\InvalidPhoneNumber;
use Identity\Interface\Http\Requests\UpdateProfileRequest;
use Identity\Interface\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Shared\Http\ErrorResource;

/**
 * RF-005 — "minha conta".
 *
 * O id sempre vem do **token**, nunca do corpo da requisição: não há como
 * apontar para a conta de outra pessoa, porque o parâmetro não existe.
 */
final class MyProfileController
{
    public function show(AuthenticatedUser $user, GetMyProfile $getProfile): JsonResponse
    {
        try {
            $perfil = $getProfile->handle($user->id);
        } catch (ProfileNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        }

        return response()->json(UserProfileResource::from($perfil));
    }

    public function update(
        UpdateProfileRequest $request,
        AuthenticatedUser $user,
        UpdateMyProfile $updateProfile,
    ): JsonResponse {
        try {
            $perfil = $updateProfile->handle(
                userId: $user->id,
                name: $request->has('name') ? $request->string('name')->toString() : null,

                // Três estados, não dois: ausente (não mexe), com valor
                // (troca) e presente-mas-nulo (remove). `has()` distingue o
                // primeiro dos outros dois; o valor distingue os outros dois.
                phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
                removePhone: $request->has('phone') && ! $request->filled('phone'),
                document: $request->has('document') ? $request->string('document')->toString() : null,
                documentType: $request->has('document_type') ? $request->string('document_type')->toString() : null,
            );
        } catch (ProfileNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        } catch (DocumentAlreadySet $e) {
            // 409 e não 422: a requisição está bem formada e o documento é
            // válido — o que impede é o estado atual da conta.
            return response()->json(ErrorResource::of('document_already_set', $e->getMessage()), 409);
        } catch (DocumentAlreadyInUse $e) {
            return response()->json(ErrorResource::of('document_already_in_use', $e->getMessage()), 422);
        } catch (InvalidDocument $e) {
            return response()->json(ErrorResource::of('invalid_document', $e->getMessage()), 422);
        } catch (InvalidPhoneNumber $e) {
            return response()->json(ErrorResource::of('invalid_phone', $e->getMessage()), 422);
        } catch (InvalidPersonName $e) {
            return response()->json(ErrorResource::of('invalid_name', $e->getMessage()), 422);
        }

        return response()->json(UserProfileResource::from($perfil));
    }
}
