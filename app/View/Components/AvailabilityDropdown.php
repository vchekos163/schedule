<?php

namespace App\View\Components;

use Illuminate\View\Component;

class AvailabilityDropdown extends Component
{
    public array $availability;

    public function __construct(array $availability = [])
    {
        $this->availability = $availability;
    }

    public function render()
    {
        return view('components.availability-dropdown');
    }
}

