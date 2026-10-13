<?php

namespace Tests\Feature;

use App\Services\Mikrotik\MikrotikConnectionService;
use ReflectionClass;
use Tests\TestCase;

class MikrotikPrintEndpointAllowlistTest extends TestCase
{
    public function test_bgp_instance_print_is_allowed_as_a_read_only_endpoint(): void
    {
        $allowedEndpoints = (new ReflectionClass(MikrotikConnectionService::class))
            ->getConstant('PRINT_ENDPOINTS');

        $this->assertContains('/routing/bgp/instance/print', $allowedEndpoints);
    }

    public function test_bgp_write_endpoints_remain_rejected_by_print_allowlist(): void
    {
        $allowedEndpoints = (new ReflectionClass(MikrotikConnectionService::class))
            ->getConstant('PRINT_ENDPOINTS');

        $this->assertNotContains('/routing/bgp/peer/add', $allowedEndpoints);
        $this->assertNotContains('/routing/bgp/peer/set', $allowedEndpoints);
        $this->assertNotContains('/routing/bgp/peer/remove', $allowedEndpoints);
    }
}
