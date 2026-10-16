<!-- Reusable Confirmation Modal -->
<div id="rafloraConfirmModal" style="display:none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4" aria-modal="true" role="dialog" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeConfirmModal()"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[420px] p-6 transform transition-all text-center border border-slate-200">
        <!-- Close 'X' Button -->
        <button type="button" onclick="closeConfirmModal()" class="absolute top-4 right-4 rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" aria-label="Close modal">
            <i class="fa-solid fa-xmark text-base" aria-hidden="true"></i>
        </button>

        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-slate-50 mb-4" id="confirmModalIconContainer">
            <i id="confirmModalIcon" class="text-xl"></i>
        </div>
        <h3 id="confirmModalTitle" class="text-lg font-bold text-slate-900 mb-2">Confirm Action</h3>
        <p id="confirmModalMessage" class="text-sm text-slate-600 mb-6 leading-relaxed">Are you sure you want to proceed?</p>
        
        <div class="flex flex-col-reverse sm:flex-row gap-3">
            <button type="button" onclick="closeConfirmModal()" id="confirmModalCancelBtn" class="w-full sm:w-1/2 flex justify-center items-center px-4 py-2.5 border border-slate-300 text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-colors">
                Cancel
            </button>
            <button type="button" onclick="submitConfirmModal()" id="confirmModalSubmitBtn" class="w-full sm:w-1/2 flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white focus:outline-none focus:ring-2 transition-colors shadow-sm">
                Confirm
            </button>
        </div>
    </div>
</div>

<script>
    let currentConfirmFormId = null;
    let currentTriggerElement = null;

    /**
     * Opens the reusable confirmation modal.
     * @param {string} formId - ID of the form to submit upon confirmation
     * @param {string} title - Modal title text
     * @param {string} message - Modal body text
     * @param {string} confirmText - Text for the primary action button
     * @param {string} actionType - 'archive', 'restore', 'danger', or 'default' (controls colors/icons)
     * @param {HTMLElement} triggerElement - Element that opened the modal (to return focus)
     */
    function openConfirmModal(formId, title, message, confirmText, actionType = 'default', triggerElement = null) {
        currentConfirmFormId = formId;
        currentTriggerElement = triggerElement || document.activeElement;
        
        const modal = document.getElementById('rafloraConfirmModal');
        const titleEl = document.getElementById('confirmModalTitle');
        const msgEl = document.getElementById('confirmModalMessage');
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        const iconContainer = document.getElementById('confirmModalIconContainer');
        const iconEl = document.getElementById('confirmModalIcon');
        
        titleEl.textContent = title;
        msgEl.textContent = message;
        submitBtn.textContent = confirmText;
        
        // Reset classes
        submitBtn.className = "w-full sm:w-1/2 flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white focus:outline-none focus:ring-2 transition-colors shadow-sm";
        iconContainer.className = "mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-4";
        iconEl.className = "text-xl";

        if (actionType === 'archive' || actionType === 'danger') {
            submitBtn.classList.add('bg-rose-600', 'hover:bg-rose-700', 'focus:ring-rose-500');
            iconContainer.classList.add('bg-rose-50');
            iconEl.className = (actionType === 'archive' ? "fa-solid fa-box-archive" : "fa-solid fa-triangle-exclamation") + " text-rose-600 text-xl";
        } else if (actionType === 'restore') {
            submitBtn.classList.add('bg-emerald-700', 'hover:bg-emerald-800', 'focus:ring-emerald-500');
            iconContainer.classList.add('bg-emerald-50');
            iconEl.className = "fa-solid fa-rotate-left text-emerald-700 text-xl";
        } else {
            submitBtn.classList.add('bg-emerald-700', 'hover:bg-emerald-800', 'focus:ring-emerald-500');
            iconContainer.classList.add('bg-emerald-50');
            iconEl.className = "fa-solid fa-circle-question text-emerald-700 text-xl";
        }

        modal.style.display = 'flex';
        document.getElementById('confirmModalCancelBtn').focus();
    }

    function closeConfirmModal() {
        const modal = document.getElementById('rafloraConfirmModal');
        modal.style.display = 'none';
        currentConfirmFormId = null;
        if (currentTriggerElement && typeof currentTriggerElement.focus === 'function') {
            currentTriggerElement.focus();
            currentTriggerElement = null;
        }
    }

    function submitConfirmModal() {
        if (currentConfirmFormId) {
            const form = document.getElementById(currentConfirmFormId);
            if (form) {
                // One submission only: the page is about to navigate.
                document.getElementById('confirmModalSubmitBtn').disabled = true;
                form.submit();
            }
        }
    }

    /*
     * Declarative use, instead of the browser's confirm():
     *   <form method="POST" action="..." data-confirm="Verify this payment?"
     *         data-confirm-message="..." data-confirm-label="Verify payment" data-confirm-tone="default|danger|archive">
     * The form is submitted only after the dialog's confirm button is pressed.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        event.preventDefault();
        if (!form.id) form.id = 'confirm-form-' + Math.random().toString(36).slice(2, 10);
        // A named submit button's value would be lost by form.submit(), so it is carried over.
        const submitter = event.submitter;
        form.querySelectorAll('input[data-confirm-submitter]').forEach((input) => input.remove());
        if (submitter && submitter.name) {
            const carried = document.createElement('input');
            carried.type = 'hidden';
            carried.name = submitter.name;
            carried.value = submitter.value;
            carried.dataset.confirmSubmitter = '';
            form.appendChild(carried);
        }
        document.getElementById('confirmModalSubmitBtn').disabled = false;
        openConfirmModal(
            form.id,
            form.dataset.confirm,
            form.dataset.confirmMessage || 'Please confirm that you want to continue.',
            form.dataset.confirmLabel || 'Confirm',
            form.dataset.confirmTone || 'default',
            submitter || document.activeElement
        );
    });

    // Keyboard handling: Escape to close, Tab focus trapping
    document.addEventListener('keydown', (event) => {
        const modal = document.getElementById('rafloraConfirmModal');
        if (!modal || modal.style.display !== 'flex') return;

        if (event.key === 'Escape') {
            closeConfirmModal();
            return;
        }

        if (event.key === 'Tab') {
            const focusableElements = modal.querySelectorAll('button:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (event.shiftKey) {
                if (document.activeElement === firstElement) {
                    event.preventDefault();
                    lastElement.focus();
                }
            } else {
                if (document.activeElement === lastElement) {
                    event.preventDefault();
                    firstElement.focus();
                }
            }
        }
    });
</script>
