(() => {
    let generatedFieldIndex = 0;

    const slugify = (value, fallback = 'field') => {
        const slug = String(value ?? '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return slug || fallback;
    };

    const nextGeneratedValue = (seed, fallback = 'field') => {
        generatedFieldIndex += 1;

        return `${slugify(seed, fallback)}-${generatedFieldIndex}`;
    };

    const ensureControlIdentity = (root) => {
        root.querySelectorAll('input, select, textarea').forEach((control) => {
            if (control instanceof HTMLInputElement && control.type === 'hidden') {
                return;
            }

            if (!control.name) {
                control.name = nextGeneratedValue(
                    control.id || control.getAttribute('aria-label') || control.getAttribute('placeholder') || control.tagName,
                    'control',
                );
            }

            if (!control.id) {
                control.id = nextGeneratedValue(control.name || control.getAttribute('aria-label'), 'control');
            }
        });
    };

    const repairLabels = (root) => {
        root.querySelectorAll('label[for]').forEach((label) => {
            const targetId = label.getAttribute('for');

            if (!targetId || root.querySelector(`#${CSS.escape(targetId)}`)) {
                return;
            }

            const fieldWrapper = label.closest('.fi-fo-field-wrp');
            const candidate = fieldWrapper?.querySelector(
                'input:not([type="hidden"]), select, textarea, button.fi-select-input-btn, button[aria-haspopup], [contenteditable="true"]',
            );

            if (!candidate) {
                label.removeAttribute('for');

                return;
            }

            if (!candidate.id) {
                candidate.id = targetId || nextGeneratedValue(label.textContent, 'control');
            }

            label.setAttribute('for', candidate.id);
        });

        root.querySelectorAll('label:not([for])').forEach((label) => {
            const fieldWrapper = label.closest('[data-field-wrapper], .fi-fo-field, .fi-sc-component');
            const candidate = fieldWrapper?.querySelector(
                'input:not([type="hidden"]), select, textarea, button.fi-select-input-btn, button[aria-haspopup], button, [contenteditable="true"], [role="textbox"], [role="combobox"]',
            );

            if (!candidate) {
                return;
            }

            if (!candidate.id) {
                candidate.id = nextGeneratedValue(label.textContent, 'control');
            }

            if (!label.id) {
                label.id = nextGeneratedValue(label.textContent, 'label');
            }

            label.setAttribute('for', candidate.id);
            candidate.setAttribute('aria-labelledby', label.id);
        });
    };

    const enhanceFormAccessibility = (root = document) => {
        ensureControlIdentity(root);
        repairLabels(root);
    };

    const scheduleEnhancements = () => {
        [0, 100, 300, 750, 1500, 2500].forEach((delay) => {
            window.setTimeout(() => {
                enhanceFormAccessibility(document);
            }, delay);
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        scheduleEnhancements();
    });

    document.addEventListener('livewire:navigated', () => {
        scheduleEnhancements();
    });

    const observer = new MutationObserver((mutations) => {
        const hasAddedElement = mutations.some((mutation) =>
            [...mutation.addedNodes].some((node) => node instanceof HTMLElement),
        );

        if (!hasAddedElement) {
            return;
        }

        enhanceFormAccessibility(document);
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
})();