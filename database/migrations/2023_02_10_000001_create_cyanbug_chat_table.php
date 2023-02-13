<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('cyanbug_chat')) {
            return;
        }
        Schema::create('cyanbug_chat', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable(false);
            $table->integer('weight')->nullable(false)->default(0);
            $table->string('trigger', 255)->nullable(false);
            $table->string('answer', 4000)->nullable(false);
            $table->boolean('reward')->nullable(false)->default(0);
            $table->bigInteger('reward_type')->nullable(true)->default(0);
            $table->integer('reward_interval')->nullable(true);
            $table->bigInteger('reward_amount')->nullable(true);
            $table->string('reward_warning', 255)->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cyanbug_chat');
    }
};