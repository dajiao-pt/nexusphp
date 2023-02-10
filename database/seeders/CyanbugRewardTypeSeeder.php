<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CyanbugRewardTypeSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('cyanbug_reward_type')->delete();
        
        \DB::table('cyanbug_reward_type')->insert(array (
            0 => 
            array (
                'id' => 1,
                'type' => 'VIP',
            ),
            1 => 
            array (
                'id' => 2,
                'type' => '彩虹ID',
            ),
            2 => 
            array (
                'id' => 3,
                'type' => '魔力',
            ),
            3 => 
            array (
                'id' => 4,
                'type' => '上传',
            ),
            4 => 
            array (
                'id' => 5,
                'type' => '下载',
            ),
        ));
        
    }
}