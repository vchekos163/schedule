<div class="max-w-xl mx-auto py-6 px-4 bg-white shadow rounded">
    <form wire:submit.prevent="save">
        {{ $this->form }}

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
