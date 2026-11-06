<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/**
 * For tests that render a whole page: the real layout builds the signed-in user's menu, notifications and more,
 * and these pages only need somewhere to put their content.
 */
trait RendersPagesWithPlainLayout
{
    private string $plainLayoutFolder;

    protected function usePlainLayout(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        // The layout's composer still reads the signed-in user's menu, so give it empty menu tables
        Schema::create('cb_role_user', function (Blueprint $table) { $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('role_id'); });
        Schema::create('cb_modules', function (Blueprint $table) { $table->id(); $table->integer('module_order')->nullable(); });
        Schema::create('cb_super_modules', function (Blueprint $table) { $table->id(); $table->integer('order')->nullable(); });
        Schema::create('cb_sub_modules', function (Blueprint $table) { $table->id(); $table->integer('order')->nullable(); $table->integer('module_id')->nullable(); });
        Schema::create('cb_sub_module_permissions', function (Blueprint $table) { $table->unsignedBigInteger('sub_module_id'); $table->unsignedBigInteger('role_id'); });

        $this->plainLayoutFolder = sys_get_temp_dir() . '/plain-layout-' . uniqid();
        File::ensureDirectoryExists($this->plainLayoutFolder . '/layouts');
        File::put($this->plainLayoutFolder . '/layouts/main.blade.php', "@yield('content')");
        View::getFinder()->prependLocation($this->plainLayoutFolder);
        View::share('errors', new ViewErrorBag());
    }

    protected function removePlainLayout(): void
    {
        File::deleteDirectory($this->plainLayoutFolder);
    }
}
