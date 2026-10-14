<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Application\DocumentAlreadyInUse;
use Identity\Application\DocumentAlreadySet;
use Identity\Application\ProfileNotFound;
use Identity\Application\UpdateMyProfile;
use Identity\Domain\DocumentType;
use Identity\Domain\InvalidDocument;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Identity\Support\FakeProfileRepository;

/** RF-005 — editar nome, telefone e documento. */
final class UpdateMyProfileTest extends TestCase
{
    private const CPF = '529.982.247-25';

    public function test_altera_o_nome(): void
    {
        $perfil = (new UpdateMyProfile($this->repositorio()))
            ->handle('user-1', name: 'Joao  Pedro   Rodrigues');

        $this->assertSame('Joao Pedro Rodrigues', $perfil->name->value());
    }

    public function test_altera_o_telefone_guardando_so_os_digitos(): void
    {
        $perfil = (new UpdateMyProfile($this->repositorio()))
            ->handle('user-1', phone: '(11) 98888-7777');

        $this->assertSame('11988887777', $perfil->phone?->value());
    }

    public function test_define_o_documento_com_o_tipo_informado(): void
    {
        $perfil = (new UpdateMyProfile($this->repositorio()))
            ->handle('user-1', document: self::CPF, documentType: 'cpf');

        $this->assertSame('52998224725', $perfil->document?->value);
        $this->assertSame(DocumentType::Cpf, $perfil->document?->type);
    }

    public function test_nao_toca_no_que_nao_foi_informado(): void
    {
        // A essência de um PATCH: mandar só o telefone não pode apagar o nome.
        $repositorio = $this->repositorio(phone: '2122223333');

        $perfil = (new UpdateMyProfile($repositorio))->handle('user-1', name: 'Outro Nome');

        $this->assertSame('Outro Nome', $perfil->name->value());
        $this->assertSame('2122223333', $perfil->phone?->value());
    }

    public function test_remove_o_telefone_quando_pedido_explicitamente(): void
    {
        $perfil = (new UpdateMyProfile($this->repositorio(phone: '11988887777')))
            ->handle('user-1', removePhone: true);

        $this->assertNull($perfil->phone);
    }

    public function test_remover_o_telefone_nao_toca_no_resto(): void
    {
        $perfil = (new UpdateMyProfile($this->repositorio(phone: '11988887777', document: '52998224725')))
            ->handle('user-1', removePhone: true);

        $this->assertSame('Joao Pedro', $perfil->name->value());
        $this->assertSame('52998224725', $perfil->document?->value);
    }

    public function test_pedido_de_remocao_conta_como_mudanca(): void
    {
        // `isEmpty()` precisa enxergar a remocao: sem isso o use case
        // retornaria cedo e o telefone continuaria la.
        $repositorio = $this->repositorio(phone: '11988887777');

        (new UpdateMyProfile($repositorio))->handle('user-1', removePhone: true);

        $this->assertSame(1, $repositorio->gravacoes);
    }

    public function test_recusa_alterar_documento_ja_definido(): void
    {
        // Decisão do projeto: define uma vez. Trocar CPF depois é o padrão de
        // quem está reaproveitando uma conta para outra identidade.
        $this->expectException(DocumentAlreadySet::class);

        (new UpdateMyProfile($this->repositorio(document: '11144477735')))
            ->handle('user-1', document: self::CPF, documentType: 'cpf');
    }

    public function test_reenviar_o_mesmo_documento_nao_e_erro(): void
    {
        // PATCH é idempotente: reenviar o formulário inteiro sem mexer no
        // documento não pode falhar.
        $perfil = (new UpdateMyProfile($this->repositorio(document: '52998224725')))
            ->handle('user-1', name: 'Novo Nome', document: self::CPF, documentType: 'cpf');

        $this->assertSame('Novo Nome', $perfil->name->value());
        $this->assertSame('52998224725', $perfil->document?->value);
    }

    public function test_recusa_documento_ja_usado_por_outra_conta(): void
    {
        $this->expectException(DocumentAlreadyInUse::class);

        (new UpdateMyProfile($this->repositorio(documentoDeOutro: true)))
            ->handle('user-1', document: self::CPF, documentType: 'cpf');
    }

    public function test_recusa_tipo_de_documento_desconhecido(): void
    {
        $this->expectException(InvalidDocument::class);

        (new UpdateMyProfile($this->repositorio()))
            ->handle('user-1', document: self::CPF, documentType: 'rg');
    }

    public function test_recusa_documento_sem_tipo(): void
    {
        $this->expectException(InvalidDocument::class);

        (new UpdateMyProfile($this->repositorio()))->handle('user-1', document: self::CPF);
    }

    public function test_recusa_cpf_invalido(): void
    {
        $this->expectException(InvalidDocument::class);

        (new UpdateMyProfile($this->repositorio()))
            ->handle('user-1', document: '529.982.247-26', documentType: 'cpf');
    }

    public function test_recusa_conta_inexistente(): void
    {
        $this->expectException(ProfileNotFound::class);

        (new UpdateMyProfile($this->repositorio()))->handle('nao-existe', name: 'Qualquer');
    }

    public function test_nao_grava_quando_nada_foi_informado(): void
    {
        $repositorio = $this->repositorio();

        $perfil = (new UpdateMyProfile($repositorio))->handle('user-1');

        $this->assertSame(0, $repositorio->gravacoes);
        $this->assertSame('Joao Pedro', $perfil->name->value());
    }

    public function test_valida_tudo_antes_de_gravar_qualquer_coisa(): void
    {
        // Nome válido + documento inválido não pode gravar o nome e depois
        // estourar: o PATCH é tudo ou nada.
        $repositorio = $this->repositorio();

        try {
            (new UpdateMyProfile($repositorio))
                ->handle('user-1', name: 'Novo Nome', document: '000', documentType: 'cpf');
        } catch (InvalidDocument) {
            // esperado
        }

        $this->assertSame(0, $repositorio->gravacoes);
    }

    private function repositorio(
        ?string $phone = null,
        ?string $document = null,
        bool $documentoDeOutro = false,
    ): FakeProfileRepository {
        return new FakeProfileRepository($phone, $document, $documentoDeOutro);
    }
}
