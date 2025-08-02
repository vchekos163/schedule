<?php

namespace App\View\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\Contracts\View\View;

class AddLessonModal extends Component
{
    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function render(): View|Closure|string
    {
        return view('components.add-lesson-modal');
    }
}
