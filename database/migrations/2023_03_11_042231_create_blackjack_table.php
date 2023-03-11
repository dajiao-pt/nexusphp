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
        Schema::create('blackjack', function (Blueprint $table) {
            $table->id('userid');
            $table->integer('points');
            $table->enum('status', ['playing', 'waiting'])->default('playing');
            $table->text('cards');
            $table->integer('date')->default(0);
            $table->enum('gameover', ['yes', 'no'])->default('no');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blackjack');
    }
};
