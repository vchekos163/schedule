<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Collection;

class SubjectsDropdown extends Component
{
    public Collection $subjects;

    public function __construct(Collection $subjects)
    {
        $this->subjects = $subjects;
    }

    public function render()
    {
        return view('components.subjects-dropdown');
    }
}

