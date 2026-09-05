<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De planner en de wachtrij-worker vallen stil zonder dat er ergens iets rood
 * wordt: de agent doet dan gewoon niets meer. Het dashboard hoort dat te laten
 * zien voordat iemand zich afvraagt waarom er niet gebeld wordt.
 */
class SysteemstatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    private function alsBeheerder(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function zonder_levenstekens_ligt_de_planner_stil(): void
    {
        $this->alsBeheerder();
        $this->getJson('/api/admin/system-status')
            ->assertOk()
            ->assertJsonPath('scheduler.state', 'offline')
            // Zonder planner krijgt de worker geen werk, dus over de worker
            // valt dan niets te zeggen.
            ->assertJsonPath('worker.state', 'unknown')
            ->assertJsonPath('worker.last_seen_at', null);
    }

    #[Test]
    public function een_tik_zet_planner_en_worker_op_groen(): void
    {
        $this->alsBeheerder();
        $this->artisan('agent:tick')->assertSuccessful();

        $this->getJson('/api/admin/system-status')
            ->assertOk()
            ->assertJsonPath('scheduler.state', 'online')
            ->assertJsonPath('worker.state', 'online');
    }

    #[Test]
    public function een_stilgevallen_worker_valt_op_terwijl_de_planner_doorloopt(): void
    {
        $this->alsBeheerder();
        $this->artisan('agent:tick')->assertSuccessful();

        // Tien minuten verder meldt alleen de planner zich nog.
        Carbon::setTestNow(now()->addMinutes(10));
        app(SystemHeartbeat::class)->record('scheduler');

        $this->getJson('/api/admin/system-status')
            ->assertOk()
            ->assertJsonPath('scheduler.state', 'online')
            ->assertJsonPath('worker.state', 'offline');
    }

    #[Test]
    public function de_wachtrij_wordt_geteld(): void
    {
        $this->alsBeheerder();
        $this->getJson('/api/admin/system-status')
            ->assertOk()
            ->assertJsonPath('queue.pending', 0)
            ->assertJsonPath('queue.failed', 0);
    }

    #[Test]
    public function zonder_inloggen_geeft_de_status_niets_prijs(): void
    {
        $this->getJson('/api/admin/system-status')->assertUnauthorized();
    }
}
