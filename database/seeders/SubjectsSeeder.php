<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            'MATEMATIKA B', 'MATEMATIKA P', 'MATEMATIKA A', 'MATEMATIKA B+',
            'LIETUVIŲ KALBA B+', 'LIETUVIŲ KALBA B', 'LIETUVIŲ KALBA P', 'LIETUVIŲ KALBA A',
            'ANGLŲ KALBA B', 'ANGLŲ KALBA P', 'ANGLŲ KALBA A',
            'HSM B++', 'HSM B+', 'HSM P', 'HSM A',
            'GMT B+', 'GMT B++', 'GMT P', 'GMT A',
            'LIETUVIU VBE A', 'MATEMATIKE VBE A',
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->updateOrInsert(
                ['name' => $subject],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}

