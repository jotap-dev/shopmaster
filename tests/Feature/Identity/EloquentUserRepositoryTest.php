<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\UserRepository;
use Identity\Domain\EmailAddress;
use Identity\Domain\HashedPassword;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O adapter contra o Postgres real. O que se prova aqui é o que o fake do
 * teste unitário não consegue provar: que a query e o mapeamento traduzem a
 * linha certo, e que a constraint do banco faz o que o contrato da porta diz.
 */
final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = $this->app->make(UserRepository::class);
    }

    public function test_grava_o_usuario_e_devolve_ele_com_id_e_data(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $registrado->id,
            'O id deveria ser um UUID versao 7.',
        );

        $linha = DB::table('users')->where('id', $registrado->id)->first();

        $this->assertSame('Joao Pedro', $linha->name);
        $this->assertSame('joao@shopmaster.test', $linha->email);
    }

    public function test_grava_o_papel_buyer_na_tabela_de_papeis(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $papeis = DB::table('user_platform_roles')
            ->where('user_id', $registrado->id)
            ->pluck('role')
            ->all();

        $this->assertSame(['buyer'], $papeis);
    }

    public function test_nao_grava_a_senha_em_texto_puro(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $hash = DB::table('users')->where('id', $registrado->id)->value('password_hash');

        $this->assertNotSame('senha-forte-123', $hash);
        $this->assertTrue(password_verify('senha-forte-123', $hash));
    }

    public function test_exists_by_email_encontra_quem_ja_esta_cadastrado(): void
    {
        $this->repositorio->add($this->novoUsuario());

        $this->assertTrue($this->repositorio->existsByEmail(EmailAddress::fromString('joao@shopmaster.test')));
        $this->assertFalse($this->repositorio->existsByEmail(EmailAddress::fromString('outra@shopmaster.test')));
    }

    public function test_exists_by_email_ignora_a_caixa_porque_a_coluna_e_citext(): void
    {
        // Gravado em minusculas pelo VO; a consulta vem de um caminho que nao
        // normalizou. E o `citext` que segura isto — nao ha LOWER() na query.
        DB::table('users')->insert([
            'id' => '01a07e4a-a020-73a1-bd0e-6bac810e4a44',
            'name' => 'Joao Pedro',
            'email' => 'joao@shopmaster.test',
            'password_hash' => 'irrelevante',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->repositorio->existsByEmail(
            EmailAddress::fromString('joao@shopmaster.test')
        ));

        $duplicado = DB::table('users')->where('email', 'JOAO@SHOPMASTER.TEST')->exists();
        $this->assertTrue($duplicado, 'A coluna citext deveria casar sem diferenciar caixa.');
    }

    public function test_a_constraint_do_banco_fecha_a_corrida_entre_verificacao_e_insert(): void
    {
        // Este é o cenário que o existsByEmail() do use case NÃO cobre: dois
        // cadastros simultâneos do mesmo e-mail passam os dois pela
        // verificação, e só a constraint UNIQUE impede a segunda linha.
        // O contrato da porta diz que isso vira EmailAlreadyRegistered.
        $this->repositorio->add($this->novoUsuario());

        $this->expectException(EmailAlreadyRegistered::class);

        $this->repositorio->add($this->novoUsuario());
    }

    public function test_a_corrida_e_barrada_mesmo_com_caixa_diferente(): void
    {
        $this->repositorio->add($this->novoUsuario());

        $this->expectException(EmailAlreadyRegistered::class);

        $this->repositorio->add($this->novoUsuario('JOAO@SHOPMASTER.TEST'));
    }

    public function test_nao_deixa_usuario_orfao_quando_a_gravacao_falha(): void
    {
        $this->repositorio->add($this->novoUsuario());

        try {
            $this->repositorio->add($this->novoUsuario());
        } catch (EmailAlreadyRegistered) {
            // esperado
        }

        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('user_platform_roles')->count());
    }

    private function novoUsuario(string $email = 'joao@shopmaster.test'): NewUser
    {
        return NewUser::register(
            PersonName::fromString('Joao Pedro'),
            EmailAddress::fromString($email),
            HashedPassword::fromHash(password_hash('senha-forte-123', PASSWORD_BCRYPT, ['cost' => 4])),
        );
    }
}
