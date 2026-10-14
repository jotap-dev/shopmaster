<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\EmailAddress;
use Identity\Domain\InvalidEmailAddress;
use Identity\Domain\InvalidPassword;
use Identity\Domain\InvalidPersonName;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Identity\Domain\PlainPassword;
use Identity\Domain\RegisteredUser;

/**
 * RF-001 — cadastro de usuário.
 *
 * O papel inicial não é decidido aqui: quem diz que todo mundo nasce
 * comprador é `NewUser::register()`, no domínio.
 */
final class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    /**
     * @throws InvalidPersonName
     * @throws InvalidEmailAddress
     * @throws InvalidPassword
     * @throws EmailAlreadyRegistered
     */
    public function handle(string $name, string $email, string $plainPassword): RegisteredUser
    {
        // Toda validação de entrada primeiro, antes de qualquer I/O: entrada
        // inválida não merece uma ida ao banco, e o erro sai igual em qualquer
        // ordem que os campos venham errados.
        $nome = PersonName::fromString($name);
        $endereco = EmailAddress::fromString($email);
        $senha = PlainPassword::fromString($plainPassword);

        // Cortesia, não garantia — a corrida entre este SELECT e o INSERT é
        // fechada pela constraint UNIQUE, e o repositório traduz a violação
        // para a mesma exceção. Aqui o ganho é a mensagem clara e não gastar
        // os ~100ms do bcrypt à toa numa rota pública.
        //
        // Não há vazamento de informação nisso: a resposta 422 já diz que o
        // e-mail está tomado, por exigência do RF-002. É o oposto do login,
        // onde revelar a existência da conta seria entregar uma lista de
        // e-mails cadastrados (RF-008).
        if ($this->users->existsByEmail($endereco)) {
            throw EmailAlreadyRegistered::for($endereco);
        }

        return $this->users->add(
            NewUser::register($nome, $endereco, $this->hasher->hash($senha))
        );
    }
}
