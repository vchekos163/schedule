<div class="max-w-xl mx-auto py-6 px-4 bg-white shadow rounded">
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6">
            <h3 class="text-sm font-semibold mb-2">Availability</h3>
            <div class="flex gap-4 mb-2 text-xs">
                <div class="flex items-center gap-1"><span class="w-4 h-4 border bg-green-300"></span>Class</div>
                <div class="flex items-center gap-1"><span class="w-4 h-4 border bg-blue-300"></span>Online</div>
                <div class="flex items-center gap-1"><span class="w-4 h-4 border bg-yellow-300"></span>Hybrid</div>
                <div class="flex items-center gap-1"><span class="w-4 h-4 border bg-gray-200"></span>Unavailable</div>
            </div>
            @include('livewire.teachers.availability-grid')
        </div>

        <div class="mt-4 flex justify-end gap-4">
            <a href="/admin/teachers" class="btn-cancel">
                Cancel
            </a>
            <button type="submit" class="btn-submit">
                Save
            </button>
        </div>

    </form>
</div>
