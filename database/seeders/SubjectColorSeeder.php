<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            // MATEMATIKA
            'MATEMATIKA A' => '#3b82f6',
            'MATEMATIKA B+' => '#60a5fa',
            'MATEMATIKA B' => '#93c5fd',
            'MATEMATIKA P' => '#bfdbfe',

            // LIETUVIŲ KALBA
            'LIETUVIŲ KALBA A' => '#10b981',
            'LIETUVIŲ KALBA B+' => '#34d399',
            'LIETUVIŲ KALBA B' => '#6ee7b7',
            'LIETUVIŲ KALBA P' => '#a7f3d0',

            // ANGLŲ KALBA
            'ANGLŲ KALBA A' => '#eab308',
            'ANGLŲ KALBA B' => '#facc15',
            'ANGLŲ KALBA P' => '#fde047',

            // HSM
            'HSM A' => '#8b5cf6',
            'HSM B+' => '#a78bfa',
            'HSM B++' => '#c4b5fd',
            'HSM P' => '#ddd6fe',

            // GMT
            'GMT A' => '#f97316',
            'GMT B+' => '#fb923c',
            'GMT B++' => '#fdba74',
            'GMT P' => '#fed7aa',

            // VBE
            'LIETUVIU VBE A' => '#ef4444',
            'MATEMATIKE VBE A' => '#dc2626',
        ];

        foreach ($colors as $name => $color) {
            DB::table('subjects')
                ->where('name', $name)
                ->update(['color' => $color]);
        }
    }
}

