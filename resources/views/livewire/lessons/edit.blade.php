<div class="z-50 relative">
    <x-filament::modal id="add-lesson-modal" width="md">
        <x-slot name="heading">
            {{ $editingLessonId ? 'Edit Lesson' : 'Add Lesson' }} for {{ $user->name }}
        </x-slot>

        <form wire:submit.prevent="save" class="space-y-4 bg-white rounded-lg p-6">
            {{ $this->form }}
            <button type="submit" class="btn-submit">
                Save
            </button>
        </form>
    </x-filament::modal>
</div>
