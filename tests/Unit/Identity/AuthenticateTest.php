<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Application\Authenticate;
use Identity\Application\InvalidCredentials;
use Identity\Domain\PlatformRole;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Identity\Support\FakePasswordHasher;
use Tests\Unit\Identity\Support\FakeTokenIssuer;
use Tests\Unit\Identity\Support\FakeUserRepository;

/** RF-003 — login. E o RF-008, que é o que ele NÃO pode revelar. */
final class AuthenticateTest extends TestCase
{
    public function test_autentica_e_devolve_o_par_de_tokens(): void
    {
        $sessao = $this->useCase(FakeUserRepository::com())
            ->handle('joao@shopmaster.test', 'senha-forte-123');

        $this->assertSame('access-1', $sessao->tokens->accessToken);
        $this->assertSame('refresh-1', $sessao->tokens->refreshToken);
        $this->assertSame(3600, $sessao->tokens->expiresIn);
    }

    public function test_devolve_quem_entrou(): void
    {
        $sessao = $this->useCase(FakeUserRepository::com())
            ->handle('joao@shopmaster.test', 'senha-forte-123');

        $this->assertSame('user-1', $sessao->user->id);
        $this->assertSame('joao@shopmaster.test', $sessao->user->email->value());
        $this->assertTrue($sessao->user->hasRole(PlatformRole::Buyer));
    }

    public function test_normaliza_o_email_antes_de_buscar(): void
    {
        $sessao = $this->useCase(FakeUserRepository::com())
            ->handle('  JOAO@ShopMaster.TEST  ', 'senha-forte-123');

        $this->assertSame('user-1', $sessao->user->id);
    }

    public function test_recusa_quando_o_email_nao_existe(): void
    {
        $this->expectException(InvalidCredentials::class);

        $this->useCase(FakeUserRepository::vazio())
            ->handle('ninguem@shopmaster.test', 'senha-forte-123');
    }

    public function test_recusa_quando_a_senha_esta_errada(): void
    {
        $this->expectException(InvalidCredentials::class);

        $this->useCase(FakeUserRepository::com())
            ->handle('joao@shopmaster.test', 'senha-errada-123');
    }

    public function test_a_mensagem_e_identica_para_email_inexistente_e_senha_errada(): void
    {
        // RF-008. Distinguir os dois entrega uma lista de e-mails cadastrados
        // a quem tentar — dá para varrer uma base inteira só olhando a
        // resposta.
        $semUsuario = $this->capturaMensagem(FakeUserRepository::vazio(), 'ninguem@shopmaster.test', 'senha-forte-123');
        $senhaErrada = $this->capturaMensagem(FakeUserRepository::com(), 'joao@shopmaster.test', 'outra-senha-123');

        $this->assertSame($semUsuario, $senhaErrada);
    }

    public function test_gasta_o_mesmo_tempo_quando_o_email_nao_existe(): void
    {
        // Mensagem igual não basta: se "e-mail inexistente" responde em
        // microssegundos e "senha errada" em ~100ms, o relógio conta o que a
        // mensagem cala. Por isso o verify() é chamado mesmo sem usuário.
        $hasher = new FakePasswordHasher;

        try {
            (new Authenticate(FakeUserRepository::vazio(), $hasher, new FakeTokenIssuer))
                ->handle('ninguem@shopmaster.test', 'senha-forte-123');
        } catch (InvalidCredentials) {
            // esperado
        }

        $this->assertSame(1, $hasher->verificacoes);
        $this->assertSame(1, $hasher->verificacoesSemUsuario);
    }

    public function test_email_malformado_vira_credencial_invalida(): void
    {
        // Não é InvalidEmailAddress: "esse e-mail nem é válido" também conta
        // como informação sobre a base para quem está sondando.
        $this->expectException(InvalidCredentials::class);

        $this->useCase(FakeUserRepository::com())->handle('joao-arroba', 'senha-forte-123');
    }

    public function test_nao_aplica_a_politica_de_senha_do_cadastro_no_login(): void
    {
        // Uma senha de 3 caracteres não é "fora da política" no login — é só
        // errada. Vazar InvalidPassword aqui contaria que a política mudou e
        // que a conta é antiga.
        $this->expectException(InvalidCredentials::class);

        $this->useCase(FakeUserRepository::com())->handle('joao@shopmaster.test', 'abc');
    }

    public function test_nao_emite_token_quando_a_credencial_e_invalida(): void
    {
        $emissor = new FakeTokenIssuer;

        try {
            (new Authenticate(FakeUserRepository::com(), new FakePasswordHasher, $emissor))
                ->handle('joao@shopmaster.test', 'senha-errada-123');
        } catch (InvalidCredentials) {
            // esperado
        }

        $this->assertSame(0, $emissor->emissoes);
    }

    private function useCase(FakeUserRepository $usuarios): Authenticate
    {
        return new Authenticate($usuarios, new FakePasswordHasher, new FakeTokenIssuer);
    }

    private function capturaMensagem(FakeUserRepository $usuarios, string $email, string $senha): string
    {
        try {
            $this->useCase($usuarios)->handle($email, $senha);
        } catch (InvalidCredentials $e) {
            return $e->getMessage();
        }

        $this->fail('Esperava InvalidCredentials.');
    }
}
