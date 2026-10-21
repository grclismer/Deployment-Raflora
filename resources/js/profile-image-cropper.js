const modal = document.querySelector('[data-profile-crop-modal]');

if (modal) {
    const canvas = modal.querySelector('[data-profile-crop-canvas]');
    const context = canvas.getContext('2d');
    const zoomInput = modal.querySelector('[data-profile-crop-zoom]');
    const errorMessage = modal.querySelector('[data-profile-crop-error]');
    const applyButton = modal.querySelector('[data-profile-crop-apply]');
    const cancelButtons = modal.querySelectorAll('[data-profile-crop-cancel]');
    const acceptedFiles = new WeakMap();
    let activeInput = null;
    let image = null;
    let imageUrl = null;
    let previewUrl = null;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragOrigin = null;

    function imageBounds() {
        const scale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight) * zoom;
        const width = image.naturalWidth * scale;
        const height = image.naturalHeight * scale;
        const maxX = Math.max(0, (width - canvas.width) / 2);
        const maxY = Math.max(0, (height - canvas.height) / 2);

        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));

        return { width, height };
    }

    function drawImage() {
        if (!image) return;

        const { width, height } = imageBounds();
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(
            image,
            (canvas.width - width) / 2 + offsetX,
            (canvas.height - height) / 2 + offsetY,
            width,
            height,
        );
    }

    function restoreSelection(input) {
        const previousFile = acceptedFiles.get(input);
        if (previousFile) {
            const transfer = new DataTransfer();
            transfer.items.add(previousFile);
            input.files = transfer.files;
        } else {
            input.value = '';
        }
    }

    function closeCropper(clearSelection) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = null;
        image = null;
        dragOrigin = null;

        if (clearSelection && activeInput) restoreSelection(activeInput);
        const inputToFocus = activeInput;
        activeInput = null;
        inputToFocus?.focus();
    }

    function showInputError(input, message) {
        const error = input.parentElement?.querySelector('[data-profile-image-input-error]');
        if (error) error.textContent = message;
    }

    document.querySelectorAll('input[type="file"][data-profile-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;

            errorMessage.textContent = '';
            showInputError(input, '');
            if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                showInputError(input, 'Choose a JPG, PNG, or WEBP image no larger than 2 MB.');
                restoreSelection(input);
                return;
            }

            activeInput = input;
            imageUrl = URL.createObjectURL(file);
            image = new Image();
            image.onload = () => {
                zoom = 1;
                offsetX = 0;
                offsetY = 0;
                zoomInput.value = '1';
                drawImage();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                modal.querySelector('[data-profile-crop-cancel]').focus();
            };
            image.onerror = () => {
                showInputError(activeInput, 'This image could not be opened. Choose another photo.');
                closeCropper(true);
            };
            image.src = imageUrl;
        });
    });

    zoomInput.addEventListener('input', () => {
        zoom = Number(zoomInput.value);
        drawImage();
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (!image) return;
        dragOrigin = { x: event.clientX, y: event.clientY, offsetX, offsetY };
        canvas.setPointerCapture(event.pointerId);
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!dragOrigin) return;
        const rect = canvas.getBoundingClientRect();
        offsetX = dragOrigin.offsetX + (event.clientX - dragOrigin.x) * canvas.width / rect.width;
        offsetY = dragOrigin.offsetY + (event.clientY - dragOrigin.y) * canvas.height / rect.height;
        drawImage();
    });

    canvas.addEventListener('pointerup', () => { dragOrigin = null; });
    canvas.addEventListener('pointercancel', () => { dragOrigin = null; });

    applyButton.addEventListener('click', () => {
        if (!activeInput) return;

        applyButton.disabled = true;
        errorMessage.textContent = '';
        canvas.toBlob((blob) => {
            try {
                if (!blob) throw new Error('The cropped photo could not be created.');
                const croppedFile = new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                activeInput.files = transfer.files;
                acceptedFiles.set(activeInput, croppedFile);
                const removeImageInput = activeInput.form?.querySelector('[name="remove_profile_image"]');
                if (removeImageInput) removeImageInput.value = '0';
                showInputError(activeInput, '');

                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(blob);
                document.querySelectorAll('[data-profile-image-preview]').forEach((preview) => {
                    preview.src = previewUrl;
                    preview.classList.remove('hidden');
                });
                document.querySelectorAll('[data-profile-image-fallback]').forEach((fallback) => {
                    fallback.classList.add('hidden');
                });
                closeCropper(false);
            } catch (error) {
                errorMessage.textContent = error instanceof Error ? error.message : 'The cropped photo could not be saved.';
            } finally {
                applyButton.disabled = false;
            }
        }, 'image/jpeg', 0.9);
    });

    cancelButtons.forEach((button) => button.addEventListener('click', () => closeCropper(true)));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeCropper(true);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeCropper(true);
    });
}
