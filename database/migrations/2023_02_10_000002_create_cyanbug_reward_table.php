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
        if (Schema::hasTable('cyanbug_reward')) {
            return;
        }
        Schema::create('cyanbug_reward', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('userid')->nullable(false);
            $table->bigInteger('chatid')->nullable(false);
            $table->integer('reward_date')->nullable(false);
            $table->bigInteger('reward_amount')->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cyanbug_reward');
    }
};