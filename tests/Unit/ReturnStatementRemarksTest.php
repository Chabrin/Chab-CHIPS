<?php

namespace Tests\Unit;

use App\Http\Controllers\ClientReturnsController;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ReturnStatementRemarksTest extends TestCase
{
    private $previousResolver;
    private $connection;
    private $controller;
    private array $updates = [];
    private string $lineReturn = 'R1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->connection = Mockery::mock(Connection::class . '[select,update]', [null, 'testing']);
        $this->connection->shouldReceive('select')->andReturnUsing(function ($sql, $bindings) {
            if (str_contains($sql, 'cb_returns_summary')) {
                return [(object) ['id' => 1, 'return_code' => 'R1', 'property_code' => 'P1', 'paid_status' => 1, 'approved_status' => 1]];
            }
            if (str_contains($sql, 'cb_returns_clients') && in_array($this->lineReturn, $bindings, true)) {
                return [(object) ['id' => 7, 'return_code' => 'R1', 'remarks' => 'Old note', 'net_amount' => '10000.00']];
            }
            return [];
        });
        $this->connection->shouldReceive('update')->andReturnUsing(function ($sql, $bindings) {
            $this->updates[] = [$sql, $bindings];
            return 1;
        });
        $resolver = new ConnectionResolver(['testing' => $this->connection]);
        $resolver->setDefaultConnection('testing');
        Model::setConnectionResolver($resolver);
        DB::shouldReceive('transaction')->andReturnUsing(fn($callback) => $callback());
        Auth::shouldReceive('id')->andReturn(123);
        $this->controller = Mockery::mock(ClientReturnsController::class)->makePartial();
        $this->controller->shouldReceive('checkGlobalRole')->with(123)->andReturn(true)->byDefault();
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver($this->previousResolver);
        parent::tearDown();
    }

    private function save(string $note = 'Updated note', string $expected = 'Old note')
    {
        return $this->controller->saveStatementRemarks(Request::create('/remarks', 'POST', [
            'remarks' => $note, 'expected_remarks' => $expected,
        ]), encrypt(1), encrypt(7));
    }

    public function test_saving_a_paid_return_note_updates_only_remarks(): void
    {
        $response = $this->save();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Updated note', $response->getData(true)['remarks']);
        $this->assertCount(1, $this->updates);
        $this->assertStringContainsString('"remarks" = ?', $this->updates[0][0]);
        $this->assertStringNotContainsString('net_amount', $this->updates[0][0]);
        $this->assertStringNotContainsString('paid_status', $this->updates[0][0]);
    }

    public function test_a_stale_note_is_not_overwritten(): void
    {
        $this->assertSame(409, $this->save('New note', 'Outdated note')->getStatusCode());
        $this->assertSame([], $this->updates);
    }

    public function test_a_line_from_a_different_return_cannot_be_updated(): void
    {
        $this->lineReturn = 'OTHER';
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->save();
    }

    public function test_the_database_note_length_is_validated(): void
    {
        $this->expectException(ValidationException::class);
        $this->save(str_repeat('x', 51));
    }

    public function test_users_without_property_access_cannot_save_notes(): void
    {
        $this->controller->shouldReceive('checkGlobalRole')->with(123)->andReturn(false);
        $builder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('exists')->andReturn(false);
        DB::shouldReceive('table')->with('sys_pm_properties as p')->andReturn($builder);
        try {
            $this->save();
            $this->fail('Expected access to be denied');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame([], $this->updates);
    }
}
