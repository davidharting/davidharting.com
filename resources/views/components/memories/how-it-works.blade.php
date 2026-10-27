<div>
    <button
        type="button"
        class="btn btn-circle btn-outline border-base-300 font-sans"
        aria-label="How it works"
        aria-haspopup="dialog"
        onclick="document.getElementById('how-it-works').showModal()"
    >
        ?
    </button>

    <dialog id="how-it-works" class="modal modal-bottom" aria-labelledby="how-it-works-title">
        <div class="modal-box font-sans">
            <h2 id="how-it-works-title" class="font-serif text-2xl">How it works</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5">
                <li>Each month, you each write a memory of last month: words, photos, or both.</li>
                <li>It saves as you go. Nobody sees it until you share it.</li>
                <li>You'll read each other's once you've both shared, or at the end of the next month at the latest.</li>
            </ul>
            <form method="dialog" class="mt-6">
                <button class="btn btn-ghost btn-block">Close</button>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button tabindex="-1">Close</button>
        </form>
    </dialog>
</div>
