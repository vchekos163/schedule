<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Student schedule: {{ $user->name }}</h1>

        <button
            class="grid-head-button"
            x-data
            @click="$dispatch('open-modal', { id: 'add-lesson-modal' })"
        >
            + Add Lesson
        </button>

    </div>

    <!-- Modal -->
    <div class="z-50 relative">
        <x-filament::modal id="add-lesson-modal" width="md">
            <x-slot name="heading">
                Add Lesson for {{ $user->name }}
            </x-slot>

            <form wire:submit.prevent="save" class="space-y-4 bg-white rounded-lg p-6">
                {{ $this->form }}
                <button type="submit" class="btn-submit">
                    Save
                </button>
            </form>
        </x-filament::modal>
    </div>
</div>
