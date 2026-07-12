import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Scroll-reveal via IntersectionObserver.
 *
 * Adds `.is-revealed` to every `.reveal` element inside the layout as it
 * enters the viewport. Elements are revealed once and the observer detaches.
 * Respects prefers-reduced-motion (the CSS collapses the transition).
 */
export function useReveal() {
    let observer = null;

    onMounted(() => {
        const targets = document.querySelectorAll('.reveal');

        if (!('IntersectionObserver' in window)) {
            targets.forEach((el) => el.classList.add('is-revealed'));
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
        );

        targets.forEach((el) => observer.observe(el));
    });

    onBeforeUnmount(() => observer?.disconnect());
}
