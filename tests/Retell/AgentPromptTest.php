<?php

declare(strict_types=1);

namespace App\Tests\Retell;

use App\Retell\AgentPrompt;
use PHPUnit\Framework\TestCase;

/**
 * Las instrucciones del agente son parte del producto: si se pierde una de estas
 * reglas, el agente empieza a prometer cosas que el negocio no puede cumplir.
 */
class AgentPromptTest extends TestCase
{
    public function testLlevaElHuecoParaElNombreDelNegocio(): void
    {
        self::assertStringContainsString('{{nombre_negocio}}', AgentPrompt::PROMPT);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function reglasImprescindibles(): array
    {
        return [
            ['Llama a get_menu antes de hablar de productos'],
            ['Nunca te'],
            ['Espera a que confirmen'],
            ['Solo si create_order responde'],
            ['No prometas tiempos de preparación'],
            ['get_current_order'],
            ['No tomas datos de pago'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reglasImprescindibles')]
    public function testMantieneLasReglasQueNoSePuedenPerder(string $regla): void
    {
        self::assertStringContainsString($regla, AgentPrompt::PROMPT);
    }

    public function testDefineLasTresHerramientasConSuDireccionYToken(): void
    {
        $tools = AgentPrompt::toolDefinitions('https://auto-order.code-hive.space', 'token-secreto');

        self::assertSame(['get_menu', 'create_order', 'get_current_order'], array_column($tools, 'name'));

        foreach ($tools as $tool) {
            self::assertStringStartsWith('https://auto-order.code-hive.space/api/agent/', $tool['url']);
            self::assertSame('Bearer token-secreto', $tool['headers']['Authorization']);
        }
    }

    public function testCreateOrderExigeProductosYConfirmacion(): void
    {
        $tools = AgentPrompt::toolDefinitions('https://ejemplo', 'token');
        $createOrder = $tools[1];

        self::assertSame(['items', 'confirmado'], $createOrder['parameters']['required']);
        self::assertSame(['recoger', 'domicilio'], $createOrder['parameters']['properties']['modalidad']['enum']);
    }
}
