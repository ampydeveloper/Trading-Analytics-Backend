<?php

use Illuminate\Database\Seeder;
use App\Models\SportsQueue;

class SportsQueueTableSeeder extends Seeder
{
    use DisableForeignKeys, TruncateTable;

    /**
     * Run the database seeds.
     */
    public function run()
    {
        // $this->disableForeignKeys();
        $this->truncate('sports_queue');

        SportsQueue::create([
            'id' => '1',
            'sport' => 'Football'
        ]);
        SportsQueue::create([
            'id' => '2',
            'sport' => 'Baseball'
        ]);
        SportsQueue::create([
            'id' => '3',
            'sport' => 'Basketball'
        ]);
        SportsQueue::create([
            'id' => '4',
            'sport' => 'Soccer'
        ]);
        SportsQueue::create([
            'id' => '10',
            'sport' => 'Pokémon'
        ]);
        SportsQueue::create([
            'id' => '11',
            'sport' => 'Hockey'
        ]);

        // $this->call(SportsQueueTableSeeder::class);
    }
}
