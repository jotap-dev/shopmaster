<?php

declare(strict_types=1);

namespace Customer\Interface\Http\Controllers;

use Customer\Application\AddAddress;
use Customer\Application\AddressNotFound;
use Customer\Application\GetMyAddress;
use Customer\Application\ListMyAddresses;
use Customer\Application\RemoveAddress;
use Customer\Application\UpdateAddress;
use Customer\Domain\InvalidAddress;
use Customer\Domain\InvalidPostalCode;
use Customer\Interface\Http\Requests\StoreAddressRequest;
use Customer\Interface\Http\Requests\UpdateAddressRequest;
use Customer\Interface\Http\Resources\AddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Http\ErrorResource;

/**
 * RF-006 — agenda de endereços.
 *
 * O dono vem do atributo `auth.user_id` gravado pelo middleware `auth.token`
 * (Identity). Customer não importa `Identity\Domain` — a fronteira se cruza
 * só pelo id em string (RULE 1 / DependencyRuleTest).
 *
 * Endereço de outra pessoa devolve o mesmo 404 de "não existe".
 */
final class MyAddressController
{
    public function index(Request $request, ListMyAddresses $list): JsonResponse
    {
        return response()->json([
            'data' => AddressResource::collection($list->handle($this->userId($request))),
        ]);
    }

    public function show(string $addressId, Request $request, GetMyAddress $get): JsonResponse
    {
        try {
            $endereco = $get->handle($this->userId($request), $addressId);
        } catch (AddressNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        }

        return response()->json(AddressResource::from($endereco));
    }

    public function store(
        StoreAddressRequest $request,
        AddAddress $add,
    ): JsonResponse {
        try {
            $endereco = $add->handle(
                userId: $this->userId($request),
                recipientName: $request->string('recipient_name')->toString(),
                street: $request->string('street')->toString(),
                number: $request->string('number')->toString(),
                neighborhood: $request->string('neighborhood')->toString(),
                city: $request->string('city')->toString(),
                state: $request->string('state')->toString(),
                postalCode: $request->string('postal_code')->toString(),
                label: $request->has('label') ? $request->input('label') : null,
                complement: $request->has('complement') ? $request->input('complement') : null,
                makeDefault: $request->boolean('is_default'),
            );
        } catch (InvalidPostalCode $e) {
            return response()->json(ErrorResource::of('invalid_postal_code', $e->getMessage()), 422);
        } catch (InvalidAddress $e) {
            return response()->json(ErrorResource::of('invalid_address', $e->getMessage()), 422);
        }

        return response()->json(AddressResource::from($endereco), 201);
    }

    public function update(
        UpdateAddressRequest $request,
        string $addressId,
        UpdateAddress $update,
    ): JsonResponse {
        try {
            $endereco = $update->handle(
                userId: $this->userId($request),
                addressId: $addressId,
                label: $request->filled('label') ? $request->string('label')->toString() : null,
                clearLabel: $request->has('label') && ! $request->filled('label'),
                recipientName: $request->has('recipient_name') ? $request->string('recipient_name')->toString() : null,
                street: $request->has('street') ? $request->string('street')->toString() : null,
                number: $request->has('number') ? $request->string('number')->toString() : null,
                complement: $request->filled('complement') ? $request->string('complement')->toString() : null,
                clearComplement: $request->has('complement') && ! $request->filled('complement'),
                neighborhood: $request->has('neighborhood') ? $request->string('neighborhood')->toString() : null,
                city: $request->has('city') ? $request->string('city')->toString() : null,
                state: $request->has('state') ? $request->string('state')->toString() : null,
                postalCode: $request->has('postal_code') ? $request->string('postal_code')->toString() : null,
                makeDefault: $request->boolean('is_default'),
            );
        } catch (AddressNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        } catch (InvalidPostalCode $e) {
            return response()->json(ErrorResource::of('invalid_postal_code', $e->getMessage()), 422);
        } catch (InvalidAddress $e) {
            return response()->json(ErrorResource::of('invalid_address', $e->getMessage()), 422);
        }

        return response()->json(AddressResource::from($endereco));
    }

    public function destroy(
        string $addressId,
        Request $request,
        RemoveAddress $remove,
    ): JsonResponse {
        try {
            $remove->handle($this->userId($request), $addressId);
        } catch (AddressNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        }

        return response()->json(null, 204);
    }

    private function userId(Request $request): string
    {
        return (string) $request->attributes->get('auth.user_id');
    }
}
